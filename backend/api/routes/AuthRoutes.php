<?php

require_once __DIR__ . '/RouteHelpers.php';

class AuthRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && $path === '/auth/login') {
            $body = jsonBody() ?: [];
            $email = isset($body['email']) ? $body['email'] : '';
            $password = isset($body['password']) ? $body['password'] : '';
            $r = Auth::login($email, $password);
            if (!$r['ok']) {
                fail($r['error'], 'Login failed', [], 400);
            }
            Response::success(['user_id' => $r['user_id'], 'email_verified' => $r['email_verified']]);
            return true;
        }

        if ($method === 'POST' && $path === '/auth/register') {
            $body = jsonBody() ?: [];
            $email = isset($body['email']) ? $body['email'] : '';
            $password = isset($body['password']) ? $body['password'] : '';
            $r = Auth::register($email, $password);
            if (!$r['ok']) {
                fail($r['error'], 'Registration failed', [], 400);
            }
            Response::success(['user_id' => $r['user_id'], 'email_verified' => $r['email_verified']]);
            return true;
        }

        if ($method === 'POST' && $path === '/auth/logout') {
            Auth::logout();
            Response::success(['logged_out' => true]);
            return true;
        }

        if ($method === 'GET' && $path === '/auth/verify-email') {
            $token = isset($_GET['token']) ? trim($_GET['token']) : '';
            $r = Auth::verifyEmail($token);
            if (!$r['ok']) {
                fail($r['error'], 'Verification failed', [], 400);
            }
            Response::success(['verified' => true]);
            return true;
        }

        if ($method === 'POST' && $path === '/auth/resend-verification') {
            $r = Auth::resendVerification();
            if (!$r['ok']) {
                $code = ($r['error'] === 'UNAUTHORIZED' || $r['error'] === 'NOT_FOUND') ? 'AUTH_REQUIRED' : $r['error'];
                fail($code, 'Resend failed', [], $r['error'] === 'UNAUTHORIZED' ? 401 : 400);
            }
            Response::success(['sent' => true]);
            return true;
        }

        return false;
    }
}
