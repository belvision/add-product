-- MVP-1.0 PostgreSQL schema (minimal spec)
-- Migration: 0001_init

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS drafts (
    id UUID PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    marketplace VARCHAR(32) NOT NULL DEFAULT 'ozon',
    description TEXT NOT NULL DEFAULT '',
    edited_json JSONB NOT NULL DEFAULT '{}',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    form_schema JSONB,
    pipeline_stage VARCHAR(64),
    pipeline_progress_pct INTEGER,
    pipeline_logs JSONB,
    qdrant_top10 JSONB,
    chosen_category JSONB,
    required_fields JSONB,
    filled_fields JSONB,
    final_payload_json JSONB,
    publish_status VARCHAR(32),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT drafts_status_check CHECK (status IN ('draft', 'processing', 'ready', 'published', 'failed'))
);

CREATE TABLE IF NOT EXISTS draft_images (
    id UUID PRIMARY KEY,
    draft_id UUID NOT NULL REFERENCES drafts(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    path VARCHAR(512) NOT NULL,
    width INTEGER,
    height INTEGER,
    bytes INTEGER,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_drafts_user_id ON drafts(user_id);
CREATE INDEX IF NOT EXISTS idx_draft_images_draft_id ON draft_images(draft_id);
CREATE INDEX IF NOT EXISTS idx_email_verification_tokens_user_id ON email_verification_tokens(user_id);
