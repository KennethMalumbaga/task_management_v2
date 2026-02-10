CREATE TABLE IF NOT EXISTS groups (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  created_by INTEGER,
  created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS group_members (
  id SERIAL PRIMARY KEY,
  group_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  role TEXT DEFAULT 'member',
  created_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT group_members_role_check CHECK (role = ANY (ARRAY['leader','member'])),
  CONSTRAINT group_members_group_user_key UNIQUE (group_id, user_id),
  CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_messages (
  id SERIAL PRIMARY KEY,
  group_id INTEGER NOT NULL,
  sender_id INTEGER NOT NULL,
  message TEXT,
  created_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT fk_group_messages_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_message_attachments (
  id SERIAL PRIMARY KEY,
  message_id INTEGER NOT NULL,
  attachment_name TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT fk_group_msg_attach_msg FOREIGN KEY (message_id) REFERENCES group_messages(id) ON DELETE CASCADE
);
