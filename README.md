# WPML Auto Translate Addon - React Version

This WordPress plugin has been converted from jQuery to React with modern component architecture and coding standards.

## Features

- **React 18** with functional components and hooks
- **Modern build system** using Webpack and Babel
- **Component-based architecture** for maintainability
- **Modern JavaScript** (ES6+)
- **Separation of concerns** (components, utilities, API, hooks)

## Development Setup

### Prerequisites

- Node.js (v14 or higher)
- npm or yarn

### Installation

1. Install dependencies:
```bash
npm install
```

2. Build for development (with watch mode):
```bash
npm run dev
```

3. Build for production:
```bash
npm run build
```

### Project Structure

```
wpml-auto-translate-addon/
├── src/
│   ├── components/          # React components
│   │   ├── App.js
│   │   ├── LanguageModal.js
│   │   ├── TranslationPopup.js
│   │   ├── TranslationTable.js
│   │   ├── TranslationRow.js
│   │   └── GoogleTranslateWidget.js
│   ├── api/                 # API functions
│   │   └── translations.js
│   ├── hooks/               # Custom React hooks
│   │   └── useTranslationState.js
│   ├── utils/               # Utility functions
│   │   ├── buttonUtils.js
│   │   ├── contentExtractor.js
│   │   └── languageUtils.js
│   └── index.js             # Entry point
├── assets/                  # Built files (generated)
├── admin/                   # PHP admin classes
├── includes/                # PHP core classes
├── package.json
├── webpack.config.js
└── .babelrc
```

## Components

### App
Main application component that manages global state and coordinates other components.

### LanguageModal
Modal for selecting target language for translation.

### TranslationPopup
Main translation interface with Google Translate widget and translation table.

### TranslationTable
Table displaying source text and editable translation fields.

### TranslationRow
Individual row in the translation table.

### GoogleTranslateWidget
Wrapper for Google Translate widget integration.

## API

All AJAX requests are handled through the `api/translations.js` module:
- `getPostContents(ids)` - Fetch post content for translation
- `getPendingLanguages(ids)` - Get languages that need translation
- `saveTranslation(...)` - Save translated content

## Utilities

- **buttonUtils.js**: Functions for initializing and managing translation buttons
- **contentExtractor.js**: Extract translatable content from different editor types (Classic, Gutenberg, Elementor)
- **languageUtils.js**: Language mapping and validation for Google Translate

## Building

The build process compiles React components and bundles them into a single JavaScript file:

- Development: `npm run dev` (includes watch mode)
- Production: `npm run build` (optimized build)

The output file is `assets/cp-wpml-auto-translate-admin.js`

## PHP Integration

The PHP admin class (`admin/class-wpml-at-admin.php`) enqueues the React build and provides localized data through `wp_localize_script()`.

## Notes

- The old jQuery file (`assets/cp-wpml-auto-translate-admin.js`) should be replaced by the React build
- Make sure to run `npm run build` before deploying to production
- The React app mounts to `#wpml-auto-translate-root` div added by PHP
