=== EasyIndex ===

Contributors: Jayce53
Tags: index, recipe index, product index, thumbnail gallery, post gallery, visual index, post index
Requires at least: 3.9
Tested up to: 5.3
Stable tag: 1.1.1704

Wordpress indexes made easy! EasyIndex makes post indexes, recipe indexes, product indexes and more in just minutes. Easy to use, easy to customize.

== Description ==

It's EASY to create an index on your WordPress blog with EasyIndex. It works right out of the box.

Most other index solutions require you to laboriously select and add each category or tag you want to index , or even look up category and post IDs.
With EasyIndex, you just select  the things you want to index from a list and the plugin can create an index almost instantly.

[youtube https://www.youtube.com/watch?v=aXT0PJxywWA]

With other index solutions, you are stuck with just one format - EasyIndex has 9 index styles (26 in the Plus version) that you can easily customize with different fonts, colors, text styles and HTML tags.

You can easily add indexes to your menu, and if you want, EasyIndex will automatically make submenu items as required.

And according to our Beta Testers, the link to a relevant help page that's next to every field on the setup screen really made using EasyIndex a breeze.

As well as generating indexes, [EasyIndex Plus](https://easyindexplugin.com) creates customizable sidebar photo galleries  - which you can also embed in posts using a shortcode.

Want a gallery of your latest posts but only include posts from selected categories? EasyIndex Plus can do that with just a few clicks.

Would you like to make a Pinterest style gallery from your posts? If you've got EasyIndex Plus and a spare 30 seconds, you're done!

[youtube https://www.youtube.com/watch?v=aR18dS3tZ5Q]


== Installation ==

= Using the WordPress Dashboard =

1. Login in to admin on your blog
2. Go to *Plugins*
3. Select *Add New*
4. Search for EasyIndex
5. Select *Install*
6. Select *Install Now*
7. Once the plugin has been uploaded, select *Activate Plugin*

= Manual =

1. Download and unzip the plugin into a local folder
2. Upload the entire EasyIndex directory to the wp-content/plugins directory
3. Activate the plugin through the Plugins menu in WordPress

== Frequently Asked Questions ==

= How does EasyIndex work? =

EasyIndex reads all the posts in a selected taxonomy (i.e. categories, tags or other any other grouping your theme or plugins may have defined)
and filters them by terms you select. It automatically scans each post for a suitable image to make a thumbnail from and then displays all the thumbnails grouped by terms, or in an ungrouped gallery</p>

= What does EasyIndex do that WordPress can’t? =

EasyIndex makes it really easy to select exactly what it is you want to index (you don't have to know post or category IDs) and then lets you format your index just the way you want it.

= Can I use EasyIndex "out of the box"? =

Absolutely!

You can create an index in just a few minutes with minimal setup.  All you need to do is click "Add new" index, select what it is you want to index, and hit "Publish". Job done!

There's all sorts of goodies that you can adjust later, but you can create a serviceable index right out of the box.

= How can I customize EasyIndex? =

There are 4 levels of customization in EasyIndex.

First you can decide the style of index you want. EasyIndex has 9 styles you can choose from - EasyIndex Plus has 26 styles, including Pinterest style galleries.

Second, although EasyIndex comes standard with default formatting that will work "out of the box", you can easily change things like HTML tags, fonts and colors used for headings and text.

Third, you can add your own custom CSS if you want extra control.

Finally, [Live Formatting](https://easyindexplugin.com/live-formatting.php) in EasyIndex Plus lets you change almost every CSS style on every index element and you can see the effect of your changes as you make them

= Is it supported? =

We have a [support site](http://support.easyindexplugin.com) where you can read the documentation, view instructional videos and post support tickets.

For EasyIndex Plus users, we'll do our best to answer every support ticket within 24 hours (it may take a little longer on weekends and holidays).

We'll try to get to support tickets from users of the free plugin within a week.

== Screenshots ==
1. Sample index
2. Sample index
3. Sample index
4. Sample index
5. Sample index
6. Sample index
7. Sample index
8. Sample gallery
9. Sample text index
10. Sample text index
11. Sample text index
12. Simple style selection
13. Index options
14. Easy to select what it is you want to index

== Changelog ==

= 1.1.1704 =
Update: Prevent the Polylang plugin translating index terms which causes incorrect selection of posts for indexes
Update: Tested with WordPress 5.3

= 1.1 Build 1703 =
Update: Better handling of plugin link actions to prevent errors from plugins that trash the $links array (e.g. WP Editor)
Fix: A later post with no appropriate image could in some circumstances prevent an earlier post with a valid image from being used


= 1.1 Build 1701 =
Fix: Fix error from last update that caused non "post" type items from being indexed
Fix: Fix for crash when an empty image src was specified in microdata markup

= 1.1 Build 1699 =
Update: Tested with WordPress 5.2.2
Update: Change to work with Revision Manager TMC plugin
Fix:  Minor fix for index html caching

= 1.1 Build 1698 =
Update: Workarounds for PHP 7 compatibility issues incorrectly reported by PHP Code Sniffer "PHPCompatibility" checks

= 1.1 Build 1697 =
Update: Fix to work with plugins that persist the WP object cache

= 1.1 Build 1696 =
Update: Tested with WordPress 5.2.1
Update: Use WP object cache to prevent indexes being generated multiple times per page view
Update: Add thumbnail directory URL setting and correctly discover URL when wp-content location is not the same as WP core (notably Pressable sites)
Fix: Better discovery of image source for explicity set index images

= 1.1 Build 1695 =
Update: Tested with WordPress 5.0.3
Fix: Primary category selection was incorrectly removing some posts from indexes

= 1.1 Build 1693 =
Update: Tested with WordPress 5.0.1
Update: Only select posts for their "primary" category index when indexing categories, Yoast SEO is present and "Display duplicates" is "No"
Fix: Fix PHP warning on posts/pages using an EasyIndex widget shortcode

= 1.1 Build 1690 =
Update: Set secondary index titles correctly when Yoast SEO plugin is present

= 1.1 Build 1689 =
Fix: Fix missing update code for "other plugin" content in previous update

= 1.1 Build 1688 =
Update: Allow other plugins (e.g. Quick Adsense) to insert stuff above and below the index content
Update: Option to disable Jetpack CDN for index thumbnails
Fix: Fix syntax error when with PHP < 5.4

= 1.1.1684 =
Update: Re-scan for image sources if "Regenerate existing" checked on thumb generation
Fix: Recognise image urls which have no protocol (http: or https:)

= 1.1 Build 1678 =
* Update: Tested with WP 4.9.8
* Update: Better error messages on license activation errors
* Fix: A post's "Index Image" was sometimes lost when the post was published.
* Fix: PHP warning when new thumbnail directory setting saved
* Fix: Buttons not displayed on color picker

= 1.1 Build 1670 =
* Update: Tested with WP 4.9.6

= 1.1 Build 1669 =
* Update: Tested with WP 4.9.5
* Fix to work with Phlox theme
* Fix warning on settings page

= 1.1 Build 1666 =
* Update: Tested with WP 4.9
* Fix: Fix for cutomizer glitch
* Fix: Save settings if thumbnail directory changes from http:// to https://

= 1.1 Build 1663 =
* Update: Tested with WP 4.7
* Update: Added an option to allow duplicates on "Sample of posts" and Text indexes
* Update: Resolved a clash with the GK Widget Rules plugin (Plus version)
* Update: Allow more than 3 levels of hierachy on taxonomies
* Update: Added an option to re-count taxonomy terms
* Fix: Fix settings link on Help page
* Fix: Fixed issues with special characters in widget name and "More" text (Plus version)
* Fix: Minor display issue on taxonomy term names in index edit

= 1.0 Build 1637 =
* Update: Cleaned up UI CSS
* Update: More explicit UI CSS
* Update: Added PHP memory limit option for thumbnail creation
* Update: Added new gallery style (Plus)
* Update: Added capability to adjust title height on basic formatting
* Update: Suppress errors from the GD library
* Fix: Hardcoded table prefix in Widget.php (Plus)

= 1.0 Build 1605 =
* Fix: Fix thumbnail generate in Plus version

= 1.0 Build 1602 =
* Fix: Fix thumbnail generate in Plus version

= 1.0 Build 1600 =
* Fix: Fix warning in customiseTitle()

= 1.0 Build 1599 =
* Fix: Force thumb regenerate when source image changes
* Fix: Fix index links for some permalink settings
* Fix: Fix index links for non root WordPress installs
* Update: Added debug logging

= 1.0 Build 1574 =
* Update: Include extra screenshot in readme.txt (Free version)
* Update: Prevent an image being shown twice on an index
* Update: Made the instructions for thumbnail preselection clearer
* Minor fix: Remove references to license key in settings save (Free version)

= 1.0 Build 1570 =
* Update: Minor changes for wordpress.org compliance

= 1.0 Build 1569 =
First release

