-- One draft per user per marketplace (status='draft').
-- Migration: 0003_drafts_current_step_step_state

ALTER TABLE drafts
    ADD COLUMN IF NOT EXISTS current_step INTEGER NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS step_state JSONB NOT NULL DEFAULT '{}';

CREATE UNIQUE INDEX IF NOT EXISTS uq_drafts_one_draft_per_market
    ON drafts (user_id, marketplace) WHERE status = 'draft';
