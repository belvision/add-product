<?php
$pageTitle = $lang === 'ru' ? 'Подтверждение email' : 'Verify email';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.css">
</head>
<body>
    <div class="app-page">
        <div class="app-card">
            <h1 class="app-card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p id="msg" class="app-text-muted"><?php echo $lang === 'ru' ? 'Загрузка...' : 'Loading...'; ?></p>
        </div>
    </div>
    <script>
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script>
(function() {
    var msgEl = document.getElementById('msg');
    var token = new URLSearchParams(window.location.search).get('token') || '';
    if (!token) {
        msgEl.textContent = '<?php echo $lang === 'ru' ? 'Нет токена' : 'No token'; ?>';
        return;
    }
    // Use helper to route via encoded `r` to avoid nginx 406 on raw slashes.
    var verifyUrl = window.buildApiUrlR('/auth/verify-email');
    window.apiFetchJson(verifyUrl + '&token=' + encodeURIComponent(token), {

        method: 'GET',
        credentials: 'same-origin'
    }).then(function(data) {
        if (data.ok) {
            msgEl.textContent = '<?php echo $lang === 'ru' ? 'Email подтверждён.' : 'Email verified.'; ?>';
            setTimeout(function() { window.location.href = '<?php echo htmlspecialchars($base); ?>/cabinet'; }, 1500);
            return;
        }

        // Если токен уже использован/истёк, но пользователь уже подтверждён — покажем "уже подтверждено"
        if (data && data.error && data.error.code === 'INVALID_OR_EXPIRED') {
            window.apiFetchJson(window.buildApiUrlR('/me'), { method: 'GET', credentials: 'same-origin' })
                .then(function(me) {
                    if (me && me.ok && me.data && me.data.email_verified) {
                        msgEl.textContent = '<?php echo $lang === 'ru' ? 'Email уже подтверждён.' : 'Email is already verified.'; ?>';
                        setTimeout(function() { window.location.href = '<?php echo htmlspecialchars($base); ?>/cabinet'; }, 1200);
                    } else {
                        msgEl.textContent = '<?php echo $lang === 'ru' ? 'Ссылка недействительна или истекла.' : 'Link is invalid or expired.'; ?>';
                    }
                })
                .catch(function() {
                    msgEl.textContent = '<?php echo $lang === 'ru' ? 'Ссылка недействительна или истекла.' : 'Link is invalid or expired.'; ?>';
                });
            return;
        }

        msgEl.textContent = data.error && (data.error.message || data.error.code) ? (data.error.message || data.error.code) : 'Error';
    }).catch(function(err) {
        msgEl.textContent = err && err.message ? err.message : '<?php echo $lang === 'ru' ? 'Ошибка сети или сервера' : 'Network or server error'; ?>';
    });
})();
    </script>
</body>
</html>
