-- Per-user Ozon API credentials (stored in cabinet; see user_ozon_credentials).
-- Migration: 0002_user_ozon_credentials

CREATE TABLE IF NOT EXISTS user_ozon_credentials (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    ozon_client_id TEXT NOT NULL,
    ozon_api_key TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
