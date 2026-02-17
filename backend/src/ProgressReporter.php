<?php

/**
 * Progress reporter for pipeline steps. Emits events to be consumed by UI (SSE or file).
 * Do not log secrets (API keys, proxy passwords, full text — only short previews up to 120 chars).
 */
interface ProgressReporterInterface
{
    /**
     * @param string $step   Step identifier (e.g. pipeline_start, openai_embeddings, qdrant_search).
     * @param string $level  info | success | error
     * @param string $message Human-readable message.
     * @param array<string, mixed> $meta Optional meta (ms, model, dim, http, type — no secrets).
     */
    public function emit(string $step, string $level, string $message, array $meta = []): void;
}

/**
 * Writes progress events to a JSONL file under backend/runtime/progress/<job_id>.jsonl.
 * Keeps at most MAX_EVENTS events; cleans up file when TTL exceeded (on read).
 */
class FileProgressReporter implements ProgressReporterInterface
{
    private const MAX_EVENTS = 200;
    private const TTL_SECONDS = 1800; // 30 minutes

    private string $jobId;
    private string $filePath;
    private int $eventCount = 0;

    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
        $base = dirname(__DIR__, 1);
        $dir = $base . '/runtime/progress';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $this->filePath = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId) . '.jsonl';
    }

    public function emit(string $step, string $level, string $message, array $meta = []): void
    {
        $event = [
            'ts' => gmdate('c'),
            'level' => $level,
            'step' => $step,
            'message' => $message,
            'meta' => $meta,
        ];
        $line = json_encode($event, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($this->filePath, $line, LOCK_EX | FILE_APPEND);
        $this->eventCount++;
        if ($this->eventCount > self::MAX_EVENTS) {
            $this->trimFile();
        }
    }

    private function trimFile(): void
    {
        if (!is_file($this->filePath)) {
            return;
        }
        $lines = @file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) <= self::MAX_EVENTS) {
            return;
        }
        $keep = array_slice($lines, -self::MAX_EVENTS);
        @file_put_contents($this->filePath, implode("\n", $keep) . "\n", LOCK_EX);
        $this->eventCount = count($keep);
    }

    /**
     * Path to the progress file for this job (for SSE reader).
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Create progress file with "started" event and return job_id-safe filename.
     * Called from ProgressRoutes when creating a job.
     */
    public static function createJob(string $jobId): string
    {
        $reporter = new self($jobId);
        $reporter->emit('pipeline_start', 'info', 'Pipeline started', []);
        return $reporter->getFilePath();
    }

    /**
     * Read events from a job file (for SSE stream). Optionally delete file if TTL exceeded.
     *
     * @return array<int, array{ts:string, level:string, step:string, message:string, meta:array}>
     */
    public static function readEvents(string $jobId, bool $deleteIfExpired = true): array
    {
        $base = dirname(__DIR__, 1);
        $path = $base . '/runtime/progress/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId) . '.jsonl';
        if (!is_file($path)) {
            return [];
        }
        if ($deleteIfExpired && (time() - filemtime($path)) > self::TTL_SECONDS) {
            @unlink($path);
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        $events = [];
        foreach ($lines as $line) {
            $dec = json_decode($line, true);
            if (is_array($dec)) {
                $events[] = $dec;
            }
        }
        return $events;
    }
}
