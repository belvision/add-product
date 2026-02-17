/* SOURCE: frontend/public/assets/marketplaces/ozon/ozon-wizard.js — EDIT THERE. Legacy copy for URL compatibility. */
(function() {
    'use strict';

    const locale = window.__OZON_LANG__ || 'ru';

    const i18n = {
        ru: {
            saving: 'Сохранение...',
            saved: 'Сохранено',
            error: 'Ошибка',
            next: 'Далее',
            prev: 'Назад',
            save: 'Сохранить',
            publish: 'Опубликовать',
            uploading: 'Загрузка...',
            uploadError: 'Ошибка загрузки',
            delete: 'Удалить',
            dragDrop: 'Перетащите изображения сюда или нажмите для выбора',
            fromUrl: 'Загрузить по URL',
            publishSuccess: 'Товар успешно опубликован! ID: {productId}',
            validationFailed: 'Пожалуйста, исправьте ошибки в форме',
            pipelineProgress: 'Прогресс пайплайна',
            stage: 'Этап',
            progress: 'Прогресс',
            logs: 'Логи',
            startPipeline: 'Запустить пайплайн',
            emailNotVerified: 'Подтвердите email для публикации',
            pipelineStep1: '1/5 DeepSeek строит дерево категорий...',
            pipelineStep2: '2/5 Получаем embedding по дереву категорий...',
            pipelineStep3: '3/5 Ищем 10 ближайших категорий в Qdrant...',
            pipelineStep4: '4/5 Находим названия категорий в базе...',
            pipelineStep5: '5/5 DeepSeek выбирает наиболее подходящую категорию...',
            pipelineIntro: 'Введите описание товара и нажмите «Запустить пайплайн». Здесь по шагам покажем, что происходит.',
            pipelineTop10Title: 'Кандидаты категорий (top10):',
            pipelineSelectedTitle: 'Выбрана категория:',
            goToStep2: 'Перейти к шагу 2',
            categoryWeDetected: 'Мы определили категорию',
            categoryManualHint: 'Проверьте, что эта категория подходит вашему товару.',
            continue: 'Продолжить',
            pipelineError: 'Не удалось запустить пайплайн категории',
            draftFound: 'Найден черновик (обновлён {date}).',
            draftFoundStep: 'Шаг {step}',
            continueDraft: 'Продолжить',
            resetDraft: 'Удалить и начать заново'
        },
        en: {
            saving: 'Saving...',
            saved: 'Saved',
            error: 'Error',
            next: 'Next',
            prev: 'Previous',
            save: 'Save',
            publish: 'Publish',
            uploading: 'Uploading...',
            uploadError: 'Upload error',
            delete: 'Delete',
            dragDrop: 'Drag and drop images here or click to select',
            fromUrl: 'Load from URL',
            publishSuccess: 'Product published successfully! ID: {productId}',
            validationFailed: 'Please fix form errors',
            pipelineProgress: 'Pipeline progress',
            stage: 'Stage',
            progress: 'Progress',
            logs: 'Logs',
            startPipeline: 'Start pipeline',
            emailNotVerified: 'Verify email to publish',
            pipelineStep1: '1/5 DeepSeek builds a category tree...',
            pipelineStep2: '2/5 Getting embedding for the category tree...',
            pipelineStep3: '3/5 Searching 10 nearest categories in Qdrant...',
            pipelineStep4: '4/5 Resolving category names from PostgreSQL...',
            pipelineStep5: '5/5 DeepSeek chooses the best category...',
            pipelineIntro: 'Enter product description and click “Start pipeline” to see detailed steps here.',
            pipelineTop10Title: 'Category candidates (top10):',
            pipelineSelectedTitle: 'Selected category:',
            goToStep2: 'Go to step 2',
            categoryWeDetected: 'We detected a category',
            categoryManualHint: 'Please check that this category fits your product.',
            continue: 'Continue',
            pipelineError: 'Failed to run category pipeline',
            draftFound: 'Draft found (updated {date}).',
            draftFoundStep: 'Step {step}',
            continueDraft: 'Continue',
            resetDraft: 'Delete and start over'
        }
    };

    const emailVerified = typeof window.__OZON_EMAIL_VERIFIED__ !== 'undefined' ? window.__OZON_EMAIL_VERIFIED__ : true;

    function t(key) {
        return i18n[locale][key] || key;
    }

    function getApiBase() {
        const base = (typeof window.__OZON_BASE__ !== 'undefined' && window.__OZON_BASE__ !== null)
            ? window.__OZON_BASE__
            : window.location.pathname.replace(/\/[^\/]*$/, '');
        return (base === '' ? '' : base);
    }

    /**
     * Build URL to API entrypoint using clean prefix-based path:
     *   <base>/api/<route>
     *
     * Example (base="/frontend", route="/drafts"): "/frontend/api/drafts".
     * nginx is responsible for rewriting /frontend/api/* to the actual
     * PHP entrypoint; we only keep the client-side URLs "clean".
     */
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
        // Route everything through helper that builds clean /api/<route> URLs.
        const qPos = path.indexOf('?');
        const route = (qPos === -1 ? path : path.slice(0, qPos));
        const extraQuery = (qPos === -1 ? '' : path.slice(qPos + 1)); // without '?'
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
            try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + (text ? text.substring(0, 80) : 'empty')); }
        });
    }

    const MARKETPLACE = (typeof window.__MARKETPLACE__ === 'string' && window.__MARKETPLACE__) ? window.__MARKETPLACE__ : 'ozon';

    const wizard = {
        marketplace: MARKETPLACE,
        draftId: null,
        formSchema: null,
        editedJson: {},
        images: [],
        currentStepIndex: 0,
        savingState: null,
        fieldErrors: {},
        autosaveTimer: null,
        // Pending draft from GET (show modal before applying)
        pendingDraft: null,
        // Local UX-only pipeline state for category detection on step 1.
        categoryPipeline: null, // { status: 'idle'|'running'|'done'|'error', error?: string }
        categoryPipelineLog: [], // real-time progress events from SSE
        progressEventSource: null,
        categoryTop10: [],
        categorySelected: null,
        categoryDeepseek: null,
        categoryTree: '',
        categoryDeepseekTree: null
    };

    function buildStepState() {
        const state = {};
        if (wizard.images && wizard.images.length) {
            state.images = wizard.images.map(function (img) {
                return { id: img.imageId, url: img.url || null, source: img.source || 'upload' };
            });
        }
        if (wizard.categorySelected) {
            state.category = {
                id: wizard.categorySelected.type_id || wizard.categorySelected.id,
                db_id: wizard.categorySelected.db_id,
                category_id: wizard.categorySelected.category_id,
                id_type: wizard.categorySelected.id_type,
                title_cat: wizard.categorySelected.title_cat || '',
                type_name: wizard.categorySelected.type_name || '',
                name: (wizard.categorySelected.title_cat || '') + ' \u2192 ' + (wizard.categorySelected.type_name || ''),
                score: wizard.categorySelected.score,
                top10: wizard.categoryTop10
            };
        }
        if (wizard.editedJson && typeof wizard.editedJson === 'object' && Object.keys(wizard.editedJson).length > 1) {
            state.attributes = wizard.editedJson;
        }
        return state;
    }

    function applyStepState(stepState) {
        if (!stepState || typeof stepState !== 'object') return;
        if (Array.isArray(stepState.images) && stepState.images.length) {
            wizard.images = stepState.images.map(function (item) {
                return {
                    imageId: item.id || item.imageId,
                    url: item.url || null,
                    source: item.source || 'upload'
                };
            });
        }
        if (stepState.category) {
            var c = stepState.category;
            var titleCat = (c.title_cat !== undefined && c.title_cat !== '') ? c.title_cat : (c.name || '');
            var typeName = (c.type_name !== undefined && c.type_name !== '') ? c.type_name : (c.name || '');
            if (!titleCat && typeName) { titleCat = typeName; typeName = ''; }
            if ((!typeName || typeName === titleCat) && titleCat && titleCat.indexOf(' \u2192 ') !== -1) {
                var parts = titleCat.split(' \u2192 ');
                titleCat = parts[0] ? parts[0].trim() : titleCat;
                typeName = parts[1] ? parts[1].trim() : '';
            }
            var hasId = !!(c.id || c.type_id || c.db_id);
            var hasName = !!(titleCat || typeName || (c.name && c.name.trim()));
            wizard.categorySelected = (hasId || hasName) ? {
                type_id: c.id || c.type_id,
                id: c.id || c.type_id,
                db_id: c.db_id,
                category_id: c.category_id,
                id_type: c.id_type,
                title_cat: titleCat,
                type_name: typeName,
                score: c.score
            } : null;
            if (stepState.category.top10) wizard.categoryTop10 = stepState.category.top10;
        }
        if (stepState.attributes && typeof stepState.attributes === 'object') {
            wizard.editedJson = Object.assign({}, wizard.editedJson, stepState.attributes);
        }
    }

    /**
     * Resolve current step from draft (source of truth for restore).
     * If draft.current_step >= 2 → use it; else if chosen_category or editedJson.selected_category filled → 2;
     * else if step_state.category filled (legacy/fallback) → 2; else 1.
     */
    function resolveDraftStep(draft) {
        var step = typeof draft.current_step === 'number' && draft.current_step >= 2 ? draft.current_step : 0;
        if (step >= 2) return step;
        if (draft.editedJson && draft.editedJson.selected_category && typeof draft.editedJson.selected_category === 'object' && Object.keys(draft.editedJson.selected_category).length > 0) return 2;
        var cc = draft.chosen_category;
        if (cc && typeof cc === 'object' && Object.keys(cc).length > 0) return 2;
        var sc = draft.step_state && draft.step_state.category;
        if (sc && typeof sc === 'object' && (sc.id || sc.name || Object.keys(sc).length > 0)) return 2;
        return 1;
    }

    /**
     * Navigate to step (1-based), update stepper UI and content.
     */
    function goToStep(step1Based) {
        wizard.currentStepIndex = Math.max(0, (step1Based || 1) - 1);
        render();
    }

    /**
     * Apply restored draft state to wizard and show the resolved step.
     * Must be used after wizard has loaded state from draft (e.g. on "Continue" or after init).
     */
    function applyDraftToWizard(draft) {
        wizard.draftId = draft.id || draft.draftId;
        wizard.marketplace = MARKETPLACE;
        wizard.formSchema = draft.formSchema || wizard.formSchema;
        wizard.editedJson = draft.editedJson && typeof draft.editedJson === 'object' ? draft.editedJson : {};
        if (typeof draft.description === 'string') {
            wizard.editedJson.description = draft.description;
        }
        wizard.images = (draft.step_state && Array.isArray(draft.step_state.images) && draft.step_state.images.length)
            ? draft.step_state.images.map(function (item) {
                return { imageId: item.id || item.imageId, url: item.url || null, source: item.source || 'upload' };
            })
            : (draft.images && Array.isArray(draft.images) && draft.images.length)
                ? draft.images
                : [];
        wizard.chosenCategory = draft.chosen_category && typeof draft.chosen_category === 'object' ? draft.chosen_category : null;
        wizard.stepState = draft.step_state && typeof draft.step_state === 'object' ? draft.step_state : {};
        applyStepState(draft.step_state);
        if (wizard.chosenCategory && !wizard.categorySelected) {
            wizard.categorySelected = wizard.chosenCategory;
        }
        if (!wizard.categorySelected && draft.step_state && draft.step_state.category) {
            var cat = draft.step_state.category;
            var tCat = (cat.title_cat !== undefined && cat.title_cat !== '') ? cat.title_cat : (cat.name || '');
            var tName = (cat.type_name !== undefined && cat.type_name !== '') ? cat.type_name : (cat.name || '');
            if (!tCat && tName) { tCat = tName; tName = ''; }
            if ((!tName || tName === tCat) && tCat && tCat.indexOf(' \u2192 ') !== -1) {
                var parts = tCat.split(' \u2192 ');
                tCat = parts[0] ? parts[0].trim() : tCat;
                tName = parts[1] ? parts[1].trim() : '';
            }
            var hasId = !!(cat.id || cat.type_id || cat.db_id);
            var hasName = !!(tCat || tName || (cat.name && cat.name.trim()));
            wizard.categorySelected = (hasId || hasName) ? {
                type_id: cat.id || cat.type_id,
                id: cat.id || cat.type_id,
                db_id: cat.db_id,
                category_id: cat.category_id,
                id_type: cat.id_type,
                title_cat: tCat,
                type_name: tName,
                score: cat.score
            } : null;
        }
        if (draft.step_state && draft.step_state.category && draft.step_state.category.top10) {
            wizard.categoryTop10 = draft.step_state.category.top10;
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

    function buildDeepseekDiagnostic(deepseek) {
        if (!deepseek || !deepseek.status) {
            return '';
        }
        var status = deepseek.status;
        var http = typeof deepseek.http_status === 'number' && deepseek.http_status > 0
            ? 'HTTP ' + deepseek.http_status
            : null;

        if (locale === 'ru') {
            if (status === 'called') {
                return 'DeepSeek: вызван' + (http ? ' (' + http + ')' : '');
            }
            if (status === 'skipped') {
                return 'DeepSeek: skipped (not configured)';
            }
            if (status === 'failed' || status === 'fallback') {
                return 'DeepSeek: failed' + (http ? ' (' + http + ', fallback used)' : ' (fallback used)');
            }
            return 'DeepSeek: ' + status;
        } else {
            if (status === 'called') {
                return 'DeepSeek: called' + (http ? ' (' + http + ')' : '');
            }
            if (status === 'skipped') {
                return 'DeepSeek: skipped (not configured)';
            }
            if (status === 'failed' || status === 'fallback') {
                return 'DeepSeek: failed' + (http ? ' (' + http + ', fallback used)' : ' (fallback used)');
            }
            return 'DeepSeek: ' + status;
        }
    }

    function renderPipelineContent() {
        const cp = wizard.categoryPipeline;
        const log = wizard.categoryPipelineLog || [];
        const isRunning = cp && cp.status === 'running';

        if (!cp || cp.status === 'idle') {
            return '<p>' + t('pipelineIntro') + '</p>' +
                '<ul class="pipeline-steps">' +
                '<li>' + t('pipelineStep1') + '</li>' +
                '<li>' + t('pipelineStep2') + '</li>' +
                '<li>' + t('pipelineStep3') + '</li>' +
                '<li>' + t('pipelineStep4') + '</li>' +
                '<li>' + t('pipelineStep5') + '</li>' +
                '</ul>';
        }

        if (cp.status === 'error') {
            let h = '<p class="status-error">' + escapeHtml(cp.error || t('pipelineError')) + '</p>';
            if (log.length) {
                h += '<div class="pipeline-log-title">' + (locale === 'ru' ? 'Ход обработки' : 'Processing log') + '</div>';
                h += '<ul class="pipeline-log">';
                log.forEach(function (ev) {
                    h += '<li class="pipeline-log-entry ' + (ev.level || 'info') + '">' + escapeHtml(ev.message || '') + '</li>';
                });
                h += '</ul>';
            }
            return h;
        }

        let h = '';
        if (log.length > 0 || isRunning) {
            h += '<div class="pipeline-log-title">' + (locale === 'ru' ? 'Ход обработки' : 'Processing log') + '</div>';
            if (isRunning) {
                h += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
            }
            h += '<ul class="pipeline-log">';
            log.forEach(function (ev) {
                const level = ev.level || 'info';
                const msg = ev.message || '';
                const meta = ev.meta || {};
                let metaStr = '';
                if (level === 'success' && (meta.ms !== undefined || meta.model)) {
                    const parts = [];
                    if (meta.ms !== undefined) parts.push(meta.ms + ' ms');
                    if (meta.model) parts.push(escapeHtml(String(meta.model)));
                    if (parts.length) metaStr = ' <span class="pipeline-log-meta">(' + parts.join(', ') + ')</span>';
                }
                if (meta.scores && Array.isArray(meta.scores) && meta.scores.length) {
                    metaStr = ' <span class="pipeline-log-meta">(' + (locale === 'ru' ? 'scores: ' : 'scores: ') + meta.scores.slice(0, 5).join(', ') + ')</span>';
                }
                h += '<li class="pipeline-log-entry ' + escapeHtml(level) + '">' + escapeHtml(msg) + metaStr + '</li>';
            });
            h += '</ul>';
        }

        h += '<ul class="pipeline-steps">';
        h += '<li>' + t('pipelineStep1') + '</li>';
        h += '<li>' + t('pipelineStep2') + '</li>';
        h += '<li>' + t('pipelineStep3') + '</li>';
        h += '<li>' + t('pipelineStep4') + '</li>';
        h += '<li>' + t('pipelineStep5') + '</li>';
        h += '</ul>';

        // Show category tree used for embedding (DeepSeek or fallback).
        if (wizard.categoryTree) {
            const title = (locale === 'ru'
                ? 'Дерево категорий для embedding (DeepSeek): '
                : 'Category tree for embedding (DeepSeek): ');
            h += '<p class="category-tree-line"><strong>' + title + '</strong>' + escapeHtml(wizard.categoryTree) + '</p>';
        }

        // Explicit DeepSeek status line (final category choice) after steps.
        if (wizard.categoryDeepseek) {
            const diag = buildDeepseekDiagnostic(wizard.categoryDeepseek);
            if (diag) {
                h += '<p class="deepseek-status"><strong>' + (locale === 'ru' ? 'Статус DeepSeek: ' : 'DeepSeek status: ') + '</strong>' + escapeHtml(diag) + '</p>';
                if (wizard.categoryDeepseek.error) {
                    h += '<p class="deepseek-error">' + escapeHtml(String(wizard.categoryDeepseek.error)) + '</p>';
                }
            }
        }

        const top10 = wizard.categoryTop10 || [];
        if (top10.length) {
            h += '<div class="pipeline-top10"><strong>' + t('pipelineTop10Title') + '</strong><ol>';
            top10.forEach(function (item, idx) {
                const title = (item.title_cat || '') + ' \u2192 ' + (item.type_name || '');
                const score = (typeof item.score === 'number') ? item.score.toFixed(3) : item.score;
                h += '<li>' + (idx + 1) + ') ' + title + ' (score: ' + score + ')</li>';
            });
            h += '</ol></div>';
        }

        if (wizard.categorySelected) {
            const sel = wizard.categorySelected;
            const label = (sel.title_cat || '') + ' \u2192 ' + (sel.type_name || '');
            h += '<p class="pipeline-selected"><strong>' + t('pipelineSelectedTitle') + '</strong> ' + label + '</p>';
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
        const desc = (wizard.editedJson && typeof wizard.editedJson.description === 'string')
            ? wizard.editedJson.description
            : '';
        if (!desc) {
            showStatus('error', locale === 'ru'
                ? 'Сначала введите описание товара на шаге 1'
                : 'Please enter product description on step 1 first');
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

        apiFetch('/progress/create', { method: 'POST' }).then(function (createData) {
            const jobId = createData && createData.job_id ? createData.job_id : null;
            if (!jobId) {
                wizard.categoryPipeline = { status: 'error', error: 'Failed to create progress job' };
                render();
                return;
            }
            var streamUrl = buildApiUrlR('/progress/stream') + '?job_id=' + encodeURIComponent(jobId);
            var es = new EventSource(streamUrl);
            wizard.progressEventSource = es;
            es.addEventListener('step', function (e) {
                if (e.data) {
                    try {
                        var ev = JSON.parse(e.data);
                        wizard.categoryPipelineLog.push(ev);
                        render();
                    } catch (err) {}
                }
            });
            es.onerror = function () {
                closeProgressEventSource();
                render();
            };

            var detectBody = {
                draftId: wizard.draftId,
                job_id: jobId,
                lang: locale
            };
            // WAF (ModSecurity CRS 941120) may block long description as XSS.
            (function() {
                try {
                    var utf8 = unescape(encodeURIComponent(desc || ''));
                    var b64 = btoa(utf8).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
                    detectBody.description_b64 = b64;
                } catch (e) {
                    detectBody.description = desc;
                }
            })();
            apiFetch('/pipeline/category-detect', {
                method: 'POST',
                body: detectBody
            }).then(function (data) {
                closeProgressEventSource();
                wizard.categoryTop10 = data.top10 || [];
                wizard.categorySelected = data.selected || null;
                wizard.categoryDeepseek = data.deepseek || null;
                wizard.categoryTree = data.category_tree || '';
                wizard.categoryDeepseekTree = data.deepseek_tree || null;
                if (!wizard.editedJson || typeof wizard.editedJson !== 'object') {
                    wizard.editedJson = {};
                }
                wizard.editedJson.qdrant_top10 = wizard.categoryTop10;
                wizard.editedJson.selected_category = wizard.categorySelected;
                wizard.editedJson.deepseek = wizard.categoryDeepseek;
                wizard.editedJson.category_tree = wizard.categoryTree;
                wizard.editedJson.deepseek_tree = wizard.categoryDeepseekTree;
                wizard.categoryPipeline = { status: 'done' };
                saveDraft(true);
                render();
            }).catch(function (err) {
                closeProgressEventSource();
                wizard.categoryPipeline = {
                    status: 'error',
                    error: err && err.message ? err.message : t('pipelineError')
                };
                render();
            });
        }).catch(function (err) {
            wizard.categoryPipeline = {
                status: 'error',
                error: err && err.message ? err.message : (locale === 'ru' ? 'Не удалось создать задачу прогресса' : 'Failed to create progress job')
            };
            render();
        });
    }

    function renderStepper() {
        const container = document.getElementById('ozonWizard');
        if (!wizard.formSchema || !wizard.formSchema.steps) return;

        let html = '<div class="stepper">';
        wizard.formSchema.steps.forEach(function(step, index) {
            const isCurrent = index === wizard.currentStepIndex;
            const isCompleted = index < wizard.currentStepIndex;
            html += '<div class="step' + (isCurrent ? ' current' : '') + (isCompleted ? ' completed' : '') + '">';
            html += '<div class="step-circle">' + (isCompleted ? '✓' : (index + 1)) + '</div>';
            html += '<div class="step-label">' + (step.label[locale] || step.key) + '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function validateField(field, value) {
        const errors = [];

        if (field.required && (value === null || value === '' || (Array.isArray(value) && value.length === 0))) {
            errors.push(t('error') + ': ' + (field.label[locale] || field.key) + ' ' + (locale === 'ru' ? 'обязательно' : 'is required'));
            return errors;
        }

        if (value === null || value === '') return errors;

        if (field.type === 'string' && typeof value === 'string') {
            if (field.constraints) {
                if (field.constraints.minLen && value.length < field.constraints.minLen) {
                    errors.push((locale === 'ru' ? 'Минимум' : 'Minimum') + ' ' + field.constraints.minLen + ' ' + (locale === 'ru' ? 'символов' : 'characters'));
                }
                if (field.constraints.maxLen && value.length > field.constraints.maxLen) {
                    errors.push((locale === 'ru' ? 'Максимум' : 'Maximum') + ' ' + field.constraints.maxLen + ' ' + (locale === 'ru' ? 'символов' : 'characters'));
                }
            }
        }

        if (field.type === 'image_list' && Array.isArray(value)) {
            if (field.constraints) {
                if (field.constraints.minItems && value.length < field.constraints.minItems) {
                    errors.push((locale === 'ru' ? 'Минимум' : 'Minimum') + ' ' + field.constraints.minItems + ' ' + (locale === 'ru' ? 'изображений' : 'images'));
                }
                if (field.constraints.maxItems && value.length > field.constraints.maxItems) {
                    errors.push((locale === 'ru' ? 'Максимум' : 'Maximum') + ' ' + field.constraints.maxItems + ' ' + (locale === 'ru' ? 'изображений' : 'images'));
                }
            }
        }

        return errors;
    }

    function validateCurrentStep() {
        if (!wizard.formSchema || !wizard.formSchema.steps[wizard.currentStepIndex]) return true;

        const step = wizard.formSchema.steps[wizard.currentStepIndex];
        const errors = {};
        let isValid = true;

        step.fields.forEach(function(field) {
            const value = field.type === 'image_list' ? wizard.images : wizard.editedJson[field.key];
            const fieldErrors = validateField(field, value);
            if (fieldErrors.length > 0) {
                errors[field.key] = fieldErrors[0];
                isValid = false;
            }
        });

        wizard.fieldErrors = Object.assign({}, wizard.fieldErrors, errors);
        return isValid;
    }

    function renderField(field) {
        const value = field.type === 'image_list' ? wizard.images : (wizard.editedJson[field.key] || '');
        const error = wizard.fieldErrors[field.key];
        const fieldLabel = field.label[locale] || field.key;

        let html = '<div class="field-row">';
        html += '<label class="field-label">' + fieldLabel;
        if (field.required) {
            html += '<span class="required">*</span>';
        }
        html += '</label>';

        if (field.type === 'string') {
            if (field.textarea) {
                html += '<textarea class="field-input field-textarea" data-field="' + field.key + '">' + (value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</textarea>';
            } else {
                html += '<input type="text" class="field-input" data-field="' + field.key + '" value="' + (value || '').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '">';
            }
        } else if (field.type === 'select') {
            html += '<select class="field-input" data-field="' + field.key + '">';
            html += '<option value="">' + (locale === 'ru' ? 'Выберите...' : 'Select...') + '</option>';
            if (field.options) {
                field.options.forEach(function(opt) {
                    html += '<option value="' + opt.value + '"' + (value === opt.value ? ' selected' : '') + '>' + (opt.label[locale] || opt.value) + '</option>';
                });
            }
            html += '</select>';
        } else if (field.type === 'image_list') {
            html += '<div class="images-upload-area" id="uploadArea">';
            html += '<p>' + t('dragDrop') + '</p>';
            html += '<input type="file" id="fileInput" accept="image/*" multiple style="display:none">';
            html += '</div>';
            html += '<div class="images-url-input">';
            html += '<input type="text" id="urlInput" placeholder="https://example.com/image.jpg">';
            html += '<button class="btn btn-secondary" id="urlButton">' + t('fromUrl') + '</button>';
            html += '</div>';
            html += '<div class="images-grid" id="imagesGrid"></div>';
        }

        if (error) {
            html += '<div class="field-error">' + error + '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderImagesGrid() {
        const grid = document.getElementById('imagesGrid');
        if (!grid) return;

        grid.innerHTML = '';
        wizard.images.forEach(function(image) {
            const item = document.createElement('div');
            item.className = 'image-item';
            
            if (image.url) {
                const img = document.createElement('img');
                img.src = image.url;
                img.alt = image.imageId;
                item.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'image-placeholder';
                placeholder.textContent = image.imageId;
                item.appendChild(placeholder);
            }

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'image-delete';
            deleteBtn.textContent = t('delete');
            deleteBtn.onclick = function() {
                deleteImage(image.imageId);
            };
            item.appendChild(deleteBtn);

            grid.appendChild(item);
        });
    }

    function render() {
        const container = document.getElementById('ozonWizard');
        if (!container) return;

        if (wizard.pendingDraft) {
            const stepNum = resolveDraftStep(wizard.pendingDraft);
            const stepLabel = wizard.formSchema && wizard.formSchema.steps && wizard.formSchema.steps[stepNum - 1]
                ? wizard.formSchema.steps[stepNum - 1].label[locale] || stepNum
                : stepNum;
            const updatedAt = wizard.pendingDraft.updatedAt || wizard.pendingDraft.updated_at;
            const dateStr = formatDraftDate(updatedAt);
            const msg = t('draftFound').replace('{date}', dateStr) + ' ' + t('draftFoundStep').replace('{step}', stepNum + ' – ' + stepLabel);
            container.innerHTML =
                '<div class="draft-restore-modal">' +
                '<p>' + escapeHtml(msg) + '</p>' +
                '<div class="draft-restore-buttons">' +
                '<button type="button" class="btn btn-primary" id="draftContinueBtn">' + t('continueDraft') + '</button> ' +
                '<button type="button" class="btn btn-secondary" id="draftResetBtn">' + t('resetDraft') + '</button>' +
                '</div></div>';
            const continueBtn = document.getElementById('draftContinueBtn');
            const resetBtn = document.getElementById('draftResetBtn');
            if (continueBtn) {
                continueBtn.onclick = function () {
                    applyDraftToWizard(wizard.pendingDraft);
                };
            }
            if (resetBtn) {
                resetBtn.onclick = function () {
                    apiFetch('/draft/reset', { method: 'POST', body: { marketplace: MARKETPLACE } })
                        .then(function (data) {
                            applyDraftToWizard(data);
                            wizard.images = [];
                            wizard.currentStepIndex = 0;
                            wizard.categoryPipeline = null;
                            wizard.categoryTop10 = [];
                            wizard.categorySelected = null;
                            wizard.categoryDeepseek = null;
                            wizard.categoryTree = '';
                            wizard.editedJson = wizard.editedJson || {};
                            wizard.pendingDraft = null;
                            render();
                        })
                        .catch(function (err) {
                            showStatus('error', (err && err.message) || t('error'));
                            render();
                        });
                };
            }
            return;
        }

        if (!wizard.formSchema) return;

        let html = renderStepper();

        const step = wizard.formSchema.steps[wizard.currentStepIndex];
        html += '<div class="wizard-two-col">';
        html += '<div class="wizard-form">';
        if (step) {
            html += '<div class="step-content">';
            if (step.key === 'category') {
                html += renderCategoryStep();
            } else {
                step.fields.forEach(function(field) {
                    html += renderField(field);
                });
            }
            html += '</div>';
        }

        html += '<div class="buttons-row">';
        // Step 0: single smart button instead of Save/Next/Start pipeline.
        if (wizard.currentStepIndex === 0) {
            html += '<div></div>';
            html += '<div>';
            var cp = wizard.categoryPipeline;
            var label;
            var disabledAttr = '';
            if (!cp || cp.status === 'idle') {
                label = t('startPipeline');
            } else if (cp.status === 'running') {
                label = (locale === 'ru'
                    ? 'Пайплайн запускается...'
                    : 'Pipeline is running...');
                disabledAttr = ' disabled';
            } else if (cp.status === 'done') {
                label = t('goToStep2');
            } else if (cp.status === 'error') {
                label = (locale === 'ru'
                    ? 'Повторить пайплайн'
                    : 'Retry pipeline');
            } else {
                label = t('startPipeline');
            }
            html += '<button type="button" class="btn btn-primary" id="primaryStep1Btn"' + disabledAttr + '>' + label + '</button>';
            html += '</div>';
        } else if (step && step.key === 'category') {
            // On category step, show ONLY one primary button to continue.
            html += '<div></div>';
            html += '<div>';
            html += '<button class="btn btn-primary" id="continueCategoryBtn">' + t('continue') + '</button>';
            html += '</div>';
        } else {
            if (wizard.currentStepIndex > 0) {
                html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            } else {
                html += '<div></div>';
            }
            html += '<div>';
            html += '<button class="btn btn-secondary" id="saveBtn">' + t('save') + '</button>';
            if (wizard.currentStepIndex < wizard.formSchema.steps.length - 1) {
                html += '<button class="btn btn-primary" id="nextBtn">' + t('next') + '</button>';
            } else {
                html += '<button class="btn btn-primary" id="publishBtn"' + (emailVerified ? '' : ' disabled title="' + t('emailNotVerified') + '"') + '>' + t('publish') + '</button>';
            }
            html += '</div>';
        }
        html += '</div>';
        if (wizard.savingState) {
            html += '<div class="status-area status-' + wizard.savingState.type + '">' + wizard.savingState.message + '</div>';
        }
        html += '</div>';
        html += '<div class="pipeline-panel">';
        html += '<h3>' + t('pipelineProgress') + '</h3>';
        html += '<div id="pipelineStatusContent">' + renderPipelineContent() + '</div>';
        html += '</div>';
        html += '</div>';

        container.innerHTML = html;

        attachEventHandlers();
        if (step && step.fields.some(function(f) { return f.type === 'image_list'; })) {
            renderImagesGrid();
            setupImageHandlers();
        }
    }

    function attachEventHandlers() {
        const inputs = document.querySelectorAll('.field-input[data-field]');
        inputs.forEach(function(input) {
            var ev = input.tagName === 'TEXTAREA' ? 'input' : 'change';
            input.addEventListener(ev, function() {
                const fieldKey = input.getAttribute('data-field');
                wizard.editedJson[fieldKey] = input.value;
                delete wizard.fieldErrors[fieldKey];
                scheduleAutosave();
            });
        });

        const primaryStep1Btn = document.getElementById('primaryStep1Btn');
        if (primaryStep1Btn) {
            primaryStep1Btn.onclick = function() {
                const cp = wizard.categoryPipeline;
                // After successful pipeline, save step 2 + category and move to category step.
                if (cp && cp.status === 'done' && wizard.formSchema && wizard.formSchema.steps.length > 1) {
                    wizard.currentStepIndex = 1;
                    saveDraft(true);
                    render();
                    return;
                }
                // Otherwise (idle or error) — (re)run pipeline.
                saveDraft(true);
                setTimeout(startCategoryPipeline, 300);
            };
        }

        const prevBtn = document.getElementById('prevBtn');
        if (prevBtn) {
            prevBtn.onclick = function() {
                if (wizard.currentStepIndex > 0) {
                    wizard.currentStepIndex--;
                    render();
                }
            };
        }

        const nextBtn = document.getElementById('nextBtn');
        if (nextBtn) {
            nextBtn.onclick = function() {
                if (validateCurrentStep()) {
                    wizard.currentStepIndex++;
                    render();
                } else {
                    render();
                }
            };
        }

        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.onclick = saveDraft;
        }

        const publishBtn = document.getElementById('publishBtn');
        if (publishBtn) {
            publishBtn.onclick = publishDraft;
        }

        const continueCategoryBtn = document.getElementById('continueCategoryBtn');
        if (continueCategoryBtn) {
            continueCategoryBtn.onclick = function () {
                showStatus('saved', locale === 'ru'
                    ? 'Следующие шаги появятся позже'
                    : 'Next steps will be available later');
            };
        }
    }

    function renderCategoryStep() {
        const top10 = wizard.categoryTop10 || [];
        const selected = wizard.categorySelected || (top10.length ? top10[0] : null);

        if (selected && !wizard.categorySelected) {
            wizard.categorySelected = selected;
        }

        let html = '';
        html += '<div class="category-step">';

        // Dynamic headline based on DeepSeek status.
        const ds = wizard.categoryDeepseek;
        let headline;
        if (ds && ds.status === 'called') {
            headline = (locale === 'ru'
                ? 'DeepSeek выбрал категорию'
                : 'DeepSeek chose a category');
        } else if (ds && ds.status === 'skipped') {
            // DeepSeek not configured — fallback to nearest category.
            headline = (locale === 'ru'
                ? 'DeepSeek не настроен — выбрана ближайшая категория автоматически'
                : 'DeepSeek is not configured — selected nearest category automatically');
        } else if (ds && (ds.status === 'failed' || ds.status === 'fallback')) {
            // DeepSeek failed/unavailable — fallback to nearest category.
            headline = (locale === 'ru'
                ? 'DeepSeek недоступен — выбрана ближайшая категория автоматически'
                : 'DeepSeek is unavailable — selected nearest category automatically');
        } else {
            headline = t('categoryWeDetected');
        }

        html += '<h3>' + headline + '</h3>';

        const diag = buildDeepseekDiagnostic(ds);
        if (diag) {
            html += '<p class="deepseek-diagnostic">' + escapeHtml(diag) + '</p>';
        }

        if (!selected) {
            html += '<p>' + (locale === 'ru'
                ? 'Сначала заполните описание на шаге 1 и запустите пайплайн категории.'
                : 'First fill description on step 1 and run the category pipeline.') + '</p>';
            html += '</div>';
            return html;
        }

        var label = (selected.title_cat || '') + (selected.title_cat && selected.type_name ? ' \u2192 ' : '') + (selected.type_name || '');
        if (!label.trim()) {
            label = (locale === 'ru' ? 'Категория (ID: ' : 'Category (ID: ') + (selected.type_id || selected.db_id || selected.id || '') + ')';
        }
        html += '<p class="category-selected-main">' + escapeHtml(label) + '</p>';
        html += '<p class="category-hint">' + t('categoryManualHint') + '</p>';

        html += '</div>';
        return html;
    }

    function setupImageHandlers() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const urlInput = document.getElementById('urlInput');
        const urlButton = document.getElementById('urlButton');

        if (uploadArea && fileInput) {
            uploadArea.onclick = function() {
                fileInput.click();
            };

            uploadArea.ondragover = function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            };

            uploadArea.ondragleave = function() {
                uploadArea.classList.remove('dragover');
            };

            uploadArea.ondrop = function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    uploadImage(files[0]);
                }
            };

            fileInput.onchange = function() {
                if (fileInput.files.length > 0) {
                    uploadImage(fileInput.files[0]);
                }
            };
        }

        if (urlButton && urlInput) {
            urlButton.onclick = function() {
                const url = urlInput.value.trim();
                if (url) {
                    loadImageFromUrl(url);
                    urlInput.value = '';
                }
            };
        }
    }

    function uploadImage(file) {
        const formData = new FormData();
        formData.append('file', file);

        showStatus('uploading', t('uploading'));

        var uploadPath = buildApiUrlR('/drafts/' + wizard.draftId + '/images:upload');
        var uploadPromise = typeof window.apiFetchJson === 'function'
            ? window.apiFetchJson(uploadPath, { method: 'POST', body: formData })
            : fetch(uploadPath, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function(r) { return r.text(); }).then(function(text) {
                try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + (text ? text.substring(0, 80) : 'empty')); }
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

        apiFetch('/drafts/' + wizard.draftId + '/images:from-url', {
            method: 'POST',
            body: { url: url }
        })
        .then(function(data) {
            wizard.images = data.images;
            render();
            clearStatus();
        })
        .catch(function(err) {
            showStatus('error', t('uploadError') + ': ' + err.message);
        });
    }

    function deleteImage(imageId) {
        apiFetch('/drafts/' + wizard.draftId + '/images/' + imageId, {
            method: 'DELETE'
        })
        .then(function(data) {
            wizard.images = data.images;
            render();
        })
        .catch(function(err) {
            showStatus('error', t('error') + ': ' + err.message);
        });
    }

    function scheduleAutosave() {
        if (wizard.autosaveTimer) {
            clearTimeout(wizard.autosaveTimer);
        }
        wizard.autosaveTimer = setTimeout(function() {
            saveDraft(true);
        }, 800);
    }

    function saveDraft(silent) {
        if (!wizard.draftId) return;

        if (!silent) {
            showStatus('saving', t('saving'));
        }

        const patch = {
            description: (wizard.editedJson && wizard.editedJson.description) || '',
            current_step: wizard.currentStepIndex + 1,
            step_state: buildStepState()
        };
        if (wizard.categorySelected && typeof wizard.categorySelected === 'object') {
            patch.chosen_category = wizard.categorySelected;
        }
        // POST вместо PATCH: в части nginx PATCH даёт 406, как в старой версии использовали POST /drafts/:id?op=patch
        apiFetch('/draft', {
            method: 'POST',
            body: { marketplace: MARKETPLACE, patch: patch }
        })
            .then(function () {
                if (!silent) {
                    showStatus('saved', t('saved'));
                    setTimeout(clearStatus, 2000);
                }
            })
            .catch(function (err) {
                showStatus('error', t('error') + ': ' + (err && err.message));
            });
    }

    function publishDraft() {
        if (!wizard.draftId) return;

        showStatus('saving', t('saving'));

        apiFetch('/drafts/' + wizard.draftId + '/validate', {
            method: 'POST'
        })
        .then(function(data) {
            if (!data.valid) {
                wizard.fieldErrors = data.errors.fieldErrors || {};
                const firstErrorStep = findFirstErrorStep();
                if (firstErrorStep !== -1) {
                    wizard.currentStepIndex = firstErrorStep;
                }
                render();
                showStatus('error', t('validationFailed'));
                return;
            }

            return apiFetch('/drafts/' + wizard.draftId + '/publish', {
                method: 'POST'
            });
        })
        .then(function(data) {
            if (data && data.published) {
                const message = t('publishSuccess').replace('{productId}', data.productId);
                showStatus('success', message);
            }
        })
        .catch(function(err) {
            if (err.code === 'VALIDATION_FAILED' && err.details && err.details.fieldErrors) {
                wizard.fieldErrors = err.details.fieldErrors;
                const firstErrorStep = findFirstErrorStep();
                if (firstErrorStep !== -1) {
                    wizard.currentStepIndex = firstErrorStep;
                }
                render();
            }
            showStatus('error', t('error') + ': ' + err.message);
        });
    }

    function findFirstErrorStep() {
        if (!wizard.formSchema) return -1;

        for (let i = 0; i < wizard.formSchema.steps.length; i++) {
            const step = wizard.formSchema.steps[i];
            for (let j = 0; j < step.fields.length; j++) {
                if (wizard.fieldErrors[step.fields[j].key]) {
                    return i;
                }
            }
        }
        return -1;
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
            .then(function (data) {
                wizard.formSchema = data.formSchema;
                wizard.pendingDraft = data;
                render();
            })
            .catch(function (err) {
                // 404 + NO_DRAFT: нормальный случай «черновика нет». 406: nginx может отдать HTML вместо JSON — пробуем init, чтобы визард загрузился.
                if (err.code === 'NO_DRAFT' || err.status === 406) {
                    return apiFetch('/draft/init', { method: 'POST', body: { marketplace: MARKETPLACE } });
                }
                throw err;
            })
            .then(function (data) {
                if (data && !wizard.pendingDraft) {
                    applyDraftToWizard(data);
                }
            })
            .catch(function (err) {
                const container = document.getElementById('ozonWizard');
                if (container) {
                    if (err.code === 'UNAUTHORIZED' || err.code === 'AUTH_REQUIRED') {
                        window.location.href = (typeof window.__OZON_BASE__ !== 'undefined' ? window.__OZON_BASE__ : '') + '/login';
                        return;
                    }
                    container.innerHTML = '<div class="status-error">' + t('error') + ': ' + (err.message || '') + '</div>';
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
