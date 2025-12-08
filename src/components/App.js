import React, { useEffect, useState } from 'react';
import LanguageModal from './LanguageModal';
import TranslationPopup from './TranslationPopup';
import { ensureGoogleButtons, initBulkTranslateButton, initRowActionTranslateButton } from '../utils/buttonUtils';

const App = () => {
  const [languageModalOpen, setLanguageModalOpen] = useState(false);
  const [selectedPostIds, setSelectedPostIds] = useState([]);
  const [translationPopupOpen, setTranslationPopupOpen] = useState(false);
  const [currentPostId, setCurrentPostId] = useState(null);
  const [currentTargetLang, setCurrentTargetLang] = useState(null);

  useEffect(() => {
    // Initialize buttons on mount
    ensureGoogleButtons(document);
    
    const handleBulkTranslate = (postIds) => {
      setSelectedPostIds(postIds);
      setLanguageModalOpen(true);
    };
    
    const handleRowTranslate = (postId) => {
      setSelectedPostIds([postId]);
      setLanguageModalOpen(true);
    };
    
    initBulkTranslateButton(handleBulkTranslate);
    initRowActionTranslateButton(handleRowTranslate);

    // Listen for custom events
    const handleTranslateRequest = (e) => {
      const { postIds } = e.detail;
      setSelectedPostIds(postIds);
      setLanguageModalOpen(true);
    };

    window.addEventListener('wpml-translate-request', handleTranslateRequest);

    // Watch for DOM changes
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1) {
            ensureGoogleButtons(node);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    return () => {
      observer.disconnect();
      window.removeEventListener('wpml-translate-request', handleTranslateRequest);
    };
  }, []);

  const handleLanguageSelect = (targetLang) => {
    if (selectedPostIds.length > 0) {
      setCurrentPostId(selectedPostIds[0]);
      setCurrentTargetLang(targetLang);
      setLanguageModalOpen(false);
      setTranslationPopupOpen(true);
    }
  };

  const handleTranslationClose = () => {
    setTranslationPopupOpen(false);
    setCurrentPostId(null);
    setCurrentTargetLang(null);
  };

  const handleTranslationSave = () => {
    // Reload page after successful save
    // setTimeout(() => {
    //   window.location.reload();
    // }, 1000);
  };

  return (
    <>
      {languageModalOpen && (
        <LanguageModal
          selectedIds={selectedPostIds}
          onClose={() => setLanguageModalOpen(false)}
          onSelect={handleLanguageSelect}
        />
      )}
      {translationPopupOpen && currentPostId && currentTargetLang && (
        <TranslationPopup
          postId={currentPostId}
          targetLang={currentTargetLang}
          onClose={handleTranslationClose}
          onSave={handleTranslationSave}
        />
      )}
    </>
  );
};

export default App;

