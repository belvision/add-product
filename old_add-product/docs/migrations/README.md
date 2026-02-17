# Database migrations (PostgreSQL)

Migrations are plain SQL files in `backend/migrations/*.sql`, applied in lexicographic order by filename. Applied versions are recorded in the `schema_migrations` table.

## Prerequisites

- PHP CLI with PDO PostgreSQL (`pdo_pgsql`)
- `.env` with `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`

**Env file lookup** (from project root): the runner looks for `.env` in the project root first, then in `env/.env` if not found. At least one must exist or the script exits with an error.

## Commands

From the **project root**:

| Command | Description |
|--------|-------------|
| `php backend/bin/migrate.php init` | Create the `schema_migrations` table (idempotent). Use for CI or to check DB connectivity. |
| `php backend/bin/migrate.php status` | List applied migrations (with timestamps) and pending migration filenames. |
| `php backend/bin/migrate.php up` | Apply all pending migrations in order. |

**Advisory lock:** `status` and `up` take a Postgres session-level advisory lock. If another process is already running migrations, you get "Migrations already running" and exit code 1. The lock is released on normal exit and on failure (e.g. migration error).

## Running locally

```bash
php backend/bin/migrate.php init    # optional: ensure schema_migrations exists
php backend/bin/migrate.php status
php backend/bin/migrate.php up
```

Ensure the database exists and credentials in `.env` point to it.

## Running in production

1. Deploy the code (including `backend/migrations/` and `backend/bin/migrate.php`).
2. Ensure `.env` on the server has the production Postgres credentials (same lookup order: root `.env` then `env/.env`).
3. From the app directory (project root):

   ```bash
   php backend/bin/migrate.php init    # optional
   php backend/bin/migrate.php status
   php backend/bin/migrate.php up
   ```

4. Run as the same user that runs the app, or a user with sufficient DB privileges.
5. Each migration runs in a transaction; on failure it rolls back and the script exits with code 1.

## Manual apply via pgAdmin (when pdo_pgsql is unavailable)

If the server has no PHP `pdo_pgsql` and you do not install it, apply the schema manually in pgAdmin:

1. Connect to the target database in pgAdmin.
2. Open **Query Tool** for that database.
3. Open `backend/migrations/0001_init.sql`, copy its contents, paste into the Query Tool and execute.
4. Verify tables exist:

```sql
SELECT to_regclass('public.users') AS users,
       to_regclass('public.drafts') AS drafts,
       to_regclass('public.draft_images') AS draft_images,
       to_regclass('public.email_verification_tokens') AS tokens;
```

All four columns should return a non-null value (e.g. public.users).

**Note:** `CREATE EXTENSION pgcrypto` in the migration may require elevated privileges. If it fails, run it with a privileged role (e.g. superuser) or ask your DBA; do not remove it from the migration.

This manual method is a one-time bootstrap. When `pdo_pgsql` is available, you can use the CLI runner (`migrate.php`) for future migrations.

## Adding new migrations

Add a new `.sql` file with a lexicographically greater name, e.g. `0002_add_foo.sql`. The version recorded is the filename without extension (e.g. `0002_add_foo`). Do not edit or remove already-applied migration files.
