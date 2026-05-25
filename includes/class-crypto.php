<?php
/**
 * Token encryption helper.
 *
 * @package PulsePress
 */

declare(strict_types=1);

namespace Pulse_Press;

/**
 * Encrypts and decrypts sensitive strings at rest.
 */
final class Crypto {

	private const CIPHER = 'AES-256-CBC';

	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$enc = openssl_encrypt( $plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return '';
		}
		return base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = substr( $raw, 0, $iv_len );
		$data   = substr( $raw, $iv_len );
		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$plain  = openssl_decrypt( $data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}
