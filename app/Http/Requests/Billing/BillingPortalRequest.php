<?php

namespace App\Http\Requests\Billing;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class BillingPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => [
                'nullable',
                'url:http,https',
                // Without this check the portal's "return" link could send users to
                // any site chosen by whoever made the request (an open redirect).
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! in_array(parse_url($value, PHP_URL_HOST), $this->allowedHosts(), true)) {
                        $fail('The return URL must point to this application or its frontend.');
                    }
                },
            ],
        ];
    }

    public function returnUrl(): string
    {
        return $this->validated('return_url') ?? config('app.frontend_url');
    }

    /**
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        return array_values(array_unique(array_filter([
            parse_url((string) config('app.frontend_url'), PHP_URL_HOST),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ])));
    }
}
