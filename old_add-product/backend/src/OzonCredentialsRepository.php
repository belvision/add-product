<?php

require_once __DIR__ . '/Db.php';

class OzonCredentialsRepository
{
    /**
     * @param int $userId
     * @return array|null { client_id, api_key } or null if not set
     */
    public static function getForUser($userId)
    {
        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT ozon_client_id, ozon_api_key FROM user_ozon_credentials WHERE user_id = ?');
        $stmt->execute([(int) $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'client_id' => $row['ozon_client_id'],
            'api_key' => $row['ozon_api_key'],
        ];
    }

    /**
     * @param int $userId
     * @param string $clientId
     * @param string $apiKey
     * @return void
     */
    public static function saveForUser($userId, $clientId, $apiKey)
    {
        $pdo = Db::get();
        $stmt = $pdo->prepare(
            'INSERT INTO user_ozon_credentials (user_id, ozon_client_id, ozon_api_key, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())
             ON CONFLICT (user_id) DO UPDATE SET
               ozon_client_id = EXCLUDED.ozon_client_id,
               ozon_api_key = EXCLUDED.ozon_api_key,
               updated_at = NOW()'
        );
        $stmt->execute([(int) $userId, $clientId, $apiKey]);
    }
}
