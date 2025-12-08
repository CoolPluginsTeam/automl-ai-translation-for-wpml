import React, { useEffect, useState } from 'react';
import { getPendingLanguages } from '../api/translations';
import { isGoogleTranslateSupported } from '../utils/languageUtils';

const LanguageModal = ({ selectedIds, onClose, onSelect }) => {
  const [languages, setLanguages] = useState([]);
  const [selectedLang, setSelectedLang] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const loadLanguages = async () => {
      try {
        setLoading(true);
        const response = await getPendingLanguages(selectedIds);
        if (response.success && response.data?.languages) {
          setLanguages(response.data.languages);
        } else {
          setError('No languages available');
        }
      } catch (err) {
        setError('Error loading languages');
        console.error('Error loading languages:', err);
      } finally {
        setLoading(false);
      }
    };

    if (selectedIds.length > 0) {
      loadLanguages();
    }
  }, [selectedIds]);

  const handleNext = () => {
    if (!selectedLang) {
      alert('Please select a target language.');
      return;
    }

    if (!isGoogleTranslateSupported(selectedLang)) {
      const lang = languages.find((l) => l.code === selectedLang);
      const langName = lang ? lang.name : selectedLang;
      alert(`Sorry, Google Translate does not support "${langName}" language. Please select a different language.`);
      return;
    }

    onSelect(selectedLang);
  };

  const handleBackdropClick = (e) => {
    if (e.target.id === 'cp-wpml-auto-translate-language-modal') {
      onClose();
    }
  };

  return (
    <div
      id="cp-wpml-auto-translate-language-modal"
      className="cp-wpml-modal"
      onClick={handleBackdropClick}
    >
      <div className="cp-wpml-modal-content">
        <h2>Select Target Language</h2>
        <p>Choose one language to translate the selected post.</p>
        <div className="cp-wpml-modal-select-wrapper">
          <select
            id="cp-wpml-auto-translate-lang-select"
            value={selectedLang}
            onChange={(e) => setSelectedLang(e.target.value)}
            disabled={loading}
          >
            <option value="">
              {loading ? 'Loading languages...' : 'Select Language'}
            </option>
            {languages.map((lang) => (
              <option key={lang.code} value={lang.code}>
                {lang.name} ({lang.code})
              </option>
            ))}
          </select>
        </div>
        {error && <div className="cp-wpml-error">{error}</div>}
        <div className="cp-wpml-modal-actions">
          <button
            type="button"
            className="button"
            id="cp-wpml-auto-translate-modal-cancel"
            onClick={onClose}
          >
            Cancel
          </button>
          <button
            type="button"
            className="button button-primary"
            id="cp-wpml-auto-translate-modal-next"
            onClick={handleNext}
            disabled={loading || !selectedLang}
          >
            Next
          </button>
        </div>
      </div>
    </div>
  );
};

export default LanguageModal;

