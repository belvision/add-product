/* SOURCE: frontend/public/assets/marketplaces/emall/emall-wizard.js — EDIT THERE. Legacy copy for URL compatibility. */
(function() {
    'use strict';

    const locale = (typeof window.__OZON_LANG__ === 'string' && window.__OZON_LANG__) ? window.__OZON_LANG__ : (window.__LANG__ || 'ru');
    const MARKETPLACE = 'emall';

    const i18n = {
        ru: {
            saving: 'Сохранение...',
            saved: 'Сохранено',
            error: 'Ошибка',
            next: 'Далее',
            prev: 'Назад',
            continue: 'Продолжить',
            uploading: 'Загрузка...',
            uploadError: 'Ошибка загрузки',
            delete: 'Удалить',
            dragDrop: 'Перетащите изображения сюда или нажмите для выбора',
            fromUrl: 'Загрузить по URL',
            apiKeyLabel: 'API ключ eMall',
            descriptionLabel: 'Описание товара',
            startPipeline: 'Запустить пайплайн',
            pipelineProgress: 'Прогресс пайплайна',
            pipelineStep1: '1/5 DeepSeek строит дерево категорий...',
            pipelineStep2: '2/5 Получаем embedding...',
            pipelineStep3: '3/5 Ищем 10 ближайших в Qdrant (eMall)...',
            pipelineStep4: '4/5 Получаем названия категорий...',
            pipelineStep5: '5/5 DeepSeek выбирает категорию...',
            pipelineIntro: 'Введите описание и нажмите «Запустить пайплайн».',
            pipelineTop10Title: 'Кандидаты (top10):',
            pipelineSelectedTitle: 'Выбрана категория:',
            goToStep2: 'Перейти к шагу 2',
            categoryWeDetected: 'Определена категория eMall',
            categoryManualHint: 'Проверьте, что категория подходит товару.',
            pipelineError: 'Не удалось запустить пайплайн',
            draftFound: 'Найден черновик (обновлён {date}).',
            draftFoundStep: 'Шаг {step}',
            continueDraft: 'Продолжить',
            resetDraft: 'Удалить и начать заново',
            step1Label: 'Данные и пайплайн',
            step2Label: 'Категория'
        },
        en: {
            saving: 'Saving...',
            saved: 'Saved',
            error: 'Error',
            next: 'Next',
            prev: 'Previous',
            continue: 'Continue',
            uploading: 'Uploading...',
            uploadError: 'Upload error',
            delete: 'Delete',
            dragDrop: 'Drag and drop images here or click to select',
            fromUrl: 'Load from URL',
            apiKeyLabel: 'eMall API key',
            descriptionLabel: 'Product description',
            startPipeline: 'Start pipeline',
            pipelineProgress: 'Pipeline progress',
            pipelineStep1: '1/5 DeepSeek builds category tree...',
            pipelineStep2: '2/5 Getting embedding...',
            pipelineStep3: '3/5 Searching top10 in Qdrant (eMall)...',
            pipelineStep4: '4/5 Resolving category names...',
            pipelineStep5: '5/5 DeepSeek chooses category...',
            pipelineIntro: 'Enter description and click "Start pipeline".',
            pipelineTop10Title: 'Candidates (top10):',
            pipelineSelectedTitle: 'Selected category:',
            goToStep2: 'Go to step 2',
            categoryWeDetected: 'eMall category detected',
            categoryManualHint: 'Check that the category fits your product.',
            pipelineError: 'Failed to run pipeline',
            draftFound: 'Draft found (updated {date}).',
            draftFoundStep: 'Step {step}',
            continueDraft: 'Continue',
            resetDraft: 'Delete and start over',
            step1Label: 'Data & pipeline',
            step2Label: 'Category'
        }
    };

    function t(key) {
        return i18n[locale][key] || key;
    }

    function getApiBase() {
        const base = (typeof window.__OZON_BASE__ !== 'undefined' && window.__OZON_BASE__ != null)
            ? window.__OZON_BASE__
            : (typeof window.__APP_BASE__ !== 'undefined' && window.__APP_BASE__ != null)
                ? window.__APP_BASE__
                : (window.location.pathname.replace(/\/[^/]*$/, '') || '');
        return base === '' ? '' : base;
    }

    function buildApiUrlR(route, extraQuery) {
        const apiBase = getApiBase();
        let r = route || '';
        if (r && r[0] !== '/') r = '/' + r;
        let url = apiBase + '/api' + r;
        if (extraQuery) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + extraQuery;
        }
        return url;
    }

    function apiFetch(path, options) {
        options = options || {};
        const qPos = path.indexOf('?');
        const route = qPos === -1 ? path : path.slice(0, qPos);
        const extraQuery = qPos === -1 ? '' : path.slice(qPos + 1);
        const fullPath = buildApiUrlR(route, extraQuery);
        return (typeof window.apiFetchJson === 'function' ? window.apiFetchJson(fullPath, options) : legacyFetch(fullPath, options))
            .then(function(data) {
                if (!data.ok) {
                    const err = new Error(data.error && data.error.message || 'Request failed');
                    err.code = data.error && data.error.code;
                    err.details = data.error && data.error.details || {};
                    throw err;
                }
                return data.data;
            });
    }

    function legacyFetch(url, options) {
        return fetch(url, {
            method: options.method || 'GET',
            headers: options.headers || {},
            body: options.body,
            credentials: 'same-origin'
        }).then(function(r) { return r.text(); }).then(function(text) {
            try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON'); }
        });
    }

    const wizard = {
        marketplace: MARKETPLACE,
        draftId: null,
        editedJson: {},
        images: [],
        currentStepIndex: 0,
        savingState: null,
        pendingDraft: null,
        categoryPipeline: null,
        categoryPipelineLog: [],
        progressEventSource: null,
        categoryTop10: [],
        categorySelected: null,
        categoryDeepseek: null,
        categoryTree: '',
        categoryDeepseekTree: null,
        steps: [
            { key: 'data', labelKey: 'step1Label' },
            { key: 'category', labelKey: 'step2Label' }
        ]
    };

    function buildStepState() {
        const state = {};
        if (wizard.images && wizard.images.length) {
            state.images = wizard.images.map(function(img) {
                return { id: img.imageId, url: img.url || null, source: img.source || 'upload' };
            });
        }
        if (wizard.editedJson && typeof wizard.editedJson === 'object') {
            state.attributes = Object.assign({}, wizard.editedJson);
        }
        if (wizard.categorySelected) {
            state.category = {
                category_id: wizard.categorySelected.category_id,
                title_cat: wizard.categorySelected.title_cat || '',
                path: wizard.categorySelected.path || '',
                type_name: wizard.categorySelected.type_name || '',
                score: wizard.categorySelected.score,
                top10: wizard.categoryTop10
            };
        }
        return state;
    }

    function applyStepState(stepState) {
        if (!stepState || typeof stepState !== 'object') return;
        if (Array.isArray(stepState.images) && stepState.images.length) {
            wizard.images = stepState.images.map(function(item) {
                return {
                    imageId: item.id || item.imageId,
                    url: item.url || null,
                    source: item.source || 'upload'
                };
            });
        }
        if (stepState.attributes && typeof stepState.attributes === 'object') {
            wizard.editedJson = Object.assign({}, wizard.editedJson, stepState.attributes);
        }
        if (stepState.category) {
            var c = stepState.category;
            wizard.categorySelected = {
                category_id: c.category_id,
                title_cat: c.title_cat || '',
                path: c.path || '',
                type_name: c.type_name || '',
                score: c.score
            };
            if (stepState.category.top10) wizard.categoryTop10 = stepState.category.top10;
        }
    }

    /**
     * Step 1: no selected_category. Step 2: selected_category present (from editedJson or chosen_category/step_state).
     */
    function resolveDraftStep(draft) {
        var step = typeof draft.current_step === 'number' && draft.current_step >= 2 ? draft.current_step : 0;
        if (step >= 2) return step;
        if (draft.editedJson && draft.editedJson.selected_category && typeof draft.editedJson.selected_category === 'object' && Object.keys(draft.editedJson.selected_category).length > 0) {
            return 2;
        }
        if (draft.chosen_category && typeof draft.chosen_category === 'object' && Object.keys(draft.chosen_category).length > 0) return 2;
        var sc = draft.step_state && draft.step_state.category;
        if (sc && typeof sc === 'object' && (sc.category_id || sc.title_cat || sc.path || (Object.keys(sc).length > 0))) return 2;
        return 1;
    }

    function goToStep(step1Based) {
        wizard.currentStepIndex = Math.max(0, (step1Based || 1) - 1);
        render();
    }

    function applyDraftToWizard(draft) {
        wizard.draftId = draft.id || draft.draftId;
        wizard.editedJson = draft.editedJson && typeof draft.editedJson === 'object' ? draft.editedJson : {};
        if (typeof draft.description === 'string') {
            wizard.editedJson.description = draft.description;
        }
        wizard.images = (draft.step_state && Array.isArray(draft.step_state.images) && draft.step_state.images.length)
            ? draft.step_state.images.map(function(item) {
                return { imageId: item.id || item.imageId, url: item.url || null, source: item.source || 'upload' };
            })
            : (draft.images && Array.isArray(draft.images) && draft.images.length) ? draft.images : [];
        applyStepState(draft.step_state);
        if (draft.chosen_category && typeof draft.chosen_category === 'object') {
            wizard.categorySelected = draft.chosen_category;
        }
        if (!wizard.categorySelected && draft.step_state && draft.step_state.category) {
            var c = draft.step_state.category;
            wizard.categorySelected = {
                category_id: c.category_id,
                title_cat: c.title_cat || '',
                path: c.path || '',
                type_name: c.type_name || '',
                score: c.score
            };
        }
        if (draft.step_state && draft.step_state.category && draft.step_state.category.top10) {
            wizard.categoryTop10 = draft.step_state.category.top10;
        }
        if (draft.editedJson && draft.editedJson.selected_category && !wizard.categorySelected) {
            wizard.categorySelected = draft.editedJson.selected_category;
        }
        if (draft.editedJson && draft.editedJson.qdrant_top10 && draft.editedJson.qdrant_top10.length) {
            wizard.categoryTop10 = draft.editedJson.qdrant_top10;
        }
        var resolvedStep = resolveDraftStep(draft);
        wizard.currentStepIndex = Math.max(0, resolvedStep - 1);
        wizard.pendingDraft = null;
        render();
        goToStep(resolvedStep);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDraftDate(isoOrNull) {
        if (!isoOrNull) return '';
        var d = new Date(isoOrNull);
        if (isNaN(d.getTime())) return '';
        if (locale === 'ru') {
            return d.getDate().toString().padStart(2, '0') + '.' + (d.getMonth() + 1).toString().padStart(2, '0') + '.' + d.getFullYear() + ', ' + d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
        }
        return (d.getMonth() + 1) + '/' + d.getDate() + '/' + d.getFullYear() + ', ' + d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
    }

    function renderPipelineContent() {
        const cp = wizard.categoryPipeline;
        const log = wizard.categoryPipelineLog || [];
        const isRunning = cp && cp.status === 'running';

        if (!cp || cp.status === 'idle') {
            return '<p>' + t('pipelineIntro') + '</p>' +
                '<ul class="pipeline-steps">' +
                '<li>' + t('pipelineStep1') + '</li><li>' + t('pipelineStep2') + '</li><li>' + t('pipelineStep3') + '</li><li>' + t('pipelineStep4') + '</li><li>' + t('pipelineStep5') + '</li></ul>';
        }

        if (cp.status === 'error') {
            var h = '<p class="status-error">' + escapeHtml(cp.error || t('pipelineError')) + '</p>';
            if (log.length) {
                h += '<div class="pipeline-log-title">' + (locale === 'ru' ? 'Ход обработки' : 'Processing log') + '</div><ul class="pipeline-log">';
                log.forEach(function(ev) {
                    h += '<li class="pipeline-log-entry ' + (ev.level || 'info') + '">' + escapeHtml(ev.message || '') + '</li>';
                });
                h += '</ul>';
            }
            return h;
        }

        var h = '';
        if (log.length > 0 || isRunning) {
            h += '<div class="pipeline-log-title">' + (locale === 'ru' ? 'Ход обработки' : 'Processing log') + '</div>';
            if (isRunning) {
                h += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
            }
            h += '<ul class="pipeline-log">';
            log.forEach(function(ev) {
                var level = ev.level || 'info';
                var msg = ev.message || '';
                var meta = ev.meta || {};
                var metaStr = '';
                if (meta.scores && Array.isArray(meta.scores) && meta.scores.length) {
                    metaStr = ' <span class="pipeline-log-meta">(' + (locale === 'ru' ? 'scores: ' : 'scores: ') + meta.scores.slice(0, 5).join(', ') + ')</span>';
                }
                h += '<li class="pipeline-log-entry ' + escapeHtml(level) + '">' + escapeHtml(msg) + metaStr + '</li>';
            });
            h += '</ul>';
        }
        h += '<ul class="pipeline-steps"><li>' + t('pipelineStep1') + '</li><li>' + t('pipelineStep2') + '</li><li>' + t('pipelineStep3') + '</li><li>' + t('pipelineStep4') + '</li><li>' + t('pipelineStep5') + '</li></ul>';

        if (wizard.categoryTree) {
            h += '<p class="category-tree-line"><strong>' + (locale === 'ru' ? 'Дерево категорий: ' : 'Category tree: ') + '</strong>' + escapeHtml(wizard.categoryTree) + '</p>';
        }

        var top10 = wizard.categoryTop10 || [];
        if (top10.length) {
            h += '<div class="pipeline-top10"><strong>' + t('pipelineTop10Title') + '</strong><ol>';
            top10.forEach(function(item, idx) {
                var second = (item.type_name || item.path || '');
                var title = (item.title_cat || '') + (item.title_cat && second ? ' \u2192 ' : '') + second || ('id ' + (item.category_id || idx));
                var score = (typeof item.score === 'number') ? item.score.toFixed(3) : item.score;
                h += '<li>' + (idx + 1) + ') ' + escapeHtml(title) + ' (score: ' + score + ')</li>';
            });
            h += '</ol></div>';
        }

        if (wizard.categorySelected) {
            var sel = wizard.categorySelected;
            var second = (sel.type_name || sel.path || '');
            var label = (sel.title_cat || '') + (sel.title_cat && second ? ' \u2192 ' : '') + second || ('id ' + (sel.category_id || sel.id_embedding || sel.db_id || ''));
            h += '<p class="pipeline-selected"><strong>' + t('pipelineSelectedTitle') + '</strong> ' + escapeHtml(label) + '</p>';
        }

        return h;
    }

    function closeProgressEventSource() {
        if (wizard.progressEventSource) {
            wizard.progressEventSource.close();
            wizard.progressEventSource = null;
        }
    }

    function startCategoryPipeline() {
        if (!wizard.draftId) return;
        var desc = (wizard.editedJson && typeof wizard.editedJson.description === 'string') ? wizard.editedJson.description.trim() : '';
        if (!desc) {
            showStatus('error', locale === 'ru' ? 'Сначала введите описание товара' : 'Please enter product description first');
            return;
        }

        wizard.categoryPipeline = { status: 'running' };
        wizard.categoryPipelineLog = [];
        wizard.categoryTop10 = [];
        wizard.categorySelected = null;
        wizard.categoryDeepseek = null;
        wizard.categoryTree = '';
        wizard.categoryDeepseekTree = null;
        closeProgressEventSource();
        render();

        apiFetch('/progress/create', { method: 'POST' }).then(function(createData) {
            var jobId = createData && createData.job_id ? createData.job_id : null;
            if (!jobId) {
                wizard.categoryPipeline = { status: 'error', error: 'Failed to create progress job' };
                render();
                return;
            }
            var streamUrl = buildApiUrlR('/progress/stream') + '?job_id=' + encodeURIComponent(jobId);
            var es = new EventSource(streamUrl);
            wizard.progressEventSource = es;
            es.addEventListener('step', function(e) {
                if (e.data) {
                    try {
                        var ev = JSON.parse(e.data);
                        wizard.categoryPipelineLog.push(ev);
                        render();
                    } catch (err) {}
                }
            });
            es.onerror = function() {
                closeProgressEventSource();
                render();
            };

            apiFetch('/pipeline/category:detect', {
                method: 'POST',
                body: {
                    draftId: wizard.draftId,
                    description: desc,
                    job_id: jobId,
                    marketplace: MARKETPLACE,
                    lang: locale
                }
            }).then(function(data) {
                closeProgressEventSource();
                wizard.categoryTop10 = data.top10 || [];
                wizard.categorySelected = data.selected || null;
                wizard.categoryDeepseek = data.deepseek || null;
                wizard.categoryTree = data.category_tree || '';
                wizard.categoryDeepseekTree = data.deepseek_tree || null;
                if (!wizard.editedJson || typeof wizard.editedJson !== 'object') wizard.editedJson = {};
                wizard.editedJson.qdrant_top10 = wizard.categoryTop10;
                wizard.editedJson.selected_category = wizard.categorySelected;
                wizard.editedJson.deepseek = wizard.categoryDeepseek;
                wizard.editedJson.category_tree = wizard.categoryTree;
                wizard.editedJson.deepseek_tree = wizard.categoryDeepseekTree;
                wizard.categoryPipeline = { status: 'done' };
                saveDraft(true);
                render();
            }).catch(function(err) {
                closeProgressEventSource();
                wizard.categoryPipeline = { status: 'error', error: (err && err.message) ? err.message : t('pipelineError') };
                render();
            });
        }).catch(function(err) {
            wizard.categoryPipeline = { status: 'error', error: (err && err.message) ? err.message : (locale === 'ru' ? 'Не удалось создать задачу прогресса' : 'Failed to create progress job') };
            render();
        });
    }

    function renderStepper() {
        var container = document.getElementById('emallWizard');
        if (!container) return '';
        var html = '<div class="stepper">';
        wizard.steps.forEach(function(step, index) {
            var isCurrent = index === wizard.currentStepIndex;
            var isCompleted = index < wizard.currentStepIndex;
            html += '<div class="step' + (isCurrent ? ' current' : '') + (isCompleted ? ' completed' : '') + '">';
            html += '<div class="step-circle">' + (isCompleted ? '\u2713' : (index + 1)) + '</div>';
            html += '<div class="step-label">' + t(step.labelKey) + '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function renderStep1Content() {
        var desc = (wizard.editedJson && wizard.editedJson.description) || '';
        var apiKey = (wizard.editedJson && wizard.editedJson.emall_api_key) || '';
        var html = '';
        html += '<p id="emallSavedKeyHint" class="emall-saved-key-hint" style="margin-bottom:8px;color:#666;font-size:14px;"></p>';
        html += '<div class="field-row">';
        html += '<label class="field-label">' + t('apiKeyLabel') + '</label>';
        html += '<input type="password" class="field-input" data-field="emall_api_key" value="' + escapeHtml(apiKey) + '" placeholder="" autocomplete="off">';
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
    }

    function renderStep2Content() {
        var top10 = wizard.categoryTop10 || [];
        var selected = wizard.categorySelected || (top10.length ? top10[0] : null);
        var html = '<div class="category-step">';
        html += '<h3>' + t('categoryWeDetected') + '</h3>';
        if (!selected) {
            html += '<p>' + (locale === 'ru' ? 'Сначала заполните описание на шаге 1 и запустите пайплайн.' : 'Fill description on step 1 and run the pipeline first.') + '</p>';
            html += '</div>';
            return html;
        }
        var second = (selected.type_name || selected.path || '');
        var label = (selected.title_cat || '') + (selected.title_cat && second ? ' \u2192 ' : '') + second || ('id ' + (selected.category_id || selected.id_embedding || selected.db_id || ''));
        html += '<p class="category-selected-main">' + escapeHtml(label) + '</p>';
        html += '<p class="category-hint">' + t('categoryManualHint') + '</p>';
        html += '</div>';
        return html;
    }

    function renderImagesGrid() {
        var grid = document.getElementById('imagesGrid');
        if (!grid) return;
        grid.innerHTML = '';
        (wizard.images || []).forEach(function(image) {
            var item = document.createElement('div');
            item.className = 'image-item';
            if (image.url) {
                var img = document.createElement('img');
                img.src = image.url;
                img.alt = image.imageId;
                item.appendChild(img);
            } else {
                var placeholder = document.createElement('div');
                placeholder.className = 'image-placeholder';
                placeholder.textContent = image.imageId;
                item.appendChild(placeholder);
            }
            var deleteBtn = document.createElement('button');
            deleteBtn.className = 'image-delete';
            deleteBtn.textContent = t('delete');
            deleteBtn.onclick = function() { deleteImage(image.imageId); };
            item.appendChild(deleteBtn);
            grid.appendChild(item);
        });
    }

    function render() {
        var container = document.getElementById('emallWizard');
        if (!container) return;

        if (wizard.pendingDraft) {
            var stepNum = resolveDraftStep(wizard.pendingDraft);
            var stepLabel = wizard.steps[stepNum - 1] ? t(wizard.steps[stepNum - 1].labelKey) : stepNum;
            var updatedAt = wizard.pendingDraft.updatedAt || wizard.pendingDraft.updated_at;
            var dateStr = formatDraftDate(updatedAt);
            var msg = t('draftFound').replace('{date}', dateStr) + ' ' + t('draftFoundStep').replace('{step}', stepNum + ' – ' + stepLabel);
            container.innerHTML = '<div class="draft-restore-modal"><p>' + escapeHtml(msg) + '</p><div class="draft-restore-buttons"><button type="button" class="btn btn-primary" id="draftContinueBtn">' + t('continueDraft') + '</button> <button type="button" class="btn btn-secondary" id="draftResetBtn">' + t('resetDraft') + '</button></div></div>';
            var continueBtn = document.getElementById('draftContinueBtn');
            var resetBtn = document.getElementById('draftResetBtn');
            if (continueBtn) continueBtn.onclick = function() { applyDraftToWizard(wizard.pendingDraft); };
            if (resetBtn) {
                resetBtn.onclick = function() {
                    apiFetch('/draft/reset', { method: 'POST', body: { marketplace: MARKETPLACE } })
                        .then(function(data) {
                            applyDraftToWizard(data);
                            wizard.images = [];
                            wizard.currentStepIndex = 0;
                            wizard.categoryPipeline = null;
                            wizard.categoryTop10 = [];
                            wizard.categorySelected = null;
                            wizard.editedJson = wizard.editedJson || {};
                            wizard.pendingDraft = null;
                            render();
                        })
                        .catch(function(err) {
                            showStatus('error', (err && err.message) || t('error'));
                            render();
                        });
                };
            }
            return;
        }

        var html = renderStepper();
        html += '<div class="wizard-two-col"><div class="wizard-form">';
        html += '<div class="step-content">';
        if (wizard.currentStepIndex === 0) {
            html += renderStep1Content();
        } else {
            html += renderStep2Content();
        }
        html += '</div>';

        html += '<div class="buttons-row">';
        if (wizard.currentStepIndex === 0) {
            var cp = wizard.categoryPipeline;
            var label = !cp || cp.status === 'idle' ? t('startPipeline') : cp.status === 'running' ? (locale === 'ru' ? 'Пайплайн запускается...' : 'Pipeline running...') : cp.status === 'done' ? t('goToStep2') : cp.status === 'error' ? (locale === 'ru' ? 'Повторить пайплайн' : 'Retry pipeline') : t('startPipeline');
            var disabledAttr = (cp && cp.status === 'running') ? ' disabled' : '';
            html += '<div></div><div><button type="button" class="btn btn-primary" id="primaryStep1Btn"' + disabledAttr + '>' + label + '</button></div>';
        } else {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            html += '<div><button class="btn btn-primary" id="continueCategoryBtn">' + t('continue') + '</button></div>';
        }
        html += '</div>';
        if (wizard.savingState) {
            html += '<div class="status-area status-' + wizard.savingState.type + '">' + escapeHtml(wizard.savingState.message) + '</div>';
        }
        html += '</div>';
        html += '<div class="pipeline-panel"><h3>' + t('pipelineProgress') + '</h3><div id="pipelineStatusContent">' + renderPipelineContent() + '</div></div>';
        html += '</div>';

        container.innerHTML = html;
        attachEventHandlers();
        renderImagesGrid();
        setupImageHandlers();
        if (wizard.currentStepIndex === 0) {
            loadEmallSavedKeyHint();
        }
    }

    function loadEmallSavedKeyHint() {
        var el = document.getElementById('emallSavedKeyHint');
        if (!el) return;
        apiFetch('/me/emall-credentials')
            .then(function(data) {
                if (data && data.apiKeyMasked) {
                    el.textContent = (locale === 'ru' ? 'В кабинете сохранён ключ: ' : 'Saved key in cabinet: ') + data.apiKeyMasked;
                }
            })
            .catch(function() {});
    }

    function attachEventHandlers() {
        var inputs = document.querySelectorAll('.field-input[data-field], .field-textarea[data-field]');
        inputs.forEach(function(input) {
            var ev = input.tagName === 'TEXTAREA' ? 'input' : 'change';
            input.addEventListener(ev, function() {
                var fieldKey = input.getAttribute('data-field');
                wizard.editedJson[fieldKey] = input.value;
                scheduleAutosave();
            });
        });

        var primaryStep1Btn = document.getElementById('primaryStep1Btn');
        if (primaryStep1Btn) {
            primaryStep1Btn.onclick = function() {
                var cp = wizard.categoryPipeline;
                if (cp && cp.status === 'done' && wizard.steps.length > 1) {
                    wizard.currentStepIndex = 1;
                    saveDraft(true);
                    render();
                    return;
                }
                saveDraft(true);
                setTimeout(startCategoryPipeline, 300);
            };
        }

        var prevBtn = document.getElementById('prevBtn');
        if (prevBtn) prevBtn.onclick = function() {
            if (wizard.currentStepIndex > 0) {
                wizard.currentStepIndex--;
                render();
            }
        };

        var continueCategoryBtn = document.getElementById('continueCategoryBtn');
        if (continueCategoryBtn) {
            continueCategoryBtn.onclick = function() {
                showStatus('saved', locale === 'ru' ? 'Следующие шаги появятся позже' : 'Next steps will be available later');
            };
        }
    }

    function setupImageHandlers() {
        var uploadArea = document.getElementById('uploadArea');
        var fileInput = document.getElementById('fileInput');
        var urlInput = document.getElementById('urlInput');
        var urlButton = document.getElementById('urlButton');

        if (uploadArea && fileInput) {
            uploadArea.onclick = function() { fileInput.click(); };
            uploadArea.ondragover = function(e) { e.preventDefault(); uploadArea.classList.add('dragover'); };
            uploadArea.ondragleave = function() { uploadArea.classList.remove('dragover'); };
            uploadArea.ondrop = function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) uploadImage(e.dataTransfer.files[0]);
            };
            fileInput.onchange = function() {
                if (fileInput.files.length > 0) uploadImage(fileInput.files[0]);
            };
        }
        if (urlButton && urlInput) {
            urlButton.onclick = function() {
                var url = urlInput.value.trim();
                if (url) {
                    loadImageFromUrl(url);
                    urlInput.value = '';
                }
            };
        }
    }

    function uploadImage(file) {
        var formData = new FormData();
        formData.append('file', file);
        showStatus('uploading', t('uploading'));
        var uploadPath = buildApiUrlR('/drafts/' + wizard.draftId + '/images:upload');
        var uploadPromise = typeof window.apiFetchJson === 'function'
            ? window.apiFetchJson(uploadPath, { method: 'POST', body: formData })
            : fetch(uploadPath, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function(r) { return r.text(); }).then(function(text) {
                try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON'); }
            });
        uploadPromise.then(function(data) {
            if (data.ok) {
                wizard.images = data.data.images;
                render();
                clearStatus();
            } else {
                showStatus('error', t('uploadError') + ': ' + (data.error && data.error.message || 'Unknown error'));
            }
        }).catch(function(err) {
            showStatus('error', t('uploadError') + ': ' + (err && err.message || ''));
        });
    }

    function loadImageFromUrl(url) {
        showStatus('uploading', t('uploading'));
        apiFetch('/drafts/' + wizard.draftId + '/images:from-url', { method: 'POST', body: { url: url } })
            .then(function(data) {
                wizard.images = data.images;
                render();
                clearStatus();
            })
            .catch(function(err) {
                showStatus('error', t('uploadError') + ': ' + (err && err.message || ''));
            });
    }

    function deleteImage(imageId) {
        apiFetch('/drafts/' + wizard.draftId + '/images/' + imageId, { method: 'DELETE' })
            .then(function(data) {
                wizard.images = data.images;
                render();
            })
            .catch(function(err) {
                showStatus('error', t('error') + ': ' + (err && err.message || ''));
            });
    }

    function scheduleAutosave() {
        if (wizard.autosaveTimer) clearTimeout(wizard.autosaveTimer);
        wizard.autosaveTimer = setTimeout(function() { saveDraft(true); }, 800);
    }

    function saveDraft(silent) {
        if (!wizard.draftId) return;
        if (!silent) showStatus('saving', t('saving'));
        var patch = {
            description: (wizard.editedJson && wizard.editedJson.description) || '',
            current_step: wizard.currentStepIndex + 1,
            step_state: buildStepState()
        };
        if (wizard.categorySelected && typeof wizard.categorySelected === 'object') {
            patch.chosen_category = wizard.categorySelected;
        }
        apiFetch('/draft', {
            method: 'POST',
            body: { marketplace: MARKETPLACE, patch: patch }
        }).then(function() {
            if (!silent) {
                showStatus('saved', t('saved'));
                setTimeout(clearStatus, 2000);
            }
        }).catch(function(err) {
            showStatus('error', t('error') + ': ' + (err && err.message || ''));
        });
    }

    function showStatus(type, message) {
        wizard.savingState = { type: type, message: message };
        render();
    }

    function clearStatus() {
        wizard.savingState = null;
        render();
    }

    function init() {
        apiFetch('/draft?marketplace=' + encodeURIComponent(MARKETPLACE))
            .then(function(data) {
                wizard.editedJson = data.editedJson && typeof data.editedJson === 'object' ? data.editedJson : {};
                if (typeof data.description === 'string') wizard.editedJson.description = data.description;
                wizard.pendingDraft = data;
                render();
            })
            .catch(function(err) {
                if (err.code === 'NO_DRAFT' || err.status === 406) {
                    return apiFetch('/draft/init', { method: 'POST', body: { marketplace: MARKETPLACE } });
                }
                throw err;
            })
            .then(function(data) {
                if (data && !wizard.pendingDraft) {
                    applyDraftToWizard(data);
                }
            })
            .catch(function(err) {
                var container = document.getElementById('emallWizard');
                if (container) {
                    if (err.code === 'UNAUTHORIZED' || err.code === 'AUTH_REQUIRED') {
                        window.location.href = (getApiBase() || '') + '/login';
                        return;
                    }
                    container.innerHTML = '<div class="status-error">' + t('error') + ': ' + escapeHtml(err.message || '') + '</div>';
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
