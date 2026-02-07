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
            validationFailed: 'Пожалуйста, исправьте ошибки в форме'
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
            validationFailed: 'Please fix form errors'
        }
    };

    function t(key) {
        return i18n[locale][key] || key;
    }

    function apiFetch(path, options) {
        options = options || {};
        const url = '/api' + path + (path.indexOf('?') === -1 ? '?lang=' + locale : '&lang=' + locale);
        
        return fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            body: options.body ? JSON.stringify(options.body) : undefined
        })
        .then(function(response) {
            return response.json().then(function(data) {
                if (!data.ok) {
                    const err = new Error(data.error.message || 'Request failed');
                    err.code = data.error.code;
                    err.details = data.error.details || {};
                    throw err;
                }
                return data.data;
            });
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
        autosaveTimer: null
    };

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
            html += '<input type="text" class="field-input" data-field="' + field.key + '" value="' + (value || '') + '">';
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
        if (step) {
            html += '<div class="step-content">';
            step.fields.forEach(function(field) {
                html += renderField(field);
            });
            html += '</div>';
        }

        html += '<div class="buttons-row">';
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
            html += '<button class="btn btn-primary" id="publishBtn">' + t('publish') + '</button>';
        }
        html += '</div>';
        html += '</div>';

        if (wizard.savingState) {
            html += '<div class="status-area status-' + wizard.savingState.type + '">' + wizard.savingState.message + '</div>';
        }

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
            input.addEventListener('change', function() {
                const fieldKey = input.getAttribute('data-field');
                wizard.editedJson[fieldKey] = input.value;
                delete wizard.fieldErrors[fieldKey];
                scheduleAutosave();
            });
        });

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

        fetch('/api/ozon/drafts/' + wizard.draftId + '/images:upload?lang=' + locale, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.ok) {
                wizard.images = data.data.images;
                render();
                clearStatus();
            } else {
                showStatus('error', t('uploadError') + ': ' + (data.error.message || 'Unknown error'));
            }
        })
        .catch(function(err) {
            showStatus('error', t('uploadError') + ': ' + err.message);
        });
    }

    function loadImageFromUrl(url) {
        showStatus('uploading', t('uploading'));

        apiFetch('/ozon/drafts/' + wizard.draftId + '/images:from-url', {
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
        apiFetch('/ozon/drafts/' + wizard.draftId + '/images/' + imageId, {
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

        apiFetch('/ozon/drafts/' + wizard.draftId, {
            method: 'PATCH',
            body: { editedJson: wizard.editedJson }
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

        apiFetch('/ozon/drafts/' + wizard.draftId + '/validate', {
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

            return apiFetch('/ozon/drafts/' + wizard.draftId + '/publish', {
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
        apiFetch('/ozon/drafts', {
            method: 'POST'
        })
        .then(function(data) {
            wizard.draftId = data.draftId;
            wizard.formSchema = data.formSchema;
            wizard.editedJson = data.editedJson;
            wizard.images = data.images || [];
            render();
        })
        .catch(function(err) {
            const container = document.getElementById('ozonWizard');
            if (container) {
                container.innerHTML = '<div class="status-error">' + t('error') + ': ' + err.message + '</div>';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
