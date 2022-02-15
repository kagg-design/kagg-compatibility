=== KAGG Compatibility ===
Contributors: kaggdesign
Tags: compatibility, PHP 8.1, development, deprecation, notice
Requires at least: 5.9
Tested up to: 5.9
Stable tag: 1.0.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin blocks PHP 8.1 deprecation messages from WordPress core, WooCommerce, Jetpack and other plugins.

== Description ==

[WordPress is not fully compatible with PHP 8.0 or 8.1](https://make.wordpress.org/core/2022/01/10/wordpress-5-9-and-php-8-0-8-1/). All remaining known PHP 8.1 issues are deprecation notices.

The same is related to many popular plugins such as WooCommerce, Jetpack and others. Mainly, they produce deprecation notices from the Action Scheduler library.

= Features =
* Plugin blocks all deprecation notices from WordPress core, WooCommerce, JetPack and many others using Action Scheduler library.
* Plugin filters out errors from these libraries only. Errors produced by the user code are not blocked, which helps to debug.
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

= 1.0.1 =
* Fixed creation of the mu-plugin folder if it does not exist.

= 1.0.0 =
* Initial release
