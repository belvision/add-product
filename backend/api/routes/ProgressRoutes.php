<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/ProgressReporter.php';
require_once __DIR__ . '/../../src/Config.php';

class ProgressRoutes
{
    /**
     * POST /api/progress/create — create a progress job, return job_id.
     */
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && preg_match('#^/progress/create$#', $path)) {
            requireAuth();
            $jobId = bin2hex(random_bytes(16));
            FileProgressReporter::createJob($jobId);
            Response::success(['job_id' => $jobId]);
            return true;
        }

        if ($method === 'GET' && preg_match('#^/progress/stream$#', $path)) {
            requireAuth();
            // IMPORTANT: release PHP session lock so parallel API requests don't block while SSE is open
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            ignore_user_abort(true);
            $jobId = isset($_GET['job_id']) ? trim((string) $_GET['job_id']) : '';
            if ($jobId === '') {
                fail('VALIDATION_ERROR', 'job_id is required', [], 400);
            }
            $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
            if ($jobId === '') {
                fail('VALIDATION_ERROR', 'Invalid job_id', [], 400);
            }

            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            if (ob_get_level()) {
                ob_end_clean();
            }

            $maxWait = (int) Config::get('PROGRESS_STREAM_TIMEOUT', '300'); // seconds
            @set_time_limit($maxWait + 60); // чтобы PHP не обрывал long-poll до истечения $maxWait
            $start = time();
            $lastEventCount = 0;
            $onceWait = true;

            while (time() - $start < $maxWait) {
                // Heartbeat every 10s to prevent proxy timeouts
                static $lastPing = 0;
                if ($lastPing === 0) { $lastPing = time(); }
                if (time() - $lastPing >= 10) {
                    echo ": ping\n\n";
                    flush();
                    $lastPing = time();
                }
                $events = FileProgressReporter::readEvents($jobId, false);
                if (count($events) === 0 && $onceWait) {
                    $onceWait = false;
                    echo "event: step\ndata: " . json_encode([
                        'ts' => gmdate('c'),
                        'level' => 'info',
                        'step' => 'pipeline_wait',
                        'message' => 'Waiting for pipeline to start...',
                        'meta' => [],
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }
                for ($i = $lastEventCount; $i < count($events); $i++) {
                    $ev = $events[$i];
                    echo "event: step\ndata: " . json_encode($ev, JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                    if (isset($ev['step']) && in_array($ev['step'], ['pipeline_done', 'pipeline_error'], true)) {
                        return true;
                    }
                }
                $lastEventCount = count($events);
                usleep(400000); // 400ms
            }

            echo "event: step\ndata: " . json_encode([
                'ts' => gmdate('c'),
                'level' => 'error',
                'step' => 'pipeline_timeout',
                'message' => 'Stream timeout',
                'meta' => [],
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            return true;
        }

        return false;
    }
}
