<?php

class Response
{
    /** UUID v4 for request tracing only. Do not use for entity ids (draftId, imageId); use DraftRepository::generateUuid() for those. */
    public static function traceId()
    {
        // Generate pseudo-UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function success($data, $metaExtra = [])
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $response = [
            'ok' => true,
            'data' => $data,
            'meta' => array_merge([
                'traceId' => self::traceId(),
                'ts' => gmdate('c')
            ], $metaExtra)
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($code, $message, $details = [], $httpStatus = 400)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        $response = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'meta' => [
                    'traceId' => self::traceId()
                ]
            ]
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
