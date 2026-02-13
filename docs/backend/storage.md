# Storage (MVP-1.0)

## Environment

POSTGRES_HOST, POSTGRES_PORT, POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD, APP_BASE_URL, APP_LOCALE_DEFAULT, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, QDRANT_URL, DEEPSEEK_API_KEY, OPENAI_API_KEY (as needed). Ozon API credentials are not in env; they are stored per-user in DB (see `user_ozon_credentials`).

## PostgreSQL

- **users**: id (bigserial), email, password_hash, email_verified_at, created_at
- **email_verification_tokens**: id, user_id, token_hash, expires_at, created_at
- **drafts**: id (uuid, draftId), user_id, marketplace, description, edited_json (jsonb), status, form_schema, pipeline_* fields, created_at, updated_at
- **draft_images**: id (uuid, imageId), draft_id, user_id, path, width, height, bytes, created_at
- **user_ozon_credentials**: user_id (PK, FK users), ozon_client_id, ozon_api_key, created_at, updated_at — per-user Ozon API keys (see security.md for encryption TODO).

Schema: `backend/migrations/0001_init.sql` — apply via `php backend/bin/migrate.php up`.
When the CLI runner cannot be used, apply the schema manually in pgAdmin (Query Tool); see `docs/migrations/README.md` (section "Manual apply via pgAdmin").

## File storage

Images and temp files only under:

```
storage/marketplace/ozon/tmp/{userId}/{draftId}/images/{imageId}.jpg
```

- draftId and imageId are UUIDs.
- All uploads converted to JPG.
- `.touch` file in `tmp/{userId}/{draftId}/.touch` updated on each write; cleanup job may use it.
- On successful publish, `storage/marketplace/ozon/tmp/{userId}/{draftId}` is deleted recursively.
