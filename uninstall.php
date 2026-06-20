<?php
/**
 * Uninstall handler.
 *
 * The plugin intentionally leaves generated SVG files in place. The
 * theme copy at assets/images/cc-icons-sprite.svg may be used by the theme
 * after the plugin is removed, and source icons are removed naturally when
 * the plugin directory is deleted.
 *
 * @package CCSVGSpriteManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
