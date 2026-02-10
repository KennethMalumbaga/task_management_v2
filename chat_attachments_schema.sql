CREATE TABLE IF NOT EXISTS chat_attachments (
  id SERIAL PRIMARY KEY,
  chat_id INTEGER NOT NULL,
  attachment_name TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT fk_chat
    FOREIGN KEY (chat_id)
    REFERENCES chats(chat_id)
    ON DELETE CASCADE
);
