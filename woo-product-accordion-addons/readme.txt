=== WooCommerce Product Accordion Add-ons ===
Contributors: globe2
Tags: woocommerce, product add-ons, cart
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Add optional WooCommerce products to a parent product, displayed above the main Add to Cart button as minimalist accordions.

== Installation ==

1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Upload the ZIP and activate it. WooCommerce must already be active.
3. Edit a product and open Product data > Linked Products.
4. Use Product add-ons to search for and choose its optional add-on products, then Update.

== How it works ==

On the product page, each configured add-on is a checkbox within an accordion. Its featured image appears beside its short description once expanded; clicking the small thumbnail opens a larger image lightbox. Selecting a checkbox alone does nothing. The selected add-ons are added only when the customer clicks the parent product's normal Add to Cart button.

Every add-on is added with quantity 1. Add-ons must be simple products (or other non-variable purchasable products) and in stock. The plugin uses a WordPress nonce and validates submitted IDs against the add-ons saved on the parent product, preventing a visitor from adding arbitrary products through the form.

The plugin uses WooCommerce's normal cart actions. If your shop redirects to cart after adding a product, that redirect will continue to work and the chosen add-ons will already be in the cart.

== Notes ==

* Variable, grouped and external products are not offered as add-ons because they need further customer choices.
* The main product retains its normal chosen quantity. Each selected add-on is always quantity 1.
