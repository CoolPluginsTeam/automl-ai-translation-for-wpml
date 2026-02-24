import {
  filterContent,
  updateFilterContent,
} from "./components/filter-content";
import { AITranslationRequest } from "./helper";
import ChromeAiTranslator from "./components/translate-provider/local-ai/local-ai-translate";
import {
  updatePendingPosts,
  unsetPendingPost,
  updateCompletedPosts,
  updateTranslatePostInfo,
  updateCountInfo,
  updateSourceContent,
  updateParentPostsInfo,
  updateTargetContent,
  updateTargetLanguages,
  updateBlockParseRules,
  updateProgressStatus,
  updateErrorPostsInfo,
} from "./redux-store/features/actions";
import { store } from "./redux-store/store";
import { __, sprintf } from "@wordpress/i18n";
import Provider from "./components/translate-provider";
import {
  updateTranslateData,
  translateFieldNameSort,
  getContentCount,
} from "./helper";
import LoopCallback from "./components/loop-callback";
import { selectTranslatePostInfo } from "./redux-store/features/selectors";

const initBulkTranslate = async (
  postKeys = [],
  nonce,
  storeDispatch,
  prefix,
  updateDestoryHandler,
) => {
  const pendingPosts = store.getState().pendingPosts;

  if (pendingPosts.length < 1) {
    return;
  }

  let modalClosed = false;

  updateDestoryHandler(() => {
    modalClosed = true;
  });

  const translatePost = async (index) => {
    const postId = postKeys[index];

    if (!postId || modalClosed) {
      return;
    }

    const postContent = store.getState().parentPostsInfo[postId];

    if (postContent) {
      const {
        originalContent: { title, content, post_name, excerpt },
        languages,
        editorType,
        sourceLanguage,
      } = postContent;

      if (!languages || languages.length === 0) {
        console.log(
          `All target languages for post ${postId} already exist. Skipping translation.`,
        );
        return;
      }

      for (const lang of languages) {
        storeDispatch(unsetPendingPost(postId + "_" + lang));
        storeDispatch(updateProgressStatus(100 / pendingPosts.length));
        storeDispatch(
          updateTranslatePostInfo({
            [postId + "_" + lang]: {
              status: "error",
              messageClass: "error",
              errorMessage: __(
                "This post editor type is not supported for translation",
                "automl-ai-translation-for-wpml",
              ),
            },
          }),
        );
      }

      const source = {
        title: title,
        content: JSON.parse(JSON.stringify(content)),
        post_name: post_name,
        excerpt: excerpt,
      };

      await translateContent({
        sourceLang: sourceLanguage,
        targetLangs: languages,
        totalPosts: pendingPosts.length,
        storeDispatch,
        prefix,
        postId,
        source,
        editorType,
        createTranslatePostNonce: nonce,
        updateDestoryHandler,
      });
    }

    index++;

    if (index > postKeys.length - 1 || modalClosed) {
      return;
    }

    await translatePost(index);
  };

  await translatePost(0);
};

const translateContent = async ({
  sourceLang,
  targetLangs,
  totalPosts,
  storeDispatch,
  postId,
  prefix,
  source,
  editorType,
  createTranslatePostNonce,
  updateDestoryHandler,
}) => {
  const activeProvider = store.getState().serviceProvider;
  const providerDetails = Provider({ Service: activeProvider });

  if (providerDetails && providerDetails.Provider) {
    const updateContentCallback = async (lang) => {
      await updateContent({
        source,
        postId,
        sourceLang,
        lang,
        editorType,
        createTranslatePostNonce,
        storeDispatch,
      });
    };

    const data = {
      sourceLang,
      targetLangs,
      totalPosts,
      storeDispatch,
      postId,
      createTranslatePostNonce,
      updateContent: updateContentCallback,
      prefix,
      updateDestoryHandler,
    };

    const provider = new providerDetails.Provider(data);

    await provider.initTranslation();
  }
};

export const updateContent = async ({
  source,
  postId,
  sourceLang,
  lang,
  editorType,
  createTranslatePostNonce,
  storeDispatch,
}) => {
  const service = store.getState().serviceProvider;

  const deepCloneSource = JSON.parse(JSON.stringify(source));

  const updateContent = await updateFilterContent({
    source: deepCloneSource,
    postId,
    lang,
    editorType,
    service,
  });

  const bulkTranslateRouteUrl =
    automl_wpml_bulk_translate_object.bulkTranslateRouteUrl;
  const nonce = automl_wpml_bulk_translate_object.nonce;

  storeDispatch(
    updateTranslatePostInfo({
      [postId + "_" + lang]: {
        status: "in-progress",
        messageClass: "in-progress",
      },
    }),
  );

  let endPoint = "create-translate-post";

  let body = {
    target_language: lang,
    editor_type: editorType,
    privateKey: createTranslatePostNonce,
    source_language: sourceLang,
  };

  if (editorType === "taxonomy") {
    endPoint = "create-translate-taxonomy";
    body.term_id = postId;
    body.taxonomy_name = updateContent.title || "";
    body.taxonomy_description = updateContent.content || "";
    body.taxonomy = automl_wpml_bulk_translate_object.taxonomy_page;

    if (updateContent.post_name && updateContent.post_name.trim() !== "") {
      body.taxonomy_slug = updateContent.post_name;
    }
  } else {
    body.post_id = postId;
    body.post_title = updateContent.title || "";
    body.post_name = updateContent.post_name || "";
    body.post_content = updateContent.content
      ? JSON.stringify(updateContent.content)
      : "";
    body.post_excerpt = updateContent.excerpt || "";
  }

  await fetch(bulkTranslateRouteUrl + `/${postId}/${endPoint}`, {
    method: "POST",
    body: new URLSearchParams(body),
    headers: {
      "X-WP-Nonce": nonce,
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      Accept: "application/json",
    },
  })
    .then(async (response) => {
      const data = await response.json();

      let updateData = {};

      if (data.success && data.data.post_id) {
        const extraData = {};

        if (editorType === "taxonomy") {
          extraData.taxonomy = automl_wpml_bulk_translate_object.taxonomy_page;
        }

        updateTranslateData({
          provider: service,
          sourceLang,
          targetLang: lang,
          currentPostId: data.data.post_id,
          parentPostId: postId,
          editorType,
          updateTranslateDataNonce: data?.data?.update_translate_data_nonce,
          extraData,
        });

        data.data.post_title =
          "" === data.data.post_title
            ? __("N/A", "automl-ai-translation-for-wpml")
            : data.data.post_title;
        updateData = {
          targetPostId: data.data.post_id,
          targetPostTitle: data.data.post_title,
          targetLanguage: lang,
          postLink: data.data.post_link,
          postEditLink: data.data.post_edit_link,
          status: "completed",
          messageClass: "success",
        };
        storeDispatch(
          updateCountInfo({
            postsTranslated: store.getState().countInfo.postsTranslated + 1,
          }),
        );
      } else {
        if (data.data && data.data.error) {
          let errorHtml = "Error Code:" + data.data.status;

          if (typeof data.data.error === "string") {
            errorHtml +=
              "<br>Error Message:" +
              data.data.error +
              "(" +
              data.data.error +
              ")";
          }

          if (typeof data.data.error === "object") {
            errorHtml += "<br>Error Message:" + JSON.stringify(data.data.error);
          }

          updateData = {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml:
              '<div class="automl-wpml-error-html">' + errorHtml + "</div>",
          };
        } else if (data.code && data.message) {
          updateData = {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml:
              '<div class="automl-wpml-error-html">' + data.message + "</div>",
          };
        } else if (!data.success || data.data) {
          updateData = {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml:
              '<div class="automl-wpml-error-html">' + data.data + "</div>",
          };
        } else if (!data.data.post_id) {
          updateData = {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml:
              '<div class="automl-wpml-error-html">' + data.data + "</div>",
          };
        } else if (typeof data === "string") {
          updateData = {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml: '<div class="automl-wpml-error-html">' + data + "</div>",
          };
        }
      }

      storeDispatch(unsetPendingPost(postId + "_" + lang));
      storeDispatch(updateCompletedPosts([postId + "_" + lang]));
      storeDispatch(
        updateTranslatePostInfo({ [postId + "_" + lang]: updateData }),
      );
    })
    .catch((error) => {
      console.log(error);
      storeDispatch(unsetPendingPost(postId + "_" + lang));
      storeDispatch(updateCompletedPosts([postId + "_" + lang]));
      let errorHtml = error;

      if (error.message) {
        errorHtml = error.message;
      }

      if (error.data && error.data.status) {
        errorHtml = "Error Code:" + error.data.status;

        if (typeof error.data.error === "string") {
          errorHtml += "<br>Error Message:" + error.data.error;
        }

        if (typeof error.data.error === "object") {
          errorHtml += "<br>Error Message:" + JSON.stringify(error.data.error);
        }
      }

      storeDispatch(
        updateTranslatePostInfo({
          [postId + "_" + lang]: {
            status: "error",
            messageClass: "error",
            errorMessage: __(
              "Post not created. Please try again.",
              "automl-ai-translation-for-wpml",
            ),
            errorHtml:
              '<div class="automl-wpml-error-html">' + errorHtml + "</div>",
          },
        }),
      );
    });
};

const bulkTranslateEntries = async ({ ids, langs, storeDispatch }) => {
  const bulkTranslateRouteUrl =
    automl_wpml_bulk_translate_object.bulkTranslateRouteUrl;
  const bulkTranslatePrivateKey =
    automl_wpml_bulk_translate_object.bulkTranslatePrivateKey;
  const nonce = automl_wpml_bulk_translate_object.nonce;
  let storeParseBlockRules = false;

  const body = {
    ids: JSON.stringify(ids),
    lang: JSON.stringify(langs),
    privateKey: bulkTranslatePrivateKey,
  };

  let postUrl = "automl_wpml_/bulk-translate-entries";

  if (
    automl_wpml_bulk_translate_object.taxonomy_page &&
    "" !== automl_wpml_bulk_translate_object.taxonomy_page
  ) {
    body.taxonomy = automl_wpml_bulk_translate_object.taxonomy_page;
    postUrl = "automl_wpml_/bulk-translate-taxonomy-entries";
  }

  const untranslatedPosts = await fetch(bulkTranslateRouteUrl + "/" + postUrl, {
    method: "POST",
    body: new URLSearchParams(body),
    headers: {
      "X-WP-Nonce": nonce,
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      Accept: "application/json",
    },
  });

  const untranslatedPostsData = await untranslatedPosts.json();

  if (
    !untranslatedPostsData.success &&
    !untranslatedPostsData.code &&
    untranslatedPostsData.data &&
    untranslatedPostsData.data.message
  ) {
    return { success: false, message: untranslatedPostsData.data.message };
  } else if (
    !untranslatedPostsData.success &&
    !untranslatedPostsData.message &&
    untranslatedPostsData.data.error
  ) {
    return {
      success: false,
      message: JSON.stringify(untranslatedPostsData.data.error),
    };
  } else if (
    !untranslatedPostsData.success &&
    untranslatedPostsData.message &&
    untranslatedPostsData.data.trace &&
    untranslatedPostsData.data.message
  ) {
    // trace key aur uska data hatao, phir pura object stringify karo
    if (untranslatedPostsData.data && untranslatedPostsData.data.trace) {
      delete untranslatedPostsData.data.trace;
    }
    return {
      success: false,
      message: JSON.stringify(untranslatedPostsData.data),
    };
  } else if (!untranslatedPostsData.success && untranslatedPostsData.message) {
    return { success: false, message: untranslatedPostsData.message };
  }

  if (!untranslatedPostsData) {
    return {
      success: false,
      message: __(
        "No posts to translate data undefined",
        "automl-ai-translation-for-wpml",
      ),
    };
  }

  if (!untranslatedPostsData.success) {
    return { success: false, message: untranslatedPostsData.message };
  }

  if (!untranslatedPostsData.data) {
    return {
      success: false,
      message: __(
        "No posts to translate untranslated data not found",
        "automl-ai-translation-for-wpml",
      ),
    };
  }

  if (!untranslatedPostsData.data.posts) {
    return {
      success: false,
      message: __(
        "No posts to translate untranslated posts data not found",
        "automl-ai-translation-for-wpml",
      ),
    };
  }

  if (!untranslatedPostsData.data.CreateTranslatePostNonce) {
    return {
      success: false,
      message: __(
        "No create translate post nonce",
        "automl-ai-translation-for-wpml",
      ),
    };
  }

  const posts = untranslatedPostsData.data.posts;

  const postKeys = Object.keys(posts);

  if (postKeys.length > 0) {
    let allTargetLanguages = {};

    const postIdExist = new Array();
    const existsPostInPendingPosts = Object.keys(
      store.getState().translatePostInfo,
    );

    postKeys.forEach((postId) => {
      postId = parseInt(postId);
      const languages = posts[postId].languages;
      const parentPostTitle = posts[postId].title;

      allTargetLanguages[posts[postId].sourceLanguage] = {
        languages: [
          ...(allTargetLanguages[posts[postId].sourceLanguage]?.languages ||
            []),
          ...(posts[postId].languages || []),
        ],
      };

      if (languages && languages.length > 0) {
        languages.forEach((language) => {
          if (existsPostInPendingPosts.includes(postId + "_" + language)) {
            return;
          }

          let firstPostLanguage = false;

          if (
            !postIdExist.includes(postId) &&
            !existsPostInPendingPosts.includes(postId + "_" + language)
          ) {
            postIdExist.push(postId);
            firstPostLanguage = true;
          }

          const flagUrl =
            automl_wpml_bulk_translate_object.languageObject[language].flag;
          const languageName =
            automl_wpml_bulk_translate_object.languageObject[language].name;
          storeDispatch(updatePendingPosts([postId + "_" + language]));
          storeDispatch(
            updateTranslatePostInfo({
              [postId + "_" + language]: {
                parentPostId: postId,
                targetPostId: null,
                targetLanguage: language,
                postLink: null,
                status: "pending",
                parentPostTitle,
                firstPostLanguage,
                flagUrl,
                languageName,
                messageClass: "warning",
              },
            }),
          );
        });
      }
    });

    const storeSourceContent = async (index, translatePostsCount) => {
      const postId = postKeys[index];
      const activeProvider = store.getState().serviceProvider;
      const {
        title,
        post_name,
        languages,
        editor_type,
        sourceLanguage,
        excerpt = null,
      } = posts[postId];

      let content =
        posts[postId] && posts[postId].content ? posts[postId].content : {};

      if (!sourceLanguage) {
        const postTitle = title || "N/A";
        let titleLink = false;
        let postLink = false;
        if (posts[postId]?.post_link) {
          postLink = posts[postId].post_link;
          titleLink = postLink;
        }

        const errorInfo = {
          title: title,
          editorType: editor_type,
          sourceLanguage,
          errorMessage: sprintf(
            __(
              "Set source language for this %s %s before translating.",
              "automl-ai-translation-for-wpml",
            ),
            titleLink
              ? '<a href="' +
                  titleLink +
                  '" target="_blank" rel="noopener noreferrer">' +
                  postTitle +
                  "</a>"
              : postTitle,
            window?.automl_wpml_bulk_translate_object?.taxonomy_page ||
              window?.automl_wpml_bulk_translate_object?.post_label,
          ),
        };

        if (posts[postId]?.post_link) {
          errorInfo.postLink = postLink;
        }

        storeDispatch(
          updateErrorPostsInfo({
            postId,
            data: errorInfo,
          }),
        );

        storeDispatch(
          updateCountInfo({
            errorPosts: store.getState().countInfo.errorPosts + 1,
          }),
        );

        index++;
        if (index > postKeys.length - 1) {
          return;
        }

        await storeSourceContent(index, translatePostsCount);

        return;
      }

      if (languages && languages.length > 0) {
        let targetLang = languages;

        storeDispatch(updateTargetLanguages({ lang: targetLang }));

        const data = {
          content,
          editorType: editor_type,
          service: activeProvider,
          postId,
          storeDispatch,
          sourceLanguage,
        };

        if (editor_type === "block") {
          data.blockParseRules = JSON.parse(
            untranslatedPostsData?.data?.blockParseRules,
          );

          if (!storeParseBlockRules) {
            storeDispatch(
              updateBlockParseRules(
                JSON.parse(untranslatedPostsData?.data?.blockParseRules),
              ),
            );
            storeParseBlockRules = true;
          }
        }

        if (content && content !== "") {
          await filterContent(data);
        }

        if (title && title.trim() !== "") {
          let filteredTitle = title;
          storeDispatch(
            updateSourceContent({ postId, uniqueKey: "title", value: title }),
          );
          storeDispatch(
            updateTargetContent({
              postId,
              uniqueKey: "title",
              value: filteredTitle,
            }),
          );
        }

        if (post_name && post_name.trim() !== "") {
          let filteredPostName = post_name;
          storeDispatch(
            updateSourceContent({
              postId,
              uniqueKey: "post_name",
              value: post_name,
            }),
          );
          storeDispatch(
            updateTargetContent({
              postId,
              uniqueKey: "post_name",
              value: filteredPostName,
            }),
          );
        }

        if (excerpt && excerpt.trim() !== "") {
          let filteredExcerpt = excerpt;
          storeDispatch(
            updateSourceContent({
              postId,
              uniqueKey: "excerpt",
              value: excerpt,
            }),
          );
          storeDispatch(
            updateTargetContent({
              postId,
              uniqueKey: "excerpt",
              value: filteredExcerpt,
            }),
          );
        }

        const previousParentPostsInfo =
          store.getState().parentPostsInfo[postId];

        let charactersCount = previousParentPostsInfo?.charactersCount || 0;
        let wordsCount = previousParentPostsInfo?.wordsCount || 0;
        let stringsCount = previousParentPostsInfo?.stringsCount || 0;

        const originalContent = {};

        if (title && title.trim() !== "") {
          const titleCounts = getContentCount(title);
          charactersCount += titleCounts.charactersCount;
          wordsCount += titleCounts.wordsCount;
          stringsCount += titleCounts.stringsCount;
          originalContent.title = title;
        }

        if (content) {
          originalContent.content = content;
        } else {
          originalContent.content = {};
        }

        if (post_name && post_name.trim() !== "") {
          originalContent.post_name = post_name;
        }

        if (excerpt && excerpt.trim() !== "") {
          originalContent.excerpt = excerpt;
        }

        storeDispatch(
          updateParentPostsInfo({
            postId,
            data: {
              editorType: editor_type,
              originalContent,
              languages: targetLang,
              sourceLanguage,
              charactersCount,
              wordsCount,
              stringsCount,
            },
          }),
        );
        storeDispatch(
          updateCountInfo({
            totalPosts:
              store.getState().countInfo.totalPosts + targetLang.length,
          }),
        );
      } else {
        console.log(
          `All target languages for post ${postId} already exist. Skipping translation.`,
        );
      }

      index++;
      if (index > postKeys.length - 1) {
        return;
      }

      await storeSourceContent(index, translatePostsCount);
    };

    const translatePostsCount = store.getState().pendingPosts.length;

    await storeSourceContent(0, translatePostsCount);

    return {
      postKeys,
      nonce: untranslatedPostsData.data.CreateTranslatePostNonce,
    };
  }
};
/**
 * Bulk translate strings for String Translation page.
 * Fetches first page only; initBulkTranslateStrings will fetch further pages in a pipeline (no 10k in memory).
 */
const bulkTranslateStrings = async ({
  langs,
  storeDispatch,
  stringFilters,
  selectedStrings = {},
  stringLanguageStatus = {},
}) => {
  const nonce = automl_wpml_bulk_translate_object.nonce;
  const stringsByLanguage = {};
  const totalPerLanguage = {};
  const stringKeys = []; // only used for count; we don't put 10k keys in Redux



  for (const lang of langs) {
    let strings = [];
    let total = 0;
  
    const hasLocalSelected =
      selectedStrings &&
      Object.keys(selectedStrings).length > 0 &&
      Array.isArray(stringFilters?.selected_string_ids) &&
      stringFilters.selected_string_ids.length > 0;
  
             // Build rows from selected strings, then filter out already-translated for this language
             const rows = (stringFilters.selected_string_ids || [])
             .map((id) => selectedStrings[String(id)])
             .filter(Boolean);
   
           const rowsToTranslate = rows.filter((row) => {
             const stringId = String(row.string_id);
             return stringLanguageStatus[stringId]?.[lang] !== "edit";
           });
   
           strings = rowsToTranslate.map((row) => ({
             text: row.value || "",
             html: row.value || "",
             field_key: String(row.string_id),
             field_name: row.name || String(row.string_id),
             format: "html",
           }));
           total = strings.length;
  
    if (total === 0 || !strings.length) continue;
  
    totalPerLanguage[lang] = total;
    stringsByLanguage[lang] = strings;
  
    const flagUrl =
      automl_wpml_bulk_translate_object.languageObject[lang]?.flag || "";
    const languageName =
      automl_wpml_bulk_translate_object.languageObject[lang]?.name || lang;
  
    // One Redux entry per language
    const key = `strings_${lang}`;
    stringKeys.push(key);
    storeDispatch(updatePendingPosts([key]));
    storeDispatch(
      updateTranslatePostInfo({
        [key]: {
          parentPostId: `strings_${lang}`,
          targetLanguage: lang,
          status: "in-progress",
          messageClass: "in-progress",
          parentPostTitle: languageName,
          firstPostLanguage: true,
          flagUrl,
          languageName,
          editorType: "strings",
          total,
          completed: 0,
        },
      }),
    );
  }

  if (stringKeys.length === 0) {
    return {
      success: false,
      message: __(
        "No strings found to translate.",
        "automl-ai-translation-for-wpml",
      ),
    };
  }

  const totalStrings = Object.values(totalPerLanguage).reduce(
    (a, b) => a + b,
    0,
  );
  storeDispatch(updateCountInfo({ totalPosts: totalStrings }));

  return {
    success: true,
    stringKeys,
    stringsByLanguage,
    totalPerLanguage,
    nonce,
  };
};
/**
 * Translate an array of plain strings using Chrome built-in AI (client-side).
 * Returns Promise<string[]> with same order as input, or rejects on error.
 */
const translateStringsWithChromeAI = (strings, sourceLang, targetLang) => {
  const languageObject =
    automl_wpml_bulk_translate_object?.languageObject || {};
  const textContentObject = strings.reduce((acc, text, i) => {
    acc[i] = text || "";
    return acc;
  }, {});

  return new Promise((resolve, reject) => {
    const translations = [];
    ChromeAiTranslator.Object({
      sourceLanguage: sourceLang,
      targetLanguage: targetLang,
      sourceLanguageLabel: languageObject[sourceLang]?.name || sourceLang,
      targetLanguageLabel: languageObject[targetLang]?.name || targetLang,
      onAfterTranslate: (key, translated) => {
        const index = parseInt(key, 10);
        if (!Number.isNaN(index)) {
          translations[index] = translated || "";
        }
      },
      onComplete: () => resolve(translations),
      onLanguageError: (err) =>
        reject(
          err?.message
            ? new Error(err.message)
            : new Error("Chrome AI translation failed"),
        ),
    })
      .then((translatorObj) => {
        if (!translatorObj?.init || !translatorObj?.startTranslation) {
          reject(
            new Error(
              __(
                "Chrome AI is not available. Use Chrome and enable the Translation API.",
                "automl-ai-translation-for-wpml",
              ),
            ),
          );
          return;
        }
        translatorObj.init(textContentObject);
        translatorObj.startTranslation();
      })
      .catch(reject);
  });
};

/**
 * Initialize bulk translation for strings.
 * Pipeline: fetch one page (500) → translate → save → fetch next page. Never holds more than 500 strings in memory.
 */
const initBulkTranslateStrings = async (
  stringKeys = [],
  stringsByLanguage = {},
  nonce,
  storeDispatch,
  prefix,
  updateDestoryHandler,
  totalPerLanguage = {},
) => {
  const pendingPosts = store.getState().pendingPosts;
  if (pendingPosts.length < 1) return;

  let modalClosed = false;
  updateDestoryHandler(() => {
    modalClosed = true;
  });

  const sourceLang =
    automl_wpml_bulk_translate_object.default_language_slug || "en";
  const BATCH_SIZE = 500;

  const translateStringsForLanguage = async (lang, initialStrings) => {
    if (!initialStrings?.length || modalClosed) return;

    const activeProvider = store.getState().serviceProvider;
    let serviceSlug = activeProvider;
    if (serviceSlug?.endsWith("_ai"))
      serviceSlug = serviceSlug.replace("_ai", "");

    const controller = new AbortController();
    const totalForLang = totalPerLanguage[lang] || initialStrings.length;
    const langKey = `strings_${lang}`;

    let offset = 0;
    let strings = initialStrings;
    let batchStringsTranslated = 0;
    let batchCharsTranslated = 0;

    while (strings.length > 0 && !modalClosed) {
      const batch = strings.slice(0, BATCH_SIZE);
      const batchStartTime = Date.now();

      try {
        const stringsToTranslate = batch.map((str) => ({
          text: str.text || str.html || "",
          field_key: str.field_key,
        }));

        let translationResponse;
        if (activeProvider === "localAiTranslator") {
          const translations = await translateStringsWithChromeAI(
            stringsToTranslate.map((s) => s.text),
            sourceLang,
            lang,
          );
          translationResponse = { success: true, data: { translations } };
        } else {
          translationResponse = await AITranslationRequest({
            controller,
            Strings: stringsToTranslate.map((s) => s.text),
            slug: serviceSlug,
            source_language: sourceLang,
            target_language: lang,
          });
        }

        if (translationResponse?.success && translationResponse.data) {
          let translations = [];

          const data = translationResponse.data;

          // 1) AI SDK route: { translate_data: { "0": "…", "1": "…" } }
          if (data.translate_data && typeof data.translate_data === "object") {
            translations = Object.keys(data.translate_data)
              .sort((a, b) => Number(a) - Number(b))
              .map((key) => data.translate_data[key] || "");
          }
          // 2) Old shape: raw array
          else if (Array.isArray(data)) {
            translations = data;
          }
          // 3) Old shape: { translations: [...] }
          else if (Array.isArray(data.translations)) {
            translations = data.translations;
          }

          const batchToSave = [];
          batch.forEach((str, index) => {
            const sourceText = str.text || str.html || "";
            const translatedText = translations[index] || sourceText;
            batchToSave.push({
              field_key: str.field_key,
              translated: translatedText,
            });
            batchStringsTranslated += 1;
            batchCharsTranslated += sourceText.length;
          });
          let timeTakenSec = 0;
          if (batchToSave.length > 0) {
            timeTakenSec = Math.round((Date.now() - batchStartTime) / 1000);
            await saveStringTranslations(lang, batchToSave, nonce);
            if (automl_wpml_bulk_translate_object?.update_translate_data_nonce) {
              const batchWords = batch.reduce(
                (sum, s) =>
                  sum +
                  (typeof (s.text || s.html || "") === "string"
                    ? (s.text || s.html || "")
                        .trim()
                        .split(/\s+/)
                        .filter(Boolean).length
                    : 0),
                0,
              );
              const parentKey = `strings_${lang}`;
              const translateInfoKey = `${parentKey}_${lang}`;
              const saveId = `strings_${lang}_${Date.now()}`;

              storeDispatch(
                updateParentPostsInfo({
                  postId: parentKey,
                  data: {
                    wordsCount: batchWords,
                    charactersCount: batchCharsTranslated,
                    stringsCount: batchToSave.length,
                  },
                }),
              );
              storeDispatch(
                updateTranslatePostInfo({
                  [translateInfoKey]: {
                    stringsTranslated: batchToSave.length,
                    wordsTranslated: batchWords,
                    charactersTranslated: batchCharsTranslated,
                    duration: timeTakenSec * 1000,
                  },
                }),
              );

              updateTranslateData({
                provider: serviceSlug || activeProvider,
                sourceLang,
                targetLang: lang,
                parentPostId: parentKey,
                currentPostId: saveId,
                editorType: "strings",
                updateTranslateDataNonce:
                  automl_wpml_bulk_translate_object.update_translate_data_nonce,
                extraData: {},
              });
            }
          }

          offset += batch.length;
          const state = store.getState();
          const prev = state.translatePostInfo[langKey] || {};
          const completed = (prev.completed || 0) + batch.length;

          storeDispatch(
            updateTranslatePostInfo({
              [langKey]: {
                ...prev,
                status: offset >= totalForLang ? "completed" : "in-progress",
                messageClass:
                  offset >= totalForLang ? "success" : "in-progress",
                completed,
                total: totalForLang,
              },
            }),
          );

          const count = state.countInfo || {};
          storeDispatch(
            updateCountInfo({
              stringsTranslated:
                (count.stringsTranslated || 0) + batchStringsTranslated,
              charactersTranslated:
                (count.charactersTranslated || 0) + batchCharsTranslated,
              timeTaken: (count.timeTaken || 0) + timeTakenSec,
              sourceLanguage: sourceLang,
              serviceProvider: serviceSlug || activeProvider,
            }),
          );
          batchStringsTranslated = 0;
          batchCharsTranslated = 0;

          const totalStrings = Object.values(totalPerLanguage).reduce(
            (a, b) => a + b,
            0,
          );
          storeDispatch(
            updateProgressStatus((100 * batch.length) / totalStrings),
          );

          if (offset >= totalForLang) {
            storeDispatch(unsetPendingPost(langKey));
            storeDispatch(updateCompletedPosts([langKey]));
            return;
          }
        } else {
          const errorMsg =
            translationResponse?.data?.message ||
            __("Translation failed", "automl-ai-translation-for-wpml");
          storeDispatch(
            updateTranslatePostInfo({
              [langKey]: {
                status: "error",
                messageClass: "error",
                errorMessage: errorMsg,
              },
            }),
          );
          storeDispatch(unsetPendingPost(langKey));
          storeDispatch(updateCompletedPosts([langKey]));
          return;
        }
      } catch (err) {
        const errorMsg =
          err?.message ||
          __("Translation failed", "automl-ai-translation-for-wpml");
        storeDispatch(
          updateTranslatePostInfo({
            [langKey]: {
              status: "error",
              messageClass: "error",
              errorMessage: errorMsg,
            },
          }),
        );
        storeDispatch(unsetPendingPost(langKey));
        storeDispatch(updateCompletedPosts([langKey]));
        return;
      }
    }

    storeDispatch(unsetPendingPost(langKey));
    storeDispatch(updateCompletedPosts([langKey]));
  };

  for (const lang of Object.keys(stringsByLanguage)) {
    if (modalClosed) break;
    await translateStringsForLanguage(lang, stringsByLanguage[lang]);
  }
};

/**
 * Save translated strings via AJAX.
 * @param {string} targetLang - Target language code.
 * @param {Array} translatedStrings - Array of { field_key, translated }.
 * @param {string} nonce - Security nonce.
 * @param {Object} [stats] - Optional dashboard stats: source_lang, service_provider, string_count, character_count, time_taken.
 */
const saveStringTranslations = async (
  targetLang,
  translatedStrings,
  nonce,
  stats = null,
) => {
  const ajaxUrl = automl_wpml_bulk_translate_object.ajax_url;

  const body = {
    action: "automl_wpml_google_auto_translate_save_string_translations",
    nonce: nonce,
    target_lang: targetLang,
    translated_strings: translatedStrings,
  };
  if (stats && typeof stats === "object") {
    body.dashboard_stats = stats;
  }

  try {
    const response = await fetch(
      ajaxUrl +
        "?action=automl_wpml_google_auto_translate_save_string_translations",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          Accept: "application/json",
        },
        body: JSON.stringify(body),
      },
    );

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Error saving string translations:", error);
    throw error;
  }
};

export {
  bulkTranslateEntries,
  initBulkTranslate,
  bulkTranslateStrings,
  initBulkTranslateStrings,
};
