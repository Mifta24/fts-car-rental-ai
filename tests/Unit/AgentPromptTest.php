<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Rental;
use App\Services\Agent\AgentPrompt;
use Tests\TestCase;

class AgentPromptTest extends TestCase
{
    public function test_the_system_prompt_names_the_rental_and_the_scene_the_customer_is_in(): void
    {
        $rental = new Rental(['name' => 'Bali Wheels', 'city' => 'Denpasar', 'country' => 'Indonesia', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar']);
        $conversation = new Conversation(['locale' => 'en', 'current_scene' => 'services']);

        $prompt = (new AgentPrompt)->system($rental, $conversation);

        $this->assertStringContainsString('AI Rental Agent for Bali Wheels', $prompt);
        $this->assertStringContainsString('Current scene: services', $prompt);
        $this->assertStringContainsString('Currency for all prices: IDR', $prompt);
    }

    public function test_the_reminder_asks_for_the_customers_language_and_keeps_the_agent_in_scope(): void
    {
        $reminder = (new AgentPrompt)->scopeReminder(new Rental(['name' => 'Bali Wheels']), 'Bahasa Indonesia');

        $this->assertStringContainsString('write your whole reply in Bahasa Indonesia', $reminder);
        $this->assertStringContainsString("Bali Wheels's car rental staff only", $reminder);
    }
}
