<?php
/**
 * REST endpoints for block templates.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press\Rest;

use Pulse_Press\Security;
use Pulse_Press\Templates\Template_Registry;
use Pulse_Press\Templates\Template_Renderer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Template preview and save.
 */
final class Templates_Controller {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'pulse-press/v1',
			'/templates',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_templates' ),
					'permission_callback' => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'save_template' ),
					'permission_callback' => static function (): bool {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'pulse-press/v1',
			'/templates/(?P<id>[a-z0-9\-]+)/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview_template' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function get_templates( WP_REST_Request $request ): WP_REST_Response {
		$templates = Template_Registry::get_all_for_admin();
		$defaults  = array();
		foreach ( Template_Registry::get_template_ids() as $id ) {
			$defaults[ $id ] = Template_Registry::get_default( $id );
		}

		return new WP_REST_Response(
			array(
				'templates' => $templates,
				'defaults'  => $defaults,
				'samples'   => array_map(
					static function ( string $id ): array {
						return Template_Registry::get_sample_data( $id );
					},
					Template_Registry::get_template_ids()
				),
			)
		);
	}

	public static function save_template( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$template_id = sanitize_key( (string) $request->get_param( 'id' ) );
		$content     = (string) $request->get_param( 'content' );

		if ( ! in_array( $template_id, Template_Registry::get_template_ids(), true ) ) {
			return new \WP_Error( 'invalid_template', __( 'Unknown template.', 'pulse-press' ), array( 'status' => 400 ) );
		}

		if ( strlen( $content ) > Security::MAX_TEMPLATE_BYTES ) {
			return new \WP_Error(
				'template_too_large',
				__( 'Template is too large.', 'pulse-press' ),
				array( 'status' => 400 )
			);
		}

		$overrides = \Pulse_Press\Settings::get( 'template_overrides', array() );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		if ( '' === trim( $content ) || $content === Template_Registry::get_default( $template_id ) ) {
			unset( $overrides[ $template_id ] );
		} else {
			$overrides[ $template_id ] = $content;
		}

		\Pulse_Press\Settings::update( array( 'template_overrides' => $overrides ) );

		return new WP_REST_Response( array( 'saved' => true ) );
	}

	public static function preview_template( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$template_id = sanitize_key( (string) $request->get_param( 'id' ) );
		if ( ! in_array( $template_id, Template_Registry::get_template_ids(), true ) ) {
			return new \WP_Error( 'invalid_template', __( 'Unknown template.', 'pulse-press' ), array( 'status' => 400 ) );
		}

		$body = $request->get_json_params();
		$data = is_array( $body['data'] ?? null ) ? $body['data'] : Template_Registry::get_sample_data( $template_id );
		$flat = is_array( $body['flat'] ?? null ) ? $body['flat'] : array();

		$override = ! empty( $body['content'] ) ? (string) $body['content'] : '';

		if ( strlen( $override ) > Security::MAX_TEMPLATE_BYTES ) {
			return new \WP_Error(
				'template_too_large',
				__( 'Template is too large.', 'pulse-press' ),
				array( 'status' => 400 )
			);
		}

		$html = Template_Renderer::preview_html( $template_id, $data, $flat, $override );

		return new WP_REST_Response(
			array(
				'html' => wp_kses_post( $html ),
			)
		);
	}
}
