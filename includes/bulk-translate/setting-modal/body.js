import Providers from "./providers";
import TranslateService from "../components/translate-provider";

const SettingModalBody = (props) => {
    const { prefix, localAiModalError } = props;
    const ServiceProviders = TranslateService();
    const openai_aiDisabled = !atfpp_bulk_translate_object?.AIServices?.includes('openai');
    const google_aiDisabled = !atfpp_bulk_translate_object?.AIServices?.includes('google');
    const deepl_aiDisabled = !atfpp_bulk_translate_object?.AIServices?.includes('deepl');
    const openrouter_aiDisabled = !atfpp_bulk_translate_object?.AIServices?.includes('openrouter');
    return (
        <div className={`${prefix}-setting-modal-body`}>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Translate</th>
                        <th>Docs</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.keys(ServiceProviders).map((provider) => (
                        <Providers
                            key={provider}
                            {...props}
                            openai_aiDisabled={openai_aiDisabled}
                            google_aiDisabled={google_aiDisabled}
                            openrouter_aiDisabled={openrouter_aiDisabled}
                            deepl_aiDisabled={deepl_aiDisabled}
                            localAiTranslatorDisabled={localAiModalError}
                            localAiModalError={localAiModalError}
                            openErrorModalHandler={props.errorModalHandler}
                            Service={provider}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default SettingModalBody;
