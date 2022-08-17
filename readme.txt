=== WooCommerce Zenkipay ===
Contributors: zenki
Tags: woocommerce, zenki, zenkipay, cryptocurrency, wallets, metamask, rainbow, muun, argent, payments, ecommerce, e-commerce, store, sales, sell, shop, shopping, cart, checkout
Requires at least: 5.3
Tested up to: 6.0.1
Requires PHP: 7.1
Stable tag: 1.6.4
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Zenkipay’s latest crypto payments processing solution. Accept any coin from any wallet. We support more than 150 wallets and the transaction is 100% secured. Want to learn [more](https://zenki.fi/)?

== Description ==

Zenkipay’s latest, most complete cryptocurrency payment processing solution. Accept any crypto coin with over 150 wallets around the world.

Built and supported by [Zenki](https://zenki.fi/).

= Give your customers a new experience to pay with any cryptos from any wallet with one single integration =

Streamline your business with one simple, powerful solution.

With the latest Zenkipay extension, your customers can pay with almost any wallet option and almost any cryptocurrency in just a few minutes on any device — all with one seamless checkout experience.

== Installation ==

= Requirements =

To install WooCommerce Zenkipay, you need:

* WordPress Version 5.3 or newer (installed).
* WooCommerce Version 3.9 or newer (installed and activated).
* PHP Version 7.1 or newer.
* Zenkipay merchat [account](https://zenki.fi/).

= Instructions =

1. Log in to WordPress admin.
2. Go to **Plugins > Add New**.
3. Search for the **Zenkipay** plugin.
4. Click on **Install Now** and wait until the plugin is installed successfully.
5. You can activate the plugin immediately by clicking on create your Zenkipay account [here](https://zenki.fi/).
6. Now on the success page. If you want to activate it later, you can do so via **Plugins > Installed Plugins**.

= Setup and Configuration =

Follow the steps below to connect the plugin to your Zenki account:

1. After you have activated the Zenkipay plugin, go to **WooCommerce  > Settings**.
2. Click the **Payments** tab.
3. The Payment methods list will include one Zenkipay options. Click on **Zenkipay** .
4. Enter your production/sadbox plugin key. If you do not have a Zenki merchant account, click **Create your Zenkipay account here**
5. After you have successfully obtained you plugin keys, click on the **Enable Zenkipay** checkbox to enable Zenkipay.
6. Click **Save changes**.

== Screenshots ==

1. Zenkipay button on checkout page.
2. Zenki payment gateway.
3. Main settings screen.
4. Enable "Zenkipay" on the Payment methods tab in WooCommerce.

== Changelog ==
= 1.6.4 =
* Added es_MX translations
= 1.6.3 =
* Replace text for Order Received Thank You
= 1.6.2 =
* Fix: Gateway URL was fixed 
= 1.6.1 =
* Webhook was implemented to change the order status to complete
* Capture order's zenkipay_tracking_number
= 1.5.0 =
* Updated purchaseOptions object structure
= 1.4.5 =
* Fix: If a product has a variation, the variation price is sent it in the purchaseData object
= 1.4.4 =
* Some console logs were removed and some CSS styles were added
= 1.4.3 =
* Fix: Added signature property to purchaseOptions object
= 1.4.2 =
* Fix: Zenkipay plugin was showing twice in the backoffice
= 1.4.0 =
* Modal payload data is signed with RSA-SHA256 algorithm
= 1.3.2 =
* Checkout content and style was updated
= 1.3.1 =
* Fixed bug when sandbox key was updated
= 1.3.0 =
* WooCommerce OrderId is sent to Zenkipay
= 1.2.0 =
* New PurchaseItem's properties were added when modal is launched
= 1.1.2 =
* Zenkipay key validation.
= 1.0.0 =
* Initial release.
