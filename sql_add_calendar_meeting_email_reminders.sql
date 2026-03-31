-- Calendar meeting email reminder queue support for existing databases
-- Run this once on existing databases.

CREATE TABLE IF NOT EXISTS calendar_meeting_email_reminders (
    id int NOT NULL AUTO_INCREMENT,
    meeting_id int NOT NULL,
    user_id int NOT NULL,
    scheduled_for datetime NOT NULL,
    sent_at datetime DEFAULT NULL,
    error_message text DEFAULT NULL,
    organization_id int DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_calendar_meeting_email_reminders_meeting_user (meeting_id, user_id),
    KEY idx_calendar_meeting_email_reminders_due (scheduled_for, sent_at),
    KEY idx_calendar_meeting_email_reminders_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
