# Security (MVP-1.0)

## Secrets

- All secrets from environment (no hardcoding). Use `.env` or server env.
- Env names: POSTGRES_*, SMTP_PASS, SMTP_FROM, APP_BASE_URL, QDRANT_URL, DEEPSEEK_API_KEY, OPENAI_API_KEY.
- **TODO (security):** Ozon API keys are stored per-user in `user_ozon_credentials` (plaintext). Consider adding a server-only `APP_ENC_KEY` and storing `ozon_api_key_enc` instead; decrypt in backend when calling Ozon API.
- Passwords hashed with `password_hash(..., PASSWORD_DEFAULT)`.
- Verification tokens stored as hash (e.g. SHA-256) in `email_verification_tokens`.

## Auth

- Session-based auth. Email verification gate: publish and pipeline:start require verified email.
- Ownership: drafts and images scoped by user_id; API validates session user.
- Error codes: AUTH_REQUIRED, EMAIL_NOT_VERIFIED, DRAFT_NOT_FOUND, DRAFT_NOT_OWNED.

## Image upload

- Allowed MIME: jpg/jpeg/png/webp only; server-side MIME sniffing (finfo). Error: UNSUPPORTED_IMAGE_FORMAT.
- Max size enforced (e.g. 8MB). Error: UPLOAD_TOO_LARGE.
- URL upload: SSRF protection — http/https only, private/local/link-local/metadata ranges blocked, max 3 redirects with re-check of final resolved host, byte limit. Errors: SSRF_BLOCKED, FETCH_FAILED.
- MISSING_EXTENSION when fileinfo not available.
