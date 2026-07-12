<?php
/**
 * Merges AI JSON into block markup templates.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Renders block-serialized templates with Mustache-style placeholders.
 */
final class Template_Renderer {

	/**
	 * @param string               $template_id Template slug.
	 * @param array<string, mixed> $data        AI JSON.
	 * @param array<string, mixed> $flat        Extra scalar placeholders.
	 */
	public static function render( string $template_id, array $data, array $flat = array(), string $template_override = '' ): string {
		$template = '' !== $template_override ? $template_override : Template_Registry::get( $template_id );
		if ( '' === $template ) {
			return '';
		}

		$scalars = array_merge( self::scalarize( $data ), $flat );
		$output  = self::expand_loops( $template, $data );
		$output  = self::replace_scalars( $output, $scalars );

		return trim( $output );
	}

	/**
	 * Preview HTML for admin (runs do_blocks).
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function preview_html( string $template_id, array $data, array $flat = array(), string $template_override = '' ): string {
		$blocks = self::render( $template_id, $data, $flat, $template_override );
		if ( '' === $blocks ) {
			return '';
		}
		return wp_kses_post( (string) do_blocks( $blocks ) );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return array<string, string>
	 */
	private static function scalarize( array $data ): array {
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private static function expand_loops( string $template, array $data ): string {
		return (string) preg_replace_callback(
			'/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
			static function ( array $matches ) use ( $data ): string {
				$key   = $matches[1];
				$inner = $matches[2];
				$items = $data[ $key ] ?? array();
				if ( ! is_array( $items ) ) {
					return '';
				}
				$parts = array();
				foreach ( $items as $item ) {
					if ( is_string( $item ) ) {
						$chunk = self::replace_scalars( $inner, array( 'item' => $item, '.' => $item ) );
						$parts[] = self::format_scalar_in_template( $chunk );
					} elseif ( is_array( $item ) ) {
						$scalar_item = array();
						foreach ( $item as $ik => $iv ) {
							if ( is_string( $iv ) || is_numeric( $iv ) ) {
								$scalar_item[ (string) $ik ] = (string) $iv;
							}
						}
						$parts[] = self::format_scalar_in_template( self::replace_scalars( $inner, $scalar_item ) );
					}
				}
				return implode( "\n\n", $parts );
			},
			$template
		);
	}

	/**
	 * @param array<string, string> $scalars Scalars.
	 */
	private static function replace_scalars( string $template, array $scalars ): string {
		foreach ( $scalars as $key => $value ) {
			$template = str_replace( '{{' . $key . '}}', self::format_text( $value ), $template );
		}
		$template = (string) preg_replace( '/\{\{[^}]+\}\}/', '', $template );
		return $template;
	}

	private static function format_scalar_in_template( string $chunk ): string {
		return (string) preg_replace_callback(
			'/<p>([^<]*)<\/p>/',
			static function ( array $m ): string {
				$inner = trim( $m[1] );
				if ( '' === $inner || '(None)' === $inner ) {
					return '<p>' . esc_html__( '(No content)', 'pulse-press' ) . '</p>';
				}
				return '<p>' . self::format_text( $inner ) . '</p>';
			},
			$chunk
		);
	}

	private static function format_text( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
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
}
