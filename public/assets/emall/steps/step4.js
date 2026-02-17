/**
 * eMall Wizard - Step 4 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(4, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var dim = wizard.dimensionsResult;
                    var pr = wizard.priceResult;
                    var pred = wizard.brandCountryPredicted;
                    // brandsList is populated on-demand via AJAX search (do NOT preload the whole directory)
                    var brands = (wizard.brandsList || []).slice();
                    var countries = (wizard.countriesList || []).slice();
                    var brandSel = wizard.brandSelected;
                    var countrySel = wizard.countrySelected;
                    // Ensure saved brand/country from step_state appear in options even if not in fetched lists
                    if (brandSel && typeof brandSel === 'object' && brandSel.id != null && !brands.some(function(b) { return b.id == brandSel.id; })) {
                        brands.unshift({ id: brandSel.id, name: brandSel.name || '' });
                    }
                    if (countrySel && typeof countrySel === 'object' && countrySel.id != null && !countries.some(function(c) { return c.id == countrySel.id; })) {
                        countries.unshift({ id: countrySel.id, name: countrySel.name || '' });
                    }
                    var brandNotFound = pred && pred.brand && !brandSel;
                    var countryNotFound = pred && pred.country && !countrySel;
            
                    var html = '<div class="category-step step4-brand-country">';
                    html += '<h3>' + t('step4Label') + '</h3>';
                    if (dim) {
                        html += '<div class="step4-dimensions-summary"><strong>' + (locale === 'ru' ? 'Габариты: ' : 'Dimensions: ') + '</strong>' + dim.length_mm + '×' + dim.width_mm + '×' + dim.height_mm + ' мм, ' + (locale === 'ru' ? 'вес ' : 'weight ') + dim.weight_g + ' г</div>';
                    }
                    if (pr && pr.price != null) {
                        html += '<div class="step4-price-summary"><strong>' + t('resultPriceLabel') + ':</strong> ' + pr.price.toFixed(2) + ' BYN</div>';
                    }
                    if (wizard.brandCountryLoading) {
                        html += '<p class="step4-loading">' + (locale === 'ru' ? 'Определяем бренд и страну...' : 'Predicting brand and country...') + '</p>';
                        html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
                    } else {
                        if (brandNotFound && brands.length) {
                            html += '<div class="step4-not-found">';
                            html += '<p>' + t('brandNotFound') + ': ' + escapeHtml(pred.brand) + '</p>';
                            html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="reloadBrandsBtn">' + t('reloadDirectory') + '</button> ';
                            html += '<button type="button" class="btn btn-secondary" id="selectBrandAgainBtn">' + t('selectBrandAgain') + '</button> ';
                            html += '<button type="button" class="btn btn-primary" id="selectRandomBrandBtn">' + t('selectRandomBrand') + '</button></div>';
                            html += '</div>';
                        }
                        // Brand
                        if (brandSel && !wizard.brandManual) {
                            html += '<div class="field-row"><label>' + t('brandLabel') + '</label>';
                            html += '<div class="selected-pill">' + escapeHtml(brandSel.name || '') + '</div>';
                            html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="selectBrandAgainBtn">' + t('selectBrandAgain') + '</button></div>';
                            html += '</div>';
                        } else {
                            html += '<div class="field-row"><label>' + t('brandLabel') + '</label>';
                            html += '<div class="typeahead">';
                            html += '<input id="brandInput" class="field-input" type="text" autocomplete="off" '
                                + 'placeholder="' + (locale === 'ru' ? 'Введите бренд (минимум 4 символа)' : 'Type brand (min 4 chars)') + '" '
                                + 'value="' + escapeHtml((brandSel && brandSel.name) ? brandSel.name : '') + '" />';
                            html += '<div id="brandSuggest" class="typeahead-list" style="display:none"></div>';
                            html += '</div>';
                            html += '<div class="typeahead-hint">' + (locale === 'ru' ? 'Подсказки появятся после ввода 4 символов.' : 'Suggestions appear after 4 characters.') + '</div>';
                            html += '</div>';
                        }
            
            html += '</div>';
            
                        if (countryNotFound && countries.length) {
                            html += '<div class="step4-not-found">';
                            html += '<p>' + t('countryNotFound') + ': ' + escapeHtml(pred.country) + '</p>';
                            html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="reloadCountriesBtn">' + t('reloadDirectory') + '</button> ';
                            html += '<button type="button" class="btn btn-secondary" id="selectCountryAgainBtn">' + t('selectCountryAgain') + '</button> ';
                            html += '<button type="button" class="btn btn-primary" id="selectRandomCountryBtn">' + t('selectRandomCountry') + '</button></div>';
                            html += '</div>';
                        }
                        // Country
                        if (countrySel && !wizard.countryManual) {
                            html += '<div class="field-row"><label>' + t('countryLabel') + '</label>';
                            html += '<div class="selected-pill">' + escapeHtml(countrySel.name || '') + '</div>';
                            html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="selectCountryAgainBtn">' + (locale === 'ru' ? 'Выбрать страну заново' : 'Select country again') + '</button></div>';
                            html += '</div>';
                        } else {
                            html += '<div class="field-row"><label>' + t('countryLabel') + '</label>';
                            html += '<select id="countrySelect" class="field-input">';
                            html += '<option value="">' + (locale === 'ru' ? '— Выберите страну —' : '— Select country —') + '</option>';
                            countries.forEach(function(c) {
                                var sel = countrySel && countrySel.id == c.id ? ' selected' : '';
                                html += '<option value="' + c.id + '" data-name="' + escapeHtml(c.name) + '"' + sel + '>' + escapeHtml(c.name) + '</option>';
                            });
                            html += '</select></div>';
                        }
                    }
                    html += '</div>';
                    return html;
        });
    })();
