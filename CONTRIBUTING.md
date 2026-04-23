# Contributing Guide

Thank you for contributing to `hakhant/myanmar-payments`.

## Development Setup

1. Clone the repository.
2. Install dependencies:

```bash
composer install
```

1. Run quality checks:

```bash
composer format
composer analyse
composer test
composer test:coverage
```

## Coding Rules

- PHP 8.2+ only
- `declare(strict_types=1);` in every PHP file
- Strict return types on all methods
- Keep namespaces under `Hakhant\\Payments`
- Follow SOLID and existing architecture (Domain/Application/Infrastructure/Laravel)
- Prefer immutable DTOs and value objects
- Keep provider-specific logic inside provider adapters

## Testing Requirements

- Use PestPHP for all new tests
- Add unit tests for behavior and edge cases
- Add feature tests for framework integration
- Do not call real third-party APIs in tests
- New changes should keep or increase coverage

## Pull Request Checklist

- [ ] Code is formatted (`composer format`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Tests pass (`composer test`)
- [ ] Coverage checked (`composer test:coverage`)
- [ ] Docs updated when behavior/config changed

## Commit Guidelines

Use clear commit messages focused on intent, for example:

- `feat(kbz): add mmqr precreate support`
- `test(kbz): add webhook signature verification cases`
- `docs: add custom provider integration guide`

## Reporting Issues

Include:

- PHP version
- Laravel version
- Package version/branch
- Reproduction steps
- Expected behavior
- Actual behavior
- Stack trace/log snippets (redact secrets)
