import React, { useEffect, useState } from 'react';
import TranslationTable from './TranslationTable';
import GoogleTranslateWidget from './GoogleTranslateWidget';
import { getPostContents } from '../api/translations';

const TranslationPopup = ({ postId, targetLang, onClose, onSave }) => {
  const [postData, setPostData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [canSave, setCanSave] = useState(false);

  useEffect(() => {
    const loadPostContent = async () => {
      try {
        setLoading(true);
        const response = await getPostContents([postId]);
        if (response.success && response.data?.[postId]) {
          setPostData(response.data[postId]);
        } else {
          setError('Failed to load post content.');
        }
      } catch (err) {
        setError('AJAX error while loading content.');
        console.error('Error loading post content:', err);
      } finally {
        setLoading(false);
      }
    };

    if (postId) {
      loadPostContent();
    }
  }, [postId]);

  const handleSaveButtonStateChange = (canSaveState) => {
    setCanSave(canSaveState);
  };

  const handleBackdropClick = (e) => {
    if (e.target.id === 'cp-wpml-auto-translate-translation-popup') {
      onClose();
    }
  };

  if (loading) {
    return (
      <div
        id="cp-wpml-auto-translate-translation-popup"
        className="cp-wpml-popup"
        onClick={handleBackdropClick}
      >
        <div className="cp-wpml-popup-content">
          <div className="cp-wpml-loading">Loading content...</div>
        </div>
      </div>
    );
  }

  if (error || !postData) {
    return (
      <div
        id="cp-wpml-auto-translate-translation-popup"
        className="cp-wpml-popup"
        onClick={handleBackdropClick}
      >
        <div className="cp-wpml-popup-content">
          <div className="cp-wpml-error">{error || 'Failed to load post content.'}</div>
          <button type="button" className="button" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    );
  }

  return (
    <div
      id="cp-wpml-auto-translate-translation-popup"
      className="cp-wpml-popup"
      onClick={handleBackdropClick}
    >
      <div className="cp-wpml-popup-content">
        <div className="cp-wpml-popup-header">
          <h2>Start Automatic Translation Process</h2>
          <div>
            <button
              type="button"
              className="button button-primary cp-wpml-save-btn"
              id="cp-wpml-auto-translate-translation-save"
              disabled={!canSave}
            >
              Update Content
            </button>
            <button
              type="button"
              id="cp-wpml-auto-translate-translation-close"
              className="cp-wpml-close-btn"
              onClick={onClose}
            >
              ×
            </button>
          </div>
        </div>
        <div className="cp-wpml-popup-body">
          <div className="cp-wpml-language-selector">
            <h3>
              <span>文</span> Choose Language
            </h3>
          </div>
          <div className="cp-wpml-google-translate-wrapper">
            <h4>Google Translator</h4>
            <GoogleTranslateWidget targetLang={targetLang} />
          </div>
          <TranslationTable
            postData={postData}
            targetLang={targetLang}
            postId={postId}
            onSaveButtonStateChange={handleSaveButtonStateChange}
            onSave={onSave}
          />
        </div>
        <div className="cp-wpml-popup-footer">
          <button
            type="button"
            className="button button-primary cp-wpml-save-btn"
            id="cp-wpml-auto-translate-translation-save-footer"
            disabled={!canSave}
          >
            Update Content
          </button>
        </div>
      </div>
    </div>
  );
};

export default TranslationPopup;

