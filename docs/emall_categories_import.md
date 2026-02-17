# Импорт категорий eMall в Postgres

Справочник категорий eMall хранится в таблице `public.category_emall` и заполняется из eMall API с помощью CLI-скрипта.

## Переменные окружения

Задайте в `.env` (в корне проекта или там, откуда запускаете скрипт):

| Переменная | Описание |
|------------|----------|
| `EMALL_API_BASE` | Базовый URL API каталога eMall, без завершающего `/`. Пример: `https://api-preprod.emall.by/open/api/v1/catalog` |
| `EMALL_API_TOKEN` | Токен авторизации. Подставляется в заголовок `Authorization` как есть. Пример: `Bearer <ваш_токен>` |

Для подключения к БД используются стандартные переменные проекта: `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`.

## Запуск импорта

Из корня проекта:

```bash
php backend/scripts/emall_import_categories.php
```

Либо из каталога `backend`:

```bash
php scripts/emall_import_categories.php
```

Скрипт:

- запрашивает у API страницы категорий (`GET {EMALL_API_BASE}/categories?page=1&per_page=100` и далее);
- обходит дерево категорий (включая `children`);
- строит путь вида `Root / Child / Subchild`;
- выполняет upsert по `category_id`: вставка или обновление `title_cat`, `path`, `parent_id`, `statuse`, `disabled`;
- выводит количество полученных из API категорий и количество обработанных (upserted);
- при HTTP != 200 выводит тело ответа API в stderr и завершается с ненулевым кодом.

## Проверка количества записей

В psql или любом клиенте Postgres:

```sql
SELECT COUNT(*) FROM public.category_emall;
```

Проверка по родителям:

```sql
SELECT parent_id, COUNT(*) FROM public.category_emall GROUP BY parent_id ORDER BY parent_id NULLS FIRST;
```

## Миграция таблицы

Таблица создаётся миграцией:

```bash
psql -U ... -d ... -f backend/migrations/0005_category_emall.sql
```

Либо выполните SQL из `backend/migrations/0005_category_emall.sql` вручную.

## Связь с Qdrant и пайплайн

В коллекции Qdrant `categoryEmall` в **payload** хранятся поля:
- `name` — название категории
- `path` — человекочитаемый путь (например `Root / Child / Subchild`)
- `emall_id` — реальный ID категории eMall

**Важно:** ID точки в Qdrant (point id) — это не emall_id. Связь с Postgres идёт по **payload.emall_id = category_emall.category_id**.

Пайплайн eMall:
1. Получает top10 из Qdrant (with_payload=true).
2. Для каждого hit берёт `emall_id` из payload, собирает список `category_id`.
3. Одним запросом выбирает справочник: `SELECT ... FROM category_emall WHERE category_id IN (...)`.
4. Обогащает кандидатов: title_cat/path из БД (источник истины), score из Qdrant. Если записи в БД нет — используется payload (name, path).
5. DeepSeek возвращает **category_id** (emall_id). В черновик сохраняется `editedJson.selected_category = { category_id, title_cat, path }`.

Колонки `id_embedding`, `id_type`, `type_name` в таблице оставлены nullable «на будущее»; текущая логика работает без них.

## Ozon

Импорт и таблицы Ozon не изменяются. Всё выше — только для eMall.
