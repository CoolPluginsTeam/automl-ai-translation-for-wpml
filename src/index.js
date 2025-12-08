import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './components/App';
// Import CSS - will be extracted to separate file by MiniCssExtractPlugin
import '../css/main.css';

// Initialize React app when DOM is ready
function initApp() {
  const container = document.getElementById('wpml-auto-translate-root');
  if (container) {
    const root = createRoot(container);
    root.render(React.createElement(App));
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  // DOM already loaded
  initApp();
}

// Export for potential external use
if (typeof window !== 'undefined') {
  window.wpmlAutoTranslate = {
    init: initApp,
  };
}

