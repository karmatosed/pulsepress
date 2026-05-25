<?php
/**
 * Fetches and formats Slack channel content.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Slack;

/**
 * Builds normalized text from Slack channels.
 */
final class Channel_Repository {

	/**
	 * @param string[] $channel_ids Channel IDs.
	 * @param int      $period_days Days to look back.
	 * @return array{content: string, channels: array<int, array<string, string>>}|\WP_Error
	 */
	public static function fetch_team_period( array $channel_ids, int $period_days ) {
		$api    = new API_Client();
		$latest = microtime( true );
		$oldest = $latest - ( max( 1, $period_days ) * DAY_IN_SECONDS );
		$parts  = array();
		$meta   = array();

		foreach ( $channel_ids as $channel_id ) {
			$channel_id = sanitize_text_field( $channel_id );
			if ( '' === $channel_id ) {
				continue;
			}
			$text = $api->fetch_channel_history_text( $channel_id, $oldest, $latest );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			if ( '' === $text ) {
				continue;
			}
			$meta[] = array(
				'id'   => $channel_id,
				'name' => $channel_id,
			);
			$parts[] = "## Channel {$channel_id}\n\n{$text}";
		}

		if ( empty( $parts ) ) {
			return new \WP_Error(
				'pulse_press_no_activity',
				__( 'No Slack messages found for the selected period.', 'pulse-press' )
			);
		}

		return array(
			'content'  => implode( "\n\n", $parts ),
			'channels' => $meta,
		);
	}

	/**
	 * @param array<string, mixed> $boundary_tags Optional use_tags, start_tag, end_tag.
	 * @return array{content: string, label: string}|\WP_Error
	 */
	public static function fetch_meeting( string $channel_id, int $period_days, string $thread_ts = '', array $boundary_tags = array() ) {
		$api    = new API_Client();
		$latest = microtime( true );
		$oldest = $latest - ( max( 1, $period_days ) * DAY_IN_SECONDS );

		if ( '' !== $thread_ts ) {
			$result = $api->request(
				'conversations.replies',
				array(
					'channel' => $channel_id,
					'ts'      => $thread_ts,
					'limit'   => 200,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$messages = array();
			foreach ( (array) ( $result['messages'] ?? array() ) as $msg ) {
				if ( is_array( $msg ) ) {
					$messages[] = $msg;
				}
			}
			$messages = Meeting_Boundary::filter_messages( $messages, $boundary_tags );
			$lines    = array();
			foreach ( $messages as $msg ) {
				$formatted = Message_Formatter::format_message( $msg, 0 );
				if ( '' !== $formatted ) {
					$lines[] = $formatted;
				}
			}
			$content = implode( "\n", $lines );
		} else {
			$content = $api->fetch_channel_history_text( $channel_id, $oldest, $latest, $boundary_tags );
			if ( is_wp_error( $content ) ) {
				return $content;
			}
		}

		if ( '' === $content ) {
			return new \WP_Error(
				'pulse_press_no_activity',
				__( 'No Slack messages found for this meeting.', 'pulse-press' )
			);
		}

		return array(
			'content' => $content,
			'label'   => $channel_id,
		);
	}
}
