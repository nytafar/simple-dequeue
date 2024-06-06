# Simple Dequeue

**Contributors:** Lasse Jellum  
**Author URI:** https://jellum.net  
**Tags:** dequeue, performance, optimization, scripts, styles  
**Requires at least:** 4.6  
**Tested up to:** 6.2  
**Stable tag:** 1.4.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

## Description

Simple Dequeue is a WordPress plugin designed to help you manage the CSS and JS files enqueued by other plugins. It provides an easy-to-use interface to selectively disable or dequeue these assets, improving the performance and load times of your website.

I finally wrpte this plugin after years of searching out a myriad of assets in dev console to dequeue them manually and write my own styles.

### Features

- **Manage Dequeues**: View a list of all CSS and JS files enqueued by other plugins and selectively disable them based on different contexts (e.g., front page, single post, product page).
- **Dequeue Modes**:
  - **Direct from Settings**: Dequeue assets on the frontend according to your settings, ideal for building and testing.
  - **Theme Functions File**: Generate code to paste into your theme's `functions.php` file. The plugin will not dequeue assets by itself but will generate the necessary code. You can safely disable the plugin once the code is implemented.
  - **Direct File Mode**: Generate a file that is loaded on the frontend without accessing settings in the database for changes. This higher performance option bypasses the need to keep a copy (requires system write permissions).
- **Manual Dequeue Code**: Provides the code needed to manually dequeue selected assets, which can be copied to your theme's `functions.php` file.

## Installation

1. Upload the `simple-dequeue` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > Simple Dequeue** to configure the plugin.

## Usage

1. **Manage Dequeues**: 
   - View the list of enqueued assets.
   - Select the contexts in which you want to disable each asset.
   - Click "Save Changes" to apply the settings.

2. **Dequeue Modes**:
   - **Direct from Settings**: Choose this mode to manage dequeues directly from the plugin settings.
   - **Theme Functions File**: Generate the dequeue code and paste it into your theme's `functions.php` file.
   - **Direct File Mode**: Enable this mode to generate a file that will be loaded on the frontend. Note that this mode requires system write permissions.

3. **Manual Dequeue Code**:
   - Copy the generated code and paste it into your theme's `functions.php` file.
   - Disable the plugin after implementing the code.

## Translations

The plugin is fully translatable. Currently, it includes translations for:
- Norwegian Bokmål (`nb_NO`)

## Frequently Asked Questions

### What does this plugin do?
Simple Dequeue helps you manage and selectively disable or dequeue enqueued CSS and JS files from other plugins, improving your site's performance.

### How do I use the plugin?
After installing and activating the plugin, go to **Settings > Simple Dequeue** to configure which assets to dequeue and in which contexts.

### Can I disable the plugin after generating the code for my theme?
Yes, if you choose the "Theme Functions File" mode and paste the generated code into your theme's `functions.php` file, you can safely disable the plugin.

## Contributing

1. Fork the repository.
2. Create a new branch for your feature or bugfix.
3. Make your changes.
4. Submit a pull request.

## License

This plugin is licensed under the GPLv2 or later.  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

## Credits

Developed by Lasse Jellum.  
**Author URI:** https://jellum.net
