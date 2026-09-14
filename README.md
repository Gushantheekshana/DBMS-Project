# GymPro

GymPro is a role-based gym membership management system built for a third-year university project. It uses PHP 8.1+, MariaDB/MySQL, server-rendered HTML, and a responsive custom design system.

## Product scope

- Member self-registration, Gmail-only verification, membership plans, 72-hour payment window, simulated card checkout, renewals, class requests, notifications, attendance history, and two-way messages with GymPro Support or eligible class trainers
- Trainer self-registration with protected qualification documents, super-admin approval, class creation, enrollment decisions, class attendance, student notifications, class-scoped member messages, and commission statements
- Receptionist member search and general gym check-in/check-out
- Super-admin trainer/class approvals, plans, member/payment oversight, staff accounts, announcements, payouts, and audit records
- CLI maintenance for account/subscription expiry and 30-day anonymization

There is no personal-trainer assignment module. Trainers are associated with members through classes. Each class has a maximum of 10 enrolled students.

## Requirements

- XAMPP with PHP 8.1+ (`mysqli`, `fileinfo`, `openssl`)
- MariaDB 10.4+ or MySQL 8+
- Composer (recommended, required to send real SMTP email and run PHPUnit)

## Installation

1. Back up an existing database. A modernization backup is stored locally under `storage/backups/` and is ignored by version control.
2. Copy `.env.example` to `.env` and update database settings.
3. Install dependencies:

```bash
composer install
```

4. Apply additive migrations:

```bash
C:/xampp/php/php.exe cli/migrate.php
```

5. Create the initial super admin if one does not exist:

```bash
C:/xampp/php/php.exe cli/bootstrap-admin.php admin@gmail.com 'A-strong-password'
```

6. For a populated local demonstration, run the additive demo seeder:

```bash
C:/xampp/php/php.exe cli/seed-demo.php
```

7. Start Apache and MySQL in XAMPP and visit `http://localhost/gym_system/`.

## Demo data and role accounts

`cli/seed-demo.php` is CLI-only, local-environment restricted, transactional, lock-protected, and safe to rerun. It creates or refreshes only records identified by reserved `Demo` names and `*.demo@gmail.com` addresses; it does not truncate or delete existing members, trainers, classes, payments, or audit history.

All seeded accounts use the password `Demo@12345`:

| Role | Email |
|---|---|
| Member | `member.demo@gmail.com` |
| Trainer | `trainer.demo@gmail.com` |
| Receptionist | `reception.demo@gmail.com` |
| Super admin | `admin.demo@gmail.com` |

The dataset includes an all-access plan, active subscription, simulated payment, future and completed classes, accepted enrollments, a genuine pending trainer request, gym and class attendance, trainer/support conversations, read/unread notifications, paid commission, and a payout. Dates are refreshed relative to the day the command runs so dashboards remain useful.

Open `/auth/login.php` to choose the member, trainer, receptionist, or super-admin sign-in portal. Each portal enforces its expected role on the server; credentials for one role cannot be used through another role's page. In `APP_ENV=local`, each page shows only its matching demo account. Change or remove all demonstration credentials before any public deployment. The separately bootstrapped `admin@gmail.com` account is left unchanged.

## Gmail SMTP

Use a dedicated Gmail account with two-step verification and a Google app password. Set `MAIL_ENABLED=true`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` in `.env`. Never commit `.env`.

When mail is disabled, verification links are written to `storage/logs/mail.log` for local development. This log is not web-accessible and is ignored by version control.

## Simulated payment disclaimer

The checkout is an educational simulation. It accepts syntactically complete demonstration card values but does not contact a payment provider. The full number, expiry, and CVV are never stored or logged; only a demo brand and last four digits may be retained.

## Scheduled maintenance

Run this hourly through Windows Task Scheduler:

```bash
C:/xampp/php/php.exe C:/xampp/htdocs/gym_system/cli/run-scheduled.php
```

The command is lock-protected and idempotent. It expires missed payment windows and subscriptions, cleans old tokens, and anonymizes rejected/expired registrations after 30 days.

## Security notes

- All role routes enforce authentication and authorization on the server.
- State changes use POST and CSRF protection in the modern workflows.
- New SQL uses prepared statements and transactional services.
- Trainer files live outside direct web access and are streamed only to super admins.
- Legacy CRUD pages are restricted to super admin through `config.php` while they are phased out.
- `setup.php` and `seed.php` are disabled on the web.

See [docs/architecture.md](docs/architecture.md), [docs/workflows.md](docs/workflows.md), and [docs/testing.md](docs/testing.md).
