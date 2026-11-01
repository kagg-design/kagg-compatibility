<?php
/**
 * Generate the complete changelog from readme releases and archived history.
 *
 * @package kagg/compatibility
 */

/**
 * Parse release sections, including legacy headings such as 2.0.
 *
 * @param string $text Release history.
 * @return array
 */
function kagg_compatibility_parse_releases( string $text ): array {

	$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
	$text = preg_replace( '/^\[See changelog for all versions\].*$/m', '', $text );
	preg_match_all( '/^= ([0-9]+(?:\.[0-9]+){1,2}(?:-[^\s=]+)?) =[ \t]*\n(.*?)(?=^= [^=\n]+ =[ \t]*$|\z)/ms', $text, $matches, PREG_SET_ORDER );
	$releases = [];

	foreach ( $matches as $match ) {
		$releases[ $match[1] ] = trim( $match[2] );
	}

	return $releases;
}

/**
 * Merge every readme release into the archive without duplicate sections.
 *
 * @param string $readme    Readme content.
 * @param string $changelog Existing archive.
 * @return string
 * @throws RuntimeException When the readme has no changelog or release notes.
 */
function kagg_compatibility_generate_changelog( string $readme, string $changelog ): string {
	$readme = str_replace( [ "\r\n", "\r" ], "\n", $readme );

	if ( ! preg_match( '/^== Changelog ==[ \t]*\n(.*?)(?=^== [^=\n]+ ==[ \t]*$|\z)/ms', $readme, $section ) ) {
		throw new RuntimeException( 'No Changelog section found in readme.txt.' );
	}

	$releases = kagg_compatibility_parse_releases( $section[1] );

	if ( ! $releases ) {
		throw new RuntimeException( 'No release notes found in readme.txt.' );
	}

	$releases = array_replace( kagg_compatibility_parse_releases( $changelog ), $releases );
	uksort( $releases, static function ( string $left, string $right ): int {
		return version_compare( $right, $left );
	} );
	$output = [];

	foreach ( $releases as $version => $notes ) {
		$output[] = '= ' . $version . " =\n" . $notes;
	}

	return implode( "\n\n", $output ) . "\n";
}

/**
 * Read the index so a partially staged commit is checked correctly.
 *
 * @param string $file Tracked file.
 * @return string
 * @throws RuntimeException When Git cannot read the file.
 */
function kagg_compatibility_read_staged_file( string $file ): string {
	exec( 'git show ' . escapeshellarg( ':' . $file ), $lines, $exit_code );

	if ( 0 !== $exit_code ) {
		throw new RuntimeException( 'Could not read staged ' . $file . '.' );
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Generate or check the release archive.
 *
 * @param string[] $arguments Command arguments.
 * @return int
 */
function kagg_compatibility_update_changelog( array $arguments ): int {
	if ( array_diff( $arguments, [ '--check', '--staged' ] ) || ( in_array( '--staged', $arguments, true ) && ! in_array( '--check', $arguments, true ) ) ) {
		fwrite( STDERR, "Usage: php .github/scripts/update-changelog.php [--check [--staged]]\n" );

		return 1;
	}

	$root = dirname( __DIR__, 2 );
	chdir( $root );

	try {
		if ( in_array( '--staged', $arguments, true ) ) {
			$readme    = kagg_compatibility_read_staged_file( 'readme.txt' );
			$changelog = kagg_compatibility_read_staged_file( 'changelog.txt' );
		} else {
			$readme    = file_get_contents( $root . '/readme.txt' );
			$changelog = file_exists( $root . '/changelog.txt' ) ? file_get_contents( $root . '/changelog.txt' ) : '';
		}

		if ( false === $readme || false === $changelog ) {
			throw new RuntimeException( 'Could not read release notes.' );
		}

		$generated = kagg_compatibility_generate_changelog( $readme, $changelog );

		if ( in_array( '--check', $arguments, true ) ) {
			if ( $generated !== str_replace( [ "\r\n", "\r" ], "\n", $changelog ) ) {
				fwrite( STDERR, "changelog.txt is out of date. Run composer changelog and stage the result.\n" );

				return 1;
			}

			echo "Changelog is up to date.\n";

			return 0;
		}

		if ( false === file_put_contents( $root . '/changelog.txt', $generated ) ) {
			throw new RuntimeException( 'Could not write changelog.txt.' );
		}
	} catch ( RuntimeException $exception ) {
		fwrite( STDERR, $exception->getMessage() . "\n" );

		return 1;
	}

	echo "Changelog generated from readme.txt.\n";

	return 0;
}

if ( isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	exit( kagg_compatibility_update_changelog( array_slice( $argv, 1 ) ) );
}
