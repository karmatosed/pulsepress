<?php
/**
 * Main plugin bootstrap.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

/**
 * Registers hooks and services.
 */
final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'init', array( Activator::class, 'register_taxonomy' ) );
		add_action( 'init', array( Activator::class, 'ensure_terms' ), 20 );

		if ( is_admin() ) {
			Admin\Settings_Page::register();
			Admin\Admin_Assets::register();
			Admin\OAuth_Controller::register();
			Admin\Actions_Handler::register();
		}

		Jobs\Digest_Cron::register();

		Rest\Config_Controller::register();
		Rest\Templates_Controller::register();
	}
}
