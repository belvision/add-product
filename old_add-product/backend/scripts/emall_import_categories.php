<?php
/**
 * eMall categories import -> public.category_emall
 *
 * ВНИМАНИЕ: в этом варианте токены/пароли зашиты в код (временно).
 * Работает без PDO драйверов: грузит в Postgres через psql \copy и UPSERT.
 *
 * Запуск:
 *   php backend/scripts/emall_import_categories.php
 */

declare(strict_types=1);

function fail(string $msg, int $code = 1): void {
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}
function info(string $msg): void {
    fwrite(STDOUT, $msg . PHP_EOL);
}

/** ====== ЗАШИТЫЕ НАСТРОЙКИ (ВРЕМЕННО) ====== */
$EMALL_API_BASE  = 'https://api-preprod.emall.by/open/api/v1/catalog';
$EMALL_API_TOKEN = 'Bearer 270|MPADzEQeSMjVyOVRpHn5f1Ytmc2xfy8uhpvHbaWXadeb5bf6';

$PGHOST     = '10.1.1.215';
$PGPORT     = '5432';
$PGDATABASE = 'add';
$PGUSER     = 'postgres1848';
$PGPASSWORD = '15f5@jirlvy2@Q7YYa1';

/** Параметры */
$perPage = 100;   // как у тебя в отчёте
$timeout = 25;

if ($EMALL_API_TOKEN === '' || $EMALL_API_TOKEN === 'Bearer <token>') {
    fail("EMALL_API_TOKEN is empty");
}

/** ====== HTTP JSON GET ====== */
function http_get_json(string $url, array $headers, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException("HTTP error: $err");
    }
    if ($code !== 200) {
        throw new RuntimeException("API status=$code, body=$body");
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException("Invalid JSON: $body");
    }
    return $json;
}

/** ====== Flatten tree ====== */
function flatten_categories(array $nodes, array $parentNames = [], ?int $parentId = null, array &$out = []): void {
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        if (!isset($node['id'], $node['name'])) continue;

        $id   = (int)$node['id'];
        $name = (string)$node['name'];

        $pathParts = array_merge($parentNames, [$name]);
        $path = implode(' / ', $pathParts);

        $out[] = [
            'category_id' => $id,
            'title_cat'   => $name,
            'path'        => $path,
            'parent_id'   => $parentId,
        ];

        if (!empty($node['children']) && is_array($node['children'])) {
            flatten_categories($node['children'], $pathParts, $id, $out);
        }
    }
}

/** ====== Fetch all categories ====== */
info("Fetching eMall categories from: {$EMALL_API_BASE}/categories (per_page={$perPage})");

$all  = [];
$page = 1;

while (true) {
    $url = $EMALL_API_BASE . '/categories?page=' . $page . '&per_page=' . $perPage;

    try {
        $json = http_get_json($url, [
            'Authorization: ' . $EMALL_API_TOKEN,
            'Accept: application/json',
        ], $timeout);
    } catch (Throwable $e) {
        fail("Failed to fetch page {$page}: " . $e->getMessage());
    }

    $data = $json['data'] ?? [];
    if (!is_array($data) || count($data) === 0) {
        break;
    }

    flatten_categories($data, [], null, $all);

    // остановка пагинации
    if (count($data) < $perPage) {
        break;
    }

    $page++;
    if ($page > 10000) {
        fail("Too many pages, stop.");
    }
}

if (count($all) === 0) {
    fail("No categories received from API.");
}

info("Flattened rows: " . count($all));

/** ====== Write CSV ====== */
$tmpCsv = tempnam(sys_get_temp_dir(), 'emall_cat_');
if ($tmpCsv === false) fail("Cannot create temp file");
$tmpCsv .= '.csv';

$fp = fopen($tmpCsv, 'wb');
if (!$fp) fail("Cannot open temp csv for write: {$tmpCsv}");

fputcsv($fp, ['category_id', 'title_cat', 'path', 'parent_id']);
foreach ($all as $row) {
    fputcsv($fp, [
        (string)$row['category_id'],
        (string)$row['title_cat'],
        (string)$row['path'],
        $row['parent_id'] === null ? '' : (string)$row['parent_id'],
    ]);
}
fclose($fp);

info("CSV prepared: {$tmpCsv} (" . filesize($tmpCsv) . " bytes)");

/** ====== Build psql command ====== */
$psqlArgs = ['psql', '-h', $PGHOST, '-U', $PGUSER, '-d', $PGDATABASE, '-p', $PGPORT];
$env = $_ENV;
$env['PGPASSWORD'] = $PGPASSWORD;

/**
 * ВАЖНО: QUOTE должен быть одним байтом → QUOTE '"'
 */
$importSql = <<<SQL
\\set ON_ERROR_STOP on

BEGIN;

CREATE TEMP TABLE tmp_emall_categories (
  category_id bigint,
  title_cat   text,
  path        text,
  parent_id   bigint
) ON COMMIT DROP;

\\copy tmp_emall_categories(category_id,title_cat,path,parent_id) FROM '{$tmpCsv}' WITH (FORMAT csv, HEADER true, DELIMITER ',', QUOTE '"');

INSERT INTO public.category_emall (category_id, title_cat, path, parent_id)
SELECT
  category_id,
  title_cat,
  NULLIF(path,'')::text,
  NULLIF(parent_id::text,'')::bigint
FROM tmp_emall_categories
ON CONFLICT (category_id)
DO UPDATE SET
  title_cat = EXCLUDED.title_cat,
  path      = EXCLUDED.path,
  parent_id = EXCLUDED.parent_id;

COMMIT;

SELECT COUNT(*) AS total_in_table FROM public.category_emall;
SQL;

info("Running psql import/upsert...");

/** ====== Run psql ====== */
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($psqlArgs, $descriptors, $pipes, null, $env);
if (!is_resource($proc)) {
    @unlink($tmpCsv);
    fail("Failed to start psql");
}

fwrite($pipes[0], $importSql);
fclose($pipes[0]);

$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($proc);

@unlink($tmpCsv);

if ($exitCode !== 0) {
    fail("psql failed (code {$exitCode}). stderr:\n{$err}\nstdout:\n{$out}", $exitCode);
}

info("Import completed.");
info(trim($out));
exit(0);
