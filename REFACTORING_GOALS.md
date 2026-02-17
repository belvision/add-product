(
echo # Цель рефакторинга
echo.
echo ## Контекст
echo Проект содержит дубли ассетов и крупные wizard-файлы. Цель — сделать структуру понятной и изменения безопасными.
echo.
echo ## Цели
echo - Один source-of-truth для каждого marketplace wizard.
echo - Автосинхронизация legacy копий в public/ через sync-public-assets.php.
echo - Разбивка wizard на модули по шагам и core-модули (state/api/utils).
echo - Стабильная работа: drafts, кнопки, определение категории, загрузка изображений.
echo.
echo ## Правило разработки
echo 1^) Правим только source-of-truth.
echo 2^) Запускаем php backend/scripts/sync-public-assets.php.
echo 3^) Проверяем в браузере и только потом коммит/деплой.
) > REFACTORING_GOALS.md
