<?php

class Db
{
    private static $pdo = null;

    public static function get()
    {
        if (self::$pdo === null) {
            Config::loadEnv();
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                Config::get('POSTGRES_HOST', 'localhost'),
                Config::get('POSTGRES_PORT', '5432'),
                Config::get('POSTGRES_DB', 'add_product')
            );
            self::$pdo = new PDO($dsn, Config::get('POSTGRES_USER', ''), Config::get('POSTGRES_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return self::$pdo;
    }
}
