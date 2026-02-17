<?php

class Config
{
    private static $loaded = false;

    public static function loadEnv($path = null)
    {
        if (self::$loaded) {
            return;
        }
        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }
        if (!is_file($path)) {
            self::$loaded = true;
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\"'");
                if ($name !== '' && !getenv($name)) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                }
            }
        }
        self::$loaded = true;
    }

    public static function get($key, $default = null)
    {
        self::loadEnv();
        $v = getenv($key);
        if ($v === false) {
            return $default;
        }
        // Normalize APP_BASE_URL for prod: no trailing slash, no leading/trailing spaces.
        if ($key === 'APP_BASE_URL' && $v !== '') {
            return rtrim(trim($v), '/');
        }
        return $v;
    }
}
