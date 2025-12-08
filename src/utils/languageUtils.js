// Language mapping for Google Translate
export const GOOGLE_LANG_MAP = {
  kir: 'ky',
  oci: 'oc',
  bel: 'be',
  he: 'iw',
  snd: 'sd',
  jv: 'jw',
  nb: 'no',
  nn: 'no',
  'pt-br': 'pt',
  'zh-hans': 'zh-CN',
  'zh-hant': 'zh-TW',
  zh: 'zh-CN',
};

// Google Translate supported languages (2-letter codes)
export const GOOGLE_SUPPORTED_LANGUAGES = [
  'af', 'sq', 'am', 'ar', 'hy', 'az', 'eu', 'be', 'bn', 'bs', 'bg', 'ca', 'ceb', 'ny',
  'zh-CN', 'zh-TW', 'co', 'hr', 'cs', 'da', 'nl', 'en', 'eo', 'et', 'tl', 'fi', 'fr',
  'fy', 'gl', 'ka', 'de', 'el', 'gu', 'ht', 'ha', 'haw', 'iw', 'hi', 'hmn', 'hu', 'is',
  'ig', 'id', 'ga', 'it', 'ja', 'jw', 'kn', 'kk', 'km', 'rw', 'ko', 'ku', 'ky', 'lo',
  'la', 'lv', 'lt', 'lb', 'mk', 'mg', 'ms', 'ml', 'mt', 'mi', 'mr', 'mn', 'my', 'ne',
  'no', 'ps', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sm', 'gd', 'sr', 'st', 'sn', 'sd',
  'si', 'sk', 'sl', 'so', 'es', 'su', 'sw', 'sv', 'tg', 'ta', 'tt', 'te', 'th', 'tr',
  'uk', 'ur', 'uz', 'vi', 'cy', 'xh', 'yi', 'yo', 'zu',
];

/**
 * Check if Google Translate supports a language
 */
export function isGoogleTranslateSupported(targetLang) {
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
export function mapLanguageForGoogle(targetLang) {
  let googleLang = targetLang.toLowerCase();
  if (GOOGLE_LANG_MAP[googleLang]) {
    googleLang = GOOGLE_LANG_MAP[googleLang];
  } else if (googleLang.indexOf('-') !== -1) {
    const parts = googleLang.split('-');
    googleLang = parts[0];
  }
  return googleLang;
}

