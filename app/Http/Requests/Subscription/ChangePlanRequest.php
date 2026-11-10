<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_id' => ['required', 'string', 'exists:plan_prices,stripe_price_id'],
        ];
    }

    /**
     * Changing to the price the user already pays would be a pointless
     * Stripe call, so it is rejected as a validation error.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $currentPriceId = $this->user()->activeSubscription()?->price?->stripe_price_id;

                if ($currentPriceId !== null && $currentPriceId === $this->input('price_id')) {
                    $validator->errors()->add('price_id', 'You are already subscribed to this price.');
                }
            },
        ];
    }
}
