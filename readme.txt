=== Noreferrer ===
Contributors: andersju
Tags: noreferrer, referrer, referer, rel, privacy, links
Requires at least: 3.0
Tested up to: 4.2.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple privacy-enhancing plugin that adds rel="noreferrer" to external links in posts, pages and comments.

== Description ==

As defined in the [HTML5 spec](http://www.w3.org/TR/html5/links.html#link-type-noreferrer), `rel="noreferrer"` "indicates that no referrer information is to be leaked when following the link".

This plugin modifies external links right before they are displayed. It doesn't modify anything in the database. Existing `rel` attributes, such as the one set by `wp_rel_nofollow()`, are preserved.

There are currently no options: just activate it and you're done.

The `rel="noreferrer"` attribute is supported by Firefox (since [version 33](https://developer.mozilla.org/en-US/Firefox/Releases/33#HTML)) and Chrome/Safari (added to WebKit in [November 2009](https://www.webkit.org/blog/907/webkit-nightlies-support-html5-noreferrer-link-relation/)). It's not supported by Internet Explorer.

Inspired by the Drupal module [No referrer](https://www.drupal.org/project/noreferrer).

The code is available on [GitHub](https://github.com/andersju/noreferrer).

== Installation ==

1. Download the latest zip file and extract the `noreferrer` directory.
2. Upload it to your `/wp-content/plugins/` directory.
3. Activate Noreferrer through the Plugins menu in WordPress.

== Frequently Asked Questions ==

= Why should I use this? =

Because you might care about the privacy of your users.

== Changelog ==

= 1.0.0 =
* Initial release.
