<?php
/**
 * Admin form actions.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\AI\AI_Gateway;
use Pulse_Press\Crypto;
use Pulse_Press\Jobs\Digest_Cron;
use Pulse_Press\Posts\Pipeline;
use Pulse_Press\Security;
use Pulse_Press\Settings;
use Pulse_Press\Posts\Post_Type_Registry;
use Pulse_Press\GitHub\API_Client as GitHub_API_Client;
use Pulse_Press\GitHub\OAuth_Client as GitHub_OAuth_Client;
use Pulse_Press\GitHub\Release_Repository;
use Pulse_Press\Slack\API_Client;
use Pulse_Press\Slack\OAuth_Client;

/**
 * Processes admin POST actions.
 */
final class Actions_Handler {

	public static function register(): void {
		add_action( 'admin_post_pulse_press_action', array( self::class, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
		}

		$action = isset( $_POST['pulse_press_action'] ) ? sanitize_key( wp_unslash( $_POST['pulse_press_action'] ) ) : '';
		check_admin_referer( 'pulse_press_action_' . $action );

		$tab      = isset( $_POST['pulse_press_tab'] ) ? sanitize_key( wp_unslash( $_POST['pulse_press_tab'] ) ) : '';
		$redirect = '' !== $tab ? \Pulse_Press\Admin_Url::page( $tab ) : \Pulse_Press\Admin_Url::page();

		switch ( $action ) {
			case 'save_settings':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				self::save_settings();
				$redirect = add_query_arg( array( 'tab' => 'team', 'pulse_press_notice' => 'saved' ), $redirect );
				break;

			case 'disconnect_github':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				GitHub_OAuth_Client::disconnect();
				$redirect = add_query_arg( array( 'tab' => 'connection', 'pulse_press_notice' => 'github_disconnected' ), $redirect );
				break;

			case 'disconnect_slack':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				OAuth_Client::disconnect();
				$redirect = add_query_arg( array( 'tab' => 'connection', 'pulse_press_notice' => 'disconnected' ), $redirect );
				break;

			case 'run_team':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$result = Digest_Cron::run_team_digest();
				if ( is_wp_error( $result ) ) {
					Settings::log_run( 'error', $result->get_error_message() );
					$redirect = add_query_arg(
						array(
							'tab'                  => 'run',
							'pulse_press_notice'   => 'error',
							'error_message'        => rawurlencode( $result->get_error_message() ),
						),
						$redirect
					);
				} else {
					Settings::log_run( 'success', __( 'Team digest draft created.', 'pulse-press' ), $result );
					$redirect = add_query_arg(
						array(
							'tab'                => 'run',
							'pulse_press_notice' => 'draft_created',
							'post_id'            => $result,
						),
						$redirect
					);
				}
				break;

			case 'run_meeting':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$channel = isset( $_POST['meeting_channel'] )
					? Security::sanitize_slack_channel_id( (string) wp_unslash( $_POST['meeting_channel'] ) )
					: '';
				$days     = isset( $_POST['meeting_period_days'] ) ? absint( $_POST['meeting_period_days'] ) : 1;
				$thread   = isset( $_POST['meeting_thread_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['meeting_thread_ts'] ) ) : '';
				$label    = isset( $_POST['meeting_label'] ) ? sanitize_text_field( wp_unslash( $_POST['meeting_label'] ) ) : '';
				$boundary = Settings::meeting_boundary_options_from_post( wp_unslash( $_POST ) );
				$result   = Digest_Cron::run_meeting_digest( $channel, max( 1, $days ), $thread, $label, $boundary );
				if ( is_wp_error( $result ) ) {
					$redirect = add_query_arg(
						array(
							'tab'                => 'run',
							'pulse_press_notice' => 'error',
							'error_message'      => rawurlencode( $result->get_error_message() ),
						),
						$redirect
					);
				} else {
					$redirect = add_query_arg(
						array(
							'tab'                => 'run',
							'pulse_press_notice' => 'draft_created',
							'post_id'            => $result,
						),
						$redirect
					);
				}
				break;

			case 'run_release':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$days    = isset( $_POST['release_period_days'] ) ? absint( $_POST['release_period_days'] ) : (int) Settings::get( 'release_period_days', 7 );
				$content = Release_Repository::fetch_since( max( 1, $days ) );
				if ( is_wp_error( $content ) ) {
					$result = $content;
				} else {
					$result = Pipeline::run(
						'release-update',
						$content,
						'github',
						array(
							'period_end'   => gmdate( 'Y-m-d' ),
							'period_start' => gmdate( 'Y-m-d', strtotime( '-' . max( 1, $days ) . ' days' ) ),
						)
					);
				}
				if ( is_wp_error( $result ) ) {
					$redirect = add_query_arg(
						array(
							'tab'                => 'run',
							'pulse_press_notice' => 'error',
							'error_message'      => rawurlencode( $result->get_error_message() ),
						),
						$redirect
					);
				} else {
					Settings::log_run( 'success', __( 'Release draft created.', 'pulse-press' ), $result );
					$redirect = add_query_arg(
						array(
							'tab'                => 'run',
							'pulse_press_notice' => 'draft_created',
							'post_id'            => $result,
						),
						$redirect
					);
				}
				break;

			case 'paste_draft':
				if ( ! current_user_can( 'edit_posts' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$type = isset( $_POST['paste_type'] ) ? sanitize_key( wp_unslash( $_POST['paste_type'] ) ) : 'team-update';
				if ( ! Post_Type_Registry::is_valid( $type ) ) {
					wp_die(
						esc_html__( 'Invalid content type.', 'pulse-press' ),
						esc_html__( 'Error', 'pulse-press' ),
						array( 'response' => 400 )
					);
				}
				$content = isset( $_POST['paste_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['paste_content'] ) ) : '';
				$label   = isset( $_POST['paste_label'] ) ? sanitize_text_field( wp_unslash( $_POST['paste_label'] ) ) : '';
				$title   = isset( $_POST['paste_title'] ) ? sanitize_text_field( wp_unslash( $_POST['paste_title'] ) ) : '';
				$context = isset( $_POST['paste_context'] ) ? sanitize_text_field( wp_unslash( $_POST['paste_context'] ) ) : '';

				$args = array( 'context' => $context );
				if ( '' !== $title ) {
					$args['title'] = $title;
				}
				if ( '' !== $label ) {
					$args['label'] = $label;
				}
				if ( isset( $_POST['paste_agenda_subtype'] ) ) {
					$subtype = sanitize_key( wp_unslash( $_POST['paste_agenda_subtype'] ) );
					if ( isset( Post_Type_Registry::get_agenda_subtypes()[ $subtype ] ) ) {
						$args['agenda_subtype'] = $subtype;
					}
				}
				if ( isset( $_POST['paste_product'] ) ) {
					$args['product'] = sanitize_text_field( wp_unslash( $_POST['paste_product'] ) );
				}
				if ( isset( $_POST['paste_version'] ) ) {
					$args['version'] = sanitize_text_field( wp_unslash( $_POST['paste_version'] ) );
				}
				if ( isset( $_POST['paste_date'] ) ) {
					$args['date'] = sanitize_text_field( wp_unslash( $_POST['paste_date'] ) );
				}

				$extra_context = self::build_paste_context( $type, $args );
				if ( '' !== $extra_context ) {
					$args['context'] = trim( $args['context'] . "\n" . $extra_context );
				}

				$result = Pipeline::run( $type, $content, 'paste', $args );
				if ( is_wp_error( $result ) ) {
					$redirect = add_query_arg(
						array(
							'tab'                => 'paste',
							'pulse_press_notice' => 'error',
							'error_message'      => rawurlencode( $result->get_error_message() ),
						),
						$redirect
					);
				} else {
					$redirect = add_query_arg(
						array(
							'tab'                => 'paste',
							'pulse_press_notice' => 'draft_created',
							'post_id'            => $result,
						),
						$redirect
					);
				}
				break;

			case 'test_github':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$test = ( new GitHub_API_Client() )->get_user();
				$redirect = add_query_arg(
					array(
						'tab'                => 'connection',
						'pulse_press_notice' => is_wp_error( $test ) ? 'error' : 'github_ok',
						'error_message'      => is_wp_error( $test ) ? rawurlencode( $test->get_error_message() ) : '',
					),
					$redirect
				);
				break;

			case 'test_slack':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$test = ( new API_Client() )->auth_test();
				$redirect = add_query_arg(
					array(
						'tab'                => 'connection',
						'pulse_press_notice' => is_wp_error( $test ) ? 'error' : 'slack_ok',
						'error_message'      => is_wp_error( $test ) ? rawurlencode( $test->get_error_message() ) : '',
					),
					$redirect
				);
				break;

			case 'test_ai':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Unauthorized.', 'pulse-press' ) );
				}
				$test = AI_Gateway::smoke_test();
				$redirect = add_query_arg(
					array(
						'tab'                => 'run',
						'pulse_press_notice' => is_wp_error( $test ) ? 'error' : 'ai_ok',
						'error_message'      => is_wp_error( $test ) ? rawurlencode( $test->get_error_message() ) : '',
					),
					$redirect
				);
				break;

			default:
				wp_die(
					esc_html__( 'Unknown action.', 'pulse-press' ),
					esc_html__( 'Error', 'pulse-press' ),
					array( 'response' => 400 )
				);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function save_settings(): void {
		$updates = array();

		if ( isset( $_POST['slack_client_id'] ) ) {
			$updates['slack_client_id'] = sanitize_text_field( wp_unslash( $_POST['slack_client_id'] ) );
		}
		if ( isset( $_POST['slack_client_secret'] ) ) {
			$secret = sanitize_text_field( wp_unslash( $_POST['slack_client_secret'] ) );
			if ( '' !== $secret ) {
				$updates['slack_client_secret'] = Crypto::encrypt( $secret );
			}
		}
		if ( isset( $_POST['team_channels'] ) ) {
			$updates['team_channels'] = Security::sanitize_team_channels( (array) wp_unslash( $_POST['team_channels'] ) );
		}
		if ( isset( $_POST['schedule_frequency'] ) ) {
			$updates['schedule_frequency'] = Security::sanitize_schedule_frequency(
				sanitize_key( wp_unslash( $_POST['schedule_frequency'] ) )
			);
		}
		if ( isset( $_POST['schedule_weekday'] ) ) {
			$updates['schedule_weekday'] = min( 6, max( 0, absint( $_POST['schedule_weekday'] ) ) );
		}
		if ( isset( $_POST['schedule_time'] ) ) {
			$updates['schedule_time'] = sanitize_text_field( wp_unslash( $_POST['schedule_time'] ) );
		}
		if ( isset( $_POST['team_period_days'] ) ) {
			$updates['team_period_days'] = max( 1, absint( $_POST['team_period_days'] ) );
		}
		if ( isset( $_POST['meeting_default_channel'] ) ) {
			$updates['meeting_default_channel'] = Security::sanitize_slack_channel_id(
				(string) wp_unslash( $_POST['meeting_default_channel'] )
			);
		}
		if ( isset( $_POST['meeting_thread_ts'] ) ) {
			$updates['meeting_thread_ts'] = sanitize_text_field( wp_unslash( $_POST['meeting_thread_ts'] ) );
		}
		if ( isset( $_POST['meeting_use_tags'] ) ) {
			$updates['meeting_use_tags'] = (bool) absint( $_POST['meeting_use_tags'] );
		}
		if ( isset( $_POST['meeting_start_tag'] ) ) {
			$updates['meeting_start_tag'] = sanitize_text_field( wp_unslash( $_POST['meeting_start_tag'] ) );
		}
		if ( isset( $_POST['meeting_end_tag'] ) ) {
			$updates['meeting_end_tag'] = sanitize_text_field( wp_unslash( $_POST['meeting_end_tag'] ) );
		}
		if ( isset( $_POST['draft_author_id'] ) ) {
			$author_id = absint( $_POST['draft_author_id'] );
			if ( Security::is_valid_draft_author( $author_id ) ) {
				$updates['draft_author_id'] = $author_id;
			}
		}
		if ( isset( $_POST['category_team_update'] ) ) {
			$updates['category_team_update'] = absint( $_POST['category_team_update'] );
		}
		if ( isset( $_POST['category_meeting_update'] ) ) {
			$updates['category_meeting_update'] = absint( $_POST['category_meeting_update'] );
		}
		if ( isset( $_POST['github_client_id'] ) ) {
			$updates['github_client_id'] = sanitize_text_field( wp_unslash( $_POST['github_client_id'] ) );
		}
		if ( isset( $_POST['github_client_secret'] ) ) {
			$secret = sanitize_text_field( wp_unslash( $_POST['github_client_secret'] ) );
			if ( '' !== $secret ) {
				$updates['github_client_secret'] = Crypto::encrypt( $secret );
			}
		}
		if ( isset( $_POST['github_repos'] ) ) {
			$updates['github_repos'] = Security::sanitize_github_repos(
				sanitize_textarea_field( wp_unslash( $_POST['github_repos'] ) )
			);
		}
		if ( isset( $_POST['release_period_days'] ) ) {
			$updates['release_period_days'] = max( 1, absint( $_POST['release_period_days'] ) );
		}
		if ( isset( $_POST['category_release_update'] ) ) {
			$updates['category_release_update'] = absint( $_POST['category_release_update'] );
		}
		if ( isset( $_POST['category_whats_new_in'] ) ) {
			$updates['category_whats_new_in'] = absint( $_POST['category_whats_new_in'] );
		}
		if ( isset( $_POST['category_agenda'] ) ) {
			$updates['category_agenda'] = absint( $_POST['category_agenda'] );
		}

		if ( ! empty( $updates ) ) {
			Settings::update( $updates );
		}

		if ( isset( $_POST['schedule_frequency'] ) || isset( $_POST['schedule_time'] ) || isset( $_POST['schedule_weekday'] ) ) {
			Digest_Cron::schedule();
		}
	}

	/**
	 * @param string               $type_id Type.
	 * @param array<string, mixed> $args    Paste args.
	 */
	private static function build_paste_context( string $type_id, array $args ): string {
		$parts = array();
		if ( 'whats-new-in' === $type_id ) {
			foreach ( array( 'product', 'version', 'date' ) as $key ) {
				if ( ! empty( $args[ $key ] ) ) {
					$parts[] = ucfirst( $key ) . ': ' . $args[ $key ];
				}
			}
		}
		if ( 'agenda' === $type_id && ! empty( $args['agenda_subtype'] ) ) {
			$parts[] = 'Agenda type: ' . $args['agenda_subtype'];
		}
		return implode( "\n", $parts );
	}
}
