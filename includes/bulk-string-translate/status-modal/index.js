import React, { useEffect, useState } from "react";
import {
  bulkTranslateEntries,
  initBulkTranslate,
  bulkTranslateStrings,
  initBulkTranslateStrings,
} from "../bulk-translate";
import { useSelector, useDispatch } from "react-redux";
import {
  selectTranslatePostInfo,
  selectProgressStatus,
  selectCountInfo,
  selectPendingPosts,
  selectServiceProvider,
  selectErrorPostsInfo,
  selectTargetLanguages,
} from "../redux-store/features/selectors";
import { __, sprintf } from "@wordpress/i18n";
import ErrorModalBox from "../components/error-modal-box";
import AIService from "../components/translate-provider/ai-services";
import { store } from "../redux-store/store";
import DOMPurify from "dompurify";

const StatusModal = ({ postIds, selectedLanguages, prefix, onDestory }) => {
  const storeDispatch = useDispatch();
  const [isLoading, setIsLoading] = useState(true);
  const [errorModal, setErrorModal] = useState(false);
  const [errorModalData, setErrorModalData] = useState(false);
  const translatePostInfo = useSelector(selectTranslatePostInfo);
  const [destroyHandlers, setDestroyHandlers] = useState([]);
  const errorPostsInfo = useSelector(selectErrorPostsInfo);
  const pendingPosts = useSelector(selectPendingPosts);
  const serviceProvider = useSelector(selectServiceProvider);
  const [progressBarVisibility, setProgressBarVisibility] = useState(true);
  const [charactersCountVisibility, setCharactersCountVisibility] =
    useState(false);
  const [bulkStatus, setBulkStatus] = useState("status");
  const countInfo = useSelector(selectCountInfo);
  let [emptyPostMessage, setEmptyPostMessage] = useState(
    sprintf(
      __(
        "Translations already exist for all selected %s in the chosen languages. There are no new %s to translate.",
        "automl-ai-translation-for-wpml",
      ),
      automl_wpml_bulk_translate_object.post_label,
      automl_wpml_bulk_translate_object.post_label,
    ),
  );
  let progressStatus = useSelector(selectProgressStatus);
  progressStatus = progressStatus.toFixed(1);
  progressStatus = Math.min(progressStatus, 100);
  const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;

  useEffect(() => {
    const translateContent = async () => {
      const isStringTranslationPage =
        window.wpmlIsStringTranslationPage || false;
      const stringFilters = window.wpmlStringFilters || {};
      if (isStringTranslationPage) {
        // String translation flow
        const { bulkTranslateStrings } = await import("../bulk-translate");
        const response = await bulkTranslateStrings({
          langs: selectedLanguages,
          storeDispatch,
          stringFilters: stringFilters,
        });
        setIsLoading(false);
        if (!response.success) {
          setEmptyPostMessage(
            response.message ||
              __(
                "No strings found to translate.",
                "automl-ai-translation-for-wpml",
              ),
          );
          return;
        }
        // Initialize string translation flow
        initBulkTranslateStrings(
          response.stringKeys,
          response.stringsByLanguage,
          response.nonce,
          storeDispatch,
          prefix,
          updateDestoryHandler,
          response.totalPerLanguage || {},
          response.fetchPage || null,
        );
      } else {
        // Post/taxonomy translation flow
        const response = await bulkTranslateEntries({
          ids: postIds,
          langs: selectedLanguages,
          storeDispatch,
        });
        setIsLoading(false);

        if (
          !response.success &&
          false === response.success &&
          response.message
        ) {
          setEmptyPostMessage(response.message);
          return;
        }

        initBulkTranslate(
          response.postKeys,
          response.nonce,
          storeDispatch,
          prefix,
          updateDestoryHandler,
        );
      }
    };
    translateContent();
  }, []);

  const handleErrorModal = (data) => {
    setErrorModalData(data);
    setErrorModal(true);
  };

  const closeErrorModal = (e) => {
    setErrorModal(false);
    setErrorModalData(false);
  };

  const updateDestoryHandler = (callback) => {
    setDestroyHandlers((prev) => [...prev, callback]);
  };

  const onModalClose = (e) => {
    destroyHandlers.forEach(
      (callback) => typeof callback === "function" && callback(),
    );
    onDestory(e);
  };

  useEffect(() => {
    if (countInfo.totalPosts < 1 && !isLoading && bulkStatus !== "status") {
      updateBulkStatus("status");
      return;
    }

    if (translatePostInfo && Object.keys(translatePostInfo).length > 0) {
      if (pendingPosts.length < 1) {
        updateBulkStatus("completed");
        return;
      }

      let error = false;
      let running = false;

      const runLoop = (items, index) => {
        const status = translatePostInfo[items[index]].status;

        if (
          status === "running" ||
          status === "in-progress" ||
          status === "pending"
        ) {
          running = true;
          bulkStatus !== "running" && updateBulkStatus("running");
          return;
        }

        if (status === "error") {
          error = true;
        }

        index++;
        if (index < items.length) {
          runLoop(items, index);
        }
      };

      runLoop(Object.keys(translatePostInfo), 0);

      if (running) return;

      if (error) {
        updateBulkStatus("pending");
      } else {
        updateBulkStatus("pending");
      }
    }
  }, [translatePostInfo]);

  const updateBulkStatus = (status) => {
    setBulkStatus(status);
  };

  const getBulkStatus = () => {
    switch (bulkStatus) {
      case "running":
        return __("In Progress", "automl-ai-translation-for-wpml");
      case "pending":
        return __("Pending", "automl-ai-translation-for-wpml");
      case "completed":
        return __("Completed", "automl-ai-translation-for-wpml");
      default:
        return __("Status", "automl-ai-translation-for-wpml");
    }
  };

  useEffect(() => {
    if (progressStatus >= 100 && pendingPosts.length < 1) {
      if (countInfo.postsTranslated < 1 && countInfo.stringsTranslated < 1) {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
        return;
      }

      if (countInfo.stringsTranslated > 0) {
        setTimeout(() => {
          setCharactersCountVisibility(true);
        }, 1000);
      }

      setTimeout(() => {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
      }, 7500);
    }
  }, [pendingPosts]);

  const AIErrorBtnHandler = (e) => {
    const type = {
      translateAgain: AIService.translateAgain,
      continue: AIService.translateComplete,
    };

    const btnType = e.target.dataset.status;
    type[btnType]({
      postId: errorModalData.parentPostId,
      targetLang: errorModalData.targetLanguage,
      storeDispatch,
      prefix,
      updateDestoryHandler,
      nonce: errorModalData.nonce,
      closeErrorModal,
      completedStrings: errorModalData.completedStrings,
      totalPosts: errorModalData.totalPosts,
    });
  };

  const getTranslatedPostLink = () => {
    const translatedLanguagesArr = Object.values(translatePostInfo).filter(
      (post) => post.status === "completed" && post.targetLanguage,
    );
    const translatedLangs = translatedLanguagesArr
      .map((post) => post.targetLanguage)
      .filter((lang, index, self) => self.indexOf(lang) === index);

    if (translatedLangs.length === 1) {
      const translatedLang = translatedLangs[0];
      // Get current query params
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);

      // Set or update the required params
      params.set("lang", translatedLang);
      params.set("orderby", "date");
      params.set("order", "desc");

      const newQuery = Object.fromEntries(params.entries());

      return (
        window.location.href.split("?")[0] +
        "?" +
        new URLSearchParams(newQuery).toString()
      );
    } else {
      return window.location.href;
    }
  };

  const getServiceProviderLabel = () => {
    switch (serviceProvider) {
      case "google":
        return "Google Translate";
      case "localAiTranslator":
        return "Chrome AI Translator";
      case "openai_ai":
        return "OpenAI";
      case "google_ai":
        return "Gemini";
      case "openrouter_ai":
        return "OpenRouter";
      case "deepl_ai":
        return "DeepL";
      default:
        return "AI Translator";
    }
  };

  const allPostStatus = (postId) => {
    const targetLangsArr = selectTargetLanguages(store.getState(), postId);
    let allPostStatus = true;

    if (!targetLangsArr || !targetLangsArr.length) {
      return true;
    }

    for (let i = 0; i < targetLangsArr.length; i++) {
      if (
        !translatePostInfo[postId + "_" + targetLangsArr[i]] ||
        ["pending", "in-progress", "running"].includes(
          translatePostInfo[postId + "_" + targetLangsArr[i]].status,
        )
      ) {
        allPostStatus = false;
        break;
      }
    }

    return allPostStatus;
  };
  return errorModal ? (
    <ErrorModalBox
      message={errorModalData.errorHtml}
      onClose={closeErrorModal}
      Title={__("Bulk Translation Error", "automl-ai-translation-for-wpml")}
      prefix={prefix}
    >
      {errorModalData.aiError && (
        <div className={`${prefix}-ai-error-buttons`}>
          <button
            className={`${prefix}-ai-error-button button`}
            data-status="translateAgain"
            onClick={AIErrorBtnHandler}
          >
            {__("Translate", "automl-ai-translation-for-wpml")}
          </button>
          <button
            className={`${prefix}-ai-error-button button`}
            data-status="continue"
            onClick={AIErrorBtnHandler}
          >
            {__("Continue", "automl-ai-translation-for-wpml")}
          </button>
        </div>
      )}
    </ErrorModalBox>
  ) : (
    <div id={`${prefix}-status-modal-container`}>
      <h2 className={`${prefix}-bulk-status-heading ${bulkStatus}`}>
        {sprintf(
          __("Bulk Translation %s", "automl-ai-translation-for-wpml"),
          getBulkStatus(),
        )}
        {bulkStatus === "running" && (
          <span className={`${prefix}-bulk-status-running`}></span>
        )}
      </h2>
      <div className={`${prefix}-status-modal-close`} onClick={onModalClose}>
        &times;
      </div>
      {countInfo.totalPosts < 1 &&
      countInfo.errorPosts < 1 &&
      countInfo.stringsTranslated < 1 &&
      !isLoading ? (
        <p>{emptyPostMessage}</p>
      ) : (
        <>
          {isLoading && <div className={`${prefix}-progress-skeleton`}></div>}
          {countInfo.totalPosts > 1 && progressBarVisibility && !isLoading ? (
            <>
              <div className={`${prefix}-overall-progress`}>
                <div className={`${prefix}-progress-bar`}>
                  <div
                    className={`${prefix}-progress`}
                    style={{ width: progressStatus + "%" }}
                  >
                    {progressStatus + "%"}
                  </div>
                </div>
              </div>
              {charactersCountVisibility && (
                <div className={`${prefix}-translator-strings-count`}>
                  {__(
                    "Wahooo! You have saved your valuable time via auto translating",
                    "automl-ai-translation-for-wpml",
                  )}
                  <strong className="totalChars">
                    {" "}
                    {countInfo.charactersTranslated}{" "}
                  </strong>
                  {__("characters using", "automl-ai-translation-for-wpml")}
                  <strong> {getServiceProviderLabel()}</strong>
                </div>
              )}
            </>
          ) : (
            countInfo.stringsTranslated > 0 && (
              <div className={`${prefix}-count-container`}>
                <div className={`${prefix}-string-count`}>
                  <span className={`${prefix}-count-text-heading`}>
                    {__("Strings:", "automl-ai-translation-for-wpml")}{" "}
                  </span>
                  <span className={`${prefix}-string-number`}>
                    {countInfo.stringsTranslated}
                  </span>
                </div>
                <div className={`${prefix}-char-count`}>
                  <span className={`${prefix}-count-text-heading`}>
                    {__("Characters:", "automl-ai-translation-for-wpml")}{" "}
                  </span>
                  <span className={`${prefix}-char-number`}>
                    {countInfo.charactersTranslated}
                  </span>
                </div>
                <div className={`${prefix}-time-taken`}>
                  <span className={`${prefix}-count-text-heading`}>
                    {__("Time Taken:", "automl-ai-translation-for-wpml")}{" "}
                  </span>
                  <span className={`${prefix}-time-taken-number`}>
                    {countInfo.timeTaken ?? 0}{" "}
                    {__("seconds", "automl-ai-translation-for-wpml")}
                  </span>
                </div>
              </div>
            )
          )}
          {(!isStringTranslationPage ||
            (!isLoading && pendingPosts.length === 0)) && (
            <div className={`${prefix}-status-table-container`}>
              <div>
                <table className={`${prefix}-status-table`}>
                  <thead>
                    <tr>
                      <th>
                        {__("Language", "automl-ai-translation-for-wpml")}
                      </th>
                      <th>{__("Status", "automl-ai-translation-for-wpml")}</th>
                      <th>{__("Title", "automl-ai-translation-for-wpml")}</th>
                    </tr>
                  </thead>

                  <tbody>
                    {isLoading && (
                      <>
                        <tr>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                          <td>
                            <div
                              className={`${prefix}-progress-skeleton`}
                            ></div>
                          </td>
                        </tr>
                      </>
                    )}
                    {!isLoading &&
                      Object.keys(errorPostsInfo).length > 0 &&
                      Object.keys(errorPostsInfo).map((key, index) => {
                        return (
                          <React.Fragment key={key}>
                            <tr
                              key={`group-title-${key}`}
                              className={`${prefix}-group-title`}
                            >
                              <td colSpan="5">
                                {errorPostsInfo[key]?.title ||
                                  __(
                                    "Untitled",
                                    "automl-ai-translation-for-wpml",
                                  )}
                              </td>
                            </tr>
                            <tr key={key}>
                              <td
                                colSpan="4"
                                style={{ textAlign: "center", width: "100%" }}
                                className={`${prefix}-error-message`}
                                dangerouslySetInnerHTML={{
                                  __html: DOMPurify.sanitize(
                                    errorPostsInfo[key].errorMessage,
                                  ),
                                }}
                              ></td>
                            </tr>
                          </React.Fragment>
                        );
                      })}
                    {!isLoading &&
                      Object.keys(translatePostInfo).map((key, index) => {
                        const info = translatePostInfo[key];
                        const isStringAggregate = key.startsWith("strings_");
                        const workingStatus =
                          info.status === "running" ||
                          info.status === "in-progress";

                        if (isStringAggregate) {
                          const total = info.total || 0;
                          const completed = info.completed || 0;
                          const pct =
                            total > 0
                              ? Math.min(
                                  100,
                                  Math.round((100 * completed) / total),
                                )
                              : 0;
                          return (
                            <tr
                              key={key}
                              className={`${prefix}-td-${info.status}`}
                            >
                              <td className={`${prefix}-status-flag`}>
                                <div>
                                  {info.flagUrl && (
                                    <img
                                      src={info.flagUrl}
                                      width="20"
                                      alt={info.targetLanguage}
                                    />
                                  )}
                                  {info.languageName || info.targetLanguage}
                                </div>
                              </td>
                              <td>
                                <span
                                  className={`${prefix}-status ${
                                    info.messageClass || ""
                                  } ${info.status || ""}`}
                                >
                                  {info.status === "completed" &&
                                    __(
                                      "Completed",
                                      "automl-ai-translation-for-wpml",
                                    )}
                                  {info.status === "error" && info.errorMessage}
                                  {workingStatus &&
                                    `${completed}/${total} ${__(
                                      "strings",
                                      "automl-ai-translation-for-wpml",
                                    )}`}
                                  {info.status === "pending" &&
                                    __(
                                      "Pending",
                                      "automl-ai-translation-for-wpml",
                                    )}
                                </span>
                              </td>
                              <td>
                                {workingStatus
                                  ? `${completed} / ${total}`
                                  : info.status === "completed"
                                  ? `${total} ${__(
                                      "translated",
                                      "automl-ai-translation-for-wpml",
                                    )}`
                                  : "—"}
                              </td>
                            </tr>
                          );
                        }

                        const rows = [];
                        if (info.firstPostLanguage) {
                          rows.push(
                            <tr
                              key={`group-title-${info.parentPostId || key}`}
                              className={`${prefix}-group-title`}
                            >
                              <td colSpan="5">
                                {info.parentPostTitle ||
                                  __(
                                    "Untitled",
                                    "automl-ai-translation-for-wpml",
                                  )}
                              </td>
                            </tr>,
                          );
                        }

                        rows.push(
                          <tr
                            key={key}
                            className={`${prefix}-td-${info.status}`}
                          >
                            <td className={`${prefix}-status-flag`}>
                              <div>
                                {info.flagUrl && (
                                  <img
                                    src={info.flagUrl}
                                    width="20"
                                    alt={info.targetLanguage}
                                  />
                                )}
                                {info.languageName || info.targetLanguage}
                              </div>
                            </td>
                            {info.status === "error" ? (
                              <>
                                <td colSpan={info.errorHtml ? "2" : "3"}>
                                  {info.errorMessage}
                                </td>
                                {info.errorHtml && (
                                  <td
                                    colSpan="1"
                                    onClick={() => {
                                      handleErrorModal(info);
                                    }}
                                  >
                                    <button
                                      className={`${prefix}-status-error-button`}
                                    >
                                      {__(
                                        "Error Details",
                                        "automl-ai-translation-for-wpml",
                                      )}
                                    </button>
                                  </td>
                                )}
                              </>
                            ) : (
                              <>
                                <td>
                                  <span
                                    className={`${prefix}-status ${info.messageClass} ${info.status}`}
                                  >
                                    {info.status === "pending" &&
                                      __(
                                        "Pending",
                                        "automl-ai-translation-for-wpml",
                                      )}
                                    {info.status === "completed" &&
                                      __(
                                        "Completed",
                                        "automl-ai-translation-for-wpml",
                                      )}
                                    {workingStatus && (
                                      <div
                                        className={`${prefix}-progress-bar-circular`}
                                        data-id={
                                          info.parentPostId +
                                          "_" +
                                          info.targetLanguage
                                        }
                                      >
                                        <svg
                                          className={`${prefix}-circle`}
                                          viewBox="0 0 36 36"
                                        >
                                          <path
                                            className={`${prefix}-bg`}
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                          />
                                          <path
                                            className={`${prefix}-progress`}
                                            strokeDasharray="0, 100"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                          />
                                        </svg>
                                        <div className={`${prefix}-percentage`}>
                                          0%
                                        </div>
                                      </div>
                                    )}
                                  </span>
                                </td>
                                <td>
                                  {info.status === "completed" ? (
                                    <a
                                      href={info.postLink}
                                      target="_blank"
                                      rel="noopener noreferrer"
                                    >
                                      {info.targetPostTitle}
                                    </a>
                                  ) : info.status === "in-progress" ? (
                                    <div
                                      className={`${prefix}-${info.messageClass}-text`}
                                    >
                                      {__(
                                        "In Progress",
                                        "automl-ai-translation-for-wpml",
                                      )}
                                      <span></span>
                                    </div>
                                  ) : (
                                    <div
                                      className={`${prefix}-progress-skeleton short`}
                                    ></div>
                                  )}
                                </td>
                                <td>
                                  {info.status === "completed" &&
                                  info.targetPostId ? (
                                    <span className={`${prefix}-view-link`}>
                                      {allPostStatus(info.parentPostId) ? (
                                        <a
                                          href={info.postEditLink}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          className="button button-primary"
                                          title={sprintf(
                                            __(
                                              "Open the translated %s for review",
                                              "automl-ai-translation-for-wpml",
                                            ),
                                            automl_wpml_bulk_translate_object.post_label,
                                          )}
                                        >
                                          {__(
                                            "Review",
                                            "automl-ai-translation-for-wpml",
                                          )}
                                        </a>
                                      ) : (
                                        <button
                                          className="button disabled"
                                          disabled
                                          title={sprintf(
                                            __(
                                              "Please wait until all translations for this %s are complete before reviewing.",
                                              "automl-ai-translation-for-wpml",
                                            ),
                                            automl_wpml_bulk_translate_object.post_label,
                                          )}
                                        >
                                          {__(
                                            "Review",
                                            "automl-ai-translation-for-wpml",
                                          )}
                                        </button>
                                      )}
                                    </span>
                                  ) : info.status === "in-progress" ? (
                                    <div
                                      className={`${prefix}-${info.messageClass}-text`}
                                    >
                                      {__(
                                        "In Progress",
                                        "automl-ai-translation-for-wpml",
                                      )}
                                      <span></span>
                                    </div>
                                  ) : (
                                    <div
                                      className={`${prefix}-progress-skeleton short`}
                                    ></div>
                                  )}
                                </td>
                              </>
                            )}
                          </tr>,
                        );
                        return rows;
                      })}
                  </tbody>
                </table>
              </div>
            </div>
          )}
          {countInfo.postsTranslated > 0 &&
            !pendingPosts.length &&
            !progressBarVisibility && (
              <div className={`${prefix}-progress-footer`}>
                <a
                  className={`${prefix}-progress-button button button-primary`}
                  href={getTranslatedPostLink()}
                >
                  {sprintf(
                    __("Check Translated %s", "automl-ai-translation-for-wpml"),
                    automl_wpml_bulk_translate_object.post_label,
                  )}
                </a>
              </div>
            )}
        </>
      )}
    </div>
  );
};

export default StatusModal;
