import React, { useEffect, useRef } from 'react';

const TranslationRow = ({ item, index, translation, onTranslationChange }) => {
  const translationRef = useRef(null);
  const targetDivRef = useRef(null);

  useEffect(() => {
    // Monitor Google Translate changes in the editable field itself
    const observer = new MutationObserver(() => {
      if (translationRef.current) {
        const currentContent = translationRef.current.innerHTML || translationRef.current.textContent || '';
        const sourceText = item.text.trim();
        const currentText = translationRef.current.textContent?.trim() || '';
        
        // If content has changed and is different from source, update the state
        if (currentText && currentText !== sourceText) {
          // Call onTranslationChange to update the parent state
          onTranslationChange(item.key, currentContent);
        }
      }
    });

    // Also monitor the hidden target div for Google Translate changes
    const targetObserver = new MutationObserver(() => {
      if (targetDivRef.current && translationRef.current) {
        const translatedText = targetDivRef.current.textContent?.trim() || '';
        const sourceText = item.text.trim();
        
        if (translatedText && translatedText !== sourceText) {
          const currentTranslation = translationRef.current.textContent?.trim() || '';
          if (!currentTranslation || currentTranslation === sourceText) {
            // Check if original had HTML
            const originalHtml = item.html || item.innerHTML;
            const hasOriginalHtml = originalHtml && /<[^>]+>/.test(originalHtml);
            
            if (hasOriginalHtml) {
              // Preserve HTML structure
              let translatedHtml = targetDivRef.current.innerHTML || '';
              // Clean up Google Translate wrapper elements
              const temp = document.createElement('div');
              temp.innerHTML = translatedHtml;
              temp.querySelectorAll('.goog-te-spinner-pos, .goog-te-banner-frame, .skiptranslate, .goog-te-banner').forEach((el) => el.remove());
              temp.querySelectorAll('font[dir="auto"], font[style*="vertical-align: inherit"]').forEach((font) => {
                font.replaceWith(...font.childNodes);
              });
              const cleanedHtml = temp.innerHTML;
              translationRef.current.innerHTML = cleanedHtml;
              // Update state with the translated content
              onTranslationChange(item.key, cleanedHtml);
            } else {
              translationRef.current.textContent = translatedText;
              // Update state with the translated text
              onTranslationChange(item.key, translatedText);
            }
          }
        }
      }
    });

    // Observe the editable translation field
    if (translationRef.current) {
      observer.observe(translationRef.current, {
        childList: true,
        subtree: true,
        characterData: true,
      });
    }

    // Observe the hidden target div
    if (targetDivRef.current) {
      targetObserver.observe(targetDivRef.current, {
        childList: true,
        subtree: true,
        characterData: true,
      });
    }

    // Also listen for input events on the editable field
    const handleInput = () => {
      if (translationRef.current) {
        const newContent = translationRef.current.innerHTML || translationRef.current.textContent || '';
        onTranslationChange(item.key, newContent);
      }
    };

    if (translationRef.current) {
      translationRef.current.addEventListener('input', handleInput);
      translationRef.current.addEventListener('change', handleInput);
    }

    return () => {
      observer.disconnect();
      targetObserver.disconnect();
      if (translationRef.current) {
        translationRef.current.removeEventListener('input', handleInput);
        translationRef.current.removeEventListener('change', handleInput);
      }
    };
  }, [item, onTranslationChange]);

  const handleTranslationInput = (e) => {
    const newTranslation = e.target.textContent || e.target.value || '';
    onTranslationChange(item.key, newTranslation);
  };

  return (
    <tr>
      <td>{index}</td>
      <td className="cp-wpml-source-text">
        <div
          className="cp-wpml-auto-translate-source"
          dangerouslySetInnerHTML={{ __html: item.html || item.text }}
        />
      </td>
      <td className="cp-wpml-translation-cell">
        <div
          ref={targetDivRef}
          className="cp-wpml-auto-translate-translation-target"
          style={{ display: 'none' }}
          dangerouslySetInnerHTML={{ __html: item.html || item.text }}
        />
        <div
          ref={translationRef}
          className="cp-wpml-auto-translate-translation-field target"
          translate="yes"
          contentEditable
          suppressContentEditableWarning
          onInput={handleTranslationInput}
          onBlur={handleTranslationInput}
          dangerouslySetInnerHTML={{ __html: translation || item.text }}
        />
      </td>
    </tr>
  );
};

export default TranslationRow;

