<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers;

use Hakhant\Payments\Infrastructure\Http\HttpClient;

abstract readonly class AbstractProviderGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected HttpClient $httpClient,
        protected array $config,
    ) {}

    protected function baseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? '');
    }

    protected function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }
}
