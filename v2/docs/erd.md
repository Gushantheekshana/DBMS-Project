# Entity relationship diagram

```mermaid
erDiagram
    USER_ACCOUNT ||--o| MEMBER : owns
    USER_ACCOUNT ||--o| TRAINER : owns
    MEMBERSHIP_PLAN ||--o{ MEMBERSHIP : defines
    MEMBER ||--o{ MEMBERSHIP : holds
    MEMBERSHIP ||--o{ MEMBERSHIP_PAYMENT : receives
    USER_ACCOUNT ||--o{ MEMBERSHIP_PAYMENT : records
    TRAINER ||--o{ GYM_CLASS : teaches
    MEMBER ||--o{ CLASS_ENROLLMENT : requests
    GYM_CLASS ||--o{ CLASS_ENROLLMENT : has
    CLASS_ENROLLMENT ||--o| CLASS_ATTENDANCE : records
    USER_ACCOUNT ||--o{ CLASS_ATTENDANCE : marks
    MEMBER ||--o{ GYM_VISIT : makes
    USER_ACCOUNT ||--o{ GYM_VISIT : processes

    USER_ACCOUNT {
        bigint UserAccountID PK
        varchar Email UK
        varchar PasswordHash
        enum Role
        enum Status
    }
    MEMBER {
        bigint MemberID PK
        bigint UserAccountID FK,UK
        varchar MemberNumber UK
        varchar FirstName
        varchar LastName
        date JoinedOn
        enum Status
    }
    TRAINER {
        bigint TrainerID PK
        bigint UserAccountID FK,UK
        varchar TrainerNumber UK
        varchar FirstName
        varchar LastName
        varchar Specialization
        enum Status
    }
    MEMBERSHIP_PLAN {
        bigint MembershipPlanID PK
        varchar Name UK
        int DurationMonths
        decimal Price
        char Currency
        int ClassLimit
        boolean IsActive
    }
    MEMBERSHIP {
        bigint MembershipID PK
        bigint MemberID FK
        bigint MembershipPlanID FK
        varchar PlanNameSnapshot
        decimal PriceSnapshot
        char CurrencySnapshot
        date StartsOn
        date EndsOn
        enum Status
    }
    MEMBERSHIP_PAYMENT {
        bigint MembershipPaymentID PK
        bigint MembershipID FK
        decimal Amount
        char Currency
        enum Status
        enum Method
        varchar TransactionReference UK
        varchar IdempotencyKey UK
        bigint RecordedByUserAccountID FK
    }
    GYM_CLASS {
        bigint GymClassID PK
        bigint TrainerID FK
        varchar Name
        datetime StartsAt
        datetime EndsAt
        int Capacity
        varchar Location
        enum Status
    }
    CLASS_ENROLLMENT {
        bigint ClassEnrollmentID PK
        bigint GymClassID FK
        bigint MemberID FK
        enum Status
        datetime EnrolledAt
        datetime CancelledAt
    }
    CLASS_ATTENDANCE {
        bigint ClassAttendanceID PK
        bigint ClassEnrollmentID FK
        bigint GymClassID FK
        enum Status
        datetime CheckedInAt
        bigint MarkedByUserAccountID FK
    }
    GYM_VISIT {
        bigint GymVisitID PK
        bigint MemberID FK
        datetime CheckedInAt
        datetime CheckedOutAt
        bigint CheckedInByUserAccountID FK
        bigint CheckedOutByUserAccountID FK
        boolean OpenVisitMarker UK
    }
```

`CLASS_ATTENDANCE` uses a composite foreign key (`ClassEnrollmentID`, `GymClassID`) so an attendance record cannot name a different class from its enrollment. Administrative and receptionist accounts have no profile row. Member and trainer profiles reference their account, preserving one authentication table without exceeding the ten-business-table boundary.
