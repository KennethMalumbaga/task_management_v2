-- Bulletin meeting reminder scope support for existing databases
-- Optional: the app can auto-add these columns at runtime, but you can run this once manually too.

ALTER TABLE bulletin_posts
    ADD COLUMN source_type varchar(30) NULL AFTER body,
    ADD COLUMN source_id int NULL AFTER source_type,
    ADD COLUMN audience_type varchar(20) NOT NULL DEFAULT 'everyone' AFTER source_id,
    ADD COLUMN group_id int NULL AFTER audience_type,
    ADD COLUMN task_id int NULL AFTER group_id;
