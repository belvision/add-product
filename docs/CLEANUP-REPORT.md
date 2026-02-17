# Отчёт о чистке: env, md, миграции

Дата: 2025-02 (после реорганизации marketplaces/shared).

## 1) ENV: таблица файлов и итог

| path | Назначение по факту | Где используется | Можно трогать? |
|------|---------------------|------------------|----------------|
| `/.env` | Главный конфиг (секреты) | `Config::loadEnv()` без аргументов вызывается из `backend/api/index.php`, `frontend/index.php`, `Db.php`, `Auth.php`. Путь к файлу: `dirname(__DIR__, 2) . '/.env'` в `Config.php`. | Не коммитить (в .gitignore). Не удалять. |
| `env/.env` | Fallback только для мигратора | `backend/bin/migrate.php`: если в корне нет `/.env`, читает `env/.env` и передаёт путь в `Config::loadEnv($envPath)`. | Оставлен как legacy fallback. В .gitignore. |
| `env/.env.example` | Единственный версионируемый шаблон | Документация: копировать в корень как `.env`. Используется вручную. | Единственный шаблон. Добавлен комментарий про docs/ENV.md. |
| `/env.example` (корень) | Дубликат шаблона (возможные секреты) | Нигде в коде не читается. | Добавлен в .gitignore. Не удаляли (может быть у кого-то локально). В ENV.md указано не использовать. |

**Главный env:** `/.env` в корне. Порядок поиска при запуске сайта/API: только корневой `/.env`. При запуске `migrate.php`: 1) `/.env`, 2) `env/.env`.

**Что сделано (без ломания):**
- Добавлен **docs/ENV.md**: где главный env, где шаблон, порядок поиска, обязательные переменные, про дубликаты.
- В **.gitignore** добавлен `/env.example`, чтобы корневой дубликат не коммитился с секретами.
- В **env/.env.example** — комментарий «Canonical template» и ссылка на docs/ENV.md.
- В **docs/env/README.md** — ссылка на docs/ENV.md.
- Логику загрузки (Config, migrate) не меняли — совместимость сохранена.

**Удалено/заархивировано:** ничего. `env/.env` оставлен как fallback для мигратора.

---

## 2) MD / мусор: что удалено или перенесено

**Список всех *.md (и смежных):**
- docs/STRUCTURE.md, docs/README.md, docs/ENV.md, docs/DB.md, docs/prod-sanity-check.md, docs/emall_files.md, docs/emall_categories_import.md, docs/draft-marketplace-requirements.md, docs/backend/*.md, docs/migrations/README.md, docs/env/README.md — **оставлены**, используются или являются основной документацией.
- **docs/step5-cached-diff.txt** — не .md; временный diff-файл (кэш). Поиск по коду: на него нет ссылок из README, docs или скриптов.

**Действия:**
- **Перенесён в _archive/docs_legacy/:** `docs/step5-cached-diff.txt` → `_archive/docs_legacy/step5-cached-diff.txt`.
- В **\_archive/docs_legacy/README.md** описано, что это за файл и зачем архив.

**Удалено:** ничего. Только перенос одного неиспользуемого файла.

---

## 3) Миграции: структура и стратегия

**Текущее состояние:**
- Каталог: `backend/migrations/`.
- Файлы (порядок применения по имени): `0001_init.sql` … `0006_user_emall_credentials.sql`.
- Учёт: таблица `schema_migrations` (version, applied_at). Скрипт: `backend/bin/migrate.php` (init | status | up).
- Скрипт сканирует только `backend/migrations/*.sql` (вложенные каталоги не смотрит).

**Стратегия (без риска):**
- Все текущие миграции оставлены на месте — они нужны для поднятия базы с нуля.
- Устаревшие/дубли не выносились (таковых не выявлено).
- В **docs/DB.md** описано: как поднять базу с нуля (migrate up или ручное применение), где лежат миграции, что в будущем устаревшие можно переносить в `backend/migrations/legacy/` (скрипт их не подхватит).

**Ссылки в коде:** в документации упоминаются `backend/migrations/0001_init.sql`, `0005_category_emall.sql` и т.д.; пути соответствуют текущей структуре.

---

## 4) Проверки

**Выполнено:**
- Подтверждено: все ссылки на ассеты в PHP остаются на `$base/public/assets/ozon-wizard.*`, `$base/public/assets/emall/emall-wizard.*`, `$base/public/js/api.js` — URL не менялись.
- Проверено наличие файлов:
  - frontend/public: assets/ozon-wizard.js|css, assets/emall/emall-wizard.js|css, js/api.js — на месте.
  - public/: assets/ozon-wizard.js|css, assets/emall/emall-wizard.js|css, js/api.js — на месте.
- Скрипт **backend/scripts/sync-public-assets.php** актуален и копирует все перечисленные файлы из frontend/public в public/.

**Выполнить локально (если доступен PHP):**
- `php -l backend/src/Config.php`
- `php -l backend/bin/migrate.php`
- `php -l frontend/index.php`
- При необходимости: `php backend/scripts/sync-public-assets.php` — проверить, что копирование проходит без ошибок.

---

## 5) Подтверждение: старые URL и ассеты не сломаны

- В коде не менялись пути к ассетам и к .env.
- Конфиги веб-сервера не трогались.
- Legacy-прокладки (ozon-wizard.php, emall-wizard.php, legacy-копии ассетов в frontend/public и в public/) не удалялись и не переименовывались.
- 404 по старым путям не появляются: файлы по-прежнему отдаются с тех же URL.
