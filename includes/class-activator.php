<?php
/**
 * Activation routines.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Plugin activation.
 */
final class Activator {

	public static function activate(): void {
		self::register_taxonomy();
		Settings::set_defaults();
		Jobs\Digest_Cron::schedule();
		flush_rewrite_rules();
	}

	public static function register_taxonomy(): void {
		register_taxonomy(
			'pulse_press_type',
			array( 'post' ),
			array(
				'labels'            => array(
					'name'          => __( 'Pulse types', 'pulse-press' ),
					'singular_name' => __( 'Pulse type', 'pulse-press' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);

		self::ensure_terms();
	}

	public static function ensure_terms(): void {
		$types = Posts\Post_Type_Registry::get_types();
		foreach ( array_keys( $types ) as $slug ) {
			if ( ! term_exists( $slug, 'pulse_press_type' ) ) {
				wp_insert_term( $slug, 'pulse_press_type' );
			}
		}
	}
}
