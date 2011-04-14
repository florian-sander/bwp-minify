=== Better WordPress Minify ===
Contributors: OddOneOut
Donate link: http://betterwp.net/wordpress-plugins/bwp-minify/
Tags: CSS, javascript, JS, minify, minification, optimization, optimize
Requires at least: 2.8
Tested up to: 3.1.1
Stable tag: 1.0.3

Allows you to minify your CSS and JS files for faster page loading for visitors.

== Description ==

Allows you to minify your CSS and JS files for faster page loading for visitors. This plugin uses the PHP library [Minify](http://code.google.com/p/minify/) and relies on WordPress's enqueueing system rather than the output buffer (will not break your website in most cases). This plugin is very customizable and easy to use.

**Some Features**

* Uses the enqueueing system of WordPress which improves compatibility with other plugins and themes
* Allows you to customize all minify strings
* Offers various way to add a cache buster to your minify string
* Gives you total control over how this plugin minifies your scripts
* Supports script localization (`wp_localize_script()`)
* Supports RTL stylesheets
* Supports media-specific stylesheets (e.g. 'screen', 'print', etc.)
* Supports conditional stylesheets (e.g. `<!--[if lt IE 7]>`)
* Provides hooks for further customization
* WordPress Multi-site compatible (not tested with WPMU)

**Languages**

* This plugin is currently available only in English. Please [help translate](http://betterwp.net/wordpress-tips/create-pot-file-using-poedit/) it!

**Important Notes**

The cache folder must be writable, please visit [Plugin's Official Page](http://betterwp.net/wordpress-plugins/bwp-minify/) for more information!

== Installation ==

1. Upload the `bwp-minify` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the Plugins menu in WordPress. After activation, you should see a menu of this plugin under Settings.
3. Configure the plugin, optionally choose a minify URL (ony recommended if you are experienced with URL and server paths), and modify Minify's `config.php` as you please. Please read [here](http://betterwp.net/wordpress-plugins/bwp-minify/#customization) for more information.
4. Make sure the `cache` folder is writable, by `CHMOD` it to either `755` or `777`, depending on which one will work for you.
5. Enjoy!

== Frequently Asked Questions ==

[Check plugin news and ask questions](http://betterwp.net/topic/bwp-minify/).

== Screenshots ==

1. Changing the Minify URL, now one can have a shorter and nicer URL ;)

== Changelog ==

= 1.0.3 =
* Fixed a compatibility issue with dynamically generated media files, thanks to naimer!
* Not really a changelog, but [a small snippet](http://betterwp.net/wordpress-plugins/bwp-minify/#positioning-your-scripts) for users who want to exclude CSS files has been posted.

= 1.0.2 =
* Fixed a compatibility issue with other plugins loading styles and scripts on a separate .php page. Thanks to larry!
* Also fixed a possible bug in 1.0.1

= 1.0.1 =
* The plugin should now detect cache folder correctly for users who install WordPress in a sub-directory.

= 1.0.0 =
* Initial Release.

== Upgrade Notice ==

= 1.0.0 =
* Enjoy the plugin!