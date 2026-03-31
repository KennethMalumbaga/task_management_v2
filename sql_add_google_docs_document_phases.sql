-- Google Docs document-phase support (MySQL/MariaDB)
-- Run this once on existing databases.

ALTER TABLE project_timeline_phases
    ADD COLUMN phase_type varchar(30) NOT NULL DEFAULT 'standard' AFTER description;

ALTER TABLE subtasks
    ADD COLUMN google_doc_id varchar(255) NULL AFTER timeline_phase_id,
    ADD COLUMN google_doc_url varchar(2048) NULL AFTER google_doc_id;

CREATE TABLE IF NOT EXISTS user_google_oauth_tokens (
    id int NOT NULL AUTO_INCREMENT,
    user_id int NOT NULL,
    google_sub varchar(255) DEFAULT NULL,
    google_email varchar(255) DEFAULT NULL,
    refresh_token text NOT NULL,
    scope text DEFAULT NULL,
    organization_id int DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT user_google_oauth_tokens_pkey PRIMARY KEY (id),
    UNIQUE KEY uniq_user_google_oauth_tokens_user (user_id),
    KEY idx_user_google_oauth_tokens_org_user (organization_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
