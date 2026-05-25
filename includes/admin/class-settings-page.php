<?php
/**
 * Admin menu and mount point.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Admin;

/**
 * Top-level Pulse Press admin page (React app).
 */
final class Settings_Page {

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
	}

	public static function add_menu(): void {
		add_menu_page(
			__( 'Pulse Press', 'pulse-press' ),
			__( 'Pulse Press', 'pulse-press' ),
			'edit_posts',
			'pulse-press',
			array( self::class, 'render' ),
			'dashicons-megaphone',
			58
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$asset_file = PULSE_PRESS_DIR . 'build/admin.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Pulse Press', 'pulse-press' ) . '</h1>';
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Admin assets are missing. Run npm install && npm run build in the plugin directory.', 'pulse-press' );
			echo '</p></div></div>';
			return;
		}

		echo '<div id="pulse-press-root" class="pulse-press-root"></div>';
	}
}
