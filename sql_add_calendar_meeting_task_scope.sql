-- Calendar meeting task scope support for existing databases
-- Run this once if your calendar_meetings table already exists.

ALTER TABLE calendar_meetings
    ADD COLUMN task_id int DEFAULT NULL AFTER group_id;
