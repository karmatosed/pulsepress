<?php
/**
 * AI prompts and JSON schemas per content type.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\AI;

/**
 * Builds prompts and response schemas.
 */
final class Prompt_Factory {

	public const MAX_INPUT_CHARS = 120000;

	/**
	 * @return array{prompt: string, schema: array<string, mixed>}
	 */
	public static function build( string $type_id, string $content, string $context = '' ): array {
		$content = self::normalize_input( $content );
		$context = trim( $context );

		switch ( $type_id ) {
			case 'meeting-update':
				return array(
					'prompt' => self::meeting_prompt( $content, $context ),
					'schema' => self::meeting_schema(),
				);
			case 'release-update':
				return array(
					'prompt' => self::release_prompt( $content, $context ),
					'schema' => self::release_schema(),
				);
			case 'whats-new-in':
				return array(
					'prompt' => self::whats_new_prompt( $content, $context ),
					'schema' => self::whats_new_schema(),
				);
			case 'agenda':
				return array(
					'prompt' => self::agenda_prompt( $content, $context ),
					'schema' => self::agenda_schema(),
				);
			default:
				return array(
					'prompt' => self::team_prompt( $content, $context ),
					'schema' => self::team_schema(),
				);
		}
	}

	private static function normalize_input( string $content ): string {
		$content = wp_strip_all_tags( $content, true );
		// Preserve message boundaries (Slack paste is line-oriented); do not collapse newlines.
		$lines = preg_split( '/\r\n|\r|\n/', $content ) ?? array();
		$lines = array_map(
			static function ( string $line ): string {
				$line = preg_replace( '/[ \t]+/', ' ', $line ) ?? $line;
				return trim( $line );
			},
			$lines
		);
		$content = trim( implode( "\n", $lines ) );
		$content = preg_replace( "/\n{3,}/", "\n\n", $content ) ?? $content;
		if ( strlen( $content ) > self::MAX_INPUT_CHARS ) {
			$content = substr( $content, 0, self::MAX_INPUT_CHARS );
			$content .= "\n\n[Source was truncated due to length.]";
		}
		return $content;
	}

	private static function preservation_rules(): string {
		return 'Rules: '
			. 'Preserve every URL exactly as written (https://…). Never drop links from the source. '
			. 'Include every bullet, checklist line, and deliverable—do not summarize away list items. '
			. 'Include plugin links, GitHub issues, blog posts, screenshots (as [Image: …] URLs), and schedule mentions when present. '
			. 'Do not invent facts, attendees, or decisions. '
			. 'Use "(None)" only when a section truly has no content from the source. '
			. 'Return JSON matching the schema only.';
	}

	private static function team_prompt( string $content, string $context ): string {
		$intro = 'You are an editorial assistant creating a team digest for a WordPress blog. '
			. 'The source is Slack messages (each line often includes a speaker and timestamp). '
			. 'Summarize factually in clear prose. '
			. self::preservation_rules() . ' '
			. 'For "channels", use one entry per channel or theme (e.g. #core-ai). '
			. 'For "notable", list distinct links, release dates, action asks, and upcoming meetings as separate bullets with URLs.';

		if ( '' !== $context ) {
			$intro .= "\n\nContext: " . $context;
		}

		return $intro . "\n\n---\n\nSlack content:\n\n" . $content;
	}

	private static function meeting_prompt( string $content, string $context ): string {
		$intro = 'You are an editorial assistant creating meeting notes for a WordPress blog from a Slack conversation backscroll. '
			. self::preservation_rules() . ' '
			. 'Put narrative context in "discussion". '
			. 'Put only explicit agreements in "decisions". '
			. 'Put follow-ups and release/testing asks in "action_items". '
			. 'Put unresolved questions (e.g. pending other people) in "open_questions". '
			. 'List people who joined or spoke materially in "attendees". '
			. 'Put every URL from the source in "links" with a short label.';

		if ( '' !== $context ) {
			$intro .= "\n\nContext: " . $context;
		}

		return $intro . "\n\n---\n\nMeeting content:\n\n" . $content;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function team_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'summary'  => array( 'type' => 'string' ),
				'channels' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'name'       => array( 'type' => 'string' ),
							'highlights' => array( 'type' => 'string' ),
						),
						'required'             => array( 'name', 'highlights' ),
					),
				),
				'notable'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'summary', 'channels', 'notable' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function meeting_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'summary'        => array( 'type' => 'string' ),
				'attendees'      => array( 'type' => 'string' ),
				'discussion'     => array( 'type' => 'string' ),
				'decisions'      => array( 'type' => 'string' ),
				'action_items'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'open_questions' => array( 'type' => 'string' ),
				'links'          => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'summary', 'attendees', 'discussion', 'decisions', 'action_items', 'open_questions', 'links' ),
		);
	}

	private static function release_prompt( string $content, string $context ): string {
		$intro = 'You are an editorial assistant creating a release announcements post for a WordPress blog. '
			. 'Source may be a Slack release channel, GitHub release notes, changelogs, or pasted text. '
			. self::preservation_rules() . ' '
			. 'Group by what is being released (product, team, or initiative). '
			. 'List each distinct release in "releases" with name, version, date if known, summary body, URL, and every sub-item with its link in "links".';

		if ( '' !== $context ) {
			$intro .= "\n\nContext: " . $context;
		}

		return $intro . "\n\n---\n\nRelease content:\n\n" . $content;
	}

	private static function whats_new_prompt( string $content, string $context ): string {
		$intro = 'You are an editorial assistant creating a "What\'s new in…" post in the style of Make WordPress Core Gutenberg release posts. '
			. 'Source may be release notes, PR lists, or changelog paste. '
			. self::preservation_rules() . ' '
			. 'Put 3–5 major items in "highlights" with title and body (include PR/issue links). '
			. 'Put shorter bullets in "other_highlights". '
			. 'Summarize remaining items in "changelog" as prose or grouped text. '
			. 'Include contributor handles in "contributors" when present.';

		if ( '' !== $context ) {
			$intro .= "\n\nContext: " . $context;
		}

		return $intro . "\n\n---\n\nSource content:\n\n" . $content;
	}

	private static function agenda_prompt( string $content, string $context ): string {
		$intro = 'You are an editorial assistant creating a meeting agenda for Make WordPress dev chat or similar. '
			. 'Source is pasted agenda notes, Slack, or bullet list. '
			. self::preservation_rules() . ' '
			. 'Extract meeting_when and meeting_where from the source when present. '
			. 'Put announcement bullets in "announcements". '
			. 'Put discussion topics in "discussions" with title and body. '
			. 'Put open-floor items in "open_floor".';

		if ( '' !== $context ) {
			$intro .= "\n\nContext: " . $context;
		}

		return $intro . "\n\n---\n\nAgenda source:\n\n" . $content;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function release_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'summary'  => array( 'type' => 'string' ),
				'releases' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'name'    => array( 'type' => 'string' ),
							'version' => array( 'type' => 'string' ),
							'date'    => array( 'type' => 'string' ),
							'body'    => array( 'type' => 'string' ),
							'url'     => array( 'type' => 'string' ),
						),
						'required'             => array( 'name', 'version', 'date', 'body', 'url' ),
					),
				),
				'notes'    => array( 'type' => 'string' ),
				'links'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'summary', 'releases', 'notes', 'links' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function whats_new_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'product'            => array( 'type' => 'string' ),
				'version'            => array( 'type' => 'string' ),
				'date'               => array( 'type' => 'string' ),
				'intro'              => array( 'type' => 'string' ),
				'overview'           => array( 'type' => 'string' ),
				'highlights'         => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'title' => array( 'type' => 'string' ),
							'body'  => array( 'type' => 'string' ),
						),
						'required'             => array( 'title', 'body' ),
					),
				),
				'other_highlights' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'changelog'          => array( 'type' => 'string' ),
				'contributors'       => array( 'type' => 'string' ),
			),
			'required'             => array( 'product', 'version', 'date', 'intro', 'overview', 'highlights', 'other_highlights', 'changelog', 'contributors' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function agenda_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'meeting_when'  => array( 'type' => 'string' ),
				'meeting_where' => array( 'type' => 'string' ),
				'announcements' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'discussions'   => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'title' => array( 'type' => 'string' ),
							'body'  => array( 'type' => 'string' ),
						),
						'required'             => array( 'title', 'body' ),
					),
				),
				'open_floor'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'links'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'meeting_when', 'meeting_where', 'announcements', 'discussions', 'open_floor', 'links' ),
		);
	}
}
