<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivedWebhook extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_DELIVERING = 'delivering';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_PARTIALLY_FAILED = 'partially_failed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'webhook_endpoint_id',
        'method',
        'ip',
        'content_type',
        'headers',
        'body',
        'query',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'query' => 'array',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }

    /**
     * Recompute aggregate status from the latest attempt per target.
     */
    public function refreshStatus(): void
    {
        $latest = $this->attempts()
            ->select('delivery_target_id', 'status')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('delivery_attempts')
                    ->where('received_webhook_id', $this->id)
                    ->groupBy('delivery_target_id');
            })
            ->pluck('status');

        if ($latest->isEmpty()) {
            $this->status = self::STATUS_RECEIVED;
        } elseif ($latest->every(fn ($s) => $s === DeliveryAttempt::STATUS_SUCCESS)) {
            $this->status = self::STATUS_DELIVERED;
        } elseif ($latest->contains(DeliveryAttempt::STATUS_SUCCESS)) {
            $this->status = self::STATUS_PARTIALLY_FAILED;
        } elseif ($latest->contains(DeliveryAttempt::STATUS_PENDING)) {
            $this->status = self::STATUS_DELIVERING;
        } else {
            $this->status = self::STATUS_FAILED;
        }

        $this->save();
    }
}
