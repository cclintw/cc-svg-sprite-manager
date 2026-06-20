<?php
/**
 * Plugin Name: CC SVG Sprite Manager
 * Plugin URI: https://plugin.cclin.cc/cc-svg-sprite-manager
 * Description: Manage SVG source icons and generate sprite files with SVG uploads, ID deduplication, shortcode support, and icon deletion.
 * Version: 3.6.2
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Author: Chance Lin
 * Author URI: https://cclin.cc
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cc-svg-sprite-manager
 * Domain Path: /languages
 * Network: false
 *
 * @package CCSVGSpriteManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC_SVG_SPRITE_MANAGER_VERSION', '3.6.2' );
define( 'CC_SVG_SPRITE_MANAGER_FILE', __FILE__ );
define( 'CC_SVG_SPRITE_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME', 'cc-icons-sprite.svg' );
define( 'CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME', 'cc-icons-sprite.txt' );
define( 'CC_SVG_SPRITE_MANAGER_MAX_UPLOAD_FILES', 100 );
define( 'CC_SVG_SPRITE_MANAGER_UPLOAD_BATCH_SIZE', 10 );

require_once CC_SVG_SPRITE_MANAGER_DIR . 'includes/view-details.php';

add_action( 'init', 'cc_svg_sprite_manager_load_textdomain' );
add_action( 'init', 'cc_svg_sprite_manager_register_shortcode', 20 );
add_action( 'init', 'cc_svg_sprite_manager_sync_theme_sprite', 40 );
add_action( 'admin_menu', 'cc_svg_sprite_manager_register_admin_menu' );
add_action( 'admin_enqueue_scripts', 'cc_svg_sprite_manager_enqueue_admin_assets' );
add_action( 'admin_post_cc_bulk_delete_icons', 'cc_svg_sprite_manager_bulk_delete_icons' );
add_action( 'wp_ajax_cc_svg_sprite_upload', 'cc_svg_sprite_manager_ajax_upload' );

/**
 * Load plugin translations.
 */
function cc_svg_sprite_manager_load_textdomain() {
	load_plugin_textdomain( 'cc-svg-sprite-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Register the public icon shortcode.
 */
function cc_svg_sprite_manager_register_shortcode() {
	add_shortcode( 'icon', 'cc_svg_sprite_manager_icon_shortcode' );
}

/**
 * Register the admin menu.
 */
function cc_svg_sprite_manager_register_admin_menu() {
	add_management_page(
		__( 'CC SVG Sprite Manager', 'cc-svg-sprite-manager' ),
		__( 'CC SVG Sprite Manager', 'cc-svg-sprite-manager' ),
		'manage_options',
		'cc-svg-sprite-manager',
		'cc_svg_sprite_manager_page'
	);
}

/**
 * Load the uploader only on this plugin's admin page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function cc_svg_sprite_manager_enqueue_admin_assets( $hook_suffix ) {
	if ( 'tools_page_cc-svg-sprite-manager' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'cc-svg-sprite-manager-admin',
		plugins_url( 'assets/css/admin.css', CC_SVG_SPRITE_MANAGER_FILE ),
		array(),
		CC_SVG_SPRITE_MANAGER_VERSION
	);

	wp_enqueue_script(
		'cc-svg-sprite-manager-admin',
		plugins_url( 'assets/js/admin.js', CC_SVG_SPRITE_MANAGER_FILE ),
		array(),
		CC_SVG_SPRITE_MANAGER_VERSION,
		true
	);

	wp_localize_script(
		'cc-svg-sprite-manager-admin',
		'CCSVGSpriteManager',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'action'        => 'cc_svg_sprite_upload',
			'nonce'         => wp_create_nonce( 'cc_svg_sprite_upload_action' ),
			'maxFiles'      => CC_SVG_SPRITE_MANAGER_MAX_UPLOAD_FILES,
			'batchSize'     => CC_SVG_SPRITE_MANAGER_UPLOAD_BATCH_SIZE,
			'invalidType'   => __( 'Only valid SVG files can be uploaded.', 'cc-svg-sprite-manager' ),
			'tooManyFiles'  => sprintf(
				/* translators: %d: maximum number of SVG files. */
				__( 'You can upload a maximum of %d SVG files at a time.', 'cc-svg-sprite-manager' ),
				CC_SVG_SPRITE_MANAGER_MAX_UPLOAD_FILES
			),
			'validating'    => __( 'Validating SVG files...', 'cc-svg-sprite-manager' ),
			'uploading'     => __( 'Uploading SVG files...', 'cc-svg-sprite-manager' ),
			'uploadFailed'  => __( 'The SVG upload failed.', 'cc-svg-sprite-manager' ),
			'uploadComplete' => __( 'Upload complete. Refreshing the icon list...', 'cc-svg-sprite-manager' ),
			'selectAll'      => __( 'Select All', 'cc-svg-sprite-manager' ),
			'deselectAll'    => __( 'Deselect All', 'cc-svg-sprite-manager' ),
		)
	);
}

/**
 * Process a batch of SVG files sent by the admin uploader.
 */
function cc_svg_sprite_manager_ajax_upload() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cc-svg-sprite-manager' ) ), 403 );
	}

	check_ajax_referer( 'cc_svg_sprite_upload_action', 'nonce' );

	if ( empty( $_FILES['svg_files'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No upload files were received. Check the selected file and the server upload size limits.', 'cc-svg-sprite-manager' ) ), 400 );
	}

	$file_count = is_array( $_FILES['svg_files']['name'] ?? null ) ? count( $_FILES['svg_files']['name'] ) : 1;
	if ( $file_count > CC_SVG_SPRITE_MANAGER_UPLOAD_BATCH_SIZE ) {
		wp_send_json_error( array( 'message' => __( 'The upload batch contains too many files.', 'cc-svg-sprite-manager' ) ), 400 );
	}

	$dedupe_mode = isset( $_POST['dedupe_mode'] ) ? sanitize_key( wp_unslash( $_POST['dedupe_mode'] ) ) : 'rename';
	$notice      = cc_handle_svg_uploads( $_FILES['svg_files'], $dedupe_mode ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$message     = wp_strip_all_tags( $notice );

	if ( str_contains( $notice, 'notice-error' ) ) {
		wp_send_json_error( array( 'message' => $message ), 400 );
	}

	wp_send_json_success( array( 'message' => $message ) );
}

/**
 * Get plugin-managed paths.
 *
 * @return array{theme_assets_dir:string,theme_sprite_file:string,theme_instructions_file:string,sprite_dir:string,sprite_file:string,instructions_file:string,legacy_sprite_file:string,icon_dir:string}
 */
function cc_svg_sprite_manager_get_paths() {
	$theme_assets_dir = get_template_directory() . '/assets/images';
	$sprite_dir       = CC_SVG_SPRITE_MANAGER_DIR . 'assets/sprite';

	return array(
		'theme_assets_dir'        => $theme_assets_dir,
		'theme_sprite_file'       => trailingslashit( $theme_assets_dir ) . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'theme_instructions_file' => trailingslashit( $theme_assets_dir ) . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'sprite_dir'              => $sprite_dir,
		'sprite_file'             => trailingslashit( $sprite_dir ) . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'instructions_file'       => trailingslashit( $sprite_dir ) . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'legacy_sprite_file'      => trailingslashit( $sprite_dir ) . 'sprite.svg',
		'icon_dir'                => trailingslashit( $sprite_dir ) . 'icons',
	);
}

/**
 * Copy the plugin-local sprite and instructions to the active theme assets directory.
 */
function cc_svg_sprite_manager_sync_theme_sprite() {
	$paths = cc_svg_sprite_manager_get_paths();
	cc_svg_sprite_manager_migrate_legacy_sprite_file( $paths );

	if ( ! file_exists( $paths['sprite_file'] ) ) {
		return true;
	}

	$instructions = cc_svg_sprite_manager_generate_instructions();
	$write_result = cc_svg_sprite_manager_write_file(
		$paths['instructions_file'],
		$instructions,
		__( 'Unable to write plugin cc-icons-sprite.txt.', 'cc-svg-sprite-manager' )
	);
	if ( is_wp_error( $write_result ) ) {
		return $write_result;
	}

	wp_mkdir_p( $paths['theme_assets_dir'] );
	if ( ! is_writable( $paths['theme_assets_dir'] ) ) {
		return new WP_Error(
			'cc_svg_theme_assets_not_writable',
			sprintf(
				/* translators: %s: directory path. */
				__( 'The theme assets directory is not writable: %s', 'cc-svg-sprite-manager' ),
				$paths['theme_assets_dir']
			)
		);
	}

	if ( ! copy( $paths['sprite_file'], $paths['theme_sprite_file'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return new WP_Error( 'cc_svg_theme_sprite_copy_failed', __( 'Unable to copy cc-icons-sprite.svg to the theme assets directory.', 'cc-svg-sprite-manager' ) );
	}

	if ( ! copy( $paths['instructions_file'], $paths['theme_instructions_file'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return new WP_Error( 'cc_svg_theme_instructions_copy_failed', __( 'Unable to copy cc-icons-sprite.txt to the theme assets directory.', 'cc-svg-sprite-manager' ) );
	}

	return true;
}

/**
 * Copy an existing legacy sprite.svg once when upgrading to the renamed file.
 *
 * @param array<string,string> $paths Plugin paths.
 */
function cc_svg_sprite_manager_migrate_legacy_sprite_file( $paths ) {
	if ( file_exists( $paths['sprite_file'] ) || ! file_exists( $paths['legacy_sprite_file'] ) ) {
		return;
	}

	copy( $paths['legacy_sprite_file'], $paths['sprite_file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
}

/**
 * Generate a plain-text handoff note for the plugin and theme sprite files.
 *
 * @return string
 */
function cc_svg_sprite_manager_generate_instructions() {
	$paths = cc_svg_sprite_manager_get_paths();
	$icons = array();

	foreach ( glob( trailingslashit( $paths['icon_dir'] ) . '*.svg' ) as $file ) {
		$icons[] = sanitize_title( basename( $file, '.svg' ) );
	}

	natcasesort( $icons );

	$lines = array(
		'CC SVG Sprite Manager',
		'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
		'',
		'中文說明',
		'這個檔案由 CC SVG Sprite Manager 自動產生，提供 theme 使用 SVG sprite 的基本資訊。Theme hook 會在檔案存在時讀取 assets/images/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME . '，並在 wp_footer inline 輸出。',
		'',
		'產生檔案：',
		'- Plugin sprite：wp-content/plugins/cc-svg-sprite-manager/assets/sprite/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'- Plugin 說明檔：wp-content/plugins/cc-svg-sprite-manager/assets/sprite/' . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'- Theme sprite：wp-content/themes/' . get_template() . '/assets/images/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'- 說明檔：wp-content/themes/' . get_template() . '/assets/images/' . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'',
		'引用方式：',
		'<svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-search"></use></svg>',
		'[icon name="search" class="icon icon--search" size="24"]',
		'',
		'English Notes',
		'This file is generated by CC SVG Sprite Manager for theme-side SVG sprite usage. The theme hook reads assets/images/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME . ' and inlines it in wp_footer when the file exists.',
		'',
		'Generated files:',
		'- Plugin sprite: wp-content/plugins/cc-svg-sprite-manager/assets/sprite/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'- Plugin note: wp-content/plugins/cc-svg-sprite-manager/assets/sprite/' . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'- Theme sprite: wp-content/themes/' . get_template() . '/assets/images/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME,
		'- This note: wp-content/themes/' . get_template() . '/assets/images/' . CC_SVG_SPRITE_MANAGER_INSTRUCTIONS_FILENAME,
		'',
		'Theme hook:',
		'The Classic X theme hook classic_x_inline_svg_sprite() reads assets/images/' . CC_SVG_SPRITE_MANAGER_SPRITE_FILENAME . ' and inlines it in wp_footer when the file exists.',
		'',
		'Usage:',
		'<svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-search"></use></svg>',
		'[icon name="search" class="icon icon--search" size="24"]',
		'',
		'Available icons:',
	);

	foreach ( $icons as $icon ) {
		$lines[] = '- ' . $icon . ' (#icon-' . $icon . ')';
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Output the generated sprite once in the frontend footer.
 */
function cc_svg_sprite_manager_inline_sprite() {
	static $printed = false;
	$paths          = cc_svg_sprite_manager_get_paths();

	if ( $printed || ! file_exists( $paths['theme_sprite_file'] ) ) {
		return;
	}

	$svg_content = file_get_contents( $paths['theme_sprite_file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $svg_content || '' === trim( $svg_content ) ) {
		return;
	}

	$printed = true;
	echo cc_svg_sprite_manager_kses_svg_sprite( $svg_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render an icon from the generated sprite.
 *
 * @param array<string,string> $atts Shortcode attributes.
 * @return string
 */
function cc_svg_sprite_manager_icon_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'name'  => '',
			'class' => 'icon',
			'size'  => '24',
		),
		$atts,
		'icon'
	);

	$name = sanitize_title( $atts['name'] );
	if ( '' === $name ) {
		return '';
	}

	$size = preg_replace( '/[^0-9.%a-zA-Z_-]/', '', (string) $atts['size'] );
	if ( '' === $size ) {
		$size = '24';
	}

	return sprintf(
		'<svg class="%1$s" width="%2$s" height="%2$s" aria-hidden="true" focusable="false"><use href="#icon-%3$s"></use></svg>',
		esc_attr( $atts['class'] ),
		esc_attr( $size ),
		esc_attr( $name )
	);
}

/**
 * Handle bulk icon deletion.
 */
function cc_svg_sprite_manager_bulk_delete_icons() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cc-svg-sprite-manager' ) );
	}

	check_admin_referer( 'cc_bulk_delete_action' );

	$raw_icons = isset( $_POST['icons'] ) ? wp_unslash( $_POST['icons'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! is_array( $raw_icons ) ) {
		$raw_icons = array();
	}

	$icons = array_filter( array_map( 'cc_svg_sprite_manager_sanitize_symbol_id', $raw_icons ) );
	if ( empty( $icons ) ) {
		wp_safe_redirect( admin_url( 'tools.php?page=cc-svg-sprite-manager' ) );
		exit;
	}

	$paths = cc_svg_sprite_manager_get_paths();

	cc_svg_sprite_manager_migrate_legacy_sprite_file( $paths );
	if ( ! file_exists( $paths['sprite_file'] ) ) {
		wp_die( esc_html__( 'cc-icons-sprite.svg not found.', 'cc-svg-sprite-manager' ) );
	}

	$svg_content = file_get_contents( $paths['sprite_file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $svg_content ) {
		wp_die( esc_html__( 'Unable to read cc-icons-sprite.svg.', 'cc-svg-sprite-manager' ) );
	}

	foreach ( $icons as $icon_id ) {
		$pattern     = '/<symbol\b[^>]*id="' . preg_quote( $icon_id, '/' ) . '"[^>]*>.*?<\/symbol>/is';
		$svg_content = preg_replace( $pattern, '', $svg_content );

		$source_file = trailingslashit( $paths['icon_dir'] ) . preg_replace( '/^icon-/', '', $icon_id ) . '.svg';
		if ( file_exists( $source_file ) ) {
			unlink( $source_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	$write_result = cc_svg_sprite_manager_write_file( $paths['sprite_file'], $svg_content, __( 'Unable to update plugin cc-icons-sprite.svg after deleting icons.', 'cc-svg-sprite-manager' ) );
	if ( is_wp_error( $write_result ) ) {
		wp_die( esc_html( $write_result->get_error_message() ) );
	}

	$sync_result = cc_svg_sprite_manager_sync_theme_sprite();
	if ( is_wp_error( $sync_result ) ) {
		wp_die( esc_html( $sync_result->get_error_message() ) );
	}

	wp_safe_redirect(
		add_query_arg(
			'deleted_count',
			count( $icons ),
			admin_url( 'tools.php?page=cc-svg-sprite-manager' )
		)
	);
	exit;
}

/**
 * Render the plugin admin page.
 */
function cc_svg_sprite_manager_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cc-svg-sprite-manager' ) );
	}

	cc_svg_sprite_manager_ensure_sprite_dir();

	$notice = '';
	if ( 'POST' === cc_svg_sprite_manager_get_request_method() ) {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cc_svg_sprite_upload_action' ) ) {
			$notice = cc_svg_sprite_manager_notice( __( 'Upload request could not be verified. Please reload the page and try again.', 'cc-svg-sprite-manager' ), 'error' );
		} elseif ( empty( $_FILES['svg_files'] ) ) {
			$notice = cc_svg_sprite_manager_notice( __( 'No upload files were received. Check the selected file and the server upload size limits.', 'cc-svg-sprite-manager' ), 'error' );
		} else {
			$dedupe_mode = isset( $_POST['dedupe_mode'] ) ? sanitize_key( wp_unslash( $_POST['dedupe_mode'] ) ) : 'rename';
			$file_count  = is_array( $_FILES['svg_files']['name'] ?? null ) ? count( $_FILES['svg_files']['name'] ) : 1;

			if ( $file_count > CC_SVG_SPRITE_MANAGER_MAX_UPLOAD_FILES ) {
				$notice = cc_svg_sprite_manager_notice(
					sprintf(
						/* translators: %d: maximum number of SVG files. */
						__( 'You can upload a maximum of %d SVG files at a time.', 'cc-svg-sprite-manager' ),
						CC_SVG_SPRITE_MANAGER_MAX_UPLOAD_FILES
					),
					'error'
				);
			} else {
				$notice = cc_handle_svg_uploads( $_FILES['svg_files'], $dedupe_mode ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}
	}

	echo '<div class="wrap cc-svg-sprite-manager"><h1>' . esc_html__( 'CC SVG Sprite Manager', 'cc-svg-sprite-manager' ) . '</h1>';
	echo wp_kses_post( $notice );

	if ( 'POST' !== cc_svg_sprite_manager_get_request_method() && isset( $_GET['deleted_count'] ) ) {
		$deleted_count = absint( $_GET['deleted_count'] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: deleted icon count. */
					_n( '%d icon deleted.', '%d icons deleted.', $deleted_count, 'cc-svg-sprite-manager' ),
					$deleted_count
				)
			)
		);
	}

	echo '<section class="cc-svg-section"><h2>' . esc_html__( 'Upload SVG Files', 'cc-svg-sprite-manager' ) . '</h2>';
	echo '<form id="cc-svg-upload-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'tools.php?page=cc-svg-sprite-manager' ) ) . '">';
	wp_nonce_field( 'cc_svg_sprite_upload_action' );
	echo '<input type="hidden" name="cc_svg_sprite_upload" value="1">';
	echo '<p><input id="cc-svg-files" type="file" name="svg_files[]" required multiple accept=".svg,image/svg+xml" /></p>';
	echo '<p class="description">' . esc_html__( 'Only SVG files are supported. You can upload a maximum of 100 SVG files at a time.', 'cc-svg-sprite-manager' ) . '</p>';
	echo '<fieldset class="cc-svg-duplicate-options"><legend>' . esc_html__( 'Duplicate Icon IDs:', 'cc-svg-sprite-manager' ) . '</legend>';
	echo '<label><input type="radio" name="dedupe_mode" value="replace"> ' . esc_html__( 'Overwrite', 'cc-svg-sprite-manager' ) . '</label>';
	echo '<label><input type="radio" name="dedupe_mode" value="skip"> ' . esc_html__( 'Skip', 'cc-svg-sprite-manager' ) . '</label>';
	echo '<label><input type="radio" name="dedupe_mode" value="rename" checked> ' . esc_html__( 'Add as New', 'cc-svg-sprite-manager' ) . '</label></fieldset>';
	echo '<input id="cc-svg-upload-submit" type="submit" class="button button-primary" value="' . esc_attr__( 'Upload', 'cc-svg-sprite-manager' ) . '">';
	echo '<div id="cc-svg-upload-status" aria-live="polite"></div>';
	echo '</form></section>';
	echo '<hr class="cc-svg-section-divider">';

	cc_svg_sprite_manager_render_icon_list();

	echo '</div>';
}

/**
 * Return the current request method.
 *
 * @return string
 */
function cc_svg_sprite_manager_get_request_method() {
	return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
}

/**
 * Render the current icon list.
 */
function cc_svg_sprite_manager_render_icon_list() {
	$paths = cc_svg_sprite_manager_get_paths();
	cc_svg_sprite_manager_migrate_legacy_sprite_file( $paths );

	if ( ! file_exists( $paths['sprite_file'] ) ) {
		echo '<p>' . esc_html__( 'cc-icons-sprite.svg not found.', 'cc-svg-sprite-manager' ) . '</p>';
		return;
	}

	$svg_content = file_get_contents( $paths['sprite_file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $svg_content ) {
		echo '<p>' . esc_html__( 'Unable to read cc-icons-sprite.svg.', 'cc-svg-sprite-manager' ) . '</p>';
		return;
	}

	$file_size = round( filesize( $paths['sprite_file'] ) / 1024, 1 );

	if ( ! preg_match_all( '/<symbol\b[^>]*id="icon-([^"]+)"[^>]*>.*?<\/symbol>/is', $svg_content, $matches ) ) {
		echo '<p>' . esc_html__( 'No icons found.', 'cc-svg-sprite-manager' ) . '</p>';
		return;
	}

	$icons = array_map(
		static fn( $id ) => array( 'id' => sanitize_title( $id ) ),
		$matches[1]
	);
	usort( $icons, static fn( $a, $b ) => strnatcasecmp( $a['id'], $b['id'] ) );

	echo '<h2>' . esc_html__( 'Icon List', 'cc-svg-sprite-manager' ) . '</h2>';
	printf(
		'<p><strong>%1$d</strong> %2$s, <strong>%3$s</strong></p>',
		count( $icons ),
		esc_html__( 'icons', 'cc-svg-sprite-manager' ),
		esc_html( $file_size . 'k' )
	);
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="cc_bulk_delete_icons">';
	wp_nonce_field( 'cc_bulk_delete_action' );
	echo '<ul class="cc-svg-icon-grid">';
	foreach ( $icons as $icon ) {
		$style = str_ends_with( $icon['id'], '-filled' ) ? '' : 'fill:none;stroke:currentColor;stroke-width:1;';
		echo '<li class="cc-svg-icon-item">';
		echo '<input type="checkbox" name="icons[]" value="icon-' . esc_attr( $icon['id'] ) . '" style="margin-right:5px">';
		echo '<svg width="25" height="25" style="margin-right:5px;' . esc_attr( $style ) . '"><use href="#icon-' . esc_attr( $icon['id'] ) . '"></use></svg>';
		echo esc_html( $icon['id'] );
		echo '</li>';
	}
	echo '</ul>';
	echo '<p class="cc-svg-list-actions">';
	echo '<button id="cc-svg-toggle-all" type="button" class="button button-secondary">' . esc_html__( 'Select All', 'cc-svg-sprite-manager' ) . '</button>';
	echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'' . esc_js( __( 'Delete selected?', 'cc-svg-sprite-manager' ) ) . '\')">' . esc_html__( 'Delete Selected', 'cc-svg-sprite-manager' ) . '</button>';
	echo '</p>';
	echo '</form>';
	echo cc_svg_sprite_manager_kses_svg_sprite( $svg_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Handle uploaded SVG files.
 *
 * @param array<string,mixed> $files Uploaded file array.
 * @param string              $dedupe_mode Duplicate handling mode.
 * @return string Admin notice HTML.
 */
function cc_handle_svg_uploads( $files, $dedupe_mode = 'rename' ) {
	$dedupe_mode = in_array( $dedupe_mode, array( 'replace', 'skip', 'rename' ), true ) ? $dedupe_mode : 'rename';
	$uploads     = cc_svg_sprite_manager_normalize_uploaded_files( $files );
	$paths       = cc_svg_sprite_manager_get_paths();

	if ( empty( $uploads ) ) {
		return cc_svg_sprite_manager_notice( __( 'No upload files found.', 'cc-svg-sprite-manager' ), 'error' );
	}

	$wp_upload_dir = wp_upload_dir();
	if ( ! empty( $wp_upload_dir['error'] ) ) {
		return cc_svg_sprite_manager_notice( $wp_upload_dir['error'], 'error' );
	}

	$temp_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'cc-svg-tmp-' . wp_generate_uuid4();
	if ( ! wp_mkdir_p( $temp_dir ) ) {
		return cc_svg_sprite_manager_notice( __( 'Unable to create a temporary upload directory.', 'cc-svg-sprite-manager' ), 'error' );
	}

	$imported_count = 0;
	$errors         = array();

	foreach ( $uploads as $upload ) {
		$result = cc_svg_sprite_manager_collect_upload_svgs( $upload, $temp_dir );
		if ( is_wp_error( $result ) ) {
			$filename = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
			$errors[] = '' === $filename ? $result->get_error_message() : sprintf(
				/* translators: 1: file name, 2: error message. */
				__( '%1$s: %2$s', 'cc-svg-sprite-manager' ),
				$filename,
				$result->get_error_message()
			);
			continue;
		}

		$imported_count += $result;
	}

	if ( 0 === $imported_count ) {
		cc_rrmdir( $temp_dir );
		return cc_svg_sprite_manager_notice(
			empty( $errors ) ? __( 'No valid SVG files were found.', 'cc-svg-sprite-manager' ) : implode( ' ', $errors ),
			'error'
		);
	}

	cc_svg_sprite_manager_ensure_sprite_dir();
	$writable_check = cc_svg_sprite_manager_check_writable_paths();
	if ( is_wp_error( $writable_check ) ) {
		cc_rrmdir( $temp_dir );
		return cc_svg_sprite_manager_notice( $writable_check->get_error_message(), 'error' );
	}

	$save_stats = cc_svg_sprite_manager_save_source_icons( $temp_dir, $dedupe_mode );
	if ( is_wp_error( $save_stats ) ) {
		cc_rrmdir( $temp_dir );
		return cc_svg_sprite_manager_notice( $save_stats->get_error_message(), 'error' );
	}

	$sprite  = new CCSVGSprite( $paths['icon_dir'] );
	$new_svg = $sprite->generate();

	$sprite_write = cc_svg_sprite_manager_write_file( $paths['sprite_file'], $new_svg, __( 'Unable to write plugin cc-icons-sprite.svg.', 'cc-svg-sprite-manager' ) );
	if ( is_wp_error( $sprite_write ) ) {
		cc_rrmdir( $temp_dir );
		return cc_svg_sprite_manager_notice( $sprite_write->get_error_message(), 'error' );
	}

	$sync_result = cc_svg_sprite_manager_sync_theme_sprite();
	if ( is_wp_error( $sync_result ) ) {
		cc_rrmdir( $temp_dir );
		return cc_svg_sprite_manager_notice( $sync_result->get_error_message(), 'error' );
	}
	cc_rrmdir( $temp_dir );

	$total_icons = count( glob( trailingslashit( $paths['icon_dir'] ) . '*.svg' ) );
	$message = sprintf(
		/* translators: 1: saved SVG count, 2: new SVG count, 3: updated SVG count, 4: renamed SVG count, 5: skipped SVG count, 6: total icon count. */
		__( 'cc-icons-sprite.svg updated. Saved: %1$d. New: %2$d. Updated: %3$d. Renamed: %4$d. Skipped: %5$d. Total icons: %6$d.', 'cc-svg-sprite-manager' ),
		$save_stats['saved'],
		$save_stats['created'],
		$save_stats['updated'],
		$save_stats['renamed'],
		$save_stats['skipped'],
		$total_icons
	);

	if ( ! empty( $errors ) ) {
		$message .= ' ' . implode( ' ', $errors );
	}

	return cc_svg_sprite_manager_notice( $message, 'success' );
}

/**
 * Normalize single and multiple file upload arrays.
 *
 * @param array<string,mixed> $files Uploaded file array.
 * @return array<int,array<string,mixed>>
 */
function cc_svg_sprite_manager_normalize_uploaded_files( $files ) {
	if ( ! isset( $files['name'] ) ) {
		return array();
	}

	if ( ! is_array( $files['name'] ) ) {
		return array( $files );
	}

	$normalized = array();
	foreach ( array_keys( $files['name'] ) as $index ) {
		$normalized[] = array(
			'name'     => $files['name'][ $index ] ?? '',
			'type'     => $files['type'][ $index ] ?? '',
			'tmp_name' => $files['tmp_name'][ $index ] ?? '',
			'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
			'size'     => $files['size'][ $index ] ?? 0,
		);
	}

	return $normalized;
}

/**
 * Collect an uploaded SVG file into a temp directory.
 *
 * @param array<string,mixed> $upload Uploaded file.
 * @param string              $temp_dir Temporary directory.
 * @return int|WP_Error Number of SVGs collected, or an error.
 */
function cc_svg_sprite_manager_collect_upload_svgs( $upload, $temp_dir ) {
	$error = absint( $upload['error'] ?? UPLOAD_ERR_NO_FILE );
	if ( UPLOAD_ERR_NO_FILE === $error ) {
		return new WP_Error( 'cc_svg_no_file', __( 'No upload file selected.', 'cc-svg-sprite-manager' ) );
	}
	if ( UPLOAD_ERR_OK !== $error ) {
		return new WP_Error( 'cc_svg_upload_error', cc_svg_sprite_manager_upload_error_message( $error ) );
	}

	$tmp_name = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
	$name     = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
	$ext      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

	if ( ( ! is_uploaded_file( $tmp_name ) && ! is_readable( $tmp_name ) ) || '' === $name ) {
		return new WP_Error( 'cc_svg_invalid_upload', __( 'Invalid uploaded file.', 'cc-svg-sprite-manager' ) );
	}

	if ( 'svg' !== $ext ) {
		return new WP_Error( 'cc_svg_unsupported_type', __( 'Only SVG files are supported.', 'cc-svg-sprite-manager' ) );
	}

	$contents = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents || ! cc_svg_sprite_manager_is_svg_content( $contents ) ) {
		return new WP_Error( 'cc_svg_invalid_svg', __( 'The uploaded SVG file is invalid.', 'cc-svg-sprite-manager' ) );
	}

	$target       = trailingslashit( $temp_dir ) . wp_unique_filename( $temp_dir, $name );
	$write_result = cc_svg_sprite_manager_write_file( $target, $contents, __( 'Unable to save the uploaded SVG to the temporary directory.', 'cc-svg-sprite-manager' ) );
	if ( is_wp_error( $write_result ) ) {
		return $write_result;
	}

	return 1;
}

/**
 * Save collected SVG files as persistent source icons.
 *
 * @param string $temp_dir Temporary directory with collected SVG files.
 * @param string $dedupe_mode Duplicate handling mode.
 * @return array{saved:int,created:int,updated:int,renamed:int,skipped:int}|WP_Error Save stats, or an error.
 */
function cc_svg_sprite_manager_save_source_icons( $temp_dir, $dedupe_mode ) {
	$paths = cc_svg_sprite_manager_get_paths();

	if ( ! wp_mkdir_p( $paths['icon_dir'] ) ) {
		return new WP_Error( 'cc_svg_icon_dir_failed', __( 'Unable to create the source SVG icon directory.', 'cc-svg-sprite-manager' ) );
	}

	$stats = array(
		'saved'   => 0,
		'created' => 0,
		'updated' => 0,
		'renamed' => 0,
		'skipped' => 0,
	);

	foreach ( glob( trailingslashit( $temp_dir ) . '*.svg' ) as $file ) {
		$slug = sanitize_title( basename( $file, '.svg' ) );
		if ( '' === $slug ) {
			continue;
		}

		$target = trailingslashit( $paths['icon_dir'] ) . $slug . '.svg';
		$exists = file_exists( $target );
		if ( file_exists( $target ) ) {
			if ( 'skip' === $dedupe_mode ) {
				$stats['skipped']++;
				continue;
			}

			if ( 'rename' === $dedupe_mode ) {
				$base_slug = $slug;
				$i         = 1;
				while ( file_exists( $target ) ) {
					$slug   = $base_slug . '-' . $i;
					$target = trailingslashit( $paths['icon_dir'] ) . $slug . '.svg';
					$i++;
				}
				$exists = false;
				$stats['renamed']++;
			}
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || ! cc_svg_sprite_manager_is_svg_content( $contents ) ) {
			continue;
		}

		$write_result = cc_svg_sprite_manager_write_file( $target, $contents, __( 'Unable to save the source SVG icon.', 'cc-svg-sprite-manager' ) );
		if ( is_wp_error( $write_result ) ) {
			return $write_result;
		}
		$stats['saved']++;

		if ( $exists ) {
			$stats['updated']++;
		} else {
			$stats['created']++;
		}
	}

	if ( 0 === $stats['saved'] ) {
		if ( $stats['skipped'] > 0 ) {
			return new WP_Error( 'cc_svg_all_icons_skipped', __( 'All uploaded SVG icons already existed and were skipped.', 'cc-svg-sprite-manager' ) );
		}

		return new WP_Error( 'cc_svg_no_saved_icons', __( 'No source SVG icons were saved.', 'cc-svg-sprite-manager' ) );
	}

	return $stats;
}

/**
 * Check whether uploaded content looks like an SVG document.
 *
 * @param string $contents File contents.
 * @return bool
 */
function cc_svg_sprite_manager_is_svg_content( $contents ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return 1 === preg_match( '/^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*<svg\b/is', $contents );
	}

	$previous_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadXML( $contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous_errors );

	return $loaded && $document->documentElement && 'svg' === strtolower( $document->documentElement->localName );
}

/**
 * Check required runtime directories are writable.
 *
 * @return true|WP_Error
 */
function cc_svg_sprite_manager_check_writable_paths() {
	$paths = cc_svg_sprite_manager_get_paths();

	$directories = array(
		$paths['sprite_dir'],
		$paths['icon_dir'],
		$paths['theme_assets_dir'],
	);

	foreach ( $directories as $directory ) {
		if ( ! file_exists( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return new WP_Error(
				'cc_svg_directory_create_failed',
				sprintf(
					/* translators: %s: directory path. */
					__( 'Unable to create directory: %s', 'cc-svg-sprite-manager' ),
					$directory
				)
			);
		}

		if ( ! is_writable( $directory ) ) {
			return new WP_Error(
				'cc_svg_directory_not_writable',
				sprintf(
					/* translators: %s: directory path. */
					__( 'Directory is not writable: %s', 'cc-svg-sprite-manager' ),
					$directory
				)
			);
		}
	}

	return true;
}

/**
 * Write a file and return a WordPress error when it fails.
 *
 * @param string $path File path.
 * @param string $contents File contents.
 * @param string $message Error message.
 * @return true|WP_Error
 */
function cc_svg_sprite_manager_write_file( $path, $contents, $message ) {
	$directory = dirname( $path );

	if ( ! file_exists( $directory ) && ! wp_mkdir_p( $directory ) ) {
		return new WP_Error(
			'cc_svg_write_directory_create_failed',
			sprintf(
				/* translators: 1: error message, 2: directory path. */
				__( '%1$s Directory could not be created: %2$s', 'cc-svg-sprite-manager' ),
				$message,
				$directory
			)
		);
	}

	if ( ! is_writable( $directory ) ) {
		return new WP_Error(
			'cc_svg_write_directory_not_writable',
			sprintf(
				/* translators: 1: error message, 2: directory path. */
				__( '%1$s Directory is not writable: %2$s', 'cc-svg-sprite-manager' ),
				$message,
				$directory
			)
		);
	}

	$bytes = file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === $bytes ) {
		return new WP_Error(
			'cc_svg_write_failed',
			sprintf(
				/* translators: 1: error message, 2: file path. */
				__( '%1$s File could not be written: %2$s', 'cc-svg-sprite-manager' ),
				$message,
				$path
			)
		);
	}

	return true;
}

/**
 * Convert PHP upload error code to a readable message.
 *
 * @param int $error_code PHP upload error code.
 * @return string
 */
function cc_svg_sprite_manager_upload_error_message( $error_code ) {
	switch ( $error_code ) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return __( 'The uploaded file exceeds the allowed upload size.', 'cc-svg-sprite-manager' );
		case UPLOAD_ERR_PARTIAL:
			return __( 'The uploaded file was only partially uploaded.', 'cc-svg-sprite-manager' );
		case UPLOAD_ERR_NO_TMP_DIR:
			return __( 'The server is missing a temporary upload directory.', 'cc-svg-sprite-manager' );
		case UPLOAD_ERR_CANT_WRITE:
			return __( 'The server failed to write the uploaded file to disk.', 'cc-svg-sprite-manager' );
		case UPLOAD_ERR_EXTENSION:
			return __( 'A PHP extension stopped the upload.', 'cc-svg-sprite-manager' );
		default:
			return __( 'A file upload failed.', 'cc-svg-sprite-manager' );
	}
}

/**
 * Ensure the sprite directory exists.
 *
 * @param string|null $dir Optional directory.
 */
function cc_svg_sprite_manager_ensure_sprite_dir( $dir = null ) {
	$paths = cc_svg_sprite_manager_get_paths();
	$dir   = $dir ?: $paths['icon_dir'];

	if ( ! file_exists( $paths['sprite_dir'] ) ) {
		wp_mkdir_p( $paths['sprite_dir'] );
	}

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! file_exists( $paths['theme_assets_dir'] ) ) {
		wp_mkdir_p( $paths['theme_assets_dir'] );
	}
}

/**
 * Remove a directory recursively.
 *
 * @param string $dir Directory path.
 */
function cc_rrmdir( $dir ) {
	if ( ! file_exists( $dir ) ) {
		return;
	}

	foreach ( glob( trailingslashit( $dir ) . '*' ) as $file ) {
		is_dir( $file ) ? cc_rrmdir( $file ) : unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}

/**
 * Build an admin notice.
 *
 * @param string $message Notice message.
 * @param string $type Notice type.
 * @return string
 */
function cc_svg_sprite_manager_notice( $message, $type = 'success' ) {
	$class = 'success' === $type ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

	return '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Sanitize a symbol ID.
 *
 * @param mixed $symbol_id Raw symbol ID.
 * @return string
 */
function cc_svg_sprite_manager_sanitize_symbol_id( $symbol_id ) {
	$symbol_id = sanitize_title( (string) $symbol_id );

	if ( ! str_starts_with( $symbol_id, 'icon-' ) ) {
		return '';
	}

	return $symbol_id;
}

/**
 * Allow only the SVG elements and attributes used by generated sprites.
 *
 * @param string $svg SVG content.
 * @return string
 */
function cc_svg_sprite_manager_kses_svg_sprite( $svg ) {
	$allowed = array(
		'svg'      => array(
			'xmlns'       => true,
			'style'       => true,
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'viewBox'     => true,
			'aria-hidden' => true,
			'focusable'   => true,
		),
		'symbol'   => array(
			'id'      => true,
			'viewbox' => true,
			'viewBox' => true,
		),
		'g'        => array(
			'fill'              => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-miterlimit' => true,
			'clip-path'         => true,
			'transform'         => true,
			'opacity'           => true,
		),
		'path'     => array(
			'd'                 => true,
			'fill'              => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-miterlimit' => true,
			'opacity'           => true,
			'fill-rule'         => true,
			'clip-rule'         => true,
			'transform'         => true,
		),
		'circle'   => array(
			'cx'           => true,
			'cy'           => true,
			'r'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'opacity'      => true,
		),
		'rect'     => array(
			'x'            => true,
			'y'            => true,
			'width'        => true,
			'height'       => true,
			'rx'           => true,
			'ry'           => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'opacity'      => true,
			'transform'    => true,
		),
		'line'     => array(
			'x1'              => true,
			'y1'              => true,
			'x2'              => true,
			'y2'              => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'opacity'         => true,
		),
		'polyline' => array(
			'points'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'opacity'         => true,
		),
		'polygon'  => array(
			'points'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'opacity'         => true,
		),
		'ellipse'  => array(
			'cx'           => true,
			'cy'           => true,
			'rx'           => true,
			'ry'           => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'opacity'      => true,
		),
		'use'      => array(
			'href'       => true,
			'xlink:href' => true,
		),
	);

	return wp_kses( $svg, $allowed );
}

/**
 * SVG sprite generator.
 */
class CCSVGSprite {
	/**
	 * Directory containing source SVG files.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Constructor.
	 *
	 * @param string $dir Directory containing SVG files.
	 */
	public function __construct( $dir ) {
		$this->dir = $dir;
	}

	/**
	 * Generate a fresh sprite.
	 *
	 * @return string
	 */
	public function generate() {
		$symbols = array();

		foreach ( glob( trailingslashit( $this->dir ) . '*.svg' ) as $file ) {
			$id     = 'icon-' . sanitize_title( basename( $file, '.svg' ) );
			$symbol = $this->create_symbol_from_file( $file, $id );
			if ( '' !== $symbol ) {
				$symbols[ $id ] = $symbol;
			}
		}

		return $this->wrap_svg( $symbols );
	}

	/**
	 * Generate a sprite by merging with existing symbols.
	 *
	 * @param string $existing_svg Existing sprite path.
	 * @param string $dedupe_mode Duplicate handling mode.
	 * @return string
	 */
	public function generate_with_existing( $existing_svg, $dedupe_mode = 'replace' ) {
		$existing_symbols = array();

		if ( file_exists( $existing_svg ) ) {
			$svg = file_get_contents( $existing_svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $svg && preg_match_all( '/<symbol\b[^>]*id="([^"]+)"[^>]*>.*?<\/symbol>/is', $svg, $matches ) ) {
				foreach ( $matches[1] as $index => $id ) {
					$existing_symbols[ cc_svg_sprite_manager_sanitize_symbol_id( $id ) ] = $matches[0][ $index ];
				}
			}
		}

		foreach ( glob( trailingslashit( $this->dir ) . '*.svg' ) as $file ) {
			$base_id = 'icon-' . sanitize_title( basename( $file, '.svg' ) );
			$id      = $base_id;

			if ( isset( $existing_symbols[ $id ] ) ) {
				if ( 'skip' === $dedupe_mode ) {
					continue;
				}
				if ( 'rename' === $dedupe_mode ) {
					$i = 2;
					while ( isset( $existing_symbols[ $id = $base_id . '-' . $i ] ) ) {
						$i++;
					}
				}
			}

			$symbol = $this->create_symbol_from_file( $file, $id );
			if ( '' !== $symbol ) {
				$existing_symbols[ $id ] = $symbol;
			}
		}

		return $this->wrap_svg( $existing_symbols );
	}

	/**
	 * Convert one SVG file into a symbol.
	 *
	 * @param string $file SVG path.
	 * @param string $id Symbol ID.
	 * @return string
	 */
	private function create_symbol_from_file( $file, $id ) {
		$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $svg ) {
			return '';
		}

		if ( ! preg_match( '/<svg\b([^>]*)>(.*?)<\/svg>/is', $svg, $matches ) ) {
			return '';
		}

		$viewbox = '0 0 24 24';
		if ( preg_match( '/\sviewBox=("|\')([^"\']+)\1/i', $matches[1], $viewbox_match ) ) {
			$viewbox = $viewbox_match[2];
		}

		$inner_svg = trim( preg_replace( '/<(script|foreignObject)\b[^>]*>.*?<\/\1>/is', '', $matches[2] ) );

		return sprintf(
			'<symbol id="%1$s" viewBox="%2$s">%3$s</symbol>',
			esc_attr( $id ),
			esc_attr( $viewbox ),
			cc_svg_sprite_manager_kses_svg_sprite( $inner_svg )
		);
	}

	/**
	 * Wrap symbols in one hidden SVG sprite.
	 *
	 * @param array<string,string> $symbols Symbol markup.
	 * @return string
	 */
	private function wrap_svg( $symbols ) {
		return "<svg xmlns=\"http://www.w3.org/2000/svg\" style=\"display:none\">\n" . implode( "\n", array_filter( $symbols ) ) . "\n</svg>";
	}
}
