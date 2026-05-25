<?php
/**
 * Scheduled team digest.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Jobs;

use Pulse_Press\Settings;
use Pulse_Press\Slack\Channel_Repository;
use Pulse_Press\Slack\OAuth_Client;
use Pulse_Press\Posts\Pipeline;

/**
 * Cron handler for team updates.
 */
final class Digest_Cron {

	public const HOOK = 'pulse_press_run_team_update';

	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
	}

	public static function schedule(): void {
		self::unschedule();
		$frequency = (string) Settings::get( 'schedule_frequency', 'weekly' );
		$recurrence = 'weekly' === $frequency ? 'weekly' : 'daily';
		$timestamp  = self::next_run_timestamp();
		wp_schedule_event( $timestamp, $recurrence, self::HOOK );
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public static function next_run_timestamp(): int {
		$time     = (string) Settings::get( 'schedule_time', '09:00' );
		$parts    = explode( ':', $time );
		$hour     = isset( $parts[0] ) ? (int) $parts[0] : 9;
		$minute   = isset( $parts[1] ) ? (int) $parts[1] : 0;
		$weekday  = (int) Settings::get( 'schedule_weekday', 1 );
		$now      = current_time( 'timestamp' );
		$target   = mktime( $hour, $minute, 0, (int) gmdate( 'n', $now ), (int) gmdate( 'j', $now ), (int) gmdate( 'Y', $now ) );
		if ( 'weekly' === Settings::get( 'schedule_frequency', 'weekly' ) ) {
			while ( (int) gmdate( 'w', $target ) !== $weekday || $target <= $now ) {
				$target += DAY_IN_SECONDS;
			}
		} elseif ( $target <= $now ) {
			$target += DAY_IN_SECONDS;
		}
		return $target;
	}

	public static function run(): void {
		$backoff = (int) Settings::get( 'backoff_until', 0 );
		if ( $backoff > time() ) {
			return;
		}

		if ( ! OAuth_Client::get_access_token() ) {
			Settings::log_run( 'error', __( 'Slack not connected.', 'pulse-press' ) );
			return;
		}

		$result = self::run_team_digest();
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			if ( 'pulse_press_slack_rate_limit' === $code ) {
				Settings::update( array( 'backoff_until' => time() + HOUR_IN_SECONDS ) );
			}
			Settings::log_run( 'error', $result->get_error_message() );
			return;
		}

		Settings::log_run(
			'success',
			__( 'Team digest draft created.', 'pulse-press' ),
			$result
		);
	}

	/**
	 * @return int|\WP_Error Post ID.
	 */
	public static function run_team_digest() {
		wp_set_current_user( Settings::get_draft_author_id() );

		$channels = Settings::get( 'team_channels', array() );
		$channels = is_array( $channels ) ? $channels : array();
		if ( empty( $channels ) ) {
			return new \WP_Error( 'pulse_press_no_channels', __( 'No channels selected.', 'pulse-press' ) );
		}

		$days   = (int) Settings::get( 'team_period_days', 7 );
		$end    = gmdate( 'Y-m-d' );
		$start  = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$fetch  = Channel_Repository::fetch_team_period( $channels, $days );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}

		return Pipeline::run(
			'team-update',
			$fetch['content'],
			'slack',
			array(
				'context'       => sprintf(
					/* translators: 1: start date, 2: end date */
					__( 'Team digest period: %1$s to %2$s', 'pulse-press' ),
					$start,
					$end
				),
				'period_start'  => $start,
				'period_end'    => $end,
				'meta_extra'    => array(
					'period_start'   => $start,
					'period_end'     => $end,
					'slack_channels' => wp_json_encode( $fetch['channels'] ),
				),
			)
		);
	}

	/**
	 * @return int|\WP_Error
	 */
	public static function run_meeting_digest( string $channel_id, int $period_days, string $thread_ts = '', string $label = '' ) {
		wp_set_current_user( Settings::get_draft_author_id() );

		$fetch = Channel_Repository::fetch_meeting( $channel_id, $period_days, $thread_ts );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}

		$end = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . $period_days . ' days' ) );

		return Pipeline::run(
			'meeting-update',
			$fetch['content'],
			'slack',
			array(
				'label'        => '' !== $label ? $label : $fetch['label'],
				'period_start' => $start,
				'period_end'   => $end,
				'meta_extra'   => array(
					'period_start' => $start,
					'period_end'   => $end,
				),
			)
		);
	}
}
