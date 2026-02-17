/**
 * eMall Wizard - Step 1 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(1, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                var desc = (wizard.editedJson && wizard.editedJson.description) || '';
                var innerArticle = (wizard.editedJson && wizard.editedJson.inner_article) || '';
                var barcode = (wizard.editedJson && wizard.editedJson.barcode) || '';
                var html = '';
                html += '<p id="emallSavedKeyHint" class="emall-saved-key-hint" style="margin-bottom:8px;color:#666;font-size:14px;"></p>';        html += '<div class="field-row">';
                html += '<label class="field-label">' + t('innerArticleLabel') + '</label>';
                html += '<input type="text" class="field-input" data-field="inner_article" value="' + escapeHtml(innerArticle) + '" placeholder="">';
                html += '</div>';
                html += '<div class="field-row">';
                html += '<label class="field-label">' + t('barcodeLabel') + '</label>';
                html += '<input type="text" class="field-input" data-field="barcode" value="' + escapeHtml(barcode) + '" placeholder="">';
                html += '</div>';
                html += '<div class="field-row">';
                html += '<label class="field-label">' + t('descriptionLabel') + '</label>';
                html += '<textarea class="field-input field-textarea" data-field="description" placeholder="">' + escapeHtml(desc) + '</textarea>';
                html += '</div>';
                html += '<div class="field-row">';
                html += '<div class="field-label">' + (locale === 'ru' ? 'Изображения' : 'Images') + '</div>';
                html += '<div id="uploadArea" class="images-upload-area">' + t('dragDrop') + '</div>';
                html += '<input type="file" id="fileInput" accept="image/*" style="display:none">';
                html += '<div class="images-url-input"><input type="url" id="urlInput" placeholder="' + (locale === 'ru' ? 'URL изображения' : 'Image URL') + '"><button type="button" class="btn btn-secondary" id="urlButton">' + t('fromUrl') + '</button></div>';
                html += '<div id="imagesGrid" class="images-grid"></div>';
                html += '</div>';
                return html;
    });
})();
