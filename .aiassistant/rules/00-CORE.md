---
apply: always
---

# Core rules

## Rule precedence
1. Project configuration: `phpcs.xml`, `composer.json`, and `phpunit.xml`.
2. `.aiassistant/rules/PHPCS.md` and `.aiassistant/rules/ESLINT.md`.
3. This file.

## General principles
- Follow the existing architecture and naming conventions.
- Prefer small, focused methods, early returns, and reusable code.
- Use descriptive class, method, parameter, hook, and file names.
- Write concise English comments with punctuation.
- Document classes, properties, methods, parameters, and return values in the
  existing PHPDoc style. Keep IDE annotations in descriptive PHPDoc blocks.
- Preserve behavior during style cleanup; avoid unrelated refactoring.

## PHP and WordPress
- Keep runtime code compatible with PHP 7.4+ and WordPress 6.0+.
- Use WordPress APIs where applicable and hooks for extensibility.
- Validate and sanitize external input; escape output for its destination.
- Verify nonces and capabilities for state-changing requests.
- Prefer WordPress query APIs; use `$wpdb->prepare()` for dynamic SQL.
- Translate user-facing strings with the `kagg-compatibility` text domain.
- Preserve the standalone MU error-handler payload: it loads before regular
  plugins and must not depend on this plugin's Composer autoloader.

## Tooling and verification
- PHP dependencies and scripts are managed by Composer.
- Style: `composer phpcs`; tests: `composer test`.
- The unit suite uses PHPUnit 9.6 and WP_Mock without WordPress or a database.
- JavaScript checks and conventions are in `ESLINT.md`; no JS build is configured.
- Run targeted checks for focused changes and full checks for plugin-wide work.
- Do not add repository-tracked local URLs, credentials, or environment secrets.
- Explain any necessary lint suppression at the narrowest possible scope.
