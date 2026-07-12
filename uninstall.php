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

$terms = get_terms(
	array(
		'taxonomy'   => 'pulse_press_type',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( (int) $term_id, 'pulse_press_type' );
	}
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_pulse_press_' ) . '%'
	)
);
