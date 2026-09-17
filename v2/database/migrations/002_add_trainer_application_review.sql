-- Add an explicit trainer application review lifecycle without changing the original schema migration.
ALTER TABLE TRAINER
    MODIFY Status ENUM('PENDING', 'ACTIVE', 'INACTIVE', 'ARCHIVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    ADD COLUMN ReviewedAt DATETIME NULL AFTER Status,
    ADD COLUMN ReviewedByUserAccountID BIGINT UNSIGNED NULL AFTER ReviewedAt,
    ADD COLUMN RejectionReason VARCHAR(500) NULL AFTER ReviewedByUserAccountID,
    ADD KEY idx_trainer_application_queue (Status, CreatedAt),
    ADD KEY idx_trainer_reviewed_by (ReviewedByUserAccountID),
    ADD CONSTRAINT fk_trainer_reviewed_by FOREIGN KEY (ReviewedByUserAccountID)
        REFERENCES USER_ACCOUNT (UserAccountID) ON UPDATE RESTRICT ON DELETE SET NULL;
