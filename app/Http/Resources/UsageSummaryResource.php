<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for transforming usage summary data
 */
class UsageSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'feature' => $this->resource['feature'] ?? null,
            'current_usage' => $this->resource['current'],
            'limit' => $this->resource['limit'],
            'percentage_used' => $this->resource['percentage'],
            'is_unlimited' => $this->resource['unlimited'],
            'status' => $this->getUsageStatus(),
        ];
    }

    /**
     * Get usage status based on percentage
     */
    private function getUsageStatus(): string
    {
        if ($this->resource['unlimited']) {
            return 'unlimited';
        }

        $percentage = $this->resource['percentage'];
        
        if ($percentage >= 100) {
            return 'exceeded';
        } elseif ($percentage >= 80) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'moderate';
        }
        
        return 'low';
    }
}