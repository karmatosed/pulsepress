<?php
/**
 * Unified content pipeline.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Posts;

use Pulse_Press\AI\AI_Gateway;
use Pulse_Press\Settings;
use Pulse_Press\Templates\Template_Renderer;

/**
 * Source → AI → blocks → draft.
 */
final class Pipeline {

	/**
	 * @param string               $type_id Content type.
	 * @param string               $content Raw text.
	 * @param string               $source  slack|paste|github.
	 * @param array<string, mixed> $args    title, context, meta_extra, agenda_subtype, product, version, date.
	 * @return int|\WP_Error
	 */
	public static function run( string $type_id, string $content, string $source, array $args = array() ) {
		$content = trim( $content );
		if ( '' === $content ) {
			return new \WP_Error(
				'pulse_press_empty_content',
				__( 'No content to summarize.', 'pulse-press' )
			);
		}

		$context = (string) ( $args['context'] ?? '' );
		$data    = AI_Gateway::summarize( $type_id, $content, $context );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$blocks = self::render_blocks( $type_id, $data, $args );
		$title  = (string) ( $args['title'] ?? self::default_title( $type_id, $data, $args ) );

		$meta = (array) ( $args['meta_extra'] ?? array() );

		return Draft_Creator::create( $type_id, $title, $blocks, $source, $meta );
	}

	/**
	 * @param string               $type_id Type.
	 * @param array<string, mixed> $data    AI JSON.
	 * @param array<string, mixed> $args    Args.
	 */
	private static function render_blocks( string $type_id, array $data, array $args ): string {
		$types = Post_Type_Registry::get_types();
		if ( ! empty( $types[ $type_id ]['uses_template'] ) ) {
			$template_id = Post_Type_Registry::resolve_template_id( $type_id, $args );
			$flat        = array();
			if ( 'whats-new-in' === $type_id ) {
				$flat['version'] = (string) ( $data['version'] ?? $args['version'] ?? '' );
			}
			$rendered = Template_Renderer::render( $template_id, $data, $flat );
			if ( '' !== $rendered ) {
				return $rendered;
			}
		}
		return Block_Template::render( $type_id, $data );
	}

	/**
	 * @param string               $type_id Type.
	 * @param array<string, mixed> $data    AI data.
	 * @param array<string, mixed> $args    Args.
	 */
	private static function default_title( string $type_id, array $data, array $args ): string {
		$label = (string) ( $args['label'] ?? '' );
		$end   = (string) ( $args['period_end'] ?? gmdate( 'Y-m-d' ) );
		$start = (string) ( $args['period_start'] ?? gmdate( 'Y-m-d', strtotime( '-7 days' ) ) );
		$range = $start . ' – ' . $end;

		switch ( $type_id ) {
			case 'meeting-update':
				if ( '' === $label ) {
					$label = __( 'Meeting', 'pulse-press' );
				}
				return sprintf(
					/* translators: 1: meeting label, 2: date */
					__( 'Meeting notes — %1$s — %2$s', 'pulse-press' ),
					$label,
					$end
				);

			case 'release-update':
				return sprintf(
					/* translators: %s: date range */
					__( 'Release announcements — %s', 'pulse-press' ),
					$range
				);

			case 'whats-new-in':
				$product = (string) ( $data['product'] ?? $args['product'] ?? 'Gutenberg' );
				$version = (string) ( $data['version'] ?? $args['version'] ?? '' );
				$date    = (string) ( $data['date'] ?? $args['date'] ?? $end );
				return sprintf(
					/* translators: 1: product name, 2: version, 3: date */
					__( 'What\'s new in %1$s %2$s? (%3$s)', 'pulse-press' ),
					$product,
					$version,
					$date
				);

			case 'agenda':
				$subtype = (string) ( $args['agenda_subtype'] ?? 'dev-chat' );
				$subtypes = Post_Type_Registry::get_agenda_subtypes();
				$type_label = $subtypes[ $subtype ]['label'] ?? __( 'Agenda', 'pulse-press' );
				if ( '' === $label ) {
					$label = $type_label;
				}
				return sprintf(
					/* translators: 1: agenda type label, 2: date */
					__( '%1$s — %2$s', 'pulse-press' ),
					$label,
					$end
				);

			default:
				return sprintf(
					/* translators: %s: date range */
					__( 'Team digest — %s', 'pulse-press' ),
					$range
				);
		}
	}
}
