---
apply: always
---

# KAGG Compatibility PHPCS rules

`phpcs.xml` is authoritative. Keep this document in sync with configuration changes.

## Scan scope and commands
- `<file>.</file>` scans the plugin's PHP files, including `plugin.php`, `src/`,
  and `tests/`. Tests follow the same coding standards as runtime code.
- Excluded paths: `.git/`, `.github/`, `.idea/`, `.wordpress-org/`, `assets/`,
  `build/`, `languages/`, and `vendor/`.
- `.github/scripts/` is excluded from the default scan. Check changed PHP tooling
  explicitly with `php vendor/bin/phpcs --standard=phpcs.xml --ignore= <files>`.
- Full check: `composer phpcs`.
- Targeted check: `php vendor/bin/phpcs --standard=phpcs.xml <files>`.
- Apply automatic fixes with `php vendor/bin/phpcbf --standard=phpcs.xml <files>`
  only when ready to modify them; review the resulting diff and rerun checks.
- Output uses sniff codes and progress, PHP extensions only, relative paths, and
  up to 12 parallel processes where supported.

## Standards and compatibility
- WordPress Coding Standards (`WordPress`).
- `PHPCompatibility` and `PHPCompatibilityWP`, with `testVersion="7.4-"`.
- `minimum_supported_wp_version=6.0`.
- `WordPress.WP.I18n` requires the `kagg-compatibility` text domain.
- Standards and their dependencies are loaded from `vendor/`.

## Project conventions
The WordPress ruleset has these existing exclusions:

- `Generic.CodeAnalysis.UnusedFunctionParameter.Found` and `FoundAfterLastUsed`:
  unused parameters are allowed for hooks and interface signatures.
- `Universal.Arrays.DisallowShortArraySyntax.Found`: short arrays are allowed.
- `Universal.Operators.DisallowShortTernary.Found`: short ternaries are allowed.
- `WordPress.Files.FileName.InvalidClassFileName` and `NotHyphenatedLowercase`:
  PSR-4 class filenames are allowed.
- `Generic.Functions.CallTimePassByReference`: the deprecated check is disabled;
  do not introduce call-time references such as `some_function( &$value )`.

Other WPCS rules apply, including tabs, spacing, descriptive PHPDoc, Yoda
conditions, sanitization, escaping, and internationalization. Use snake_case
for project methods and variables; inherited framework methods retain their names.

## Complexity
`Generic.Metrics.CyclomaticComplexity` and `Generic.Metrics.NestingLevel` use
default thresholds. Prefer early returns and small helper methods over deep nesting.

## Tests and justified exceptions
- Do not disable documentation rules for standalone IDE suppression comments;
  put annotations in a PHPDoc block with a short description instead.
- Unit tests deliberately set WordPress version globals and install native PHP
  error handlers. Use a line-specific `phpcs:ignore` for the exact sniff, with
  a reason describing the fixture or assertion.
- Keep production and test files in the scan. Fix violations rather than lowering
  severities, excluding tests, or adding blanket `phpcs:disable` directives.
