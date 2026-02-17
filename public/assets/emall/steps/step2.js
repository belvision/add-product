/**
 * eMall Wizard - Step 2 renderer module
 * Registers renderer into window.EmallWizard (no bundler).
 */
(function() {
    'use strict';
    var ns = window.EmallWizard;
    if (!ns || typeof ns.registerStepRenderer !== 'function') return;

    ns.registerStepRenderer(2, function() {
        var ctx = ns.ctx || {};
        var wizard = (typeof ctx.getWizard === 'function') ? ctx.getWizard() : (ns.wizard || {});
        var t = ctx.t || function(k){ return k; };
        var escapeHtml = ctx.escapeHtml || function(s){ return (s==null?'':String(s)); };
        var locale = ctx.locale || (window.__OZON_LANG__ || window.__LANG__ || 'ru');

                    var gt = wizard.generatedText || { title: '', description: '', benefits: '', usage: '' };
                    var combined = buildCombinedDescription(gt);
                    var html = '<div class="emall-text-step">';
                    html += '<button type="button" class="btn btn-secondary" id="generateTextBtn"' + (wizard.textGenerating ? ' disabled' : '') + '>' + (wizard.textGenerating ? t('generating') : t('generateBtn')) + '</button>';
                    html += '<div class="field-row"><label class="field-label">' + t('titleLabel') + '</label>';
                    html += '<input type="text" class="field-input" id="genTitle" data-gen-field="title" value="' + escapeHtml(gt.title) + '" placeholder="">';
                    html += '</div>';
                    html += '<div class="field-row"><label class="field-label">' + (locale === 'ru' ? 'Описание (3 абзаца: описание, преимущества, применение)' : 'Description (3 paragraphs: description, benefits, usage)') + '</label>';
                    html += '<textarea class="field-input field-textarea" id="genDescCombined" rows="12" placeholder="">' + escapeHtml(combined) + '</textarea>';
                    html += '</div>';
                    html += '</div>';
                    return html;
        });
    })();
