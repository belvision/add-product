<?php
$user = Auth::user();
if (!$user) {
    header('Location: ' . $base . '/login');
    exit;
}
$draftId = isset($_GET['draftId']) ? trim($_GET['draftId']) : '';
if ($draftId === '') {
    header('Location: ' . $base . '/add');
    exit;
}
$pageTitle = $lang === 'ru' ? 'Редактирование черновика' : 'Edit draft';
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
    <div class="wizard-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="lang-switcher">
            <a href="?lang=ru&draftId=<?php echo htmlspecialchars($draftId); ?>" class="<?php echo $lang === 'ru' ? 'active' : ''; ?>">RU</a>
            <a href="?lang=en&draftId=<?php echo htmlspecialchars($draftId); ?>" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
        </div>
    </div>
    <div id="ozonWizard"></div>
    <script>
        window.__OZON_LANG__ = '<?php echo htmlspecialchars($lang); ?>';
        window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
        window.__OZON_DRAFT_ID__ = '<?php echo htmlspecialchars($draftId); ?>';
        window.__OZON_EMAIL_VERIFIED__ = <?php echo Auth::isEmailVerified() ? 'true' : 'false'; ?>;
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/assets/ozon-wizard.js" defer></script>
</body>
</html>
