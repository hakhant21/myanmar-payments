<?php

declare(strict_types=1);

namespace Hakhant\Payments\Support\Idempotency;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class CallbackIdempotencyGuard
{
    public function __construct(private CacheRepository $cache) {}

    public function lock(string $key, int $ttlSeconds = 300): bool
    {
        return $this->cache->add($this->cacheKey($key), true, $ttlSeconds);
    }

    private function cacheKey(string $key): string
    {
        return 'myanmar-payments:callback:'.$key;
    }
}
