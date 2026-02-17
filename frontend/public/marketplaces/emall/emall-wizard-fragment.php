<?php
/**
 * eMall wizard fragment: container and scripts for eMall marketplace.
 * Included by add.php when marketplace=emall, or by frontend/public/emall-wizard.php.
 */
$base = isset($base) ? $base : '';
?>
<div id="emallWizard"></div>
<script>
    window.__OZON_LANG__ = '<?php echo isset($lang) ? htmlspecialchars($lang) : 'ru'; ?>';
    window.__OZON_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    window.__APP_BASE__ = '<?php echo htmlspecialchars($base); ?>';
    window.__MARKETPLACE__ = 'emall';
    window.__LANG__ = '<?php echo isset($lang) ? htmlspecialchars($lang) : 'ru'; ?>';
    window.__OZON_DRAFT_ID__ = null;
    window.__OZON_EMAIL_VERIFIED__ = <?php echo (isset($emailVerified) && $emailVerified) || (function_exists('Auth::isEmailVerified') && Auth::isEmailVerified()) ? 'true' : 'false'; ?>;
</script>
<script src="<?php echo htmlspecialchars($base); ?>/public/js/api.js"></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/emall-wizard.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step1.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step2.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step3.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step4.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step5.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step6.js" defer></script>
<script src="<?php echo htmlspecialchars($base); ?>/public/assets/emall/steps/step7.js" defer></script>
