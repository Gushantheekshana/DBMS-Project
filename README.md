# GymPro

GymPro is a role-based gym membership management system built as a university DBMS project with PHP and MySQL/MariaDB. It demonstrates a ten-table relational model through practical workflows for administrators, receptionists, trainers, and members.

> [!NOTE]
> GymPro is an educational project intended for local development and demonstration. It has not undergone an independent security audit and should be hardened before any production deployment.

## Features

- Role-specific authentication and dashboards for administrators, receptionists, trainers, and members
- Administrative and reception account management
- Membership plans, memberships, and simulated payment records
- Trainer applications with administrator approval and rejection
- Class scheduling, review, enrollment, and attendance
- Reception check-in and check-out with visit history
- Responsive V3 interface built with Tailwind CSS, Flowbite, and Lucide icons
- Transactional PHP services, prepared statements, CSRF protection, and database constraints

## Repository layout

| Path | Purpose |
|---|---|
| [`v3/`](v3/) | Recommended presentation layer and browser entry point. It reuses the V2 backend and database. |
| [`v2/`](v2/) | Canonical PHP backend, ten-table schema, migrations, services, demo seeder, tests, and technical documentation. |
| [`v1/`](v1/) | Earlier application implementation retained for reference, with its own Composer dependencies, docs, and PHPUnit tests. |

Opening the repository root in a browser redirects to the V2 interface.

Start with V3 for the current user interface and V2 for backend, database, and workflow development.

## Technology

- PHP 8.1 or newer
- MySQL 8.0+ or MariaDB 10.5+
- Apache, XAMPP, or another PHP-capable web server
- Composer and PHPUnit
- Tailwind CSS 4, Flowbite 4, and Lucide icons for V3
- Node.js/npm only when rebuilding V3 frontend assets

## Quick start

### 1. Install dependencies

V2 and V3 require no Composer packages. Install dependencies only if you intend to run the legacy V1 implementation:

```bash
composer install --working-dir=v1
```

### 2. Configure V2 and V3

Copy the example environment file:

```bash
cp v2/.env.example v2/.env
```

Update `v2/.env` for your local database and URLs. Never commit this file.

Create the default development database:

```sql
CREATE DATABASE gym_system_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

### 3. Apply migrations

```bash
php v2/cli/migrate.php
```

Check migration status:

```bash
php v2/cli/migrate.php status
```

### 4. Load optional demo data

```bash
php v2/cli/seed-demo.php
```

The seeder creates fictional `example.test` accounts and simulated operational records for local demonstrations. Do not run it in production or reuse its passwords outside local development.

### 5. Open the application

With the default XAMPP folder and URLs:

- V3 interface: `http://localhost/gym_system/v3/`
- V2 interface: `http://localhost/gym_system/v2/`

On Windows, use `C:\xampp\php\php.exe` instead of `php` when PHP is not on `PATH`. See the complete [V2 setup guide](v2/README.md) for platform-specific instructions and troubleshooting.

## Demo accounts

The local seeder creates fictional accounts on the reserved `example.test` domain. The primary accounts are:

| Role | Email |
|---|---|
| Administrator | `admin.demo@example.test` |
| Receptionist | `reception.demo@example.test` |
| Trainer | `trainer.demo@example.test` |
| Member | `member.demo@example.test` |

The seeded password is documented in the [V2 demo guide](v2/README.md#demo-accounts). These credentials and the payment workflow are for local demonstration only.

## Testing

Run the legacy V1 PHPUnit suite:

```bash
composer test --working-dir=v1
```

Lint V1 PHP files:

```bash
composer lint --working-dir=v1
```

Run V2 database and workflow checks:

```bash
php v2/tests/schema_contract.php
```

```bash
php v2/tests/trainer_application_workflow.php
```

```bash
php v2/tests/staff_account_workflow.php
```

Run V3 frontend contracts:

```bash
php v3/tests/frontend_contract.php
```

```bash
php v3/tests/runtime_probe.php
```

To rebuild and check V3 assets:

```bash
cd v3 && npm ci && npm run build && npm run check:js
```

Some workflow and interface scenarios require manual verification. See [V2 testing](v2/docs/testing.md) and [V3 testing](v3/docs/testing.md) for the full checklists.

## Documentation

- [V2 architecture](v2/docs/architecture.md)
- [Entity-relationship model](v2/docs/erd.md)
- [Data dictionary](v2/docs/data-dictionary.md)
- [Business workflows](v2/docs/workflows.md)
- [V2 testing](v2/docs/testing.md)
- [V3 architecture](v3/docs/architecture.md)
- [V3 testing](v3/docs/testing.md)

## Security

Read [SECURITY.md](SECURITY.md) before reporting a vulnerability. Please use GitHub's private vulnerability reporting workflow rather than opening a public issue for an undisclosed security problem.

For deployment, use HTTPS, disable debug output, enable secure cookies, generate a private application key, protect environment values, and never load the demo seed data.

## Contributing

Contributions are welcome. Before opening a pull request, read:

- [Contributing guidelines](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Security policy](SECURITY.md)

## License

GymPro is available under the [MIT License](LICENSE).
