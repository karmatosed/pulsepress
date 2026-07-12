<?php
/**
 * Plugin Name:       Pulse Press
 * Plugin URI:        https://github.com/karmatosed/pulsepress
 * Description:       Turn Slack, GitHub, and pasted content into formatted WordPress draft posts using WordPress AI.
 * Version:           1.1.1
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Pulse Press
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pulse-press
 *
 * @package PulsePress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PULSE_PRESS_VERSION', '1.1.1' );
define( 'PULSE_PRESS_FILE', __FILE__ );
define( 'PULSE_PRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PULSE_PRESS_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4-like autoloader for Pulse_Press namespace.
 *
 * @param string $class Class name.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Pulse_Press\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( '\\', '/', $relative ) );
		$parts    = explode( '/', $relative );
		$file     = array_pop( $parts );
		$path     = PULSE_PRESS_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$path .= implode( '/', $parts ) . '/';
		}
		$path .= 'class-' . str_replace( '_', '-', $file ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Plugin requirements check.
 */
function pulse_press_meets_requirements(): bool {
	// Do not compare to "7.0" — PHP treats 7.0-RC4 as older than 7.0. The AI Client is the real dependency.
	return function_exists( 'wp_ai_client_prompt' )
		&& function_exists( 'openssl_encrypt' );
}

/**
 * Admin notice when requirements are missing.
 */
function pulse_press_requirements_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$message = __( 'Pulse Press requires WordPress with the AI Client (7.0+) and OpenSSL.', 'pulse-press' );
	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( ! pulse_press_meets_requirements() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'Pulse Press could not be activated. This site needs the WordPress AI Client (bundled in WordPress 7.0).', 'pulse-press' ) );
		}
		Pulse_Press\Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Pulse_Press\Deactivator::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! pulse_press_meets_requirements() ) {
			add_action( 'admin_notices', 'pulse_press_requirements_notice' );
			return;
		}
		Pulse_Press\Plugin::instance()->init();
	}
);
