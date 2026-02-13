# Environment template

This folder contains a safe example of environment variables (no real secrets).

## Setup

1. Copy the example file to the project root as `.env`:

   ```bash
   cp env/.env.example .env
   ```
   On Windows (cmd): `copy env\.env.example .env`

2. Edit `.env` and replace placeholder values with your real configuration (see variable groups below).

3. Ensure required PHP extensions are enabled: **curl**, **fileinfo**, **gd**, **pdo_pgsql** (e.g. in `php.ini` or your environment).

**.env** is copied from `env/.env.example` and must stay uncommitted (it is in `.gitignore`). Only `env/.env.example` is versioned.

### Variable groups

- **POSTGRES_*** — PostgreSQL connection.
- **APP_BASE_URL**, **APP_LOCALE_DEFAULT** — app base URL and default locale (`ru` or `en`).
- **SMTP_*** — mail settings for verification emails; leave empty or use a local relay for development.
- **OpenAI (GPT + embeddings)** — **OPENAI_API_KEY**: API key. **GPT_EMBEDDING_MODEL**: embeddings model (`text-embedding-3-small`).
- **Proxy** — **PROXY_ADDR**, **PROXY_USER**, **PROXY_PASS**. Optional; empty = no proxy.
- **DeepSeek** — **DEEPSEEK_API_URL**: chat completions endpoint (fixed as in env example: `https://api.deepseek.com/v1/chat/completions`). **DEEPSEEK_API_KEY**: API key.
- **QDRANT_URL** — optional; only if used by the app.
