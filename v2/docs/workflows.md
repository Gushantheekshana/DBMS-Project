# Workflows

## Account and profile lifecycle

1. An administrator or registration flow creates a `MEMBER` or `TRAINER` profile.
2. A `USER_ACCOUNT` is created with a unique normalized email and a `password_hash()` value.
3. The account links to exactly the appropriate profile; administrator and receptionist accounts have neither link.
4. A self-registering trainer and linked account begin in `PENDING`, so valid credentials show an approval-pending message without creating a session.
5. An active `ADMIN` reviews the request: approval makes both records `ACTIVE`; rejection makes the trainer `REJECTED` and disables the account.
6. A rejected trainer may submit revised details with the same email. The existing linked records return to `PENDING`, preserving their identifiers and history.
7. Sign-in verifies the hash and checks both role and active status. Only an approved trainer can sign in and perform trainer operations such as creating classes.
8. Other deactivation changes status instead of deleting operational history.

## Staff account provisioning

1. An active administrator opens the Staff workspace and submits a CSRF-protected account form.
2. The service verifies that the acting account is still an active `ADMIN`; route authorization alone is not trusted.
3. The administrator may create only an Administrative account, stored as `ADMIN`, or a Receptionist account, stored as `RECEPTIONIST`.
4. The email is normalized and remains globally unique, while the password is stored only as a `password_hash()` value.
5. Provisioned staff accounts are immediately `ACTIVE` and email-verified because V2 has no invitation or email-verification workflow.
6. Administrator and receptionist accounts have no `MEMBER` or `TRAINER` profile row and sign in only through their matching role portal.

## Staff account removal

1. An active administrator selects Remove for an eligible staff account, which opens a contextual confirmation dialog.
2. The primary demo administrator (`admin.demo@example.test`) is protected and can never be removed or disabled.
3. Self-removal is strictly blocked so an administrator cannot remove their own currently logged-in account.
4. The last active administrator account cannot be removed, preventing total administrative lockout.
5. If the target staff account has recorded operational activity (`GYM_VISIT` check-ins/check-outs, `CLASS_ATTENDANCE` marked, `MEMBERSHIP_PAYMENT` recorded, or `TRAINER` reviewed), the service updates its status to `DISABLED` instead of hard-deleting, preserving audit attribution and foreign key integrity.
6. If the target staff account has zero recorded operational activity, the service permanently deletes the `USER_ACCOUNT` row.

## Membership purchase

1. The member selects an active `MEMBERSHIP_PLAN`.
2. A `MEMBERSHIP` is created as pending or active with a bounded start/end interval and plan snapshots.
3. Payment processing inserts one `MEMBERSHIP_PAYMENT` with unique transaction and idempotency references.
4. On success, the payment becomes `PAID` and the membership becomes `ACTIVE` in the same transaction.
5. Retries use the idempotency key to avoid duplicate charges.

## Class scheduling and enrollment

1. A `GYM_CLASS` is created with one trainer, start/end times, capacity, and status.
2. A member requests a `CLASS_ENROLLMENT`.
3. The service checks that the member is active, the class is scheduled, the membership covers the class date, and the member is not already enrolled.
4. A new request is `WAITLISTED`; the trainer confirms an Accept or Decline decision in a contextual dialog. Acceptance moves it to `ENROLLED` after rechecking entitlement and capacity, while decline retains the row as `CANCELLED`.
5. An administrator likewise confirms approval or rejection of each draft class before it becomes `SCHEDULED` or `CANCELLED`.
6. Successful decisions appear as non-blocking toast feedback; routine class creation and member enrollment requests do not add unnecessary confirmation steps.
7. A unique member/class key makes request retries safe.

## Attendance

1. The assigned trainer opens an enrolled class roster.
2. The service requires the submitted roster to match every `ENROLLED` row for the class.
3. It inserts or updates each attendance status, records the acting account, and sets `CheckedInAt` for `PRESENT` or `LATE`.
4. The composite attendance foreign key ensures each `ClassEnrollmentID` belongs to the supplied `GymClassID`.
5. Separate receptionist check-in/check-out activity is stored in `GYM_VISIT`; the generated `OpenVisitMarker` value supports one-open-visit enforcement.

## Demo data

`php v2/cli/seed-demo.php` is available only in `APP_ENV=local`. It retains the original one-per-role demonstration accounts and adds five connected fictional scenarios numbered `101` through `105`. Each added scenario includes a member and trainer account/profile, plan, membership, payment, completed class, enrollment, attendance record, and completed gym visit. Every email ends in `example.test`, every account uses `Demo@12345`, and only the existing `ADMIN`, `RECEPTIONIST`, `TRAINER`, and `MEMBER` roles are used. Re-running refreshes reserved records without truncating tables or modifying unrelated rows.
