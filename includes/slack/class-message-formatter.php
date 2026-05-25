<?php
/**
 * Turns Slack message payloads into plain text for the AI pipeline.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Slack;

/**
 * Extracts text, links, attachments, blocks, and files from Slack messages.
 */
final class Message_Formatter {

	/**
	 * @param array<string, mixed> $msg   Slack message object.
	 * @param int                  $depth Nesting depth for thread replies.
	 */
	public static function format_message( array $msg, int $depth = 0 ): string {
		$parts = array();

		$text = self::expand_mrkdwn( (string) ( $msg['text'] ?? '' ) );
		if ( '' !== $text ) {
			$parts[] = $text;
		}

		foreach ( (array) ( $msg['attachments'] ?? array() ) as $attachment ) {
			if ( is_array( $attachment ) ) {
				$chunk = self::format_attachment( $attachment );
				if ( '' !== $chunk ) {
					$parts[] = $chunk;
				}
			}
		}

		foreach ( (array) ( $msg['blocks'] ?? array() ) as $block ) {
			if ( is_array( $block ) ) {
				$chunk = self::format_block( $block );
				if ( '' !== $chunk ) {
					$parts[] = $chunk;
				}
			}
		}

		foreach ( (array) ( $msg['files'] ?? array() ) as $file ) {
			if ( is_array( $file ) ) {
				$chunk = self::format_file( $file );
				if ( '' !== $chunk ) {
					$parts[] = $chunk;
				}
			}
		}

		$body = trim( implode( "\n", array_filter( $parts ) ) );
		if ( '' === $body ) {
			return '';
		}

		$prefix = $depth > 0 ? str_repeat( '  ', $depth ) . '↳ ' : '';
		$lines  = preg_split( '/\r\n|\r|\n/', $body ) ?: array();
		$lines  = array_map(
			static function ( string $line ) use ( $prefix ): string {
				return $prefix . $line;
			},
			$lines
		);

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, mixed> $attachment Slack attachment.
	 */
	private static function format_attachment( array $attachment ): string {
		$parts = array();

		$title = trim( (string) ( $attachment['title'] ?? '' ) );
		$link  = trim( (string) ( $attachment['title_link'] ?? $attachment['from_url'] ?? '' ) );
		if ( '' !== $title ) {
			$parts[] = '' !== $link ? $title . ' — ' . $link : $title;
		}

		foreach ( array( 'pretext', 'text', 'fallback' ) as $key ) {
			$chunk = self::expand_mrkdwn( trim( (string) ( $attachment[ $key ] ?? '' ) ) );
			if ( '' !== $chunk && ! in_array( $chunk, $parts, true ) ) {
				$parts[] = $chunk;
			}
		}

		$image = trim( (string) ( $attachment['image_url'] ?? $attachment['thumb_url'] ?? '' ) );
		if ( '' !== $image ) {
			$parts[] = '[Image: ' . $image . ']';
		}

		$fields = (array) ( $attachment['fields'] ?? array() );
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$label = trim( (string) ( $field['title'] ?? '' ) );
			$value = self::expand_mrkdwn( trim( (string) ( $field['value'] ?? '' ) ) );
			if ( '' === $value ) {
				continue;
			}
			$parts[] = '' !== $label ? $label . ': ' . $value : $value;
		}

		return trim( implode( "\n", $parts ) );
	}

	/**
	 * @param array<string, mixed> $block Slack block.
	 */
	private static function format_block( array $block ): string {
		$type = (string) ( $block['type'] ?? '' );

		if ( 'rich_text' === $type ) {
			return self::format_rich_text_elements( (array) ( $block['elements'] ?? array() ) );
		}

		if ( 'section' === $type ) {
			$parts = array();
			if ( isset( $block['text'] ) && is_array( $block['text'] ) ) {
				$parts[] = self::format_block_text( $block['text'] );
			}
			foreach ( (array) ( $block['fields'] ?? array() ) as $field ) {
				if ( is_array( $field ) ) {
					$parts[] = self::format_block_text( $field );
				}
			}
			if ( ! empty( $block['accessory'] ) && is_array( $block['accessory'] ) ) {
				$parts[] = self::format_block_element( $block['accessory'] );
			}
			return trim( implode( "\n", array_filter( $parts ) ) );
		}

		if ( 'context' === $type ) {
			$parts = array();
			foreach ( (array) ( $block['elements'] ?? array() ) as $element ) {
				if ( is_array( $element ) ) {
					$parts[] = self::format_block_element( $element );
				}
			}
			return trim( implode( ' ', array_filter( $parts ) ) );
		}

		if ( 'image' === $type ) {
			$alt = trim( (string) ( $block['alt_text'] ?? '' ) );
			$url = trim( (string) ( $block['image_url'] ?? '' ) );
			if ( '' !== $url ) {
				return '' !== $alt ? $alt . ' — [Image: ' . $url . ']' : '[Image: ' . $url . ']';
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $text_obj Block text object.
	 */
	private static function format_block_text( array $text_obj ): string {
		$text = (string) ( $text_obj['text'] ?? '' );
		if ( 'mrkdwn' === (string) ( $text_obj['type'] ?? '' ) ) {
			return self::expand_mrkdwn( $text );
		}
		return trim( $text );
	}

	/**
	 * @param array<string, mixed> $element Block element.
	 */
	private static function format_block_element( array $element ): string {
		$type = (string) ( $element['type'] ?? '' );
		if ( 'link' === $type ) {
			$url   = trim( (string) ( $element['url'] ?? '' ) );
			$label = trim( (string) ( $element['text'] ?? $url ) );
			return '' !== $url ? $label . ' — ' . $url : $label;
		}
		if ( 'mrkdwn' === $type || 'plain_text' === $type ) {
			return 'mrkdwn' === $type ? self::expand_mrkdwn( (string) ( $element['text'] ?? '' ) ) : trim( (string) ( $element['text'] ?? '' ) );
		}
		if ( 'rich_text' === $type ) {
			return self::format_rich_text_elements( (array) ( $element['elements'] ?? array() ) );
		}
		return '';
	}

	/**
	 * @param array<int, mixed> $elements Rich text elements.
	 */
	private static function format_rich_text_elements( array $elements ): string {
		$parts = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type = (string) ( $element['type'] ?? '' );
			if ( 'rich_text_section' === $type || 'rich_text_quote' === $type || 'rich_text_preformatted' === $type ) {
				$section = self::format_rich_text_elements( (array) ( $element['elements'] ?? array() ) );
				if ( '' !== $section ) {
					$parts[] = $section;
				}
				continue;
			}
			if ( 'rich_text_list' === $type ) {
				$style = (string) ( $element['style'] ?? 'bullet' );
				foreach ( (array) ( $element['elements'] ?? array() ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$line = self::format_rich_text_elements( (array) ( $item['elements'] ?? array() ) );
					if ( '' !== $line ) {
						$parts[] = ( 'ordered' === $style ? '1. ' : '• ' ) . $line;
					}
				}
				continue;
			}
			if ( 'link' === $type ) {
				$url   = trim( (string) ( $element['url'] ?? '' ) );
				$label = trim( (string) ( $element['text'] ?? $url ) );
				$parts[] = '' !== $url ? $label . ' — ' . $url : $label;
				continue;
			}
			if ( 'text' === $type ) {
				$parts[] = (string) ( $element['text'] ?? '' );
			}
		}
		return trim( implode( "\n", array_filter( $parts ) ) );
	}

	/**
	 * @param array<string, mixed> $file Slack file object.
	 */
	private static function format_file( array $file ): string {
		$name = trim( (string) ( $file['title'] ?? $file['name'] ?? '' ) );
		$link = trim( (string) ( $file['permalink'] ?? $file['permalink_public'] ?? $file['url_private'] ?? '' ) );
		$mime = trim( (string) ( $file['mimetype'] ?? '' ) );

		if ( '' === $name && '' === $link ) {
			return '';
		}

		$line = '' !== $name ? $name : __( 'Attached file', 'pulse-press' );
		if ( '' !== $link ) {
			$line .= ' — ' . $link;
		}
		if ( '' !== $mime && str_starts_with( $mime, 'image/' ) ) {
			$image_url = self::file_image_url( $file );
			if ( '' !== $image_url ) {
				$line .= "\n[Image: " . $image_url . ']';
			}
		}

		return $line;
	}

	/**
	 * Direct download URL for sideloading (not the Slack permalink page).
	 *
	 * @param array<string, mixed> $file Slack file object.
	 */
	private static function file_image_url( array $file ): string {
		foreach ( array( 'url_private_download', 'thumb_720', 'thumb_480', 'thumb_360', 'url_private' ) as $key ) {
			$url = trim( (string) ( $file[ $key ] ?? '' ) );
			if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
				return $url;
			}
		}
		return '';
	}

	public static function expand_mrkdwn( string $text ): string {
		if ( '' === $text ) {
			return '';
		}

		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$text = (string) preg_replace_callback(
			'/<(https?:\/\/[^|>]+)(?:\|([^>]+))?>/i',
			static function ( array $m ): string {
				$url   = $m[1];
				$label = isset( $m[2] ) ? trim( $m[2] ) : '';
				if ( '' !== $label && $label !== $url ) {
					return $label . ' — ' . $url;
				}
				return $url;
			},
			$text
		);

		$text = (string) preg_replace( '/<(https?:\/\/[^>]+)>/i', '$1', $text );

		return trim( $text );
	}
}
