# Deployment (frontend / backend separation)

The project is split so that **only the frontend** is exposed as the web root; the backend (API, sources, storage) must not be served directly.

## Layout

- **Document root**: set to `frontend/` (project-relative path). All HTTP requests are handled by `frontend/index.php` (front controller).
- **Backend** (`backend/`) must **not** be reachable over HTTP (no URL should map to `backend/api/`, `backend/src/`, or `backend/storage/`).
- **Static assets** are available at `/public/*` (e.g. `/public/js/api.js`, `/public/assets/ozon-wizard.css`). The repo includes a root `public/` directory (copy of `frontend/public/` assets) so that when DocumentRoot is the project root, these URLs still resolve. If DocumentRoot is `frontend/`, paths are built with `$base` and work the same.

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

## Таймауты и 504 при пайплайне категорий

Пайплайн «категория» (Ozon и **eMall**: DeepSeek → embedding → Qdrant → DeepSeek) может выполняться **1–3 минуты**. Если nginx/proxy обрывает долгие запросы, приходят **504 Gateway Timeout** — в интерфейсе «Пайплайн запускается...» и затем «Server returned non-JSON (status 504)».

**Что сделано в коде:** для `POST /api/pipeline/category:detect` и для `GET /api/progress/stream` в PHP выставлен увеличенный `set_time_limit` (до 300 с), чтобы скрипт не обрывался по лимиту PHP. Роут один и тот же для Ozon и eMall (тело запроса содержит `marketplace`).

**Что нужно на стороне nginx:** увеличить таймауты проксирования для API, иначе 504 неизбежен. Например:

```nginx
location ~ ^/(frontend/)?api/(pipeline|progress) {
    proxy_read_timeout 300s;
    proxy_connect_timeout 60s;
    proxy_send_timeout 300s;
    # ... остальная конфигурация proxy_pass к PHP
}
```

Либо для всего `location ~ \.php$` задать `fastcgi_read_timeout 300;` (или больше), если все долгие запросы идут через PHP.

## Smoke check

After deployment:

- Open `/login`, `/register`, `/cabinet`, `/add`, `/draft/{id}` — all should render.
- API and auth: `/api/...` and `/auth/...` should respond (via front controller forwarding to `backend/api/index.php`).
- Image uploads should still save under `backend/storage/...`.
