-- One inwork draft per user per marketplace (status IN 'draft','processing').
-- Migration: 0004_uq_drafts_one_inwork_per_market
-- Replaces uq_drafts_one_draft_per_market (WHERE status = 'draft') with inwork variant.

DROP INDEX IF EXISTS uq_drafts_one_draft_per_market;

CREATE UNIQUE INDEX IF NOT EXISTS uq_drafts_one_inwork_per_market
    ON drafts (user_id, marketplace)
    WHERE status IN ('draft', 'processing');
