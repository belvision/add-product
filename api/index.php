<?php
/**
 * API entrypoint used by .htaccess rewrite: /api/* -> /api/index.php
 * Must delegate to the actual backend router.
 */
require __DIR__ . '/../backend/api/index.php';
