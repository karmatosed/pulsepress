<?php
/**
 * GitHub OAuth.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\GitHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\Crypto;
use Pulse_Press\Settings;

/**
 * OAuth authorize and token exchange.
 */
final class OAuth_Client {

	private const SCOPES = 'read:user,repo';

	public static function get_redirect_uri(): string {
		return add_query_arg(
			array(
				'page'                      => 'pulse-press',
				'tab'                       => 'connection',
				'pulse_press_github_oauth'  => '1',
			),
			admin_url( 'admin.php' )
		);
	}

	public static function get_authorize_url(): string {
		$client_id = (string) Settings::get( 'github_client_id', '' );
		if ( '' === $client_id ) {
			return '';
		}

		$secret = (string) Settings::get( 'github_client_secret', '' );
		if ( '' === $secret ) {
			return '';
		}

		$state = wp_create_nonce( 'pulse_press_github_oauth' );

		return 'https://github.com/login/oauth/authorize?' . http_build_query(
			array(
				'client_id'    => $client_id,
				'scope'       => self::SCOPES,
				'redirect_uri' => self::get_redirect_uri(),
				'state'       => $state,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function handle_callback( string $code, string $state ) {
		if ( ! wp_verify_nonce( $state, 'pulse_press_github_oauth' ) ) {
			return new \WP_Error( 'pulse_press_github_oauth_state', __( 'Invalid OAuth state.', 'pulse-press' ) );
		}

		$client_id     = (string) Settings::get( 'github_client_id', '' );
		$client_secret = Crypto::decrypt( (string) Settings::get( 'github_client_secret', '' ) );

		if ( '' === $client_id || '' === $client_secret ) {
			return new \WP_Error( 'pulse_press_github_oauth_config', __( 'GitHub app credentials are not configured.', 'pulse-press' ) );
		}

		$response = wp_remote_post(
			'https://github.com/login/oauth/access_token',
			array(
				'timeout' => 30,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => self::get_redirect_uri(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$error = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? 'unknown' ) : 'unknown';
			return new \WP_Error(
				'pulse_press_github_oauth_failed',
				sprintf(
					/* translators: %s: GitHub error */
					__( 'GitHub authorization failed: %s', 'pulse-press' ),
					$error
				)
			);
		}

		$token = (string) $body['access_token'];
		Settings::update(
			array(
				'github_token'        => Crypto::encrypt( $token ),
				'github_connected_at' => gmdate( 'c' ),
			)
		);

		$user = ( new API_Client() )->get_user();
		if ( ! is_wp_error( $user ) && ! empty( $user['login'] ) ) {
			Settings::update( array( 'github_user_login' => (string) $user['login'] ) );
		}

		return true;
	}

	public static function disconnect(): void {
		Settings::update(
			array(
				'github_token'        => '',
				'github_connected_at' => '',
				'github_user_login'   => '',
			)
		);
	}

	public static function get_access_token(): string {
		$stored = (string) Settings::get( 'github_token', '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = Crypto::decrypt( $stored );
		return $plain;
	}

	public static function is_connected(): bool {
		return '' !== self::get_access_token();
	}
}
