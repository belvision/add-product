<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';

class Auth
{
    public static function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function userId()
    {
        self::initSession();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user()
    {
        $id = self::userId();
        if ($id === null) {
            return null;
        }
        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT id, email, email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function isEmailVerified()
    {
        $u = self::user();
        return $u && $u['email_verified_at'] !== null;
    }

    /**
     * Current user for API (e.g. GET /api/me). Single source of truth.
     * @return array|null ['id' => int, 'email' => string, 'email_verified' => bool] or null
     */
    public static function currentUser(): ?array
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        return [
            'id' => (int) $u['id'],
            'email' => (string) $u['email'],
            'email_verified' => $u['email_verified_at'] !== null,
        ];
    }

    public static function register($email, $password)
    {
        self::initSession();
        Config::loadEnv();
        $pdo = Db::get();

        $email = trim(strtolower($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'INVALID_EMAIL'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'WEAK_PASSWORD'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
            $stmt->execute([$email, $hash]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique') !== false) {
                return ['ok' => false, 'error' => 'EMAIL_EXISTS'];
            }
            throw $e;
        }

        $userId = (int) $pdo->lastInsertId();

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $tokenHash, $expires]);

        // Важно: отправка письма не должна ломать регистрацию.
        self::sendVerificationEmail($email, $token);

        $_SESSION['user_id'] = $userId;

        return ['ok' => true, 'user_id' => $userId, 'email_verified' => false];
    }

    public static function login($email, $password)
    {
        self::initSession();
        $pdo = Db::get();

        $email = trim(strtolower($email));
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'INVALID_CREDENTIALS'];
        }

        $_SESSION['user_id'] = (int) $row['id'];

        $stmt = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$row['id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['ok' => true, 'user_id' => (int) $row['id'], 'email_verified' => $u['email_verified_at'] !== null];
    }

    public static function logout()
    {
        self::initSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        return ['ok' => true];
    }

    public static function verifyEmail($token)
    {
        if ($token === '' || strlen($token) !== 64) {
            return ['ok' => false, 'error' => 'INVALID_TOKEN'];
        }
        $tokenHash = hash('sha256', $token);
        $pdo = Db::get();

        $stmt = $pdo->prepare('SELECT user_id, expires_at FROM email_verification_tokens WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'INVALID_OR_EXPIRED'];
        }

        $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$row['user_id']]);
        $pdo->prepare('DELETE FROM email_verification_tokens WHERE token_hash = ?')->execute([$tokenHash]);

        self::initSession();
        $_SESSION['user_id'] = (int) $row['user_id'];

        return ['ok' => true];
    }

    public static function resendVerification()
    {
        $userId = self::userId();
        if ($userId === null) {
            return ['ok' => false, 'error' => 'UNAUTHORIZED'];
        }

        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT email, email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'error' => 'NOT_FOUND'];
        }
        if ($row['email_verified_at'] !== null) {
            return ['ok' => true, 'already_verified' => true];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400);

        $pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?')->execute([$userId]);
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $tokenHash, $expires]);

        $sent = self::sendVerificationEmail($row['email'], $token);
        if (!$sent) {
            return ['ok' => false, 'error' => 'EMAIL_SEND_FAILED'];
        }

        return ['ok' => true, 'sent' => true];
    }

    private static function sendVerificationEmail($to, $token): bool
    {
        $base = self::baseUrl();
        $link = $base . '/verify-email?token=' . urlencode($token);

        $subject = 'Verify your email';
        $body = "Click to verify: " . $link;

        $fromHeader = Config::get('SMTP_FROM', 'noreply@localhost');
        $smtpHost = Config::get('SMTP_HOST');

        // For SMTP envelope (MAIL FROM) нужен чистый email
        $fromEmail = self::extractEmail($fromHeader);
        if ($fromEmail === null) {
            $fromEmail = $fromHeader;
        }

        if ($smtpHost) {
            return self::smtpSend($to, $subject, $body, $fromHeader, $fromEmail, $smtpHost);
        }

        // Важно: на сервере без MTA mail() часто не работает. Это ветка "на всякий случай".
        $headers = "From: $fromHeader\r\nContent-Type: text/plain; charset=UTF-8";
        return @mail($to, $subject, $body, $headers) ? true : false;
    }

    private static function baseUrl()
    {
        $base = Config::get('APP_BASE_URL');
        if ($base !== null && $base !== '') {
            return rtrim($base, '/');
        }

        $req = $_SERVER['REQUEST_URI'] ?? '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = '';

        if ($script !== '' && strpos($req, $script) === 0) {
            $base = rtrim(dirname($script), '/');
            if (substr($base, -7) === '/public') {
                $base = substr($base, 0, -7);
            }
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $proto . '://' . $host . ($base ?: '');
    }

    private static function extractEmail($from)
    {
        // "Name <email@domain>"
        if (preg_match('/<([^>]+)>/', (string) $from, $m)) {
            return trim($m[1]);
        }
        // "email@domain"
        if (filter_var((string) $from, FILTER_VALIDATE_EMAIL)) {
            return (string) $from;
        }
        return null;
    }

    /**
     * SMTP send with basic error handling.
     * Supports:
     * - 587 STARTTLS
     * - 465 SMTPS (ssl://)
     *
     * Returns true/false. Does not throw.
     */
    private static function smtpSend($to, $subject, $body, $fromHeader, $fromEmail, $host): bool
    {
        $port = (int) Config::get('SMTP_PORT', 587);
        $user = (string) Config::get('SMTP_USER', '');
        $pass = (string) Config::get('SMTP_PASS', '');

        $errno = 0;
        $errstr = '';

        $transport = 'tcp';
        if ($port === 465) {
            $transport = 'ssl';
        }

        $sock = @stream_socket_client("$transport://$host:$port", $errno, $errstr, 15);
        if (!$sock) {
            error_log("[SMTP] connect failed: $errno $errstr");
            return false;
        }

        stream_set_timeout($sock, 10);

        $readResponse = function () use ($sock) {
            $lines = [];
            while (!feof($sock)) {
                $line = fgets($sock);
                if ($line === false) {
                    break;
                }
                $line = rtrim($line, "\r\n");
                $lines[] = $line;

                // Multi-line SMTP: "250-" continues, "250 " ends
                if (preg_match('/^\d{3}\s/', $line)) {
                    break;
                }
            }
            return $lines;
        };

        $write = function ($line) use ($sock) {
            fwrite($sock, $line . "\r\n");
        };

        $expectCode = function ($lines, $wantCode) {
            if (!$lines || !isset($lines[0])) {
                return false;
            }
            return strpos($lines[0], (string) $wantCode) === 0;
        };

        // Banner
        $banner = $readResponse();
        if (!$expectCode($banner, 220)) {
            error_log('[SMTP] bad banner: ' . implode(' | ', $banner));
            fclose($sock);
            return false;
        }

        // EHLO
        $write('EHLO localhost');
        $ehlo = $readResponse();
        if (!$expectCode($ehlo, 250)) {
            error_log('[SMTP] EHLO failed: ' . implode(' | ', $ehlo));
            fclose($sock);
            return false;
        }

        // STARTTLS for 587
        if ($port === 587) {
            $write('STARTTLS');
            $resp = $readResponse();
            if (!$expectCode($resp, 220)) {
                error_log('[SMTP] STARTTLS failed: ' . implode(' | ', $resp));
                fclose($sock);
                return false;
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('[SMTP] TLS enable failed');
                fclose($sock);
                return false;
            }
            $write('EHLO localhost');
            $ehlo2 = $readResponse();
            if (!$expectCode($ehlo2, 250)) {
                error_log('[SMTP] EHLO after STARTTLS failed: ' . implode(' | ', $ehlo2));
                fclose($sock);
                return false;
            }
        }

        // AUTH LOGIN
        if ($user !== '') {
            $write('AUTH LOGIN');
            $r1 = $readResponse();
            if (!$expectCode($r1, 334)) {
                error_log('[SMTP] AUTH LOGIN rejected: ' . implode(' | ', $r1));
                fclose($sock);
                return false;
            }

            $write(base64_encode($user));
            $r2 = $readResponse();
            if (!$expectCode($r2, 334)) {
                error_log('[SMTP] AUTH user rejected: ' . implode(' | ', $r2));
                fclose($sock);
                return false;
            }

            $write(base64_encode($pass));
            $r3 = $readResponse();
            if (!$expectCode($r3, 235)) {
                error_log('[SMTP] AUTH pass rejected: ' . implode(' | ', $r3));
                fclose($sock);
                return false;
            }
        }

        // MAIL FROM / RCPT TO / DATA
        $write("MAIL FROM:<$fromEmail>");
        $rFrom = $readResponse();
        if (!$expectCode($rFrom, 250)) {
            error_log('[SMTP] MAIL FROM failed: ' . implode(' | ', $rFrom));
            fclose($sock);
            return false;
        }

        $write("RCPT TO:<$to>");
        $rTo = $readResponse();
        if (!$expectCode($rTo, 250) && !$expectCode($rTo, 251)) {
            error_log('[SMTP] RCPT TO failed: ' . implode(' | ', $rTo));
            fclose($sock);
            return false;
        }

        $write('DATA');
        $rData = $readResponse();
        if (!$expectCode($rData, 354)) {
            error_log('[SMTP] DATA failed: ' . implode(' | ', $rData));
            fclose($sock);
            return false;
        }

        // Headers + body
        $write("Subject: $subject");
        $write("From: $fromHeader");
        $write("To: $to");
        $write("Content-Type: text/plain; charset=UTF-8");
        $write('');
        $write($body);
        $write('.');
        $rEnd = $readResponse();
        if (!$expectCode($rEnd, 250)) {
            error_log('[SMTP] end DATA failed: ' . implode(' | ', $rEnd));
            fclose($sock);
            return false;
        }

        $write('QUIT');
        @fclose($sock);
        return true;
    }
}
