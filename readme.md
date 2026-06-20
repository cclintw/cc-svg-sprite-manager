# CC SVG Sprite Manager

CC SVG Sprite Manager is a WordPress plugin for managing SVG source icons and generating an SVG sprite.

## Features

- Upload a single SVG file or select up to 100 SVG files at a time.
- Validate each selected file before uploading and automatically send files in small batches.
- Store uploaded source SVG icons in `assets/sprite/icons/` inside the plugin directory.
- Generate `assets/sprite/cc-icons-sprite.svg` inside the plugin directory.
- Generate `assets/sprite/cc-icons-sprite.txt` inside the plugin directory.
- Sync a copy of `cc-icons-sprite.svg` to the active theme `assets/images/` directory.
- Generate `cc-icons-sprite.txt` in the theme `assets/images/` directory with icon list and usage notes.
- Preserve existing icons when importing new SVG files.
- Handle duplicate filenames by overwriting, skipping, or renaming to `name-1.svg`, `name-2.svg`, and so on.
- Preview existing icons in the WordPress admin.
- Delete selected source icons and regenerate the sprite.
- Select or deselect all icons with one toggle button.
- Use the `[icon]` shortcode to render an icon from the generated sprite.

## Usage

1. Activate the plugin.
2. Open **CC SVG Sprite** in the WordPress admin menu.
3. Upload up to 100 SVG files.
4. Use the generated `wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg` from your theme, or use the `[icon]` shortcode.

Example:

```text
[icon name="search" class="icon icon--search" size="24"]
```

## File Locations

- Source icons: `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/icons/`
- Plugin sprite: `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.svg`
- Plugin handoff note: `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.txt`
- Theme-readable sprite copy: `wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg`
- Theme handoff note: `wp-content/themes/classic-x/assets/images/cc-icons-sprite.txt`

The plugin intentionally leaves the theme copy in place on uninstall so a theme can keep reading it after the plugin is removed.

## License

GPLv2 or later.
