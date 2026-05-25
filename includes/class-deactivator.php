<?php
/**
 * Deactivation routines.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

/**
 * Plugin deactivation.
 */
final class Deactivator {

	public static function deactivate(): void {
		Jobs\Digest_Cron::unschedule();
	}
}
