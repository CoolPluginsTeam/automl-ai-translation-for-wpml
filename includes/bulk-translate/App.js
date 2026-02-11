import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import StatusModal from './status-modal';
import { useDispatch, useSelector } from 'react-redux';
import { resetStore, updateServiceProvider } from './redux-store/features/actions';
import { selectCountInfo } from './redux-store/features/selectors';
import ChromeAiTranslator from './components/translate-provider/local-ai/local-ai-translate';
import ErrorModalBox from './components/error-modal-box';
import SettingModal from './setting-modal';
import DOMPurify from 'dompurify';
import Notice from './components/notice';

const App = ({ onDestory, prefix, postIds }) => {
    const dispatch = useDispatch();
    const { languageObject = {} } = automl_wpml_bulk_translate_object || {};
    const emptyPostIdsErrorMessage = sprintf(__('Please select at least one %s for translation.', 'autopoly-ai-translation-for-polylang-pro'), automl_wpml_bulk_translate_object.post_label);
    const [selectedLanguages, setSelectedLanguages] = useState([]);
    // Don't show error for string translation page even if postIds is empty
    const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;
    const [errorMessage, setErrorMessage] = useState((postIds.length === 0 && !isStringTranslationPage) ? emptyPostIdsErrorMessage : '');
    const [settingModalVisibility, setSettingModalVisibility] = useState(false);
    const [statusModalVisibility, setStatusModalVisibility] = useState(false);
    const translatePostsCount = useSelector(selectCountInfo).totalPosts;
    const [isLoading, setIsLoading] = useState(true);
    const [errorModal, setErrorModal] = useState(false);
    const [localAiModalError, setLocalAiModalError] = useState(false);

    const destroyApp = (e) => {
        setStatusModalVisibility(false);
        setSettingModalVisibility(false);
        onDestory(e);
    }


    useEffect(() => {
        const checkStatus = async () => {
            const status = await ChromeAiTranslator.languageSupportedStatus('en', 'hi', 'English', 'Hindi');
            if (status.type === 'browser-not-supported' || status.type === 'translation-api-not-available' || status.type === 'browser-not-supported') {
                setLocalAiModalError(__(status.html[0].outerHTML, 'autopoly-ai-translation-for-polylang-pro'));
            }

            setIsLoading(false);
        }

        checkStatus();
    }, [statusModalVisibility]);

    useEffect(() => {
        if (!statusModalVisibility && !settingModalVisibility) {
            dispatch(resetStore());
        }
    }, [statusModalVisibility, settingModalVisibility, dispatch]);

    const settingModalVisibilityHandler = async () => {
        if (selectedLanguages.length === 0 && !settingModalVisibility) {
            setErrorMessage(__('Please select at least one language', 'autopoly-ai-translation-for-polylang-pro'));
            setErrorModal(true);
            return;
        }

        setSettingModalVisibility((prev) => !prev);
    }

    const handleLanguageChange = (e) => {
        const { value } = e.target;
        console.log('value', value);
        const checked = e.target.checked;
        if (checked) {
            setSelectedLanguages([...selectedLanguages, value]);
        } else {
            setSelectedLanguages(selectedLanguages.filter(language => language !== value));
        }
    }

    const closeErrorModal = (e) => {
        setErrorModal(false);
    }

    const handleSelectAllLanguages = (e) => {
        const checked = e.target.checked;
        if (checked) {
            setSelectedLanguages(Object.keys(languageObject));
        } else {
            setSelectedLanguages([]);
        }
    }

    const updateProviderHandler = (services) => {
        dispatch(updateServiceProvider(services));
        setSettingModalVisibility(false);
        setStatusModalVisibility(true);
        setIsLoading(false);
    }

    const containerCls=()=>{
        let cls=[];
        if(statusModalVisibility){
            cls.push(`${prefix}-status-modal-active`);
        }

        if(settingModalVisibility){
            cls.push(`${prefix}-setting-modal-active`);
        }

        if(!translatePostsCount && !settingModalVisibility && statusModalVisibility){
            cls.push(`${prefix}-empty-posts`);
        }

        return cls.join(' ');
    }

    const SelectLanguageNotice = () => {

        const notices = [];
      
        const noticeLength = notices.length;
      
        if (notices.length > 0) {
          return notices.map((notice, index) => <Notice className={notice.className} key={index} lastNotice={index === noticeLength - 1}>{notice.message}</Notice>);
        }
      
        return;
    }

    return <div
        id={`${prefix}-container`}
        className={containerCls()}>
        {settingModalVisibility && <SettingModal
            prefix={prefix}
            onDestory={destroyApp}
            onCloseHandler={settingModalVisibilityHandler}
            updateProviderHandler={updateProviderHandler} 
            localAiModalError={localAiModalError}
        />}

        {statusModalVisibility && !settingModalVisibility && (isLoading ?
            <div
                className={`${prefix}-skeleton-loader`}></div> :
            <StatusModal
                postIds={postIds}
                selectedLanguages={selectedLanguages}
                prefix={prefix}
                onDestory={destroyApp}
            />)}
        {!statusModalVisibility && !settingModalVisibility &&
            <div
                className={`${prefix}-language-container`}>
                <div
                    className={`${prefix}-header`}>
                    <h2>{__('Step 1: Select Languages', 'autopoly-ai-translation-for-polylang-pro')}</h2>
                    <span
                        className="close"
                        onClick={destroyApp}
                        title={__('Close', 'autopoly-ai-translation-for-polylang-pro')}
                    >
                        &times;
                    </span>
                </div>
                {errorMessage && errorMessage !== '' ? (errorModal ? <ErrorModalBox
                    message={errorMessage}
                    onClose={closeErrorModal}
                /> : <div
                    className={`${prefix}-error-message`}
                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(errorMessage) }}
                />) :
                    <>
                        <div
                            className={`${prefix}-body`}>
                            <SelectLanguageNotice />
                            <div
                                className={`${prefix}-languages`}>
                                {Object.keys(languageObject).map((language) => {
                                    return (automl_wpml_bulk_translate_object.default_language_slug && automl_wpml_bulk_translate_object.default_language_slug === language ? null : <div key={language} className={`${prefix}-language`}>
                                        <div
                                            title={(!postIds.length && !isStringTranslationPage) ? emptyPostIdsErrorMessage : languageObject[language].name}>
                                            <input
                                                type="checkbox"
                                                name="languages"
                                                id={language}
                                                value={language}
                                                onChange={(e) => handleLanguageChange(e)}
                                                disabled={!postIds.length && !isStringTranslationPage}
                                                checked={selectedLanguages.includes(language)} />
                                            <label
                                                htmlFor={language}
                                                className={`${prefix}-language-label`}
                                                title={languageObject[language].name}
                                            >
                                                <img
                                                    src={languageObject[language].flag}
                                                    alt={languageObject[language].name} />
                                                &nbsp; {languageObject[language].name}
                                            </label>
                                        </div>
                                    </div>)
                                })}
                            </div>
                            <div
                                className={`${prefix}-select-all-languages`}>
                                <input
                                    type="checkbox"
                                    name="select-all-languages"
                                    id="select-all-languages"
                                    onChange={(e) => handleSelectAllLanguages(e)}
                                    checked={selectedLanguages.length === Object.keys(languageObject).length} />
                                <label
                                    htmlFor="select-all-languages"
                                >
                                    {selectedLanguages.length === Object.keys(languageObject).length ? __('Unselect All', 'autopoly-ai-translation-for-polylang-pro') : __('Select All', 'autopoly-ai-translation-for-polylang-pro')}
                                </label>
                            </div>
                        </div>
                        <div
                            className={`${prefix}-footer`}>
                            <button
                                className={`${prefix}-footer-button button button-primary`}
                                onClick={destroyApp}
                                title={(!postIds.length && !isStringTranslationPage) ? emptyPostIdsErrorMessage : ''}>
                                {__('Close', 'autopoly-ai-translation-for-polylang-pro')}
                            </button>
                            <button
                                className={`${prefix}-footer-button button button-primary`}
                                onClick={settingModalVisibilityHandler}
                                disabled={(!postIds.length && !isStringTranslationPage) || !selectedLanguages.length}
                                title={(!postIds.length && !isStringTranslationPage) ? emptyPostIdsErrorMessage : (!selectedLanguages.length ? __('Please select at least one language', 'autopoly-ai-translation-for-polylang-pro') : '')}>
                                {__('Translate', 'autopoly-ai-translation-for-polylang-pro')}
                            </button>
                        </div>
                    </>}
            </div>
        }
    </div>
}

export default App;
