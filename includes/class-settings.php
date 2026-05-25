<?php
/**
 * Plugin settings helper.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

/**
 * Options API wrapper for pulse_press_settings.
 */
final class Settings {

	public const OPTION = 'pulse_press_settings';

	public static function set_defaults(): void {
		$current = get_option( self::OPTION, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$defaults = array(
			'slack_client_id'          => '',
			'slack_client_secret'      => '',
			'slack_token'              => '',
			'slack_team_id'            => '',
			'slack_user_id'            => '',
			'slack_connected_at'       => '',
			'slack_user_name'          => '',
			'team_channels'            => array(),
			'schedule_frequency'       => 'weekly',
			'schedule_weekday'         => 1,
			'schedule_time'            => '09:00',
			'team_period_days'         => 7,
			'meeting_default_channel'  => '',
			'meeting_thread_ts'        => '',
			'meeting_use_tags'         => false,
			'meeting_start_tag'        => '',
			'meeting_end_tag'          => '',
			'draft_author_id'          => (int) get_current_user_id(),
			'category_team_update'     => 0,
			'category_meeting_update'  => 0,
			'github_client_id'         => '',
			'github_client_secret'     => '',
			'github_token'             => '',
			'github_user_login'        => '',
			'github_connected_at'      => '',
			'github_repos'             => array(),
			'release_period_days'      => 7,
			'category_release_update'  => 0,
			'category_whats_new_in'    => 0,
			'category_agenda'          => 0,
			'template_overrides'       => array(),
			'run_log'                  => array(),
			'backoff_until'            => 0,
		);
		update_option( self::OPTION, wp_parse_args( $current, $defaults ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$defaults = array(
			'slack_client_id'          => '',
			'slack_client_secret'      => '',
			'slack_token'              => '',
			'slack_team_id'            => '',
			'slack_user_id'            => '',
			'slack_connected_at'       => '',
			'slack_user_name'          => '',
			'team_channels'            => array(),
			'schedule_frequency'       => 'weekly',
			'schedule_weekday'         => 1,
			'schedule_time'            => '09:00',
			'team_period_days'         => 7,
			'meeting_default_channel'  => '',
			'meeting_thread_ts'        => '',
			'meeting_use_tags'         => false,
			'meeting_start_tag'        => '',
			'meeting_end_tag'          => '',
			'draft_author_id'          => 0,
			'category_team_update'     => 0,
			'category_meeting_update'  => 0,
			'github_client_id'         => '',
			'github_client_secret'     => '',
			'github_token'             => '',
			'github_user_login'        => '',
			'github_connected_at'      => '',
			'github_repos'             => array(),
			'release_period_days'      => 7,
			'category_release_update'  => 0,
			'category_whats_new_in'    => 0,
			'category_agenda'          => 0,
			'template_overrides'       => array(),
			'run_log'                  => array(),
			'backoff_until'            => 0,
		);
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * @param array<string, mixed> $values Values to merge.
	 */
	public static function update( array $values ): void {
		$all = self::all();
		update_option( self::OPTION, array_merge( $all, $values ) );
	}

	public static function is_slack_connected(): bool {
		$token = self::get( 'slack_token', '' );
		return is_string( $token ) && '' !== $token;
	}

	/**
	 * Meeting digest boundary tags from settings.
	 *
	 * @return array{use_tags: bool, start_tag: string, end_tag: string}
	 */
	public static function meeting_boundary_options(): array {
		return array(
			'use_tags'  => (bool) self::get( 'meeting_use_tags', false ),
			'start_tag' => sanitize_text_field( (string) self::get( 'meeting_start_tag', '' ) ),
			'end_tag'   => sanitize_text_field( (string) self::get( 'meeting_end_tag', '' ) ),
		);
	}

	/**
	 * @param array<string, mixed> $post Raw POST overrides for a single run.
	 * @return array{use_tags: bool, start_tag: string, end_tag: string}
	 */
	public static function meeting_boundary_options_from_post( array $post ): array {
		$options = self::meeting_boundary_options();

		if ( array_key_exists( 'meeting_use_tags', $post ) ) {
			$options['use_tags'] = (bool) absint( $post['meeting_use_tags'] );
		}
		if ( isset( $post['meeting_start_tag'] ) ) {
			$options['start_tag'] = sanitize_text_field( (string) $post['meeting_start_tag'] );
		}
		if ( isset( $post['meeting_end_tag'] ) ) {
			$options['end_tag'] = sanitize_text_field( (string) $post['meeting_end_tag'] );
		}

		return $options;
	}

	public static function get_draft_author_id(): int {
		$id = (int) self::get( 'draft_author_id', 0 );
		if ( $id > 0 && Security::is_valid_draft_author( $id ) ) {
			return $id;
		}
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);
		return ! empty( $admins ) ? (int) $admins[0]->ID : 1;
	}

	/**
	 * @param string $status Status slug.
	 * @param string $message Human message.
	 * @param int    $post_id Draft post ID.
	 */
	public static function log_run( string $status, string $message, int $post_id = 0 ): void {
		$log   = self::get( 'run_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time'    => time(),
			'status'  => $status,
			'message' => $message,
			'post_id' => $post_id,
		);
		$log = array_slice( $log, -5 );
		self::update( array( 'run_log' => $log ) );
	}
}
