<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Services\Agent\AgentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agent:chat {rental? : Rental slug} {--locale=id : id|en|ja}')]
#[Description('Talk to the AI Rental Agent for a rental from the terminal, for testing the tool-calling loop before the web UI exists.')]
class AgentChatCommand extends Command
{
    public function handle(AgentService $service): int
    {
        $rental = $this->argument('rental')
            ? Rental::where('slug', $this->argument('rental'))->first()
            : Rental::first();

        if (! $rental) {
            $this->error('No rental found. Seed one first: php artisan db:seed');

            return self::FAILURE;
        }

        if (blank(config('services.local_llm.base_url'))) {
            $this->error('LOCAL_LLM_BASE_URL is not set in .env — the agent has no model endpoint to call.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $conversation = $service->startConversation($rental, $locale);

        $this->info("Chatting with the AI Rental Agent for {$rental->name} ({$locale}). Type 'exit' to quit.");
        $this->newLine();

        while (true) {
            $customerMessage = $this->ask('You');

            if ($customerMessage === null || in_array(trim($customerMessage), ['exit', 'quit'], true)) {
                break;
            }

            $message = $service->reply($rental, $conversation, $customerMessage);

            $this->newLine();
            $this->line('<fg=cyan>Agent:</> '.($message->content ?? '(no text — see UI payload below)'));

            if ($message->ui_payload) {
                $this->line('<fg=gray>[ui_payload] '.json_encode($message->ui_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</>');
            }

            $conversation->refresh();
            if ($conversation->isHandedOver()) {
                $this->warn('Conversation handed over to human staff. Ending session.');
                break;
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
