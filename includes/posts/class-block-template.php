<?php
/**
 * Maps AI JSON to block markup.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Posts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Builds Gutenberg block content from structured AI output.
 */
final class Block_Template {

	/**
	 * @param string               $type_id Type slug.
	 * @param array<string, mixed> $data    Decoded AI JSON.
	 */
	public static function render( string $type_id, array $data ): string {
		if ( 'meeting-update' === $type_id ) {
			return self::render_meeting( $data );
		}
		return self::render_team( $data );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private static function render_team( array $data ): string {
		$blocks = array( self::open_group() );
		$blocks[] = self::section( __( 'Summary', 'pulse-press' ), (string) ( $data['summary'] ?? '' ) );

		$channels = $data['channels'] ?? array();
		if ( is_array( $channels ) && ! empty( $channels ) ) {
			$body = '';
			foreach ( $channels as $channel ) {
				if ( ! is_array( $channel ) ) {
					continue;
				}
				$name = (string) ( $channel['name'] ?? '' );
				$high = (string) ( $channel['highlights'] ?? '' );
				if ( '' === $name && '' === $high ) {
					continue;
				}
				$body .= '<h3 class="wp-block-heading">' . esc_html( $name ) . "</h3>\n";
				$body .= '<p>' . self::format_text( $high ) . "</p>\n";
			}
			$blocks[] = self::section_html( __( 'Highlights by channel', 'pulse-press' ), $body );
		}

		$notable = $data['notable'] ?? array();
		if ( is_array( $notable ) && ! empty( $notable ) ) {
			$blocks[] = self::section_list( __( 'Notable threads and links', 'pulse-press' ), $notable );
		}

		$blocks[] = self::close_group();
		return implode( "\n\n", $blocks );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private static function render_meeting( array $data ): string {
		$blocks   = array( self::open_group() );
		$sections = array(
			'summary'        => __( 'Summary', 'pulse-press' ),
			'attendees'      => __( 'Attendees', 'pulse-press' ),
			'discussion'     => __( 'Discussion', 'pulse-press' ),
			'decisions'      => __( 'Decisions', 'pulse-press' ),
			'action_items'   => __( 'Action items', 'pulse-press' ),
			'open_questions' => __( 'Open questions', 'pulse-press' ),
		);

		foreach ( $sections as $key => $label ) {
			$value = $data[ $key ] ?? '';
			if ( 'action_items' === $key && is_array( $value ) ) {
				$blocks[] = self::section_list( $label, $value );
			} else {
				$blocks[] = self::section( $label, (string) $value );
			}
		}

		$links = $data['links'] ?? array();
		if ( is_array( $links ) && ! empty( $links ) ) {
			$blocks[] = self::section_list( __( 'Links', 'pulse-press' ), $links );
		}

		$blocks[] = self::close_group();
		return implode( "\n\n", $blocks );
	}

	private static function open_group(): string {
		return '<!-- wp:group {"className":"pulse-press-post","layout":{"type":"constrained"}} -->
<div class="wp-block-group pulse-press-post">';
	}

	private static function close_group(): string {
		return '</div>
<!-- /wp:group -->';
	}

	private static function section( string $title, string $content ): string {
		$content = trim( $content );
		if ( '' === $content || '(None)' === $content ) {
			$content = __( '(No content)', 'pulse-press' );
		}
		$paragraph = '<!-- wp:paragraph -->
<p>' . self::format_text( $content ) . '</p>
<!-- /wp:paragraph -->';

		return '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html( $title ) . "</h2>\n<!-- /wp:heading -->\n\n" . $paragraph;
	}

	/**
	 * Escape text and linkify URLs for readable drafts.
	 */
	private static function format_text( string $text ): string {
		if ( ! function_exists( 'make_clickable' ) ) {
			return esc_html( $text );
		}
		return wp_kses(
			make_clickable( esc_html( $text ) ),
			array(
				'a' => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
				),
			)
		);
	}

	private static function section_html( string $title, string $html ): string {
		return '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html( $title ) . "</h2>\n<!-- /wp:heading -->\n\n" .
			'<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
	}

	/**
	 * @param string[] $items List items.
	 */
	private static function section_list( string $title, array $items ): string {
		$lis = '';
		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			$lis .= '<li>' . self::format_text( $item ) . '</li>';
		}
		if ( '' === $lis ) {
			$lis = '<li>' . esc_html__( '(None)', 'pulse-press' ) . '</li>';
		}
		$list = '<!-- wp:list -->
<ul class="wp-block-list">' . $lis . '</ul>
<!-- /wp:list -->';

		return '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html( $title ) . "</h2>\n<!-- /wp:heading -->\n\n" . $list;
	}
}
