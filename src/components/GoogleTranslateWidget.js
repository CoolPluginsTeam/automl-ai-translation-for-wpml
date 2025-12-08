import React, { useEffect, useRef } from 'react';
import { mapLanguageForGoogle } from '../utils/languageUtils';

const GoogleTranslateWidget = ({ targetLang }) => {
  const containerRef = useRef(null);
  const widgetInitializedRef = useRef(false);

  useEffect(() => {
    if (!containerRef.current || widgetInitializedRef.current) {
      return;
    }

    const googleLang = mapLanguageForGoogle(targetLang);
    let initAttempts = 0;
    const maxAttempts = 20;

    const tryInitGoogleTranslate = () => {
      initAttempts++;

      if (!containerRef.current) {
        if (initAttempts < maxAttempts) {
          setTimeout(tryInitGoogleTranslate, 200);
        }
        return;
      }

      if (typeof google === 'undefined' || !google.translate) {
        if (initAttempts < maxAttempts) {
          setTimeout(tryInitGoogleTranslate, 200);
        }
        return;
      }

      // Check if already initialized
      if (containerRef.current.querySelector('.goog-te-combo')) {
        widgetInitializedRef.current = true;
        return;
      }

      // Use global function if available
      if (typeof window.cpWpmlGTranslateWidget === 'function') {
        window.cpWpmlGTranslateWidget(googleLang);
        widgetInitializedRef.current = true;
        return;
      }

      // Initialize Google Translate widget
      try {
        if (googleLang === 'zh' || googleLang === 'zh-CN' || googleLang === 'zh-hans') {
          new google.translate.TranslateElement(
            {
              pageLanguage: 'en',
              includedLanguages: 'zh-CN,zh-TW',
              defaultLanguage: 'zh-CN',
              multilanguagePage: true,
            },
            containerRef.current.id || 'google_translate_element'
          );
        } else {
          new google.translate.TranslateElement(
            {
              pageLanguage: 'en',
              includedLanguages: googleLang,
              defaultLanguage: googleLang,
              multilanguagePage: true,
            },
            containerRef.current.id || 'google_translate_element'
          );
        }
        widgetInitializedRef.current = true;
      } catch (error) {
        console.error('Error initializing Google Translate widget:', error);
        if (initAttempts < maxAttempts) {
          setTimeout(tryInitGoogleTranslate, 200);
        }
      }
    };

    // Load Google Translate script if not already loaded
    if (typeof google === 'undefined' || !google.translate) {
      const script = document.createElement('script');
      script.src = 'https://translate.google.com/translate_a/element.js';
      script.async = true;
      script.onload = () => {
        setTimeout(tryInitGoogleTranslate, 300);
      };
      document.head.appendChild(script);
    } else {
      setTimeout(tryInitGoogleTranslate, 300);
    }
  }, [targetLang]);

  return (
    <div
      id="google_translate_element"
      ref={containerRef}
      style={{ minHeight: '40px' }}
    />
  );
};

export default GoogleTranslateWidget;

