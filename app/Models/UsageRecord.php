<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for tracking user feature usage
 */
class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'amount',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by feature
     */
    public function scopeForFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeInPeriod($query, \Carbon\Carbon $start, \Carbon\Carbon $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Scope for current month usage
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereBetween('recorded_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }
}