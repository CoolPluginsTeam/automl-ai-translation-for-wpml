# Setup Guide - React Conversion

## Quick Start

1. **Install Dependencies**
   ```bash
   npm install
   ```

2. **Build for Development**
   ```bash
   npm run dev
   ```
   This will watch for changes and rebuild automatically.

3. **Build for Production**
   ```bash
   npm run build
   ```
   This creates an optimized production build.

## What Changed

### From jQuery to React

- **Old**: Single jQuery file (`assets/cp-wpml-auto-translate-admin.js`) with all functionality
- **New**: React component-based architecture with:
  - Modular components
  - Modern JavaScript (ES6+)
  - Hooks for state management
  - Separated concerns (API, utilities, components)

### File Structure

```
src/
├── components/          # React components
│   ├── App.js          # Main app component
│   ├── LanguageModal.js
│   ├── TranslationPopup.js
│   ├── TranslationTable.js
│   ├── TranslationRow.js
│   └── GoogleTranslateWidget.js
├── api/                # AJAX API functions
├── hooks/              # Custom React hooks
├── utils/              # Utility functions
└── index.js            # Entry point
```

### PHP Changes

The PHP admin class (`admin/class-wpml-at-admin.php`) now:
- Enqueues the React build instead of jQuery
- Adds a root div (`#wpml-auto-translate-root`) for React to mount
- Removes jQuery dependency (React handles DOM manipulation)

### Backward Compatibility

The plugin maintains the same functionality:
- Same AJAX endpoints
- Same WordPress hooks
- Same user interface
- Same translation workflow

## Development Workflow

1. Make changes to React components in `src/`
2. Run `npm run dev` to watch for changes
3. Test in WordPress admin
4. Run `npm run build` before committing

## Troubleshooting

### Build Errors
- Ensure Node.js v14+ is installed
- Delete `node_modules` and run `npm install` again
- Check that all dependencies in `package.json` are compatible

### React Not Loading
- Check browser console for errors
- Verify `#wpml-auto-translate-root` div exists in page source
- Ensure `CP_WPML_AUTO_TRANSLATE` object is available (check localized script)

### Translation Not Working
- Check network tab for AJAX errors
- Verify nonce is valid
- Check PHP error logs

## Migration Notes

The old jQuery file is still in `assets/` but will be replaced by the React build. The build process outputs to the same location, so the PHP enqueue path remains the same.

