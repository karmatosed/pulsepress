<?php
/**
 * OAuth callback handling.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Admin;

use Pulse_Press\GitHub\OAuth_Client as GitHub_OAuth;
use Pulse_Press\Slack\OAuth_Client as Slack_OAuth;

/**
 * Handles Slack and GitHub OAuth redirects.
 */
final class OAuth_Controller {

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_handle_callback' ) );
	}

	public static function maybe_handle_callback(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'pulse-press' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$oauth_error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $oauth_error && empty( $_GET['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$description = isset( $_GET['error_description'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				: $oauth_error;
			$result = new \WP_Error( 'pulse_press_oauth_denied', $description );
			$notice = ! empty( $_GET['pulse_press_github_oauth'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? 'github_oauth_error'
				: 'oauth_error';
			self::redirect_after_oauth( $result, $notice );
		}

		if ( empty( $_GET['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $_GET['pulse_press_github_oauth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result = GitHub_OAuth::handle_callback( $code, $state );
			$notice = is_wp_error( $result ) ? 'github_oauth_error' : 'github_oauth_success';
		} else {
			$result = Slack_OAuth::handle_callback( $code, $state );
			$notice = is_wp_error( $result ) ? 'oauth_error' : 'oauth_success';
		}

		self::redirect_after_oauth( $result, $notice );
	}

	/**
	 * @param true|\WP_Error $result OAuth result.
	 * @param string         $notice Admin notice key.
	 */
	private static function redirect_after_oauth( $result, string $notice ): void {
		$redirect = add_query_arg(
			array(
				'page'               => 'pulse-press',
				'tab'                => 'connection',
				'pulse_press_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);
		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg( 'error_message', rawurlencode( $result->get_error_message() ), $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}
