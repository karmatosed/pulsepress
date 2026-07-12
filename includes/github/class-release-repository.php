<?php
/**
 * Fetches GitHub releases for the pipeline.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\GitHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\Security;
use Pulse_Press\Settings;

/**
 * Builds normalized release text from configured repos.
 */
final class Release_Repository {

	/**
	 * @return string|\WP_Error
	 */
	public static function fetch_since( int $days = 7 ) {
		if ( ! OAuth_Client::is_connected() ) {
			return new \WP_Error( 'pulse_press_github', __( 'Connect GitHub first.', 'pulse-press' ) );
		}

		$repos = Settings::get( 'github_repos', array() );
		if ( ! is_array( $repos ) || empty( $repos ) ) {
			return new \WP_Error( 'pulse_press_github_repos', __( 'Add at least one GitHub repository (owner/repo).', 'pulse-press' ) );
		}

		$since    = strtotime( '-' . max( 1, $days ) . ' days' );
		$client   = new API_Client();
		$sections = array();

		foreach ( $repos as $repo_slug ) {
			$repo_slug = trim( (string) $repo_slug );
			if ( '' === $repo_slug || false === strpos( $repo_slug, '/' ) ) {
				continue;
			}
			list( $owner, $repo ) = array_map( 'trim', explode( '/', $repo_slug, 2 ) );
			if ( ! Security::is_valid_github_repo_part( $owner, $repo ) ) {
				continue;
			}
			$releases = $client->list_releases( $owner, $repo, 20 );
			if ( is_wp_error( $releases ) ) {
				return $releases;
			}

			foreach ( $releases as $release ) {
				if ( ! is_array( $release ) ) {
					continue;
				}
				$published = strtotime( (string) ( $release['published_at'] ?? '' ) );
				if ( $published && $published < $since ) {
					continue;
				}
				if ( ! empty( $release['draft'] ) ) {
					continue;
				}
				$tag  = (string) ( $release['tag_name'] ?? '' );
				$body = (string) ( $release['body'] ?? '' );
				$url  = (string) ( $release['html_url'] ?? '' );
				$sections[] = "## {$repo_slug} {$tag}\nURL: {$url}\n\n{$body}";
			}
		}

		if ( empty( $sections ) ) {
			return new \WP_Error(
				'pulse_press_no_releases',
				__( 'No GitHub releases found for the selected period.', 'pulse-press' )
			);
		}

		return implode( "\n\n---\n\n", $sections );
	}
}
