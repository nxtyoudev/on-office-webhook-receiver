<?php

namespace Tests\Feature;

use App\Jobs\ForwardWebhook;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryTarget;
use App\Models\ReceivedWebhook;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_webhook_is_stored_and_dispatched(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'secret' => 's3cr3t',
        ]);
        $target = DeliveryTarget::create([
            'webhook_endpoint_id' => $endpoint->id,
            'name' => 'Sink',
            'url' => 'https://example.com/sink',
        ]);

        $body = '{"event":"order.created"}';
        $signature = hash_hmac('sha256', $body, 's3cr3t');

        $response = $this->postJson("/api/webhooks/{$endpoint->slug}", json_decode($body, true), [
            'X-Webhook-Signature' => "sha256={$signature}",
        ]);

        // postJson re-encodes the body; send raw instead for exact HMAC.
        $response = $this->call('POST', "/api/webhooks/{$endpoint->slug}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => "sha256={$signature}",
        ], $body);

        $response->assertStatus(202);
        $this->assertDatabaseHas('received_webhooks', [
            'webhook_endpoint_id' => $endpoint->id,
            'body' => $body,
        ]);
        $this->assertDatabaseHas('delivery_attempts', [
            'delivery_target_id' => $target->id,
            'status' => DeliveryAttempt::STATUS_PENDING,
        ]);
        Queue::assertPushed(ForwardWebhook::class);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'secret' => 's3cr3t',
        ]);

        $this->call('POST', "/api/webhooks/{$endpoint->slug}", [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=deadbeef',
        ], '{}')->assertStatus(401);

        $this->assertDatabaseCount('received_webhooks', 0);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->postJson('/api/webhooks/does-not-exist', [])->assertStatus(404);
    }

    public function test_forward_job_posts_body_and_marks_attempt(): void
    {
        Http::fake(['https://example.com/*' => Http::response('ok', 200)]);

        $endpoint = WebhookEndpoint::create(['name' => 'Test']);
        $target = DeliveryTarget::create([
            'webhook_endpoint_id' => $endpoint->id,
            'name' => 'Sink',
            'url' => 'https://example.com/sink',
            'secret' => 'out-secret',
        ]);
        $webhook = ReceivedWebhook::create([
            'webhook_endpoint_id' => $endpoint->id,
            'method' => 'POST',
            'headers' => ['content-type' => ['application/json']],
            'body' => '{"a":1}',
            'content_type' => 'application/json',
        ]);
        $attempt = DeliveryAttempt::create([
            'received_webhook_id' => $webhook->id,
            'delivery_target_id' => $target->id,
            'status' => DeliveryAttempt::STATUS_PENDING,
        ]);

        (new ForwardWebhook($attempt->id))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/sink'
                && $request->body() === '{"a":1}'
                && str_starts_with($request->header('X-Webhook-Signature')[0] ?? '', 'sha256=');
        });

        $attempt->refresh();
        $this->assertSame(DeliveryAttempt::STATUS_SUCCESS, $attempt->status);
        $this->assertSame(200, $attempt->response_status);
        $this->assertSame(ReceivedWebhook::STATUS_DELIVERED, $webhook->fresh()->status);
    }
}
