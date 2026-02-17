/**
 * eMall Wizard - Step 6 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(6, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var payload = wizard.payloadData;
                    var loading = wizard.payloadLoading;
                    var stock = (wizard.editedJson && wizard.editedJson.stock != null) ? parseInt(wizard.editedJson.stock, 10) : 30;
                    if (isNaN(stock) || stock < 0) stock = 30;
                    var innerArticle = (wizard.editedJson && wizard.editedJson.inner_article) || '';
                    var importer = (wizard.editedJson && wizard.editedJson.importer) || '';
                    var brandName = (wizard.brandSelected && wizard.brandSelected.name) || '';
            
                    var html = '<div class="category-step step6-payload">';
                    html += '<h3>' + t('step6Label') + '</h3>';
                    html += '<div class="field-row">';
                    html += '<label class="field-label">' + t('stockLabel') + '</label>';
                    html += '<input type="number" id="stockInput" class="field-input step6-stock" value="' + stock + '" min="0" placeholder="30">';
                    html += '</div>';
                    html += '<div class="field-row">';
                    html += '<label class="field-label">' + t('innerArticleOverrideLabel') + '</label>';
                    html += '<input type="text" id="innerArticleInput" class="field-input step6-inner-article" value="' + escapeHtml(innerArticle) + '" placeholder="">';
                    html += '</div>';
                    html += '<div class="field-row">';
                    html += '<label class="field-label">' + t('importerLabel') + '</label>';
                    html += '<input type="text" id="importerInput" class="field-input step6-importer" value="' + escapeHtml(importer) + '" placeholder="' + escapeHtml(brandName || (locale === 'ru' ? 'По умолчанию = бренд' : 'Default = brand')) + '">';
                    html += '</div>';
                    if (loading) {
                        html += '<p class="step6-loading">' + (locale === 'ru' ? 'Формируем payload...' : 'Building payload...') + '</p>';
                        html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
                    } else if (payload) {
                        if (payload.errors && payload.errors.length) {
                            html += '<div class="step6-errors">';
                            payload.errors.forEach(function(err) {
                                html += '<p class="step6-error">' + escapeHtml(err) + '</p>';
                            });
                            html += '</div>';
                        }
                        html += '<p class="step6-success">' + t('payloadReady') + '</p>';
                        html += '<pre class="step6-payload-json">' + escapeHtml(JSON.stringify(payload.product, null, 2)) + '</pre>';
                    } else {
                        html += '<p>' + (locale === 'ru' ? 'Нажмите «Обновить», чтобы сформировать payload.' : 'Click Refresh to build payload.') + '</p>';
                    }
                    html += '</div>';
                    return html;
        });
    })();
