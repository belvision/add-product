# API (MVP-1.0)

**Locale:** Default from `APP_LOCALE_DEFAULT` (config, fallback `ru`). Override by query `lang` (ru|en) or header `X-Lang` (ru|en); header takes priority over query.

## Response envelope

Success: `{ "ok": true, "data": {...}, "meta": { "traceId": "uuid", "ts": "ISO8601" } }`

Error: `{ "ok": false, "error": { "code": "...", "message": "...", "details": {...}, "meta": { "traceId": "uuid" } } }`

## Error codes (MVP subset)

AUTH_REQUIRED, EMAIL_NOT_VERIFIED, VALIDATION_ERROR, OZON_CREDENTIALS_REQUIRED, UPLOAD_TOO_LARGE, UNSUPPORTED_IMAGE_FORMAT, SSRF_BLOCKED, FETCH_FAILED, DRAFT_NOT_FOUND, DRAFT_NOT_OWNED, PIPELINE_ALREADY_RUNNING, PIPELINE_FAILED, PUBLISH_VALIDATION_FAILED, PUBLISH_CLEANUP_FAILED, MISSING_EXTENSION, ENDPOINT_NOT_FOUND

**ENDPOINT_NOT_FOUND** — HTTP 404 when the request path does not match any API route (e.g. GET /api/does-not-exist).

## Auth

### POST /auth/register
Body: `{ "email": "...", "password": "..." }`  
Response: `{ "ok": true, "data": { "user_id": N, "email_verified": false } }`

### POST /auth/login
Body: `{ "email": "...", "password": "..." }`  
Response: `{ "ok": true, "data": { "user_id": N, "email_verified": true } }`

### POST /auth/logout
Response: `{ "ok": true, "data": { "logged_out": true } }`

### GET /auth/verify-email?token=...
Query: `token` (required).  
Response: `{ "ok": true, "data": { "verified": true } }`

### POST /auth/resend-verification
Response: `{ "ok": true, "data": { "sent": true } }`

## Profile (auth required)

### GET /api/me
Returns current user. Without session: **401**, body `{ "ok": false, "error": { "code": "AUTH_REQUIRED", "message": "Authentication required", ... } }` (JSON, no HTML).  
With session: `{ "ok": true, "data": { "user_id": N, "email": "...", "email_verified": true|false } }`

### GET /api/me/ozon-credentials
Returns masked Ozon keys (apiKey not exposed).  
Response: `{ "ok": true, "data": { "clientId": "..."|null, "apiKeyMasked": "****abcd"|null } }`

### PUT /api/me/ozon-credentials
Body: `{ "clientId": "...", "apiKey": "..." }` (both required, non-empty after trim).  
Response: `{ "ok": true, "data": { "saved": true } }`  
Errors: VALIDATION_ERROR (400) if body invalid.

## Drafts

### POST /api/drafts
Creates draft. Requires auth.  
Body: `{ "marketplace": "ozon" }` (optional, default ozon).  
Response: `{ "ok": true, "data": { "draftId", "formSchema", "editedJson", "images", "status" } }`

### GET /api/drafts/{draftId}
Requires auth, ownership.  
Response: `{ "ok": true, "data": { "draftId", "formSchema", "editedJson", "images", "status", "description", ... } }`

### PATCH /api/drafts/{draftId}
Body: `{ "description": "...", "editedJson": { ... } }` (one or both).  
Response: `{ "ok": true, "data": { "saved": true } }`

## Images

### POST /api/drafts/{draftId}/images:upload
Multipart, field `file`. jpg/jpeg/png/webp only, converted to JPG.  
Response: `{ "ok": true, "data": { "image", "images" } }`

### POST /api/drafts/{draftId}/images:from-url
Body: `{ "url": "https://..." }`. SSRF-protected.  
Response: `{ "ok": true, "data": { "image", "images" } }`  
Errors: SSRF_BLOCKED, FETCH_FAILED, UPLOAD_TOO_LARGE, UNSUPPORTED_IMAGE_FORMAT

### DELETE /api/drafts/{draftId}/images/{imageId}
Response: `{ "ok": true, "data": { "deleted": true, "images" } }`

## Pipeline

### POST /api/drafts/{draftId}/pipeline:start
Requires verified email and Ozon credentials set in cabinet.  
Response: `{ "ok": true, "data": { "started": true, "stage": "..." } }`  
Errors: OZON_CREDENTIALS_REQUIRED (400), PIPELINE_ALREADY_RUNNING

### GET /api/drafts/{draftId}/pipeline:status
Response: `{ "ok": true, "data": { "stage", "progressPct", "logs" (last 200), "qdrantTop10", "chosenCategory", "requiredFields", "filledFields", "editedJson", "finalPayloadJson", "publishStatus" } }`

## Publish

### POST /api/drafts/{draftId}/publish
Requires verified email, Ozon credentials set in cabinet, ownership, status draft|ready|payload_ready.  
Success: `{ "ok": true, "data": { "published": true, "productId": "..." } }`  
Errors: OZON_CREDENTIALS_REQUIRED (400)  
Errors: PUBLISH_VALIDATION_FAILED, PUBLISH_CLEANUP_FAILED

---

## Manual checks (MVP-1.0)

Используйте этот чеклист для ручной проверки перед релизом. Разделение: Backend (API/curl) и Frontend (браузер).

### A) Backend checks

- **Регистрация**  
  `curl -X POST .../auth/register -H "Content-Type: application/json" -d '{"email":"test@example.com","password":"Test123!"}' -c cookies.txt -v`  
  Ожидание: HTTP 200, в теле `ok: true`, `data.email_verified: false`; в ответе Set-Cookie (сессия); в БД в таблице email_verification создана запись с токеном.

- **Логин с неверными данными**  
  `curl -X POST .../auth/login -H "Content-Type: application/json" -d '{"email":"wrong@example.com","password":"wrong"}'`  
  Ожидание: HTTP 400, `ok: false`, `error.code: "INVALID_CREDENTIALS"`.

- **POST /api/drafts без сессии**  
  `curl -X POST .../api/drafts -H "Content-Type: application/json" -d '{}'`  
  Ожидание: HTTP 401, `error.code: "AUTH_REQUIRED"`.

- **POST /api/drafts с сессией**  
  `curl -X POST .../api/drafts -H "Content-Type: application/json" -d '{}' -b cookies.txt`  
  Ожидание: HTTP 200, `data.draftId` — UUID, `data.status: "draft"`.

- **Pipeline:start без verified email**  
  Создать пользователя, не подтверждать email.  
  `curl -X POST .../api/drafts/{draftId}/pipeline:start -b cookies.txt`  
  Ожидание: HTTP 403, `error.code: "EMAIL_NOT_VERIFIED"`.

- **Publish без verified email**  
  Аналогично: POST .../api/drafts/{draftId}/publish без верификации → HTTP 403, `error.code: "EMAIL_NOT_VERIFIED"`.

- **Locale: приоритет X-Lang > ?lang > APP_LOCALE_DEFAULT**  
  В .env задать `APP_LOCALE_DEFAULT=en`. Запрос с `?lang=en` и заголовком `X-Lang: ru` → ответ (например GET /api/drafts/{id}) должен содержать локализованные поля на русском (formSchema и т.д. с ru). Без заголовка при `?lang=ru` — русская локаль.

- **images:upload — лимит 8MB, конвертация в JPG**  
  Загрузить PNG/WebP через multipart `file` → ответ 200, `data.image.imageId` — UUID, файл на диске в формате .jpg. Файл > 8MB → `error.code: "UPLOAD_TOO_LARGE"`.

- **images:upload без fileinfo**  
  При отключённом расширении fileinfo: `error.code: "MISSING_EXTENSION"`, в `details.ext: "fileinfo"`.

- **images:from-url без curl**  
  При отключённом расширении curl: `error.code: "MISSING_EXTENSION"`, в `details.ext: "curl"`.

- **SSRF: блок приватных адресов**  
  `curl -X POST .../api/drafts/{draftId}/images:from-url -H "Content-Type: application/json" -d '{"url":"http://127.0.0.1/image.png"}' -b cookies.txt`  
  Ожидание: HTTP 400, `error.code: "SSRF_BLOCKED"`. Аналогично проверить URL на 10.0.0.1 или localhost.

- **images:from-url — HTTP не 200 → FETCH_FAILED**  
  URL, который возвращает HTTP 404 (или 500). Ожидание: `error.code: "FETCH_FAILED"`, в сообщении фигурирует "HTTP 404" (или соответствующий код).

- **Редиректы**  
  URL с 301/302 на разрешённый публичный URL с картинкой — запрос должен следовать редиректу (до лимита) и в итоге вернуть 200 при успешной загрузке. URL с редиректом на 127.0.0.1 → SSRF_BLOCKED.

- **Неизвестный путь**  
  `curl .../api/unknown` или `GET .../api/does-not-exist`  
  Ожидание: HTTP 404, `error.code: "ENDPOINT_NOT_FOUND"`.

- **Auth: verify-email и resend-verification**  
  GET /auth/verify-email?token=... с валидным токеном из БД → 200, `data.verified: true`.  
  POST /auth/resend-verification без сессии → 401 (AUTH_REQUIRED). С сессией и неподтверждённым email → 200 и отправка письма (или fallback в лог, если SMTP не настроен).

### B) Frontend checks

- **Роуты**  
  Открыть в браузере: `/login`, `/register`, `/cabinet`, `/add`, `/draft/{id}` (подставить реальный draftId). Страницы открываются без 404; для /draft/{id} загружается черновик при наличии сессии.

- **Редирект без сессии**  
  Выйти из аккаунта (или в режиме инкогнито). Перейти на `/add` или `/draft/{id}`. Ожидание: редирект на `/login`.

- **Publish при неподтверждённом email**  
  Залогиниться под пользователем с `email_verified: false`. Открыть драфт. Кнопка «Опубликовать» (Publish) должна быть неактивна (disabled) или с подсказкой/текстом о необходимости подтверждения email (например tooltip или текст «Подтвердите email для публикации»).

- **Start pipeline только при verified**  
  Кнопка «Запустить пайплайн» доступна только при подтверждённом email. При клике: сохранить текущий драфт (PATCH), затем вызвать POST pipeline:start; отобразить панель пайплайна.

- **Pipeline panel**  
  После запуска пайплайна: опрос GET pipeline:status (polling), отображение stage, progressPct, logs. При stage payload_ready (или ready) блок finalPayloadJson отображается раскрываемо (например `<details>`/`<summary>` или аналогично).

- **Загрузка картинок**  
  Drag-and-drop и выбор файла (images:upload), загрузка по URL (images:from-url). Удаление картинки (DELETE images/{imageId}). В списке изображений при наличии отображаются размеры/байты (если реализовано в UI).

- **Base path (подкаталог)**  
  Если приложение развёрнуто в подкаталоге (например /myapp/), проверить, что `window.__OZON_BASE__` задаётся так, чтобы запросы уходили в `/myapp/api` (или корректный base). Страницы и API вызываются без 404.
