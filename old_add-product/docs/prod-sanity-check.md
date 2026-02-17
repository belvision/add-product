# Prod sanity check (curl, no PHP-CLI migrations)

Выполнять на сервере или локально после деплоя. Замените `https://add.logistgo.pro` на ваш base URL при необходимости.

## 1) GET /api/me без cookie → 401 + JSON AUTH_REQUIRED

```bash
curl -i https://add.logistgo.pro/api/me
```

**Ожидаем:** HTTP 401, заголовок `Content-Type: application/json`, тело вида:
`{"ok":false,"error":{"code":"AUTH_REQUIRED","message":"Authentication required",...}}`

---

## 2) Register

```bash
curl -i -c cookies.txt -H "Content-Type: application/json" -d "{\"email\":\"test@example.com\",\"password\":\"password123\"}" https://add.logistgo.pro/auth/register
```

**Ожидаем:** HTTP 200, JSON `ok: true`, cookie сессии в `cookies.txt`.

---

## 3) GET /api/me с cookie → 200 + user

```bash
curl -i -b cookies.txt https://add.logistgo.pro/api/me
```

**Ожидаем:** HTTP 200, JSON `ok: true`, `data.user_id`, `data.email`, `data.email_verified`.

---

## 4) GET ozon-credentials

```bash
curl -i -b cookies.txt https://add.logistgo.pro/api/me/ozon-credentials
```

**Ожидаем:** HTTP 200, JSON `ok: true`, `data.clientId` (или null), `data.apiKeyMasked` (или null).

---

## 5) PUT ozon-credentials (сохранить ключи)

```bash
curl -i -b cookies.txt -H "Content-Type: application/json" -X PUT -d "{\"clientId\":\"123\",\"apiKey\":\"abc\"}" https://add.logistgo.pro/api/me/ozon-credentials
```

**Ожидаем:** HTTP 200, JSON `ok: true`, `data.saved: true`.

---

## 6) Проверка, что ответы — JSON, не HTML

По любому из запросов выше: в ответе заголовок должен быть `Content-Type: application/json`, тело — валидный JSON, не HTML-страница.

---

**Примечание:** Миграции БД выполняются отдельно (например `php backend/bin/migrate.php` или вручную). Этот чеклист проверяет только HTTP API и auth flow.
