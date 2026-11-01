<?php
/**
 * Changelog generation tests.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verify release merging, archive formatting, and invalid-input handling.
 */
final class ChangelogTest extends TestCase {

	private const README = "== Changelog ==\n\n= 2.6.0 =\n* Current.\n\n= 2.5.0 =\n* Updated.\n\n= 2.0 =\n* Legacy.\n\n[See changelog for all versions](https://example.org).\n\n== Upgrade Notice ==\nNot release notes.\n";

	private const ARCHIVE = "= 2.5.0 =\n* Old.\n\n= 2.4.3 =\n* Archived minor.\n\n= 1.0.0 =\n* Initial.\n";

	/**
	 * Verify that merging releases preserves archived versions in descending order.
	 *
	 * @return void
	 */
	public function test_preserves_archived_releases_in_version_order(): void {
		$generated = kagg_compatibility_generate_changelog( self::README, self::ARCHIVE );

		self::assertSame(
			[ '2.6.0', '2.5.0', '2.4.3', '2.0', '1.0.0' ],
			array_keys( kagg_compatibility_parse_releases( $generated ) )
		);
	}

	/**
	 * Verify that current readme notes replace older notes for the same release.
	 *
	 * @return void
	 */
	public function test_readme_notes_replace_existing_archive_notes(): void {
		$generated = kagg_compatibility_generate_changelog( self::README, self::ARCHIVE );

		self::assertStringContainsString( '* Updated.', $generated );
		self::assertStringNotContainsString( '* Old.', $generated );
	}

	/**
	 * Verify that navigation links and upgrade notices stay out of the changelog.
	 *
	 * @return void
	 */
	public function test_excludes_non_release_content(): void {
		$generated = kagg_compatibility_generate_changelog( self::README, self::ARCHIVE );

		self::assertStringNotContainsString( 'See changelog', $generated );
		self::assertStringNotContainsString( 'Upgrade Notice', $generated );
		self::assertStringNotContainsString( 'Not release notes.', $generated );
	}

	/**
	 * Verify that generating the changelog again does not duplicate releases.
	 *
	 * @return void
	 */
	public function test_repeated_generation_is_idempotent(): void {
		$generated = kagg_compatibility_generate_changelog( self::README, self::ARCHIVE );

		self::assertSame( $generated, kagg_compatibility_generate_changelog( self::README, $generated ) );
	}

	/**
	 * Verify that CRLF and LF readmes produce identical release history.
	 *
	 * @return void
	 */
	public function test_windows_line_endings_produce_the_same_changelog(): void {
		self::assertSame(
			kagg_compatibility_generate_changelog( self::README, self::ARCHIVE ),
			kagg_compatibility_generate_changelog( str_replace( "\n", "\r\n", self::README ), self::ARCHIVE )
		);
	}

	/**
	 * Verify that invalid readmes raise the expected error instead of producing an archive.
	 *
	 * @dataProvider invalid_readmes
	 *
	 * @param string $readme Readme content without a valid release section.
	 * @param string $message Expected exception message.
	 *
	 * @return void
	 */
	public function test_rejects_readmes_without_release_notes( string $readme, string $message ): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( $message );

		kagg_compatibility_generate_changelog( $readme, '' );
	}

	/**
	 * Provide readmes with missing changelog sections or empty release notes.
	 *
	 * @return array<string, array{0: string, 1: string}> Invalid readmes and expected messages.
	 */
	public static function invalid_readmes(): array {
		return [
			'missing section' => [ 'No changelog.', 'No Changelog section found' ],
			'empty section'   => [ "== Changelog ==\n\n== Upgrade Notice ==\n", 'No release notes found' ],
		];
	}
}
