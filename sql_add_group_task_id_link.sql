-- Add task_id link to groups for reliable task_chat cleanup
ALTER TABLE public.groups
ADD COLUMN IF NOT EXISTS task_id integer;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'groups_task_id_fkey'
    ) THEN
        ALTER TABLE ONLY public.groups
        ADD CONSTRAINT groups_task_id_fkey
        FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_groups_task_chat_task_id
ON public.groups(task_id)
WHERE type = 'task_chat';
