<?php
$user = Auth::user();
if (!$user) {
    header('Location: ' . $base . '/login');
    exit;
}
$pageTitle = $lang === 'ru' ? 'Добавить товар' : 'Add product';
$marketplace = isset($_GET['marketplace']) ? trim((string) $_GET['marketplace']) : 'ozon';
if (!in_array($marketplace, ['ozon', 'wb', 'emall'], true)) {
    $marketplace = 'ozon';
}
$addQuery = function ($m, $l = null) use ($lang) {
    $q = ['marketplace' => $m];
    if ($l !== null) $q['lang'] = $l; else $q['lang'] = $lang;
    return '?' . http_build_query($q);
};
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <?php if ($marketplace === 'emall'): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/public/assets/emall/emall-wizard.css">
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.css">
    <?php endif; ?>
</head>
<body>
    <div class="wizard-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="header-controls">
            <div class="marketplace-tabs" role="tablist">
                <a href="<?php echo htmlspecialchars($base . '/add' . $addQuery('ozon')); ?>" class="marketplace-tab <?php echo $marketplace === 'ozon' ? 'active' : ''; ?>" role="tab">Ozon</a>
                <a href="<?php echo htmlspecialchars($base . '/add' . $addQuery('wb')); ?>" class="marketplace-tab <?php echo $marketplace === 'wb' ? 'active' : ''; ?>" role="tab">Wildberries</a>
                <a href="<?php echo htmlspecialchars($base . '/add' . $addQuery('emall')); ?>" class="marketplace-tab <?php echo $marketplace === 'emall' ? 'active' : ''; ?>" role="tab">eMall</a>
            </div>
            <div class="lang-switcher">
                <a href="<?php echo htmlspecialchars($base . '/add' . $addQuery($marketplace, 'ru')); ?>" class="<?php echo $lang === 'ru' ? 'active' : ''; ?>">RU</a>
                <a href="<?php echo htmlspecialchars($base . '/add' . $addQuery($marketplace, 'en')); ?>" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="<?php echo htmlspecialchars($base); ?>/cabinet" class="header-link"><?php echo $lang === 'ru' ? 'Кабинет' : 'Cabinet'; ?></a>
            <a href="<?php echo htmlspecialchars($base); ?>/auth/logout" id="logoutLink" class="header-link"><?php echo $lang === 'ru' ? 'Выйти' : 'Log out'; ?></a>
        </div>
    </div>
    <?php if ($marketplace === 'emall'): ?>
    <div id="emallWizard"></div>
    <?php else: ?>
    <div id="ozonWizard"></div>
    <?php endif; ?>
    <script>
        window.__OZON_LANG__ = '<?php echo htmlspecialchars($lang); ?>';
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
        window.__APP_BASE__ = '<?php echo htmlspecialchars($base); ?>';
        window.__MARKETPLACE__ = '<?php echo htmlspecialchars($marketplace); ?>';
        window.__LANG__ = '<?php echo htmlspecialchars($lang); ?>';
        window.__OZON_DRAFT_ID__ = null;
        window.__OZON_EMAIL_VERIFIED__ = <?php echo Auth::isEmailVerified() ? 'true' : 'false'; ?>;
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
    <?php if ($marketplace === 'emall'): ?>
    <script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/emall-wizard.js" defer></script>
    <?php else: ?>
    <script src="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.js" defer></script>
    <?php endif; ?>
</body>
</html>
