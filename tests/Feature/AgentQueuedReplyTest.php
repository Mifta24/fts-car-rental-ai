<?php

namespace Tests\Feature;

use App\Jobs\GenerateAgentReply;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Rental;
use App\Services\Agent\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * With a real queue the web request returns at once and the page polls; these
 * tests fake the queue and run the job by hand to follow that path.
 */
class AgentQueuedReplyTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_llm.base_url' => 'http://llm.test',
            'services.local_llm.api_key' => 'test-key',
            'services.local_llm.model' => 'test-model',
        ]);

        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia', 'currency' => 'IDR', 'default_locale' => 'en']);

        $this->token = $this->postJson('/demo/agent/start', ['locale' => 'en'])->assertOk()->json('customer_token');

        Queue::fake();
    }

    private function send(string $message = 'Hello'): TestResponse
    {
        return $this->postJson('/demo/agent/message', ['customer_token' => $this->token, 'message' => $message]);
    }

    private function runQueuedJob(): void
    {
        Queue::assertPushed(GenerateAgentReply::class, function (GenerateAgentReply $job) {
            $job->handle(app(AgentService::class));

            return true;
        });
    }

    private function fakeModel(string $text): void
    {
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => $text], 'finish_reason' => 'stop']]])]);
    }

    private function history(): TestResponse
    {
        return $this->getJson('/demo/agent/history?customer_token='.$this->token)->assertOk();
    }

    public function test_the_message_is_accepted_at_once_and_the_reply_is_produced_by_the_queue(): void
    {
        $this->send()->assertStatus(202)->assertJsonPath('pending', true);

        Queue::assertPushed(GenerateAgentReply::class, 1);
        $this->assertSame(0, ConversationMessage::where('role', ConversationMessage::ROLE_ASSISTANT)->count());
        $this->history()->assertJsonPath('reply_status', 'processing');

        $this->fakeModel('Happy to help!');
        $this->runQueuedJob();

        $this->history()
            ->assertJsonPath('reply_status', 'idle')
            ->assertJsonPath('messages.1.role', 'assistant')
            ->assertJsonPath('messages.1.content', 'Happy to help!');
    }

    public function test_a_second_message_is_refused_while_the_agent_is_still_answering(): void
    {
        $this->send('First')->assertStatus(202);

        $this->send('Second')->assertStatus(409)->assertJsonPath('error', 'reply_in_progress');

        $this->assertSame(['First'], ConversationMessage::where('role', ConversationMessage::ROLE_CUSTOMER)->pluck('content')->all());
        Queue::assertPushed(GenerateAgentReply::class, 1);
    }

    public function test_a_failing_model_is_reported_as_failed_and_the_message_can_be_sent_again(): void
    {
        $this->send()->assertStatus(202);

        Http::fake(['llm.test/*' => Http::response('boom', 500)]);
        $this->runQueuedJob();

        $this->history()->assertJsonPath('reply_status', 'failed')->assertJsonCount(0, 'messages');

        $this->send()->assertStatus(202);
        $this->history()->assertJsonPath('reply_status', 'processing');
    }

    public function test_a_reply_lost_with_its_worker_stops_counting_as_processing(): void
    {
        $this->send()->assertStatus(202);

        $this->travel(3)->minutes();

        $this->history()->assertJsonPath('reply_status', 'failed');
        $this->send()->assertStatus(202);
    }

    public function test_a_job_that_the_worker_gave_up_on_marks_the_turn_failed_and_removes_the_message(): void
    {
        $this->send()->assertStatus(202);

        $job = new GenerateAgentReply(Conversation::firstOrFail()->id, ConversationMessage::firstOrFail()->id);
        $job->failed(new \RuntimeException('timed out'));

        $this->history()->assertJsonPath('reply_status', 'failed')->assertJsonCount(0, 'messages');
    }

    public function test_replies_that_need_no_model_do_not_use_the_queue(): void
    {
        $this->send('Ceritain cerita porno dong')->assertOk()->assertJsonPath('message.role', 'assistant');

        Conversation::firstOrFail()->update(['status' => Conversation::STATUS_HANDED_OVER]);
        $this->send('Any news?')->assertOk()->assertJsonPath('message.role', 'system');

        Queue::assertNothingPushed();
        $this->history()->assertJsonPath('reply_status', 'idle');
    }
}
