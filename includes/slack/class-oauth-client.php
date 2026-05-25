<?php
/**
 * Slack OAuth v2 (user token).
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Slack;

use Pulse_Press\Crypto;
use Pulse_Press\Settings;

/**
 * OAuth authorize and token exchange.
 */
final class OAuth_Client {

	private const USER_SCOPES = 'channels:read,groups:read,channels:history,groups:history,users:read,files:read';

	public static function get_redirect_uri(): string {
		return add_query_arg(
			array(
				'page'               => 'pulse-press',
				'tab'                => 'connection',
				'pulse_press_oauth'  => '1',
			),
			admin_url( 'admin.php' )
		);
	}

	public static function get_authorize_url(): string {
		$client_id = (string) Settings::get( 'slack_client_id', '' );
		if ( '' === $client_id ) {
			return '';
		}

		$secret = (string) Settings::get( 'slack_client_secret', '' );
		if ( '' === $secret ) {
			return '';
		}

		$state = wp_create_nonce( 'pulse_press_oauth' );

		// redirect_uri contains query args; must be encoded or Slack only receives the segment before "&".
		return 'https://slack.com/oauth/v2/authorize?' . http_build_query(
			array(
				'client_id'    => $client_id,
				'user_scope'   => self::USER_SCOPES,
				'redirect_uri' => self::get_redirect_uri(),
				'state'        => $state,
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
		if ( ! wp_verify_nonce( $state, 'pulse_press_oauth' ) ) {
			return new \WP_Error( 'pulse_press_oauth_state', __( 'Invalid OAuth state.', 'pulse-press' ) );
		}

		$client_id     = (string) Settings::get( 'slack_client_id', '' );
		$client_secret = Crypto::decrypt( (string) Settings::get( 'slack_client_secret', '' ) );
		if ( '' === $client_secret ) {
			$client_secret = (string) Settings::get( 'slack_client_secret', '' );
		}

		if ( '' === $client_id || '' === $client_secret ) {
			return new \WP_Error( 'pulse_press_oauth_config', __( 'Slack app credentials are not configured.', 'pulse-press' ) );
		}

		$response = wp_remote_post(
			'https://slack.com/api/oauth.v2.access',
			array(
				'timeout' => 30,
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
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$error = is_array( $body ) ? (string) ( $body['error'] ?? 'unknown' ) : 'unknown';
			return new \WP_Error(
				'pulse_press_oauth_failed',
				sprintf(
					/* translators: %s: Slack error code */
					__( 'Slack authorization failed: %s', 'pulse-press' ),
					$error
				)
			);
		}

		$user_token = (string) ( $body['authed_user']['access_token'] ?? '' );
		if ( '' === $user_token ) {
			return new \WP_Error( 'pulse_press_oauth_token', __( 'No user token returned from Slack.', 'pulse-press' ) );
		}

		Settings::update(
			array(
				'slack_token'        => Crypto::encrypt( $user_token ),
				'slack_team_id'      => (string) ( $body['team']['id'] ?? '' ),
				'slack_user_id'      => (string) ( $body['authed_user']['id'] ?? '' ),
				'slack_connected_at' => gmdate( 'c' ),
			)
		);

		$api = new API_Client();
		$user = $api->auth_test();
		if ( ! is_wp_error( $user ) && ! empty( $user['user'] ) ) {
			Settings::update( array( 'slack_user_name' => (string) $user['user'] ) );
		}

		return true;
	}

	public static function disconnect(): void {
		Settings::update(
			array(
				'slack_token'        => '',
				'slack_team_id'      => '',
				'slack_user_id'      => '',
				'slack_connected_at' => '',
				'slack_user_name'    => '',
			)
		);
	}

	public static function get_access_token(): string {
		$stored = (string) Settings::get( 'slack_token', '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = Crypto::decrypt( $stored );
		return '' !== $plain ? $plain : $stored;
	}

	public static function is_connected(): bool {
		return '' !== self::get_access_token();
	}
}
