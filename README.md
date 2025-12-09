# WPML Google Auto Translate Addon

A powerful WordPress plugin that adds Google Translate integration to WPML Translation Dashboard, enabling bulk and per-row translation actions with Google Translate API and preview functionality.

## Description

**WPML Google Auto Translate Addon** seamlessly integrates Google Translate into your WPML workflow, allowing you to translate posts, pages, and custom post types directly from the WordPress admin. The plugin uses Google Translate for saving translations and includes a Google Translate widget for real-time preview of translations.

This addon significantly reduces translation workload by automating the translation process while maintaining full compatibility with Gutenberg blocks, Elementor, and other popular page builders.


## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- WPML (WordPress Multilingual Plugin) - **Required**
- Google Translate API access (for saving translations)

## Installation

1. **Download the plugin**
   - Download the plugin ZIP file or clone the repository

2. **Install via WordPress Admin**
   - Navigate to `Plugins > Add New`
   - Click `Upload Plugin`
   - Choose the plugin ZIP file
   - Click `Install Now`

3. **Activate the plugin**
   - After installation, click `Activate Plugin`
   - Ensure WPML is installed and activated before activating this plugin

4. **Verify Installation**
   - Go to `WPML > Translation Dashboard`
   - You should see "Translate with Google" buttons in the bulk actions and row actions

## Usage

### Bulk Translation

1. Navigate to `WPML > Translation Dashboard`
2. Select the posts/pages you want to translate
3. Choose your target language from the language selector
4. Click the "Translate with Google" bulk action button
5. Review translations in the popup modal
6. Save translations to WPML

### Per-Row Translation

1. Go to any post list page (`Posts`, `Pages`, or custom post types)
2. Hover over a post and click the "Translate" link in the row actions
3. Select the target language
4. Review and save translations

### Translation Preview

- The Google Translate widget is automatically loaded on translation pages
- Use the widget to preview translations before saving
- Translations are extracted from the widget and saved to WPML


### Translation Process

1. User initiates translation (bulk or single)
2. Plugin fetches post content (title, content, meta fields)
3. Content is sent to Google Translate API
4. Translations are displayed in a modal popup
5. User can preview using Google Translate widget
6. Translations are saved to WPML translation posts

## Development

### Hooks and Filters

The plugin uses standard WordPress hooks and filters. Key hooks include:

- `post_row_actions` - Adds translate button to post rows
- `page_row_actions` - Adds translate button to page rows
- `admin_enqueue_scripts` - Enqueues plugin assets
- `admin_footer` - Loads Google Translate widget scripts

### JavaScript API

The plugin exposes a JavaScript object `CP_WPML_AUTO_TRANSLATE` with:
- `ajax`: AJAX endpoint URL
- `nonce`: Security nonce for AJAX requests
- `languages`: Array of available WPML languages

## Troubleshooting

### Plugin Not Working

1. **Check WPML Installation**
   - Ensure WPML is installed and activated
   - Verify WPML is properly configured

2. **Check Browser Console**
   - Open browser developer tools (F12)
   - Check for JavaScript errors
   - Ensure jQuery is loaded

3. **Verify Permissions**
   - Ensure you have permission to edit posts
   - Check user capabilities


### Google Translate Widget Not Loading

1. **Check Network Connection**
   - Ensure internet connection is active
   - Verify Google services are accessible

2. **Check Browser Console**
   - Look for script loading errors
   - Verify no ad blockers are interfering

## Changelog

### 1.0.0
- Initial release
- Bulk translation support
- Per-row translation actions
- Google Translate widget integration
- Gutenberg block support
- Elementor page builder support
- Custom post type support


## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Cool Plugins

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

