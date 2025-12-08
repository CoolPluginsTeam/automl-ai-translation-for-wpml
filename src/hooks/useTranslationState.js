import { useState, useEffect } from 'react';

/**
 * Custom hook for managing translation state
 */
export function useTranslationState() {
  const [translations, setTranslations] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const updateTranslation = (key, value) => {
    setTranslations((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const resetTranslations = () => {
    setTranslations({});
    setError(null);
  };

  return {
    translations,
    loading,
    error,
    updateTranslation,
    resetTranslations,
    setLoading,
    setError,
  };
}

