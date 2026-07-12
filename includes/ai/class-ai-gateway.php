<?php
/**
 * WordPress AI Client wrapper.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Calls wp_ai_client_prompt for structured summaries.
 */
final class AI_Gateway {

	/** Default HTTP timeout (seconds) for Anthropic/OpenAI via WordPress AI Client. Core default is 30. */
	private const DEFAULT_REQUEST_TIMEOUT = 180.0;

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

		self::extend_php_time_limit( $type_id, $content );

		$built   = Prompt_Factory::build( $type_id, $content, $context );
		$timeout = self::request_timeout_seconds( $type_id, $content );
		$prompt  = wp_ai_client_prompt( $built['prompt'] )
			->as_json_response( $built['schema'] );

		$prompt = self::apply_request_timeout( $prompt, $timeout );

		$json = $prompt->generate_text();

		if ( is_wp_error( $json ) ) {
			return self::normalize_error( $json );
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

	/**
	 * @param object $prompt WP_AI_Client_Prompt_Builder instance.
	 * @param float  $timeout Seconds.
	 * @return object
	 */
	private static function apply_request_timeout( object $prompt, float $timeout ) {
		if ( ! class_exists( '\WordPress\AiClient\Providers\Http\DTO\RequestOptions' ) ) {
			return $prompt;
		}

		$options_class = '\WordPress\AiClient\Providers\Http\DTO\RequestOptions';

		return $prompt->using_request_options(
			$options_class::fromArray(
				array(
					$options_class::KEY_TIMEOUT => $timeout,
				)
			)
		);
	}

	private static function request_timeout_seconds( string $type_id, string $content ): float {
		/**
		 * HTTP timeout in seconds for Pulse Press AI requests (Slack/GitHub digests can be large).
		 *
		 * @param float  $timeout  Default 180 seconds.
		 * @param string $type_id  Content type slug.
		 * @param string $content  Source text sent to the model.
		 */
		$timeout = apply_filters( 'pulse_press_ai_request_timeout', self::DEFAULT_REQUEST_TIMEOUT, $type_id, $content );

		return max( 30.0, (float) $timeout );
	}

	private static function extend_php_time_limit( string $type_id, string $content ): void {
		if ( ! function_exists( 'set_time_limit' ) ) {
			return;
		}

		/**
		 * Max PHP execution time (seconds) while Pulse Press runs AI + fetch. 0 = no change.
		 *
		 * @param int    $seconds Default 300.
		 * @param string $type_id Content type.
		 * @param string $content Source text.
		 */
		$seconds = (int) apply_filters( 'pulse_press_ai_max_execution_time', 300, $type_id, $content );
		if ( $seconds > 0 ) {
			set_time_limit( $seconds );
		}
	}

	private static function normalize_error( \WP_Error $error ): \WP_Error {
		$message = $error->get_error_message();
		if (
			str_contains( strtolower( $message ), 'curl error 28' )
			|| str_contains( strtolower( $message ), 'timed out' )
			|| str_contains( strtolower( $message ), 'timeout' )
		) {
			return new \WP_Error(
				'pulse_press_ai_timeout',
				__(
					'The AI request timed out. Try fewer days of Slack history, meeting start/finish tags, or ask your host to allow longer HTTP timeouts. Developers can raise the limit with the pulse_press_ai_request_timeout filter.',
					'pulse-press'
				),
				array(
					'status'       => 504,
					'original'     => $message,
				)
			);
		}

		return $error;
	}
}
