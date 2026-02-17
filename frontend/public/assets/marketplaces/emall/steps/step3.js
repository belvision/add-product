/**
 * eMall Wizard - Step 3 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(3, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var gt = wizard.generatedText || { title: '', description: '', benefits: '', usage: '' };
                    var combined = buildCombinedDescription(gt);
                    var pr = wizard.priceResult;
                    var dim = wizard.dimensionsResult;
                    var priceZacVal = pr ? String(pr.priceZac) : '';
                    var weightVal = pr ? String(pr.weightGram) : (dim && dim.weight_g != null ? String(dim.weight_g) : '1000');
                    var marginVal = pr ? String(pr.marginPercent) : '20';
                    var html = '<div class="category-step step3-with-text step3-price">';
                    html += '<h3>' + t('step3Label') + '</h3>';
                    if (wizard.dimensionsLoading) {
                        html += '<p class="step3-dimensions-loading">' + t('dimensionsLoading') + '</p>';
                        html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
                    } else if (dim) {
                        html += '<p class="step3-price-hint">' + (locale === 'ru' ? 'Габариты и вес определены по описанию. Введите закупочную цену и маржинальность. Маржинальность — доля чистой прибыли (после налога) в итоговой цене.' : 'Dimensions and weight predicted from description. Enter purchase price and margin. Margin is the share of net profit (after tax) in the final price.') + '</p>';
                        html += '<div class="dimensions-summary"><strong>' + (locale === 'ru' ? 'Определено: ' : 'Predicted: ') + '</strong>' + (locale === 'ru' ? 'Д×Ш×В ' : 'L×W×H ') + dim.length_mm + '×' + dim.width_mm + '×' + dim.height_mm + ' мм, ' + (locale === 'ru' ? 'вес ' : 'weight ') + dim.weight_g + ' г</div>';
                    } else {
                        html += '<p class="step3-price-hint">' + (locale === 'ru' ? 'Введите закупочную цену, вес и желаемую маржинальность. Маржинальность — доля чистой прибыли (после налога) в итоговой цене.' : 'Enter purchase price, weight and target margin. Margin is the share of net profit (after tax) in the final price.') + '</p>';
                    }
                    html += '<div class="price-form">';
                    html += '<div class="field-row"><label>' + t('priceZacLabel') + '</label><input type="text" id="priceZacInput" class="field-input" placeholder="0.00" value="' + escapeHtml(priceZacVal) + '"></div>';
                    html += '<div class="field-row"><label>' + t('weightLabel') + '</label><input type="text" id="weightInput" class="field-input" placeholder="1000" value="' + escapeHtml(weightVal) + '"></div>';
                    html += '<div class="field-row"><label>' + t('marginLabel') + '</label><input type="text" id="marginInput" class="field-input" placeholder="20" value="' + escapeHtml(marginVal) + '"></div>';
                    html += '<button type="button" class="btn btn-primary" id="calcPriceBtn">' + t('calcPriceBtn') + '</button>';
                    html += '</div>';
                    if (pr && pr.price != null) {
                        html += '<div class="price-result-block">';
                        html += '<p class="price-result-item"><strong>' + t('resultPriceLabel') + ':</strong> <span class="price-value">' + pr.price.toFixed(2) + ' BYN</span></p>';
                        html += '<p class="price-result-item old-price"><strong>' + t('resultOldPriceLabel') + ':</strong> <span class="price-value strikethrough">' + pr.old_price.toFixed(2) + ' BYN</span></p>';
                        html += '</div>';
                    }
                    if (gt.title) {
                        html += '<div class="saved-text-block"><strong>' + (locale === 'ru' ? 'Сохранённый заголовок: ' : 'Saved title: ') + '</strong><span class="saved-title">' + escapeHtml(gt.title) + '</span></div>';
                    }
                    if (combined) {
                        html += '<div class="saved-text-block"><strong>' + (locale === 'ru' ? 'Сохранённое описание: ' : 'Saved description: ') + '</strong><div class="saved-description">' + escapeHtml(combined).replace(/\n/g, '<br>') + '</div></div>';
                    }
                    html += '</div>';
                    return html;
        });
    })();
