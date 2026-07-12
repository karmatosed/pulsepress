<?php
/**
 * Content type profiles.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Posts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Registry of Pulse post format types.
 */
final class Post_Type_Registry {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_types(): array {
		$types = array(
			'team-update'     => array(
				'label'         => __( 'Team updates', 'pulse-press' ),
				/* translators: %s: date range for the digest period. */
				'title_pattern' => __( 'Team digest — %s', 'pulse-press' ),
				'uses_template' => false,
			),
			'meeting-update'  => array(
				'label'         => __( 'Meeting updates', 'pulse-press' ),
				/* translators: 1: meeting label, 2: date. */
				'title_pattern' => __( 'Meeting notes — %1$s — %2$s', 'pulse-press' ),
				'uses_template' => false,
			),
			'release-update'  => array(
				'label'         => __( 'Release announcements', 'pulse-press' ),
				/* translators: %s: date range for the release period. */
				'title_pattern' => __( 'Release announcements — %s', 'pulse-press' ),
				'uses_template' => true,
			),
			'whats-new-in'    => array(
				'label'         => __( "What's new in…", 'pulse-press' ),
				/* translators: 1: product name, 2: version, 3: date. */
				'title_pattern' => __( 'What\'s new in %1$s %2$s? (%3$s)', 'pulse-press' ),
				'uses_template' => true,
			),
			'agenda'          => array(
				'label'         => __( 'Agendas', 'pulse-press' ),
				/* translators: 1: agenda type label, 2: date. */
				'title_pattern' => __( '%1$s — %2$s', 'pulse-press' ),
				'uses_template' => true,
			),
		);

		/**
		 * Filter registered Pulse Press content types.
		 *
		 * @param array<string, array<string, mixed>> $types Types.
		 */
		return apply_filters( 'pulse_press_post_types', $types );
	}

	/**
	 * Agenda subtypes map to template slugs.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_agenda_subtypes(): array {
		$subtypes = array(
			'dev-chat'        => array(
				'label'    => __( 'Dev chat', 'pulse-press' ),
				'template' => 'agenda-dev-chat',
			),
			'core-ai'         => array(
				'label'    => __( 'Core AI office hours', 'pulse-press' ),
				'template' => 'agenda-core-ai',
			),
			'performance-chat' => array(
				'label'    => __( 'Performance chat', 'pulse-press' ),
				'template' => 'agenda-performance-chat',
			),
			'release-party'   => array(
				'label'    => __( 'Release party', 'pulse-press' ),
				'template' => 'agenda-release-party',
			),
			'custom'          => array(
				'label'    => __( 'Custom', 'pulse-press' ),
				'template' => 'agenda-custom',
			),
		);

		/**
		 * @param array<string, array<string, string>> $subtypes Subtypes.
		 */
		return apply_filters( 'pulse_press_agenda_subtypes', $subtypes );
	}

	public static function is_valid( string $type_id ): bool {
		return isset( self::get_types()[ $type_id ] );
	}

	public static function resolve_template_id( string $type_id, array $args = array() ): string {
		if ( 'agenda' === $type_id ) {
			$subtype = (string) ( $args['agenda_subtype'] ?? 'dev-chat' );
			$subtypes = self::get_agenda_subtypes();
			if ( isset( $subtypes[ $subtype ]['template'] ) ) {
				return $subtypes[ $subtype ]['template'];
			}
			return 'agenda-dev-chat';
		}
		return $type_id;
	}
}
