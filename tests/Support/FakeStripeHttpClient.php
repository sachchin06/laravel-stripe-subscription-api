<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Replaces the Stripe SDK's HTTP layer, so a test can run the real
 * StripeService and SDK code (URLs, headers, pagination, error handling)
 * without any network access. Responses come from the given callback.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, path: string, headers: array<int, string>, params: array}> */
    public array $requests = [];

    /**
     * @param  callable(string $method, string $path, array $params): array{0: array, 1?: int}  $responder
     */
    private function __construct(private $responder) {}

    public static function install(callable $responder): self
    {
        $client = new self($responder);
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $this->requests[] = ['method' => $method, 'path' => $path, 'headers' => $headers, 'params' => $params];

        [$body, $status] = ($this->responder)($method, $path, $params) + [1 => 200];

        return [json_encode($body), $status, []];
    }

    public function header(int $request, string $name): ?string
    {
        foreach ($this->requests[$request]['headers'] as $header) {
            [$key, $value] = array_map('trim', explode(':', $header, 2));
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
