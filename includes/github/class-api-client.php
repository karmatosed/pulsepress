<?php
/**
 * GitHub REST API client.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\GitHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\Security;

/**
 * Minimal GitHub API wrapper.
 */
final class API_Client {

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_user() {
		return $this->request( 'GET', 'https://api.github.com/user' );
	}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function list_releases( string $owner, string $repo, int $per_page = 10 ) {
		if ( ! Security::is_valid_github_repo_part( $owner, $repo ) ) {
			return new \WP_Error( 'pulse_press_github_repo', __( 'Invalid repository.', 'pulse-press' ) );
		}

		$per_page = min( 100, max( 1, $per_page ) );

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases?per_page=%d',
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			$per_page
		);
		$result = $this->request( 'GET', $url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return is_array( $result ) ? $result : array();
	}

	/**
	 * @return array<string, mixed>|array<int, mixed>|\WP_Error
	 */
	private function request( string $method, string $url ) {
		$token = OAuth_Client::get_access_token();
		if ( '' === $token ) {
			return new \WP_Error( 'pulse_press_github_token', __( 'GitHub is not connected.', 'pulse-press' ) );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/vnd.github+json',
					'User-Agent'    => 'Pulse-Press-WordPress-Plugin',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) ? (string) ( $body['message'] ?? 'HTTP ' . $code ) : 'HTTP ' . $code;
			return new \WP_Error( 'pulse_press_github_api', $message );
		}

		return is_array( $body ) ? $body : array();
	}
}
