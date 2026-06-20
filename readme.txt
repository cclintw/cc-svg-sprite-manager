=== CC SVG Sprite Manager ===
Contributors: Chance Lin
Tags: SVG, sprite, icon, generator, upload
Requires at least: 6.6
Tested up to: 7.0
Stable tag: 3.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage source SVG icons in the plugin directory, generate `cc-icons-sprite.svg`, and sync a copy plus `cc-icons-sprite.txt` to the active theme `assets/images` directory. Upload up to 100 SVG files at a time, handle duplicate IDs, and delete icons individually or in batch.

== Description ==

- Upload up to 100 validated SVG files at a time
- Automatically upload in small batches to avoid the PHP file-count limit
- Preserve uploaded source SVG icons in the plugin directory
- Generate `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.svg`
- Generate `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.txt` with icon list and usage notes
- Sync a copy to `wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg`
- Generate `wp-content/themes/classic-x/assets/images/cc-icons-sprite.txt` with icon list and usage notes
- Preserve existing source icons when uploading
- Options to overwrite, skip, or auto-rename duplicate icon IDs
- `[icon]` shortcode support
- Visual icon list with preview
- Select all, deselect all, and delete icons in batch
- Fully internationalized (i18n ready)

== Installation ==

1. Upload plugin files to the `/wp-content/plugins/cc-svg-sprite-manager` directory.
2. Activate through the 'Plugins' menu in WordPress.
3. Go to 'Tools > CC SVG Sprite Manager' in the admin menu.
4. Upload SVG files and choose how duplicate icon IDs should be handled.

== Frequently Asked Questions ==

= Where is the main sprite file stored? =
In `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.svg`

= Where is the frontend sprite copy stored? =
In the active theme `assets/images/cc-icons-sprite.svg`, for example `wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg`.

= Where are uploaded source SVG icons stored? =
In `wp-content/plugins/cc-svg-sprite-manager/assets/sprite/icons/`

= Can I use the sprite without this plugin? =
Yes. If your theme reads `assets/images/cc-icons-sprite.svg`, you can remove the plugin or manually place `cc-icons-sprite.svg` there.

= Can I upload icons one by one? =
Yes. You can upload a single SVG file or select up to 100 SVG files at a time.

= How do I output an icon? =
Use the `[icon]` shortcode, for example `[icon name="edit" class="icon icon--edit" size="32"]`.

= Will uploading overwrite existing icons? =
You can choose to overwrite, skip, or rename on conflict.

== Changelog ==

= 3.6.2 =
* Moved the plugin page under the WordPress Tools menu.
* Added the Traditional Chinese admin name `CC SVG Sprite 管理`.

= 3.6.1 =
* Added a plugin-local `assets/sprite/cc-icons-sprite.txt` file and synchronized it to the active theme.

= 3.6.0 =
* Removed the replace-all upload mode so existing icons are always preserved.
* Added a Select All / Deselect All toggle to the icon list.

= 3.5.1 =
* Changed the icon list to four desktop columns and two columns on tablet and smaller screens.
* Arranged merge and duplicate-ID options side by side.
* Added titled sections with horizontal separators and consistent button sizing.

= 3.5.0 =
* Removed ZIP upload support.
* Limited each selection to 100 SVG files and added validation before upload.
* Added batched AJAX uploads to avoid the PHP `max_file_uploads` limit.

= 3.4.0 =
* Renamed the generated sprite file to `cc-icons-sprite.svg`.
* Changed the theme-facing copy from uploads to the active theme `assets/images` directory.
* Added `cc-icons-sprite.txt` generation with icon list, usage examples, and hook notes.

= 3.3.6 =
* Fixed upload form handling by preserving the POST request method casing after sanitization.

= 3.3.5 =
* Changed the upload page to always report POST upload errors, including missing files and nonce verification failures.

= 3.3.4 =
* Added explicit writable-directory and file-write error reporting for SVG uploads.
* Added readable PHP upload error messages.
* Added write checks when generating plugin sprite.svg and syncing uploads/sprite.svg.

= 3.3.2 =
* Removed one-time sprite.svg-to-source-icons recovery code from the plugin runtime.

= 3.3.1 =
* Changed duplicate detection to use SVG filenames.
* Changed Add as New behavior to create `name-1.svg`, `name-2.svg`, and so on.
* Changed duplicate default to Add as New so uploads visibly increase the icon list unless overwrite or skip is selected.

= 3.3.0 =
* Set the plugin directory as the main source location for individual SVG icons and sprite.svg.
* Synced only a copy of sprite.svg to `wp-content/uploads/sprite.svg` for theme usage.
* Kept the admin icon list based on the plugin-managed sprite file.

= 3.2.0 =
* Added direct uploads/sprite.svg sync support, later refined in 3.3.0.
* Added persistent source SVG icon handling, later moved back to the plugin directory in 3.3.0.
* Removed automatic plugin frontend sprite output to avoid duplicate output with theme hooks.
* Added compatibility migration from the old plugin-local sprite location when uploads/sprite.svg does not exist.

= 3.1.0 =
* Added direct SVG file upload support.
* Added frontend sprite output from the plugin sprite file.
* Added `[icon]` shortcode support in the plugin.
* Added nonce, capability, input validation, and safer ZIP handling for upload and delete actions.

= 3.0.0 =
* Fully internationalized
* Improved code structure and comments
* Added batch delete confirmation
* Renamed plugin for clarity

= 2.6.1 =
* Initial public version

== License ==
This plugin is released under the GPLv2 or later.
