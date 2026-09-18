# Contributing to GymPro

Thank you for considering a contribution to GymPro. This guide explains the project boundaries, development workflow, and checks expected before a pull request.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md). Report vulnerabilities through the private process in [SECURITY.md](SECURITY.md), not through a public issue.

## Before you begin

For substantial workflow or schema changes, open a feature request first so the scope can be discussed before implementation.

Understand the application layers before editing:

- `v3/` is the recommended presentation layer. It reuses V2's backend, database, sessions, and business services.
- `v2/` owns the canonical ten-table schema, migrations, backend services, demo data, workflow tests, and technical documentation.
- The root application is an earlier implementation retained for reference.

Keep changes within the correct layer. Do not duplicate V2 business logic in V3 or alter working backend behavior for a visual-only change.

## Local development

Requirements and installation steps are in the [project README](README.md) and [V2 setup guide](v2/README.md).

Use a disposable local database migrated from scratch. Never run tests, migrations, or the demo seeder against production data.

1. Fork or clone the repository.
2. Install PHP dependencies with `composer install`.
3. Copy `v2/.env.example` to `v2/.env` and configure local values.
4. Create the local database and run `php v2/cli/migrate.php`.
5. Optionally run `php v2/cli/seed-demo.php` for fictional demo data.
6. Create a focused branch for the change.

## Development guidelines

### PHP and application behavior

- Use strict typing and follow the naming, validation, exception, and transaction patterns around the code you change.
- Use prepared statements for database access and preserve role checks, CSRF protection, escaping, and session security.
- Keep commits focused and avoid unrelated formatting or generated-file churn.
- Add or update tests and documentation when behavior changes.

### Database changes

- Add ordered migrations under `v2/database/migrations/`; do not rewrite an applied migration.
- Preserve foreign keys, uniqueness constraints, transactional behavior, and the documented schema contract.
- Update the schema contract, architecture, data dictionary, workflows, and relevant tests together.
- Include migration and rollback implications in the pull request.

### V3 frontend changes

- Preserve the boundary between V3 presentation and V2 backend behavior.
- Edit source assets and rebuild committed output where applicable; do not edit only generated files.
- Keep frontend dependencies local rather than adding runtime CDN dependencies.
- Verify responsive layouts, keyboard operation, visible focus, light/dark themes, and reduced-motion behavior.
- Include screenshots for visible changes.

## Required checks

Run the checks relevant to your change. A full verification includes:

```bash
composer test
```

```bash
composer lint
```

```bash
php v2/tests/schema_contract.php
```

```bash
php v2/tests/trainer_application_workflow.php
```

```bash
php v2/tests/staff_account_workflow.php
```

```bash
php v3/tests/frontend_contract.php
```

```bash
php v3/tests/runtime_probe.php
```

When changing V3 assets:

```bash
cd v3 && npm ci && npm run build && npm run check:js
```

Document the commands and results in the pull request. If a check cannot run, explain why.

## Sensitive and generated files

Never commit:

- `.env` files, credentials, API keys, or private application keys;
- real personal, membership, health, or payment data;
- database dumps, logs, sessions, or uploaded trainer documents;
- `vendor/`, `node_modules/`, or other dependency directories;
- local editor, operating-system, or XAMPP runtime files.

Use fictional `example.test` identities in fixtures and examples.

## Pull requests

Before submitting:

- link the related issue when one exists;
- explain the problem and the chosen solution;
- identify whether root, V2, V3, or the database is affected;
- include test evidence and UI screenshots where relevant;
- document migrations, compatibility considerations, and security/privacy impact;
- update documentation and generated V3 assets where applicable;
- confirm the change contains no secrets or personal data.

A maintainer may request changes to keep the schema, security boundaries, tests, or documentation consistent.
