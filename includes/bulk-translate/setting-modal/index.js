import React, { useState } from "react";
import ReactDOM from "react-dom/client";
import SettingModalHeader from "./header";
import SettingModalBody from "./body";
import SettingModalFooter from "./footer";
import { __ } from "@wordpress/i18n";
import ErrorModalBox from "../components/error-modal-box";

const SettingModal = (props) => {
    const prefix=props.prefix || 'automl-wpml-bulk-translate';
    const imgFolder = automl_wpml_bulk_translate_object.automl_wpml_url + 'assets/images/';
    const [errorModal, setErrorModal] = useState(false);

    /**
     * Function to handle fetching content based on the target button clicked.
     * Sets the target button and updates the fetch status to true.
     * @param {Event} e - The event object representing the button click.
     */
    const startTranslationHandler = async (e) => {
        let targetElement = !e.target.classList.contains(`${prefix}-service-btn`) ? e.target.closest(`.${prefix}-service-btn`) : e.target;

        if (!targetElement) {
            return;
        }

        const dataService = targetElement.dataset && targetElement.dataset.service;

        props.updateProviderHandler(dataService);
    };

    const errorModalHandler = (msg) => {
        setErrorModal(msg);
    }

    const closeErrorModal = () => {
        setErrorModal(false);
    }

    return (
        <>
            {errorModal ? <ErrorModalBox message={errorModal} onDestroy={props.onDestory} onClose={closeErrorModal} Title='AutoPoly - AI Translation For Polylang (Pro)' prefix={prefix} /> :
            <div id={`${prefix}-setting-modal-container`}>
                <div className={`${prefix}-setting-modal-content`}>
                    <SettingModalHeader
                        setSettingVisibility={props.onDestory}
                        prefix={prefix}
                    />
                    <SettingModalBody
                        startTranslationHandler={startTranslationHandler}
                        imgFolder={imgFolder}
                        prefix={prefix}
                        localAiModalError={props.localAiModalError}
                        errorModalHandler={errorModalHandler}
                    />
                    <SettingModalFooter
                        setSettingVisibility={props.onCloseHandler}
                        prefix={prefix}
                    />
                </div>
            </div>}
        </>
    );
};

export default SettingModal;