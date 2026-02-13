# Deployment (frontend / backend separation)

The project is split so that **only the frontend** is exposed as the web root; the backend (API, sources, storage) must not be served directly.

## Layout

- **Document root**: set to `frontend/` (project-relative path). All HTTP requests are handled by `frontend/index.php` (front controller).
- **Backend** (`backend/`) must **not** be reachable over HTTP (no URL should map to `backend/api/`, `backend/src/`, or `backend/storage/`).

## Apache

- Set `DocumentRoot` to the `frontend/` directory.
- Route everything through the front controller:

```apache
DocumentRoot /path/to/project/frontend

<Directory /path/to/project/frontend>
    AllowOverride None
    Require all granted
    FallbackResource /index.php
</Directory>
```

- Ensure no alias or directory exposes `backend/`. If the project root is visible, restrict or deny access to `backend/`:

```apache
<Directory /path/to/project/backend>
    Require all denied
</Directory>
```

## Nginx

- Root should point to `frontend/`:

```nginx
root /path/to/project/frontend;
index index.php;

# Static assets under frontend/public
location /public/assets/ {
    try_files $uri =404;
}

# Front controller for everything else
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php-fpm.sock;  # or your PHP-FPM upstream
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

- Do **not** add a location that serves files from `backend/`. Only the front controller in `frontend/` should `require` backend code.

## Single .env

- Keep one `.env` file in the **project root** (same level as `frontend/` and `backend/`).
- The backend loads it via `Config::loadEnv()` using `dirname(__DIR__, 2) . '/.env'` from `backend/src/Config.php`.

## Smoke check

After deployment:

- Open `/login`, `/register`, `/cabinet`, `/add`, `/draft/{id}` — all should render.
- API and auth: `/api/...` and `/auth/...` should respond (via front controller forwarding to `backend/api/index.php`).
- Image uploads should still save under `backend/storage/...`.
