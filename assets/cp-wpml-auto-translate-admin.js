/**
 * WPML Auto Translate Admin JavaScript
 *
 * @package WPML_Auto_Translate
 */
(function ($) {
    'use strict';

    // Check if required global object exists
    if (typeof CP_WPML_AUTO_TRANSLATE === 'undefined') {
        console.error('WPML Auto Translate: Required global object CP_WPML_AUTO_TRANSLATE is not defined.');
        return;
    }

    // Constants
    const SELECTORS = {
        actionRow: '.action-row',
        translateBtn: '.cp-wpml-auto-translate-btn',
        bulkTranslateBtn: '#cp-wpml-auto-translate-bulk-translate',
        languageModal: '#cp-wpml-auto-translate-language-modal',
        langSelect: '#cp-wpml-auto-translate-lang-select',
        translationPopup: '#cp-wpml-auto-translate-translation-popup',
        translationTable: '#cp-wpml-auto-translate-translation-table',
        translationTbody: '#cp-wpml-auto-translate-translation-tbody',
        googleTranslateElement: '#google_translate_element'
    };

    const CLASSES = {
        translateBtn: 'cp-wpml-auto-translate-btn',
        sourceText: 'cp-wpml-auto-translate-source',
        translationTarget: 'cp-wpml-auto-translate-translation-target',
        translationField: 'cp-wpml-auto-translate-translation-field'
    };

    // Language mapping for Google Translate
    const GOOGLE_LANG_MAP = {
        'kir': 'ky',
        'oci': 'oc',
        'bel': 'be',
        'he': 'iw',
        'snd': 'sd',
        'jv': 'jw',
        'nb': 'no',
        'nn': 'no',
        'pt-br': 'pt',
        'zh-hans': 'zh-CN',
        'zh-hant': 'zh-TW',
        'zh': 'zh-CN'
    };

    // Google Translate supported languages (2-letter codes)
    const GOOGLE_SUPPORTED_LANGUAGES = [
        'af', 'sq', 'am', 'ar', 'hy', 'az', 'eu', 'be', 'bn', 'bs', 'bg', 'ca', 'ceb', 'ny', 'zh-CN', 'zh-TW', 'co', 'hr', 'cs', 'da', 'nl', 'en', 'eo', 'et', 'tl', 'fi', 'fr', 'fy', 'gl', 'ka', 'de', 'el', 'gu', 'ht', 'ha', 'haw', 'iw', 'hi', 'hmn', 'hu', 'is', 'ig', 'id', 'ga', 'it', 'ja', 'jw', 'kn', 'kk', 'km', 'rw', 'ko', 'ku', 'ky', 'lo', 'la', 'lv', 'lt', 'lb', 'mk', 'mg', 'ms', 'ml', 'mt', 'mi', 'mr', 'mn', 'my', 'ne', 'no', 'ps', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sm', 'gd', 'sr', 'st', 'sn', 'sd', 'si', 'sk', 'sl', 'so', 'es', 'su', 'sw', 'sv', 'tg', 'ta', 'tt', 'te', 'th', 'tr', 'uk', 'ur', 'uz', 'vi', 'cy', 'xh', 'yi', 'yo', 'zu'
    ];

    /**
     * Check if Google Translate supports a language
     */
    function isGoogleTranslateSupported(targetLang) {
        if (!targetLang) {
            return false;
        }
        
        let googleLang = targetLang.toLowerCase();
        
        // Check mapped languages first
        if (GOOGLE_LANG_MAP[googleLang]) {
            googleLang = GOOGLE_LANG_MAP[googleLang];
        } else if (googleLang.indexOf('-') !== -1) {
            // Handle language variants like zh-hans, pt-br
            const parts = googleLang.split('-');
            googleLang = parts[0];
            
            // Special cases
            if (googleLang === 'zh') {
                googleLang = 'zh-CN';
            }
        }
        
        // Check if the language is in the supported list
        return GOOGLE_SUPPORTED_LANGUAGES.indexOf(googleLang) !== -1;
    }

    /**
     * Map WPML language code to Google Translate language code
     */
    function mapLanguageForGoogle(targetLang) {
        let googleLang = targetLang.toLowerCase();
        if (GOOGLE_LANG_MAP[googleLang]) {
            googleLang = GOOGLE_LANG_MAP[googleLang];
        } else if (googleLang.indexOf('-') !== -1) {
            const parts = googleLang.split('-');
            googleLang = parts[0];
        }
        return googleLang;
    }

    /**
     * Extract page ID from checkbox ID
     *
     * @param {jQuery} $row Row element
     * @returns {string|null} Page ID or null
     */
    function extractPageId($row) {
        if (!$row || !$row.length) {
            return null;
        }
        const checkboxId = $row.find('td.checkboxes input[type="checkbox"]').attr('id');
        return checkboxId ? checkboxId.replace(/^\D+/, '') : null;
    }


    /**
     * Ensure every .action-row has a "Translate by Google" button
     *
     * @param {Element|jQuery} root Root element to search within
     * @returns {void}
     */
    function ensureGoogleButtons(root) {
        if (!root) {
            return;
        }
        $(root).find(SELECTORS.actionRow).each(function () {
            const $actionRow = $(this);

            if ($actionRow.find(SELECTORS.translateBtn).length) {
                return;
            }

            const $btn = $(
                '<button class="wpml-button base-btn text-button ' + CLASSES.translateBtn + '">Translate by Google</button>'
            );

            $actionRow.append('<span class="link-separator">|</span>');
            $actionRow.append($btn);

            $btn.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $row = $(this).closest('tr');
                const pageId = extractPageId($row);

                if (!pageId) {
                    alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorPageId || 'Could not detect page ID for this row.');
                    return;
                }

                showLanguageModal([pageId]);
            });
        });
    }

    /**
     * Initialize bulk translate button
     *
     * @returns {void}
     */
    function initBulkTranslateButton() {
        const $section = $('.wpml-dashboard__SelectionSection .wpml-global-filter-wrapper .wpml-global-filter .wpml-flex-space-between');

        if ($section.length) {
            // Check if button already exists
            if ($section.find(SELECTORS.bulkTranslateBtn).length) {
                return;
            }

            const $translateBtn = $('<button type="button" class="button button-primary" id="' + SELECTORS.bulkTranslateBtn.replace('#', '') + '">Translate with Google</button>');
            $section.append($translateBtn);

            $translateBtn.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const selectedPageIds = $('.wpml-item-type-element-list table tbody tr')
                    .filter(function () {
                        return $(this).find('td.checkboxes input[type="checkbox"][aria-checked="true"]').length > 0;
                    })
                    .map(function () {
                        return extractPageId($(this));
                    })
                    .get()
                    .filter(function (id) {
                        return id !== null && id !== '';
                    });

                if (!selectedPageIds.length) {
                    alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorNoSelection || 'Please select at least one post to translate.');
                    return;
                }

                showLanguageModal(selectedPageIds);
            });
        }
    }

    /**
     * Create language selection modal
     */
    function createLanguageModal() {
        const modalHtml = `
            <div id="${SELECTORS.languageModal.replace('#', '')}" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:100000; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:20px; border-radius:5px; max-width:500px; width:90%; max-height:80vh; overflow-y:auto;">
                    <h2 style="margin-top:0;">Select Target Language</h2>
                    <p>Choose one language to translate the selected post.</p>
                    <div style="margin:15px 0;">
                        <select id="${SELECTORS.langSelect.replace('#', '')}" style="width:100%; padding:8px; font-size:14px;">
                            <option value="">Select Language</option>
                        </select>
                    </div>
                    <div style="margin-top:20px; text-align:right;">
                        <button type="button" class="button" id="cp-wpml-auto-translate-modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="cp-wpml-auto-translate-modal-next" style="margin-left:10px;">Next</button>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHtml);
    }

    /**
     * Show language selection modal
     *
     * @param {Array<number>} selectedIds Array of selected post IDs
     * @returns {void}
     */
    function showLanguageModal(selectedIds) {
        if (!selectedIds || !Array.isArray(selectedIds) || selectedIds.length === 0) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorNoSelection || 'No posts selected.');
            return;
        }

        const $modal = $(SELECTORS.languageModal);
        const $langSelect = $(SELECTORS.langSelect);

        if (!$modal.length || !$langSelect.length) {
            console.error('Language modal elements not found');
            return;
        }

        $langSelect.empty();
        $langSelect.append('<option value="">Loading languages...</option>');

        $.post(CP_WPML_AUTO_TRANSLATE.ajax, {
            action: 'cp_wpml_google_auto_translate_get_pending_languages',
            nonce: CP_WPML_AUTO_TRANSLATE.nonce,
            ids: selectedIds
        })
            .done(function (resp) {
                $langSelect.empty();

                if (resp && resp.success && resp.data && resp.data.languages) {
                    const pendingLanguages = resp.data.languages;

                    if (pendingLanguages.length > 0) {
                        $langSelect.append('<option value="">Select Language</option>');
                        pendingLanguages.forEach(function (lang) {
                            if (lang && lang.code && lang.name) {
                                $langSelect.append('<option value="' + esc_attr(lang.code) + '">' + esc_html(lang.name) + ' (' + esc_html(lang.code) + ')</option>');
                            }
                        });
                    } else {
                        $langSelect.append('<option value="">All languages already have translations</option>');
                    }
                } else {
                    const errorMsg = (resp && resp.data && resp.data.msg) ? resp.data.msg : 'No languages available';
                    $langSelect.append('<option value="">' + esc_html(errorMsg) + '</option>');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Failed to load languages:', status, error);
                $langSelect.empty();
                $langSelect.append('<option value="">Error loading languages</option>');
            });

        $modal.data('selected-ids', selectedIds);
        $modal.css('display', 'flex');
    }

    /**
     * Escape HTML entities
     *
     * @param {string} text Text to escape
     * @returns {string} Escaped text
     */
    function esc_html(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Escape HTML attributes
     *
     * @param {string} text Text to escape
     * @returns {string} Escaped text
     */
    function esc_attr(text) {
        return esc_html(text).replace(/"/g, '&quot;');
    }

    /**
     * Hide language modal
     */
    function hideLanguageModal() {
        $(SELECTORS.languageModal).css('display', 'none');
    }

    /**
     * Open translation table popup
     *
     * @param {number} postId Post ID
     * @param {string} targetLang Target language code
     * @returns {void}
     */
    function openTranslationTablePopup(postId, targetLang) {
        if (!postId || !targetLang) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorInvalidData || 'Invalid post ID or language.');
            return;
        }

        // Check if Google Translate supports this language
        if (!isGoogleTranslateSupported(targetLang)) {
            const langName = CP_WPML_AUTO_TRANSLATE.languages && Array.isArray(CP_WPML_AUTO_TRANSLATE.languages) 
                ? CP_WPML_AUTO_TRANSLATE.languages.find(function(l) { return l && l.code === targetLang; })
                : null;
            const langDisplayName = langName ? langName.name : targetLang;
            alert('Sorry, Google Translate does not support "' + esc_html(langDisplayName) + '" language. Please select a different language.');
            return;
        }

        $(SELECTORS.translationPopup).remove();

        const modalHtml = `
            <div id="${SELECTORS.translationPopup.replace('#', '')}" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:100001; display:flex; align-items:center; justify-content:center;">
                <div style="background:#fff; max-width:1200px; width:95%; max-height:90vh; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.2); display:flex; flex-direction:column;">
                    <div style="background:#4CAF50; color:#fff; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
                        <h2 style="margin:0; color:#fff; font-size:18px;">Start Automatic Translation Process</h2>
                        <div>
                            <button type="button" class="button button-primary" id="cp-wpml-auto-translate-translation-save" style="background:#ccc; color:#666; border:none; margin-right:10px; cursor:not-allowed;" disabled>Update Content</button>
                            <button type="button" id="cp-wpml-auto-translate-translation-close" style="background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer; padding:0 10px;">&times;</button>
                        </div>
                    </div>
                    <div style="padding:20px; overflow-y:auto; flex:1;">
                        <div style="margin-bottom:20px; display:flex; align-items:center; gap:15px;">
                            <h3 style="margin:0; color:#4CAF50; display:flex; align-items:center; gap:8px;">
                                <span style="font-size:20px;">A文</span> Choose Language
                            </h3>
                        </div>
                        <div style="margin-bottom:20px;">
                            <h4 style="margin:0 0 10px 0;">Google Translator</h4>
                            <div id="${SELECTORS.googleTranslateElement.replace('#', '')}" style="min-height:40px;"></div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table id="${SELECTORS.translationTable.replace('#', '')}" style="width:100%; border-collapse:collapse; border:1px solid #ddd;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:12px; text-align:left; border:1px solid #ddd; width:60px;">S.No</th>
                                        <th style="padding:12px; text-align:left; border:1px solid #ddd;">Source Text</th>
                                        <th style="padding:12px; text-align:left; border:1px solid #ddd;">Translation</th>
                                    </tr>
                                </thead>
                                <tbody id="${SELECTORS.translationTbody.replace('#', '')}">
                                    <tr>
                                        <td colspan="3" style="padding:20px; text-align:center; color:#666;">Loading content...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="background:#4CAF50; padding:15px 20px; text-align:right;">
                        <button type="button" class="button button-primary" id="cp-wpml-auto-translate-translation-save-footer" style="background:#ccc; color:#666; border:none; padding:10px 20px; font-weight:bold; cursor:not-allowed;" disabled>Update Content</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        const $modal = $(SELECTORS.translationPopup);
        $modal.data('post-id', postId);
        $modal.data('target-lang', targetLang);

        $.post(CP_WPML_AUTO_TRANSLATE.ajax, {
            action: 'cp_wpml_google_auto_translate_get_post_contents',
            nonce: CP_WPML_AUTO_TRANSLATE.nonce,
            ids: [postId],
            target_lang: targetLang
        })
            .done(function (resp) {
                if (resp && resp.success && resp.data && resp.data[postId]) {
                    const postData = resp.data[postId];
                    window.CurrentWPMLPackage = postData.package || null;
                    $(SELECTORS.translationPopup).data(
                        'payload',
                        postData.original_content
                    );
                    populateTranslationTable(postData, targetLang);
                    initGoogleTranslateWidget(targetLang);
                } else {
                    const errorMsg = (resp && resp.data && resp.data.msg) ? resp.data.msg : 'Failed to load post content.';
                    console.error('Failed to load post content:', errorMsg);
                    alert(esc_html(errorMsg));
                    $modal.remove();
                }
            })
            .fail(function (xhr, status, error) {
                console.error('AJAX error while loading content:', status, error);
                alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorAjax || 'AJAX error while loading content.');
                $modal.remove();
            });

        $(document).off('click', '#cp-wpml-auto-translate-translation-close, ' + SELECTORS.translationPopup).on('click', '#cp-wpml-auto-translate-translation-close', function () {
            $(SELECTORS.translationPopup).remove();
        });

        $modal.on('click', function (e) {
            if (e.target.id === SELECTORS.translationPopup.replace('#', '')) {
                $(SELECTORS.translationPopup).remove();
            }
        });

        $(document).off('click', '#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer').on('click', '#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!$(this).prop('disabled')) {
                saveTranslationFromTable(postId, targetLang);
            }
        });

        // Listen for manual edits in translation fields
        $(document).on('input change', '.' + CLASSES.translationField + '.target', function() {
            const $field = $(this);
            // Update the data attribute with the current text content (user edits are plain text)
            const currentText = $field.text();
            $field.attr('data-translated-html', currentText);
            updateSaveButtonState();
        });
    }

    /**
     * Populate translation table with post content using WPML package data
     */
    function populateTranslationTable(postData, targetLang) {
        const $tbody = $(SELECTORS.translationTbody);
        $tbody.empty();

        const contentItems = [];
        const editorType = postData.editor_type;

        // Store editor type and original content
        $(SELECTORS.translationPopup).data('editor-type', editorType);
        
        if (postData.original_content) {
            if (editorType === 'elementor') {
                $(SELECTORS.translationPopup).data('original-elementor', postData.original_content);
            } else if (editorType === 'block') {
                $(SELECTORS.translationPopup).data('original-blocks', postData.original_content);
            } else {
                $(SELECTORS.translationPopup).data('original-content', postData.original_content);
            }
        }

        // Store WPML package for reference
        if (postData.package) {
            $(SELECTORS.translationPopup).data('wpml-package', postData.package);
        }

        // Add title as first item
        if (postData.title && postData.title.trim()) {
            contentItems.push({
                type: 'title',
                text: postData.title.trim(),
                field_name: 'title',
                field_key: 'title',          // 👈 IMPORTANT
                html: postData.title.trim(),
                format: 'text'
            });
        }

        // Use WPML strings array - each string is already extracted individually
        if (postData.strings && Array.isArray(postData.strings)) {
            postData.strings.forEach(function(stringData) {
                if (stringData.text && stringData.text.trim()) {
                    contentItems.push({
                        type: 'content',
                        text: stringData.text.trim(),
                        field_name: stringData.field_name,
                        field_key: stringData.field_key || stringData.field_name || '',    // 👈 IMPORTANT
                        html: stringData.html || stringData.text,
                        format: stringData.format || 'text'
                    });
                }
            });
        }

        // Populate table rows
        contentItems.forEach(function(item, index) {
            const rowNum = index + 1;
            const $row = $('<tr>')
                .attr('data-field-key', item.field_key || '');
        
            const escapedSourceText = $('<div>').text(item.text).html();
            let sourceHtml = escapedSourceText;
            if (item.html && item.html !== item.text) {
                sourceHtml = item.html;
            }
            if (item.settingValue && item.settingValue !== item.text) {
                sourceHtml = item.settingValue;
            }
        
            const $numCell = $('<td>')
                .css({'padding': '12px', 'border': '1px solid #ddd'})
                .text(rowNum);
        
            const $sourceCell = $('<td>')
                .addClass(CLASSES.sourceText)
                .attr('data-original-html', sourceHtml) // Store original HTML for saving
                .css({'padding': '12px', 'border': '1px solid #ddd', 'background': '#f9f9f9', 'white-space': 'pre-wrap', 'font-family': 'inherit'})
                .text(sourceHtml);
        
            const $translationCell = $('<td>')
                .css({'padding': '12px', 'border': '1px solid #ddd', 'position': 'relative'});
        
            // Check if source has HTML tags
            const hasHtml = sourceHtml && /<[^>]+>/.test(sourceHtml);
            const plainText = hasHtml ? $('<div>').html(sourceHtml).text().trim() : sourceHtml;
            
            const $translationTarget = $('<div>')
                .attr('translate', 'yes')
                .addClass(CLASSES.translationTarget)
                .attr('data-type', item.type)
                .attr('data-index', index)
                .attr('data-field-key', item.field_key || '')
                .attr('data-original-html', sourceHtml) // Store original HTML for reconstruction
                .attr('data-plain-text', plainText) // Store plain text for better translation
                .css({
                    'position': 'absolute',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%',
                    'opacity': '0',
                    'pointer-events': 'none',
                    'z-index': '-1'
                });
            
            // Use plain text for Google Translate (translates better than HTML)
            if (hasHtml) {
                $translationTarget.text(plainText);
            } else {
                $translationTarget.html(sourceHtml);
            }
        
            const $translationField = $('<div>')
                .addClass(CLASSES.translationField + ' target')
                .attr('data-type', item.type)
                .attr('data-index', index)
                .attr('data-field-key', item.field_key || '')
                .attr('data-translated-html', sourceHtml) // Store HTML for saving
                .attr('contenteditable', 'true')
                .css({'padding': '8px', 'font-family': 'inherit', 'font-size': '14px', 'background': '#fff', 'white-space': 'pre-wrap'})
                .text(sourceHtml);
        
            $translationCell.append($translationTarget);
            $translationCell.append($translationField);
        
            $row.append($numCell);
            $row.append($sourceCell);
            $row.append($translationCell);
        
            $tbody.append($row);
        });
        

        $(SELECTORS.translationPopup).data('content-items', contentItems);
        
        // Initially disable save buttons
        updateSaveButtonState();
    }

    /**
     * Check if translations have been updated and enable/disable save buttons accordingly
     */
    function updateSaveButtonState() {
        const $saveBtns = $('#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer');
        let hasTranslations = false;

        // Check if any translation field has been updated (different from source)
        $('.' + CLASSES.translationField + '.target').each(function() {
            const $field = $(this);
            const $row = $field.closest('tr');
            const $sourceCell = $row.find('td.' + CLASSES.sourceText);
            const sourceText = $sourceCell.text().trim();
            const translatedText = $field.text().trim();

            // If translation exists and is different from source, enable buttons
            if (translatedText && translatedText !== sourceText) {
                hasTranslations = true;
                return false; // Break loop
            }
        });

        // Enable or disable buttons based on whether translations exist
        if (hasTranslations) {
            $saveBtns.prop('disabled', false)
                .css({
                    'background': '#fff',
                    'color': '#4CAF50',
                    'cursor': 'pointer'
                });
        } else {
            $saveBtns.prop('disabled', true)
                .css({
                    'background': '#ccc',
                    'color': '#666',
                    'cursor': 'not-allowed'
                });
        }
    }

    /**
     * Update Elementor element using elementPath to directly locate and update the value
     * @param {Array} elements - Elementor elements array
     * @param {string} elementPath - Path to the element (e.g., "0.elements.0.settings.title" or "0.settings.key[0].repeaterKey")
     * @param {string} translatedText - Translated text to set
     * @returns {Array} - Updated elements array
     */

    function cpWpmlGoogleReset() {
      $('#\\:1\\.container').contents().find('#\\:1\\.restore').click();
    }
    
    /**
     * Save translation from table
     *
     * @param {number} postId Post ID
     * @param {string} targetLang Target language code
     * @returns {void}
     */
    function saveTranslationFromTable(postId, targetLang) {
        if (!postId || !targetLang) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorInvalidData || 'Invalid post ID or language.');
            return;
        }

        const strings = [];
        const payload = $(SELECTORS.translationPopup).data('payload');
        $('.' + CLASSES.translationField + '.target').each(function () {
            const $field      = $(this);
            const fieldKey    = $field.data('field-key') || $field.closest('tr').data('field-key') || '';
            const type        = $field.data('type') || 'content';
            // Get translated HTML from data attribute, fallback to text content
            const translated  = ($field.attr('data-translated-html') || $field.text()).trim();
    
            if (!fieldKey) {
                console.warn('Missing field_key for row, skipping', $field);
                return;
            }
            const $sourceCell = $field.closest('tr').find('td.' + CLASSES.sourceText);
            const original = $sourceCell.attr('data-original-html') || $sourceCell.text().trim();

            strings.push({
                field_key: fieldKey,
                translated: translated,
                original: original,
                type: type,
                translate: 1
            });
        });

        if (strings.length === 0) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorNoStrings || 'No translation strings found.');
            return;
        }

        $.post(CP_WPML_AUTO_TRANSLATE.ajax, {
            action: 'cp_wpml_google_auto_translate_save_translation',
            nonce: CP_WPML_AUTO_TRANSLATE.nonce,
            post_id: postId,
            target_lang: targetLang,
            translated_strings: strings,
            payload: payload
        })
        .done(function (resp) {
            if (resp && resp.success) {
                hideLanguageModal();
                cpWpmlGoogleReset();
                
                const editorType = $(SELECTORS.translationPopup).data('editor-type');
                const adminBase = CP_WPML_AUTO_TRANSLATE.admin_url || CP_WPML_AUTO_TRANSLATE.ajax.replace('/admin-ajax.php', '');
                
                // Check if it's a Gutenberg block page - redirect to editor
                if (resp.data && resp.data.is_blocks && resp.data.translated_post_id) {
                    // Redirect to the Gutenberg editor for the translated post
                    const translatedPostId = resp.data.translated_post_id;
                    const editUrl = adminBase + 'post.php?post=' + translatedPostId + '&action=edit';
                    setTimeout(function() {
                        window.location.href = editUrl;
                    }, 500);
                } else {
                    // For other editors, reload current page
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }
            } else {
                const errorMsg = (resp && resp.data && resp.data.msg) ? resp.data.msg : (CP_WPML_AUTO_TRANSLATE.i18n?.errorUnknown || 'Unknown error');
                alert(esc_html(errorMsg));
            }
        })
        .fail(function (xhr, status, error) {
            console.error('AJAX error while saving:', status, error);
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorAjaxSave || 'AJAX error while saving.');
        });
    }

    /**
     * Initialize Google Translate widget
     */
    function initGoogleTranslateWidget(targetLang) {
        const googleLang = mapLanguageForGoogle(targetLang);
        const $widgetContainer = $(SELECTORS.googleTranslateElement);

        if (!$widgetContainer.length) {
            console.error('Google Translate element container not found');
            return;
        }

        $widgetContainer.empty();

        let initAttempts = 0;
        const maxAttempts = 20;

        function tryInitGoogleTranslate() {
            initAttempts++;

            const $container = $(SELECTORS.googleTranslateElement);
            if (!$container.length) {
                if (initAttempts < maxAttempts) {
                    setTimeout(tryInitGoogleTranslate, 200);
                }
                return;
            }

            if (typeof google === 'undefined' || !google.translate) {
                if (initAttempts < maxAttempts) {
                    setTimeout(tryInitGoogleTranslate, 200);
                }
                return;
            }

            if ($container.find('.goog-te-combo').length > 0) { 
                monitorGoogleTranslation();
                return;
            }

            if (typeof cpWpmlGTranslateWidget === 'function') {
                cpWpmlGTranslateWidget(googleLang);
            } else {
                if (googleLang === 'zh' || googleLang === 'zh-CN' || googleLang === 'zh-hans') {
                    new google.translate.TranslateElement(
                        {
                            pageLanguage: 'en',
                            includedLanguages: 'zh-CN,zh-TW',
                            defaultLanguage: 'zh-CN',
                            multilanguagePage: true
                        },
                        SELECTORS.googleTranslateElement.replace('#', '')
                    );
                } else {
                    new google.translate.TranslateElement(
                        {
                            pageLanguage: 'en',
                            includedLanguages: googleLang,
                            defaultLanguage: googleLang,
                            multilanguagePage: true
                        },
                        SELECTORS.googleTranslateElement.replace('#', '')
                    );
                }
            }

            setTimeout(function() {
                monitorGoogleTranslation();
            }, 1000);
        }

        setTimeout(tryInitGoogleTranslate, 300);
    }


    /**
     * Monitor Google Translate widget translation
     */
    function monitorGoogleTranslation() {
        let lastExtractionTime = 0;
        const extractionCooldown = 2000;
        let translationPerformed = false;

        $(document).on('change', '.goog-te-combo', function() {
            const selectedLang = $(this).val();
            if (selectedLang && selectedLang !== '') {
                translationPerformed = true;
                setTimeout(function() {
                    const now = Date.now();
                    if (now - lastExtractionTime > extractionCooldown) {
                        lastExtractionTime = now;
                        extractTranslatedText();
                    }
                }, 2000);
            }
        });

        const observer = new MutationObserver(function(mutations) {
            const $translatedElements = $('.goog-te-banner-frame, .skiptranslate');
            if ($translatedElements.length && translationPerformed) {
                const now = Date.now();
                if (now - lastExtractionTime > extractionCooldown) {
                    lastExtractionTime = now;
                    setTimeout(extractTranslatedText, 1000);
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        let checkCount = 0;
        const maxChecks = 60;
        const checkInterval = setInterval(function() {
            checkCount++;
            const selectedLang = $('.goog-te-combo option:selected').val();
            if (selectedLang && selectedLang !== '' && translationPerformed) {
                const now = Date.now();
                if (now - lastExtractionTime > extractionCooldown) {
                    lastExtractionTime = now;
                    setTimeout(extractTranslatedText, 1000);
                }
            }

            if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
            }
        }, 1000);
    }

    /**
     * Reconstruct HTML with translated text while preserving tag structure
     * This function replaces text content in HTML tags while keeping the tag structure intact
     */
    function reconstructHtmlWithTranslation(originalHtml, translatedText) {
        if (!originalHtml || !/<[^>]+>/.test(originalHtml)) {
            return translatedText;
        }
        
        // Create a temporary container to parse the original HTML
        const $original = $('<div>').html(originalHtml);
        const originalText = $original.text().trim();
        
        // If original text matches exactly, we can do a simple replacement
        if (originalText === $('<div>').html(originalHtml).text().trim()) {
            // Extract all text nodes and their positions
            const textNodes = [];
            const walker = document.createTreeWalker(
                $original[0],
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            let node;
            while (node = walker.nextNode()) {
                const text = node.textContent.trim();
                if (text) {
                    textNodes.push({
                        node: node,
                        text: text,
                        fullText: node.textContent
                    });
                }
            }
            
            // If we have multiple text nodes, try to distribute translated text proportionally
            if (textNodes.length > 1) {
                const totalOriginalLength = originalText.length;
                const translatedWords = translatedText.split(/\s+/);
                const originalWords = originalText.split(/\s+/);
                
                // Calculate ratio for word distribution
                const wordRatio = translatedWords.length / originalWords.length;
                
                let wordIndex = 0;
                textNodes.forEach(function(textNode) {
                    const nodeWords = textNode.text.split(/\s+/);
                    const nodeWordCount = nodeWords.length;
                    const translatedWordCount = Math.round(nodeWordCount * wordRatio);
                    const translatedWordsForNode = translatedWords.slice(wordIndex, wordIndex + translatedWordCount);
                    wordIndex += translatedWordCount;
                    
                    if (translatedWordsForNode.length > 0) {
                        // Replace the text content while preserving whitespace
                        const newText = textNode.fullText.replace(textNode.text, translatedWordsForNode.join(' '));
                        textNode.node.textContent = newText;
                    }
                });
                
                return $original.html();
            } else if (textNodes.length === 1) {
                // Single text node - simple replacement
                textNodes[0].node.textContent = translatedText;
                return $original.html();
            }
        }
        
        // Fallback: Try to preserve the outermost tag structure
        const firstElement = $original[0].querySelector('*');
        if (firstElement) {
            // Clone the structure but replace all text with translated text
            const $cloned = $original.clone();
            $cloned.find('*').contents().filter(function() {
                return this.nodeType === 3; // TEXT_NODE
            }).each(function() {
                if ($.trim(this.textContent)) {
                    this.textContent = translatedText;
                }
            });
            return $cloned.html();
        }
        
        // Last resort: wrap translated text in a span (preserves some structure)
        return '<span>' + translatedText + '</span>';
    }

    /**
     * Extract translated text from DOM and populate translation divs
     */
    function extractTranslatedText() {
        let hasUpdates = false;

        $('.' + CLASSES.translationTarget).each(function() {
            const $targetDiv = $(this);
            const $row = $targetDiv.closest('tr');
            const $sourceCell = $row.find('td.' + CLASSES.sourceText);
            const $translationDiv = $row.find('.' + CLASSES.translationField + '.target');
            const sourceText = $sourceCell.text().trim();
            
            // Get translated text from target div (Google Translate modifies this)
            // Since we're using plain text for HTML content, we get the text directly
            let translatedText = $targetDiv.text().trim();
            
            // Also try to get HTML in case Google Translate added wrapper elements
            let translatedHtml = $targetDiv.html() || '';
            
            // Clean up Google Translate wrapper elements if present
            if (translatedHtml && translatedHtml !== translatedText) {
                const $temp = $('<div>').html(translatedHtml);
                $temp.find('.goog-te-spinner-pos, .goog-te-banner-frame, .skiptranslate, .goog-te-banner').remove();
                $temp.find('font[dir="auto"], font[style*="vertical-align: inherit"]').each(function() {
                    const $font = $(this);
                    $font.replaceWith($font.contents());
                });
                translatedText = $temp.text().trim() || translatedText;
            }

            if (translatedText && translatedText !== sourceText) {
                const currentText = $translationDiv.text().trim();
                if (!currentText || currentText === sourceText) {
                    // Check if original had HTML tags
                    const originalHtml = $sourceCell.attr('data-original-html') || $sourceCell.text();
                    const hasOriginalHtml = originalHtml && /<[^>]+>/.test(originalHtml);
                    
                    if (hasOriginalHtml) {
                        // Original had HTML - reconstruct HTML with translated text
                        // Google Translate translated the plain text, now we reconstruct HTML structure
                        const reconstructedHtml = reconstructHtmlWithTranslation(originalHtml, translatedText);
                        
                        // Store reconstructed HTML in data attribute and display as literal text
                        $translationDiv.attr('data-translated-html', reconstructedHtml);
                        $translationDiv.text(reconstructedHtml);
                    } else {
                        // Plain text - store and display as text
                        $translationDiv.attr('data-translated-html', translatedText);
                        $translationDiv.text(translatedText);
                    }
                    
                    $translationDiv.trigger('change');
                    hasUpdates = true;
                }
            }
        });

        if (hasUpdates) {
            // Enable save buttons when translations are updated
            updateSaveButtonState();
        }
    }

    // Event handlers
    $(document).on('click', '#cp-wpml-auto-translate-modal-cancel', function () {
        hideLanguageModal();
    });

    $(document).on('click', '#cp-wpml-auto-translate-modal-next', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $modal = $(SELECTORS.languageModal);
        const selectedIds = $modal.data('selected-ids') || [];
        const selectedLanguage = $(SELECTORS.langSelect).val();

        if (!selectedLanguage) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorNoLanguage || 'Please select a target language.');
            return;
        }

        if (!selectedIds.length) {
            alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorNoSelection || 'No posts selected for translation.');
            return;
        }

        // Check if Google Translate supports this language
        if (!isGoogleTranslateSupported(selectedLanguage)) {
            const langName = CP_WPML_AUTO_TRANSLATE.languages && Array.isArray(CP_WPML_AUTO_TRANSLATE.languages)
                ? CP_WPML_AUTO_TRANSLATE.languages.find(function(l) { return l && l.code === selectedLanguage; })
                : null;
            const langDisplayName = langName ? langName.name : selectedLanguage;
            alert('Sorry, Google Translate does not support "' + esc_html(langDisplayName) + '" language. Please select a different language.');
            return;
        }

        hideLanguageModal();
        openTranslationTablePopup(selectedIds[0], selectedLanguage);
    });

    $(document).on('click', SELECTORS.languageModal, function (e) {
        if ($(e.target).attr('id') === SELECTORS.languageModal.replace('#', '')) {
            hideLanguageModal();
        }
    });

    /**
     * Initialize row action translate button
     *
     * @returns {void}
     */
    function initRowActionTranslateButton() {
        $(document).on('click', '.cp-wpml-row-translate-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const postId = $(this).data('post-id');
            
            if (!postId) {
                alert(CP_WPML_AUTO_TRANSLATE.i18n?.errorPageId || 'Could not detect post ID.');
                return;
            }
            
            showLanguageModal([postId]);
        });
    }

    // Initialize
    ensureGoogleButtons(document);
    initBulkTranslateButton();
    createLanguageModal();
    initRowActionTranslateButton();

    // Watch DOM changes
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                $(mutation.addedNodes).each(function () {
                    if (this.nodeType !== 1) return;
                    ensureGoogleButtons(this);
                });
            }
        });
    });

    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})(jQuery);
