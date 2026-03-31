-- MySQL/MariaDB-ready conversion generated from task_management_db.sql
-- Source dump date: 2026-02-13
SET NAMES utf8mb4;
SET time_zone = "+00:00";

CREATE TABLE attendance (
    id int NOT NULL AUTO_INCREMENT,
    user_id int,
    att_date date,
    total_hours numeric(5,2) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    time_in time,
    time_out time,
    last_heartbeat_at datetime DEFAULT NULL,
    CONSTRAINT attendance_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chat_attachments (
    attachment_id int NOT NULL AUTO_INCREMENT,
    chat_id int NOT NULL,
    attachment_name varchar(255) NOT NULL,
    CONSTRAINT chat_attachments_pkey PRIMARY KEY (attachment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chats (
    chat_id int NOT NULL AUTO_INCREMENT,
    sender_id int NOT NULL,
    receiver_id int NOT NULL,
    message text NOT NULL,
    opened tinyint(1) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chats_pkey PRIMARY KEY (chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_members (
    id int NOT NULL AUTO_INCREMENT,
    group_id int NOT NULL,
    user_id int NOT NULL,
    role varchar(20) DEFAULT 'member',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT group_members_role_check CHECK ((role  IN ('leader', 'member'))),
    CONSTRAINT group_members_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_message_attachments (
    id int NOT NULL AUTO_INCREMENT,
    message_id int NOT NULL,
    attachment_name text NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT group_message_attachments_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_message_reads (
    id int NOT NULL AUTO_INCREMENT,
    group_id int NOT NULL,
    user_id int NOT NULL,
    last_message_id int NOT NULL,
    CONSTRAINT group_message_reads_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_messages (
    id int NOT NULL AUTO_INCREMENT,
    group_id int NOT NULL,
    sender_id int NOT NULL,
    message text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT group_messages_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE groups (
    id int NOT NULL AUTO_INCREMENT,
    name text NOT NULL,
    created_by int,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    type varchar(50) DEFAULT 'group',
    task_id int,
    CONSTRAINT groups_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leader_feedback (
    id bigint NOT NULL AUTO_INCREMENT,
    task_id int NOT NULL,
    leader_id int NOT NULL,
    member_id int NOT NULL,
    rating smallint NOT NULL,
    comment text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT leader_feedback_rating_check CHECK (((rating >= 1) AND (rating <= 5))),
    CONSTRAINT leader_feedback_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id int NOT NULL AUTO_INCREMENT,
    message text NOT NULL,
    recipient int,
    type varchar(50) NOT NULL,
    date date DEFAULT CURRENT_DATE,
    is_read tinyint(1) DEFAULT 0,
    task_id int,
    CONSTRAINT notifications_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
    id int NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    token varchar(255) NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp NOT NULL,
    CONSTRAINT password_resets_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE screenshots (
    id int NOT NULL AUTO_INCREMENT,
    user_id int,
    attendance_id int,
    image_path varchar(255) NOT NULL,
    taken_at timestamp NOT NULL,
    CONSTRAINT screenshots_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subtasks (
    id int NOT NULL AUTO_INCREMENT,
    task_id int NOT NULL,
    member_id int NOT NULL,
    description text NOT NULL,
    due_date date NOT NULL,
    status varchar(20) DEFAULT 'pending',
    submission_file varchar(255),
    feedback text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP,
    submission_note text,
    score smallint,
    google_doc_id varchar(255),
    google_doc_url varchar(2048),
    CONSTRAINT subtasks_score_check CHECK (((score >= 1) AND (score <= 5))),
    CONSTRAINT subtasks_status_check CHECK ((status IN ('pending', 'submitted', 'completed', 'revise'))),
    CONSTRAINT subtasks_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_assignees (
    id int NOT NULL AUTO_INCREMENT,
    task_id int,
    user_id int,
    role varchar(20) DEFAULT 'member',
    assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
    performance_rating smallint,
    rating_comment text,
    rated_by int,
    rated_at timestamp,
    CONSTRAINT task_assignees_performance_rating_check CHECK (((performance_rating >= 1) AND (performance_rating <= 5))),
    CONSTRAINT task_assignees_role_check CHECK ((role  IN ('leader', 'member'))),
    CONSTRAINT task_assignees_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tasks (
    id int NOT NULL AUTO_INCREMENT,
    title varchar(100) NOT NULL,
    description text,
    assigned_to int,
    status varchar(20) DEFAULT 'pending',
    submission_file varchar(255),
    template_file varchar(255),
    google_doc_url varchar(2048),
    review_comment text,
    reviewed_by int,
    reviewed_at timestamp,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    due_date date NOT NULL,
    submission_note text,
    rating int DEFAULT 0,
    leader_rating smallint,
    leader_review_comment text,
    CONSTRAINT tasks_leader_rating_check CHECK (((leader_rating >= 1) AND (leader_rating <= 5))),
    CONSTRAINT tasks_status_check CHECK ((status  IN ('pending', 'in_progress', 'completed', 'rejected', 'revise'))),
    CONSTRAINT tasks_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id int NOT NULL AUTO_INCREMENT,
    full_name varchar(50) NOT NULL,
    username varchar(50) NOT NULL,
    google_sub varchar(255) DEFAULT NULL,
    password varchar(255) NOT NULL,
    role varchar(20) NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    phone varchar(20) DEFAULT NULL,
    address text,
    skills text,
    profile_image varchar(255) DEFAULT 'default.png',
    google_picture varchar(2048) DEFAULT NULL,
    google_email_verified tinyint(1) DEFAULT 0,
    must_change_password tinyint(1) DEFAULT 0,
    bio text,
    CONSTRAINT users_role_check CHECK ((role  IN ('admin', 'employee'))),
    CONSTRAINT users_pkey PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_google_oauth_tokens (
    id int NOT NULL AUTO_INCREMENT,
    user_id int NOT NULL,
    google_sub varchar(255) DEFAULT NULL,
    google_email varchar(255) DEFAULT NULL,
    refresh_token text NOT NULL,
    scope text,
    organization_id int DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT user_google_oauth_tokens_pkey PRIMARY KEY (id),
    UNIQUE KEY uniq_user_google_oauth_tokens_user (user_id),
    KEY idx_user_google_oauth_tokens_org_user (organization_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE calendar_meetings (
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
    UNIQUE KEY uniq_calendar_meetings_google_event (google_event_id),
    KEY idx_calendar_meetings_org_date (organization_id, meeting_date),
    KEY idx_calendar_meetings_creator_date (created_by, meeting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE calendar_meeting_email_reminders (
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

INSERT INTO chats (chat_id, sender_id, receiver_id, message, opened, created_at) VALUES ('1', '1', '11', 'bruh', 1, '2026-02-12 00:08:34.221095');
INSERT INTO chats (chat_id, sender_id, receiver_id, message, opened, created_at) VALUES ('2', '11', '1', 'sir', 1, '2026-02-12 00:19:11.71629');
INSERT INTO chats (chat_id, sender_id, receiver_id, message, opened, created_at) VALUES ('3', '1', '11', 'jan', 0, '2026-02-13 02:07:32.444704');

INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('1', '1', '8', 'leader', '2026-02-11 22:36:59.82759');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('2', '1', '12', 'member', '2026-02-11 22:36:59.830312');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('3', '2', '11', 'leader', '2026-02-11 22:37:12.12798');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('4', '2', '9', 'member', '2026-02-11 22:37:12.129054');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('5', '2', '10', 'member', '2026-02-11 22:37:12.12947');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('6', '3', '8', 'leader', '2026-02-11 22:38:15.149086');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('7', '3', '12', 'member', '2026-02-11 22:38:15.149647');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('8', '3', '1', 'member', '2026-02-11 22:38:15.149982');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('9', '4', '11', 'leader', '2026-02-11 22:38:44.578618');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('10', '4', '10', 'member', '2026-02-11 22:38:44.579854');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('11', '4', '9', 'member', '2026-02-11 22:38:44.58026');
INSERT INTO group_members (id, group_id, user_id, role, created_at) VALUES ('12', '4', '1', 'member', '2026-02-11 22:38:44.580646');

INSERT INTO group_message_reads (id, group_id, user_id, last_message_id) VALUES ('2', '4', '11', '3');
INSERT INTO group_message_reads (id, group_id, user_id, last_message_id) VALUES ('1', '4', '1', '3');
INSERT INTO group_message_reads (id, group_id, user_id, last_message_id) VALUES ('3', '4', '10', '3');

INSERT INTO group_messages (id, group_id, sender_id, message, created_at) VALUES ('1', '4', '1', 'guys?', '2026-02-12 00:08:46.1123');
INSERT INTO group_messages (id, group_id, sender_id, message, created_at) VALUES ('2', '4', '1', 'hello?', '2026-02-12 00:08:48.811023');
INSERT INTO group_messages (id, group_id, sender_id, message, created_at) VALUES ('3', '4', '11', 'yes sir', '2026-02-12 00:19:04.613332');

INSERT INTO groups (id, name, created_by, created_at, type, task_id) VALUES ('1', 'group 1', '1', '2026-02-11 22:36:59.818765', 'group', NULL);
INSERT INTO groups (id, name, created_by, created_at, type, task_id) VALUES ('2', 'group 2', '1', '2026-02-11 22:37:12.127025', 'group', NULL);
INSERT INTO groups (id, name, created_by, created_at, type, task_id) VALUES ('3', 'Task Management System', '1', '2026-02-11 22:38:15.14828', 'task_chat', '1');
INSERT INTO groups (id, name, created_by, created_at, type, task_id) VALUES ('4', 'E-Clinic System', '1', '2026-02-11 22:38:44.57751', 'task_chat', '2');

INSERT INTO leader_feedback (id, task_id, leader_id, member_id, rating, comment, created_at, updated_at) VALUES ('1', '2', '11', '10', '5', 'Goods rapud kaayo', '2026-02-12 23:06:26.634918', '2026-02-12 23:07:02.998237');
INSERT INTO leader_feedback (id, task_id, leader_id, member_id, rating, comment, created_at, updated_at) VALUES ('4', '2', '11', '9', '5', 'goods siya', '2026-02-12 23:07:55.097185', '2026-02-12 23:07:55.097185');
INSERT INTO leader_feedback (id, task_id, leader_id, member_id, rating, comment, created_at, updated_at) VALUES ('5', '1', '8', '12', '5', 'goods ni siya sir, maatiman kaayo ang task', '2026-02-13 00:57:11.680698', '2026-02-13 00:57:11.680698');

INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('1', '''Task Management System'' has been assigned to you as leader. Please review and start working on it', '8', 'New Task Assigned', '2026-02-11', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('2', '''Task Management System'' has been assigned to you. Please review and start working on it', '12', 'New Task Assigned', '2026-02-11', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('3', '''E-Clinic System'' has been assigned to you as leader. Please review and start working on it', '11', 'New Task Assigned', '2026-02-11', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('4', '''E-Clinic System'' has been assigned to you. Please review and start working on it', '10', 'New Task Assigned', '2026-02-11', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('5', '''E-Clinic System'' has been assigned to you. Please review and start working on it', '9', 'New Task Assigned', '2026-02-11', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('6', 'You have been assigned a subtask for: E-Clinic System', '9', 'New Subtask', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('7', 'You have been assigned a subtask for: E-Clinic System', '10', 'New Subtask', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('8', 'You have been assigned a subtask for: E-Clinic System', '11', 'New Subtask', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('9', 'Subtask submitted by User 11', '11', 'Subtask Submitted', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('10', 'Subtask submitted by User 10', '11', 'Subtask Submitted', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('11', 'Subtask submitted by User 9', '11', 'Subtask Submitted', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('12', 'Your subtask submission has been ACCEPTED. Score: 5/5.', '11', 'Subtask Review', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('13', 'Your subtask submission has been ACCEPTED. Score: 5/5.', '10', 'Subtask Review', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('14', 'Your subtask submission has been ACCEPTED. Score: 5/5.', '9', 'Subtask Review', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('15', 'Task Submitted by Leader (neljhan redondo)', '1', 'Task Submitted', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('16', 'Task Accepted & Rated (5/5): E-Clinic System', '9', 'Task Verified', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('17', 'Task Accepted & Rated (5/5): E-Clinic System', '10', 'Task Verified', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('18', 'Task Accepted & Rated (5/5): E-Clinic System', '11', 'Task Verified', '2026-02-12', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('19', 'Task Accepted & Rated (5/5): E-Clinic System', '9', 'Task Verified', '2026-02-13', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('20', 'Task Accepted & Rated (5/5): E-Clinic System', '10', 'Task Verified', '2026-02-13', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('21', 'Task Accepted & Rated (5/5): E-Clinic System', '11', 'Task Verified', '2026-02-13', 0, '2');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('22', 'You have been assigned a subtask for: Task Management System', '12', 'New Subtask', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('23', 'You have been assigned a subtask for: Task Management System', '8', 'New Subtask', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('24', 'Subtask submitted by User 12', '8', 'Subtask Submitted', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('25', 'Your subtask submission has been ACCEPTED. Score: 5/5.', '12', 'Subtask Review', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('26', 'Subtask submitted by User 8', '8', 'Subtask Submitted', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('27', 'Your subtask submission has been ACCEPTED. Self-rating is disabled.', '8', 'Subtask Review', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('28', 'Task Submitted by Leader (Kenneth Bryan Malumbaga)', '1', 'Task Submitted', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('29', 'Task Accepted & Rated (5/5): Task Management System', '8', 'Task Verified', '2026-02-13', 0, '1');
INSERT INTO notifications (id, message, recipient, type, date, is_read, task_id) VALUES ('30', 'Task Accepted & Rated (5/5): Task Management System', '12', 'Task Verified', '2026-02-13', 0, '1');

INSERT INTO subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) VALUES ('3', '2', '11', 'Implement firebase password reset function with custom template and custom page', '2026-02-13', 'completed', 'uploads/subtask_3_1770905800.jpg', 'para saakoa goods ni', '2026-02-12 22:15:57.720444', '2026-02-12 22:19:42.987625', 'Done', '5');
INSERT INTO subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) VALUES ('2', '2', '10', 'UI/UX design', '2026-02-13', 'completed', 'uploads/subtask_2_1770905885.jpg', 'Good', '2026-02-12 22:15:30.074561', '2026-02-12 22:20:11.014832', 'here you goo', '5');
INSERT INTO subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) VALUES ('1', '2', '9', 'Implement firebase email and password auth', '2026-02-14', 'completed', 'uploads/subtask_1_1770905943.png', 'good ka boi', '2026-02-12 22:14:55.182513', '2026-02-12 22:20:33.576559', 'goodsheesh boss', '5');
INSERT INTO subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) VALUES ('4', '1', '12', 'UI/UX design', '2026-02-21', 'completed', 'uploads/subtask_4_1770915244.png', 'goods ni', '2026-02-13 00:53:03.22524', '2026-02-13 00:54:34.388893', 'goods', '5');
INSERT INTO subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) VALUES ('5', '1', '8', 'Implement firebase email and password auth', '2026-02-21', 'completed', 'uploads/subtask_5_1770915316.png', '', '2026-02-13 00:53:14.072824', '2026-02-13 00:55:22.692208', 'okay na ni', NULL);

INSERT INTO task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) VALUES ('2', '1', '12', 'member', '2026-02-11 22:38:15.138549', NULL, NULL, NULL, NULL);
INSERT INTO task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) VALUES ('4', '2', '10', 'member', '2026-02-11 22:38:44.563968', NULL, NULL, NULL, NULL);
INSERT INTO task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) VALUES ('5', '2', '9', 'member', '2026-02-11 22:38:44.564454', NULL, NULL, NULL, NULL);
INSERT INTO task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) VALUES ('3', '2', '11', 'leader', '2026-02-11 22:38:44.562842', '5', NULL, '1', '2026-02-13 00:51:59.799642');
INSERT INTO task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) VALUES ('1', '1', '8', 'leader', '2026-02-11 22:38:15.137271', '3', NULL, '1', '2026-02-13 00:58:00.724405');

INSERT INTO tasks (id, title, description, assigned_to, status, submission_file, template_file, review_comment, reviewed_by, reviewed_at, created_at, due_date, submission_note, rating, leader_rating, leader_review_comment) VALUES ('2', 'E-Clinic System', 'Create E-Clinic System', '11', 'completed', 'uploads/task_2_submit_1770906069.png', NULL, 'nice guys', '1', '2026-02-13 00:51:59.778917', '2026-02-11 22:38:44.561664', '2026-03-14', 'goods nami sir', '5', NULL, NULL);
INSERT INTO tasks (id, title, description, assigned_to, status, submission_file, template_file, review_comment, reviewed_by, reviewed_at, created_at, due_date, submission_note, rating, leader_rating, leader_review_comment) VALUES ('1', 'Task Management System', 'Create a task management system', '8', 'completed', 'uploads/task_1_submit_1770915393.png', NULL, 'nice ken', '1', '2026-02-13 00:58:00.707275', '2026-02-11 22:38:15.134776', '2026-03-14', 'goods ni sir', '5', NULL, NULL);

INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('1', 'Admin ako', 'admin', '$2y$10$b/v2OHMZLbahxklajBoPguDE4JtJiSN4k84v4CCZSHZ8Bpd1MYbwS', 'admin', '2026-01-31 10:55:22.536092', '09123456789', 'sa lugar na wala ka', 'skill 1 ni ling', 'IMG-697ebe16f28190.33379626.jpg', 0, NULL);
INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('8', 'Kenneth Bryan Malumbaga', 'malumbaga.kennethbryan@dnsc.edu.ph', '$2y$10$ZMqTCNmtpFvS3gXx4Xw0vuzd/I9tv1/M0N0CEgWE7uy1q1RnYcUem', 'employee', '2026-02-11 22:25:13.990673', '09702641643', 'Davao City', 'Web Developer', 'IMG-698e1596b41879.56473981.jpg', 0, '');
INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('12', 'mary zhane torrecampo', 'torrecampo.maryzhane@dnsc.edu.ph', '$2y$10$ldKs3VmVUZNFGJdKq49a7ezDjxam7UDWFe9MXbyPTZcYNisOa6UM2', 'employee', '2026-02-11 22:35:56.093871', '', '', '', 'IMG-698e15cc451b95.57089192.png', 0, '');
INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('9', 'Lorenz Laurente', 'laurente.lorenzmaikel@dnsc.edu.ph', '$2y$10$rlbSPSTufi9fXmNHGvMrpelWyimyFd5N8aunKCFs1FQ7/W7h0Rwwy', 'employee', '2026-02-11 22:32:42.762689', '', '', '', 'IMG-698e160de78e24.93447518.jpg', 0, '');
INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('10', 'kenshie maling', 'maling.kenshie@dnsc.edu.ph', '$2y$10$efxlOnLPTaqP55kWwFEBGeWZmLpy1DjlKZ0ir7Np7uekxRRQ8r8/e', 'employee', '2026-02-11 22:34:07.770259', '', '', '', 'IMG-698e1678d5d446.93340106.jpg', 0, '');
INSERT INTO users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) VALUES ('11', 'neljhan redondo', 'redondo.neljhan@dnsc.edu.ph', '$2y$10$6q/OPW/EogpiC4Qd2u/Qu.VoDRamTegeYddomWxy2rRsYFhc78CyG', 'employee', '2026-02-11 22:34:56.034939', '', '', '', 'IMG-698e16a826e894.60947703.jpg', 0, '');

ALTER TABLE group_members
    ADD CONSTRAINT group_members_group_user_key UNIQUE (group_id, user_id);

ALTER TABLE leader_feedback
    ADD CONSTRAINT leader_feedback_unique UNIQUE (task_id, leader_id, member_id);

ALTER TABLE task_assignees
    ADD CONSTRAINT task_assignees_task_id_user_id_key UNIQUE (task_id, user_id);

ALTER TABLE users
    ADD CONSTRAINT users_username_key UNIQUE (username);

CREATE INDEX idx_groups_task_chat_task_id ON groups (type, task_id);

CREATE INDEX idx_subtasks_member_id ON subtasks (member_id);

CREATE INDEX idx_subtasks_task_id ON subtasks (task_id);

ALTER TABLE attendance
    ADD CONSTRAINT attendance_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id);

ALTER TABLE chat_attachments
    ADD CONSTRAINT chat_attachments_chat_id_fkey FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE;

ALTER TABLE calendar_meetings
    ADD CONSTRAINT calendar_meetings_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE calendar_meetings
    ADD CONSTRAINT calendar_meetings_task_id_fkey FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL;

ALTER TABLE calendar_meeting_email_reminders
    ADD CONSTRAINT calendar_meeting_email_reminders_meeting_id_fkey FOREIGN KEY (meeting_id) REFERENCES calendar_meetings(id) ON DELETE CASCADE;

ALTER TABLE calendar_meeting_email_reminders
    ADD CONSTRAINT calendar_meeting_email_reminders_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE group_members
    ADD CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE;

ALTER TABLE group_members
    ADD CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE group_messages
    ADD CONSTRAINT fk_group_messages_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE;

ALTER TABLE group_messages
    ADD CONSTRAINT fk_group_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE group_message_attachments
    ADD CONSTRAINT fk_group_msg_attach_msg FOREIGN KEY (message_id) REFERENCES group_messages(id) ON DELETE CASCADE;

ALTER TABLE group_message_reads
    ADD CONSTRAINT group_message_reads_group_id_fkey FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE;

ALTER TABLE group_message_reads
    ADD CONSTRAINT group_message_reads_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE groups
    ADD CONSTRAINT groups_task_id_fkey FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;

ALTER TABLE notifications
    ADD CONSTRAINT notifications_recipient_fkey FOREIGN KEY (recipient) REFERENCES users(id);

ALTER TABLE notifications
    ADD CONSTRAINT notifications_task_id_fkey FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL;

ALTER TABLE screenshots
    ADD CONSTRAINT screenshots_attendance_id_fkey FOREIGN KEY (attendance_id) REFERENCES attendance(id);

ALTER TABLE screenshots
    ADD CONSTRAINT screenshots_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id);

ALTER TABLE subtasks
    ADD CONSTRAINT subtasks_member_id_fkey FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE subtasks
    ADD CONSTRAINT subtasks_task_id_fkey FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;

ALTER TABLE task_assignees
    ADD CONSTRAINT task_assignees_task_id_fkey FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;

ALTER TABLE task_assignees
    ADD CONSTRAINT task_assignees_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE tasks
    ADD CONSTRAINT tasks_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES users(id);

ALTER TABLE tasks
    ADD CONSTRAINT tasks_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES users(id);
