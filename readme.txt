=== CF7 & WooCommerce Google Sheet Connector ===
Contributors: MaiSyDat
Tags: contact form 7, woocommerce, google sheets, integration, forms, orders, crm
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Contact Form 7 and WooCommerce to Google Sheets automatically with lightweight JWT authentication.

== Description ==

CF7 & WooCommerce Google Sheet Connector is a WordPress plugin that automatically sends form submissions and WooCommerce orders to Google Sheets in real-time. The plugin uses lightweight JWT authentication (no external dependencies) and provides flexible field mapping for both Contact Form 7 and WooCommerce.

= Key Features =

* **Automatic Data Sync**: Automatically sends Contact Form 7 submissions and WooCommerce orders to Google Sheets
* **Lightweight JWT Authentication**: No external dependencies, uses native PHP OpenSSL for secure authentication
* **Flexible Field Mapping**: Customize which fields are sent to Google Sheets
* **Source Tracking**: Automatically tracks customer source (UTM parameters, referrers, social media platforms)
* **First Visit Attribution**: Captures and stores the first visit URL and referrer source (180-day persistence)
* **Real-time Updates**: Data is sent immediately when forms are submitted or orders are placed
* **Secure**: Uses Google Service Account authentication (no user tokens required)

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* OpenSSL extension (for JWT authentication)
* Contact Form 7 plugin
* WooCommerce plugin (optional, for order tracking)
* Google Cloud Project with Service Account and Google Sheets API enabled

= Installation =

1. Upload the plugin files to `/wp-content/plugins/cf7-woo-sheet-connector` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Connect to Google API service
4. Create a Google Service Account in Google Cloud Console and download the JSON key file
5. Paste the entire JSON content into the settings page
6. Test the connection using the "Check connection" button
7. Configure field mapping in Contact Form 7 or WooCommerce settings

= Frequently Asked Questions =

= How do I get Google Service Account credentials? =

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google Sheets API
4. Go to APIs & Services → Credentials
5. Create a new Service Account
6. Download the JSON key file
7. Share your Google Sheet with the service account email address

= Does this plugin require Composer or external libraries? =

No. This plugin uses lightweight JWT authentication with native PHP OpenSSL extension. No external dependencies are required.

= Can I customize which fields are sent to Google Sheets? =

Yes. You can configure field mapping in the Contact Form 7 settings or WooCommerce settings pages.

= How does source tracking work? =

The plugin automatically detects customer sources from:
* UTM parameters (utm_source, utm_medium, etc.)
* Ad click identifiers (fbclid, gclid, etc.)
* Referrer domains (Google, Facebook, Instagram, etc.)
* First visit URL and referrer (stored for 180 days)

= Changelog =

= 1.2.2 =
* Security improvements: Added nonce verification for all AJAX handlers
* Code cleanup: Removed unnecessary platforms from source detection (X/Twitter, Bing, Tiki, Lazada, Sendo, Chotot)
* Enhanced security: Improved input sanitization and validation
* WordPress.org compliance: Full compliance with WordPress.org plugin submission requirements

= 1.1.0 =
* Refactored to use lightweight JWT authentication (no external dependencies)
* Improved first visit tracking with 180-day cookie persistence
* Enhanced source detection for multiple platforms
* Fixed order-link tracking for WooCommerce orders
* Updated all Vietnamese text to English for internationalization

= 1.0.0 =
* Initial release
* Contact Form 7 integration
* WooCommerce integration
* Field mapping customization
* Source tracking

== Upgrade Notice ==

= 1.2.2 =
Security update: Enhanced security with improved nonce verification and input sanitization. Recommended for all users.

= 1.1.0 =
Major update: Plugin now uses lightweight JWT authentication. No external dependencies required. All Vietnamese text has been translated to English.

== Screenshots ==

1. Settings page for Google Service Account configuration
2. Contact Form 7 integration panel
3. WooCommerce integration settings

== Support ==

For support, please visit the plugin's support forum or contact the plugin author.

