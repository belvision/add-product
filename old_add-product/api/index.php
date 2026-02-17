<?php
/**
 * API entrypoint for shared hosting.
 *
 * We intentionally use a real file (/api/index.php) instead of relying on
 * Apache/Nginx rewrite rules for routes like /api/drafts.
 *
 * Frontend JS calls /api/index.php/... and the backend router reads REQUEST_URI
 * to dispatch endpoints.
 */
require __DIR__ . '/../backend/api/index.php';
