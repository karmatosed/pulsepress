<?php
/**
 * REST config for admin UI.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Rest;

use Pulse_Press\AI\AI_Gateway;
use Pulse_Press\Posts\Post_Type_Registry;
use Pulse_Press\Settings;
use Pulse_Press\GitHub\OAuth_Client as GitHub_OAuth_Client;
use Pulse_Press\Slack\API_Client;
use Pulse_Press\Slack\OAuth_Client;
use Pulse_Press\Templates\Template_Registry;
use Pulse_Press\Admin_Url;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes read-only config for the React admin app.
 */
final class Config_Controller {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'pulse-press/v1',
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_config' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function get_config( WP_REST_Request $request ): WP_REST_Response {
		$can_manage = current_user_can( 'manage_options' );
		$settings   = Settings::all();

		$users = get_users(
			array(
				'capability' => 'edit_posts',
				'orderby'    => 'display_name',
				'fields'     => array( 'ID', 'display_name' ),
			)
		);

		$categories = get_categories(
			array(
				'hide_empty' => false,
			)
		);

		$slack_channels = array();
		if ( $can_manage && Settings::is_slack_connected() ) {
			$list = ( new API_Client() )->list_member_channels();
			if ( ! is_wp_error( $list ) ) {
				$slack_channels = $list;
			}
		}

		$secret_stored        = (string) ( $settings['slack_client_secret'] ?? '' );
		$github_secret_stored = (string) ( $settings['github_client_secret'] ?? '' );
		$github_repos         = (array) ( $settings['github_repos'] ?? array() );

		return new WP_REST_Response(
			array(
				'canManage'        => $can_manage,
				'aiAvailable'      => AI_Gateway::is_available(),
				'slackConnected'   => Settings::is_slack_connected(),
				'slackUserName'    => (string) ( $settings['slack_user_name'] ?? '' ),
				'slackAuthorize'   => $can_manage ? OAuth_Client::get_authorize_url() : '',
				'slackRedirect'    => OAuth_Client::get_redirect_uri(),
				'hasSecret'        => '' !== $secret_stored,
				'githubConnected'  => GitHub_OAuth_Client::is_connected(),
				'githubUserLogin'  => (string) ( $settings['github_user_login'] ?? '' ),
				'githubAuthorize'  => $can_manage ? GitHub_OAuth_Client::get_authorize_url() : '',
				'githubRedirect'   => GitHub_OAuth_Client::get_redirect_uri(),
				'hasGithubSecret'  => '' !== $github_secret_stored,
				'agendaSubtypes'   => Post_Type_Registry::get_agenda_subtypes(),
				'templateIds'      => Template_Registry::get_template_ids(),
				'settings'         => array(
					'slack_client_id'          => (string) ( $settings['slack_client_id'] ?? '' ),
					'github_client_id'         => (string) ( $settings['github_client_id'] ?? '' ),
					'github_repos'              => implode( "\n", $github_repos ),
					'team_channels'            => (array) ( $settings['team_channels'] ?? array() ),
					'schedule_frequency'       => (string) ( $settings['schedule_frequency'] ?? 'weekly' ),
					'schedule_weekday'         => (int) ( $settings['schedule_weekday'] ?? 1 ),
					'schedule_time'            => (string) ( $settings['schedule_time'] ?? '09:00' ),
					'team_period_days'         => (int) ( $settings['team_period_days'] ?? 7 ),
					'release_period_days'      => (int) ( $settings['release_period_days'] ?? 7 ),
					'meeting_default_channel'  => (string) ( $settings['meeting_default_channel'] ?? '' ),
					'meeting_thread_ts'        => (string) ( $settings['meeting_thread_ts'] ?? '' ),
					'meeting_use_tags'         => (bool) ( $settings['meeting_use_tags'] ?? false ),
					'meeting_start_tag'        => (string) ( $settings['meeting_start_tag'] ?? '' ),
					'meeting_end_tag'          => (string) ( $settings['meeting_end_tag'] ?? '' ),
					'draft_author_id'          => Settings::get_draft_author_id(),
					'category_team_update'     => (int) ( $settings['category_team_update'] ?? 0 ),
					'category_meeting_update'  => (int) ( $settings['category_meeting_update'] ?? 0 ),
					'category_release_update'  => (int) ( $settings['category_release_update'] ?? 0 ),
					'category_whats_new_in'    => (int) ( $settings['category_whats_new_in'] ?? 0 ),
					'category_agenda'          => (int) ( $settings['category_agenda'] ?? 0 ),
				),
				'slackChannels'  => $slack_channels,
				'users'          => array_map(
					static function ( $user ) {
						return array(
							'id'   => (int) $user->ID,
							'name' => $user->display_name,
						);
					},
					$users
				),
				'categories'     => array_map(
					static function ( $cat ) {
						return array(
							'id'   => (int) $cat->term_id,
							'name' => $cat->name,
						);
					},
					$categories
				),
				'types'          => Post_Type_Registry::get_types(),
				'weekdays'       => self::weekdays(),
				'runLog'         => array_reverse( (array) ( $settings['run_log'] ?? array() ) ),
				'urls'           => array(
					'adminPost' => admin_url( 'admin-post.php' ),
					'page'      => Admin_Url::page(),
					'aiSettings'=> admin_url( 'options-general.php?page=ai-settings' ),
				),
				'nonces'         => array(
					'save_settings'     => wp_create_nonce( 'pulse_press_action_save_settings' ),
					'disconnect_slack'  => wp_create_nonce( 'pulse_press_action_disconnect_slack' ),
					'disconnect_github' => wp_create_nonce( 'pulse_press_action_disconnect_github' ),
					'run_team'          => wp_create_nonce( 'pulse_press_action_run_team' ),
					'run_meeting'       => wp_create_nonce( 'pulse_press_action_run_meeting' ),
					'run_release'       => wp_create_nonce( 'pulse_press_action_run_release' ),
					'paste_draft'       => wp_create_nonce( 'pulse_press_action_paste_draft' ),
					'test_slack'        => wp_create_nonce( 'pulse_press_action_test_slack' ),
					'test_github'       => wp_create_nonce( 'pulse_press_action_test_github' ),
					'test_ai'           => wp_create_nonce( 'pulse_press_action_test_ai' ),
				),
			)
		);
	}

	/**
	 * @return array<int, array{value: int, label: string}>
	 */
	private static function weekdays(): array {
		global $wp_locale;
		$days = array();
		for ( $i = 0; $i <= 6; $i++ ) {
			$days[] = array(
				'value' => $i,
				'label' => $wp_locale->get_weekday( $i ),
			);
		}
		return $days;
	}
}
