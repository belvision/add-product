# Documentation

All project documentation lives here.

## Contents

| Path | Description |
|------|-------------|
| **docs/backend/** | Backend: API, storage, security, deployment, UX |
| **docs/migrations/** | Database migrations: CLI runner and manual apply |
| [docs/ENV.md](ENV.md) | Переменные окружения: главный .env, шаблон, порядок поиска |
| [docs/DB.md](DB.md) | База данных: как поднять с нуля, миграции, legacy |
| [docs/STRUCTURE.md](STRUCTURE.md) | Структура фронта: marketplaces, shared, ассеты |

## Database schema

- **Schema file:** `backend/migrations/0001_init.sql`
- **Apply via CLI** (from project root): `php backend/bin/migrate.php up`
- **Manual apply:** When PHP `pdo_pgsql` is unavailable, open the SQL file in pgAdmin (Query Tool), paste and run. See [docs/migrations/README.md](migrations/README.md) (section "Manual apply via pgAdmin") for steps.
