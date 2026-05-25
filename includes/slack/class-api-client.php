<?php
/**
 * Slack Web API client.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Slack;

/**
 * Minimal Slack API wrapper.
 */
final class API_Client {

	/**
	 * @param array<string, mixed> $args Query/body args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function request( string $method, array $args = array() ) {
		$token = OAuth_Client::get_access_token();
		if ( '' === $token ) {
			return new \WP_Error( 'pulse_press_slack_disconnected', __( 'Slack is not connected.', 'pulse-press' ) );
		}

		$url      = 'https://slack.com/api/' . $method;
		$response = wp_remote_get(
			add_query_arg( $args, $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			return new \WP_Error( 'pulse_press_slack_rate_limit', __( 'Slack rate limit reached. Try again later.', 'pulse-press' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$error = is_array( $body ) ? (string) ( $body['error'] ?? 'unknown' ) : 'unknown';
			return new \WP_Error(
				'pulse_press_slack_api',
				sprintf(
					/* translators: %s: Slack error */
					__( 'Slack API error: %s', 'pulse-press' ),
					$error
				)
			);
		}

		return $body;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function auth_test() {
		return $this->request( 'auth.test' );
	}

	/**
	 * @return array<int, array<string, string>>|\WP_Error
	 */
	public function list_member_channels() {
		$channels = array();
		$types    = array(
			'public_channel'  => 'channels',
			'private_channel' => 'groups',
		);

		foreach ( $types as $type => $key ) {
			$cursor = '';
			do {
				$args = array(
					'types'            => $type,
					'exclude_archived' => true,
					'limit'            => 200,
				);
				if ( '' !== $cursor ) {
					$args['cursor'] = $cursor;
				}
				$result = $this->request( 'users.conversations', $args );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				foreach ( (array) ( $result[ $key ] ?? array() ) as $ch ) {
					if ( ! is_array( $ch ) || empty( $ch['id'] ) ) {
						continue;
					}
					$channels[ (string) $ch['id'] ] = array(
						'id'   => (string) $ch['id'],
						'name' => (string) ( $ch['name'] ?? $ch['id'] ),
					);
				}
				$cursor = (string) ( $result['response_metadata']['next_cursor'] ?? '' );
			} while ( '' !== $cursor );
		}

		usort(
			$channels,
			static function ( array $a, array $b ): int {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return array_values( $channels );
	}

	/**
	 * @return string|\WP_Error
	 */
	/**
	 * @param array<string, mixed> $boundary_tags Optional use_tags, start_tag, end_tag.
	 * @return string|\WP_Error
	 */
	public function fetch_channel_history_text( string $channel_id, float $oldest, float $latest, array $boundary_tags = array() ): string {
		$raw_messages = $this->fetch_all_history_messages( $channel_id, $oldest, $latest );
		if ( is_wp_error( $raw_messages ) ) {
			return $raw_messages;
		}

		$raw_messages = Meeting_Boundary::filter_messages( $raw_messages, $boundary_tags );

		$thread_parents = array();
		foreach ( $raw_messages as $msg ) {
			if ( ! is_array( $msg ) ) {
				continue;
			}
			$reply_count = (int) ( $msg['reply_count'] ?? 0 );
			$ts          = (string) ( $msg['ts'] ?? '' );
			if ( $reply_count > 0 && '' !== $ts ) {
				$thread_parents[ $ts ] = true;
			}
		}

		$lines = array();
		foreach ( $raw_messages as $msg ) {
			if ( ! is_array( $msg ) ) {
				continue;
			}
			if ( ! empty( $msg['thread_ts'] ) && (string) $msg['thread_ts'] !== (string) ( $msg['ts'] ?? '' ) ) {
				continue;
			}

			$line = $this->format_message_line( $msg );
			if ( '' !== $line ) {
				$lines[] = $line;
			}

			$ts = (string) ( $msg['ts'] ?? '' );
			if ( '' !== $ts && isset( $thread_parents[ $ts ] ) ) {
				$replies = $this->fetch_thread_replies( $channel_id, $ts, $oldest, $latest );
				if ( is_wp_error( $replies ) ) {
					return $replies;
				}
				foreach ( $replies as $reply ) {
					if ( ! is_array( $reply ) ) {
						continue;
					}
					$reply_line = $this->format_message_line( $reply, 1 );
					if ( '' !== $reply_line ) {
						$lines[] = $reply_line;
					}
				}
			}
		}

		if ( empty( $lines ) ) {
			return '';
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * @return array<int, mixed>|\WP_Error
	 */
	private function fetch_all_history_messages( string $channel_id, float $oldest, float $latest ) {
		$messages = array();
		$cursor   = '';

		do {
			$args = array(
				'channel' => $channel_id,
				'oldest'  => (string) $oldest,
				'latest'  => (string) $latest,
				'limit'   => 200,
			);
			if ( '' !== $cursor ) {
				$args['cursor'] = $cursor;
			}

			$result = $this->request( 'conversations.history', $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( (array) ( $result['messages'] ?? array() ) as $msg ) {
				if ( is_array( $msg ) ) {
					$messages[] = $msg;
				}
			}

			$cursor = (string) ( $result['response_metadata']['next_cursor'] ?? '' );
			if ( '' !== $cursor ) {
				sleep( 1 );
			}
		} while ( '' !== $cursor );

		return array_reverse( $messages );
	}

	/**
	 * @return array<int, mixed>|\WP_Error
	 */
	private function fetch_thread_replies( string $channel_id, string $thread_ts, float $oldest, float $latest ) {
		$replies = array();
		$cursor  = '';

		do {
			$args = array(
				'channel' => $channel_id,
				'ts'      => $thread_ts,
				'oldest'  => (string) $oldest,
				'latest'  => (string) $latest,
				'limit'   => 200,
			);
			if ( '' !== $cursor ) {
				$args['cursor'] = $cursor;
			}

			$result = $this->request( 'conversations.replies', $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( (array) ( $result['messages'] ?? array() ) as $msg ) {
				if ( ! is_array( $msg ) ) {
					continue;
				}
				if ( (string) ( $msg['ts'] ?? '' ) === $thread_ts ) {
					continue;
				}
				$replies[] = $msg;
			}

			$cursor = (string) ( $result['response_metadata']['next_cursor'] ?? '' );
			if ( '' !== $cursor ) {
				sleep( 1 );
			}
		} while ( '' !== $cursor );

		return $replies;
	}

	/**
	 * @param array<string, mixed> $msg   Slack message.
	 * @param int                  $depth Thread depth.
	 */
	private function format_message_line( array $msg, int $depth = 0 ): string {
		$body = Message_Formatter::format_message( $msg, $depth );
		if ( '' === $body ) {
			return '';
		}

		$ts   = (string) ( $msg['ts'] ?? '' );
		$user = (string) ( $msg['user'] ?? $msg['username'] ?? $msg['bot_id'] ?? 'unknown' );

		return sprintf( '[%s] %s:%s%s', $ts, $user, "\n", $body );
	}
}
