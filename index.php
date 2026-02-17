<?php
/**
 * Root entrypoint.
 * Needed when DocumentRoot points to the project root.
 * Delegates all routing to frontend front-controller.
 */
require __DIR__ . '/frontend/index.php';
