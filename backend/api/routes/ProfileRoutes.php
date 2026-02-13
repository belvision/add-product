<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/OzonCredentialsRepository.php';

class ProfileRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'GET' && $path === '/me') {
            $user = Auth::currentUser();
            if ($user === null) {
                fail('AUTH_REQUIRED', 'Authentication required', [], 401);
            }
            Response::success([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'email_verified' => $user['email_verified'],
            ]);
            return true;
        }

        if ($method === 'GET' && $path === '/me/ozon-credentials') {
            $uid = requireAuth();
            $creds = OzonCredentialsRepository::getForUser($uid);
            if (!$creds) {
                Response::success(['clientId' => null, 'apiKeyMasked' => null]);
                return true;
            }
            $masked = strlen($creds['api_key']) > 4
                ? '****' . substr($creds['api_key'], -4)
                : '****';
            Response::success([
                'clientId' => $creds['client_id'],
                'apiKeyMasked' => $masked,
            ]);
            return true;
        }

        if ($method === 'PUT' && $path === '/me/ozon-credentials') {
            $uid = requireAuth();
            $body = jsonBody();
            if (!is_array($body)) {
                fail('VALIDATION_ERROR', 'JSON body required', [], 400);
            }
            $clientId = isset($body['clientId']) ? trim((string) $body['clientId']) : '';
            $apiKey = isset($body['apiKey']) ? trim((string) $body['apiKey']) : '';
            if ($clientId === '' || $apiKey === '') {
                fail('VALIDATION_ERROR', 'clientId and apiKey are required and must be non-empty', [], 400);
            }
            OzonCredentialsRepository::saveForUser($uid, $clientId, $apiKey);
            Response::success(['saved' => true]);
            return true;
        }

        return false;
    }
}
