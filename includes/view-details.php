<?php
/**
 * Plugin details modal shown from the Plugins screen.
 *
 * @package CCSVGSpriteManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'plugin_row_meta', 'cc_svg_sprite_manager_plugin_row_meta', 10, 2 );
add_action( 'admin_enqueue_scripts', 'cc_svg_sprite_manager_enqueue_plugin_details_assets' );
add_action( 'admin_footer-plugins.php', 'cc_svg_sprite_manager_render_plugin_details_modal' );

/**
 * Add a details link to this plugin row.
 *
 * @param array<int,string> $links Existing row metadata links.
 * @param string            $file  Current plugin file.
 * @return array<int,string>
 */
function cc_svg_sprite_manager_plugin_row_meta( $links, $file ) {
	$main_file = plugin_basename( CC_SVG_SPRITE_MANAGER_FILE );

	if ( $file !== $main_file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s">%3$s</a>',
		esc_url( '#TB_inline?width=640&height=520&inlineId=cc-plugin-modal-cc-svg-sprite-manager' ),
		esc_attr__( 'View CC SVG Sprite Manager details', 'cc-svg-sprite-manager' ),
		esc_html__( 'View Details', 'cc-svg-sprite-manager' )
	);

	return $links;
}

/**
 * Load ThickBox only on the Plugins screen.
 *
 * @param string $hook Current admin page hook.
 */
function cc_svg_sprite_manager_enqueue_plugin_details_assets( $hook ) {
	if ( 'plugins.php' !== $hook ) {
		return;
	}

	add_thickbox();
}

/**
 * Render the plugin details modal.
 */
function cc_svg_sprite_manager_render_plugin_details_modal() {
	?>
	<div id="cc-plugin-modal-cc-svg-sprite-manager" style="display:none;">
		<div class="cc-plugin-modal">
			<h2><?php esc_html_e( 'CC SVG Sprite Manager', 'cc-svg-sprite-manager' ); ?></h2>
			<p><?php esc_html_e( 'Upload validated SVG icons, generate cc-icons-sprite.svg in the plugin directory, and keep a copy in the active theme assets/images directory.', 'cc-svg-sprite-manager' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Supports up to 100 validated SVG files per selection.', 'cc-svg-sprite-manager' ); ?></li>
				<li><?php esc_html_e( 'Keeps uploaded source SVG files in the plugin icon directory.', 'cc-svg-sprite-manager' ); ?></li>
				<li><?php esc_html_e( 'Uses filename-based icon IDs with overwrite, skip, or rename handling.', 'cc-svg-sprite-manager' ); ?></li>
			</ul>
		</div>
	</div>
	<?php
}
