<?php
/**
 * PHP 8.6 compatibility for WP_Mock callback identity keys.
 *
 * @package kagg/compatibility
 */

namespace WP_Mock;

/**
 * Replace WP_Mock 1.1.1's deprecated object hashing with stable object IDs.
 *
 * WP_Mock only compares these keys internally; their hash format is not used.
 * Remove this bridge when WP_Mock switches to spl_object_id() upstream.
 *
 * @param object $callback_object Callback or matcher object.
 *
 * @return string Object identity key.
 */
function spl_object_hash( object $callback_object ): string {
	return (string) \spl_object_id( $callback_object );
}
