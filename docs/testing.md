# Testing and demonstration

## Automated checks

```bash
C:/xampp/php/php.exe -l bootstrap/app.php
```

```bash
C:/xampp/php/php.exe cli/migrate.php
```

```bash
C:/xampp/php/php.exe cli/run-scheduled.php
```

After Composer is installed:

```bash
composer test
```

## Seeded role demonstration

Populate a coherent local dataset without removing existing records:

```bash
C:/xampp/php/php.exe cli/seed-demo.php
```

Start at `/auth/login.php`, choose the matching role portal, and use `Demo@12345` with `member.demo@gmail.com`, `trainer.demo@gmail.com`, `reception.demo@gmail.com`, or `admin.demo@gmail.com`. Verify that each account succeeds only through its own portal and that cross-role credentials return the generic invalid-credentials response without creating a session. The seed command may be rerun: stable demo keys prevent duplicate accounts, payments, classes, enrollments, attendance, notifications, commissions, and payouts.

The member account has an active all-access subscription, completed and upcoming class enrollments, attendance history, notifications, and trainer/support conversations. A separate future enrollment remains `PENDING`, so the trainer immediately sees working accept/reject controls. The trainer owns those classes and has a paid commission statement. Reception can find the active member and record a new visit. Super admin can inspect all seeded operational and audit views and reply through the shared support inbox.

## Full onboarding demonstration

1. Register a member with Gmail, retrieve the development verification URL from `storage/logs/mail.log`, verify, and sign in.
2. Select a plan, use any syntactically valid demo card, and confirm activation and immutable payment reference.
3. Register a trainer with a PDF/JPG/PNG qualification and verify Gmail.
4. As super admin, view the protected document and approve the trainer.
5. As trainer, submit a non-overlapping class with capacity no greater than 10.
6. As super admin, approve the class.
7. As active member with class access, request enrollment; as trainer, accept it.
8. As super admin create a receptionist, then check the member in and out.
9. As trainer, mark class attendance and complete the session; verify one commission record.
10. As super admin, record a payout and inspect the audit log.
11. Test denied routes between roles, invalid CSRF, duplicate check-in, class capacity, duplicate payment submission, and scheduler idempotency.
12. Test desktop, tablet, and mobile navigation plus keyboard focus and table scrolling.
