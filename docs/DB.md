# База данных (PostgreSQL)

## Как поднять базу с нуля

1. Создайте базу и убедитесь, что в `/.env` (или в `env/.env` при запуске только мигратора) заданы `POSTGRES_*`.
2. Из корня проекта выполните:

   ```bash
   php backend/bin/migrate.php init    # создаёт таблицу schema_migrations при необходимости
   php backend/bin/migrate.php up       # применяет все неприменённые миграции по порядку
   ```

3. Либо применить схему вручную (если нет PHP/PDO): откройте `backend/migrations/0001_init.sql` в pgAdmin (Query Tool), выполните. Затем по очереди выполните остальные `0002_*.sql` … `0006_*.sql`. Подробнее — [docs/migrations/README.md](migrations/README.md) (раздел «Manual apply via pgAdmin»).

## Где что лежит

| Что | Путь |
|-----|------|
| Миграции (порядок по имени) | `backend/migrations/*.sql` |
| Учёт применённых версий | таблица `schema_migrations` (version, applied_at) |
| Скрипт применения | `backend/bin/migrate.php` (команды: `init`, `status`, `up`) |

Текущие миграции: `0001_init.sql` … `0006_user_emall_credentials.sql`. Скрипт обрабатывает только файлы `*.sql` в каталоге `backend/migrations/` (вложенные каталоги не сканируются).

## Legacy-миграции (на будущее)

Если какую-то миграцию решат вывести из оборота (не применять на новых инстансах), её можно перенести в `backend/migrations/legacy/`. Скрипт `migrate.php` сейчас читает только `backend/migrations/*.sql`, поэтому файлы в `legacy/` не будут применяться. Перед переносом убедитесь, что нужные изменения уже есть в актуальной схеме или в более поздних миграциях.

## Дополнительно

- Полное описание миграций и команд: [docs/migrations/README.md](migrations/README.md).
- Общая навигация по документации: [docs/README.md](README.md).
