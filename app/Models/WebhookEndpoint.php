<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'secret',
        'signature_header',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $endpoint) {
            if (empty($endpoint->slug)) {
                $endpoint->slug = Str::random(24);
            }
        });
    }

    public function receivedWebhooks(): HasMany
    {
        return $this->hasMany(ReceivedWebhook::class);
    }

    public function deliveryTargets(): HasMany
    {
        return $this->hasMany(DeliveryTarget::class);
    }

    public function inboundUrl(): string
    {
        return url("/api/webhooks/{$this->slug}");
    }
}
