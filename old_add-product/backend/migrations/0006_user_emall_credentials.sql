-- Per-user eMall API key (saved in cabinet; used in wizard).
-- Migration: 0006_user_emall_credentials

CREATE TABLE IF NOT EXISTS user_emall_credentials (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    api_key TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
