-- Gym System v2 canonical schema: MariaDB 10.5+ / MySQL 8.0+
-- Ten business tables plus SCHEMA_MIGRATION metadata.

CREATE TABLE IF NOT EXISTS SCHEMA_MIGRATION (
    MigrationID VARCHAR(190) NOT NULL,
    Checksum CHAR(64) NOT NULL,
    StartedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AppliedAt DATETIME NULL,
    ExecutionMilliseconds INT UNSIGNED NULL,
    IsDirty TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (MigrationID),
    KEY idx_schema_migration_applied (IsDirty, AppliedAt),
    CONSTRAINT chk_schema_migration_dirty CHECK (IsDirty IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE USER_ACCOUNT (
    UserAccountID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Email VARCHAR(254) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('ADMIN', 'RECEPTIONIST', 'TRAINER', 'MEMBER') NOT NULL,
    Status ENUM('PENDING', 'ACTIVE', 'SUSPENDED', 'DISABLED') NOT NULL DEFAULT 'PENDING',
    EmailVerifiedAt DATETIME NULL,
    LastLoginAt DATETIME NULL,
    FailedLoginCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    LockedUntil DATETIME NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (UserAccountID),
    UNIQUE KEY uq_user_account_email (Email),
    KEY idx_user_account_role_status (Role, Status),
    KEY idx_user_account_locked_until (LockedUntil),
    CONSTRAINT chk_user_failed_logins CHECK (FailedLoginCount <= 1000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MEMBER (
    MemberID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    UserAccountID BIGINT UNSIGNED NOT NULL,
    MemberNumber VARCHAR(24) NOT NULL,
    FirstName VARCHAR(80) NOT NULL,
    LastName VARCHAR(80) NOT NULL,
    DateOfBirth DATE NULL,
    Phone VARCHAR(32) NULL,
    EmergencyContactName VARCHAR(160) NULL,
    EmergencyContactPhone VARCHAR(32) NULL,
    JoinedOn DATE NOT NULL,
    Status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (MemberID),
    UNIQUE KEY uq_member_account (UserAccountID),
    UNIQUE KEY uq_member_number (MemberNumber),
    KEY idx_member_name (LastName, FirstName),
    KEY idx_member_status (Status),
    CONSTRAINT fk_member_account FOREIGN KEY (UserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE TRAINER (
    TrainerID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    UserAccountID BIGINT UNSIGNED NOT NULL,
    TrainerNumber VARCHAR(24) NOT NULL,
    FirstName VARCHAR(80) NOT NULL,
    LastName VARCHAR(80) NOT NULL,
    Phone VARCHAR(32) NULL,
    Specialization VARCHAR(160) NULL,
    Bio TEXT NULL,
    HireDate DATE NULL,
    Status ENUM('PENDING', 'ACTIVE', 'INACTIVE', 'ARCHIVED') NOT NULL DEFAULT 'PENDING',
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (TrainerID),
    UNIQUE KEY uq_trainer_account (UserAccountID),
    UNIQUE KEY uq_trainer_number (TrainerNumber),
    KEY idx_trainer_name (LastName, FirstName),
    KEY idx_trainer_status (Status),
    CONSTRAINT fk_trainer_account FOREIGN KEY (UserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MEMBERSHIP_PLAN (
    MembershipPlanID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL,
    Description TEXT NULL,
    DurationMonths SMALLINT UNSIGNED NOT NULL,
    Price DECIMAL(12,2) UNSIGNED NOT NULL,
    Currency CHAR(3) NOT NULL DEFAULT 'USD',
    ClassLimit SMALLINT UNSIGNED NULL,
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (MembershipPlanID),
    UNIQUE KEY uq_membership_plan_name (Name),
    KEY idx_membership_plan_active (IsActive),
    CONSTRAINT chk_plan_duration CHECK (DurationMonths > 0),
    CONSTRAINT chk_plan_price CHECK (Price >= 0),
    CONSTRAINT chk_plan_currency CHECK (Currency = UPPER(Currency)),
    CONSTRAINT chk_plan_active CHECK (IsActive IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MEMBERSHIP (
    MembershipID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MemberID BIGINT UNSIGNED NOT NULL,
    MembershipPlanID BIGINT UNSIGNED NOT NULL,
    PlanNameSnapshot VARCHAR(100) NOT NULL,
    PriceSnapshot DECIMAL(12,2) UNSIGNED NOT NULL,
    CurrencySnapshot CHAR(3) NOT NULL,
    StartsOn DATE NOT NULL,
    EndsOn DATE NOT NULL,
    Status ENUM('PENDING', 'ACTIVE', 'PAUSED', 'CANCELLED', 'EXPIRED') NOT NULL DEFAULT 'PENDING',
    CancelledAt DATETIME NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (MembershipID),
    KEY idx_membership_member_status (MemberID, Status),
    KEY idx_membership_status_end (Status, EndsOn),
    KEY idx_membership_plan (MembershipPlanID),
    CONSTRAINT fk_membership_member FOREIGN KEY (MemberID)
        REFERENCES MEMBER (MemberID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_membership_plan FOREIGN KEY (MembershipPlanID)
        REFERENCES MEMBERSHIP_PLAN (MembershipPlanID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_membership_dates CHECK (EndsOn >= StartsOn),
    CONSTRAINT chk_membership_price CHECK (PriceSnapshot >= 0),
    CONSTRAINT chk_membership_currency CHECK (CurrencySnapshot = UPPER(CurrencySnapshot))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MEMBERSHIP_PAYMENT (
    MembershipPaymentID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MembershipID BIGINT UNSIGNED NOT NULL,
    Amount DECIMAL(12,2) UNSIGNED NOT NULL,
    Currency CHAR(3) NOT NULL,
    Status ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED', 'VOID') NOT NULL DEFAULT 'PENDING',
    Method ENUM('CASH', 'CARD', 'BANK_TRANSFER', 'OTHER') NOT NULL,
    TransactionReference VARCHAR(100) NULL,
    IdempotencyKey VARCHAR(100) NULL,
    PaidAt DATETIME NULL,
    RecordedByUserAccountID BIGINT UNSIGNED NULL,
    Notes VARCHAR(500) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (MembershipPaymentID),
    UNIQUE KEY uq_payment_transaction_reference (TransactionReference),
    UNIQUE KEY uq_payment_idempotency_key (IdempotencyKey),
    KEY idx_payment_membership_status (MembershipID, Status),
    KEY idx_payment_status_paid_at (Status, PaidAt),
    KEY idx_payment_recorded_by (RecordedByUserAccountID),
    CONSTRAINT fk_payment_membership FOREIGN KEY (MembershipID)
        REFERENCES MEMBERSHIP (MembershipID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_payment_recorded_by FOREIGN KEY (RecordedByUserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_payment_amount CHECK (Amount > 0),
    CONSTRAINT chk_payment_currency CHECK (Currency = UPPER(Currency)),
    CONSTRAINT chk_payment_paid_at CHECK (Status <> 'PAID' OR PaidAt IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE GYM_CLASS (
    GymClassID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    TrainerID BIGINT UNSIGNED NULL,
    Name VARCHAR(120) NOT NULL,
    Description TEXT NULL,
    StartsAt DATETIME NOT NULL,
    EndsAt DATETIME NOT NULL,
    Capacity SMALLINT UNSIGNED NOT NULL,
    Location VARCHAR(120) NULL,
    Status ENUM('DRAFT', 'SCHEDULED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'DRAFT',
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (GymClassID),
    KEY idx_gym_class_trainer_start (TrainerID, StartsAt),
    KEY idx_gym_class_status_start (Status, StartsAt),
    CONSTRAINT fk_gym_class_trainer FOREIGN KEY (TrainerID)
        REFERENCES TRAINER (TrainerID) ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_gym_class_times CHECK (EndsAt > StartsAt),
    CONSTRAINT chk_gym_class_capacity CHECK (Capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CLASS_ENROLLMENT (
    ClassEnrollmentID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    GymClassID BIGINT UNSIGNED NOT NULL,
    MemberID BIGINT UNSIGNED NOT NULL,
    Status ENUM('ENROLLED', 'WAITLISTED', 'CANCELLED') NOT NULL DEFAULT 'ENROLLED',
    EnrolledAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CancelledAt DATETIME NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ClassEnrollmentID),
    UNIQUE KEY uq_class_enrollment_member (GymClassID, MemberID),
    UNIQUE KEY uq_class_enrollment_identity (ClassEnrollmentID, GymClassID),
    KEY idx_enrollment_member_status (MemberID, Status),
    KEY idx_enrollment_class_status (GymClassID, Status),
    CONSTRAINT fk_enrollment_class FOREIGN KEY (GymClassID)
        REFERENCES GYM_CLASS (GymClassID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_enrollment_member FOREIGN KEY (MemberID)
        REFERENCES MEMBER (MemberID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_enrollment_cancelled_at CHECK (Status <> 'CANCELLED' OR CancelledAt IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CLASS_ATTENDANCE (
    ClassAttendanceID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ClassEnrollmentID BIGINT UNSIGNED NOT NULL,
    GymClassID BIGINT UNSIGNED NOT NULL,
    Status ENUM('PRESENT', 'ABSENT', 'LATE', 'EXCUSED') NOT NULL,
    CheckedInAt DATETIME NULL,
    MarkedByUserAccountID BIGINT UNSIGNED NOT NULL,
    Notes VARCHAR(500) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ClassAttendanceID),
    UNIQUE KEY uq_attendance_enrollment (ClassEnrollmentID),
    KEY idx_attendance_class_status (GymClassID, Status),
    KEY idx_attendance_marked_by (MarkedByUserAccountID),
    CONSTRAINT fk_attendance_enrollment_class FOREIGN KEY (ClassEnrollmentID, GymClassID)
        REFERENCES CLASS_ENROLLMENT (ClassEnrollmentID, GymClassID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_marked_by FOREIGN KEY (MarkedByUserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_attendance_checkin CHECK (Status NOT IN ('PRESENT', 'LATE') OR CheckedInAt IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE GYM_VISIT (
    GymVisitID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MemberID BIGINT UNSIGNED NOT NULL,
    CheckedInAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CheckedOutAt DATETIME NULL,
    CheckedInByUserAccountID BIGINT UNSIGNED NOT NULL,
    CheckedOutByUserAccountID BIGINT UNSIGNED NULL,
    Notes VARCHAR(500) NULL,
    OpenVisitMarker TINYINT GENERATED ALWAYS AS (
        CASE WHEN CheckedOutAt IS NULL THEN 1 ELSE NULL END
    ) STORED,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (GymVisitID),
    UNIQUE KEY uq_gym_visit_one_open_per_member (MemberID, OpenVisitMarker),
    KEY idx_gym_visit_member_checkin (MemberID, CheckedInAt),
    KEY idx_gym_visit_open_checkin (OpenVisitMarker, CheckedInAt),
    KEY idx_gym_visit_checked_in_by (CheckedInByUserAccountID),
    KEY idx_gym_visit_checked_out_by (CheckedOutByUserAccountID),
    CONSTRAINT fk_gym_visit_member FOREIGN KEY (MemberID)
        REFERENCES MEMBER (MemberID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_gym_visit_checked_in_by FOREIGN KEY (CheckedInByUserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_gym_visit_checked_out_by FOREIGN KEY (CheckedOutByUserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_gym_visit_times CHECK (CheckedOutAt IS NULL OR CheckedOutAt >= CheckedInAt),
    CONSTRAINT chk_gym_visit_checkout_actor CHECK (CheckedOutAt IS NULL OR CheckedOutByUserAccountID IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
