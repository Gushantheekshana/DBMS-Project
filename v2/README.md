# GymPro v2

GymPro v2 is a focused PHP and MariaDB/MySQL gym-management application built for a university project. Its ten-table business schema supports role-based accounts, member profiles, membership plans and payments, trainers and classes, class enrollment and attendance, and reception check-in/check-out.

The V2 application is isolated in this directory; it does not replace the legacy project outside `v2/`.

## Requirements

- PHP 8.1 or newer with the `mysqli` extension
- MariaDB 10.5+ or MySQL 8.0+
- Apache, the PHP development server, or another PHP-capable web server
- XAMPP is supported on Windows

## XAMPP setup on Windows

Run these steps from the `v2` directory unless stated otherwise.

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy `.env.example` to `.env`.
3. Keep the default root credentials for a standard local XAMPP installation, or update `DB_USER` and `DB_PASSWORD` in `.env`.
4. Create an empty database named `gym_system_v2`:

   ```sql
   CREATE DATABASE gym_system_v2
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

5. Apply the schema and inspect its status:

   ```sh
   C:\xampp\php\php.exe cli\migrate.php
   C:\xampp\php\php.exe cli\migrate.php status
   ```

6. Load the fictional demonstration records. The command is safe to run again because it updates only its reserved demo records and never truncates unrelated data:

   ```sh
   C:\xampp\php\php.exe cli\seed-demo.php
   ```

7. Open `http://localhost/gym_system/v2/`.

If `mysql` or `php` is already on your `PATH`, the equivalent commands are `php cli/migrate.php` and `php cli/seed-demo.php`.

## Trainer application approval

Trainers may apply from the public registration page. A new application remains `PENDING`, and the trainer cannot sign in or create classes until an administrator approves it from **Trainer requests**. Administrators can approve or reject applications. A rejected applicant may revise and resubmit using the same email address; the account remains blocked until the new request is approved.

## Demo accounts

All addresses use the reserved `example.test` domain and share the password `Demo@12345`.

| Role | Email |
|---|---|
| Administrator | `admin.demo@example.test` |
| Receptionist | `reception.demo@example.test` |
| Trainer | `trainer.demo@example.test` |
| Member | `member.demo@example.test` |
| Added trainers | `trainer.demo.101@example.test` through `trainer.demo.105@example.test` |
| Added members | `member.demo.101@example.test` through `member.demo.105@example.test` |

The expanded seed set adds five connected fictional scenarios. Each includes a member, trainer, plan, active membership, paid simulated payment, completed class, enrollment, attendance record, and completed gym visit. It uses only the four existing roles and does not add role types. These credentials are for local demonstration only; do not reuse the password in a real deployment.

## Data model

The schema has ten business tables:

1. `USER_ACCOUNT`
2. `MEMBER`
3. `TRAINER`
4. `MEMBERSHIP_PLAN`
5. `MEMBERSHIP`
6. `MEMBERSHIP_PAYMENT`
7. `GYM_CLASS`
8. `CLASS_ENROLLMENT`
9. `CLASS_ATTENDANCE`
10. `GYM_VISIT`

`SCHEMA_MIGRATION` is an additional infrastructure table used only to track migration checksums and status. It is not part of the ten-table business model.

See [`docs/architecture.md`](docs/architecture.md), [`docs/erd.md`](docs/erd.md), [`docs/data-dictionary.md`](docs/data-dictionary.md), and [`docs/workflows.md`](docs/workflows.md).

## Verification

Run the schema contract from this directory:

```sh
C:\xampp\php\php.exe tests\schema_contract.php
```

The test always validates the migration SQL. When `.env` contains reachable database settings, it also checks the live `INFORMATION_SCHEMA` metadata. To require the live checks in Git Bash:

```sh
SCHEMA_CONTRACT_REQUIRE_DB=true /c/xampp/php/php.exe tests/schema_contract.php
```

Run a syntax check for an individual PHP file with:

```sh
C:\xampp\php\php.exe -l cli\seed-demo.php
```

See [`docs/testing.md`](docs/testing.md) for the complete manual workflow checklist.

## Technical PDF

A self-contained technical report explains the V2 architecture, all ten business tables, database constraints, service implementations, role workflows, security controls, testing, and current limitations:

- [`docs/GymPro_V2_Technical_Documentation.pdf`](docs/GymPro_V2_Technical_Documentation.pdf)

To regenerate it from the repository root, install the pinned local documentation dependency and run the generator:

```sh
python -m pip install -r v2/docs/requirements-pdf.txt
python v2/docs/generate_v2_documentation.py
```

The generator uses only local ReportLab resources, does not read `.env`, and writes the PDF beside the script.

## Troubleshooting

- **Connection refused:** start MySQL in the XAMPP Control Panel and confirm `DB_HOST=127.0.0.1` and `DB_PORT=3306`.
- **Access denied:** set the matching local MariaDB username and password in `.env`.
- **Unknown database:** create `gym_system_v2` before running the migrator.
- **`php` is not recognized:** use `C:\xampp\php\php.exe` as shown above.
- **Incorrect links when using a different port or folder:** update `APP_URL` in `.env` to the exact V2 URL.
- **Production use:** set `APP_ENV=production`, disable debug output, use HTTPS, set `SESSION_SECURE=true`, generate a private `APP_KEY`, and do not run the demo seeder.
