<?php
/**
 * Admin URL helpers.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

/**
 * Builds Pulse Press admin URLs.
 */
final class Admin_Url {

	public static function page( string $tab = '', array $args = array() ): string {
		$query = array_merge(
			array( 'page' => 'pulse-press' ),
			$args
		);
		if ( '' !== $tab ) {
			$query['tab'] = $tab;
		}
		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}
}
