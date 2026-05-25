<?php
/**
 * Admin scripts and styles.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Admin;

/**
 * Enqueues React admin bundle and WPDS-related styles.
 */
final class Admin_Assets {

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
	}

	public static function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_pulse-press' === $screen->id ) {
			$classes .= ' pulse-press-admin-page';
		}
		return $classes;
	}

	public static function enqueue( string $hook ): void {
		if ( 'toplevel_page_pulse-press' !== $hook ) {
			return;
		}

		$asset_file = PULSE_PRESS_DIR . 'build/admin.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			'pulse-press-admin',
			PULSE_PRESS_URL . 'build/style-admin.css',
			array( 'wp-components' ),
			$asset['version'] ?? PULSE_PRESS_VERSION
		);

		$dependencies = array_merge(
			(array) ( $asset['dependencies'] ?? array() ),
			array( 'wp-api-fetch' )
		);

		wp_enqueue_script(
			'pulse-press-admin',
			PULSE_PRESS_URL . 'build/admin.js',
			array_values( array_unique( $dependencies ) ),
			$asset['version'] ?? PULSE_PRESS_VERSION,
			true
		);

		wp_set_script_translations( 'pulse-press-admin', 'pulse-press', PULSE_PRESS_DIR . 'languages' );

		wp_localize_script(
			'pulse-press-admin',
			'pulsePressAdmin',
			array(
				'restRoot'    => esc_url_raw( rest_url() ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'adminPostUrl'=> admin_url( 'admin-post.php' ),
				'initialTab'  => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}
}
