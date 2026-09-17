# Data dictionary

GymPro v2 owns exactly ten business tables plus the `SCHEMA_MIGRATION` metadata table. Primary identifiers are auto-incrementing; `CreatedAt`/`UpdatedAt` are lifecycle timestamps wherever listed.

## `USER_ACCOUNT`

| Columns | Meaning |
|---|---|
| `UserAccountID` | Primary key. |
| `Email` | Unique login address. |
| `PasswordHash` | One-way password hash. |
| `Role` | `ADMIN`, `RECEPTIONIST`, `TRAINER`, or `MEMBER`. |
| `Status` | `PENDING`, `ACTIVE`, `SUSPENDED`, or `DISABLED`. |
| `EmailVerifiedAt`, `LastLoginAt`, `FailedLoginCount`, `LockedUntil` | Verification, login activity, and lockout fields. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `MEMBER`

| Columns | Meaning |
|---|---|
| `MemberID` | Primary key. |
| `UserAccountID` | Unique account reference. |
| `MemberNumber` | Unique public identifier. |
| `FirstName`, `LastName`, `DateOfBirth`, `Phone` | Member identity/contact data. |
| `EmergencyContactName`, `EmergencyContactPhone` | Optional emergency contact. |
| `JoinedOn`, `Status` | Join date and `ACTIVE`, `INACTIVE`, `SUSPENDED`, or `ARCHIVED` lifecycle state. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `TRAINER`

| Columns | Meaning |
|---|---|
| `TrainerID` | Primary key. |
| `UserAccountID` | Unique account reference. |
| `TrainerNumber` | Unique public identifier. |
| `FirstName`, `LastName`, `Phone` | Trainer identity/contact data. |
| `Specialization`, `Bio` | Professional profile. |
| `HireDate`, `Status` | Optional hire date and `PENDING`, `ACTIVE`, `INACTIVE`, or `ARCHIVED` state. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `MEMBERSHIP_PLAN`

| Columns | Meaning |
|---|---|
| `MembershipPlanID`, `Name` | Primary key and unique display name. |
| `Description`, `DurationMonths`, `Price`, `Currency` | Terms, non-negative price, and uppercase ISO-style currency code. |
| `ClassLimit` | Nullable class allowance for the membership interval. |
| `IsActive` | Availability for new memberships. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `MEMBERSHIP`

| Columns | Meaning |
|---|---|
| `MembershipID` | Primary key. |
| `MemberID`, `MembershipPlanID` | Member and selected plan references. |
| `PlanNameSnapshot`, `PriceSnapshot`, `CurrencySnapshot` | Purchase-time terms. |
| `StartsOn`, `EndsOn` | Inclusive service dates. |
| `Status` | `PENDING`, `ACTIVE`, `PAUSED`, `CANCELLED`, or `EXPIRED`. |
| `CancelledAt` | Cancellation timestamp when cancelled. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `MEMBERSHIP_PAYMENT`

| Columns | Meaning |
|---|---|
| `MembershipPaymentID`, `MembershipID` | Primary key and membership reference. |
| `Amount`, `Currency`, `Status`, `Method` | Payment facts; statuses are `PENDING`, `PAID`, `FAILED`, `REFUNDED`, and `VOID`. |
| `TransactionReference`, `IdempotencyKey` | Unique transaction and retry identifiers. |
| `PaidAt`, `RecordedByUserAccountID`, `Notes` | Settlement time, recording actor, and optional notes. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `GYM_CLASS`

| Columns | Meaning |
|---|---|
| `GymClassID`, `TrainerID` | Primary key and optional assigned trainer. |
| `Name`, `Description`, `Location` | Presentation and venue. |
| `StartsAt`, `EndsAt`, `Capacity` | Scheduled interval and positive limit. |
| `Status` | `DRAFT`, `SCHEDULED`, `CANCELLED`, or `COMPLETED`. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `CLASS_ENROLLMENT`

| Columns | Meaning |
|---|---|
| `ClassEnrollmentID`, `GymClassID`, `MemberID` | Primary key and unique class/member pair. |
| `Status` | `ENROLLED`, `WAITLISTED`, or `CANCELLED`. |
| `EnrolledAt`, `CancelledAt` | Enrollment and optional cancellation times. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `CLASS_ATTENDANCE`

| Columns | Meaning |
|---|---|
| `ClassAttendanceID` | Primary key. |
| `ClassEnrollmentID`, `GymClassID` | Composite reference to the enrollment and its class. |
| `Status` | `PRESENT`, `ABSENT`, `LATE`, or `EXCUSED`. |
| `CheckedInAt`, `MarkedByUserAccountID`, `Notes` | Check-in time, acting account, and optional notes. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |

## `GYM_VISIT`

| Columns | Meaning |
|---|---|
| `GymVisitID`, `MemberID` | Primary key and visiting member. |
| `CheckedInAt`, `CheckedOutAt` | Visit interval. |
| `CheckedInByUserAccountID`, `CheckedOutByUserAccountID` | Reception actors. |
| `Notes` | Optional visit notes. |
| `OpenVisitMarker` | Generated marker for an unclosed visit; its unique key permits one open visit per member. |
| `CreatedAt`, `UpdatedAt` | Lifecycle timestamps. |
