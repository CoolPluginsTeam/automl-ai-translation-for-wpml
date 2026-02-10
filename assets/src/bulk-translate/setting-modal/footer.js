import { __ } from "@wordpress/i18n";

const SettingModalFooter = ({ setSettingVisibility, prefix }) => {
    return (
        <div className={`${prefix}-setting-modal-footer`}>
            <button className={`${prefix}-setting-close button button-primary`} onClick={() => setSettingVisibility(false)}>{__("Back", 'automl-ai-translation-for-wpml')}</button>
        </div>
    );
}

export default SettingModalFooter;
