import App from './App';
import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';
import './index.css';
import { store } from './redux-store/store';
import { Provider } from 'react-redux';
import { __ } from '@wordpress/i18n';
import LoopCallback from './components/loop-callback';
import LocalAITranslate from './components/translate-provider/local-ai/local-ai-translate';

(() => {
    const BulkTranslate = (props) => {
        const [modalVisible, setModalVisible] = useState(false);
        const [postIds, setPostIds] = useState([]);
        const prefix = props.prefix;
        const wrapper = document.getElementById(`${prefix}-wrapper`);
        let localAiCheckInProgres = false;

        const handleModalVisibility = (e) => {
            e.preventDefault();

            setModalVisible(prev => !prev);
            destroyGoogleWidget();

        }

        // 1️⃣ Clear old cached data on page load
        const clearOldTranslatorCacheOnLoad = () => {
            const loadKey = 'ATFPP_LOCAL_AI_PAGE_LOADED';

            if (sessionStorage.getItem(loadKey)) {
                return;
            }

            localStorage.removeItem('ATFPP_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES');
            sessionStorage.setItem(loadKey, '1');
        };

        // 2️⃣ Language pack availability check (gesture-based)
        const checkLanguagePackAvailability = async () => {
            const languagesObj = { ...atfpp_bulk_translate_object.languageObject };
            const supportedLanguages = LocalAITranslate.supportedLanguages || [];

            delete languagesObj.en;

            let savedLanguages = [];
            try {
                savedLanguages = JSON.parse(
                    localStorage.getItem('ATFPP_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES')
                ) || [];
            } catch {
                savedLanguages = [];
            }

            savedLanguages.forEach(lang => delete languagesObj[lang]);

            if (!Object.keys(languagesObj).length) return;


            const processNextLanguage = async () => {
                if (localAiCheckInProgres || Object.keys(languagesObj).length === 0) return;

                localAiCheckInProgres = true;
                const targetLang = Object.keys(languagesObj)[0];

                if(supportedLanguages.includes(targetLang)){
                    try {
                        const status = await LocalAITranslate.languagePairAvality('en', targetLang);
    
                        if (['available', 'readily'].includes(status)) {
                            delete languagesObj[targetLang] ;
                            savedLanguages.push(targetLang);
                            localStorage.setItem(
                                'ATFPP_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES',
                                JSON.stringify(savedLanguages)
                            );
                        }
                    } catch (err) {
                        console.error('Language availability check failed:', targetLang, err);
                    }
                }else{
                    delete languagesObj[targetLang];
                }

                localAiCheckInProgres = false;

                if (Object.keys(languagesObj).length === 0) {
                    document.removeEventListener('mousemove', onMouseMove);

                    const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
                    doActionsBtn.forEach(btn => {
                        btn.removeEventListener('mousemove', checkLanguagePackAvailability);
                        btn.removeEventListener('mouseleave', checkLanguagePackAvailability);
                        btn.removeEventListener('mouseenter', checkLanguagePackAvailability);
                    });

                }
            };

            const onMouseMove = () => {
                processNextLanguage();
            };

            document.addEventListener('mousemove', onMouseMove);
        };

        // const bulkTranslationHandler = (e) => {
        //     e.preventDefault();

        //     let checkboxClass = 'table.widefat input[name="post[]"]:checked';

        //     if (atfpp_bulk_translate_object.taxonomy_page && '' !== atfpp_bulk_translate_object.taxonomy_page) {
        //         checkboxClass = 'table.widefat input[name="delete_tags[]"]:checked';
        //     }

        //     const selectedPostIds = document.querySelectorAll(checkboxClass);
        //     const postIds = Array.from(selectedPostIds).map(postId => postId.value);

        //     checkLanguagePackAvailability();

        //     setPostIds(postIds);
        //     handleModalVisibility(e);
        // }
        const bulkTranslationHandler = (e) => {
            e.preventDefault();

            // Check if we're on the String Translation page
            const isStringTranslationPage = window.location.href.indexOf('wpml-string-translation') !== -1;
            
            let checkboxClass = 'table.widefat input[name="post[]"]:checked';
            let postIds = [];
            let stringFilters = {};

            if (isStringTranslationPage) {
                // Collect filter values from String Translation page
                // Use more specific selectors to ensure we get the values
                const statusSelect = document.querySelector('select[name="icl_st_filter_status"]');
                const contextSelect = document.querySelector('select[name="icl_st_filter_context"]');
                const prioritySelect = document.querySelector('select[name="icl-st-filter-translation-priority"]');
                const searchInput = document.querySelector('input#icl_st_filter_search');
                const searchTranslationCheckbox = document.querySelector('input#search_translation:not([disabled])');
                const exactMatchCheckbox = document.querySelector('input#icl_st_filter_search_em:not([disabled])');

                // Get values - use empty string if not found
                const statusValue = statusSelect ? (statusSelect.value || '') : '';
                const contextValue = contextSelect ? (contextSelect.value || '') : '';
                const priorityValue = prioritySelect ? (prioritySelect.value || '') : '';
                const searchValue = searchInput ? (searchInput.value || '') : '';
                const searchTranslationValue = (searchTranslationCheckbox && searchTranslationCheckbox.checked) ? '1' : '';
                const exactMatchValue = (exactMatchCheckbox && exactMatchCheckbox.checked) ? '1' : '';

                stringFilters = {
                    status: statusValue,
                    context: contextValue,
                    'translation-priority': priorityValue,
                    search: searchValue,
                    search_translation: searchTranslationValue,
                    exact_match: exactMatchValue
                };

                // Store string filters globally for use in App/StatusModal
                window.wpmlStringFilters = stringFilters;
                window.wpmlIsStringTranslationPage = true;
                // For strings, we don't need postIds - we'll translate ALL strings matching filters
                postIds = [];
            } else {
                // Normal post/taxonomy flow
                if (atfpp_bulk_translate_object.taxonomy_page && '' !== atfpp_bulk_translate_object.taxonomy_page) {
                    checkboxClass = 'table.widefat input[name="delete_tags[]"]:checked';
                }

                const selectedPostIds = document.querySelectorAll(checkboxClass);
                postIds = Array.from(selectedPostIds).map(postId => postId.value);
                
                // Clear string translation flags
                window.wpmlStringFilters = {};
                window.wpmlIsStringTranslationPage = false;
            }

            checkLanguagePackAvailability();

            setPostIds(postIds);
            handleModalVisibility(e);
        }

        const destroyGoogleWidget = () => {
            const googleWidget = document.querySelector('.skiptranslate iframe[id=":1.container"]');
            document.body.classList.remove(prefix + '-google-translate');

            if (googleWidget) {
                const closeButton = googleWidget.contentDocument.querySelector('a[id=":1.close"][title="Close"] img');
                if (closeButton) {
                    closeButton.click();
                }
            }
        }

        useEffect(() => {
            const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
            
            if (doActionsBtn) {
                clearOldTranslatorCacheOnLoad();
                doActionsBtn.forEach(btn => {
                    btn.addEventListener('click', bulkTranslationHandler);
                    btn.addEventListener('mousemove', checkLanguagePackAvailability);
                    btn.addEventListener('mouseleave', checkLanguagePackAvailability);
                    btn.addEventListener('mouseenter', checkLanguagePackAvailability);
                });
            }
        }, []);

        useEffect(() => {
            const mainWrapper = document.getElementById(`${prefix}-wrapper`);
            if (mainWrapper) {
                mainWrapper.classList.toggle(`${prefix}-active`, modalVisible);
            }
        }, [modalVisible]);

        return (
            modalVisible ? (
                <App onDestory={handleModalVisibility} prefix={prefix} postIds={postIds} />
            ) : null
        );
    }

    window.addEventListener('load', async () => {
        const prefix = 'atfpp-bulk-translate';

        await new Promise(resolve => setTimeout(resolve, 500));

        // Move bulk translate button to correct position on string translation page
        const bulkTranslateBtn = document.querySelector(`.${prefix}-btn`);
        bulkTranslateBtn.style.display = 'block';
        const stringFilterDiv = document.querySelector('.wpml-string-translation-filter');
        const filterButton = document.querySelector('#icl_st_filter_search_sb');

        if (bulkTranslateBtn && stringFilterDiv && filterButton) {
            // Insert the button after the filter button
            filterButton.insertAdjacentElement('afterend', bulkTranslateBtn);
        }

        ReactDOM.createRoot(document.getElementById(`${prefix}-wrapper`)).render(
            <Provider store={store}>
                <BulkTranslate prefix={prefix} />
            </Provider>
        );
    });
})();
