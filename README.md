# On Office Webhook Receiver

Laravel webhook proxy with a Filament admin panel. Receives inbound webhooks on
per-endpoint URLs, stores the raw request, and fans out deliveries to configured
targets via a queued job with retries.

## Stack

- Laravel 13, PHP 8.4, SQLite (default), Filament 5 admin at `/admin`
- Queue driver: `database` — run `php artisan queue:work` to process deliveries

## How it works

1. **Endpoints** (`/admin/webhook-endpoints`) — each endpoint gets an inbound URL
   `POST /api/webhooks/{slug}`. Optional HMAC-SHA256 signature
   verification: set `secret` and optionally `signature_header`
   (default `X-Webhook-Signature`; accepts raw hex or `sha256=<hex>`).
2. **Delivery targets** (`/admin/delivery-targets`) — URL, extra headers,
   optional outbound HMAC secret (`X-Webhook-Signature` is sent), timeout.
3. **Received webhooks** (`/admin/received-webhooks`) — stored request
   (method, headers, query, body, IP) plus per-target delivery attempts with
   response status/body, duration, and errors. A **Redeliver** action re-queues
   delivery to all active targets.

Forwarding preserves the original method, body, query string, and headers
(hop-by-hop and inbound signature headers are stripped). Failed deliveries retry
up to 3 times with 10s/60s backoff. Aggregate status per webhook:
`received → delivering → delivered | partially_failed | failed`.

## Setup

```bash
composer install
cp .env.example .env   # configure DB if not using SQLite
php artisan key:generate
php artisan migrate
php artisan make:filament-user   # create an admin login
php artisan serve
php artisan queue:work           # in a second terminal
```

Admin: http://localhost:8000/admin

## Testing

```bash
php artisan test
```

`tests/Feature/WebhookProxyTest.php` covers signature verification, storage,
dispatch, and the forwarding job.
