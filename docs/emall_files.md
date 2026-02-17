# Файлы и маршрутизация eMall (отдельно от Ozon)

Добавление товаров для eMall вынесено в **отдельные файлы**, чтобы не менять логику Ozon. При вкладке «eMall» подключаются только эти ресурсы; при «Ozon» — только Ozon.

## Файлы, относящиеся только к eMall

| Назначение | Путь |
|------------|------|
| Страница/фрагмент мастера eMall | `frontend/public/emall-wizard.php` |
| Стили мастера eMall | `frontend/public/assets/emall/emall-wizard.css` |
| Логика мастера eMall (шаги, пайплайн, черновик) | `frontend/public/assets/emall/emall-wizard.js` |
| Пайплайн категорий eMall (DeepSeek, Qdrant, Postgres) | `backend/src/EmallCategoryPipeline.php` |
| Импорт справочника категорий из API eMall | `backend/scripts/emall_import_categories.php` |
| Документация по импорту категорий | `docs/emall_categories_import.md` |

## Общая точка входа (разводка по маркетплейсу)

- **`frontend/public/add.php`** — одна страница «Добавить товар». По `?marketplace=emall` или `?marketplace=ozon`:
  - подключается свой CSS/JS (emall или ozon);
  - рендерится свой контейнер: `#emallWizard` или `#ozonWizard`.

То есть **ozon-wizard.js и CategoryPipeline (Ozon) не подключаются и не вызываются**, когда открыт eMall, и наоборот.

## API

- Один и тот же эндпоинт `POST /api/pipeline/category:detect`. В теле запроса передаётся **`marketplace`** (`ozon` или `emall`). Роут в `backend/api/routes/PipelineRoutes.php` вызывает:
  - при `marketplace === 'emall'` — `EmallCategoryPipeline::run()`;
  - иначе — `CategoryPipeline::run()` (Ozon).

Озон не затронут: при вкладке Ozon фронт шлёт `marketplace: 'ozon'` (или не шлёт — по умолчанию ozon), бэкенд выполняет только Ozon-пайплайн.

## 504 при запуске пайплайна eMall

Если при нажатии «Запустить пайплайн» на eMall запрос «висит» и затем приходит **504 Gateway Timeout**, причина — таймаут nginx (или другого прокси) на долгий запрос. Пайплайн eMall выполняется 1–3 минуты. Нужно увеличить таймауты для API, см. **docs/backend/deploy.md** (раздел «Таймауты и 504 при пайплайне категорий»).
