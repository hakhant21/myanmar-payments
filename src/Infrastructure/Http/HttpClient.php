<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Http;

use Hakhant\Payments\Domain\Exceptions\ProviderUnavailableException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final readonly class HttpClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        try {
            return $this->request($headers, $timeout, [])->post($url, $payload)->throw()->json() ?? [];
        } catch (Throwable $throwable) {
            throw new ProviderUnavailableException(
                sprintf('Provider request failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function postRaw(string $url, string $payload, array $headers = [], int $timeout = 30): string
    {
        try {
            return $this->request($headers, $timeout, [])
                ->withBody($payload, $headers['Content-Type'] ?? 'text/plain')
                ->post($url)
                ->throw()
                ->body();
        } catch (Throwable $throwable) {
            throw new ProviderUnavailableException(
                sprintf('Provider request failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = [], array $headers = [], int $timeout = 30): array
    {
        try {
            return $this->request($headers, $timeout, [])->get($url, $query)->throw()->json() ?? [];
        } catch (Throwable $throwable) {
            throw new ProviderUnavailableException(
                sprintf('Provider request failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $options
     */
    public function postWithOptions(string $url, array $payload, array $headers = [], int $timeout = 30, array $options = []): array
    {
        try {
            return $this->request($headers, $timeout, $options)->post($url, $payload)->throw()->json() ?? [];
        } catch (Throwable $throwable) {
            throw new ProviderUnavailableException(
                sprintf('Provider request failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $options
     */
    private function request(array $headers, int $timeout, array $options): PendingRequest
    {
        $request = Http::withHeaders($headers)->timeout($timeout);

        foreach ($options as $method => $value) {
            if (! method_exists($request, $method)) {
                throw new InvalidArgumentException(sprintf('Unsupported HTTP client option: %s', $method));
            }

            $request = $request->{$method}($value);
        }

        return $request;
    }
}
