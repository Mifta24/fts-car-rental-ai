<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\HandoverRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('conversations:prune {--days=90 : Delete conversations idle for longer than this many days}')]
#[Description('Delete old customer conversations and their messages; bookings made from them are kept')]
class PruneStaleConversations extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = Conversation::query()
            ->where(fn ($query) => $query->where('last_message_at', '<', $cutoff)
                ->orWhere(fn ($query) => $query->whereNull('last_message_at')->where('created_at', '<', $cutoff)))
            ->whereDoesntHave('handoverRequest', fn ($query) => $query->where('status', HandoverRequest::STATUS_OPEN))
            ->delete();

        $this->info("Deleted {$deleted} stale conversation(s).");

        return self::SUCCESS;
    }
}
