# AGENTS.md

## Project scope
The project is the KAGG Compatibility WordPress plugin.

The root may be the current directory (`.../kagg-compatibility`) or
`wp-content/plugins/kagg-compatibility/` within a WordPress installation.
Resolve `plugin.php`, `src/`, `assets/`, `tests/`, and configuration files from
that root; do not look for a nested plugin directory when already at the root.

## File access
- Modify only files inside this plugin's root.
- Read outside the plugin only when necessary for integration context, such as
  WordPress core behavior or third-party hooks. Keep those searches targeted.
- Preserve existing uncommitted work.

## Rules source and precedence
Apply all of these rule files to work throughout the plugin, including tests:

- `.aiassistant/rules/00-CORE.md`
- `.aiassistant/rules/PHPCS.md`
- `.aiassistant/rules/ESLINT.md`

When rules conflict, use this order:

1. Project configuration, including `phpcs.xml`, `composer.json`, and `phpunit.xml`.
2. `.aiassistant/rules/PHPCS.md` and `.aiassistant/rules/ESLINT.md`.
3. `.aiassistant/rules/00-CORE.md`.
4. This file.

## Navigation
For each local code task, make one inexpensive PhpStorm MCP probe with the
project path. If the project is open and indexed, prefer indexed file, text,
and symbol search and appropriate navigation or inspections. If unavailable,
failed, or incomplete, promptly use `rg` and ordinary file tools. Do not repeat
a failed probe or open/import the project just to force indexing. Exclude
vendor and build directories from routine searches.

## Verification
- Target PHP 7.4+ and WordPress 6.0+.
- For PHP changes, run `php vendor/bin/phpcs --standard=phpcs.xml <changed PHP files>`
  and relevant tests with `composer test -- --filter <test>`.
- Use `composer phpcs` and `composer test` for changes spanning the plugin or
  when a full check is requested. Include tests in style checks.
- After changing JavaScript, use `node --check assets/js/general.js` and
  `node --check assets/js/general.min.js`.
- There is no JS package manager, ESLint configuration, or asset build in this
  repository. Do not assume Yarn, Jest, or integration-test scripts exist.
- Consult `README.md` for release checks and the optional WordPress smoke check.
- Keep lint exceptions limited to a specific sniff and the smallest necessary
  scope, with a reason. Do not exclude tests or lower severities to hide failures.

## Test conventions
- Use PHPUnit 9.6 and WP_Mock; unit tests need no WordPress installation or database.
- Restore WordPress mocks, globals, and PHP error handlers changed by a test.
- Keep the global `QM_Collectors` fixture compatible with Query Monitor's class
  name; it is explicitly required rather than PSR-4 autoloaded.
- Put IDE suppression annotations inside descriptive PHPDoc blocks, without
  disabling PHPCS documentation rules.
- When a Mockery partial mock needs protected-method mocking, assign the result
  of `makePartial()` first, then call `shouldAllowMockingProtectedMethods()`
  separately so the analyzer recognizes it.

## Changelog
- Add release notes to the current version under `== Changelog ==` in `readme.txt`.
- Generate `changelog.txt` with `composer changelog`; do not edit the archive by hand.
- Run `composer changelog:check` after changing release notes.
- Use `composer prepare` to install the pre-commit hook. It checks the staged
  changelog and does not generate or stage it automatically.
