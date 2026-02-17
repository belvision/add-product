/**
 * eMall Wizard - Step 5 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(5, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var props = wizard.categoryPropertiesList || [];
                    var filled = wizard.categoryPropertiesFilled || [];
                    var loading = wizard.categoryPropertiesLoading || wizard.categoryPropertiesFilling;
                    var filledMap = {};
                    filled.forEach(function(p) { filledMap[p.id] = p.value; });
            
                    var html = '<div class="category-step step5-properties">';
                    html += '<h3>' + t('step5Label') + '</h3>';
                    if (loading) {
                        html += '<p class="step5-loading">' + (wizard.categoryPropertiesFilling ? t('propertiesFillLoading') : t('propertiesLoading')) + '</p>';
                        html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
                    } else if (props.length === 0) {
                        html += '<p>' + (locale === 'ru' ? 'Нет свойств для заполнения.' : 'No properties to fill.') + '</p>';
                    } else {
                        html += '<p class="step5-hint">' + (locale === 'ru' ? 'Нажмите «Продолжить», чтобы заполнить с помощью DeepSeek. Вы можете изменить значения вручную.' : 'Click Continue to fill with DeepSeek. You can change values manually.') + '</p>';
                        html += '<div class="step5-properties-form">';
                        props.forEach(function(prop) {
                            var propId = prop.id;
                            var name = prop.name || '';
                            var isReq = prop.is_required ? '<span class="step5-required">*</span>' : '';
                            var typeName = (prop.property && prop.property.type && prop.property.type.name) ? prop.property.type.name : '';
                            var typeTitle = (prop.property && prop.property.type && prop.property.type.title) ? prop.property.type.title : '';
                            var values = (prop.property && prop.property.values) ? prop.property.values : [];
                            var val = filledMap[propId];
                            var safeId = 'prop-' + propId;
                            html += '<div class="step5-prop-item step5-prop-' + escapeHtml(typeName) + '" data-prop-id="' + propId + '">';
                            html += '<label class="step5-prop-label" for="' + safeId + '"><span class="step5-prop-name">' + escapeHtml(name) + isReq + '</span>';
                            if (typeTitle) html += ' <span class="step5-prop-type">(' + escapeHtml(typeTitle) + ')</span>';
                            html += '</label>';
            
                            if (typeName === 'list' && values.length) {
                                html += '<select id="' + safeId + '" class="step5-select field-input" data-prop-id="' + propId + '">';
                                html += '<option value="">' + (locale === 'ru' ? '— Выберите —' : '— Select —') + '</option>';
                                values.forEach(function(v) {
                                    var sel = (val == v.id || (val === v.id)) ? ' selected' : '';
                                    html += '<option value="' + v.id + '"' + sel + '>' + escapeHtml(v.value || '') + ' (id: ' + v.id + ')</option>';
                                });
                                html += '</select>';
                            } else if (typeName === 'multiselect_list' && values.length) {
                                html += '<div class="step5-multiselect" id="' + safeId + '" data-prop-id="' + propId + '">';
                                var selectedIds = {};
                                if (Array.isArray(val)) val.forEach(function(id) { selectedIds[id] = true; });
                                else if (val != null && val !== '') selectedIds[val] = true;
                                values.forEach(function(v) {
                                    var checked = selectedIds[v.id] ? ' checked' : '';
                                    html += '<label class="step5-checkbox-label"><input type="checkbox" class="step5-checkbox" value="' + v.id + '" data-prop-id="' + propId + '"' + checked + '> ' + escapeHtml(v.value || '') + ' (id: ' + v.id + ')</label>';
                                });
                                html += '</div>';
                            } else if (typeName === 'number') {
                                var numVal = (val != null && val !== '') ? String(val) : '';
                                html += '<input type="number" id="' + safeId + '" class="step5-input field-input" data-prop-id="' + propId + '" value="' + escapeHtml(numVal) + '" placeholder="">';
                            } else if (typeName === 'boolean') {
                                var boolVal = val === true || val === 'true' || val === 1;
                                html += '<select id="' + safeId + '" class="step5-select field-input" data-prop-id="' + propId + '">';
                                html += '<option value="">' + (locale === 'ru' ? '— Выберите —' : '— Select —') + '</option>';
                                html += '<option value="true"' + (boolVal ? ' selected' : '') + '>' + (locale === 'ru' ? 'Да' : 'Yes') + '</option>';
                                html += '<option value="false"' + (!boolVal && (val === false || val === 'false' || val === 0) ? ' selected' : '') + '>' + (locale === 'ru' ? 'Нет' : 'No') + '</option>';
                                html += '</select>';
                            } else {
                                var strVal = (val != null && val !== '') ? String(val) : '';
                                html += '<input type="text" id="' + safeId + '" class="step5-input field-input" data-prop-id="' + propId + '" value="' + escapeHtml(strVal) + '" placeholder="">';
                            }
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    return html;
        });
    })();
