<?php
/**
 * WordPress AI Client wrapper.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\AI;

/**
 * Calls wp_ai_client_prompt for structured summaries.
 */
final class AI_Gateway {

	public static function is_available(): bool {
		return function_exists( 'wp_ai_client_prompt' )
			&& function_exists( 'wp_supports_ai' )
			&& wp_supports_ai();
	}

	/**
	 * @param string $type_id Content type.
	 * @param string $content Source text.
	 * @param string $context Extra context.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function summarize( string $type_id, string $content, string $context = '' ) {
		if ( ! self::is_available() ) {
			return new \WP_Error(
				'pulse_press_ai_unavailable',
				__( 'WordPress AI is not configured. Set up a provider under Settings → AI.', 'pulse-press' )
			);
		}

		$built  = Prompt_Factory::build( $type_id, $content, $context );
		$json   = wp_ai_client_prompt( $built['prompt'] )
			->as_json_response( $built['schema'] )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Pulse Press: invalid AI JSON response.' );
			}
			return new \WP_Error(
				'pulse_press_ai_invalid_json',
				__( 'AI returned an invalid response. Try again.', 'pulse-press' )
			);
		}

		return $data;
	}

	/**
	 * Minimal smoke test.
	 *
	 * @return true|\WP_Error
	 */
	public static function smoke_test() {
		$result = self::summarize(
			'team-update',
			__( 'Monday: shipped feature X. Tuesday: fixed bug Y.', 'pulse-press' ),
			__( 'Smoke test only.', 'pulse-press' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}
}
