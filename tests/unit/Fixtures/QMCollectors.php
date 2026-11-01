<?php
/**
 * Query Monitor collector registry test double.
 *
 * @package kagg/compatibility
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection AutoloadingIssuesInspection
 */

/**
 * Expose the collector configured by the Query Monitor unit tests.
 */
class QM_Collectors {

	/**
	 * Collector returned for the doing-it-wrong identifier.
	 *
	 * @var object|null
	 */
	public static ?object $collector;

	/**
	 * Return the configured test collector for Query Monitor doing-it-wrong notices.
	 *
	 * @param string $id Requested collector identifier.
	 *
	 * @return object|null The configured collector, or null for an unknown identifier.
	 */
	public static function get( string $id ): ?object {
		return 'doing_it_wrong' === $id ? self::$collector : null;
	}
}
