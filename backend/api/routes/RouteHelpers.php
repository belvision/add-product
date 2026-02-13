<?php

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Response.php';

function fail($code, $message, $details = [], $httpStatus = 400)
{
    Response::error($code, $message, $details, $httpStatus);
    exit;
}

function requireAuth()
{
    $userId = Auth::userId();
    if ($userId === null) {
        fail('AUTH_REQUIRED', 'Authentication required', [], 401);
    }
    return $userId;
}

function requireVerified()
{
    $userId = requireAuth();
    if (!Auth::isEmailVerified()) {
        fail('EMAIL_NOT_VERIFIED', 'Email verification required', [], 403);
    }
    return $userId;
}

function jsonBody()
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }
    return $decoded;
}
