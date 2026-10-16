<?php

namespace App\Services\Agent;

use App\Jobs\GenerateAgentReply;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Rental;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates one customer turn against a self-hosted, OpenAI-compatible chat
 * endpoint (LM Studio / Ollama over Tailscale) — runs the tool-use loop
 * against a single rental's RentalAgentTools, persists the conversation,
 * and returns the assistant message (with any UI payload to render).
 *
 * The local model is a "thinking" model, so every request sends
 * reasoning_effort=none to skip the long reasoning pass (it roughly halves
 * the latency). max_tokens stays generous (see MAX_TOKENS) in case a server
 * ignores that and the model still reasons before it emits a tool call.
 */
class AgentService
{
    private const MAX_TOOL_ITERATIONS = 6;

    private const MAX_TOKENS = 4096;

    private const REQUEST_TIMEOUT_SECONDS = 120;

    /**
     * Cloudflare drops a request that is still unanswered after 100s, so a
     * whole customer turn (every tool round trip included) has to fit inside this.
     */
    private const REPLY_BUDGET_SECONDS = 85;

    private const UNBACKED_DATA_PATTERN = '/(?:rp\.?|idr|usd|jpy|¥|\$)\s?\d|\d[\d.,]*\s?(?:rb|ribu|juta|jt)\b|\[[^\]]+\]/iu';

    public function __construct(
        private readonly ContentGuard $contentGuard,
        private readonly AgentPrompt $prompt,
    ) {}

    public function startConversation(Rental $rental, string $locale = 'id'): Conversation
    {
        return Conversation::create([
            'rental_id' => $rental->id,
            'customer_token' => (string) Str::uuid(),
            'locale' => $locale,
            'status' => Conversation::STATUS_ACTIVE,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Remembers where in the UI the customer is, so the agent can answer for
     * "this vehicle" or the reservation they are filling in. Only values that
     * exist for this rental are kept; nothing personal is stored here.
     *
     * @param  array{scene?: ?string, selected_vehicle?: ?string, selected_service?: ?int, reservation?: ?array<string, mixed>}  $context
     */
    public function rememberContext(Rental $rental, Conversation $conversation, array $context): void
    {
        $updates = [];

        if (in_array($context['scene'] ?? null, Conversation::SCENES, true)) {
            $updates['current_scene'] = $context['scene'];
        }

        if (filled($context['selected_vehicle'] ?? null)) {
            $vehicle = $rental->vehicles()->where('is_active', true)->where('slug', $context['selected_vehicle'])->first();

            if ($vehicle) {
                $updates['selected_vehicle_id'] = $vehicle->id;
            }
        }

        if (filled($context['selected_service'] ?? null)) {
            $service = $rental->knowledgeItems()->where('is_active', true)->whereKey($context['selected_service'])->first();

            if ($service) {
                $updates['selected_service_id'] = $service->id;
            }
        }

        if (array_key_exists('reservation', $context)) {
            $updates['reservation_state'] = $context['reservation'] ?: null;
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }
    }

    /**
     * Answers one customer message right now, waiting for the model. Used where
     * there is no page to poll, such as the terminal chat.
     */
    public function reply(Rental $rental, Conversation $conversation, string $customerMessage): ConversationMessage
    {
        $customerRecord = $this->recordCustomerMessage($conversation, $customerMessage);

        return $this->instantReply($conversation, $customerRecord) ?? $this->respond($rental, $conversation, $customerRecord);
    }

    /**
     * Takes one customer message from the web. Replies that need no model (the
     * conversation is with staff, or the guard refuses) are returned at once;
     * otherwise the reply is queued and null is returned.
     */
    public function submit(Conversation $conversation, string $customerMessage): ?ConversationMessage
    {
        $customerRecord = $this->recordCustomerMessage($conversation, $customerMessage);

        $instant = $this->instantReply($conversation, $customerRecord);

        if ($instant) {
            return $instant;
        }

        $conversation->update(['reply_status' => Conversation::REPLY_PROCESSING, 'reply_started_at' => now()]);

        GenerateAgentReply::dispatch($conversation->id, $customerRecord->id);

        return null;
    }

    /**
     * Runs a queued turn to the end and records how it went for the page to read.
     */
    public function generateQueuedReply(Conversation $conversation, ConversationMessage $customerRecord): void
    {
        try {
            $this->respond($conversation->rental, $conversation, $customerRecord);
        } catch (\Throwable $e) {
            Log::error('Agent reply failed', ['rental_id' => $conversation->rental_id, 'error' => $e->getMessage()]);
            $this->markReplyFailed($conversation, $customerRecord->id);

            return;
        }

        $conversation->update(['reply_status' => null, 'reply_started_at' => null]);
    }

    /**
     * The customer keeps the text on screen and can retry; leaving it stored would duplicate it.
     */
    public function markReplyFailed(Conversation $conversation, int $customerMessageId): void
    {
        ConversationMessage::whereKey($customerMessageId)->delete();

        $conversation->update(['reply_status' => Conversation::REPLY_FAILED]);
    }

    private function recordCustomerMessage(Conversation $conversation, string $customerMessage): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_CUSTOMER,
            'content' => $customerMessage,
        ]);
    }

    /**
     * The reply that needs no model, if there is one for this message.
     */
    private function instantReply(Conversation $conversation, ConversationMessage $customerRecord): ?ConversationMessage
    {
        if ($conversation->isHandedOver()) {
            return $conversation->messages()->create([
                'role' => ConversationMessage::ROLE_SYSTEM,
                'content' => 'This conversation is with rental staff now. A team member will respond shortly.',
            ]);
        }

        if ($this->contentGuard->isOffensive($customerRecord->content)) {
            return $this->refuse($conversation, $customerRecord->content);
        }

        return null;
    }

    private function respond(Rental $rental, Conversation $conversation, ConversationMessage $customerRecord): ConversationMessage
    {
        try {
            return $this->answer($rental, $conversation);
        } catch (\Throwable $e) {
            $customerRecord->delete();

            throw $e;
        }
    }

    /**
     * Answers with the fixed refusal instead of asking the model.
     */
    private function refuse(Conversation $conversation, string $customerMessage): ConversationMessage
    {
        $conversation->update(['last_message_at' => now()]);

        return $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_ASSISTANT,
            'content' => $this->contentGuard->refusal($this->contentGuard->detectLocale($customerMessage, $conversation->locale)),
        ]);
    }

    private function lastCustomerMessage(Conversation $conversation): string
    {
        return (string) $conversation->messages()->where('role', ConversationMessage::ROLE_CUSTOMER)->latest('id')->value('content');
    }

    private function claimsToolData(string $text): bool
    {
        return preg_match(self::UNBACKED_DATA_PATTERN, $text) === 1;
    }

    private function answer(Rental $rental, Conversation $conversation): ConversationMessage
    {
        $tools = new RentalAgentTools($rental, $conversation, $conversation->locale);
        $definitions = RentalAgentTools::definitions();

        $messages = [
            ['role' => 'system', 'content' => $this->prompt->system($rental, $conversation)],
            ...$this->withScopeReminder($this->buildHistory($conversation), $rental, $conversation->locale),
        ];

        $toolLog = [];
        $uiPayloads = [];
        $deadline = microtime(true) + self::REPLY_BUDGET_SECONDS;

        $message = $this->chatCompletion($messages, $definitions, $deadline);

        if (empty($message['tool_calls']) && $this->claimsToolData($message['content'] ?? '')) {
            $messages[] = ['role' => 'assistant', 'content' => $message['content']];
            $messages[] = ['role' => 'user', 'content' => '[System notice: your last reply stated a price or pretended to show car cards without calling a tool. Never state a car price or availability from memory. Call search_vehicles or check_availability now, or ask the customer for the details you still need.]'];

            $message = $this->chatCompletion($messages, $definitions, $deadline);
        }

        $iterations = 0;
        while (! empty($message['tool_calls']) && $iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;

            $messages[] = [
                'role' => 'assistant',
                'content' => $message['content'] ?? '',
                'tool_calls' => $message['tool_calls'],
            ];

            foreach ($message['tool_calls'] as $call) {
                $name = $call['function']['name'] ?? '';
                $input = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

                $result = $tools->dispatch($name, $input);

                $toolLog[] = ['name' => $name, 'input' => $input];
                if ($result['ui'] !== null) {
                    $uiPayloads[] = $result['ui'];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content' => $result['text'],
                ];
            }

            $message = $this->chatCompletion($messages, $definitions, $deadline);
        }

        $text = trim((string) ($message['content'] ?? ''));

        if ($this->contentGuard->isOffensive($text)) {
            return $this->refuse($conversation, $this->lastCustomerMessage($conversation));
        }

        // A tool (e.g. request_human_handover) may have changed the
        // conversation's status/summary directly in the DB this turn.
        $conversation->refresh();
        $conversation->update(['last_message_at' => now()]);

        return $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_ASSISTANT,
            'content' => $text !== '' ? $text : null,
            'ui_payload' => $uiPayloads !== [] ? $uiPayloads : null,
            'tool_calls' => $toolLog !== [] ? $toolLog : null,
        ]);
    }

    /**
     * One call to the local model's OpenAI-compatible /v1/chat/completions.
     *
     * @param  float  $deadline  microtime() by which the whole customer turn must be answered
     * @return array{content: ?string, tool_calls: ?array}
     */
    private function chatCompletion(array $messages, array $tools, float $deadline): array
    {
        $remaining = $deadline - microtime(true);

        if ($remaining < 1) {
            throw new RuntimeException('Local LLM did not answer within the '.self::REPLY_BUDGET_SECONDS.'s reply budget.');
        }

        $response = Http::withToken(config('services.local_llm.api_key'))
            ->timeout(min(self::REQUEST_TIMEOUT_SECONDS, (int) ceil($remaining)))
            ->post(rtrim(config('services.local_llm.base_url'), '/').'/v1/chat/completions', [
                'model' => config('services.local_llm.model'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'reasoning_effort' => 'none',
                'max_tokens' => self::MAX_TOKENS,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Local LLM request failed: HTTP {$response->status()} — {$response->body()}");
        }

        $message = $response->json('choices.0.message', []);
        $finishReason = $response->json('choices.0.finish_reason');

        if ($finishReason === 'length' && empty($message['tool_calls'])) {
            throw new RuntimeException('Local LLM ran out of tokens mid-thought before it could respond. Increase MAX_TOKENS.');
        }

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $message['tool_calls'] ?? null,
        ];
    }

    private function buildHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_CUSTOMER, ConversationMessage::ROLE_ASSISTANT])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $message) => [
                'role' => $message->role === ConversationMessage::ROLE_CUSTOMER ? 'user' : 'assistant',
                'content' => (string) $message->content,
            ])
            ->filter(fn (array $m) => $m['content'] !== '')
            ->values()
            ->all();
    }

    /**
     * Repeats the scope rule right after the customer's latest message, where the
     * model weighs it most. Only the request carries it; it is never stored.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  string  $fallbackLocale  the page language, used when the message has no clear one
     * @return list<array{role: string, content: string}>
     */
    private function withScopeReminder(array $history, Rental $rental, string $fallbackLocale): array
    {
        $last = array_key_last($history);

        if ($last === null || $history[$last]['role'] !== 'user') {
            return $history;
        }

        $language = ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => 'Japanese (日本語)'][$this->contentGuard->detectLocale($history[$last]['content'], $fallbackLocale)];

        $history[$last]['content'] .= $this->prompt->scopeReminder($rental, $language);

        return $history;
    }
}
