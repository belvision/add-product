/**
 * eMall Wizard - Step 7 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(7, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var result = wizard.submitProductResult;
                    var loading = wizard.submitProductLoading;
                    var payload = wizard.payloadData;
            
                    var html = '<div class="category-step step7-submit">';
                    html += '<h3>' + t('step7Label') + '</h3>';
                    if (loading) {
                        html += '<p class="step7-loading">' + t('submitProductLoading') + '</p>';
                        html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
                    } else if (result) {
                        if (result.success) {
                            html += '<div class="step7-success">';
                            html += '<p>' + t('submitSuccess') + '</p>';
                            if (result.removedProps && result.removedProps.length) {
                                html += '<p class="step7-removed">' + (locale === 'ru' ? 'Удалены невалидные необязательные свойства: ' : 'Removed invalid optional properties: ') + escapeHtml(result.removedProps.join(', ')) + '</p>';
                            }
                            html += '</div>';
                        } else {
                            html += '<div class="step7-error">';
                            html += '<p>' + escapeHtml(result.message || t('error')) + '</p>';
                            if (result.response) {
                                html += '<pre class="step7-response">' + escapeHtml(JSON.stringify(result.response, null, 2)) + '</pre>';
                            }
                            html += '</div>';
                        }
                    } else {
                        html += '<p>' + (locale === 'ru' ? 'Payload подготовлен. Нажмите «Отправить товар» для создания на eMall.' : 'Payload ready. Click Submit product to create on eMall.') + '</p>';
                        if (payload && payload.product) {
                            html += '<p class="step7-summary">' + (locale === 'ru' ? 'Товар: ' : 'Product: ') + escapeHtml(payload.product.name || '') + '</p>';
                        }
                    }
                    html += '</div>';
                    return html;
        });
    })();
