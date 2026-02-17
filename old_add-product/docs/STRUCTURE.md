# Project structure: frontend, marketplaces, shared

Исходники фронта и статические ассеты живут в **frontend/public**. Корневая папка **public/** — копия ассетов для деплоя, когда Document root указывает на корень проекта (см. deploy).

## Правила

- **Исходники:** `/frontend/public` — PHP-страницы, CSS/JS, разложенные по `marketplaces/ozon`, `marketplaces/emall`, `shared`.
- **Отдаётся в проде:** при Document root = `frontend/` запросы вида `$base/public/assets/...` и `$base/public/js/...` обслуживаются из `frontend/public/`; при Document root = корень проекта — из корневой `public/` (нужно держать её в синхронизации, см. скрипт ниже).
- **Редактировать:** канонические файлы в `marketplaces/*` и `js/shared/`; в старых путях лежат legacy-копии/обёртки для совместимости URL.

## Дерево ключевых папок

```
frontend/
  index.php                    # Front controller (маршруты, require страниц)
  public/
    add.php                    # Страница «Добавить товар» (ozon/emall по ?marketplace=)
    cabinet.php
    login.php
    register.php
    verify-email.php
    draft.php
    ozon-wizard.php            # Legacy entry → include marketplaces/ozon/ozon-wizard-page.php
    emall-wizard.php           # Legacy entry → include marketplaces/emall/emall-wizard-fragment.php
    marketplaces/
      ozon/
        ozon-wizard-page.php   # Полная страница мастера Ozon (HTML + скрипты)
      emall/
        emall-wizard-fragment.php  # Фрагмент: #emallWizard + скрипты (для add.php?marketplace=emall)
    shared/                    # (пока пусто; общие компоненты при необходимости)
    assets/
      ozon-wizard.css          # Legacy copy (см. таблицу)
      ozon-wizard.js           # Legacy copy
      emall/
        emall-wizard.css       # Legacy copy
        emall-wizard.js        # Legacy copy
      marketplaces/
        ozon/
          ozon-wizard.css      # Canonical source
          ozon-wizard.js       # Canonical source
        emall/
          emall-wizard.css     # Canonical source
          emall-wizard.js      # Canonical source
    js/
      api.js                   # Legacy copy (URL /public/js/api.js)
      shared/
        api.js                 # Canonical source

public/                        # Docroot copy (для деплоя с Document root = корень проекта)
  assets/
    ozon-wizard.css
    ozon-wizard.js
    emall/
      emall-wizard.css
      emall-wizard.js
  js/
    api.js
```

## Маппинг: старый путь → новый источник → способ совместимости

| Old path (URL / путь в коде)        | New source path (canonical)                    | Compatibility method        |
|------------------------------------|------------------------------------------------|-----------------------------|
| `frontend/public/ozon-wizard.php`  | `frontend/public/marketplaces/ozon/ozon-wizard-page.php` | include (legacy entry)      |
| `frontend/public/emall-wizard.php` | `frontend/public/marketplaces/emall/emall-wizard-fragment.php` | include (legacy entry)      |
| `frontend/public/assets/ozon-wizard.js`  | `frontend/public/assets/marketplaces/ozon/ozon-wizard.js`  | copy + comment in legacy   |
| `frontend/public/assets/ozon-wizard.css`  | `frontend/public/assets/marketplaces/ozon/ozon-wizard.css` | copy + comment in legacy   |
| `frontend/public/assets/emall/emall-wizard.js`  | `frontend/public/assets/marketplaces/emall/emall-wizard.js`  | copy + comment in legacy   |
| `frontend/public/assets/emall/emall-wizard.css` | `frontend/public/assets/marketplaces/emall/emall-wizard.css` | copy + comment in legacy   |
| `frontend/public/js/api.js`        | `frontend/public/js/shared/api.js`             | copy + comment in legacy   |
| `public/assets/*`, `public/js/*`   | `frontend/public/` (те же legacy/canonical)    | sync script → copy         |

После правок в canonical-файлах нужно обновить legacy-копии (ручное копирование или скрипт) и при необходимости прогнать синхронизацию в корневую `public/`.

## Синхронизация корневой public/

Скрипт копирует из `frontend/public` в корневую `public/` (без сборщика):

```bash
php backend/scripts/sync-public-assets.php
```

Запускать из корня проекта. Использовать при деплое, когда Document root указывает на корень и статика берётся из `/public/`.

## Глобальные переменные в JS (__OZON_* и алиасы)

В eMall-фрагменте и add.php для совместимости с общим api.js и визардами по-прежнему выставляются:

- `window.__OZON_LANG__`, `window.__OZON_BASE__` — legacy, используются api.js и визардами.
- `window.__APP_BASE__`, `window.__MARKETPLACE__`, `window.__LANG__` — предпочтительные имена для нового кода; при необходимости можно опираться на них и оставить __OZON_* как алиасы.

Поведение не менялось: дублирование имён оставлено намеренно, чтобы не ломать текущую загрузку и логику.

## Проверки после изменений

1. **PHP lint** (из корня проекта):
   ```bash
   php -l frontend/index.php
   php -l frontend/public/ozon-wizard.php
   php -l frontend/public/emall-wizard.php
   php -l frontend/public/marketplaces/ozon/ozon-wizard-page.php
   php -l frontend/public/marketplaces/emall/emall-wizard-fragment.php
   php -l backend/scripts/sync-public-assets.php
   ```
   Ожидается: `No syntax errors detected` для каждого файла.

2. **404-риски:** все ссылки в PHP ведут на:
   - `$base/public/assets/ozon-wizard.css`, `ozon-wizard.js`
   - `$base/public/assets/emall/emall-wizard.css`, `emall-wizard.js`
   - `$base/public/js/api.js`
   Эти пути должны существовать в `frontend/public/` (при Document root = frontend) или в корневой `public/` (при Document root = корень проекта).

3. **Наличие файлов:**
   - `frontend/public/assets/ozon-wizard.js`, `ozon-wizard.css`
   - `frontend/public/assets/emall/emall-wizard.js`, `emall-wizard.css`
   - `frontend/public/js/api.js`
   - `public/assets/ozon-wizard.js`, `ozon-wizard.css`
   - `public/assets/emall/emall-wizard.js`, `emall-wizard.css`
   - `public/js/api.js`

После любых чисток (env, md, миграции) убедитесь, что эти пути не менялись и ассеты по-прежнему отдаются без 404. См. также [docs/ENV.md](ENV.md) и [docs/DB.md](DB.md).
