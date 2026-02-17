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
            pipelineError: 'Не удалось запустить пайплайн категории'
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
            categoryManualHint: 'You can choose a different category from the list if needed.',
            continue: 'Continue',
            pipelineError: 'Failed to run category pipeline'
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

    const wizard = {
        draftId: null,
        formSchema: null,
        editedJson: {},
        images: [],
        currentStepIndex: 0,
        savingState: null,
        fieldErrors: {},
        autosaveTimer: null,
        // Local UX-only pipeline state for category detection on step 1.
        categoryPipeline: null, // { status: 'idle'|'running'|'done'|'error', error?: string }
        categoryTop10: [],
        categorySelected: null,
        categoryDeepseek: null,
        categoryTree: '',
        categoryDeepseekTree: null
    };

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
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
            return '<p class="status-error">' + (cp.error || t('pipelineError')) + '</p>';
        }

        let h = '<ul class="pipeline-steps">';
        h += '<li>' + t('pipelineStep1') + '</li>';
        h += '<li>' + t('pipelineStep2') + '</li>';
        h += '<li>' + t('pipelineStep3') + '</li>';
        h += '<li>' + t('pipelineStep4') + '</li>';
        h += '<li>' + t('pipelineStep5') + '</li>';
        h += '</ul>';

        // Показываем строку дерева категорий, использованную для embedding.
        if (wizard.categoryTree) {
            const title = (locale === 'ru'
                ? 'Дерево категорий для embedding (DeepSeek): '
                : 'Category tree for embedding (DeepSeek): ');
            h += '<p class="category-tree-line"><strong>' + title + '</strong>' +
                escapeHtml(wizard.categoryTree) + '</p>';
        }

        // Explicit DeepSeek status line after steps.
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
        wizard.categoryTop10 = [];
        wizard.categorySelected = null;
        wizard.categoryDeepseek = null;
        wizard.categoryTree = '';
        wizard.categoryDeepseekTree = null;
        render();

        apiFetch('/pipeline/category:detect', {
            method: 'POST',
            body: {
                draftId: wizard.draftId,
                description: desc,
                lang: locale
            }
        }).then(function (data) {
            wizard.categoryTop10 = data.top10 || [];
            wizard.categorySelected = data.selected || null;
            wizard.categoryDeepseek = data.deepseek || null;
            wizard.categoryTree = data.category_tree || '';
            wizard.categoryDeepseekTree = data.deepseek_tree || null;

            // Keep selected category and candidates in editedJson so backend and UI stay in sync.
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
            wizard.categoryPipeline = {
                status: 'error',
                error: err && err.message ? err.message : t('pipelineError')
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
        if (!container || !wizard.formSchema) return;

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
            primaryStep1Btn.onclick = function () {
                const cp = wizard.categoryPipeline;
                // After successful pipeline, move user to category step.
                if (cp && cp.status === 'done' && wizard.formSchema && wizard.formSchema.steps.length > 1) {
                    wizard.currentStepIndex = 1;
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

        if (!top10.length || !selected) {
            html += '<p>' + (locale === 'ru'
                ? 'Сначала заполните описание на шаге 1 и запустите пайплайн категории.'
                : 'First fill description on step 1 and run the category pipeline.') + '</p>';
            html += '</div>';
            return html;
        }

        const label = (selected.title_cat || '') + ' \u2192 ' + (selected.type_name || '');
        html += '<p class="category-selected-main">' + label + '</p>';
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

        apiFetch('/drafts/' + wizard.draftId + '?op=patch', {
            method: 'POST',
            body: { description: wizard.editedJson.description, editedJson: wizard.editedJson }
        })
        .then(function() {
            if (!silent) {
                showStatus('saved', t('saved'));
                setTimeout(clearStatus, 2000);
            }
        })
        .catch(function(err) {
            showStatus('error', t('error') + ': ' + err.message);
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
        const existingDraftId = typeof window.__OZON_DRAFT_ID__ !== 'undefined' && window.__OZON_DRAFT_ID__;
        const promise = existingDraftId
            ? apiFetch('/drafts/' + window.__OZON_DRAFT_ID__)
            : apiFetch('/drafts', { method: 'POST' });
        promise.then(function(data) {
            wizard.draftId = data.draftId;
            wizard.formSchema = data.formSchema;
            wizard.editedJson = data.editedJson || {};
            wizard.images = data.images || [];
            render();
        }).catch(function(err) {
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
