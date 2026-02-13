<?php
$pageTitle = $lang === 'ru' ? 'Регистрация' : 'Register';
$emailLabel = $lang === 'ru' ? 'Email' : 'Email';
$passwordLabel = $lang === 'ru' ? 'Пароль' : 'Password';
$submitLabel = $lang === 'ru' ? 'Зарегистрироваться' : 'Register';
$loginLink = $lang === 'ru' ? 'Вход' : 'Log in';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
</head>
<body>
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars($base); ?>/auth/register" id="registerForm">
        <div>
            <label><?php echo htmlspecialchars($emailLabel); ?></label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label><?php echo htmlspecialchars($passwordLabel); ?></label>
            <input type="password" name="password" required minlength="8">
        </div>
        <button type="submit"><?php echo htmlspecialchars($submitLabel); ?></button>
    </form>
    <p><a href="<?php echo htmlspecialchars($base); ?>/login"><?php echo htmlspecialchars($loginLink); ?></a></p>
    <p id="msg"></p>
    <script>
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script>
(function() {
    var form = document.getElementById('registerForm');
    var msgEl = document.getElementById('msg');
    form.onsubmit = function(e) {
        e.preventDefault();
        msgEl.textContent = '';
        var fd = new FormData(form);
        window.apiFetchJson('<?php echo htmlspecialchars($base); ?>/auth/register', {
            method: 'POST',
            body: { email: fd.get('email'), password: fd.get('password') },
            credentials: 'same-origin'
        }).then(function(data) {
            if (data.ok) {
                window.location.href = '<?php echo htmlspecialchars($base); ?>/cabinet';
            } else {
                msgEl.textContent = data.error && (data.error.message || data.error.code) ? (data.error.message || data.error.code) : 'Error';
            }
        }).catch(function(err) {
            msgEl.textContent = err && err.message ? err.message : '<?php echo $lang === 'ru' ? 'Ошибка сети или сервера' : 'Network or server error'; ?>';
        });
    };
})();
    </script>
</body>
</html>
