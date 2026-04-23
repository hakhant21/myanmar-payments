# Myanmar Payments Laravel Package - Agent Blueprint

## 1. Objective

Build a reusable Laravel package for Myanmar payment gateways that is:

- Cleanly extensible for multiple providers (KBZPay first, then AyaPay, CBPay, MPU/2C2P, etc.)
- Strictly aligned with SOLID principles
- Implemented with proven design patterns
- Testable, maintainable, and production-ready

Package working name: `hakhant/myanmar-payments`
Root namespace: `Hakhant\\Payments`

---

## 2. Core Scope (v1)

### In Scope

- Initiate payment transactions
- Query transaction status
- Handle callback/webhook verification
- Refund support (if provider supports it)
- Provider abstraction through a common contract
- Laravel service container integration
- Config-driven provider selection
- Logging and error normalization

### Out of Scope (v1)

- UI/frontend checkout components
- Subscription/recurring billing
- Wallet balance management
- Multi-tenant dashboard

---

## 3. Architectural Style

Use a layered architecture with clear boundaries:

1. Domain layer
2. Application layer
3. Infrastructure layer
4. Laravel integration layer

### Layer Responsibility

- Domain: contracts, value objects, business rules, exceptions
- Application: use cases (pay, status, refund, verify callback)
- Infrastructure: provider SDK/API adapters, HTTP clients, signature engines
- Laravel integration: service provider, config publish, facades, bindings

---

## 4. Design Patterns to Use

### 4.1 Strategy Pattern

Each payment provider is a strategy implementing a shared contract.

Examples:

- `KbzPayGateway implements PaymentGateway`
- `KbzPayGateway implements CanInitiateMmqr`

### 4.2 Factory Pattern

`GatewayFactory` resolves provider implementation by provider key from config/runtime.

### 4.3 Adapter Pattern

Wrap each external provider API so internal app only depends on package contracts.

### 4.4 Template Method Pattern

Use abstract base class for common provider flow (build payload, sign, send, parse, map errors).

### 4.5 DTO / Value Object Pattern

Use immutable data transfer and value objects for requests/responses.

### 4.6 Chain of Responsibility (optional)

For callback verification pipeline:

- signature validation
- timestamp validation
- idempotency check

---

## 5. SOLID Mapping

### S - Single Responsibility

- `PaymentManager`: orchestration only
- `SignatureVerifier`: signature logic only
- `ProviderClient`: HTTP communication only

### O - Open/Closed

New providers are added by creating new gateway class implementing contract.
No need to modify core manager.

### L - Liskov Substitution

All provider gateways must behave consistently against `PaymentGateway` contract.

### I - Interface Segregation

Split contracts by capability:

- `CanInitiatePayment`
- `CanQueryPayment`
- `CanRefundPayment`
- `CanVerifyCallback`

### D - Dependency Inversion

Depend on abstractions only.
Bind interfaces to implementations in package service provider.

---

## 6. Proposed Directory Structure

```text
myanmar-payments/
    src/
        Contracts/
            PaymentGateway.php
            CanInitiatePayment.php
            CanQueryPayment.php
            CanRefundPayment.php
            CanVerifyCallback.php
            GatewayFactory.php
        Domain/
            DTO/
                PaymentRequest.php
                PaymentResponse.php
                RefundRequest.php
                RefundResponse.php
                CallbackPayload.php
            ValueObjects/
                Money.php
                MerchantReference.php
                Signature.php
            Enums/
                Provider.php
                PaymentStatus.php
            Exceptions/
                PaymentException.php
                InvalidSignatureException.php
                ProviderException.php
        Application/
            PaymentManager.php
            UseCases/
                CreatePayment.php
                QueryPaymentStatus.php
                RefundPayment.php
                VerifyCallback.php
        Infrastructure/
            Providers/
                KBZPay/
                    KBZPayGateway.php
                    KBZPayClient.php
                    KBZPaySignature.php
                    KBZPayMapper.php
            Http/
                HttpClient.php
            Factories/
                DefaultGatewayFactory.php
        Support/
            Idempotency/
                CallbackIdempotencyGuard.php
            Logging/
                PaymentLogger.php
        Laravel/
            MyanmarPaymentsServiceProvider.php
            Facades/
                MyanmarPayments.php
    config/
        myanmar-payments.php
    tests/
        Unit/
        Feature/
```

---

## 7. Key Contracts (Design First)

### `PaymentGateway`

Core contract for provider gateways:

- `createPayment(PaymentRequest $request): PaymentResponse`
- `queryStatus(string $transactionId): PaymentResponse`

### Segregated capability contracts

- `CanRefundPayment::refund(RefundRequest $request): RefundResponse`
- `CanVerifyCallback::verifyCallback(CallbackPayload $payload): bool`

### `GatewayFactory`

- `make(string $provider): PaymentGateway`

---

## 8. Configuration Design

Provider reference sources used for implementation:

- KBZPay UAT API docs: <https://wap.kbzpay.com/pgw/uat/api/#/en/dashboard>

Config file: `config/myanmar-payments.php`

```php
return [
        'default' => env('MM_PAYMENT_PROVIDER', 'kbzpay'),

        'providers' => [
                'kbzpay' => [
                        'base_url' => env('KBZPAY_BASE_URL'),
                        'merchant_id' => env('KBZPAY_MERCHANT_ID'),
                        'app_id' => env('KBZPAY_APP_ID'),
                        'secret' => env('KBZPAY_SECRET'),
                        'public_key' => env('KBZPAY_PUBLIC_KEY'),
                ],
        ],
];
```

---

## 9. Public API (Developer Experience)

### Facade usage

```php
$response = MyanmarPayments::provider('kbzpay')->createPayment(
        new PaymentRequest(
                merchantReference: 'INV-1001',
                amount: 10000,
                currency: 'MMK',
                callbackUrl: 'https://example.com/payments/callback',
                redirectUrl: 'https://example.com/payment/return'
        )
);
```

### Manager usage via DI

```php
public function checkout(PaymentManager $payments)
{
    return $payments->provider('kbzpay')->createPayment($requestDto);
}
```

---

## 10. Error Handling Strategy

Normalize all provider errors into domain exceptions:

- `ValidationException` for bad input
- `AuthenticationException` for auth failures
- `ProviderUnavailableException` for network/timeout
- `PaymentFailedException` for declined/failed transactions

Never leak raw provider exception shape to package consumers.

---

## 11. Security Requirements

- Verify callback signatures using provider-specific algorithms
- Use timestamp tolerance window for replay attack mitigation
- Use idempotency key for callback processing
- Redact secrets from logs
- Constant-time signature compare

---

## 12. Testing Strategy

### Unit Tests

- Gateway contract compliance tests
- Signature generation/verification tests
- Mapper tests (provider payload <-> DTO)
- Factory resolution tests

### Feature Tests

- Laravel container bindings
- Config resolution and default provider
- Callback endpoint verification flow

### Integration Tests (optional)

- Sandbox API tests behind env flag

Coverage target for package core: >= 90%.

---

## 13. Implementation Phases

### Phase 1 - Foundation

- Create contracts and DTOs
- Create enums, exceptions, value objects
- Add service provider and config

### Phase 2 - Core Engine

- Implement `PaymentManager`
- Implement `DefaultGatewayFactory`
- Add logging and error normalizer

### Phase 3 - Provider 1 (KBZPay)

- Implement KBZ adapter classes
- Implement callback verification
- Add unit tests and fixtures

### Phase 4 - MMQR and Callback Hardening

- Implement KBZ MMQR flow adapter methods
- Add webhook verification and idempotency tests
- Add conformance tests for MMQR payload mapping

### Phase 5 - Hardening

- Improve retries/timeouts
- Add idempotency guard
- finalize docs and examples

---

## 14. Coding Standards

- PHP 8.2+
- PSR-4 (autoloading standard)
- `declare(strict_types=1);` on every PHP file
- Class naming: use single-word class names where practical; if clarity requires multi-word, multi-word is acceptable
- Strict parameter and property typing everywhere possible
- Strict return types on all functions/methods (including `: void` where applicable)
- Prefer readonly DTOs/value objects where possible
- No static state in gateways
- Keep infrastructure concerns out of domain layer

---

## 14.1 Quality Tooling and Static Rules

- Use Laravel Pint as the code formatter/linter baseline
- Use Rector (requested as Reactor) for automated refactor and type hardening rules
- Add static analysis (PHPStan/Psalm) at a strict level for type safety
- CI must fail when formatting, static analysis, or tests fail

### Expected Tooling Rules

- Pint: enforce PSR-12 and consistent imports/order
- Rector/Reactor: apply strict typing rules and return type declarations where safe
- Static analysis: no mixed/implicit return types in package core

---

## 15. Acceptance Criteria (v1)

- Can initiate payment with at least one provider
- Can query payment status
- Can verify callback signature securely
- Can switch provider via config without changing business code
- Clean test suite passing for unit + feature tests

---

## 16. Next Step

After this blueprint is approved, generate package scaffolding and implement Phase 1 with interfaces, DTOs, service provider, and config.
