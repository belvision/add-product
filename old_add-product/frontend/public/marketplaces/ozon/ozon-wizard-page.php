<?php
/**
 * Ozon wizard: full standalone page (HTML + scripts).
 * Canonical source for the Ozon wizard UI. Included by frontend/public/ozon-wizard.php.
 */
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang === 'ru' ? 'Мастер создания товара Ozon' : 'Ozon Product Creation Wizard'; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.css">
</head>
<body>
    <div class="wizard-header">
        <h1><?php echo $lang === 'ru' ? 'Мастер создания товара Ozon' : 'Ozon Product Creation Wizard'; ?></h1>
        <div class="header-controls">
            <div class="lang-switcher">
                <a href="?lang=ru" class="<?php echo $lang === 'ru' ? 'active' : ''; ?>">RU</a>
                <a href="?lang=en" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="<?php echo htmlspecialchars($base); ?>/cabinet" class="header-link"><?php echo $lang === 'ru' ? 'Кабинет' : 'Cabinet'; ?></a>
            <a href="<?php echo htmlspecialchars($base); ?>/auth/logout" id="logoutLink" class="header-link"><?php echo $lang === 'ru' ? 'Выйти' : 'Log out'; ?></a>
        </div>
    </div>
    <div id="ozonWizard"></div>
    <script>
        window.__OZON_LANG__ = '<?php echo htmlspecialchars($lang); ?>';
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script>
    (function() {
        var logoutLink = document.getElementById('logoutLink');
        if (!logoutLink) return;
        var base = (typeof window.__OZON_BASE__ !== 'undefined' && window.__OZON_BASE__) ? window.__OZON_BASE__ : '';
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            var url = (typeof window.buildApiUrlR === 'function') ? window.buildApiUrlR('/auth/logout') : (base + '/api/auth/logout');
            if (typeof window.apiFetchJson === 'function') {
                window.apiFetchJson(url, { method: 'POST', credentials: 'same-origin' })
                    .then(function() { window.location.href = base + '/login'; })
                    .catch(function() { window.location.href = base + '/login'; });
            } else {
                window.location.href = base + '/login';
            }
        });
    })();
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.js" defer></script>
</body>
</html>
