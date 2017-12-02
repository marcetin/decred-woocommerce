=== Decred Payments for WooCommerce ===
Contributors: Decred, marcetin.com
Donate link: http://marcetin.com
Tags: decred , decred wordpress, plugin , bitcoin plugin, decred payments, accept decred, bitcoins , accept decred , decreds
Requires at least: 3.0.1
Tested up to: 3.9
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html



Decred Payments for WooCommerce is a Wordpress plugin that allows to accept decred at WooCommerce-powered online stores.

== Description ==
Based in Bitcoin Payments for WooCommerce
Your online store must use WooCommerce platform (free wordpress plugin).
Once you installed and activated WooCommerce, you may install and activate Decred Payments for WooCommerce.


= Benefits =

* Fully automatic operation
* 100% hack secure - by design it is impossible for hacker to steal your Decred even if your whole server and database will be hacked.
* 100% safe against losses - no private keys are required or kept anywhere at your online store server.
* Accept payments in Decred directly into your personal wallet.
* Wallet can stay in another server.
* Accept payment in Decred for physical and digital downloadable products.
* Add Decred payments option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Zero fees and no commissions for Decred payments processing from any third party.
* Support for many currencies.
* Set main currency of your store in any currency or Decred.
* Automatic conversion to Decred via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


== Installation ==
Before you start, you must configure a RPC Decred Wallet in Windows/Linux or Mac with this settings in decred.conf:
Example:

rpcuser=Awesome
rpcpassword=Superpass
daemon=1
server=1
rpcport=12615
port=12614
rpcallowip=127.0.0.1
rpcssl=1
addnode=107.170.83.208

If you wanna use a ssl Certificate you must create with this intructions if not skip this step:

****https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon


1.  Install WooCommerce plugin and configure your store (if you haven't done so already - http://wordpress.org/plugins/woocommerce/).
2.  Install "Decred Payments for WooCommerce" wordpress plugin just like any other Wordpress plugin.
3.  Activate.
4.  Run and setup your wallet.
5.  Click on "Console" tab and run this command (to extend the size of wallet's gap limit): wallet.storage.put('gap_limit',100)
6.  Within your site's Wordpress admin, navigate to:
	    WooCommerce -> Settings -> Checkout -> Decred
7.  Fill:
    Decred RPC host address: RPC/Wallet server address
    Decred RPC Port: RPC por used : Example = 12615
    Decred RPC SSL connect: Check this options if you have ssl enabled in RPC
    Decred RPC SSL connect: Select the path to certificate server
    Decred RPC username: username used in your RPC server
    Decred RPC password: password used in your RPC server
    Decred address Prefix:The prefix for the address labels.The account will be in the form
8.  Press [Save changes]
9. If you do not see any errors - your store is ready for operation and to access payments in Decreds!
10. Please donate DECRED to:  D8UWWqaUtZ8TtWkHvJtbF3jFJqCtMdAdKE
    
All supporters will be in marcetin.com


== Screenshots ==

1. Checkout with option for Decred payment.
2. Order received screen, Decred address and payment amount.
3. Decred Gateway settings screen.


== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

*Decred: http://wordpress.org/support/profile/marcetin

== Changelog ==

= 0.30 =


== Upgrade Notice ==

soon

== Frequently Asked Questions ==

soon