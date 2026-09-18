# Architecture

## Layers

- Root and role folders contain thin server-rendered HTTP entry points.
- `bootstrap/app.php` owns configuration, sessions, DB helpers, CSRF, authentication guards, notifications, and common output utilities.
- `app/Services` owns transactional business rules for authentication, subscriptions, classes, enrollment, attendance, messaging, and commissions. `MessageService` is the authorization boundary for every conversation read or write.
- `app/Mail` isolates Gmail SMTP.
- `database/migrations` is the canonical additive schema history.
- `cli` contains privileged setup and idempotent scheduled operations.
- `storage/trainer-documents` and `storage/logs` are denied direct web access.

## Roles

| Capability | Member | Trainer | Receptionist | Super admin |
|---|---:|---:|---:|---:|
| View own membership/attendance | Yes | No | Limited search | Yes |
| Request an approved class | Yes | No | No | Override later |
| Create classes | No | Own | No | Review |
| Accept class requests | No | Own classes | No | Oversight |
| Mark class attendance | No | Own classes | No | Oversight |
| General gym check-in/out | View | No | Yes | Yes |
| Send notifications | No | Own class students | No | Role groups/all |
| Two-way messages | Support/class trainer | Own class members | No | Shared support queue |
| View commissions | No | Own | No | All/payouts |
| Approve trainer/class | No | No | No | Yes |

## Main relationships

```text
USER_ACCOUNT -> MEMBER or TRAINER profile
MEMBER -> SUBSCRIPTION -> MEMBERSHIP_PLAN
SUBSCRIPTION -> MEMBERSHIP_PAYMENT
TRAINER -> CLASS -> CLASS_SESSION
MEMBER -> ENROLLMENT -> CLASS
ENROLLMENT + CLASS_SESSION -> CLASS_ATTENDANCE
MEMBER -> GYM_ATTENDANCE
TRAINER -> COMMISSION_ENTRY -> PAYOUT_ITEM -> PAYOUT
NOTIFICATION -> NOTIFICATION_RECIPIENT -> USER_ACCOUNT
MEMBER -> CONVERSATION -> CONVERSATION_MESSAGE
CONVERSATION -> CLASS + TRAINER (class channel only)
CONVERSATION -> CONVERSATION_PARTICIPANT -> USER_ACCOUNT
```

Legacy tables/columns remain available during reconciliation. Modern writes use the new lifecycle tables.
