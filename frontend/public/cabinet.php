<?php
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
$apiBase = htmlspecialchars($base);
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
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="chooseOzonBtn">
                        <?php echo $lang === 'ru' ? 'Добавлять на Ozon' : 'Use Ozon'; ?>
                    </button>
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
                            <?php echo htmlspecialchars($comingSoon); ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="chooseEmallBtn" disabled>
                        <?php echo $lang === 'ru' ? 'Скоро' : 'Soon'; ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="app-card hidden" id="ozonCredentialsSection">
            <h2 class="app-section-title"><?php echo htmlspecialchars($ozonSection); ?></h2>
            <p id="ozonKeyHint" class="app-text-muted"></p>
            <div class="app-form-row">
                <label for="ozonClientId"><?php echo htmlspecialchars($clientIdLabel); ?></label>
                <input type="text" id="ozonClientId" autocomplete="off">
            </div>
            <div class="app-form-row">
                <label for="ozonApiKey"><?php echo htmlspecialchars($apiKeyLabel); ?></label>
                <input
                    type="password"
                    id="ozonApiKey"
                    autocomplete="off"
                    placeholder="<?php echo $lang === 'ru' ? 'Введите для сохранения или смены' : 'Enter to save or change'; ?>"
                >
            </div>
            <button type="button" id="ozonSaveBtn" class="btn btn-primary">
                <?php echo htmlspecialchars($saveBtn); ?>
            </button>
            <p id="<?php echo $ozonStatusId; ?>" class="app-text-muted"></p>
            <div class="app-form-row">
                <a href="<?php echo $apiBase; ?>/add" class="btn btn-secondary">
                    <?php echo htmlspecialchars($addProduct); ?>
                </a>
            </div>
        </div>
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
    var ozonLoaded = false;

    function showOzonSection() {
        if (ozonSection) {
            ozonSection.classList.remove('hidden');
        }
        if (!ozonLoaded) {
            ozonLoaded = true;
            loadOzonCredentials();
        }
    }

    function showComingSoon() {
        var msg = '<?php echo $lang === 'ru' ? 'Скоро добавим этот маркетплейс.' : 'This marketplace will be available soon.'; ?>';
        alert(msg);
    }

    if (chooseOzonBtn) {
        chooseOzonBtn.onclick = function() {
            showOzonSection();
        };
    }
    if (chooseWbBtn) {
        chooseWbBtn.onclick = showComingSoon;
    }
    if (chooseEmallBtn) {
        chooseEmallBtn.onclick = showComingSoon;
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

    function loadOzonCredentials() {
        window.apiFetchJson(window.buildApiUrlR('/me/ozon-credentials', base), { credentials: 'same-origin' })
            .then(function(data) {
                if (!data.ok && data.error && data.error.code === 'AUTH_REQUIRED') {
                    redirectToLogin();
                    return;
                }
                if (data.ok && data.data) {
                    var clientIdInput = document.getElementById('ozonClientId');
                    var hint = document.getElementById('ozonKeyHint');
                    if (clientIdInput) clientIdInput.value = data.data.clientId || '';
                    if (hint) hint.textContent = data.data.apiKeyMasked ? ('Api-Key: ' + data.data.apiKeyMasked) : '';
                }
            })
            .catch(function(err) {
                if (err && err.status === 401) redirectToLogin();
            });
    }

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

    var ozonSaveBtn = document.getElementById('ozonSaveBtn');
    var ozonStatusEl = document.getElementById('<?php echo $ozonStatusId; ?>');
    if (ozonSaveBtn) {
        ozonSaveBtn.onclick = function() {
            var clientId = (document.getElementById('ozonClientId') && document.getElementById('ozonClientId').value || '').trim();
            var apiKey = (document.getElementById('ozonApiKey') && document.getElementById('ozonApiKey').value || '').trim();
            if (!clientId || !apiKey) {
                ozonStatusEl.textContent = '<?php echo $lang === 'ru' ? 'Заполните Client-Id и Api-Key' : 'Enter Client-Id and Api-Key'; ?>';
                return;
            }
            ozonStatusEl.textContent = '';
            window.apiFetchJson(window.buildApiUrlR('/me/ozon-credentials', base), {
                method: 'PUT',
                credentials: 'same-origin',
                body: { clientId: clientId, apiKey: apiKey }
            }).then(function(data) {
                if (!data.ok && data.error && data.error.code === 'AUTH_REQUIRED') {
                    redirectToLogin();
                    return;
                }
                if (data.ok) {
                    ozonStatusEl.textContent = '<?php echo $savedHint; ?>';
                } else {
                    ozonStatusEl.textContent = data.error && data.error.message ? data.error.message : 'Error';
                }
            }).catch(function(err) {
                if (err && err.status === 401) redirectToLogin();
                else ozonStatusEl.textContent = err && err.message ? err.message : '<?php echo $lang === 'ru' ? 'Ошибка' : 'Error'; ?>';
            });
        };
    }
})();
    </script>
</body>
</html>
