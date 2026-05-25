<?php
/**
 * Uninstall Pulse Press.
 *
 * @package PulsePress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pulse_press_settings' );

wp_clear_scheduled_hook( 'pulse_press_run_team_update' );
