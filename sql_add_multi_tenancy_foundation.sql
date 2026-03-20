-- Multi-tenant foundation migration (MySQL/MariaDB)
-- Run this once before enabling tenant-scoped auth in production.

SET NAMES utf8mb4;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS organizations (
    id int NOT NULL AUTO_INCREMENT,
    name varchar(120) NOT NULL,
    slug varchar(120) NOT NULL,
    billing_email varchar(255) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    plan_code varchar(40) NOT NULL DEFAULT 'trial',
    theme_primary varchar(20) DEFAULT NULL,
    theme_secondary varchar(20) DEFAULT NULL,
    theme_accent varchar(20) DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT organizations_pkey PRIMARY KEY (id),
    CONSTRAINT organizations_slug_key UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_members (
    id int NOT NULL AUTO_INCREMENT,
    organization_id int NOT NULL,
    user_id int NOT NULL,
    role varchar(20) NOT NULL DEFAULT 'member',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT organization_members_pkey PRIMARY KEY (id),
    CONSTRAINT organization_members_role_check CHECK ((role IN ('owner', 'admin', 'member'))),
    CONSTRAINT organization_members_org_user_key UNIQUE (organization_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscriptions (
    id int NOT NULL AUTO_INCREMENT,
    organization_id int NOT NULL,
    provider varchar(30) NOT NULL DEFAULT 'manual',
    provider_subscription_id varchar(120) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'trialing',
    seat_limit int NOT NULL DEFAULT 10,
    trial_ends_at datetime DEFAULT NULL,
    current_period_end datetime DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT subscriptions_pkey PRIMARY KEY (id),
    CONSTRAINT subscriptions_org_key UNIQUE (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE task_assignees ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE groups ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE group_members ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE subtasks ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE notifications ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE attendance ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE screenshots ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE chats ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE group_messages ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE group_message_reads ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE leader_feedback ADD COLUMN organization_id int DEFAULT NULL;
ALTER TABLE password_resets ADD COLUMN organization_id int DEFAULT NULL;

INSERT INTO organizations (name, slug, billing_email, status, plan_code)
SELECT 'Default Workspace', 'default-workspace', NULL, 'active', 'legacy'
WHERE NOT EXISTS (SELECT 1 FROM organizations);

SET @default_org_id := (SELECT id FROM organizations ORDER BY id ASC LIMIT 1);

UPDATE users
SET organization_id = COALESCE(organization_id, @default_org_id)
WHERE organization_id IS NULL;

INSERT INTO organization_members (organization_id, user_id, role)
SELECT
    u.organization_id,
    u.id,
    CASE
        WHEN u.role = 'admin' THEN 'owner'
        ELSE 'member'
    END AS member_role
FROM users u
LEFT JOIN organization_members om
    ON om.organization_id = u.organization_id
   AND om.user_id = u.id
WHERE u.organization_id IS NOT NULL
  AND om.id IS NULL;

UPDATE tasks t
LEFT JOIN users u ON u.id = t.assigned_to
SET t.organization_id = COALESCE(t.organization_id, u.organization_id, @default_org_id)
WHERE t.organization_id IS NULL;

UPDATE task_assignees ta
LEFT JOIN tasks t ON t.id = ta.task_id
LEFT JOIN users u ON u.id = ta.user_id
SET ta.organization_id = COALESCE(ta.organization_id, t.organization_id, u.organization_id, @default_org_id)
WHERE ta.organization_id IS NULL;

UPDATE groups g
LEFT JOIN users u ON u.id = g.created_by
LEFT JOIN tasks t ON t.id = g.task_id
SET g.organization_id = COALESCE(g.organization_id, u.organization_id, t.organization_id, @default_org_id)
WHERE g.organization_id IS NULL;

UPDATE group_members gm
LEFT JOIN groups g ON g.id = gm.group_id
LEFT JOIN users u ON u.id = gm.user_id
SET gm.organization_id = COALESCE(gm.organization_id, g.organization_id, u.organization_id, @default_org_id)
WHERE gm.organization_id IS NULL;

UPDATE subtasks s
LEFT JOIN tasks t ON t.id = s.task_id
LEFT JOIN users u ON u.id = s.member_id
SET s.organization_id = COALESCE(s.organization_id, t.organization_id, u.organization_id, @default_org_id)
WHERE s.organization_id IS NULL;

UPDATE notifications n
LEFT JOIN users u ON u.id = n.recipient
LEFT JOIN tasks t ON t.id = n.task_id
SET n.organization_id = COALESCE(n.organization_id, u.organization_id, t.organization_id, @default_org_id)
WHERE n.organization_id IS NULL;

UPDATE attendance a
LEFT JOIN users u ON u.id = a.user_id
SET a.organization_id = COALESCE(a.organization_id, u.organization_id, @default_org_id)
WHERE a.organization_id IS NULL;

UPDATE screenshots s
LEFT JOIN users u ON u.id = s.user_id
LEFT JOIN attendance a ON a.id = s.attendance_id
SET s.organization_id = COALESCE(s.organization_id, u.organization_id, a.organization_id, @default_org_id)
WHERE s.organization_id IS NULL;

UPDATE chats c
LEFT JOIN users u ON u.id = c.sender_id
SET c.organization_id = COALESCE(c.organization_id, u.organization_id, @default_org_id)
WHERE c.organization_id IS NULL;

UPDATE group_messages gm
LEFT JOIN groups g ON g.id = gm.group_id
SET gm.organization_id = COALESCE(gm.organization_id, g.organization_id, @default_org_id)
WHERE gm.organization_id IS NULL;

UPDATE group_message_reads gmr
LEFT JOIN groups g ON g.id = gmr.group_id
SET gmr.organization_id = COALESCE(gmr.organization_id, g.organization_id, @default_org_id)
WHERE gmr.organization_id IS NULL;

UPDATE leader_feedback lf
LEFT JOIN tasks t ON t.id = lf.task_id
LEFT JOIN users u ON u.id = lf.leader_id
SET lf.organization_id = COALESCE(lf.organization_id, t.organization_id, u.organization_id, @default_org_id)
WHERE lf.organization_id IS NULL;

UPDATE password_resets pr
LEFT JOIN users u ON u.username = pr.email
SET pr.organization_id = COALESCE(pr.organization_id, u.organization_id, @default_org_id)
WHERE pr.organization_id IS NULL;

ALTER TABLE users MODIFY organization_id int NOT NULL;

CREATE INDEX idx_users_org_id ON users (organization_id);
CREATE INDEX idx_tasks_org_id ON tasks (organization_id);
CREATE INDEX idx_task_assignees_org_id ON task_assignees (organization_id);
CREATE INDEX idx_groups_org_id ON groups (organization_id);
CREATE INDEX idx_group_members_org_id ON group_members (organization_id);
CREATE INDEX idx_subtasks_org_id ON subtasks (organization_id);
CREATE INDEX idx_notifications_org_id ON notifications (organization_id);
CREATE INDEX idx_attendance_org_id ON attendance (organization_id);
CREATE INDEX idx_screenshots_org_id ON screenshots (organization_id);
CREATE INDEX idx_chats_org_id ON chats (organization_id);
CREATE INDEX idx_group_messages_org_id ON group_messages (organization_id);
CREATE INDEX idx_group_message_reads_org_id ON group_message_reads (organization_id);
CREATE INDEX idx_leader_feedback_org_id ON leader_feedback (organization_id);
CREATE INDEX idx_password_resets_org_id ON password_resets (organization_id);

ALTER TABLE users ADD CONSTRAINT users_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations (id);
ALTER TABLE organization_members ADD CONSTRAINT organization_members_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id);
ALTER TABLE organization_members ADD CONSTRAINT organization_members_user_fk FOREIGN KEY (user_id) REFERENCES users (id);

INSERT INTO subscriptions (organization_id, provider, status, seat_limit, trial_ends_at, current_period_end)
SELECT o.id, 'manual', 'trialing', 10, DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 1 MONTH)
FROM organizations o
LEFT JOIN subscriptions s ON s.organization_id = o.id
WHERE s.id IS NULL;
