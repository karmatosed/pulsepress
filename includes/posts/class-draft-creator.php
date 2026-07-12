<?php
/**
 * Creates draft posts from pipeline output.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Posts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Pulse_Press\Security;
use Pulse_Press\Settings;

/**
 * Inserts formatted draft posts.
 */
final class Draft_Creator {

	/**
	 * @param string               $type_id   Type slug.
	 * @param string               $title     Post title.
	 * @param string               $content   Block content.
	 * @param string               $source    slack|paste.
	 * @param array<string, mixed> $meta_extra Extra meta.
	 * @return int|\WP_Error Post ID.
	 */
	public static function create(
		string $type_id,
		string $title,
		string $content,
		string $source,
		array $meta_extra = array()
	) {
		if ( ! Post_Type_Registry::is_valid( $type_id ) ) {
			return new \WP_Error( 'pulse_press_invalid_type', __( 'Invalid content type.', 'pulse-press' ) );
		}

		$author_id = Settings::get_draft_author_id();
		wp_set_current_user( $author_id );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'post_author'  => $author_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( (int) $post_id, $type_id, 'pulse_press_type' );

		$category_map = array(
			'team-update'    => 'category_team_update',
			'meeting-update' => 'category_meeting_update',
			'release-update' => 'category_release_update',
			'whats-new-in'   => 'category_whats_new_in',
			'agenda'         => 'category_agenda',
		);
		$category_key = $category_map[ $type_id ] ?? '';
		$cat_id       = '' !== $category_key ? (int) Settings::get( $category_key, 0 ) : 0;
		if ( $cat_id > 0 ) {
			wp_set_post_categories( (int) $post_id, array( $cat_id ) );
		}

		update_post_meta( (int) $post_id, '_pulse_press_source', sanitize_key( $source ) );
		update_post_meta( (int) $post_id, '_pulse_press_generated_at', gmdate( 'c' ) );

		foreach ( Security::sanitize_post_meta( $meta_extra ) as $key => $value ) {
			update_post_meta( (int) $post_id, '_pulse_press_' . $key, $value );
		}

		return (int) $post_id;
	}
}
