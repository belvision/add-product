<?php
require_once __DIR__ . '/../../backend/src/OzonCredentialsRepository.php';
$emallCredentialsAvailable = is_file(__DIR__ . '/../../backend/src/EmallCredentialsRepository.php');
if ($emallCredentialsAvailable) {
    require_once __DIR__ . '/../../backend/src/EmallCredentialsRepository.php';
}

$user = Auth::user();
if (!$user) {
    header('Location: ' . $base . '/login');
    exit;
}
$pageTitle = $lang === 'ru' ? 'Кабинет' : 'Cabinet';
$addProduct = $lang === 'ru' ? 'Добавить товар' : 'Add product';
$logout = $lang === 'ru' ? 'Выйти' : 'Log out';
$verified = (bool) $user['email_verified_at'];
$verifyMsg = $lang === 'ru' ? 'Подтвердите email' : 'Verify your email';
$resend = $lang === 'ru' ? 'Отправить снова' : 'Resend';
$ozonSection = $lang === 'ru' ? 'Ключи Ozon' : 'Ozon credentials';
$clientIdLabel = $lang === 'ru' ? 'Client-Id' : 'Client-Id';
$apiKeyLabel = $lang === 'ru' ? 'Api-Key' : 'Api-Key';
$saveBtn = $lang === 'ru' ? 'Сохранить' : 'Save';
$savedHint = $lang === 'ru' ? 'Сохранено' : 'Saved';
$marketplaceTitle = $lang === 'ru'
    ? 'Выберите маркетплейс, куда будем добавлять товары'
    : 'Choose a marketplace to add products';
$comingSoon = $lang === 'ru' ? 'Скоро' : 'Coming soon';
$ozonStatusId = 'ozonCredentialsStatus';
$emallSection = $lang === 'ru' ? 'Ключ eMall' : 'eMall key';
$emallApiKeyFieldLabel = $lang === 'ru' ? 'API ключ' : 'API key';
$emallStatusId = 'emallCredentialsStatus';
$apiBase = htmlspecialchars($base);

/** Показать несколько символов с начала и конца (остальное скрыто) */
$maskStartEnd = function ($s, $visible = 3) {
    if ($s === null || $s === '') return '';
    $len = strlen($s);
    if ($len <= $visible * 2) return str_repeat('*', min(4, $len));
    return substr($s, 0, $visible) . '…' . substr($s, -$visible);
};

$ozonClientIdValue = '';
$ozonApiKeyMasked = '';
$ozonClientIdMasked = '';
$ozonStatusText = '';
$emallApiKeyMasked = '';
$emallStatusText = '';
$ozonCredsSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = isset($_POST['client_id']) ? trim((string) $_POST['client_id']) : '';
    $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';

    if ($clientId === '' || $apiKey === '') {
        $ozonStatusText = $lang === 'ru'
            ? 'Заполните Client-Id и Api-Key'
            : 'Enter Client-Id and Api-Key';
        $ozonClientIdValue = $clientId;
    } else {
        OzonCredentialsRepository::saveForUser((int) $user['id'], $clientId, $apiKey);

        $params = ['ok' => 1];
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'], true)) {
            $params['lang'] = $_GET['lang'];
        }
        $redirectUrl = $base . '/cabinet';
        if (!empty($params)) {
            $redirectUrl .= '?' . http_build_query($params);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// eMall API key (separate form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emall_save'])) {
    $emallKey = isset($_POST['emall_api_key']) ? trim((string) $_POST['emall_api_key']) : '';
    if ($emallKey === '') {
        $emallStatusText = $lang === 'ru'
            ? 'Введите API ключ eMall'
            : 'Enter eMall API key';
    } else {
        try {
            if (!$emallCredentialsAvailable) {
                throw new RuntimeException('EmallCredentialsRepository not loaded');
            }
            EmallCredentialsRepository::saveForUser((int) $user['id'], $emallKey);
            $params = ['ok' => 1, 'emall' => 1];
            if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'], true)) {
                $params['lang'] = $_GET['lang'];
            }
            header('Location: ' . $base . '/cabinet?' . http_build_query($params));
            exit;
        } catch (Throwable $e) {
            $emallStatusText = $lang === 'ru' ? 'Не удалось сохранить. Проверьте, что выполнена миграция для eMall.' : 'Save failed. Ensure eMall migration is applied.';
        }
    }
}

// Load existing credentials for initial render (and after validation errors).
$creds = OzonCredentialsRepository::getForUser((int) $user['id']);
if ($creds) {
    $ozonCredsSaved = ($creds['client_id'] !== '' || $creds['api_key'] !== '');
    if ($ozonClientIdValue === '') {
        $ozonClientIdValue = (string) $creds['client_id'];
    }
    if ($creds['client_id'] !== '') {
        $ozonClientIdMasked = $maskStartEnd($creds['client_id']);
    }
    if ($creds['api_key'] !== '') {
        $ozonApiKeyMasked = $maskStartEnd($creds['api_key']);
    }
}

if (isset($_GET['ok']) && $_GET['ok'] === '1') {
    $ozonStatusText = $savedHint;
}
if (isset($_GET['emall']) && $_GET['emall'] === '1') {
    $emallStatusText = $savedHint;
}

$emallCreds = null;
if ($emallCredentialsAvailable) {
    try {
        $emallCreds = EmallCredentialsRepository::getForUser((int) $user['id']);
    } catch (Throwable $e) {
        // Таблица user_emall_credentials может отсутствовать, если миграция не применена — кабинет всё равно открывается
    }
}
if ($emallCreds && $emallCreds['api_key'] !== '') {
    $emallApiKeyMasked = $maskStartEnd($emallCreds['api_key']);
}

$ozonKeyHintText = '';
if ($ozonClientIdMasked !== '' || $ozonApiKeyMasked !== '') {
    $parts = [];
    if ($ozonClientIdMasked !== '') $parts[] = 'Client-Id: ' . $ozonClientIdMasked;
    if ($ozonApiKeyMasked !== '') $parts[] = 'Api-Key: ' . $ozonApiKeyMasked;
    $ozonKeyHintText = implode(', ', $parts);
}
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
            <div class="app-top-bar">
                <div>
                    <h1 class="app-card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p id="userEmail" class="app-text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <a href="<?php echo $apiBase; ?>/auth/logout" id="logoutLink" class="app-link-muted">
                    <?php echo htmlspecialchars($logout); ?>
                </a>
            </div>

            <?php if (!$verified): ?>
            <div class="app-form-row">
                <p id="verifyMsg" class="app-text-muted">
                    <?php echo htmlspecialchars($verifyMsg); ?>
                    <button type="button" id="resendBtn" class="btn btn-secondary">
                        <?php echo htmlspecialchars($resend); ?>
                    </button>
                </p>
                <p id="resendStatus" class="app-text-muted"></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="app-card">
            <p class="app-section-title"><?php echo htmlspecialchars($marketplaceTitle); ?></p>
            <div class="app-service-grid">
                <div class="app-service-card">
                    <div>
                        <div class="app-service-name">Ozon</div>
                        <div class="app-service-status app-text-muted">
                            <?php echo $lang === 'ru' ? 'Доступно сейчас' : 'Available now'; ?>
                            <?php if ($ozonCredsSaved): ?>
                                — <?php echo $lang === 'ru' ? 'ключи сохранены' : 'credentials saved'; ?>
                                <?php
                                $ozonCardParts = [];
                                if ($ozonClientIdMasked !== '') $ozonCardParts[] = $ozonClientIdMasked;
                                if ($ozonApiKeyMasked !== '') $ozonCardParts[] = $ozonApiKeyMasked;
                                if (count($ozonCardParts) > 0): ?>
                                    (<?php echo htmlspecialchars(implode(', ', $ozonCardParts)); ?>)
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="<?php echo htmlspecialchars($base . '/add?marketplace=ozon' . (isset($_GET['lang']) ? '&lang=' . $_GET['lang'] : '')); ?>" class="btn btn-primary" id="chooseOzonBtn">
                            <?php echo $lang === 'ru' ? 'Добавлять на Ozon' : 'Use Ozon'; ?>
                        </a>
                        <button type="button" class="btn btn-secondary" id="ozonKeyBtn">
                            <?php echo $lang === 'ru' ? 'Ключи Ozon' : 'Ozon keys'; ?>
                        </button>
                    </div>
                </div>

                <div class="app-service-card">
                    <div>
                        <div class="app-service-name">Wildberries</div>
                        <div class="app-service-status app-text-muted">
                            <?php echo htmlspecialchars($comingSoon); ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="chooseWbBtn" disabled>
                        <?php echo $lang === 'ru' ? 'Скоро' : 'Soon'; ?>
                    </button>
                </div>

                <div class="app-service-card">
                    <div>
                        <div class="app-service-name">EMALL</div>
                        <div class="app-service-status app-text-muted">
                            <?php echo $lang === 'ru' ? 'Доступно сейчас' : 'Available now'; ?>
                            <?php if ($emallApiKeyMasked !== ''): ?>
                                — <?php echo $lang === 'ru' ? 'ключ сохранён' : 'key saved'; ?> (<?php echo htmlspecialchars($emallApiKeyMasked); ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="<?php echo htmlspecialchars($base . '/add?marketplace=emall' . (isset($_GET['lang']) ? '&lang=' . $_GET['lang'] : '')); ?>" class="btn btn-primary" id="chooseEmallBtn">
                            <?php echo $lang === 'ru' ? 'Добавлять на eMall' : 'Use eMall'; ?>
                        </a>
                        <?php if ($emallCredentialsAvailable): ?>
                        <button type="button" class="btn btn-secondary" id="emallKeyBtn">
                            <?php echo $lang === 'ru' ? 'API ключ' : 'API key'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-card hidden" id="ozonCredentialsSection">
            <h2 class="app-section-title"><?php echo htmlspecialchars($ozonSection); ?></h2>
            <p id="ozonKeyHint" class="app-text-muted"><?php echo htmlspecialchars($ozonKeyHintText); ?></p>
            <form method="POST" action="">
                <div class="app-form-row">
                    <label for="ozonClientId"><?php echo htmlspecialchars($clientIdLabel); ?></label>
                    <input
                        type="text"
                        id="ozonClientId"
                        name="client_id"
                        autocomplete="off"
                        value="<?php echo htmlspecialchars($ozonClientIdValue); ?>"
                    >
                </div>
                <div class="app-form-row">
                    <label for="ozonApiKey"><?php echo htmlspecialchars($apiKeyLabel); ?></label>
                    <input
                        type="password"
                        id="ozonApiKey"
                        name="api_key"
                        autocomplete="off"
                        placeholder="<?php echo $lang === 'ru' ? 'Введите для сохранения или смены' : 'Enter to save or change'; ?>"
                    >
                </div>
                <button type="submit" class="btn btn-primary">
                    <?php echo htmlspecialchars($saveBtn); ?>
                </button>
                <p id="<?php echo $ozonStatusId; ?>" class="app-text-muted">
                    <?php echo htmlspecialchars($ozonStatusText); ?>
                </p>
            </form>
            <div class="app-form-row" style="margin-top: 1rem;">
                <a href="<?php echo $apiBase; ?>/add" class="btn btn-secondary">
                    <?php echo htmlspecialchars($addProduct); ?>
                </a>
            </div>
        </div>

        <?php if ($emallCredentialsAvailable): ?>
        <div class="app-card hidden" id="emallCredentialsSection">
            <h2 class="app-section-title"><?php echo htmlspecialchars($emallSection); ?></h2>
            <p id="emallKeyHint" class="app-text-muted">
                <?php if ($emallApiKeyMasked !== ''): ?>
                    <?php echo $lang === 'ru' ? 'Сохранён (начало…конец): ' : 'Saved (start…end): '; ?><strong><?php echo htmlspecialchars($emallApiKeyMasked); ?></strong>
                <?php else: ?>
                    <?php echo $lang === 'ru' ? 'Ключ не сохранён. Введите ключ ниже и нажмите «Сохранить».' : 'Key not saved. Enter key below and click Save.'; ?>
                <?php endif; ?>
            </p>
            <form method="POST" action="">
                <input type="hidden" name="emall_save" value="1">
                <div class="app-form-row">
                    <label for="emallApiKey"><?php echo htmlspecialchars($emallApiKeyFieldLabel); ?></label>
                    <input
                        type="password"
                        id="emallApiKey"
                        name="emall_api_key"
                        autocomplete="off"
                        placeholder="<?php echo $lang === 'ru' ? 'Введите для сохранения или смены' : 'Enter to save or change'; ?>"
                    >
                </div>
                <button type="submit" class="btn btn-primary">
                    <?php echo htmlspecialchars($saveBtn); ?>
                </button>
                <p id="<?php echo $emallStatusId; ?>" class="app-text-muted">
                    <?php echo htmlspecialchars($emallStatusText); ?>
                </p>
            </form>
            <div class="app-form-row" style="margin-top: 1rem;">
                <a href="<?php echo $apiBase; ?>/add?marketplace=emall" class="btn btn-secondary">
                    <?php echo $lang === 'ru' ? 'Добавить товар на eMall' : 'Add product on eMall'; ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
        window.__OZON_BASE__ = '<?php echo $apiBase; ?>';
    </script>
    <script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
    <script>
(function() {
    var base = '<?php echo $apiBase; ?>';
    var loginUrl = base + '/login';

    function redirectToLogin() {
        window.location.href = loginUrl;
    }

    var ozonSection = document.getElementById('ozonCredentialsSection');
    var chooseOzonBtn = document.getElementById('chooseOzonBtn');
    var chooseWbBtn = document.getElementById('chooseWbBtn');
    var chooseEmallBtn = document.getElementById('chooseEmallBtn');

    function showOzonSection() {
        if (ozonSection) {
            ozonSection.classList.remove('hidden');
        }
    }

    function showComingSoon() {
        var msg = '<?php echo $lang === 'ru' ? 'Скоро добавим этот маркетплейс.' : 'This marketplace will be available soon.'; ?>';
        alert(msg);
    }

    var ozonKeyBtn = document.getElementById('ozonKeyBtn');
    if (ozonKeyBtn) {
        ozonKeyBtn.onclick = showOzonSection;
    }
    if (chooseWbBtn) {
        chooseWbBtn.onclick = showComingSoon;
    }
    var emallSection = document.getElementById('emallCredentialsSection');
    var emallKeyBtn = document.getElementById('emallKeyBtn');
    if (emallKeyBtn && emallSection) {
        emallKeyBtn.onclick = function() {
            emallSection.classList.remove('hidden');
        };
    }

    window.apiFetchJson(window.buildApiUrlR('/me', base), { credentials: 'same-origin' })
        .then(function(data) {
            if (!data.ok && data.error && data.error.code === 'AUTH_REQUIRED') {
                redirectToLogin();
                return;
            }
            if (data.ok && data.data) {
                var el = document.getElementById('userEmail');
                if (el) el.textContent = data.data.email || el.textContent;
            }
        })
        .catch(function(err) {
            if (err && err.status === 401) redirectToLogin();
        });

    var resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        resendBtn.onclick = function() {
            var statusEl = document.getElementById('resendStatus');
            statusEl.textContent = '';
            window.apiFetchJson(base + '/auth/resend-verification', {
                method: 'POST',
                credentials: 'same-origin'
            }).then(function(data) {
                statusEl.textContent = data.ok ? '<?php echo $lang === 'ru' ? 'Отправлено' : 'Sent'; ?>' : (data.error && (data.error.message || data.error.code) || 'Error');
            }).catch(function(err) {
                statusEl.textContent = err && err.message ? err.message : '<?php echo $lang === 'ru' ? 'Ошибка' : 'Error'; ?>';
            });
        };
    }

    var logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.onclick = function(e) {
            e.preventDefault();
            window.apiFetchJson(base + '/auth/logout', { method: 'POST', credentials: 'same-origin' })
                .then(function() { window.location.href = loginUrl; })
                .catch(function() { window.location.href = loginUrl; });
        };
    }

    // Ozon credentials are now saved via a regular POST form to this page.
})();
    </script>
</body>
</html>
