=== Instant Quote Form ===
Contributors: halevanthien
Tags: quote, estimate, booking, form, calculator
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A quote request form with a live price estimate, a private inbox for requests, email alerts and editable rates.

== Description ==

Add the Instant Quote block, or the [instant_quote] shortcode, to any page. Visitors pick a service, enter the size of the job and see a price estimate update as they type. When they send it, the request lands under Quote requests in the admin with its estimate and a status you can change, and an email goes to you and, if you like, a copy to the customer.

This is a sample plugin built for a fictional window and gutter cleaning company.

Security: every submit is checked with a nonce, a hidden honeypot field and a per-connection limit of five requests an hour. The estimate is always recalculated on the server. Settings need the manage_options capability, and status changes are nonce and capability checked.

== Installation ==

1. Upload the plugin folder or the zip under Plugins, Add New, Upload.
2. Activate it, then open Settings, Instant Quote to set your rates and the email that receives requests.
3. Add the Instant Quote block to a page.

== Frequently Asked Questions ==

= Is anything left behind when I delete the plugin? =

No. Deleting it removes its settings, every stored request and its temporary data.

== Changelog ==

= 1.1.0 =
* One-card calculator: house type as line drawings, a window stepper and a live price that rolls on change.
* Bundled Familjen Grotesk and Source Sans 3 (SIL Open Font License).

= 1.0.0 =
* First release.
