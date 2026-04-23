<?php

declare(strict_types=1);

namespace Hakhant\Payments\Support\Logging;

use Psr\Log\LoggerInterface;

final readonly class PaymentLogger
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->redactSecrets($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->redactSecrets($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redactSecrets(array $context): array
    {
        $sensitiveKeys = ['secret', 'token', 'signature', 'authorization'];

        foreach (array_keys($context) as $key) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $context[$key] = '***';
            }
        }

        return $context;
    }
}
