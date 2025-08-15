# Elementor Widgets in Use
Adds a Widget Usage tab to Elementor Tools in the WordPress admin showing which Elementor widget elements are used and where.

## Instructions for use:
1. Drop the file into the plugins or mu-plugins folder of a WordPress install with the Elementor plugin enabled, or add as a code snippet.
2. In the admin, navigate to Elementor > Tools and click the Widget Usage tab to view a list of Elementor widgets used in your WordPress installation.

## Notes:
- Widgets in _italic_ are no longer registered but still referenced in the post/page(s) indicated, perhaps due to a plugin that's been disabled or uninstalled.
- The '?' icon before a post/page title is a tooltip - focus or mouseover the tooltip to see the page/post's post type and status.
- Post/page titles in _italic_ are not published.
- The 'x1', 'x2', x3' etc after a post/page title indicates the number of time the widget is used in that page.

## Tested on:
- PHP ver 8.4.x
- WordPress ver 6.8.2
- Elementor ver 3.31.2
