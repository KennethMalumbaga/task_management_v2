-- Calendar meeting audience support for existing databases
-- Run this once if your calendar_meetings table already exists.

ALTER TABLE calendar_meetings
    ADD COLUMN audience_type varchar(20) NOT NULL DEFAULT 'everyone' AFTER timezone,
    ADD COLUMN group_id int DEFAULT NULL AFTER audience_type;
