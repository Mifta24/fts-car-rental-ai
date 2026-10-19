<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HandoverController extends Controller
{
    use ResolvesCurrentRental;

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $status = $request->query('status', HandoverRequest::STATUS_OPEN);

        $handovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('rental_id', $rental->id))
            ->where('status', $status)
            ->with('conversation')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.handovers.index', compact('rental', 'handovers', 'status'));
    }

    public function show(Request $request, HandoverRequest $handover): View
    {
        $rental = $this->currentRental($request);
        abort_if($handover->conversation->rental_id !== $rental->id, 404);

        $handover->load('conversation.messages');

        return view('admin.handovers.show', compact('rental', 'handover'));
    }

    public function reply(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($handover->conversation->rental_id !== $rental->id, 404);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $handover->conversation->messages()->create([
            'role' => ConversationMessage::ROLE_STAFF,
            'content' => $data['message'],
        ]);

        return back()->with('status', __('admin.handover_replied'));
    }

    public function resolve(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($handover->conversation->rental_id !== $rental->id, 404);

        $handover->update(['status' => HandoverRequest::STATUS_RESOLVED, 'resolved_at' => now(), 'assigned_to' => $request->user()->id]);
        $handover->conversation->update(['status' => Conversation::STATUS_ACTIVE]);

        return redirect()->route('admin.handovers.index')->with('status', __('admin.handover_resolved'));
    }
}
