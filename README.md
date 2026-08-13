# WooCommerce Product Accordion Add-ons

A lightweight WooCommerce plugin for offering related products as optional add-ons directly above a product’s normal **Add to Cart** button.

Customers choose the extras they want, then add the main product as usual. Selected add-ons are added to the cart only as part of that main add-to-cart action, each with a fixed quantity of one.

## Features

- Select add-on products from **Product data → Linked Products** on each parent product.
- Compact, minimalist accordion interface above the main Add to Cart button.
- Larger, easier-to-use checkbox and a clear dropdown-arrow accordion control.
- Optional add-ons are not added until the customer clicks the parent product’s Add to Cart button.
- Add-ons are always added with quantity **1**, independently of the parent product quantity.
- Featured-image thumbnail beside the add-on description, with a click-to-enlarge lightbox.
- One-paragraph short description and a **Find out more** link that opens the add-on product in a new tab.
- Supports simple and variable WooCommerce products.
- Variable add-ons show a **From** price; when selected, the variation selector appears in its place.
- Selected variable add-ons require a valid variation before the main product can be added to cart.
- Works with shops that redirect customers to the cart after adding a product.

## Requirements

- WordPress 6.4 or later
- WooCommerce active
- PHP 7.4 or later

## Installation

1. Download the ZIP attached to the latest [GitHub Release](../../releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP exactly as downloaded; do not extract it first.
4. Activate the plugin.

## Configuring add-ons

1. Edit the product that should offer extras.
2. Open **Product data → Linked Products**.
3. Use **Product add-ons** to search for and select the products to offer.
4. Update the parent product.

The selected products will now appear above the normal Add to Cart button on that product page.

## Variable-product add-ons

Variable products can be selected as add-ons. Their minimum price is shown until the customer ticks the add-on. The available variation dropdown then replaces the displayed price.

The dropdown uses the product’s configured attribute label and lists the variation name and price. A variation must be selected for every ticked variable add-on before the main product can be added to cart.

## Security and validation

The plugin uses a WordPress nonce and validates submitted add-on IDs against the add-ons configured on the parent product. It also checks product type, stock, purchasability and the selected variation on the server before anything is added to the cart. Grouped and external products are not supported as add-ons.

## Updates

The plugin checks the latest GitHub Release through the standard WordPress plugin-update process. It does not keep its own GitHub-release cache, so it will use the current release whenever WordPress runs an update check.
