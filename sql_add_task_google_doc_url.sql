-- Task Google Docs link column (MySQL/MariaDB)
-- Run this on existing databases so document-based tasks can store a working Google Docs URL.

ALTER TABLE tasks
    ADD COLUMN google_doc_url varchar(2048) NULL AFTER template_file;
