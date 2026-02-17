<?php
$pageTitle = $lang === 'ru' ? 'Вход' : 'Login';
$emailLabel = $lang === 'ru' ? 'Email' : 'Email';
$passwordLabel = $lang === 'ru' ? 'Пароль' : 'Password';
$submitLabel = $lang === 'ru' ? 'Войти' : 'Log in';
$registerLink = $lang === 'ru' ? 'Регистрация' : 'Register';
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
            <form method="post" action="<?php echo htmlspecialchars($base); ?>/auth/login" id="loginForm">
                <input type="hidden" name="ajax" value="1">
                <div class="app-form-row">
                    <label><?php echo htmlspecialchars($emailLabel); ?></label>
                    <input type="email" name="email" required>
                </div>
                <div class="app-form-row">
                    <label><?php echo htmlspecialchars($passwordLabel); ?></label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($submitLabel); ?></button>
            </form>
            <p class="app-text-muted" style="margin-top: 12px;">
                <a href="<?php echo htmlspecialchars($base); ?>/register"><?php echo htmlspecialchars($registerLink); ?></a>
            </p>
            <p id="msg" class="app-text-muted"></p>
        </div>
    </div>
    <script>
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script>
(function() {
    var form = document.getElementById('loginForm');
    var msgEl = document.getElementById('msg');
    form.onsubmit = function(e) {
        e.preventDefault();
        msgEl.textContent = '';
        var fd = new FormData(form);
        window.apiFetchJson('<?php echo htmlspecialchars($base); ?>/auth/login', {
            method: 'POST',
            body: { email: fd.get('email'), password: fd.get('password') },
            credentials: 'same-origin'
        }).then(function(data) {
            if (data.ok) {
                window.location.href = '<?php echo htmlspecialchars($base); ?>/cabinet';
            } else {
                msgEl.textContent = data.error && data.error.message ? data.error.message : 'Error';
            }
        }).catch(function(err) {
            msgEl.textContent = err && err.message ? err.message : '<?php echo $lang === 'ru' ? 'Ошибка сети или сервера' : 'Network or server error'; ?>';
        });
    };
})();
    </script>
</body>
</html>
