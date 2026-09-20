# Workflows

## Member lifecycle

`PENDING_EMAIL -> PENDING_PAYMENT -> ACTIVE -> EXPIRED`

The 72-hour deadline begins only after email verification. An expired member may sign in, select a new plan, and use simulated checkout. Active subscription dates and plan features control gym/class access.

## Trainer lifecycle

`PENDING_EMAIL -> PENDING_APPROVAL -> ACTIVE`

A super admin may instead set `NEEDS_INFORMATION` or `REJECTED`. Qualification documents are private. Only active approved trainers can perform trainer operations.

## Class lifecycle

`PENDING_APPROVAL -> SCHEDULED -> COMPLETED`

A super admin may reject a submitted class. Trainer schedule overlap is rejected. Capacity is 1–10. Approval creates the class session.

## Enrollment lifecycle

`PENDING -> ENROLLED | REJECTED`

Members need an active plan with class access, a future scheduled class, and an active login-enabled owning trainer. The owning trainer sees pending requests separately and decides. Acceptance locks relevant rows and counts enrolled seats so concurrent decisions cannot exceed 10. The legacy `TRAINER_REQUEST` table is retained only for reconciliation and is not part of this class-focused workflow.

## Messaging

Notifications remain announcements and system alerts. Conversations provide two-way messages with independent unread state. An active member may contact the shared super-admin support queue, or the trainer of a class where their enrollment is `PENDING` or `ENROLLED`. Trainers can read and reply only within their own class conversations; super admins can read and reply only to support conversations. All recipient and ownership rules are revalidated on the server.

## Attendance

Receptionists record general check-in/out only after active gym-access validation. One open visit is allowed per member. Trainers mark `PRESENT`, `ABSENT`, `LATE`, or `EXCUSED` only for students enrolled in their own class session.

## Commission

Completing a class session creates at most one immutable commission entry using the active rule snapshot. A super-admin payout allocates all earned, unpaid entries exactly once.

## Retention

Expired member registrations and rejected trainer applications remain restricted for 30 days. The scheduler then removes direct personal identifiers and trainer files while retaining minimal relational, audit, and financial history. Records subject to a dispute hold should be excluded before production use.
