<?php

require_once __DIR__ . '/Db.php';

class EmallCredentialsRepository
{
    /**
     * @param int $userId
     * @return array|null { api_key } or null if not set
     */
    public static function getForUser($userId)
    {
        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT api_key FROM user_emall_credentials WHERE user_id = ?');
        $stmt->execute([(int) $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['api_key'] === '') {
            return null;
        }
        return ['api_key' => $row['api_key']];
    }

    /**
     * @param int $userId
     * @param string $apiKey
     * @return void
     */
    public static function saveForUser($userId, $apiKey)
    {
        $pdo = Db::get();
        $stmt = $pdo->prepare(
            'INSERT INTO user_emall_credentials (user_id, api_key, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON CONFLICT (user_id) DO UPDATE SET
               api_key = EXCLUDED.api_key,
               updated_at = NOW()'
        );
        $stmt->execute([(int) $userId, $apiKey]);
    }
}
