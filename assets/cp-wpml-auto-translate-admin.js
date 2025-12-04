jQuery(function ($) {
    'use strict';

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
     */
    function extractPageId($row) {
        const checkboxId = $row.find('td.checkboxes input[type="checkbox"]').attr('id');
        return checkboxId ? checkboxId.replace(/^\D+/, '') : null;
    }


    /**
     * Ensure every .action-row has a "Translate by Google" button
     */
    function ensureGoogleButtons(root) {
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
                const $row = $(this).closest('tr');
                const pageId = extractPageId($row);

                if (!pageId) {
                    alert('Could not detect page ID for this row.');
                    return;
                }

                showLanguageModal([pageId]);
            });
        });
    }

    /**
     * Initialize bulk translate button
     */
    function initBulkTranslateButton() {
        const $section = $('.wpml-dashboard__SelectionSection .wpml-global-filter-wrapper .wpml-global-filter .wpml-flex-space-between');

        if ($section.length) {
            const $translateBtn = $('<button type="button" class="button button-primary" id="' + SELECTORS.bulkTranslateBtn.replace('#', '') + '">Translate with Google</button>');
            $section.append($translateBtn);

            $translateBtn.on('click', function () {
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
                    alert('Please select at least one post to translate.');
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
     */
    function showLanguageModal(selectedIds) {
        const $modal = $(SELECTORS.languageModal);
        const $langSelect = $(SELECTORS.langSelect);

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
                            $langSelect.append('<option value="' + lang.code + '">' + lang.name + ' (' + lang.code + ')</option>');
                        });
                    } else {
                        $langSelect.append('<option value="">All languages already have translations</option>');
                    }
                } else {
                    $langSelect.append('<option value="">No languages available</option>');
                }
            })
            .fail(function () {
                $langSelect.empty();
                $langSelect.append('<option value="">Error loading languages</option>');
            });

        $modal.data('selected-ids', selectedIds);
        $modal.css('display', 'flex');
    }

    /**
     * Hide language modal
     */
    function hideLanguageModal() {
        $(SELECTORS.languageModal).css('display', 'none');
    }

    /**
     * Open translation table popup
     */
    function openTranslationTablePopup(postId, targetLang) {
        // Check if Google Translate supports this language
        if (!isGoogleTranslateSupported(targetLang)) {
            const langName = CP_WPML_AUTO_TRANSLATE.languages.find(function(l) { return l.code === targetLang; });
            const langDisplayName = langName ? langName.name : targetLang;
            alert('Sorry, Google Translate does not support "' + langDisplayName + '" language. Please select a different language.');
            return;
        }

        $(SELECTORS.translationPopup).remove();

        const langName2 = CP_WPML_AUTO_TRANSLATE.languages.find(function(l) { return l.code === targetLang; });
        const langDisplayName2 = langName2 ? langName2.name : targetLang;

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
            ids: [postId]
        })
            .done(function (resp) {
                if (resp && resp.success && resp.data && resp.data[postId]) {
                    const postData = resp.data[postId];
                    populateTranslationTable(postData, targetLang);
                    initGoogleTranslateWidget(targetLang);
                } else {
                    alert('Failed to load post content.');
                    $modal.remove();
                }
            })
            .fail(function () {
                alert('AJAX error while loading content.');
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

        $(document).off('click', '#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer').on('click', '#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer', function () {
            if (!$(this).prop('disabled')) {
                saveTranslationFromTable(postId, targetLang);
            }
        });

        // Listen for manual edits in translation fields
        $(document).on('input change', '.' + CLASSES.translationField + '.target', function() {
            updateSaveButtonState();
        });
    }

    /**
     * Extract text from block attributes
     */
    function extractTextFromAttrs(attrs, blockPath, blockName) {
        const items = [];
        if (!attrs || typeof attrs !== 'object') {
            return items;
        }

        // Common translatable attribute keys
        const translatableKeys = ['content', 'text', 'caption', 'alt', 'title', 'summary', 'citation', 'value', 'placeholder', 'label'];
        
        translatableKeys.forEach(function(key) {
            if (attrs[key] && typeof attrs[key] === 'string' && attrs[key].trim()) {
                const text = attrs[key].trim();
                // Remove HTML tags if present
                const tempDiv = $('<div>').html(text);
                const plainText = tempDiv.text().trim();
                
                if (plainText) {
                    items.push({
                        type: 'content',
                        text: plainText,
                        blockPath: blockPath + '.attrs.' + key,
                        blockName: blockName || '',
                        attrKey: key,
                        attrValue: attrs[key],
                        blockAttrs: attrs
                    });
                }
            }
        });

        return items;
    }

    /**
     * Extract translatable text from blocks recursively
     */
    function extractTextFromBlocks(blocks, items, path) {
        if (!Array.isArray(blocks)) {
            return items;
        }

        path = path || [];

        blocks.forEach(function(block, blockIndex) {
            const currentPath = path.concat([blockIndex]);
            const blockPath = currentPath.join('.');
            const blockName = block.blockName || '';

            // Extract text from block attributes first (most important)
            if (block.attrs) {
                const attrItems = extractTextFromAttrs(block.attrs, blockPath, blockName);
                items.push.apply(items, attrItems);
            }

            // Extract individual strings from innerHTML (split by paragraphs, headings, etc.)
            if (block.innerHTML) {
                const tempDiv = $('<div>').html(block.innerHTML);
                // Find all text-containing elements
                const textElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg');
                
                if (textElements.length > 0) {
                    // Extract only leaf elements (elements without text-containing children)
                    let leafIndex = 0;
                    textElements.each(function() {
                        const $elem = $(this);
                        const elemText = $elem.text().trim();
                        
                        if (!elemText) {
                            return; // Skip elements with no text
                        }
                        
                        // Check if element has text-containing children
                        const textChildren = $elem.children().not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                            return $(this).text().trim().length > 0;
                        });
                        
                        // Skip parent elements - only include leaf elements (elements without children)
                        if (textChildren.length > 0) {
                            return; // Skip this element as it has children
                        }
                        
                        // Check if this text was already extracted from attrs
                        const alreadyInAttrs = items.some(function(item) {
                            return item.blockPath && item.blockPath.startsWith(blockPath) && item.text === elemText;
                        });
                        
                        if (!alreadyInAttrs) {
                            items.push({
                                type: 'content',
                                text: elemText,
                                blockPath: blockPath + '.innerHTML[' + leafIndex + ']',
                                blockName: blockName,
                                blockAttrs: block.attrs || {},
                                innerHTML: $elem.html(),
                                html: $elem.html(), // Also store as html for table display
                                innerHTMLIndex: leafIndex
                            });
                            leafIndex++;
                        }
                    });
                } else {
                    // No structured elements, extract as single string
                    const text = tempDiv.text().trim();
                    if (text) {
                        const alreadyInAttrs = items.some(function(item) {
                            return item.blockPath && item.blockPath.startsWith(blockPath) && item.text === text;
                        });
                        
                        if (!alreadyInAttrs) {
                            items.push({
                                type: 'content',
                                text: text,
                                blockPath: blockPath + '.innerHTML',
                                blockName: blockName,
                                blockAttrs: block.attrs || {},
                                innerHTML: block.innerHTML,
                                html: block.innerHTML // Also store as html for table display
                            });
                        }
                    }
                }
            }

            // Handle innerContent array - extract each string individually
            if (Array.isArray(block.innerContent)) {
                block.innerContent.forEach(function(content, contentIndex) {
                    if (typeof content === 'string' && content.trim()) {
                        const tempDiv = $('<div>').html(content);
                        // Find all text-containing elements
                        const textElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg');
                        
                        if (textElements.length > 0) {
                            // Extract only leaf elements (elements without text-containing children)
                            let leafIndex = 0;
                            textElements.each(function() {
                                const $elem = $(this);
                                const elemText = $elem.text().trim();
                                
                                if (!elemText) {
                                    return; // Skip elements with no text
                                }
                                
                                // Check if element has text-containing children
                                const textChildren = $elem.children().not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                                    return $(this).text().trim().length > 0;
                                });
                                
                                // Skip parent elements - only include leaf elements (elements without children)
                                if (textChildren.length > 0) {
                                    return; // Skip this element as it has children
                                }
                                
                                // Check if this text was already extracted
                                const alreadyAdded = items.some(function(item) {
                                    return item.blockPath && item.blockPath.startsWith(blockPath) && item.text === elemText;
                                });
                                
                                if (!alreadyAdded) {
                                    items.push({
                                        type: 'content',
                                        text: elemText,
                                        blockPath: blockPath + '.innerContent[' + contentIndex + '][' + leafIndex + ']',
                                        blockName: blockName,
                                        blockAttrs: block.attrs || {},
                                        innerContentIndex: contentIndex,
                                        innerContentElementIndex: leafIndex,
                                        innerContent: $elem.html(),
                                        html: $elem.html() // Also store as html for table display
                                    });
                                    leafIndex++;
                                }
                            });
                        } else {
                            // No structured elements, extract as single string
                            const text = tempDiv.text().trim();
                            if (text) {
                                const alreadyAdded = items.some(function(item) {
                                    return item.blockPath && item.blockPath.startsWith(blockPath) && item.text === text;
                                });
                                
                                if (!alreadyAdded) {
                                    items.push({
                                        type: 'content',
                                        text: text,
                                        blockPath: blockPath + '.innerContent[' + contentIndex + ']',
                                        blockName: blockName,
                                        blockAttrs: block.attrs || {},
                                        innerContentIndex: contentIndex,
                                        innerContent: content,
                                        html: content // Also store as html for table display
                                    });
                                }
                            }
                        }
                    }
                });
            }

            // Recursively process innerBlocks
            if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
                extractTextFromBlocks(block.innerBlocks, items, currentPath.concat(['innerBlocks']));
            }
        });

        return items;
    }

    /**
     * Check if a key contains translatable substring
     */
    function subStringsToCheck(strings) {
        const dynamicSubStrings = ['title', 'description', 'editor', 'text', 'content', 'label'];
        const staticSubStrings = ['caption', 'heading', 'sub_heading', 'testimonial_content', 'testimonial_job', 'testimonial_name', 'name'];
        
        return dynamicSubStrings.some(function(substring) {
            return strings.toLowerCase().indexOf(substring) !== -1;
        }) || staticSubStrings.some(function(substring) {
            return strings === substring;
        });
    }

    /**
     * Extract text from Elementor elements recursively
     */
    function extractTextFromElementor(elements, items, path) {
        if (!Array.isArray(elements)) {
            return items;
        }

        path = path || [];
        
        // Define a list of CSS properties to exclude (same as autopoly)
        const cssProperties = [
            'content_width', 'title_size', 'font_size', 'margin', 'padding', 'background', 'border', 'color', 'text_align',
            'font_weight', 'font_family', 'line_height', 'letter_spacing', 'text_transform', 'border_radius', 'box_shadow',
            'opacity', 'width', 'height', 'display', 'position', 'z_index', 'visibility', 'align', 'max_width', 
            'content_typography_typography', 'flex_justify_content', 'title_color', 'description_color', 'email_content'
        ];

        elements.forEach(function(element, elementIndex) {
            const currentPath = path.concat([elementIndex]);
            const elementPath = currentPath.join('.');

            // Extract from settings
            if (element.settings && typeof element.settings === 'object' && element.settings !== null) {
                Object.keys(element.settings).forEach(function(key) {
                    // Skip CSS properties (colors, sizes, etc.)
                    if (cssProperties.some(function(cssProp) {
                        return key.toLowerCase().indexOf(cssProp.toLowerCase()) !== -1;
                    })) {
                        return; // Skip this property
                    }
                    
                    const value = element.settings[key];
                    
                    // Check if this is a translatable field using subStringsToCheck
                    if (subStringsToCheck(key) && typeof value === 'string' && value.trim() !== '') {
                        const tempDiv = $('<div>').html(value);
                        // Check if value contains HTML elements
                        const textElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg');
                        
                        if (textElements.length > 0) {
                            // Extract only leaf elements (elements without text-containing children)
                            let leafIndex = 0;
                            textElements.each(function() {
                                const $elem = $(this);
                                const elemText = $elem.text().trim();
                                
                                if (!elemText) {
                                    return; // Skip elements with no text
                                }
                                
                                // Check if element has text-containing children
                                const textChildren = $elem.children().not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                                    return $(this).text().trim().length > 0;
                                });
                                
                                // Skip parent elements - only include leaf elements (elements without children)
                                if (textChildren.length > 0) {
                                    return; // Skip this element as it has children
                                }
                                
                                items.push({
                                    type: 'content',
                                    text: elemText,
                                    elementPath: elementPath + '.settings.' + key + '[' + leafIndex + ']',
                                    elementType: element.widgetType || element.elType || '',
                                    elementSettings: element.settings,
                                    settingKey: key,
                                    settingValue: $elem.html()
                                });
                                leafIndex++;
                            });
                        } else {
                            // No structured elements, extract as single string
                            const text = tempDiv.text().trim();
                            if (text) {
                                items.push({
                                    type: 'content',
                                    text: text,
                                    elementPath: elementPath + '.settings.' + key,
                                    elementType: element.widgetType || element.elType || '',
                                    elementSettings: element.settings,
                                    settingKey: key,
                                    settingValue: value
                                });
                            }
                        }
                    }
                    
                    // Handle arrays (repeater fields)
                    if (Array.isArray(value) && value.length > 0) {
                        value.forEach(function(item, itemIndex) {
                            if (typeof item === 'object' && item !== null) {
                                Object.keys(item).forEach(function(repeaterKey) {
                                    // Skip CSS properties in repeater fields too
                                    if (cssProperties.some(function(cssProp) {
                                        return repeaterKey.toLowerCase().indexOf(cssProp.toLowerCase()) !== -1;
                                    })) {
                                        return; // Skip this property
                                    }
                                    
                                    if (subStringsToCheck(repeaterKey) && typeof item[repeaterKey] === 'string' && item[repeaterKey].trim() !== '') {
                                        const tempDiv = $('<div>').html(item[repeaterKey]);
                                        // Check if value contains HTML elements
                                        const textElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg');
                                        
                                        if (textElements.length > 0) {
                                            // Extract only leaf elements (elements without text-containing children)
                                            let leafIndex = 0;
                                            textElements.each(function() {
                                                const $elem = $(this);
                                                const elemText = $elem.text().trim();
                                                
                                                if (!elemText) {
                                                    return; // Skip elements with no text
                                                }
                                                
                                                // Check if element has text-containing children
                                                const textChildren = $elem.children().not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                                                    return $(this).text().trim().length > 0;
                                                });
                                                
                                                // Skip parent elements - only include leaf elements (elements without children)
                                                if (textChildren.length > 0) {
                                                    return; // Skip this element as it has children
                                                }
                                                
                                                items.push({
                                                    type: 'content',
                                                    text: elemText,
                                                    elementPath: elementPath + '.settings.' + key + '[' + itemIndex + '].' + repeaterKey + '[' + leafIndex + ']',
                                                    elementType: element.widgetType || element.elType || '',
                                                    elementSettings: element.settings,
                                                    settingKey: key,
                                                    repeaterIndex: itemIndex,
                                                    repeaterKey: repeaterKey,
                                                    settingValue: $elem.html()
                                                });
                                                leafIndex++;
                                            });
                                        } else {
                                            // No structured elements, extract as single string
                                            const text = tempDiv.text().trim();
                                            if (text) {
                                                items.push({
                                                    type: 'content',
                                                    text: text,
                                                    elementPath: elementPath + '.settings.' + key + '[' + itemIndex + '].' + repeaterKey,
                                                    elementType: element.widgetType || element.elType || '',
                                                    elementSettings: element.settings,
                                                    settingKey: key,
                                                    repeaterIndex: itemIndex,
                                                    repeaterKey: repeaterKey,
                                                    settingValue: item[repeaterKey]
                                                });
                                            }
                                        }
                                    }
                                });
                            }
                        });
                    }
                });
            }

            // Recursively process nested elements
            if (Array.isArray(element.elements) && element.elements.length > 0) {
                extractTextFromElementor(element.elements, items, currentPath.concat(['elements']));
            }
        });

        return items;
    }

    /**
     * Populate translation table with post content using WPML package data
     */
    function populateTranslationTable(postData, targetLang) {
        const $tbody = $(SELECTORS.translationTbody);
        $tbody.empty();

        const contentItems = [];
        const editorType = postData.editor_type || 'classic';

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
                field_name: 'title'
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
                        html: stringData.html || stringData.text,
                        format: stringData.format || ''
                    });
                }
            });
        } else {
            // Fallback: if strings not available, extract manually
            console.log('postData.original_content', editorType);
            if (editorType === 'elementor' && Array.isArray(postData.original_content)) {
                extractTextFromElementor(postData.original_content, contentItems);
            } else if (editorType === 'block' && Array.isArray(postData.original_content)) {
                extractTextFromBlocks(postData.original_content, contentItems);
            } else if (typeof postData.original_content === 'string') {
                const tempDiv = $('<div>').html(postData.original_content);
                const allElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg');
                
                // Extract only leaf elements (elements without text-containing children)
                // Skip parent elements that contain child elements
                const elementsArray = allElements.toArray();
                
                // Process all elements - only include leaf elements
                elementsArray.forEach(function(elem) {
                    const $elem = $(elem);
                    const elemText = $elem.text().trim();
                    
                    if (!elemText) {
                        return; // Skip elements with no text
                    }
                    console.log('elemText', elemText);
                    // Check if element has text-containing children
                    const textChildren = $elem.children().not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                        return $(this).text().trim().length > 0;
                    });
                    console.log('textChildren', textChildren);
                    // Skip parent elements - only include leaf elements (elements without children)
                    if (textChildren.length > 0) {
                        return; // Skip this element as it has children
                    }
                    
                    // Include only leaf elements (elements without children)
                    const outerHtml = $('<div>').append($elem.clone()).html();
                    contentItems.push({
                        type: 'content',
                        text: elemText,
                        html: outerHtml
                    });
                });
                
                // Only use fallback if no elements were extracted
                if (contentItems.length === 0) {
                    // No structured elements, try splitting by paragraphs
                    const parts = postData.original_content.split(/\n\n+/);
                    parts.forEach(function(part) {
                        const text = $('<div>').html(part).text().trim();
                        if (text) {
                            contentItems.push({
                                type: 'content',
                                text: text,
                                html: part
                            });
                        }
                    });
                }
            }
        }

        // Populate table rows
        contentItems.forEach(function(item, index) {
            const rowNum = index + 1;
            const $row = $('<tr>');
            const escapedSourceText = $('<div>').text(item.text).html();
            // Get HTML content if available, otherwise use plain text
            let sourceHtml = escapedSourceText;
            if (item.html && item.html !== item.text) {
                sourceHtml = item.html;
            }
            if(item.settingValue && item.settingValue !== item.text) {
                sourceHtml = item.settingValue;
            }
            
            // Create cells using jQuery to properly insert HTML
            const $numCell = $('<td>').css({'padding': '12px', 'border': '1px solid #ddd'}).text(rowNum);
            const $sourceCell = $('<td>').addClass(CLASSES.sourceText).css({'padding': '12px', 'border': '1px solid #ddd', 'background': '#f9f9f9'});
            $sourceCell.html(sourceHtml); // Use .html() to properly insert HTML
            
            $row.append($numCell);
            $row.append($sourceCell);
            
            // Translation cell
            const $translationCell = $('<td>').css({'padding': '12px', 'border': '1px solid #ddd', 'position': 'relative'});
            const $translationTarget = $('<div>')
                .attr('translate', 'yes')
                .addClass(CLASSES.translationTarget)
                .attr('data-type', item.type)
                .attr('data-index', index)
                .css({'position': 'absolute', 'top': '0', 'left': '0', 'width': '100%', 'height': '100%', 'opacity': '0', 'pointer-events': 'none', 'z-index': '-1'})
                .html(sourceHtml);
            const $translationField = $('<div>')
                .addClass(CLASSES.translationField + ' target')
                .attr('data-type', item.type)
                .attr('data-index', index)
                .css({'padding': '8px', 'font-family': 'inherit', 'font-size': '14px', 'background': '#fff'})
                .html(sourceHtml);
            
            $translationCell.append($translationTarget);
            $translationCell.append($translationField);
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
     * Replace text in HTML while preserving structure
     */
    function replaceTextInHTML(html, originalText, translatedText) {
        if (!html || typeof html !== 'string') {
            return html;
        }

        const cleanTranslatedText = translatedText;
        const originalTrimmed = originalText.trim();
        
        // Check if original HTML contains the text
        const tempDiv = $('<div>').html(html);
        const htmlText = tempDiv.text();
        
        // If the text doesn't exist in the HTML, return original
        if (htmlText.indexOf(originalTrimmed) === -1) {
            return html;
        }
        
        // Check if translated text contains HTML
        const $translatedDiv = $('<div>').html(cleanTranslatedText);
        const translatedHasHTML = $translatedDiv.children().length > 0 || cleanTranslatedText !== $translatedDiv.text();
        
        // Check if original HTML has the same structure as what we're looking for
        const originalHasHTML = /<[^>]+>/.test(html);
        
        // Strategy 1: If both have HTML, try to find and replace the entire matching element
        if (translatedHasHTML && originalHasHTML) {
            // Try to find an element that contains exactly the original text
            let found = false;
            
            // First, try to find leaf elements (elements with no text-containing children)
            tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').each(function() {
                if (found) return false; // Break if already found
                
                const $elem = $(this);
                const elemText = $elem.text().trim();
                const elemHtml = $elem.html();
                
                // Check if this element's text matches exactly
                if (elemText === originalTrimmed) {
                    // Check if this element has no child elements with text (leaf element)
                    const hasTextChildren = $elem.find('*').not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                        return $(this).text().trim().length > 0;
                    }).length > 0;
                    
                    if (!hasTextChildren) {
                        // Replace the entire element's HTML
                        $elem.html(cleanTranslatedText);
                        found = true;
                        return false; // Break
                    }
                }
            });
            
            if (found) {
                return tempDiv.html();
            }
            
            // Strategy 2: Try to match by HTML structure if available
            // Find elements that might contain the text and replace their innerHTML
            tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').each(function() {
                if (found) return false; // Break if already found
                
                const $elem = $(this);
                const elemText = $elem.text().trim();
                
                if (elemText === originalTrimmed) {
                    $elem.html(cleanTranslatedText);
                    found = true;
                    return false; // Break
                }
            });
            
            if (found) {
                return tempDiv.html();
            }
        }
        
        // Strategy 3: Replace text content while preserving HTML structure
        // This handles cases where we need to replace text within HTML tags
        function replaceTextInNode(node) {
            if (node.nodeType === 3) { // Text node
                const nodeText = node.textContent || node.nodeValue || '';
                if (nodeText.indexOf(originalTrimmed) !== -1) {
                    if (translatedHasHTML) {
                        // If translated text has HTML, we need to replace the parent element's HTML
                        const $parent = $(node).parent();
                        if ($parent.length) {
                            const parentHtml = $parent.html();
                            const updatedHtml = parentHtml.replace(
                                new RegExp(originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                                cleanTranslatedText
                            );
                            $parent.html(updatedHtml);
                            return true; // Indicate replacement was done
                        }
                    } else {
                        // Plain text replacement
                        node.textContent = nodeText.replace(
                            new RegExp(originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                            cleanTranslatedText
                        );
                    }
                }
            } else if (node.nodeType === 1) { // Element node
                // Process children first
                const children = Array.from(node.childNodes);
                for (let i = 0; i < children.length; i++) {
                    if (replaceTextInNode(children[i])) {
                        // If replacement was done at a higher level, stop processing
                        return true;
                    }
                }
            }
            return false;
        }
        
        // Try replacing text nodes
        const children = Array.from(tempDiv[0].childNodes);
        for (let i = 0; i < children.length; i++) {
            replaceTextInNode(children[i]);
        }
        
        // Final fallback: Direct string replacement if structure is simple
        const resultHtml = tempDiv.html();
        if (resultHtml.indexOf(originalTrimmed) !== -1 && !translatedHasHTML) {
            // Only do direct replacement if translated text is plain text
            return resultHtml.replace(
                new RegExp(originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                cleanTranslatedText
            );
        }
        
        return resultHtml;
    }

    /**
     * Recursively find and update block content with translated text
     * 
     * @param {Array} blocks - Array of block objects
     * @param {string} originalText - Original plain text for matching
     * @param {string} translatedText - Translated content (may contain HTML)
     * @param {string} originalHtml - Optional original HTML structure for better matching
     */
    function updateBlockWithTranslation(blocks, originalText, translatedText, originalHtml) {
        if (!Array.isArray(blocks)) {
            return blocks;
        }

        return blocks.map(function(block) {
            const updatedBlock = JSON.parse(JSON.stringify(block)); // Deep clone

            // Update block attributes (attrs) - this is where most block text is stored
            if (updatedBlock.attrs && typeof updatedBlock.attrs === 'object') {
                const cleanTranslatedText = translatedText;
                
                const translatableKeys = ['content', 'text', 'caption', 'alt', 'title', 'summary', 'citation', 'value', 'placeholder', 'label'];
                
                translatableKeys.forEach(function(key) {
                    if (updatedBlock.attrs[key] && typeof updatedBlock.attrs[key] === 'string') {
                        const attrValue = updatedBlock.attrs[key];
                        const tempDiv = $('<div>').html(attrValue);
                        const attrText = tempDiv.text().trim();
                        const originalTrimmed = originalText.trim();
                        
                        // If the attribute text matches exactly, replace it
                        if (attrText === originalTrimmed) {
                            // Check if attribute contains HTML
                            if (attrValue !== attrText) {
                                // Contains HTML - use HTML replacement with clean translated text
                                updatedBlock.attrs[key] = replaceTextInHTML(attrValue, originalText, cleanTranslatedText);
                            } else {
                                // Plain text - simple replacement
                                updatedBlock.attrs[key] = cleanTranslatedText;
                            }
                        } else if (attrText.indexOf(originalTrimmed) !== -1) {
                            // Partial match - replace text within the attribute
                            if (attrValue !== attrText) {
                                // Contains HTML - use HTML replacement with clean translated text
                                updatedBlock.attrs[key] = replaceTextInHTML(attrValue, originalText, cleanTranslatedText);
                            } else {
                                // Plain text - simple replacement
                                updatedBlock.attrs[key] = attrValue.replace(
                                    new RegExp(originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                                    cleanTranslatedText
                                );
                            }
                        }
                    }
                });
            }

            // Update innerHTML if it contains the original text
            if (updatedBlock.innerHTML) {
                const originalInnerHTML = updatedBlock.innerHTML;
                const tempDiv = $('<div>').html(originalInnerHTML);
                const htmlText = tempDiv.text();
                const originalTrimmed = originalText.trim();
                
                const cleanTranslatedText = translatedText;
                
                // Check if innerHTML contains the exact text we're looking for
                if (htmlText.indexOf(originalTrimmed) !== -1) {
                    let replaced = false;
                    
                    // If we have original HTML, try to match by HTML content first
                    if (originalHtml && /<[^>]+>/.test(originalHtml)) {
                        // Normalize whitespace for comparison
                        const normalizedOriginalHtml = originalHtml.replace(/\s+/g, ' ').trim();
                        
                        // Try to find an element whose HTML matches the original
                        tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').each(function() {
                            if (replaced) return false; // Break if already replaced
                            
                            const $elem = $(this);
                            const elemText = $elem.text().trim();
                            const elemHtml = $elem.html();
                            const normalizedElemHtml = elemHtml.replace(/\s+/g, ' ').trim();
                            
                            // If the HTML content matches (ignoring whitespace), replace it
                            if (normalizedElemHtml === normalizedOriginalHtml && elemText === originalTrimmed) {
                                $elem.html(cleanTranslatedText);
                                replaced = true;
                                return false; // Break
                            }
                        });
                        
                        // Also check if the entire innerHTML matches (normalized)
                        if (!replaced) {
                            const normalizedInnerHTML = originalInnerHTML.replace(/\s+/g, ' ').trim();
                            if (normalizedInnerHTML === normalizedOriginalHtml) {
                                updatedBlock.innerHTML = cleanTranslatedText;
                                replaced = true;
                            }
                        }
                    }
                    
                    // If HTML matching didn't work, try text-based matching
                    if (!replaced) {
                        // First try to find leaf elements (elements with no text-containing children)
                        tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').each(function() {
                            if (replaced) return false; // Break if already replaced
                            
                            const $elem = $(this);
                            const elemText = $elem.text().trim();
                            
                            if (elemText === originalTrimmed) {
                                // Check if this element has no child elements with text (leaf element)
                                const hasTextChildren = $elem.find('*').not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                                    return $(this).text().trim().length > 0;
                                }).length > 0;
                                
                                if (!hasTextChildren) {
                                    // Check if translated text contains HTML
                                    const $translatedDiv = $('<div>').html(cleanTranslatedText);
                                    const translatedHasHTML = $translatedDiv.children().length > 0 || cleanTranslatedText !== $translatedDiv.text();
                                    
                                    if (translatedHasHTML) {
                                        // Contains HTML - replace innerHTML
                                        $elem.html(cleanTranslatedText);
                                    } else {
                                        // Plain text - use text() to preserve existing HTML structure
                                        $elem.text(cleanTranslatedText);
                                    }
                                    replaced = true;
                                    return false; // Break
                                }
                            }
                        });
                        
                        // If still not replaced, try common block elements
                        if (!replaced) {
                            tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').each(function() {
                                if (replaced) return false; // Break if already replaced
                                
                                const $elem = $(this);
                                const elemText = $elem.text().trim();
                                
                                if (elemText === originalTrimmed) {
                                    // Check if translated text contains HTML
                                    const $translatedDiv = $('<div>').html(cleanTranslatedText);
                                    const translatedHasHTML = $translatedDiv.children().length > 0 || cleanTranslatedText !== $translatedDiv.text();
                                    
                                    if (translatedHasHTML) {
                                        // Contains HTML - replace innerHTML while preserving structure
                                        $elem.html(cleanTranslatedText);
                                    } else {
                                        // Plain text - use text() to preserve existing HTML structure
                                        $elem.text(cleanTranslatedText);
                                    }
                                    replaced = true;
                                    return false; // Break
                                }
                            });
                        }
                    }
                    
                    if (replaced) {
                        // Get updated HTML from tempDiv if we modified it
                        const updatedHtml = tempDiv.html();
                        if (updatedHtml !== originalInnerHTML) {
                            updatedBlock.innerHTML = updatedHtml;
                        }
                    } else {
                        // Fallback to general replacement using replaceTextInHTML
                        updatedBlock.innerHTML = replaceTextInHTML(
                            originalInnerHTML,
                            originalText,
                            cleanTranslatedText
                        );
                    }
                }
            }

            // Update innerContent array
            if (Array.isArray(updatedBlock.innerContent)) {
                updatedBlock.innerContent = updatedBlock.innerContent.map(function(content) {
                    if (typeof content === 'string') {
                        const tempDiv = $('<div>').html(content);
                        const contentText = tempDiv.text().trim();
                        const originalTrimmed = originalText.trim();
                        const cleanTranslatedText = translatedText;
                        
                        // Check if this innerContent string contains the text we're looking for
                        if (contentText.indexOf(originalTrimmed) !== -1) {
                            let replaced = false;
                            
                            // Process from innermost to outermost to match leaf elements first
                            const allElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').toArray().reverse();
                            
                            allElements.forEach(function(elem) {
                                if (replaced) return;
                                
                                const $elem = $(elem);
                                const elemText = $elem.text().trim();
                                
                                if (elemText === originalTrimmed) {
                                    // Check if this is a leaf element
                                    const hasTextChildren = $elem.find('*').not('script, style, noscript, iframe, object, embed, svg').filter(function() {
                                        return $(this).text().trim().length > 0;
                                    }).length > 0;
                                    
                                    if (!hasTextChildren) {
                                        // Check if translated text contains HTML tags
                                        const $translatedDiv = $('<div>').html(cleanTranslatedText);
                                        const translatedHasHTML = $translatedDiv.children().length > 0 || cleanTranslatedText !== $translatedDiv.text();
                                        
                                        if (translatedHasHTML) {
                                            // If translated has HTML, we need to be careful
                                            // Extract just the text content from translated HTML
                                            const translatedTextOnly = $translatedDiv.text();
                                            $elem.text(translatedTextOnly);
                                        } else {
                                            // Plain text - use text() to preserve existing HTML structure
                                            $elem.text(cleanTranslatedText);
                                        }
                                        replaced = true;
                                    }
                                }
                            });
                            
                            if (replaced) {
                                return tempDiv.html();
                            } else {
                                // Fallback to replaceTextInHTML but strip HTML from translated text
                                const $translatedDiv = $('<div>').html(cleanTranslatedText);
                                const translatedTextOnly = $translatedDiv.text() || cleanTranslatedText;
                                return replaceTextInHTML(content, originalText, translatedTextOnly);
                            }
                        }
                        
                        return content;
                    }
                    return content;
                });
            }

            // Recursively update innerBlocks
            if (Array.isArray(updatedBlock.innerBlocks) && updatedBlock.innerBlocks.length > 0) {
                updatedBlock.innerBlocks = updateBlockWithTranslation(
                    updatedBlock.innerBlocks,
                    originalText,
                    translatedText,
                    originalHtml
                );
            }

            return updatedBlock;
        });
    }

    /**
     * Update Elementor element using elementPath to directly locate and update the value
     * @param {Array} elements - Elementor elements array
     * @param {string} elementPath - Path to the element (e.g., "0.elements.0.settings.title" or "0.settings.key[0].repeaterKey")
     * @param {string} translatedText - Translated text to set
     * @returns {Array} - Updated elements array
     */
    function updateElementorByPath(elements, elementPath, translatedText) {
        if (!Array.isArray(elements) || !elementPath) {
            return elements;
        }
        
        // Handle repeater fields with array notation like "key[0].repeaterKey"
        // Split by '.' but preserve array notation like "key[0]"
        const pathParts = [];
        let currentPart = '';
        let inBrackets = false;
        
        for (let i = 0; i < elementPath.length; i++) {
            const char = elementPath[i];
            if (char === '[') {
                inBrackets = true;
                currentPart += char;
            } else if (char === ']') {
                inBrackets = false;
                currentPart += char;
            } else if (char === '.' && !inBrackets) {
                if (currentPart) {
                    pathParts.push(currentPart);
                    currentPart = '';
                }
            } else {
                currentPart += char;
            }
        }
        if (currentPart) {
            pathParts.push(currentPart);
        }
        
        let current = elements;
        
        // Navigate through the path
        for (let i = 0; i < pathParts.length; i++) {
            const part = pathParts[i];
            
            // Check if this part is an array index
            if (/^\d+$/.test(part)) {
                const index = parseInt(part, 10);
                if (Array.isArray(current) && current[index] !== undefined) {
                    current = current[index];
                } else {
                    return elements; // Path invalid
                }
            } else if (part === 'settings' && current.settings) {
                current = current.settings;
            } else if (part === 'elements' && current.elements) {
                current = current.elements;
            } else if (part.indexOf('[') !== -1) {
                // Handle repeater fields like "key[0]"
                const match = part.match(/^([^\[]+)\[(\d+)\]$/);
                if (match) {
                    const arrayKey = match[1];
                    const arrayIndex = parseInt(match[2], 10);
                    if (current[arrayKey] && Array.isArray(current[arrayKey]) && current[arrayKey][arrayIndex] !== undefined) {
                        current = current[arrayKey][arrayIndex];
                    } else {
                        return elements; // Path invalid
                    }
                }
            } else if (i === pathParts.length - 1) {
                // Last part - this is the property to update
                if (typeof current === 'object' && current !== null) {
                    current[part] = translatedText;
                    return elements; // Successfully updated
                }
            } else if (current[part]) {
                current = current[part];
            } else {
                return elements; // Path invalid
            }
        }
        
        return elements;
    }

    /**
     * Recursively update Elementor elements with translated text
     */
    function updateElementorWithTranslation(elements, originalText, translatedText) {
        if (!Array.isArray(elements)) {
            return elements;
        }

        // Define a list of CSS properties to exclude (same as extraction)
        const cssProperties = [
            'content_width', 'title_size', 'font_size', 'margin', 'padding', 'background', 'border', 'color', 'text_align',
            'font_weight', 'font_family', 'line_height', 'letter_spacing', 'text_transform', 'border_radius', 'box_shadow',
            'opacity', 'width', 'height', 'display', 'position', 'z_index', 'visibility', 'align', 'max_width', 
            'content_typography_typography', 'flex_justify_content', 'title_color', 'description_color', 'email_content'
        ];

        return elements.map(function(element) {
            const updatedElement = JSON.parse(JSON.stringify(element)); // Deep clone

            // Update settings
            if (updatedElement.settings && typeof updatedElement.settings === 'object' && updatedElement.settings !== null) {
                Object.keys(updatedElement.settings).forEach(function(key) {
                    // Skip CSS properties - don't update them
                    if (cssProperties.some(function(cssProp) {
                        return key.toLowerCase().indexOf(cssProp.toLowerCase()) !== -1;
                    })) {
                        return; // Skip this property
                    }
                    
                    // Only update translatable fields
                    if (!subStringsToCheck(key)) {
                        return; // Skip non-translatable fields
                    }
                    
                    const value = updatedElement.settings[key];
                    
                    // Handle string values
                    if (typeof value === 'string' && value.trim()) {
                        const tempDiv = $('<div>').html(value);
                        const settingText = tempDiv.text().trim();
                        
                        // Extract plain text from originalText if it contains HTML
                        const originalTempDiv = $('<div>').html(originalText);
                        const originalPlainText = originalTempDiv.text().trim();
                        const originalHasHTML = /<[^>]+>/.test(originalText);
                        const originalTextForMatch = originalHasHTML ? originalPlainText : originalText.trim();
                        
                        // Check if the actual value matches the original value (exact match)
                        // This handles cases where settingValue is the exact same as the Elementor value
                        const valueMatchesOriginal = value === originalText || value.trim() === originalText.trim();
                        
                        // If the setting text matches, replace it
                        if (settingText === originalTextForMatch || valueMatchesOriginal) {
                            if (value !== settingText) {
                                // Contains HTML - use HTML replacement
                                // If original was HTML and translated is HTML, use translated directly
                                const translatedHasHTML = /<[^>]+>/.test(translatedText);
                                if (originalHasHTML && translatedHasHTML) {
                                    updatedElement.settings[key] = translatedText;
                                } else {
                                    updatedElement.settings[key] = replaceTextInHTML(value, originalText, translatedText);
                                }
                            } else {
                                // Plain text - simple replacement
                                updatedElement.settings[key] = translatedText;
                            }
                        } else if (settingText.indexOf(originalTextForMatch) !== -1) {
                            // Partial match
                            if (value !== settingText) {
                                const translatedHasHTML = /<[^>]+>/.test(translatedText);
                                if (originalHasHTML && translatedHasHTML) {
                                    updatedElement.settings[key] = replaceTextInHTML(value, originalText, translatedText);
                                } else {
                                    updatedElement.settings[key] = replaceTextInHTML(value, originalText, translatedText);
                                }
                            } else {
                                updatedElement.settings[key] = value.replace(
                                    new RegExp(originalTextForMatch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                                    translatedText
                                );
                            }
                        }
                    }
                    
                    // Handle arrays (repeater fields)
                    if (Array.isArray(value) && value.length > 0) {
                        updatedElement.settings[key] = value.map(function(item, itemIndex) {
                            if (typeof item === 'object' && item !== null) {
                                const updatedItem = JSON.parse(JSON.stringify(item));
                                
                                Object.keys(updatedItem).forEach(function(repeaterKey) {
                                    // Skip CSS properties in repeater fields too
                                    if (cssProperties.some(function(cssProp) {
                                        return repeaterKey.toLowerCase().indexOf(cssProp.toLowerCase()) !== -1;
                                    })) {
                                        return; // Skip this property
                                    }
                                    
                                    // Only update translatable fields
                                    if (!subStringsToCheck(repeaterKey)) {
                                        return; // Skip non-translatable fields
                                    }
                                    
                                    if (typeof updatedItem[repeaterKey] === 'string' && updatedItem[repeaterKey].trim()) {
                                        const tempDiv = $('<div>').html(updatedItem[repeaterKey]);
                                        const repeaterText = tempDiv.text().trim();
                                        const originalTrimmed = originalText.trim();
                                        
                                        if (repeaterText === originalTrimmed) {
                                            if (updatedItem[repeaterKey] !== repeaterText) {
                                                updatedItem[repeaterKey] = replaceTextInHTML(updatedItem[repeaterKey], originalText, translatedText);
                                            } else {
                                                updatedItem[repeaterKey] = translatedText;
                                            }
                                        } else if (repeaterText.indexOf(originalTrimmed) !== -1) {
                                            if (updatedItem[repeaterKey] !== repeaterText) {
                                                updatedItem[repeaterKey] = replaceTextInHTML(updatedItem[repeaterKey], originalText, translatedText);
                                            } else {
                                                updatedItem[repeaterKey] = updatedItem[repeaterKey].replace(
                                                    new RegExp(originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                                                    translatedText
                                                );
                                            }
                                        }
                                    }
                                });
                                
                                return updatedItem;
                            }
                            return item;
                        });
                    }
                });
            }

            // Recursively update nested elements
            if (Array.isArray(updatedElement.elements) && updatedElement.elements.length > 0) {
                updatedElement.elements = updateElementorWithTranslation(
                    updatedElement.elements,
                    originalText,
                    translatedText
                );
            }

            return updatedElement;
        });
    }

    function cpWpmlGoogleReset() {
      $('#\\:1\\.container').contents().find('#\\:1\\.restore').click();
    }
    /**
     * Save translation from table
     */
    function saveTranslationFromTable(postId, targetLang) {
        const $saveBtn = $('#cp-wpml-auto-translate-translation-save, #cp-wpml-auto-translate-translation-save-footer');
        const originalText = $saveBtn.first().text();
        $saveBtn.prop('disabled', true).text('Saving...');

        const editorType = $(SELECTORS.translationPopup).data('editor-type') || 'classic';
        const contentItems = $(SELECTORS.translationPopup).data('content-items') || [];
        let translatedTitle = '';
        let translatedContent = '';

        // Collect translations with field_name mapping
        const translations = {};
        const wpmlPackage = $(SELECTORS.translationPopup).data('wpml-package');
        
        $('.' + CLASSES.translationField + '.target').each(function(index) {
            const $field = $(this);
            const type = $field.data('type');
            
            // Get HTML content if available, otherwise use text
            let translationText = '';
            const htmlContent = $field.html();
            const textContent = $field.text().trim();
            
            // Check if content has HTML tags - more robust detection
            const hasHtmlTags = /<[^>]+>/.test(htmlContent);
            const htmlDiffersFromText = htmlContent !== textContent;
            
            // Also check if the original item had HTML
            let originalHadHtml = false;
            if (contentItems[index] && contentItems[index].html) {
                originalHadHtml = /<[^>]+>/.test(contentItems[index].html);
            }
            
            // Use HTML if: has HTML tags, or HTML differs from text, or original had HTML
            if (hasHtmlTags || (htmlDiffersFromText && originalHadHtml)) {
                // Clean up any potential wrapper elements
                const $temp = $('<div>').html(htmlContent);
                $temp.find('.goog-te-spinner-pos, .goog-te-banner-frame, .skiptranslate, .goog-te-banner').remove();
                translationText = $temp.html();
            } else {
                // Plain text
                translationText = textContent;
            }

            if (type === 'title') {
                translatedTitle = translationText;
            } else if (contentItems[index]) {
                const item = contentItems[index];
                translations[index] = {
                    item: item,
                    text: translationText,
                    field_name: item.field_name || null // Store WPML field name if available
                };
            }
        });

        // Handle Elementor editor
        if (editorType === 'elementor') {
            const originalElementor = $(SELECTORS.translationPopup).data('original-elementor');
            
            if (!originalElementor || !Array.isArray(originalElementor)) {
                alert('Error: Elementor structure not found.');
                $saveBtn.prop('disabled', false).text(originalText);
                return;
            }

            // Deep clone Elementor elements to avoid modifying original
            let translatedElementor = JSON.parse(JSON.stringify(originalElementor));

            // Update Elementor elements with translated text
            Object.keys(translations).forEach(function(index) {
                const translation = translations[index];
                if (translation.item && translation.item.text) {
                    // If we have elementPath, use it to directly update the value
                    console.log(translation);
                    if (translation.item.elementPath) {
                        translatedElementor = updateElementorByPath(
                            translatedElementor,
                            translation.item.elementPath,
                            translation.text
                        );
                    } else {
                        // Fallback to text matching if no elementPath
                        const originalValue = translation.item.settingValue || translation.item.text;
                        translatedElementor = updateElementorWithTranslation(
                            translatedElementor,
                            originalValue,
                            translation.text
                        );
                    }
                }
            });
            
            translatedContent = JSON.stringify(translatedElementor);
        } else if (editorType === 'block') {
            // Handle block editor
            const originalBlocks = $(SELECTORS.translationPopup).data('original-blocks');
            
            if (!originalBlocks || !Array.isArray(originalBlocks)) {
                alert('Error: Block structure not found.');
                $saveBtn.prop('disabled', false).text(originalText);
                return;
            }

            // Deep clone blocks to avoid modifying original
            let translatedBlocks = JSON.parse(JSON.stringify(originalBlocks));

            // Update blocks with translated text
            Object.keys(translations).forEach(function(index) {
                const translation = translations[index];
                if (translation.item && translation.item.text) {
                    // Use the HTML if available, otherwise use text
                    // This ensures we match the HTML structure properly
                    const originalContent = translation.item.html || translation.item.innerHTML || translation.item.text;
                    const translatedContent = translation.text; // Already has HTML if original had it
                    
                    translatedBlocks = updateBlockWithTranslation(
                        translatedBlocks,
                        translation.item.text, // Plain text for matching
                        translatedContent,    // Translated content (with HTML if original had it)
                        originalContent       // Original HTML structure for better matching
                    );
                }
            });

            translatedContent = JSON.stringify(translatedBlocks);
        } else {
            // Handle classic editor - preserve HTML structure
            const originalContent = $(SELECTORS.translationPopup).data('original-content') || '';
            let updatedContent = originalContent;
            const tempDiv = $('<div>').html(originalContent);
            
            // Replace each translation in the original content while preserving HTML structure
            Object.keys(translations).forEach(function(index) {
                const translation = translations[index];
                if (translation.item && translation.item.text && translation.text) {
                    const originalText = translation.item.text.trim();
                    let translatedText = translation.text;
                    const originalHtml = translation.item.html || translation.item.text;
                    
                    // Check if we have HTML structure to match
                    const hasOriginalHtml = originalHtml && /<[^>]+>/.test(originalHtml) && originalHtml !== originalText;
                    
                    if (hasOriginalHtml) {
                        // Try to find element by HTML structure first
                        let replaced = false;
                        const normalizedOriginalHtml = originalHtml.replace(/\s+/g, ' ').trim();
                        
                        // Process from innermost to outermost to match leaf elements first
                        const allElements = tempDiv.find('*').not('script, style, noscript, iframe, object, embed, svg').toArray().reverse();
                        
                        allElements.forEach(function(elem) {
                            if (replaced) return;
                            
                            const $elem = $(elem);
                            const elemText = $elem.text().trim();
                            // Get outerHTML by cloning the element
                            const elemHtml = $('<div>').append($elem.clone()).html();
                            const normalizedElemHtml = elemHtml.replace(/\s+/g, ' ').trim();
                            
                            // Match by HTML structure if available
                            if (normalizedElemHtml === normalizedOriginalHtml && elemText === originalText) {
                                // Check if translated text contains HTML
                                const $translatedDiv = $('<div>').html(translatedText);
                                const translatedHasHTML = $translatedDiv.children().length > 0 || translatedText !== $translatedDiv.text();
                                
                                if (translatedHasHTML) {
                                    // Replace with translated HTML structure
                                    $elem.replaceWith(translatedText);
                                } else {
                                    // Plain text - replace element's content but preserve structure
                                    $elem.html(translatedText);
                                }
                                replaced = true;
                            }
                        });
                        
                        if (!replaced) {
                            // Fallback to text-based replacement
                            updatedContent = replaceTextInHTML(updatedContent, originalText, translatedText);
                            tempDiv.html(updatedContent);
                        }
                    } else {
                        // No HTML structure, use text-based replacement
                        // If translated text contains HTML, extract just the text
                        const $translatedDiv = $('<div>').html(translatedText);
                        const translatedHasHTML = $translatedDiv.children().length > 0 || translatedText !== $translatedDiv.text();
                        
                        if (translatedHasHTML) {
                            // Extract just the text content, preserving the HTML structure of the original
                            translatedText = $translatedDiv.text();
                        }
                        
                        updatedContent = replaceTextInHTML(updatedContent, originalText, translatedText);
                        tempDiv.html(updatedContent);
                    }
                }
            });
            
            // Get final content from tempDiv
            translatedContent = tempDiv.html();
        }

        if (!translatedTitle && !translatedContent) {
            alert('Please enter at least one translation.');
            $saveBtn.prop('disabled', false).text(originalText);
            return;
        }

        // Prepare translations mapping for WPML package structure
        const translationsMap = {};
        Object.keys(translations).forEach(function(index) {
            const translation = translations[index];
            if (translation.field_name) {
                translationsMap[translation.field_name] = translation.text;
            }
        });

        $.post(CP_WPML_AUTO_TRANSLATE.ajax, {
            action: 'cp_wpml_google_auto_translate_save_translation',
            nonce: CP_WPML_AUTO_TRANSLATE.nonce,
            post_id: postId,
            target_lang: targetLang,
            translated_title: translatedTitle,
            translated_content: translatedContent,
            editor_type: editorType,
            translations_map: JSON.stringify(translationsMap),
            wpml_package: wpmlPackage ? JSON.stringify(wpmlPackage) : ''
        })
            .done(function (resp) {
                if (resp && resp.success) {
                    
                    // Animate popup closing smoothly with fadeOut
                    const $popup = $(SELECTORS.translationPopup);
                    $popup.fadeOut(500, function() {
                        // Remove popup and reload after fade animation completes
                        $popup.remove();
                        cpWpmlGoogleReset();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    });
                } else {
                    alert('Failed to save translation: ' + (resp.data && resp.data.msg ? resp.data.msg : 'Unknown error'));
                    $saveBtn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function () {
                alert('AJAX error while saving translation.');
                $saveBtn.prop('disabled', false).text(originalText);
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
            
            // Get HTML from target div (Google Translate modifies this)
            let translatedHtml = $targetDiv.html() || '';
            let translatedText = $targetDiv.text().trim();

            if (translatedText && translatedText !== sourceText) {
                const currentText = $translationDiv.text().trim();
                if (!currentText || currentText === sourceText) {
                    // Check if original had HTML tags
                    const originalHtml = $sourceCell.html();
                    const hasOriginalHtml = originalHtml && /<[^>]+>/.test(originalHtml);
                    
                    if (hasOriginalHtml) {
                        // Original had HTML - preserve HTML structure from Google Translate
                        // Clean up any Google Translate wrapper elements
                        const $temp = $('<div>').html(translatedHtml);
                        
                        // Remove Google Translate wrapper elements (these don't contain content we need)
                        $temp.find('.goog-te-spinner-pos, .goog-te-banner-frame, .skiptranslate, .goog-te-banner').remove();
                        
                        // Unwrap font tags (remove tag but keep content)
                        $temp.find('font[dir="auto"], font[style*="vertical-align: inherit"]').each(function() {
                            const $font = $(this);
                            $font.replaceWith($font.contents());
                        });
                        
                        const cleanHtml = $temp.html();
                        
                        // Update with HTML
                        $translationDiv.html(cleanHtml);
                    } else {
                        // Plain text - use text() method
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

    $(document).on('click', '#cp-wpml-auto-translate-modal-next', function () {
        const $modal = $(SELECTORS.languageModal);
        const selectedIds = $modal.data('selected-ids') || [];
        const selectedLanguage = $(SELECTORS.langSelect).val();

        if (!selectedLanguage) {
            alert('Please select a target language.');
            return;
        }

        if (!selectedIds.length) {
            alert('No posts selected for translation.');
            return;
        }

        // Check if Google Translate supports this language
        if (!isGoogleTranslateSupported(selectedLanguage)) {
            const langName = CP_WPML_AUTO_TRANSLATE.languages.find(function(l) { return l.code === selectedLanguage; });
            const langDisplayName = langName ? langName.name : selectedLanguage;
            alert('Sorry, Google Translate does not support "' + langDisplayName + '" language. Please select a different language.');
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
     */
    function initRowActionTranslateButton() {
        $(document).on('click', '.cp-wpml-row-translate-btn', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            
            if (!postId) {
                alert('Could not detect post ID.');
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
            $(mutation.addedNodes).each(function () {
                if (this.nodeType !== 1) return;
                ensureGoogleButtons(this);
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
