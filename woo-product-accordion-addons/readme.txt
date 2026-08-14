=== WooCommerce Product Accordion Add-ons ===
Contributors: globe2
Tags: woocommerce, product add-ons, cart
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.5
License: GPLv2 or later

Add optional WooCommerce products to a parent product. Add-ons are displayed above the main Add to Cart button as clean, minimalist accordions, allowing customers to select related hire items, upgrades or extras without leaving the page.

== Installation ==

1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Upload the plugin ZIP without extracting it, then activate it. WooCommerce must already be active.
3. Edit the main product and open Product data > Linked Products.
4. In Product add-ons, search for and select the products that should be offered as optional extras, then update the product.
5. View the main product page. The selected extras appear above its standard Add to Cart button.

== How it works ==

On the product page, each configured add-on has a larger checkbox, a price and a dropdown-arrow accordion control. Expanding it shows a one-paragraph product description, a small featured-image preview and a Find out more link that opens the add-on product in a new tab. Clicking the preview opens a larger lightbox image.

Selecting an add-on alone does not add anything to the cart. The selected add-ons are only added when the customer clicks the parent product's normal Add to Cart button. Each add-on uses a quantity of 1 by default; an administrator can optionally enable quantity selection for an individual add-on.

== Variable products ==

Variable products are supported as add-ons. They show a From price until selected. After the customer ticks the add-on, the variation dropdown appears in place of that price. The dropdown uses the product’s configured attribute label and displays each available variation name and price. A chosen variation is required before the parent product can be added to cart.

Every add-on defaults to quantity 1. Add-ons must be simple or variable purchasable products and in stock. The plugin uses a WordPress nonce and validates submitted IDs against the add-ons saved on the parent product, preventing a visitor from adding arbitrary products through the form.

== Advanced options ==

Under Product data > Linked Products, each selected add-on has optional overrides for its display name, description, quantity selection, price and allowed variations. A custom section heading can also be set for the parent product.

Under WooCommerce > Settings > Products > Accordion add-ons, you can set the default heading, decide whether linked add-ons should be removed when the parent cart item is removed, and apply a global fixed price or percentage discount. A product-level price adjustment overrides the global rule. Price adjustments are attached to the linked cart item only, and are removed with it.

For variable add-ons, you can limit the variations that may be chosen. If exactly one variation is allowed and in stock, it is selected automatically and its name is shown without a dropdown.

The plugin uses WooCommerce's normal cart actions. If your shop redirects to cart after adding a product, that redirect will continue to work and the chosen add-ons will already be in the cart.

== Notes ==

* Simple and variable products can be used as add-ons. Grouped and external products are not supported.
* The main product retains its normal chosen quantity. Each selected add-on is always quantity 1.
* Add-on IDs and chosen variations are validated server-side against the options saved on the parent product. The plugin uses WordPress nonces, stock and purchasability checks before adding anything to cart.
