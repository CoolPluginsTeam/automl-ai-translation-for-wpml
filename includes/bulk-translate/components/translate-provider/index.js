// import YandexTranslater from "./yandex";
import localAiTranslator from "./local-ai";
import AIService from "./ai-services";
import { sprintf, __ } from "@wordpress/i18n";

/**
 * Provides translation services using Yandex Translate.
 */
export default (props) => {
    props=props || {};
    const { Service = false, openErrorModalHandler=()=>{}, prefix='' } = props;
    const adminUrl = window.atfpp_bulk_translate_object.admin_url;
    const assetsUrl = window.atfpp_bulk_translate_object.atfpp_url+'assets/images/';
    const errorIcon = assetsUrl + 'error-icon.svg';

    const Services = {
        localAiTranslator: {
            Provider: localAiTranslator,
            title: "Chrome Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Chrome AI Translator",
            heading: sprintf(__("Translate Using %s", "autopoly-ai-translation-for-polylang-pro"), "Chrome built-in API"),
            Docs: "https://docs.coolplugins.net/doc/chrome-ai-translation-polylang/?utm_source=atfp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_chrome",
            BetaEnabled: true,
            ButtonDisabled: props.localAiTranslatorButtonDisabled,
            ErrorMessage: props.localAiTranslatorButtonDisabled ? <div className={`${prefix}-provider-error button button-primary`} onClick={() => openErrorModalHandler(props.localAiTranslatorButtonDisabled)}><img src={errorIcon} alt="error" /> {__('View Error', 'autopoly-ai-translation-for-polylang-pro')}</div> : <></>,
            Logo: 'chrome.png',
            filterHtmlContent: true
        },
        openai_ai: {
            Provider: AIService,
            title: "OpenAI Model",
            SettingBtnText: "Translate",
            serviceLabel: "OpenAI",
            heading: sprintf(__("Translate Using %s Model", "autopoly-ai-translation-for-polylang-pro"), "OpenAI"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-open-ai-polylang/?utm_source=atfp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_openai",
            BetaEnabled: true,
            ButtonDisabled: props.openai_aiButtonDisabled,
            ErrorMessage: props.openai_aiButtonDisabled ? <a className={`${prefix}-provider-error button button-primary`} href={adminUrl + 'admin.php?page=polylang-atfpp-dashboard&tab=settings'} target="_blank"><img src={errorIcon} alt="error" /> {__('Add API Key', 'autopoly-ai-translation-for-polylang-pro')}</a> : <></>,
            Logo: 'openai.png',
            filterHtmlContent: true
        },
        google_ai: {
            Provider: AIService,
            title: "Gemini Model",
            SettingBtnText: "Translate",
            serviceLabel: "Gemini",
            heading: sprintf(__("Translate Using %s Model", "autopoly-ai-translation-for-polylang-pro"), "Gemini"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-gemini-ai-polylang/?utm_source=atfp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_gemini",
            BetaEnabled: true,
            ButtonDisabled: props.google_aiButtonDisabled,
            ErrorMessage: props.google_aiButtonDisabled ? <a className={`${prefix}-provider-error button button-primary`} href={adminUrl + 'admin.php?page=polylang-atfpp-dashboard&tab=settings'} target="_blank"><img src={errorIcon} alt="error" /> {__('Add API Key', 'autopoly-ai-translation-for-polylang-pro')}</a> : <></>,
            Logo: 'gemini.png',
            filterHtmlContent: true
        }
    };

    if (!Service) {
        return Services;
    }
    return Services[Service];
};
