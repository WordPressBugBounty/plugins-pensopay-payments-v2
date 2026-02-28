=== pensopay Payments v2 ===
Contributors: pensopay
Tags: gateway, woocommerce, pensopay, payment, psp
Requires at least: 6.3
Tested up to: 6.8.2
Stable tag: 2.0.6
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrates your pensopay V2 payment gateway into your WooCommerce installation.

== Description ==
<u>Important: This plugin supports pensopay v2 payments via app.pensopay.com. If you are not sure it’s the right one, please reach out to us before proceeding.</u>

With pensopay payments v2 you are able to integrate pensopay v2 gateway with your WooCommerce.
In moments you will be able to receive payments via credit card, MobilePay, Apple Pay,  Viabill, Anyday, Swish.

Capture, refund and cancel payments directly from your WooCommerce Store.

This plugin is free to use, but you will need a pensopay account to accept payments.
<a href="https://pensopay.com/">https://pensopay.com/</a>
Via pensopay you can apply for an instant Visa/MasterCard agreement, which allows you to accept online payments in a matter of minutes.

Service Terms: https://pensopay.com/handelsbetingelser/


== Installation ==
1. Upload the 'pensopay-payments-v2' folder to /wp-content/plugins/ on your server
2. Log in to WordPress administration, click on the ‘Plugins’ tab
3. Find pensopay Payments v2 in the plugin overview and activate it
4. Go to WooCommerce -> Settings -> Payment Gateways -> pensopay credit cards
5. Fill in keys from app.pensopay.com and configure your settings in the plugin
6. Receive payments via pensopay

== Dependencies ==
General:
1. PHP: >= 7.4
2. WooCommerce >= 8.2

== Changelog ==
= 2.0.6 =
* Fix: Race condition when autocapture is set to on, causing stock reservation to sometimes reserve double the stock.
* Fix: KnowledgeBase URL
* Fix: Typo in plugin text-domain in pensopay.php
* Fix: Payment colors to match statuses in woocommerce

= 2.0.5 =
* Fix: Stripe Klarna complete list of countries/currencies.
* Fix: Obey complete_on_capture setting.
* Fix: Admin panel transaction status fix.

= 2.0.4 =
* NOTICE! After updating from a prior version, you may need to click Activate on the pensopay Payments v2 plugin again. Refresh the plugins page after updating.
* Fix: 7.4 compatibility issue on the settings function.

= 2.0.3 =
* Fix: Retained old entry point for the plugin
* Fix: Klarna cart calculation issue by removing optional basket

= 2.0.2 =
* Fix: use two letter country code in addresses.

= 2.0.1 =
* Fix for calculated store language setting.

= 2.0.0 =
* Redo code structure so it's cleaner.
* Remove unused and unnecessary classes.
* Merge classes where possible to reduce the amount of micro-classes.
* Globalize some variables for central control (f.x Pensopay_Payments_V2_Gateway::TEXT_DOMAIN & SETTINGS_DOMAIN).
* Split logic into basic initiator class for the module, and core functionality initialization. Smaller and simpler to maintain.
* Standardized event names.
* Cleared up duplicate functions.
* Gateway.
* Handle subscription payment event.
* Fix language setting in admin to map to the proper locales we need and support.
* Fix issue with subscriptions when updating transaction id and the status of its order(s).
* Fix issue with exceptions showing in the admin panel because of difference between exceptions thrown and handled.
* Fix issue with duplicate hooks on the creditcard instance, occuring from multiple instance initialization.
* Fix amounts in all cases (thousands issue).
* Change the last operation calculation to use the highest id instead of time, as two operations can happen at the same second.
* Change logging option to default to true.
* Cleaned up exceptions to remove unused code and corrected the self-logging functionality.
* Fix for slow loading pages when the pensopay API is malperforming by setting a low timeout and preventing requests that will fail.
* Cleaned up logs by preventing multiple log entries for the same occurrence of an issue.
* Style the settings page so it looks better.
* Rearrange settings in the settings page for better organization.
* Set timeout for GET requests to 2s and everything else to 30.
* Ensure an amount is always sent to the payment gateway for a subscription.
* Fix decimal issue when capturing a subscription.
* Module now prevents auto update on major versions.
* On subscriptions, authorize the order amount as a separate payment to keep track of instead of working with order amounts.
* Migrate settings to a class
* Further develop the payment and subscription object implementations
* Panel fixes
* Add separate subscription table panel
* More functional storage of pensopay values on order objects
* Ensure transaction id fetching and storage is universal
* Distinguish between payment and subscription objects where necessary
* Quick order actions fixes
* Add iDEAL checkout method
* Add Klarna checkout method
* Add woocommerce in the list of required modules

= 1.1.3 =
* Prevent automatic updates for major releases.

= 1.1.2 =
* Bump wordpress tested version

= 1.1.1 =
* Add VIPPS PSP
* Update VISA logo
* Add Google Pay logo to credit card payment method icon options

= 1.1.0 =
* Fixed a bug where the payment status on the order list page would not always match the latest one in the gateway, while
  the order page itself would properly reflect it.

= 1.0.9 =
* Fixed a bug where the payment status rendering would crash on the orders page if the payment was not found.

= 1.0.8 =
* Added text guideline for testmode under private key.
* Added feature to be able to auto complete an order on capture callback.
* Fixed an issue with order_id reuse on checkout when a customer navigated away from the payment window and tried to come back through it through the saved session in checkout.
* Removed automatic currency as it was an artifact that did not serve a use.

= 1.0.7 =
* Fix: 20px instead of 50px default for payment logos
* Internationalization (DA) fixes, translations were not being applied
* Fix amount (from 1.0.6) in all cases.
* Fix a styling issue in the admin panel for larger amounts on the payment box inside an order.
* Obey the current store language for the payment window
* Remove the language option from the settings page, as it is not needed anymore.

= 1.0.6 =
* Fix: order note wrongfully displayed amount in cents

= 1.0.5 =
* Add google pay support
* Remove deprecated function calls.

= 1.0.4 =
* Fix for mobilepay showing when it shouldn't
* Fix for default logo size to be smaller in admin and checkout
* Fix for when using the back button in the payment gateway to be able to alternate between payment methods instead of
  being forced to the initial choice.
* Default description for MobilePay is now in Danish, unless overriden by a saved option.

= 1.0.3 =
* Fix a bug where the payment assistant meta_box did not show on non-HPOS stores.
* Fix a bug where payment method icons did not properly render due to a URL typo when publishing the module.

= 1.0.2 =
* Fix Pensopay Exception classes overlap with old module.
* Add wp-json callback URL for woocommerce >= 9.0.
* Fix log filename to match module.
* WC, WP tested and required bumps.
* Support URL typo fix from copying over.

= 1.0.1 =
* Updated dependencies + readme

= 1.0.0 =
* Initial release