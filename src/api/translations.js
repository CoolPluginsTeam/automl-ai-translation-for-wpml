/**
 * Get WordPress AJAX URL and nonce from localized script
 */
function getAjaxConfig() {
  if (typeof window.CP_WPML_AUTO_TRANSLATE === 'undefined') {
    throw new Error('CP_WPML_AUTO_TRANSLATE is not defined. Make sure the script is localized.');
  }
  return window.CP_WPML_AUTO_TRANSLATE;
}

/**
 * Make AJAX request
 */
async function ajaxRequest(action, data = {}) {
  const config = getAjaxConfig();
  
  const formData = new FormData();
  formData.append('action', action);
  formData.append('nonce', config.nonce);
  
  Object.keys(data).forEach((key) => {
    if (Array.isArray(data[key])) {
      data[key].forEach((value) => {
        formData.append(`${key}[]`, value);
      });
    } else if (typeof data[key] === 'object') {
      formData.append(key, JSON.stringify(data[key]));
    } else {
      formData.append(key, data[key]);
    }
  });

  const response = await fetch(config.ajax, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  return response.json();
}

/**
 * Get post contents for translation
 */
export async function getPostContents(ids) {
  return ajaxRequest('cp_wpml_google_auto_translate_get_post_contents', {
    ids: ids,
  });
}

/**
 * Get pending languages (languages without existing translations)
 */
export async function getPendingLanguages(ids) {
  return ajaxRequest('cp_wpml_google_auto_translate_get_pending_languages', {
    ids: ids,
  });
}

/**
 * Save translation
 */
export async function saveTranslation(
  postId,
  targetLang,
  translatedTitle,
  translatedContent,
  translatedFields,
  translationsMap,
  editorType
) {
  const config = getAjaxConfig();
  
  const formData = new FormData();
  formData.append('action', 'cp_wpml_google_auto_translate_save_translation');
  formData.append('nonce', config.nonce);
  formData.append('post_id', postId);
  formData.append('target_lang', targetLang);
  formData.append('translated_title', translatedTitle);
  formData.append('translated_content', translatedContent);
  formData.append('editor_type', editorType);
  
  // Send translated_fields as array (PHP expects array)
  if (Array.isArray(translatedFields) && translatedFields.length > 0) {
    translatedFields.forEach((field, index) => {
      if (field.field_type) {
        formData.append(`translated_fields[${index}][field_type]`, field.field_type);
      }
      if (field.field_data !== undefined) {
        formData.append(`translated_fields[${index}][field_data]`, field.field_data);
      }
      if (field.field_format) {
        formData.append(`translated_fields[${index}][field_format]`, field.field_format);
      }
    });
  }
  
  // Send translations_map as JSON string (PHP will decode it)
  if (translationsMap && Object.keys(translationsMap).length > 0) {
    formData.append('translations_map', JSON.stringify(translationsMap));
  }

  const response = await fetch(config.ajax, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  return response.json();
}

