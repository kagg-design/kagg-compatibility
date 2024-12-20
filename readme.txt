=== KAGG Compatibility ===
Contributors: kaggdesign
Tags: compatibility, development, deprecated, notice, warning
Requires at least: 5.9
Tested up to: 6.7
Stable tag: 2.4.3
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin blocks error messages of any level from WordPress core, plugins, and themes.

== Description ==

[WordPress is not fully compatible with PHP 8.0 - 8.4](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/). Remaining known PHP 8.0+ issues are deprecation notices.

The same is related to many popular plugins such as WooCommerce, Jetpack, Gravity Forms and others. Mainly, they produce deprecation notices from the Action Scheduler library.

= Features =
* The plugin blocks deprecation notices, user deprecation notices, notices, warnings, user notices, and user warnings.
* The list of folders from which errors are blocked can be filtered. This list may specify any WordPress Core, plugin and theme folders.
* Plugin filters out errors from these folders only. Errors produced by other code are not blocked, which helps in debugging user code.
* During activation, plugin installs a mu-plugin `kagg-compatibility-error-handler.php` into the `/wp-content/mu-plugins/` folder. It contains the error handler, which loads earlier than any plugin or theme.

== Plugin Support ==

* [Support Forum](https://wordpress.org/support/plugin/kagg-compatibility/)

== Installation ==

1. Upload `kagg-compatibility` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

= Can I contribute? =

Yes, you can!

* Join in on our [GitHub repository](https://github.com/kagg-design/kagg-compatibility)

== Changelog ==

= 2.4.3 =
* Improved error handling with Query Monitor.
* Improved error handling with Action Scheduler.

= 2.4.2 =
* Improved error handling with Uncanny Automator.

= 2.4.0 =
* Added KAGG_DISABLE_ERROR_HANDLER constant to disable the error handler.
* Added blocking of WPForms error handlers to prevent conflicts.
* Improved chaining of error handlers.
* Changed wpf_error_handler_level filter name to kagg_compatibility_levels.
* Fixed skipping suppression of some errors.

= 2.3.0 =
* Tested with PHP 8.4.
* Tested with WordPress 6.7.

= 2.2.0 =
* Dropped support for PHP 7.0 and 7.1. The minimum required PHP version is now 7.2.
* Add plugins and themes to the default settings.
* Tested with WordPress 6.6.
* Tested with WooCommerce 9.1.

= 2.1.0 =
* Tested with WordPress 6.5.
* Tested with WooCommerce 8.6.

= 2.0.3 =
* Added normalization of dirs to handle errors from.
* Added plugins' directories to the default list.

= 2.0.2 =
* Fixed improper display of the "rate plugin" message on options.php.
* Fixed fatal error with improperly coded third-party error handlers.

= 2.0.1 =
* Fixed conflict with hCaptcha plugin.

= 2.0.0 =
* Tested with WordPress 6.4.
* Added settings page to specify the list of directories to process errors from.
* Added ability to chain error handlers.
* Added compatibility with Query Monitor.
* Added filter for folders.
* Added filter for error levels.

= 1.3.0 =
* Tested with WordPress 6.3.

= 1.2.0 =
* Tested with WordPress 6.2.
* Tested with PHP 8.2.

= 1.1.0 =
* Tested with WordPress 6.0.

= 1.0.1 =
* Fixed creation of the mu-plugin folder if it does not exist.

= 1.0.0 =
* Initial release.
