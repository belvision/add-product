#!/usr/bin/env php
<?php
/**
 * Minimal migrations runner for PostgreSQL.
 * Usage: php backend/bin/migrate.php init | status | up
 */

$projectRoot = dirname(__DIR__, 2);
$ds = DIRECTORY_SEPARATOR;
$envPath = null;
if (is_file($projectRoot . $ds . '.env')) {
    $envPath = $projectRoot . $ds . '.env';
} elseif (is_file($projectRoot . $ds . 'env' . $ds . '.env')) {
    $envPath = $projectRoot . $ds . 'env' . $ds . '.env';
}
if ($envPath === null) {
    fwrite(STDERR, "Error: .env not found (tried project root and env/.env)\n");
    exit(1);
}

require_once dirname(__DIR__) . $ds . 'src' . $ds . 'Config.php';
require_once dirname(__DIR__) . $ds . 'src' . $ds . 'Db.php';

Config::loadEnv($envPath);

$migrationsDir = dirname(__DIR__) . $ds . 'migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Error: migrations directory not found: {$migrationsDir}\n");
    exit(1);
}

try {
    $pdo = Db::get();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        version TEXT PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )"
);

$command = $argv[1] ?? '';
if ($command === 'init') {
    echo "OK\n";
    exit(0);
}
if ($command !== 'status' && $command !== 'up') {
    fwrite(STDERR, "Usage: php backend/bin/migrate.php init | status | up\n");
    exit(1);
}

$lockAcquired = $pdo->query("SELECT pg_try_advisory_lock(hashtext('add_product_migrations'))")->fetchColumn();
if (!$lockAcquired) {
    fwrite(STDERR, "Migrations already running\n");
    exit(1);
}

try {
    $applied = [];
    foreach ($pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY version") as $row) {
        $applied[$row['version']] = $row['applied_at'];
    }

    $files = glob($migrationsDir . $ds . '*.sql');
    if ($files === false) {
        fwrite(STDERR, "Error: could not read migrations directory\n");
        exit(1);
    }
    sort($files);

    $pending = [];
    foreach ($files as $path) {
        $version = pathinfo($path, PATHINFO_FILENAME);
        if (!isset($applied[$version])) {
            $pending[] = ['version' => $version, 'path' => $path];
        }
    }

    if ($command === 'status') {
        echo "Applied:\n";
        if (empty($applied)) {
            echo "  (none)\n";
        } else {
            foreach ($applied as $v => $at) {
                echo "  {$v}  @ {$at}\n";
            }
        }
        echo "Pending:\n";
        if (empty($pending)) {
            echo "  (none)\n";
        } else {
            foreach ($pending as $p) {
                echo "  {$p['version']}\n";
            }
        }
        exit(0);
    }

    // up
    if (empty($pending)) {
        echo "No pending migrations.\n";
        exit(0);
    }

    foreach ($pending as $p) {
        $version = $p['version'];
        $path = $p['path'];
        $sql = file_get_contents($path);
        if ($sql === false) {
            fwrite(STDERR, "Error: could not read {$path}\n");
            exit(1);
        }
        $sql = trim($sql);
        if ($sql === '') {
            fwrite(STDERR, "Error: empty migration {$path}\n");
            exit(1);
        }

        $start = microtime(true);
        try {
            $pdo->beginTransaction();
            $pdo->exec($sql);
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (:v)");
            $stmt->execute(['v' => $version]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $sqlstate = $e->getCode();
            if (!is_string($sqlstate) && method_exists($e, 'errorInfo')) {
                $info = $e->errorInfo();
                $sqlstate = $info[0] ?? '';
            }
            if ($sqlstate === '23505') {
                // Already applied by another process; continue
            } else {
                fwrite(STDERR, "Error applying {$version}: " . $e->getMessage() . "\n");
                exit(1);
            }
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        echo "Applied: {$version} ({$ms}ms)\n";
    }

    echo "Done.\n";
} finally {
    $pdo->query("SELECT pg_advisory_unlock(hashtext('add_product_migrations'))");
}

exit(0);
