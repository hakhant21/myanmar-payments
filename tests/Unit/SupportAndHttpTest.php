<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderUnavailableException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Support\Idempotency\CallbackIdempotencyGuard;
use Hakhant\Payments\Support\Logging\PaymentLogger;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

afterEach(function (): void {
    Mockery::close();
});

describe('HttpClient', function (): void {
    it('post returns decoded array response', function (): void {
        Http::fake([
            'https://provider.test/post' => Http::response(['ok' => true], 200),
        ]);

        $client = new HttpClient;

        expect($client->post('https://provider.test/post', ['a' => 1]))->toBe(['ok' => true]);
    });

    it('get returns decoded array response', function (): void {
        Http::fake([
            'https://provider.test/get*' => Http::response(['result' => 'ok'], 200),
        ]);

        $client = new HttpClient;

        expect($client->get('https://provider.test/get', ['id' => 1]))->toBe(['result' => 'ok']);
    });

    it('postRaw returns raw body response', function (): void {
        Http::fake([
            'https://provider.test/raw' => Http::response('accepted', 200, ['Content-Type' => 'text/plain']),
        ]);

        $client = new HttpClient;

        expect($client->postRaw('https://provider.test/raw', '<request />', ['Content-Type' => 'text/plain']))
            ->toBe('accepted');
    });

    it('post throws ProviderUnavailableException on request failure', function (): void {
        Http::fake([
            'https://provider.test/post' => Http::response(['error' => 'down'], 500),
        ]);

        $client = new HttpClient;

        expect(fn (): array => $client->post('https://provider.test/post', []))
            ->toThrow(ProviderUnavailableException::class, 'Provider request failed:');
    });

    it('postRaw throws ProviderUnavailableException on request failure', function (): void {
        Http::fake([
            'https://provider.test/raw' => Http::response('down', 500),
        ]);

        $client = new HttpClient;

        expect(fn (): string => $client->postRaw('https://provider.test/raw', 'payload'))
            ->toThrow(ProviderUnavailableException::class, 'Provider request failed:');
    });

    it('get throws ProviderUnavailableException on request failure', function (): void {
        Http::fake([
            'https://provider.test/get*' => Http::response(['error' => 'down'], 500),
        ]);

        $client = new HttpClient;

        expect(fn (): array => $client->get('https://provider.test/get'))
            ->toThrow(ProviderUnavailableException::class, 'Provider request failed:');
    });

    it('postWithOptions applies supported request options', function (): void {
        Http::fake([
            'https://provider.test/post-with-options' => Http::response(['ok' => true], 200),
        ]);

        $client = new HttpClient;

        expect($client->postWithOptions(
            'https://provider.test/post-with-options',
            ['a' => 1],
            ['X-Test' => 'yes'],
            30,
            ['withOptions' => ['verify' => false]],
        ))->toBe(['ok' => true]);
    });

    it('postWithOptions wraps unsupported request options', function (): void {
        $client = new HttpClient;

        expect(fn (): array => $client->postWithOptions(
            'https://provider.test/post-with-options',
            ['a' => 1],
            [],
            30,
            ['doesNotExist' => true],
        ))->toThrow(ProviderUnavailableException::class, 'Unsupported HTTP client option: doesNotExist');
    });
});

describe('CallbackIdempotencyGuard', function (): void {
    it('uses prefixed cache key and ttl', function (): void {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')
            ->once()
            ->with('myanmar-payments:callback:abc123', true, 120)
            ->andReturn(true);

        $guard = new CallbackIdempotencyGuard($cache);

        expect($guard->lock('abc123', 120))->toBeTrue();
    });
});

describe('PaymentLogger', function (): void {
    it('redacts sensitive keys on info logs', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('message', [
                'secret' => '***',
                'token' => '***',
                'signature' => '***',
                'authorization' => '***',
                'amount' => 1000,
            ]);

        (new PaymentLogger($logger))->info('message', [
            'secret' => 's',
            'token' => 't',
            'signature' => 'sig',
            'authorization' => 'auth',
            'amount' => 1000,
        ]);
    });

    it('redacts case-insensitive sensitive keys on error logs', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with('failed', [
                'SeCrEt' => '***',
                'ToKeN' => '***',
                'other' => 'ok',
            ]);

        (new PaymentLogger($logger))->error('failed', [
            'SeCrEt' => 'abc',
            'ToKeN' => 'def',
            'other' => 'ok',
        ]);
    });
});
