<?php
/**
 * Security helpers for Pulse Press.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\Slack\API_Client;

/**
 * Validation and sanitization for settings and external identifiers.
 */
final class Security {

	public const MAX_TEMPLATE_BYTES = 512000;

	/**
	 * @param int $user_id User ID.
	 */
	public static function is_valid_draft_author( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}
		return user_can( $user, 'edit_posts' );
	}

	/**
	 * Slack channel IDs use C (public) or G (private channel) prefixes.
	 * Direct message conversations use D and are not supported.
	 *
	 * @param string $channel_id Slack conversation ID.
	 */
	public static function is_slack_channel_id_format( string $channel_id ): bool {
		return '' !== $channel_id && (bool) preg_match( '/^[CG][A-Z0-9]+$/', $channel_id );
	}

	/**
	 * @return string[] Member channel IDs from Slack (excludes direct messages).
	 */
	public static function allowed_slack_channel_ids(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache = array();
		if ( ! Settings::is_slack_connected() ) {
			return $cache;
		}

		$list = ( new API_Client() )->list_member_channels();
		if ( is_wp_error( $list ) ) {
			return $cache;
		}

		foreach ( $list as $channel ) {
			if ( ! empty( $channel['id'] ) ) {
				$cache[] = (string) $channel['id'];
			}
		}

		return $cache;
	}

	/**
	 * @param string $channel_id Slack channel ID.
	 */
	public static function is_allowed_slack_channel_id( string $channel_id ): bool {
		if ( ! self::is_slack_channel_id_format( $channel_id ) ) {
			return false;
		}

		if ( ! Settings::is_slack_connected() ) {
			return false;
		}

		return in_array( $channel_id, self::allowed_slack_channel_ids(), true );
	}

	/**
	 * @param string[] $channel_ids Slack channel IDs from admin form.
	 * @return string[]
	 */
	public static function sanitize_team_channels( array $channel_ids ): array {
		$channel_ids = array_values(
			array_filter(
				array_map(
					static function ( $id ): string {
						return sanitize_text_field( (string) $id );
					},
					$channel_ids
				),
				array( self::class, 'is_slack_channel_id_format' )
			)
		);

		if ( ! Settings::is_slack_connected() || empty( $channel_ids ) ) {
			return array();
		}

		$allowed = self::allowed_slack_channel_ids();
		if ( empty( $allowed ) ) {
			return array();
		}

		return array_values( array_intersect( $channel_ids, $allowed ) );
	}

	/**
	 * @param string $channel_id Slack channel ID.
	 */
	public static function sanitize_slack_channel_id( string $channel_id ): string {
		$channels = self::sanitize_team_channels( array( $channel_id ) );
		return $channels[0] ?? '';
	}

	/**
	 * @param string $repos_text Multiline owner/repo list.
	 * @return string[]
	 */
	public static function sanitize_github_repos( string $repos_text ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $repos_text ) ?: array();
		$out   = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '#^[a-zA-Z0-9](?:[a-zA-Z0-9._-]*[a-zA-Z0-9])?/[a-zA-Z0-9](?:[a-zA-Z0-9._-]*[a-zA-Z0-9])?$#', $line ) ) {
				$out[] = $line;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string $owner Repo owner.
	 * @param string $repo  Repo name.
	 */
	public static function is_valid_github_repo_part( string $owner, string $repo ): bool {
		$pattern = '/^[a-zA-Z0-9](?:[a-zA-Z0-9._-]*[a-zA-Z0-9])?$/';
		return (bool) preg_match( $pattern, $owner ) && (bool) preg_match( $pattern, $repo );
	}

	/**
	 * @param string $frequency Schedule frequency.
	 */
	public static function sanitize_schedule_frequency( string $frequency ): string {
		return in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'weekly';
	}

	/**
	 * @param array<string, mixed> $meta Meta values from pipeline.
	 * @return array<string, string>
	 */
	public static function sanitize_post_meta( array $meta ): array {
		$out = array();
		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$out[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}

	/**
	 * @param bool $can_manage User can manage_options.
	 * @param bool $can_edit   User can edit_posts.
	 * @return array<string, string>
	 */
	public static function admin_nonces( bool $can_manage, bool $can_edit ): array {
		$nonces = array();
		if ( $can_edit ) {
			$nonces['paste_draft'] = wp_create_nonce( 'pulse_press_action_paste_draft' );
		}
		if ( $can_manage ) {
			$nonces['save_settings']     = wp_create_nonce( 'pulse_press_action_save_settings' );
			$nonces['disconnect_slack']  = wp_create_nonce( 'pulse_press_action_disconnect_slack' );
			$nonces['disconnect_github'] = wp_create_nonce( 'pulse_press_action_disconnect_github' );
			$nonces['run_team']          = wp_create_nonce( 'pulse_press_action_run_team' );
			$nonces['run_meeting']       = wp_create_nonce( 'pulse_press_action_run_meeting' );
			$nonces['run_release']       = wp_create_nonce( 'pulse_press_action_run_release' );
			$nonces['test_slack']        = wp_create_nonce( 'pulse_press_action_test_slack' );
			$nonces['test_github']       = wp_create_nonce( 'pulse_press_action_test_github' );
			$nonces['test_ai']           = wp_create_nonce( 'pulse_press_action_test_ai' );
		}
		return $nonces;
	}
}
