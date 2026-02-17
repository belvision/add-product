# Черновики по площадкам (draft marketplace): итоговые требования

## Итоговое поведение

### Backend / DraftRepository.php

- **getMarketplaceForDraft($userId, $draftId)** — читает `marketplace` из `drafts` по `id` + `user_id`.
- **tmpBase($marketplace)** → `backend/storage/marketplace/<marketplace>/tmp`.
- **tmpPathForDraft($userId, $draftId)** → определяет marketplace через `getMarketplaceForDraft`, возвращает `.../<marketplace>/tmp/<userId>/<draftId>`.
- **addImage()** сохраняет путь: `marketplace/<marketplace>/tmp/<userId>/<draftId>/images/<imageId>.jpg`.
- **getCurrentDraftRow($userId, $marketplace)** ищет один актуальный драфт со `status IN ('draft','processing')` с **ORDER BY updated_at DESC LIMIT 1**.
- **setDraftReady** и **updateDraftPatch** работают с учётом inwork-статусов (draft/processing).

### Backend / DraftRoutes.php — GET /draft

- При отсутствии черновика: **HTTP 404** и предсказуемое тело JSON с `error.code === 'NO_DRAFT'` (например `{ "ok": false, "error": { "code": "NO_DRAFT", "message": "..." } }`), чтобы фронт мог по 404 → вызвать POST /draft/init.

### Backend / DraftRoutes.php — POST /draft/reset

- Сначала: `row = getCurrentDraftRow(...)`.
- Если найден: **deleteTmpDir($uid, $row['id'])** → затем **setDraftReady(...)**.
- Далее создать/получить новый черновик и вернуть его.
- Старый "inwork" переводится в `ready`, tmp старого удаляется.

### Frontend / add.php

- **marketplace** берётся из query `marketplace=ozon|wb|emall` (default `ozon`).
- Tabs в шапке: Ozon / Wildberries / eMall, ссылки на `/add?marketplace=...&lang=...`.
- **window.__MARKETPLACE__** = выбранная площадка.
- Стили табов добавлены в `ozon-wizard.css`.

### Frontend / ozon-wizard.js

- **MARKETPLACE** из `window.__MARKETPLACE__` (fallback `ozon`), сохраняется в `wizard.marketplace`.
- **Init:** GET /draft?marketplace=... → если **404** (и `error.code === 'NO_DRAFT'`) → POST /draft/init `{ marketplace }`.
- Если драфт найден — модал: «Продолжить / Удалить и начать заново».
- **Reset:** POST /draft/reset `{ marketplace }`, подставить новый draft в wizard.
- Сохранение шагов: PATCH /draft `{ marketplace, patch }`.
- Upload/delete картинок остаётся через существующие `drafts/:id/images:*` (marketplace определяется на бэке через `draft_id`).

---

## Гарантия «один активный черновик»

Обеспечивается:

1. **Уникальным индексом `uq_drafts_one_inwork_per_market`:**
   - `UNIQUE (user_id, marketplace) WHERE status IN ('draft','processing')`

2. **Корректной логикой getCurrentDraftRow:**
   - фильтр `status IN ('draft','processing')`;
   - **ORDER BY updated_at DESC LIMIT 1** (чтобы при редких сбоях/гонках не подцепить не тот inwork-драфт).

В документации и коде **не** использовать формулировку «WHERE status = 'draft'» для этого ограничения — только inwork (draft + processing) и индекс `uq_drafts_one_inwork_per_market`.

---

## Схема таблицы drafts (справочно)

- Таблица: `public.drafts`
- Ограничения: `drafts_status_check` (status IN draft/processing/ready/published/failed), `drafts_marketplace_check` (ozon/emall/wb).
- Индекс: **uq_drafts_one_inwork_per_market** — UNIQUE (user_id, marketplace) WHERE status IN ('draft','processing').
- Доп. индексы по (user_id, marketplace, updated_at) для быстрого выбора текущего inwork-драфта с ORDER BY updated_at DESC.
