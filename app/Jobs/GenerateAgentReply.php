<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Agent\AgentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks the model for the reply to one customer message, off the web request,
 * so a slow model never holds a web worker. The page polls the conversation
 * until the reply (or a failure) shows up.
 */
class GenerateAgentReply implements ShouldQueue
{
    use Queueable;

    /**
     * A retry would answer the customer twice, so a turn gets exactly one attempt.
     */
    public int $tries = 1;

    /**
     * A little above AgentService's own reply budget, so the budget fails first.
     */
    public int $timeout = 95;

    public function __construct(public int $conversationId, public int $customerMessageId) {}

    public function handle(AgentService $agent): void
    {
        $conversation = Conversation::with('rental')->find($this->conversationId);
        $customerMessage = ConversationMessage::find($this->customerMessageId);

        if (! $conversation || ! $customerMessage) {
            return;
        }

        $agent->generateQueuedReply($conversation, $customerMessage);
    }

    /**
     * The worker gave up (timeout, crash) before the service could record a failure.
     */
    public function failed(?Throwable $exception): void
    {
        $conversation = Conversation::find($this->conversationId);

        if ($conversation) {
            app(AgentService::class)->markReplyFailed($conversation, $this->customerMessageId);
        }
    }
}
