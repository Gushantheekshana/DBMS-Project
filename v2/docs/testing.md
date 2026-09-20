# Testing

## Fast checks

Run from the repository root:

```sh
php -l v2/cli/seed-demo.php
php -l v2/tests/schema_contract.php
php -l v2/tests/staff_account_workflow.php
php v2/tests/schema_contract.php
php v2/tests/staff_account_workflow.php
```

The schema contract is a standalone PHP script. It parses the migration, requires exactly the documented ten business `CREATE TABLE` statements plus `SCHEMA_MIGRATION`, checks every business column, primary/unique keys, foreign keys, and InnoDB/utf8mb4 declarations, and exits nonzero with readable failures.

## Optional database contract

When `DB_HOST`, `DB_NAME`, and database credentials are configured and the `mysqli` extension is loaded, the same script checks `INFORMATION_SCHEMA` for the exact table set and required columns in the selected live database. To require this phase (for example in integration CI), set:

```sh
SCHEMA_CONTRACT_REQUIRE_DB=1 php v2/tests/schema_contract.php
```

Without that flag, unavailable database configuration is reported as a skip; static checks still run.

Use a disposable database migrated from scratch. Never point tests or the demo seeder at production.

## Seeder verification

With `APP_ENV=local`, run the seeder twice. Both runs must succeed, row counts for reserved demo records must remain stable, all email addresses must end in `example.test`, and unrelated fixture rows must remain unchanged. Also verify that `APP_ENV=production php v2/cli/seed-demo.php` exits with status 1 before opening a database transaction.

On a database containing only this seed set, the five added scenarios produce five reserved records in each operational dataset and ten supporting accounts: five `MEMBER` and five `TRAINER` accounts. Together with the original scenario, expected reserved totals are 14 accounts, 6 members, 6 trainers, 6 plans, 6 memberships, 6 payments, 6 classes, 6 enrollments, 6 attendance rows, and 6 completed seed visits. The six plans must be Basic Fitness, Standard Plus, Premium, VIP Elite, Family Plan, and Student Plan with their documented LKR prices, durations, descriptions, and class limits; Premium, VIP Elite, and Family Plan use `NULL` to represent unlimited classes. Additional unrelated plans and memberships are allowed and must be preserved. Verify every added membership/payment/class/enrollment/attendance/visit relationship and confirm that no role outside `ADMIN`, `RECEPTIONIST`, `TRAINER`, and `MEMBER` exists.

## Application scenarios

At minimum, automate or manually verify:

- each role can sign in and cannot access another role's restricted routes;
- an active administrator can create Administrative (`ADMIN`) and Receptionist (`RECEPTIONIST`) accounts from Staff, while non-admin, inactive, and direct-post actors remain blocked;
- staff creation rejects invalid CSRF, password mismatch/length errors, duplicate normalized email, and tampered roles without storing passwords in old input or creating partial rows;
- provisioned staff are active and verified, have no member/trainer profile, appear once after redirect/refresh, and can sign in only through their matching role portal;
- the Staff form remains labeled, keyboard-usable, aligned across inputs, and responsive while the account table retains horizontal overflow on narrow screens;
- an administrator can remove staff accounts using a contextual confirmation dialog, while `admin.demo@example.test`, self-deletion, and last-active-admin lockout remain strictly blocked;
- unreferenced staff accounts are permanently deleted, while accounts with operational records (visits, attendance, payments, trainer reviews) are safely disabled;
- a newly registered trainer appears in the administrator's Trainer requests queue, receives the approval-pending login message, and cannot create a session;
- approval activates both the trainer profile and account before login/class creation succeeds;
- rejection keeps login blocked, records a reason, and permits a revised same-email application that returns to pending;
- duplicate emails, payment references, and member/class enrollments are rejected;
- an inactive account or inactive plan cannot begin an active membership;
- class capacity and start/end validation are enforced;
- class attendance cannot refer to a different class than its enrollment;
- payment and membership activation roll back together on failure;
- HTML output is escaped and mutating requests reject invalid CSRF tokens;
- administrator class approval/rejection and trainer enrollment acceptance/decline require the contextual confirmation dialog before submission;
- each dialog supports Cancel, Escape, backdrop cancellation, safe initial focus, focus restoration, and responsive narrow-screen layout;
- repeated confirmation attempts cause only one effective mutation, while stale decisions remain rejected by the service;
- successful decisions display action-specific toast feedback and server failures remain readable and recoverable;
- routine registration, login, class creation, attendance saving, enrollment requests, checkout, and reception check-in/check-out do not display confirmation dialogs;
- routine successful mutations provide toast or receipt feedback, and reduced-motion mode does not hide state changes.
