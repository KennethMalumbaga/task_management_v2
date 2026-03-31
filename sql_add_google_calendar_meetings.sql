-- Google Calendar + Google Meet workspace meetings
-- Run this once on existing databases.

CREATE TABLE IF NOT EXISTS calendar_meetings (
    id int NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    description text DEFAULT NULL,
    meeting_date date NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    timezone varchar(100) NOT NULL DEFAULT 'Asia/Manila',
    audience_type varchar(20) NOT NULL DEFAULT 'everyone',
    group_id int DEFAULT NULL,
    task_id int DEFAULT NULL,
    google_event_id varchar(255) DEFAULT NULL,
    google_calendar_url varchar(2048) DEFAULT NULL,
    google_meet_url varchar(2048) DEFAULT NULL,
    google_conference_id varchar(255) DEFAULT NULL,
    created_by int NOT NULL,
    organization_id int DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT calendar_meetings_pkey PRIMARY KEY (id),
    CONSTRAINT calendar_meetings_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_calendar_meetings_google_event (google_event_id),
    KEY idx_calendar_meetings_org_date (organization_id, meeting_date),
    KEY idx_calendar_meetings_creator_date (created_by, meeting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
