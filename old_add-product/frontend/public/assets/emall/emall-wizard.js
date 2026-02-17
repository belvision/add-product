(function() {
    'use strict';

    const locale = (typeof window.__OZON_LANG__ === 'string' && window.__OZON_LANG__) ? window.__OZON_LANG__ : (window.__LANG__ || 'ru');
    const MARKETPLACE = 'emall';


    // === WAF-safe base64url helpers (avoid ModSecurity CRS false positives on description fields) ===
    function b64UrlEncodeUtf8(str) {
        str = (str === null || typeof str === 'undefined') ? '' : String(str);
        // UTF-8 encode then btoa
        var utf8 = encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(m, p1) {
            return String.fromCharCode(parseInt(p1, 16));
        });
        var b64 = btoa(utf8);
        return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function putB64Field(obj, fieldName, value) {
        if (!obj || !fieldName) return;
        obj[fieldName + '_b64'] = b64UrlEncodeUtf8(value);
        // do not send raw field to the server (WAF may inspect and block)
        if (Object.prototype.hasOwnProperty.call(obj, fieldName)) {
            try { delete obj[fieldName]; } catch (e) { obj[fieldName] = undefined; }
        }
    }
    const i18n = {
        ru: {
            saving: 'Сохранение...',
            saved: 'Сохранено',
            error: 'Ошибка',
            next: 'Далее',
            prev: 'Назад',
            continue: 'Продолжить',
            fillPropertiesBtn: 'Заполнить характеристики',
            saveAndContinuePropsBtn: 'Сохранить и продолжить',
            uploading: 'Загрузка...',
            uploadError: 'Ошибка загрузки',
            delete: 'Удалить',
            dragDrop: 'Перетащите изображения сюда или нажмите для выбора',
            fromUrl: 'Загрузить по URL',
            apiKeyLabel: 'API ключ eMall',
            descriptionLabel: 'Описание товара',
            innerArticleLabel: 'Артикул (inner_article)',
            barcodeLabel: 'Штрихкод (необязательно)',
            stockLabel: 'Остаток (stock)',
            innerArticleOverrideLabel: 'Внутренний артикул (замена)',
            importerLabel: 'Импортёр',
            importerPlaceholder: 'По умолчанию = бренд',
            step6Label: 'Готовим payload',
            step7Label: 'Отправка на eMall',
            payloadReady: 'Payload подготовлен',
            submitProductBtn: 'Отправить товар',
            submitProductLoading: 'Отправка...',
            submitSuccess: 'Товар успешно создан на eMall',
            priceZeroError: 'Цена не должна быть нулевой',
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
            addAnotherProduct: 'Добавить еще товар',
            addAnotherKeep: 'Добавить с сохранением категории, бренда и страны производства',
            step1Label: 'Данные и пайплайн',
            step2TextLabel: 'Название и описание',
            step2Label: 'Категория',
            step3Label: 'Расчет цены товара',
            step4Label: 'Бренд и страна производства',
            step5Label: 'Характеристики',
            propertiesLoading: 'Загрузка свойств категории...',
            propertiesFillLoading: 'Заполняем характеристики...',
            priceZacLabel: 'Закупочная цена (BYN)',
            weightLabel: 'Вес товара (г)',
            marginLabel: 'Маржинальность (%)',
            calcPriceBtn: 'Рассчитать',
            resultPriceLabel: 'Цена',
            resultOldPriceLabel: 'Старая цена',
            dimensionsLoading: 'Определяем габариты и вес...',
            brandLabel: 'Бренд',
            countryLabel: 'Страна производства',
            brandNotFound: 'Бренд не найден в справочнике',
            countryNotFound: 'Страна не найдена в справочнике',
            reloadDirectory: 'Перезагрузить справочник',
            selectBrandAgain: 'Выбрать бренд заново',
            selectRandomBrand: 'Выбрать случайный бренд',
            selectCountryAgain: 'Выбрать страну заново',
            selectRandomCountry: 'Выбрать случайную страну',
            textSavedSuccess: 'Новое описание и заголовок созданы',
            generateBtn: 'Сгенерировать',
            saveAndContinue: 'Сохранить и продолжить',
            titleLabel: 'Название товара',
            benefitsLabel: 'Преимущества (через ; )',
            usageLabel: 'Применение',
            generating: 'Генерация...',
            charsDesc: '400–900 символов',
            charsUsage: '300–700 символов'
        },
        en: {
            saving: 'Saving...',
            saved: 'Saved',
            error: 'Error',
            next: 'Next',
            prev: 'Previous',
            continue: 'Continue',
            fillPropertiesBtn: 'Fill characteristics',
            saveAndContinuePropsBtn: 'Save and continue',
            uploading: 'Uploading...',
            uploadError: 'Upload error',
            delete: 'Delete',
            dragDrop: 'Drag and drop images here or click to select',
            fromUrl: 'Load from URL',
            apiKeyLabel: 'eMall API key',
            descriptionLabel: 'Product description',
            innerArticleLabel: 'Article (inner_article)',
            barcodeLabel: 'Barcode (optional)',
            stockLabel: 'Stock',
            innerArticleOverrideLabel: 'Inner article (override)',
            importerLabel: 'Importer',
            importerPlaceholder: 'Default = brand',
            step6Label: 'Payload',
            step7Label: 'Submit to eMall',
            payloadReady: 'Payload ready',
            submitProductBtn: 'Submit product',
            submitProductLoading: 'Submitting...',
            submitSuccess: 'Product created on eMall',
            priceZeroError: 'Price must not be zero',
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
            addAnotherProduct: 'Add another product',
            addAnotherKeep: 'Add another keeping category, brand and country',
            step1Label: 'Data & pipeline',
            step2TextLabel: 'Title & description',
            step2Label: 'Category',
            step3Label: 'Product price calculation',
            step4Label: 'Brand and country of manufacture',
            step5Label: 'Attributes',
            propertiesLoading: 'Loading category properties...',
            propertiesFillLoading: 'Filling attributes...',
            priceZacLabel: 'Purchase price (BYN)',
            weightLabel: 'Product weight (g)',
            marginLabel: 'Margin (%)',
            calcPriceBtn: 'Calculate',
            resultPriceLabel: 'Price',
            resultOldPriceLabel: 'Old price',
            dimensionsLoading: 'Predicting dimensions and weight...',
            brandLabel: 'Brand',
            countryLabel: 'Country of manufacture',
            brandNotFound: 'Brand not found in directory',
            countryNotFound: 'Country not found in directory',
            reloadDirectory: 'Reload directory',
            selectBrandAgain: 'Select brand again',
            selectRandomBrand: 'Select random brand',
            selectCountryAgain: 'Select country again',
            selectRandomCountry: 'Select random country',
            textSavedSuccess: 'New description and title have been created',
            generateBtn: 'Generate',
            saveAndContinue: 'Save and continue',
            titleLabel: 'Product title',
            benefitsLabel: 'Benefits (separated by ; )',
            usageLabel: 'Usage',
            generating: 'Generating...',
            charsDesc: '400–900 characters',
            charsUsage: '300–700 characters'
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
        generatedText: { title: '', description: '', benefits: '', usage: '' },
        textGenerating: false,
        autoGenerateStep2Triggered: false,
        steps: [
            { key: 'data', labelKey: 'step1Label' },
            { key: 'text', labelKey: 'step2TextLabel' },
            { key: 'price', labelKey: 'step3Label' },
            { key: 'dimensions', labelKey: 'step4Label' },
            { key: 'properties', labelKey: 'step5Label' },
            { key: 'payload', labelKey: 'step6Label' },
            { key: 'submit', labelKey: 'step7Label' }
        ],
        priceResult: null,
        dimensionsResult: null,
        dimensionsLoading: false,
        dimensionsFetchTriggered: false,
        brandCountryPredicted: null,
        brandCountryLoading: false,
        brandCountryFetchTriggered: false,
        brandsList: [],
        countriesList: [],
        brandSelected: null,
        countrySelected: null,
        manualBrandMode: false,
        categoryPropertiesList: [],
        categoryPropertiesLoading: false,
        categoryPropertiesFetchTriggered: false,
        categoryPropertiesFilling: false,
        categoryPropertiesFilled: [],
        payloadData: null,
        payloadLoading: false,
        payloadFetchTriggered: false,
        submitProductLoading: false,
        submitProductResult: null
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
        if (wizard.generatedText && (wizard.generatedText.title || wizard.generatedText.description)) {
            state.generated_text = wizard.generatedText;
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
        if (wizard.priceResult && typeof wizard.priceResult === 'object' && wizard.priceResult.price != null) {
            state.price = {
                price: wizard.priceResult.price,
                old_price: wizard.priceResult.old_price,
                price_zac: wizard.priceResult.priceZac,
                weight_gram: wizard.priceResult.weightGram,
                margin_percent: wizard.priceResult.marginPercent
            };
        }
        if (wizard.dimensionsResult && typeof wizard.dimensionsResult === 'object') {
            state.dimensions = {
                width_mm: wizard.dimensionsResult.width_mm,
                length_mm: wizard.dimensionsResult.length_mm,
                height_mm: wizard.dimensionsResult.height_mm,
                weight_g: wizard.dimensionsResult.weight_g
            };
        }
        if (wizard.brandSelected && typeof wizard.brandSelected === 'object') {
            state.brand = { id: wizard.brandSelected.id, name: wizard.brandSelected.name };
        }
        if (wizard.countrySelected && typeof wizard.countrySelected === 'object') {
            state.country = { id: wizard.countrySelected.id, name: wizard.countrySelected.name };
        }
        if (wizard.categoryPropertiesFilled && wizard.categoryPropertiesFilled.length) {
            state.category_properties = wizard.categoryPropertiesFilled;
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
        if (stepState.generated_text && typeof stepState.generated_text === 'object') {
            wizard.generatedText = Object.assign({ title: '', description: '', benefits: '', usage: '' }, stepState.generated_text);
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
        if (stepState.price && typeof stepState.price === 'object') {
            var p = stepState.price;
            wizard.priceResult = {
                price: p.price,
                old_price: p.old_price,
                priceZac: p.price_zac,
                weightGram: p.weight_gram,
                marginPercent: p.margin_percent
            };
        }
        if (stepState.dimensions && typeof stepState.dimensions === 'object') {
            var d = stepState.dimensions;
            wizard.dimensionsResult = {
                width_mm: d.width_mm,
                length_mm: d.length_mm,
                height_mm: d.height_mm,
                weight_g: d.weight_g
            };
        }
        if (stepState.brand && typeof stepState.brand === 'object') {
            wizard.brandSelected = { id: stepState.brand.id, name: stepState.brand.name };
            wizard.manualBrandMode = false;
        }
        if (stepState.country && typeof stepState.country === 'object') {
            wizard.countrySelected = { id: stepState.country.id, name: stepState.country.name };
        }
        if (stepState.category_properties && Array.isArray(stepState.category_properties) && stepState.category_properties.length) {
            wizard.categoryPropertiesFilled = stepState.category_properties;
        }
    }

    /**
     * Resolve step: 1=data, 2=text, 3=price, 4=brand-country.
     */
    function resolveDraftStep(draft) {
        var hasCategory = (draft.editedJson && draft.editedJson.selected_category && typeof draft.editedJson.selected_category === 'object' && Object.keys(draft.editedJson.selected_category).length > 0) ||
            (draft.chosen_category && typeof draft.chosen_category === 'object' && Object.keys(draft.chosen_category).length > 0);
        var sc = draft.step_state && draft.step_state.category;
        if (sc && typeof sc === 'object' && (sc.category_id || sc.title_cat || sc.path || (Object.keys(sc).length > 0))) hasCategory = true;
        var hasText = (draft.filled_fields && draft.filled_fields.generated_text && typeof draft.filled_fields.generated_text === 'object' && (draft.filled_fields.generated_text.title || draft.filled_fields.generated_text.description));
        var hasStepStateText = draft.step_state && draft.step_state.generated_text && (draft.step_state.generated_text.title || draft.step_state.generated_text.description);
        var hasPrice = draft.step_state && draft.step_state.price && typeof draft.step_state.price === 'object' && draft.step_state.price.price != null;
        var hasBrandCountry = draft.step_state && draft.step_state.brand && draft.step_state.country
            && (draft.step_state.brand.id != null || draft.step_state.brand.id === 0)
            && (draft.step_state.country.id != null || draft.step_state.country.id === 0);
        var hasCategoryProperties = draft.step_state && Array.isArray(draft.step_state.category_properties) && draft.step_state.category_properties.length > 0;
        if (hasCategory && (hasText || hasStepStateText) && hasPrice && hasBrandCountry && hasCategoryProperties) return 6;
        if (hasCategory && (hasText || hasStepStateText) && hasPrice && hasBrandCountry) return 5;
        if (hasCategory && (hasText || hasStepStateText) && hasPrice) return 4;
        if (hasCategory && (hasText || hasStepStateText)) return 3;
        if (hasCategory || hasText || hasStepStateText) return 2;
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
        if (draft.filled_fields && draft.filled_fields.generated_text && typeof draft.filled_fields.generated_text === 'object') {
            wizard.generatedText = Object.assign({ title: '', description: '', benefits: '', usage: '' }, draft.filled_fields.generated_text);
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

            var detectBody = {
                draftId: wizard.draftId,
                job_id: jobId,
                marketplace: MARKETPLACE,
                lang: locale
            };
            // WAF (ModSecurity CRS 941120) may block long description as XSS.
            putB64Field(detectBody, 'description', desc);
            apiFetch('/pipeline/category-detect', {
                method: 'POST',
                body: detectBody
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
            var canNavigate = index <= wizard.currentStepIndex;
            var clickableClass = canNavigate ? ' step-clickable' : '';
            html += '<div class="step' + (isCurrent ? ' current' : '') + (isCompleted ? ' completed' : '') + clickableClass + '" data-step-index="' + index + '" title="' + (canNavigate ? (locale === 'ru' ? 'Перейти к шагу ' : 'Go to step ') + (index + 1) : '') + '">';
            html += '<div class="step-circle">' + (isCompleted ? '\u2713' : (index + 1)) + '</div>';
            html += '<div class="step-label">' + t(step.labelKey) + '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function renderStep1Content() {
        var desc = (wizard.editedJson && wizard.editedJson.description) || '';
        var innerArticle = (wizard.editedJson && wizard.editedJson.inner_article) || '';
        var barcode = (wizard.editedJson && wizard.editedJson.barcode) || '';
        var html = '';
        html += '<p id="emallSavedKeyHint" class="emall-saved-key-hint" style="margin-bottom:8px;color:#666;font-size:14px;"></p>';        html += '<div class="field-row">';
        html += '<label class="field-label">' + t('innerArticleLabel') + '</label>';
        html += '<input type="text" class="field-input" data-field="inner_article" value="' + escapeHtml(innerArticle) + '" placeholder="">';
        html += '</div>';
        html += '<div class="field-row">';
        html += '<label class="field-label">' + t('barcodeLabel') + '</label>';
        html += '<input type="text" class="field-input" data-field="barcode" value="' + escapeHtml(barcode) + '" placeholder="">';
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

    function buildCombinedDescription(gt) {
        var d = (gt.description || '').trim();
        var b = (gt.benefits || '').trim();
        var u = (gt.usage || '').trim();
        var parts = [];
        if (d) parts.push(d);
        if (b) parts.push(b);
        if (u) parts.push(u);
        return parts.join('\n\n');
    }

    function parseCombinedDescription(combined) {
        var s = (combined || '').trim();
        if (!s) return { description: '', benefits: '', usage: '' };
        var parts = s.split(/\n\n+/);
        return {
            description: (parts[0] || '').trim(),
            benefits: (parts[1] || '').trim(),
            usage: (parts[2] || '').trim()
        };
    }

    function renderStep2TextContent() {
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
    }

    function renderStep2RightPanel() {
        var gt = wizard.generatedText || {};
        var combined = buildCombinedDescription(gt);
        var hasResult = !!(gt.title || combined);
        var h = '<h3>' + (locale === 'ru' ? 'Результат генерации' : 'Generation result') + '</h3>';
        if (wizard.textGenerating) {
            h += '<p>' + (locale === 'ru' ? 'DeepSeek генерирует заголовок и описание из 3 абзацев на основе данных с шага 1...' : 'DeepSeek is generating title and 3-paragraph description from step 1 data...') + '</p>';
            h += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
        } else if (hasResult) {
            h += '<p>' + (locale === 'ru' ? 'Текст сгенерирован. Проверьте заголовок и описание слева, при необходимости отредактируйте и нажмите «Сохранить и продолжить».' : 'Text generated. Review title and description on the left, edit if needed, then click "Save and continue".') + '</p>';
            if (gt.title) h += '<p class="text-result-preview"><strong>' + (locale === 'ru' ? 'Заголовок: ' : 'Title: ') + '</strong>' + escapeHtml(gt.title.substring(0, 80)) + (gt.title.length > 80 ? '...' : '') + '</p>';
        } else {
            h += '<p>' + (locale === 'ru' ? 'На основе описания с шага 1 DeepSeek сгенерирует заголовок и описание из 3 абзацев (описание, преимущества, применение).' : 'Based on the description from step 1, DeepSeek will generate the title and a 3-paragraph description.') + '</p>';
            h += '<p>' + (locale === 'ru' ? 'Генерация запускается автоматически при переходе на этот шаг.' : 'Generation starts automatically when you reach this step.') + '</p>';
        }
        return h;
    }

    /**
     * Расчёт розничной цены по формуле eMall (ZennoPoster).
     * Маржинальность = доля чистой прибыли (после налога) в розничной цене.
     * @returns {{ price: number, old_price: number, priceZac: number, weightGram: number, marginPercent: number } | null}
     */
    function calculateEmallPrice(priceZac, weightGram, marginPercent) {
        var priceVal = parseFloat(String(priceZac || '').replace(',', '.'), 10);
        var weightVal = parseFloat(String(weightGram || '').replace(',', '.'), 10);
        var marginVal = parseFloat(String(marginPercent || '').replace(',', '.'), 10);
        if (isNaN(priceVal) || priceVal <= 0 || isNaN(weightVal) || weightVal <= 0 || isNaN(marginVal) || marginVal < 0 || marginVal >= 100) {
            return null;
        }
        var weightKg = weightVal / 1000;
        var deliveryKop = 0;
        if (weightKg <= 0.5) deliveryKop = 239;
        else if (weightKg <= 1) deliveryKop = 249;
        else if (weightKg <= 2) deliveryKop = 269;
        else if (weightKg <= 5) deliveryKop = 339;
        else if (weightKg <= 10) deliveryKop = 459;
        else if (weightKg <= 15) deliveryKop = 579;
        else if (weightKg <= 20) deliveryKop = 719;
        else if (weightKg <= 25) deliveryKop = 859;
        else if (weightKg <= 30) deliveryKop = 999;
        else if (weightKg <= 35) deliveryKop = 1349;
        else if (weightKg <= 40) deliveryKop = 1499;
        else deliveryKop = 1499;
        var logistik = deliveryKop / 100;
        var comissija = 0.04;
        var ekvajring = 0.015;
        var upakovkaPercent = 0.03;
        var m = marginVal / 100;
        var taxRate = 0.20;
        var r = comissija + (ekvajring * 2) + upakovkaPercent;
        var denom = 1 - r - (m / (1 - taxRate));
        if (denom <= 0.02) return null;
        var price = (priceVal + logistik) / denom;
        price = Math.round(price * 100) / 100;
        var percent = 20 + Math.floor(Math.random() * 11);
        var oldPrice = Math.round(price * (1 + percent / 100) * 100) / 100;
        return { price: price, old_price: oldPrice, priceZac: priceVal, weightGram: weightVal, marginPercent: marginVal };
    }

    function renderStep3Content() {
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
    }

    function findBrandInList(brandName, list) {
        if (!brandName || !list || !list.length) return null;
        var q = brandName.trim().toLowerCase();
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].name || '').toLowerCase() === q) return list[i];
        }
        for (var j = 0; j < list.length; j++) {
            if (String(list[j].name || '').toLowerCase().indexOf(q) !== -1) return list[j];
        }
        return null;
    }

    function findCountryInList(countryName, list) {
        if (!countryName || !list || !list.length) return null;
        var q = countryName.trim().toLowerCase();
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].name || '').toLowerCase() === q) return list[i];
        }
        for (var j = 0; j < list.length; j++) {
            if (String(list[j].name || '').toLowerCase().indexOf(q) !== -1) return list[j];
        }
        return null;
    }

    function renderStep4Content() {
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
        // Manual brand input should appear ONLY when auto-resolve failed (or user explicitly wants to change selection)
        var manualBrandMode = !!wizard.manualBrandMode;

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
            // Brand block
            if (brandSel && !manualBrandMode) {
                html += '<div class="field-row">';
                html += '<label>' + t('brandLabel') + '</label>';
                html += '<div class="step4-selected">' + escapeHtml(brandSel.name || '') + '</div>';
                html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="editBrandBtn">' + (locale === 'ru' ? 'Изменить' : 'Change') + '</button></div>';
                html += '</div>';
            } else {
                if (brandNotFound) {
                    html += '<div class="step4-not-found">';
                    html += '<p>' + t('brandNotFound') + ': ' + escapeHtml(pred.brand) + '</p>';
                    html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="reloadBrandsBtn">' + t('reloadDirectory') + '</button> ';
                    html += '<button type="button" class="btn btn-secondary" id="selectBrandAgainBtn">' + t('selectBrandAgain') + '</button>';
                    if (brands.length) {
                        html += ' <button type="button" class="btn btn-primary" id="selectRandomBrandBtn">' + t('selectRandomBrand') + '</button>';
                    }
                    html += '</div>';
                    html += '</div>';
                }
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

            if (countryNotFound && countries.length) {
                html += '<div class="step4-not-found">';
                html += '<p>' + t('countryNotFound') + ': ' + escapeHtml(pred.country) + '</p>';
                html += '<div class="step4-buttons"><button type="button" class="btn btn-secondary" id="reloadCountriesBtn">' + t('reloadDirectory') + '</button> ';
                html += '<button type="button" class="btn btn-secondary" id="selectCountryAgainBtn">' + t('selectCountryAgain') + '</button> ';
                html += '<button type="button" class="btn btn-primary" id="selectRandomCountryBtn">' + t('selectRandomCountry') + '</button></div>';
                html += '</div>';
            }
            html += '<div class="field-row"><label>' + t('countryLabel') + '</label>';
            html += '<select id="countrySelect" class="field-input">';
            html += '<option value="">' + (locale === 'ru' ? '— Выберите страну —' : '— Select country —') + '</option>';
            countries.forEach(function(c) {
                var sel = countrySel && countrySel.id == c.id ? ' selected' : '';
                html += '<option value="' + c.id + '" data-name="' + escapeHtml(c.name) + '"' + sel + '>' + escapeHtml(c.name) + '</option>';
            });
            html += '</select></div>';
        }
        html += '</div>';
        return html;
    }

    function renderStep3RightPanel() {
        var h = '<h3>' + (locale === 'ru' ? 'Прогресс' : 'Progress') + '</h3>';
        h += '<ul class="step3-progress-list">';
        h += '<li class="done">\u2713 ' + t('step1Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step2TextLabel') + '</li>';
        h += '<li class="current">' + t('step3Label') + '</li>';
        h += '<li>' + t('step4Label') + '</li>';
        h += '<li>' + t('step5Label') + '</li>';
        h += '</ul>';
        return h;
    }

    function renderStep4RightPanel() {
        var h = '<h3>' + (locale === 'ru' ? 'Прогресс' : 'Progress') + '</h3>';
        h += '<ul class="step3-progress-list">';
        h += '<li class="done">\u2713 ' + t('step1Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step2TextLabel') + '</li>';
        h += '<li class="done">\u2713 ' + t('step3Label') + '</li>';
        h += '<li class="current">' + t('step4Label') + '</li>';
        h += '<li>' + t('step5Label') + '</li>';
        h += '</ul>';
        return h;
    }

    function resolvePropertyValueDisplay(prop, val) {
        if (val == null || val === '') return '';
        var values = (prop.property && prop.property.values) ? prop.property.values : [];
        var valueById = {};
        values.forEach(function(v) { valueById[v.id] = v.value || ''; });
        var ids = Array.isArray(val) ? val : [val];
        var parts = [];
        ids.forEach(function(id) {
            var text = valueById[id];
            if (text !== undefined && text !== '') {
                parts.push(String(id) + ' (' + text + ')');
            } else {
                parts.push(String(id));
            }
        });
        return parts.join(', ');
    }

    function renderStep5Content() {
        var props = wizard.categoryPropertiesList || [];
        var filled = wizard.categoryPropertiesFilled || [];
        var loading = wizard.categoryPropertiesLoading || wizard.categoryPropertiesFilling;
        var hasFilled = Array.isArray(filled) && filled.length > 0;
        var filledMap = {};
        filled.forEach(function(p) { filledMap[p.id] = p.value; });

        var html = '<div class="category-step step5-properties">';
        html += '<h3>' + t('step5Label') + '</h3>';

        // Top action (moved from bottom): first fill via DeepSeek, then allow user edits and "Save and continue".
        if (!loading && props.length > 0) {
            html += '<div class="step5-actions-top">';
            if (!hasFilled) {
                html += '<button type="button" class="btn btn-primary" id="fillPropertiesBtnTop">' + t('fillPropertiesBtn') + '</button>';
            } else {
                html += '<button type="button" class="btn btn-primary" id="saveContinuePropertiesBtnTop">' + t('saveAndContinuePropsBtn') + '</button>';
            }
            html += '</div>';
        }

        if (loading) {
            html += '<p class="step5-loading">' + (wizard.categoryPropertiesFilling ? t('propertiesFillLoading') : t('propertiesLoading')) + '</p>';
            html += '<div class="pipeline-spinner-wrap"><span class="pipeline-spinner" aria-hidden="true"></span></div>';
        } else if (props.length === 0) {
            html += '<p>' + (locale === 'ru' ? 'Нет свойств для заполнения.' : 'No properties to fill.') + '</p>';
        } else {
            html += '<p class="step5-hint">' + (locale === 'ru'
                ? (!hasFilled
                    ? 'Нажмите «Заполнить характеристики», чтобы заполнить с помощью DeepSeek. После этого вы сможете отредактировать значения и нажать «Сохранить и продолжить».'
                    : 'Характеристики заполнены. При необходимости отредактируйте значения и нажмите «Сохранить и продолжить».')
                : (!hasFilled
                    ? 'Click "Fill characteristics" to use DeepSeek. Then you can edit values and click "Save and continue".'
                    : 'Characteristics are filled. Edit if needed and click "Save and continue".')
            ) + '</p>';
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
    }

    function renderStep5RightPanel() {
        var h = '<h3>' + (locale === 'ru' ? 'Прогресс' : 'Progress') + '</h3>';
        h += '<ul class="step3-progress-list">';
        h += '<li class="done">\u2713 ' + t('step1Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step2TextLabel') + '</li>';
        h += '<li class="done">\u2713 ' + t('step3Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step4Label') + '</li>';
        h += '<li class="current">' + t('step5Label') + '</li>';
        h += '<li>' + t('step6Label') + '</li>';
        h += '</ul>';
        return h;
    }

    function renderStep6Content() {
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
    }

    function renderStep6RightPanel() {
        var h = '<h3>' + (locale === 'ru' ? 'Прогресс' : 'Progress') + '</h3>';
        h += '<ul class="step3-progress-list">';
        h += '<li class="done">\u2713 ' + t('step1Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step2TextLabel') + '</li>';
        h += '<li class="done">\u2713 ' + t('step3Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step4Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step5Label') + '</li>';
        h += '<li class="current">' + t('step6Label') + '</li>';
        h += '<li>' + t('step7Label') + '</li>';
        h += '</ul>';
        return h;
    }

    function renderStep7Content() {
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
    }

    function renderStep7RightPanel() {
        var h = '<h3>' + (locale === 'ru' ? 'Прогресс' : 'Progress') + '</h3>';
        h += '<ul class="step3-progress-list">';
        h += '<li class="done">\u2713 ' + t('step1Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step2TextLabel') + '</li>';
        h += '<li class="done">\u2713 ' + t('step3Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step4Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step5Label') + '</li>';
        h += '<li class="done">\u2713 ' + t('step6Label') + '</li>';
        h += '<li class="current">' + t('step7Label') + '</li>';
        h += '</ul>';
        return h;
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
                            wizard.generatedText = { title: '', description: '', benefits: '', usage: '' };
                            wizard.priceResult = null;
                            wizard.dimensionsResult = null;
                            wizard.dimensionsFetchTriggered = false;
                            wizard.brandCountryPredicted = null;
                            wizard.brandCountryFetchTriggered = false;
                            wizard.brandsList = [];
                            wizard.countriesList = [];
                            wizard.brandSelected = null;
                            wizard.countrySelected = null;
                            wizard.categoryPropertiesList = [];
                wizard.categoryPropertiesFilled = [];
                wizard.categoryPropertiesFetchTriggered = false;
                wizard.categoryPropertiesFilling = false;
                wizard.payloadData = null;
                wizard.payloadFetchTriggered = false;
                wizard.submitProductResult = null;
                            wizard.autoGenerateStep2Triggered = false;
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
        } else if (wizard.currentStepIndex === 1) {
            html += renderStep2TextContent();
        } else if (wizard.currentStepIndex === 2) {
            html += renderStep3Content();
        } else if (wizard.currentStepIndex === 3) {
            html += renderStep4Content();
        } else if (wizard.currentStepIndex === 4) {
            html += renderStep5Content();
        } else if (wizard.currentStepIndex === 5) {
            html += renderStep6Content();
        } else {
            html += renderStep7Content();
        }
        html += '</div>';

        html += '<div class="buttons-row">';
        if (wizard.currentStepIndex === 0) {
            var cp = wizard.categoryPipeline;
            var label = !cp || cp.status === 'idle' ? t('startPipeline') : cp.status === 'running' ? (locale === 'ru' ? 'Пайплайн запускается...' : 'Pipeline running...') : cp.status === 'done' ? t('goToStep2') : cp.status === 'error' ? (locale === 'ru' ? 'Повторить пайплайн' : 'Retry pipeline') : t('startPipeline');
            var disabledAttr = (cp && cp.status === 'running') ? ' disabled' : '';
            html += '<div></div><div><button type="button" class="btn btn-primary" id="primaryStep1Btn"' + disabledAttr + '>' + label + '</button></div>';
        } else if (wizard.currentStepIndex === 1) {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            html += '<div><button class="btn btn-primary" id="saveTextBtn">' + t('saveAndContinue') + '</button></div>';
        } else if (wizard.currentStepIndex === 2) {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            html += '<div><button class="btn btn-primary" id="continuePriceBtn">' + t('continue') + '</button></div>';
        } else if (wizard.currentStepIndex === 3) {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            html += '<div><button class="btn btn-primary" id="continueDimensionsBtn">' + t('continue') + '</button></div>';
        } else if (wizard.currentStepIndex === 4) {
            html += '<button class="btn btn-secondary" id="prevBtn"' + (wizard.categoryPropertiesFilling ? ' disabled' : '') + '>' + t('prev') + '</button>';
            html += '<div></div>';
        } else if (wizard.currentStepIndex === 5) {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            html += '<div><button class="btn btn-secondary" id="refreshPayloadBtn">' + (locale === 'ru' ? 'Обновить' : 'Refresh') + '</button> ';
            html += '<button class="btn btn-primary" id="continueToSubmitBtn">' + t('continue') + '</button></div>';
        } else {
            html += '<button class="btn btn-secondary" id="prevBtn">' + t('prev') + '</button>';
            if (wizard.submitProductResult && wizard.submitProductResult.success) {
                html += '<div>'
                    + '<button class="btn btn-secondary" id="addAnotherProductBtn">' + t('addAnotherProduct') + '</button>'
                    + ' '
                    + '<button class="btn btn-primary" id="addAnotherKeepBtn">' + t('addAnotherKeep') + '</button>'
                    + '</div>';
            } else {
                var submitDisabled = wizard.submitProductLoading ? ' disabled' : '';
                html += '<div><button class="btn btn-primary" id="submitProductBtn"' + submitDisabled + '>' + (wizard.submitProductLoading ? t('submitProductLoading') : t('submitProductBtn')) + '</button></div>';
            }
        }
        html += '</div>';
        if (wizard.savingState) {
            html += '<div class="status-area status-' + wizard.savingState.type + '">' + escapeHtml(wizard.savingState.message) + '</div>';
        }
        html += '</div>';
        var rightPanel;
        if (wizard.currentStepIndex === 1) {
            rightPanel = renderStep2RightPanel();
        } else if (wizard.currentStepIndex === 2) {
            rightPanel = renderStep3RightPanel();
        } else if (wizard.currentStepIndex === 3) {
            rightPanel = renderStep4RightPanel();
        } else if (wizard.currentStepIndex === 4) {
            rightPanel = renderStep5RightPanel();
        } else if (wizard.currentStepIndex === 5) {
            rightPanel = renderStep6RightPanel();
        } else if (wizard.currentStepIndex === 6) {
            rightPanel = renderStep7RightPanel();
        } else {
            rightPanel = '<h3>' + t('pipelineProgress') + '</h3><div id="pipelineStatusContent">' + renderPipelineContent() + '</div>';
        }
        html += '<div class="pipeline-panel">' + rightPanel + '</div>';
        html += '</div>';

        container.innerHTML = html;
        attachEventHandlers();
        if (wizard.currentStepIndex === 4) attachStep5Handlers();
        if (wizard.currentStepIndex === 5) attachStep6Handlers();
        if (wizard.currentStepIndex === 6) attachStep7Handlers();
        renderImagesGrid();
        setupImageHandlers();
        if (wizard.currentStepIndex === 0) {
            loadEmallSavedKeyHint();
        }
        if (wizard.currentStepIndex === 1) {
            attachTextStepHandlers();
            var desc = (wizard.editedJson && wizard.editedJson.description) || '';
            var gt = wizard.generatedText || {};
            if (desc && wizard.draftId && !wizard.textGenerating && !gt.title && !buildCombinedDescription(gt) && !wizard.autoGenerateStep2Triggered) {
                wizard.autoGenerateStep2Triggered = true;
                setTimeout(startGenerateText, 400);
            }
        }
        if (wizard.currentStepIndex === 5 && wizard.draftId && !wizard.payloadLoading && !wizard.payloadFetchTriggered) {
            wizard.payloadFetchTriggered = true;
            fetchPayload();
        }
        if (wizard.currentStepIndex === 4 && wizard.draftId && wizard.categorySelected && !wizard.categoryPropertiesLoading && !wizard.categoryPropertiesFetchTriggered) {
            wizard.categoryPropertiesFetchTriggered = true;
            fetchCategoryProperties();
        }
        if (wizard.currentStepIndex === 3 && wizard.draftId && !wizard.brandCountryLoading && !wizard.brandCountryFetchTriggered) {
            wizard.brandCountryFetchTriggered = true;
            var hasSaved = wizard.brandSelected && wizard.countrySelected && wizard.brandSelected.id != null && wizard.countrySelected.id != null;
            if (hasSaved) {
                fetchCountriesOnly();
            } else {
                setTimeout(fetchBrandCountryAndDirectories, 400);
            }
        }
        if (wizard.currentStepIndex === 2 && wizard.draftId && !wizard.dimensionsResult && !wizard.dimensionsLoading && !wizard.dimensionsFetchTriggered) {
            var gt2 = wizard.generatedText || {};
            var combined2 = buildCombinedDescription(gt2);
            var desc2 = (wizard.editedJson && wizard.editedJson.description) || '';
            if ((gt2.title || combined2 || desc2) && !wizard.dimensionsResult) {
                wizard.dimensionsFetchTriggered = true;
                setTimeout(fetchDimensions, 400);
            }
        }
    }

    function fetchCountriesOnly() {
        if (!wizard.draftId) return;
        var draftId = wizard.draftId;
        var q = '?draftId=' + encodeURIComponent(draftId);
        apiFetch('/emall/countries' + q).then(function(result) {
            wizard.countriesList = (result && result.countries) ? result.countries : [];
            render();
        }).catch(function(err) {
            wizard.brandCountryFetchTriggered = false;
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function fetchBrandCountryAndDirectories() {
        if (!wizard.draftId || wizard.brandCountryLoading) return;
        wizard.brandCountryLoading = true;
        render();
        var draftId = wizard.draftId;
        var q = '?draftId=' + encodeURIComponent(draftId);
        Promise.all([
            apiFetch('/emall/drafts/' + draftId + '/brand-country', { method: 'POST' }),
            apiFetch('/emall/countries' + q)
        ]).then(function(results) {
            wizard.brandCountryLoading = false;
            var pred = results[0];
            var countries = (results[1] && results[1].countries) ? results[1].countries : [];
            wizard.brandCountryPredicted = {
                brand: (pred && pred.brand) ? pred.brand : '',
                country: (pred && pred.country) ? pred.country : '',
                brand_resolved: (pred && pred.brand_resolved) ? pred.brand_resolved : null,
                country_resolved: (pred && pred.country_resolved) ? pred.country_resolved : null
            };

            // If backend already resolved IDs against directories — use them immediately.
            if (!wizard.brandSelected && wizard.brandCountryPredicted.brand_resolved && wizard.brandCountryPredicted.brand_resolved.id) {
                wizard.brandSelected = {
                    id: wizard.brandCountryPredicted.brand_resolved.id,
                    name: wizard.brandCountryPredicted.brand_resolved.name || ''
                };
                wizard.manualBrandMode = false;
            }
            if (!wizard.countrySelected && wizard.brandCountryPredicted.country_resolved && wizard.brandCountryPredicted.country_resolved.id) {
                wizard.countrySelected = {
                    id: wizard.brandCountryPredicted.country_resolved.id,
                    name: wizard.brandCountryPredicted.country_resolved.name || ''
                };
            }
            // Do not preload all brands (huge directory). We'll search on demand.
            wizard.brandsList = [];
            wizard.countriesList = countries;
            if (!wizard.brandSelected) {
                // Try to resolve predicted brand via server-side search (fast, limited list)
                var qb = String(pred.brand || '').trim();
                if (qb) {
                    var qs = '?draftId=' + encodeURIComponent(draftId) + '&q=' + encodeURIComponent(qb) + '&limit=30';
                    apiFetch('/emall/brands/search' + qs).then(function(bres) {
                        var bList = (bres && bres.brands) ? bres.brands : [];
                        wizard.brandsList = bList;
                        var brandMatch = findBrandInList(qb, bList);
                        if (brandMatch) {
                            wizard.brandSelected = { id: brandMatch.id, name: brandMatch.name };
                            wizard.manualBrandMode = false;
                        }
                        render();
                    }).catch(function() {
                        // ignore
                    });
                }
            }
            if (!wizard.countrySelected) {
                var countryMatch = findCountryInList(pred.country, countries);
                if (countryMatch) wizard.countrySelected = { id: countryMatch.id, name: countryMatch.name };
            }
            render();
        }).catch(function(err) {
            wizard.brandCountryLoading = false;
            wizard.brandCountryFetchTriggered = false;
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function fetchCategoryProperties() {
        if (!wizard.draftId || !wizard.categorySelected) return;
        var catId = wizard.categorySelected.category_id;
        if (!catId) return;
        wizard.categoryPropertiesLoading = true;
        render();
        var q = '?categoryId=' + encodeURIComponent(catId) + '&draftId=' + encodeURIComponent(wizard.draftId);
        apiFetch('/emall/properties' + q).then(function(data) {
            wizard.categoryPropertiesLoading = false;
            wizard.categoryPropertiesList = (data && data.data) ? data.data : [];
            render();
        }).catch(function(err) {
            wizard.categoryPropertiesLoading = false;
            wizard.categoryPropertiesFetchTriggered = false;
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function updateCategoryPropertyValue(propId, value) {
        var arr = wizard.categoryPropertiesFilled || [];
        var found = false;
        for (var i = 0; i < arr.length; i++) {
            if (arr[i].id === propId) {
                arr[i].value = value;
                found = true;
                break;
            }
        }
        if (!found) arr.push({ id: propId, value: value });
        wizard.categoryPropertiesFilled = arr;
        saveDraft(true);
    }

    function attachStep5Handlers() {
        var selects = document.querySelectorAll('.step5-select[data-prop-id]');
        selects.forEach(function(sel) {
            sel.addEventListener('change', function() {
                var propId = parseInt(sel.getAttribute('data-prop-id'), 10);
                var val = sel.value;
                if (val === 'true') val = true;
                else if (val === 'false') val = false;
                else if (val !== '' && /^\d+$/.test(val)) val = parseInt(val, 10);
                else if (val === '') val = null;
                updateCategoryPropertyValue(propId, val);
            });
        });
        var checkboxes = document.querySelectorAll('.step5-checkbox[data-prop-id]');
        var checkboxByProp = {};
        checkboxes.forEach(function(cb) {
            var pid = parseInt(cb.getAttribute('data-prop-id'), 10);
            if (!checkboxByProp[pid]) checkboxByProp[pid] = [];
            checkboxByProp[pid].push(cb);
        });
        Object.keys(checkboxByProp).forEach(function(pidStr) {
            var pid = parseInt(pidStr, 10);
            var group = checkboxByProp[pid];
            group.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var vals = group.filter(function(c) { return c.checked; }).map(function(c) { return parseInt(c.value, 10); });
                    updateCategoryPropertyValue(pid, vals);
                });
            });
        });
        var inputs = document.querySelectorAll('.step5-input[data-prop-id]');
        inputs.forEach(function(inp) {
            var propId = parseInt(inp.getAttribute('data-prop-id'), 10);
            inp.addEventListener('change', function() {
                var val = inp.value.trim();
                if (inp.type === 'number') val = val === '' ? null : (parseFloat(val) || null);
                updateCategoryPropertyValue(propId, val === '' ? null : val);
            });
            inp.addEventListener('input', function() {
                var val = inp.value.trim();
                if (inp.type === 'number') val = val === '' ? null : (parseFloat(val) || null);
                updateCategoryPropertyValue(propId, val === '' ? null : val);
            });
        });
    }

    function fetchPayload() {
        if (!wizard.draftId) return;
        wizard.payloadLoading = true;
        render();
        apiFetch('/emall/drafts/' + wizard.draftId + '/build-payload').then(function(data) {
            wizard.payloadLoading = false;
            wizard.payloadData = data;
            render();
        }).catch(function(err) {
            wizard.payloadLoading = false;
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function collectStep6Overrides() {
        wizard.editedJson = wizard.editedJson || {};
        var stockEl = document.getElementById('stockInput');
        if (stockEl) {
            var num = parseInt(stockEl.value, 10);
            wizard.editedJson.stock = isNaN(num) || num < 0 ? 30 : num;
        }
        var innerEl = document.getElementById('innerArticleInput');
        if (innerEl) {
            wizard.editedJson.inner_article = (innerEl.value || '').trim();
        }
        var importerEl = document.getElementById('importerInput');
        if (importerEl) {
            wizard.editedJson.importer = (importerEl.value || '').trim();
        }
    }
    function attachStep6Handlers() {
        var stockInput = document.getElementById('stockInput');
        if (stockInput) {
            stockInput.addEventListener('change', function() {
                collectStep6Overrides();
                saveDraft(true).then(function() {
                    wizard.payloadFetchTriggered = false;
                    fetchPayload();
                });
            });
        }
        var innerArticleInput = document.getElementById('innerArticleInput');
        if (innerArticleInput) {
            innerArticleInput.addEventListener('change', function() {
                collectStep6Overrides();
                saveDraft(true).then(function() {
                    wizard.payloadFetchTriggered = false;
                    fetchPayload();
                });
            });
        }
        var importerInput = document.getElementById('importerInput');
        if (importerInput) {
            importerInput.addEventListener('change', function() {
                collectStep6Overrides();
                saveDraft(true).then(function() {
                    wizard.payloadFetchTriggered = false;
                    fetchPayload();
                });
            });
        }
        var refreshBtn = document.getElementById('refreshPayloadBtn');
        if (refreshBtn) {
            refreshBtn.onclick = function() {
                collectStep6Overrides();
                saveDraft(true).then(function() {
                    wizard.payloadFetchTriggered = false;
                    fetchPayload();
                });
            };
        }
        var continueToSubmitBtn = document.getElementById('continueToSubmitBtn');
        if (continueToSubmitBtn) {
            continueToSubmitBtn.onclick = function() {
                collectStep6Overrides();
                if (!wizard.payloadData || wizard.payloadData.errors && wizard.payloadData.errors.length) {
                    showStatus('error', locale === 'ru' ? 'Сначала сформируйте корректный payload' : 'Build valid payload first');
                    return;
                }
                saveDraft(true).then(function() {
                    wizard.currentStepIndex = 6;
                    render();
                });
            };
        }
    }

    function submitProduct() {
        if (!wizard.draftId) return;
        wizard.submitProductLoading = true;
        wizard.submitProductResult = null;
        render();
        apiFetch('/emall/drafts/' + wizard.draftId + '/submit-product', { method: 'POST' }).then(function(data) {
            wizard.submitProductLoading = false;
            wizard.submitProductResult = data;
            render();
        }).catch(function(err) {
            wizard.submitProductLoading = false;
            wizard.submitProductResult = { success: false, message: (err && err.message) ? err.message : t('error') };
            render();
        });
    }

    function attachStep7Handlers() {
        var submitBtn = document.getElementById('submitProductBtn');
        if (submitBtn) {
            submitBtn.onclick = function() {
                if (wizard.submitProductLoading) return;
                submitProduct();
            };
        }

        var addAnotherBtn = document.getElementById('addAnotherProductBtn');
        if (addAnotherBtn) {
            addAnotherBtn.onclick = function() {
                apiFetch('/draft/reset', { method: 'POST', body: { marketplace: MARKETPLACE } })
                    .then(function(data) {
                        applyDraftToWizard(data);
                        wizard.images = [];
                        wizard.currentStepIndex = 0;
                        wizard.categoryPipeline = null;
                        wizard.categoryTop10 = [];
                        wizard.categorySelected = null;
                        wizard.generatedText = { title: '', description: '', benefits: '', usage: '' };
                        wizard.priceResult = null;
                        wizard.dimensionsResult = null;
                        wizard.dimensionsFetchTriggered = false;
                        wizard.brandCountryPredicted = null;
                        wizard.brandCountryFetchTriggered = false;
                        wizard.brandsList = [];
                        wizard.countriesList = [];
                        wizard.brandSelected = null;
                        wizard.countrySelected = null;
                        wizard.categoryPropertiesList = [];
                        wizard.categoryPropertiesFilled = [];
                        wizard.categoryPropertiesFetchTriggered = false;
                        wizard.categoryPropertiesFilling = false;
                        wizard.payloadData = null;
                        wizard.payloadFetchTriggered = false;
                        wizard.submitProductResult = null;
                        wizard.autoGenerateStep2Triggered = false;
                        wizard.pendingDraft = null;
                        render();
                    })
                    .catch(function(err) {
                        showStatus('error', t('error') + ': ' + (err && err.message || ''));
                    });
            };
        }

        var addKeepBtn = document.getElementById('addAnotherKeepBtn');
        if (addKeepBtn) {
            addKeepBtn.onclick = function() {
                var keepCategory = wizard.categorySelected ? Object.assign({}, wizard.categorySelected) : null;
                var keepTop10 = wizard.categoryTop10 ? wizard.categoryTop10.slice() : [];
                var keepBrand = wizard.brandSelected ? Object.assign({}, wizard.brandSelected) : null;
                var keepCountry = wizard.countrySelected ? Object.assign({}, wizard.countrySelected) : null;

                apiFetch('/draft/reset', { method: 'POST', body: { marketplace: MARKETPLACE } })
                    .then(function(data) {
                        applyDraftToWizard(data);
                        wizard.images = [];
                        wizard.currentStepIndex = 0;
                        wizard.categoryPipeline = null;
                        wizard.generatedText = { title: '', description: '', benefits: '', usage: '' };
                        wizard.priceResult = null;
                        wizard.dimensionsResult = null;
                        wizard.dimensionsFetchTriggered = false;
                        wizard.brandCountryPredicted = null;
                        wizard.brandCountryFetchTriggered = false;
                        wizard.brandsList = [];
                        wizard.countriesList = [];
                        wizard.categoryPropertiesList = [];
                        wizard.categoryPropertiesFilled = [];
                        wizard.categoryPropertiesFetchTriggered = false;
                        wizard.categoryPropertiesFilling = false;
                        wizard.payloadData = null;
                        wizard.payloadFetchTriggered = false;
                        wizard.submitProductResult = null;
                        wizard.autoGenerateStep2Triggered = false;
                        wizard.pendingDraft = null;

                        wizard.categorySelected = keepCategory;
                        wizard.categoryTop10 = keepTop10;
                        wizard.brandSelected = keepBrand;
                        wizard.countrySelected = keepCountry;

                        saveDraft(true).finally(function() {
                            render();
                        });
                    })
                    .catch(function(err) {
                        showStatus('error', t('error') + ': ' + (err && err.message || ''));
                    });
            };
        }
    }


    function fillCategoryProperties() {
        if (!wizard.draftId || !wizard.categoryPropertiesList.length) return;
        wizard.categoryPropertiesFilling = true;
        render();
        // POST without body: schema is cached server-side in draft.step_state.category_properties_schema
        apiFetch('/emall/drafts/' + wizard.draftId + '/fill-properties', {
            method: 'POST'
        }).then(function(data) {
            wizard.categoryPropertiesFilling = false;
            wizard.categoryPropertiesFilled = (data && data.properties) ? data.properties : [];
            saveDraft(true).then(function() {
                showStatus('saved', t('saved'));
                setTimeout(function() { clearStatus(); render(); }, 1500);
            }).catch(function() { render(); });
        }).catch(function(err) {
            wizard.categoryPropertiesFilling = false;
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function fetchDimensions() {
        if (!wizard.draftId || wizard.dimensionsLoading || wizard.dimensionsResult) return;
        wizard.dimensionsLoading = true;
        render();
        apiFetch('/emall/drafts/' + wizard.draftId + '/dimensions', { method: 'POST' })
            .then(function(data) {
                wizard.dimensionsLoading = false;
                wizard.dimensionsResult = {
                    width_mm: data.width_mm,
                    length_mm: data.length_mm,
                    height_mm: data.height_mm,
                    weight_g: data.weight_g
                };
                saveDraft(true);
                render();
            })
            .catch(function(err) {
                wizard.dimensionsLoading = false;
                wizard.dimensionsFetchTriggered = false;
                showStatus('error', (err && err.message) ? err.message : t('error'));
                render();
            });
    }

    function attachTextStepHandlers() {
        var titleEl = document.getElementById('genTitle');
        if (titleEl) {
            titleEl.addEventListener('input', function() { wizard.generatedText.title = titleEl.value; });
        }
        var descEl = document.getElementById('genDescCombined');
        if (descEl) {
            descEl.addEventListener('input', function() {
                var parsed = parseCombinedDescription(descEl.value);
                wizard.generatedText.description = parsed.description;
                wizard.generatedText.benefits = parsed.benefits;
                wizard.generatedText.usage = parsed.usage;
            });
        }
    }

    function startGenerateText() {
        if (!wizard.draftId || wizard.textGenerating) return;
        wizard.textGenerating = true;
        showStatus('saving', t('generating'));
        render();
        apiFetch('/emall/drafts/' + wizard.draftId + '/generate-text', { method: 'POST' })
            .then(function(data) {
                wizard.textGenerating = false;
                clearStatus();
                wizard.generatedText = {
                    title: (data && data.title) || '',
                    description: (data && data.description) || '',
                    benefits: (data && data.benefits) || '',
                    usage: (data && data.usage) || ''
                };
                render();
            })
            .catch(function(err) {
                wizard.textGenerating = false;
                showStatus('error', (err && err.message) ? err.message : t('error'));
                render();
            });
    }

    function saveTextAndContinue() {
        if (!wizard.draftId) return;
        var gt = wizard.generatedText || {};
        var title = (document.getElementById('genTitle') && document.getElementById('genTitle').value) || gt.title || '';
        var combinedRaw = (document.getElementById('genDescCombined') && document.getElementById('genDescCombined').value) || buildCombinedDescription(gt);
        var parsed = parseCombinedDescription(combinedRaw);
        wizard.generatedText = { title: title, description: parsed.description, benefits: parsed.benefits, usage: parsed.usage };
        showStatus('saving', t('saving'));
        render();
        apiFetch('/emall/drafts/' + wizard.draftId + '/text', {
            method: 'POST',
            body: { title: title, description: parsed.description, benefits: parsed.benefits, usage: parsed.usage }
        }).then(function() {
            wizard.currentStepIndex = 2;
            saveDraft(true);
            showStatus('saved', t('textSavedSuccess'));
            setTimeout(function() { clearStatus(); render(); }, 2500);
        }).catch(function(err) {
            showStatus('error', (err && err.message) ? err.message : t('error'));
            render();
        });
    }

    function loadEmallSavedKeyHint() {
        var el = document.getElementById('emallSavedKeyHint');
        if (!el) return;
        apiFetch('/me/emall-credentials')
            .then(function(data) {
                if (data && data.apiKeyMasked) {
                    el.textContent = (locale === 'ru' ? 'Ключ eMall берётся из личного кабинета. Сохранён ключ: ' : 'eMall key is taken from your cabinet. Saved key: ') + data.apiKeyMasked;
                } else {
                    el.textContent = (locale === 'ru' ? 'Ключ eMall не найден в личном кабинете. Перейдите в «Личный кабинет» и добавьте API‑ключ.' : 'eMall key not found in your cabinet. Go to "Cabinet" and add your API key.');
                }
            })
            .catch(function() {});
    }

    function attachEventHandlers() {
        var stepClickables = document.querySelectorAll('.stepper .step-clickable[data-step-index]');
        stepClickables.forEach(function(el) {
            el.addEventListener('click', function() {
                var idx = parseInt(el.getAttribute('data-step-index'), 10);
                if (!isNaN(idx) && idx >= 0 && idx <= wizard.currentStepIndex) {
                    wizard.currentStepIndex = idx;
                    if (idx === 0) wizard.autoGenerateStep2Triggered = false;
                    render();
                }
            });
        });
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
                    var desc = (wizard.editedJson && wizard.editedJson.description) || '';
                    var gt = wizard.generatedText || {};
                    if (desc && wizard.draftId && !wizard.textGenerating && !gt.title && !gt.description) {
                        setTimeout(startGenerateText, 300);
                    }
                    return;
                }
                saveDraft(true);
                setTimeout(startCategoryPipeline, 300);
            };
        }

        var saveTextBtn = document.getElementById('saveTextBtn');
        if (saveTextBtn) {
            saveTextBtn.onclick = function() { saveTextAndContinue(); };
        }

        var prevBtn = document.getElementById('prevBtn');
        if (prevBtn) prevBtn.onclick = function() {
            if (wizard.currentStepIndex > 0) {
                wizard.currentStepIndex--;
                if (wizard.currentStepIndex === 0) wizard.autoGenerateStep2Triggered = false;
                render();
            }
        };

        var generateTextBtn = document.getElementById('generateTextBtn');
        if (generateTextBtn) {
            generateTextBtn.onclick = function() { startGenerateText(); };
        }

        var calcPriceBtn = document.getElementById('calcPriceBtn');
        if (calcPriceBtn) {
            calcPriceBtn.onclick = function() {
                var priceZac = (document.getElementById('priceZacInput') && document.getElementById('priceZacInput').value) || '';
                var weight = (document.getElementById('weightInput') && document.getElementById('weightInput').value) || '1000';
                var margin = (document.getElementById('marginInput') && document.getElementById('marginInput').value) || '20';
                var result = calculateEmallPrice(priceZac, weight, margin);
                if (!result) {
                    showStatus('error', locale === 'ru' ? 'Проверьте введённые данные: закупочная цена и вес должны быть > 0, маржинальность 0–99%' : 'Check input: purchase price and weight must be > 0, margin 0–99%');
                    return;
                }
                wizard.priceResult = result;
                clearStatus();
                saveDraft(true);
                render();
            };
        }

        var continuePriceBtn = document.getElementById('continuePriceBtn');
        if (continuePriceBtn) {
            continuePriceBtn.onclick = function() {
                if (!wizard.priceResult || wizard.priceResult.price == null) {
                    showStatus('error', locale === 'ru' ? 'Сначала рассчитайте цену' : 'Calculate price first');
                    return;
                }
                wizard.currentStepIndex = 3;
                showStatus('saving', t('saving'));
                saveDraft(true).then(function() {
                    showStatus('saved', t('saved'));
                    setTimeout(function() { clearStatus(); render(); }, 1500);
                }).catch(function() {
                    render();
                });
            };
        }

        var continueDimensionsBtn = document.getElementById('continueDimensionsBtn');
        if (continueDimensionsBtn) {
            continueDimensionsBtn.onclick = function() {
                if (!wizard.brandSelected || !wizard.countrySelected) {
                    showStatus('error', locale === 'ru' ? 'Выберите бренд и страну производства' : 'Select brand and country');
                    return;
                }
                showStatus('saving', t('saving'));
                saveDraft(true).then(function() {
                    showStatus('saved', t('saved'));
                    wizard.currentStepIndex = 4;
                    setTimeout(function() { clearStatus(); render(); }, 1500);
                }).catch(function() {
                    render();
                });
            };
        }
        // Step 5 (Characteristics): first fill via DeepSeek, then allow manual edits and explicit save+continue.
        var fillPropertiesBtnTop = document.getElementById('fillPropertiesBtnTop');
        if (fillPropertiesBtnTop) {
            fillPropertiesBtnTop.onclick = function() {
                if (wizard.categoryPropertiesFilling) return;
                if (!wizard.categoryPropertiesList.length) {
                    showStatus('error', locale === 'ru' ? 'Сначала загрузите свойства категории' : 'Load category properties first');
                    return;
                }
                fillCategoryProperties();
            };
        }

        var saveContinuePropertiesBtnTop = document.getElementById('saveContinuePropertiesBtnTop');
        if (saveContinuePropertiesBtnTop) {
            saveContinuePropertiesBtnTop.onclick = function() {
                if (wizard.categoryPropertiesFilling) return;
                if (!wizard.categoryPropertiesFilled || !wizard.categoryPropertiesFilled.length) {
                    showStatus('error', locale === 'ru' ? 'Сначала заполните характеристики' : 'Fill characteristics first');
                    return;
                }
                showStatus('saving', t('saving'));
                saveDraft(true).then(function() {
                    showStatus('saved', t('saved'));
                    wizard.currentStepIndex = 5;
                    setTimeout(function() { clearStatus(); render(); }, 1500);
                }).catch(function() { render(); });
            };
        }

        // Brand typeahead (AJAX search after 4 chars)
        var brandInput = document.getElementById('brandInput');
        var brandSuggest = document.getElementById('brandSuggest');
        function hideBrandSuggest() {
            if (brandSuggest) brandSuggest.style.display = 'none';
        }
        function renderBrandSuggest(list) {
            if (!brandSuggest) return;
            if (!list || !list.length) {
                brandSuggest.style.display = 'none';
                brandSuggest.innerHTML = '';
                return;
            }
            var h = '';
            list.forEach(function(b) {
                h += '<div class="typeahead-item" data-id="' + b.id + '" data-name="' + escapeHtml(b.name) + '">' + escapeHtml(b.name) + '</div>';
            });
            brandSuggest.innerHTML = h;
            brandSuggest.style.display = 'block';
        }
        function searchBrands(q, opts) {
            if (!wizard.draftId) return Promise.resolve([]);
            var limit = (opts && opts.limit) ? opts.limit : 50;
            var refresh = (opts && opts.refresh) ? '&refresh=1' : '';
            var qs = '?draftId=' + encodeURIComponent(wizard.draftId) + '&q=' + encodeURIComponent(q) + '&limit=' + encodeURIComponent(limit) + refresh;
            return apiFetch('/emall/brands/search' + qs).then(function(data) {
                return (data && data.brands) ? data.brands : [];
            });
        }
        if (brandInput) {
            var debounceTimer = null;
            brandInput.oninput = function() {
                var q = String(brandInput.value || '').trim();
                // If user edits text manually, clear previous selection until they pick from list
                wizard.brandSelected = null;
                saveDraft(true);
                if (debounceTimer) clearTimeout(debounceTimer);
                if (q.length < 4) {
                    hideBrandSuggest();
                    return;
                }
                debounceTimer = setTimeout(function() {
                    searchBrands(q).then(function(list) {
                        wizard.brandsList = list;
                        renderBrandSuggest(list);
                    }).catch(function() {
                        hideBrandSuggest();
                    });
                }, 250);
            };

            if (brandSuggest) {
                brandSuggest.onclick = function(e) {
                    var el = e.target;
                    while (el && el !== brandSuggest && !(el.classList && el.classList.contains('typeahead-item'))) {
                        el = el.parentNode;
                    }
                    if (!el || el === brandSuggest) return;
                    var id = parseInt(el.getAttribute('data-id'), 10);
                    var name = el.getAttribute('data-name') || el.textContent || '';
                    wizard.brandSelected = { id: id, name: name };
                    wizard.manualBrandMode = false;
                    brandInput.value = name;
                    hideBrandSuggest();
                    saveDraft(true);
                    render();
                };
            }

            document.addEventListener('click', function(ev) {
                if (!brandSuggest) return;
                if (ev.target === brandInput || ev.target === brandSuggest || brandSuggest.contains(ev.target)) return;
                hideBrandSuggest();
            });
        }
        var countrySelect = document.getElementById('countrySelect');
        if (countrySelect) {
            countrySelect.onchange = function() {
                var opt = countrySelect.options[countrySelect.selectedIndex];
                if (opt && opt.value) {
                    wizard.countrySelected = { id: parseInt(opt.value, 10), name: opt.getAttribute('data-name') || opt.text };
                } else {
                    wizard.countrySelected = null;
                }
                saveDraft(true);
                render();
            };
        }
        var reloadBrandsBtn = document.getElementById('reloadBrandsBtn');
        if (reloadBrandsBtn) {
            reloadBrandsBtn.onclick = function() {
                // Refresh search cache and search by current input (do not preload the whole directory)
                var qv = '';
                var bi = document.getElementById('brandInput');
                if (bi) qv = String(bi.value || '').trim();
                if (qv.length < 1) {
                    showStatus('error', locale === 'ru' ? 'Введите бренд для поиска' : 'Type brand to search');
                    return;
                }
                searchBrands(qv, { refresh: true, limit: 50 }).then(function(list) {
                    wizard.brandsList = list;
                    renderBrandSuggest(list);
                }).catch(function(err) {
                    showStatus('error', (err && err.message) ? err.message : t('error'));
                });
            };
        }
        var selectBrandAgainBtn = document.getElementById('selectBrandAgainBtn');
        if (selectBrandAgainBtn) {
            selectBrandAgainBtn.onclick = function() {
                wizard.brandCountryPredicted = null;
                wizard.brandSelected = null;
                wizard.manualBrandMode = false;
                wizard.brandCountryFetchTriggered = false;
                fetchBrandCountryAndDirectories();
            };
        }
        var editBrandBtn = document.getElementById('editBrandBtn');
        if (editBrandBtn) {
            editBrandBtn.onclick = function() {
                wizard.manualBrandMode = true;
                // keep current selection as text for convenience
                render();
            };
        }
        var selectRandomBrandBtn = document.getElementById('selectRandomBrandBtn');
        if (selectRandomBrandBtn) {
            selectRandomBrandBtn.onclick = function() {
                var brands = wizard.brandsList || [];
                if (brands.length) {
                    var b = brands[Math.floor(Math.random() * brands.length)];
                    wizard.brandSelected = { id: b.id, name: b.name };
                    wizard.manualBrandMode = false;
                    render();
                }
            };
        }
        var reloadCountriesBtn = document.getElementById('reloadCountriesBtn');
        if (reloadCountriesBtn) {
            reloadCountriesBtn.onclick = function() {
                var q = '?draftId=' + encodeURIComponent(wizard.draftId);
                apiFetch('/emall/countries' + q).then(function(data) {
                    wizard.countriesList = data.countries || [];
                    render();
                }).catch(function(err) {
                    showStatus('error', (err && err.message) ? err.message : t('error'));
                });
            };
        }
        var selectCountryAgainBtn = document.getElementById('selectCountryAgainBtn');
        if (selectCountryAgainBtn) {
            selectCountryAgainBtn.onclick = function() {
                wizard.brandCountryPredicted = null;
                wizard.countrySelected = null;
                wizard.brandCountryFetchTriggered = false;
                fetchBrandCountryAndDirectories();
            };
        }
        var selectRandomCountryBtn = document.getElementById('selectRandomCountryBtn');
        if (selectRandomCountryBtn) {
            selectRandomCountryBtn.onclick = function() {
                var countries = wizard.countriesList || [];
                if (countries.length) {
                    var c = countries[Math.floor(Math.random() * countries.length)];
                    wizard.countrySelected = { id: c.id, name: c.name };
                    render();
                }
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

    function base64UrlEncodeUtf8(str) {
        try {
            // UTF-8 → base64url (no padding)
            var b64 = btoa(unescape(encodeURIComponent(str || '')));
            return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        } catch (e) {
            // If btoa fails, fall back to stripping risky chars
            return '';
        }
    }

    function moveDescriptionToB64(obj, key) {
        if (!obj || typeof obj !== 'object') return;
        var val = obj[key];
        if (typeof val !== 'string' || !val) return;
        obj[key + '_b64'] = base64UrlEncodeUtf8(val);
        delete obj[key];
    }

    function saveDraft(silent) {
        if (!wizard.draftId) return Promise.resolve();
        if (!silent) showStatus('saving', t('saving'));
        var patch = {
            description: (wizard.editedJson && wizard.editedJson.description) || '',
            current_step: wizard.currentStepIndex + 1,
            step_state: buildStepState()
        };
        // WAF (ModSecurity CRS 941120) may block long rich text as XSS. Send description as base64url.
        moveDescriptionToB64(patch, 'description');
        if (patch.step_state && patch.step_state.attributes) {
            moveDescriptionToB64(patch.step_state.attributes, 'description');
        }

        if (wizard.categorySelected && typeof wizard.categorySelected === 'object') {
            patch.chosen_category = wizard.categorySelected;
        }
        return apiFetch('/draft', {
            method: 'POST',
            body: { marketplace: MARKETPLACE, patch: patch }
        }).then(function() {
            if (!silent) {
                showStatus('saved', t('saved'));
                setTimeout(clearStatus, 2000);
            }
        }).catch(function(err) {
            showStatus('error', t('error') + ': ' + (err && err.message || ''));
            throw err;
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