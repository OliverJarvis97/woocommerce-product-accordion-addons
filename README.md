# WooCommerce Product Accordion Add-ons

A lightweight WooCommerce plugin for offering related products as optional add-ons directly above a product’s normal **Add to Cart** button.

Selected add-ons are added only when the customer adds the parent product to the cart. The default add-on quantity is one, preserving the original simple hire-item workflow.

## Features

- Configure add-ons per product under **Product data → Linked Products**.
- Minimal accordion interface, image lightbox and Find out more link.
- Optional per-add-on display-name and description overrides.
- Optional section-heading override per parent product.
- Per-add-on quantity controls, disabled by default.
- Global and per-add-on price adjustments: product price, fixed price or percentage discount.
- Optional automatic removal of linked add-ons when the parent product is removed from the cart.
- Simple and variable add-on support.
- Restrict selectable variations per parent product.
- If only one variation is allowed, it is chosen automatically and its name is shown without a dropdown.
- Server-side nonce, product, variation, stock and purchasability validation.

## Configuration

1. Go to **WooCommerce → Settings → Products → Accordion add-ons** for global defaults:
   - default section title;
   - removal of linked add-ons with the parent;
   - global fixed-price or percentage-discount rule.
2. Edit a parent product and open **Product data → Linked Products**.
3. Choose the products to offer, save once, then configure the optional fields shown for each selected add-on.
4. For variable add-ons, choose the allowed variations. Leave this empty to allow all currently purchasable variations.

A product-level price rule overrides the global price rule. Adjusted pricing exists only on the linked cart line: it does not change the add-on product’s catalogue price.

## Installation

1. Download the ZIP from the latest [GitHub Release](../../releases/latest).
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP as downloaded and activate it.

## Updates

The plugin checks the latest GitHub Release through WordPress’s standard update process and has no custom release cache. Publish a GitHub Release with a matching version tag; the included workflow attaches the installable plugin ZIP.
