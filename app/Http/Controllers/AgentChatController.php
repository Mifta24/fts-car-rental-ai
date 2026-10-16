<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Rental;
use App\Services\Agent\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentChatController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly AgentService $agent) {}

    public function start(Request $request, string $rentalSlug): JsonResponse
    {
        $rental = $this->publishedRental($rentalSlug);

        $locale = in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $rental->default_locale;

        $conversation = $this->agent->startConversation($rental, $locale);

        return response()->json([
            'customer_token' => $conversation->customer_token,
            'locale' => $conversation->locale,
        ]);
    }

    public function message(Request $request, string $rentalSlug): JsonResponse
    {
        $rental = $this->publishedRental($rentalSlug);

        $data = $request->validate([
            'customer_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'scene' => ['nullable', Rule::in(Conversation::SCENES)],
            'selected_vehicle' => ['nullable', 'string', 'max:120'],
            'selected_service' => ['nullable', 'integer', 'min:1'],
            'reservation' => ['nullable', 'array'],
            'reservation.pickup_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.return_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.passengers' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reservation.units' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reservation.vehicle_slug' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->findConversation($rental, $data['customer_token']);

        $this->agent->rememberContext($rental, $conversation, [
            'scene' => $data['scene'] ?? null,
            'selected_service' => $data['selected_service'] ?? null,
            'selected_vehicle' => $data['selected_vehicle'] ?? ($data['reservation']['vehicle_slug'] ?? null),
            ...array_key_exists('reservation', $data) ? ['reservation' => array_filter($data['reservation'] ?? [], fn ($value) => $value !== null && $value !== '')] : [],
        ]);

        if ($conversation->currentReplyStatus() === Conversation::REPLY_PROCESSING) {
            return response()->json([
                'error' => 'reply_in_progress',
                'message' => 'The AI Rental Agent is still answering your last message.',
            ], 409);
        }

        $lastMessageId = (int) $conversation->messages()->reorder()->max('id');

        $assistantMessage = $this->agent->submit($conversation, $data['message']);

        if (! $assistantMessage) {
            $conversation->refresh();

            // The queue may already have finished (or failed) by the time we get here.
            $assistantMessage = match ($conversation->currentReplyStatus()) {
                Conversation::REPLY_PROCESSING => null,
                Conversation::REPLY_FAILED => false,
                default => $conversation->messages()->where('id', '>', $lastMessageId)->where('role', ConversationMessage::ROLE_ASSISTANT)->first() ?? false,
            };

            if ($assistantMessage === null) {
                return response()->json(['pending' => true, 'status' => $conversation->status], 202);
            }

            if ($assistantMessage === false) {
                return response()->json([
                    'error' => 'agent_unavailable',
                    'message' => 'The AI Rental Agent is temporarily unavailable. Please try again in a moment.',
                ], 503);
            }
        }

        return response()->json([
            'message' => $this->formatMessage($assistantMessage),
            'status' => $conversation->fresh()->status,
        ]);
    }

    public function history(Request $request, string $rentalSlug): JsonResponse
    {
        $rental = $this->publishedRental($rentalSlug);

        $data = $request->validate(['customer_token' => ['required', 'uuid']]);

        $conversation = $this->findConversation($rental, $data['customer_token']);

        $messages = $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_CUSTOMER, ConversationMessage::ROLE_ASSISTANT, ConversationMessage::ROLE_SYSTEM])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $m) => $this->formatMessage($m))
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $conversation->status,
            'reply_status' => $conversation->currentReplyStatus() ?? 'idle',
        ]);
    }

    private function publishedRental(string $rentalSlug): Rental
    {
        $rental = Rental::where('slug', $rentalSlug)->first();

        abort_if(! $rental || ! $rental->isPublished(), 404);

        return $rental;
    }

    private function findConversation(Rental $rental, string $customerToken): Conversation
    {
        $conversation = Conversation::where('rental_id', $rental->id)
            ->where('customer_token', $customerToken)
            ->first();

        if (! $conversation) {
            throw ValidationException::withMessages([
                'customer_token' => 'This conversation no longer exists. Please start a new one.',
            ]);
        }

        return $conversation;
    }

    private function formatMessage(ConversationMessage $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
            'ui_payload' => $message->ui_payload,
            'suggested_actions' => $this->suggestedActions($message->ui_payload),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Interface actions that follow from what the agent just showed, so
     * the customer can step into the matching scene instead of typing again.
     *
     * @param  list<array<string, mixed>>|null  $uiPayload
     * @return list<array{action: string, vehicle?: string}>
     */
    private function suggestedActions(?array $uiPayload): array
    {
        $actions = [];

        foreach ($uiPayload ?? [] as $payload) {
            $type = $payload['type'] ?? null;
            $vehicle = $payload['vehicle']['vehicle_slug'] ?? $payload['quote']['vehicle_slug'] ?? null;

            if ($type === 'vehicle_detail' && $vehicle) {
                $actions[] = ['action' => 'view_vehicle', 'vehicle' => $vehicle];
                $actions[] = ['action' => 'reserve', 'vehicle' => $vehicle];
            } elseif ($type === 'availability' && ($payload['available'] ?? false) && $vehicle) {
                $actions[] = ['action' => 'reserve', 'vehicle' => $vehicle];
            } elseif ($type === 'handover') {
                $actions[] = ['action' => 'staff'];
            }
        }

        return collect($actions)->unique(fn (array $action) => $action['action'].($action['vehicle'] ?? ''))->values()->all();
    }
}
