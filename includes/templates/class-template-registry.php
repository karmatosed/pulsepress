<?php
/**
 * Block markup templates (defaults + overrides).
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Templates;

use Pulse_Press\Settings;

/**
 * Loads editable block templates with {{placeholders}}.
 */
final class Template_Registry {

	public static function get_template_ids(): array {
		return array(
			'release-update',
			'whats-new-in',
			'agenda-dev-chat',
			'agenda-core-ai',
			'agenda-performance-chat',
			'agenda-release-party',
			'agenda-custom',
		);
	}

	public static function get_default( string $template_id ): string {
		$path = PULSE_PRESS_DIR . 'templates/' . $template_id . '.html';
		if ( is_readable( $path ) ) {
			return (string) file_get_contents( $path );
		}
		return '';
	}

	public static function get( string $template_id ): string {
		$overrides = Settings::get( 'template_overrides', array() );
		if ( is_array( $overrides ) && ! empty( $overrides[ $template_id ] ) ) {
			return (string) $overrides[ $template_id ];
		}
		return self::get_default( $template_id );
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_all_for_admin(): array {
		$out = array();
		foreach ( self::get_template_ids() as $id ) {
			$out[ $id ] = self::get( $id );
		}
		return $out;
	}

	/**
	 * Sample JSON for template preview (no AI).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_sample_data( string $template_id ): array {
		$samples = array(
			'release-update' => array(
				'summary'  => __( 'Weekly release roundup for internal and Make posts.', 'pulse-press' ),
				'releases' => array(
					array(
						'name'    => 'Gutenberg',
						'version' => '23.1',
						'date'    => '2026-05-07',
						'body'    => 'New experiments for taxonomies and media editor. https://github.com/WordPress/gutenberg/releases/tag/v23.1.0',
						'url'     => 'https://github.com/WordPress/gutenberg/releases/tag/v23.1.0',
					),
				),
				'notes'    => __( 'Test on staging before announcing.', 'pulse-press' ),
				'links'    => array( 'https://make.wordpress.org/core/' ),
			),
			'whats-new-in'   => array(
				'product'      => 'Gutenberg',
				'version'      => '23.1',
				'date'         => '07 May',
				'intro'        => __( 'Biweekly roundup of editor changes.', 'pulse-press' ),
				'overview'     => __( 'Parallel thumbnail uploads and new @wordpress/ui primitives.', 'pulse-press' ),
				'highlights'   => array(
					array(
						'title' => __( 'Faster image upload finalization', 'pulse-press' ),
						'body'  => __( 'Thumbnail sideloads run in parallel. #75888', 'pulse-press' ),
					),
				),
				'other_highlights' => array( __( 'Hide classic block experiment.', 'pulse-press' ) ),
				'changelog'    => __( 'Features, enhancements, and bug fixes from the release notes.', 'pulse-press' ),
				'contributors' => __( '@alice, @bob', 'pulse-press' ),
			),
		);

		if ( isset( $samples[ $template_id ] ) ) {
			return $samples[ $template_id ];
		}

		if ( 0 === strpos( $template_id, 'agenda-' ) ) {
			return array(
				'meeting_when'  => __( 'Wednesday, 15:00 UTC', 'pulse-press' ),
				'meeting_where' => __( '#core on Make WordPress Slack', 'pulse-press' ),
				'announcements' => array( __( 'WordPress 7.0 RC3 scheduled for Friday.', 'pulse-press' ) ),
				'discussions'   => array(
					array(
						'title' => __( 'Block handbook from block.json', 'pulse-press' ),
						'body'  => __( 'Feedback on auto-generated docs.', 'pulse-press' ),
					),
				),
				'open_floor'    => array( __( 'Tickets in the milestone prioritized.', 'pulse-press' ) ),
				'links'         => array( 'https://make.wordpress.org/core/' ),
			);
		}

		return array();
	}
}
