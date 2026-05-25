<?php
/**
 * Locates meeting content between Slack marker tags.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Slack;

/**
 * Filters channel messages to the latest start/finish tag pair in a period.
 */
final class Meeting_Boundary {

	/**
	 * @param array<string, mixed> $options use_tags, start_tag, end_tag.
	 * @return array{use_tags: bool, start_tag: string, end_tag: string}
	 */
	public static function normalize_options( array $options ): array {
		$use_tags = ! empty( $options['use_tags'] );
		$start    = trim( (string) ( $options['start_tag'] ?? '' ) );
		$end      = trim( (string) ( $options['end_tag'] ?? '' ) );

		return array(
			'use_tags'  => $use_tags && '' !== $start,
			'start_tag' => $start,
			'end_tag'   => $end,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $messages Chronological Slack messages.
	 * @param array<string, mixed>             $options  Boundary options.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_messages( array $messages, array $options ): array {
		$options = self::normalize_options( $options );
		if ( ! $options['use_tags'] ) {
			return $messages;
		}

		$window = self::locate_window( $messages, $options['start_tag'], $options['end_tag'] );
		if ( null === $window ) {
			return $messages;
		}

		return array_slice( $messages, $window['start'], $window['end'] - $window['start'] + 1 );
	}

	/**
	 * @param array<int, array<string, mixed>> $messages Messages oldest-first.
	 * @return array{start: int, end: int}|null
	 */
	public static function locate_window( array $messages, string $start_tag, string $end_tag ): ?array {
		$start_tag = trim( $start_tag );
		if ( '' === $start_tag ) {
			return null;
		}

		$start_idx = null;
		$end_idx   = null;

		foreach ( $messages as $index => $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			$text = self::message_text( $message );
			if ( self::text_contains_tag( $text, $start_tag ) ) {
				$start_idx = $index;
				$end_idx   = null;
				continue;
			}
			if ( null !== $start_idx && '' !== $end_tag && self::text_contains_tag( $text, $end_tag ) ) {
				$end_idx = $index;
			}
		}

		if ( null === $start_idx ) {
			return null;
		}

		if ( null === $end_idx ) {
			$end_idx = count( $messages ) - 1;
		}

		return array(
			'start' => $start_idx,
			'end'   => max( $start_idx, $end_idx ),
		);
	}

	/**
	 * @param array<string, mixed> $message Slack message.
	 */
	private static function message_text( array $message ): string {
		return Message_Formatter::format_message( $message, 0 );
	}

	private static function text_contains_tag( string $text, string $tag ): bool {
		$tag = trim( $tag );
		if ( '' === $tag || '' === $text ) {
			return false;
		}

		$needles = array( $tag );
		if ( ! str_starts_with( $tag, '#' ) ) {
			$needles[] = '#' . $tag;
		}
		if ( ! str_starts_with( $tag, ':' ) || ! str_ends_with( $tag, ':' ) ) {
			$needles[] = ':' . trim( $tag, ':' ) . ':';
		}

		foreach ( array_unique( $needles ) as $needle ) {
			if ( '' !== $needle && false !== stripos( $text, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
