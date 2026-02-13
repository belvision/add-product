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
        self::sendVerificationEmail($row['email'], $token);
        return ['ok' => true];
    }

    private static function sendVerificationEmail($to, $token)
    {
        $base = self::baseUrl();
        $link = $base . '/verify-email?token=' . urlencode($token);
        $subject = 'Verify your email';
        $body = "Click to verify: " . $link;
        $from = Config::get('SMTP_FROM', 'noreply@localhost');
        $host = Config::get('SMTP_HOST');
        if ($host) {
            self::smtpSend($to, $subject, $body, $from, $host);
        } else {
            $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8";
            mail($to, $subject, $body, $headers);
        }
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

    private static function smtpSend($to, $subject, $body, $from, $host)
    {
        $port = (int) Config::get('SMTP_PORT', 587);
        $user = Config::get('SMTP_USER', '');
        $pass = Config::get('SMTP_PASS', '');
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 10);
        if (!$sock) {
            return;
        }
        stream_set_timeout($sock, 5);
        $read = function () use ($sock) {
            $line = fgets($sock);
            return $line !== false ? trim($line) : '';
        };
        $write = function ($line) use ($sock) {
            fwrite($sock, $line . "\r\n");
        };
        $read();
        $write("EHLO localhost");
        while ($line = $read()) {
            if (preg_match('/^\d{3}\s/', $line) && substr($line, 3, 1) === ' ') break;
        }
        if ($port === 587) {
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO localhost");
            while ($line = $read()) {
                if (preg_match('/^\d{3}\s/', $line) && substr($line, 3, 1) === ' ') break;
            }
        }
        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $read();
        }
        $write("MAIL FROM:<$from>");
        $read();
        $write("RCPT TO:<$to>");
        $read();
        $write('DATA');
        $read();
        $write("Subject: $subject");
        $write("From: $from");
        $write("To: $to");
        $write("Content-Type: text/plain; charset=UTF-8");
        $write('');
        $write($body);
        $write('.');
        $read();
        $write('QUIT');
        fclose($sock);
    }
}
