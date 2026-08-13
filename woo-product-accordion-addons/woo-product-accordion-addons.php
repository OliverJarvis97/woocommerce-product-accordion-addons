<?php
/**
 * Plugin Name: WooCommerce Product Accordion Add-ons
 * Description: Select WooCommerce products as optional add-ons. They appear as compact accordions above the main Add to Cart button and are only added when that main product is submitted.
 * Version: 1.6.3
 * Author: Globe2
 * Update URI: https://github.com/OliverJarvis97/woocommerce-product-accordion-addons
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: wc-product-accordion-addons
 */

defined( 'ABSPATH' ) || exit;

final class G2_WC_Product_Accordion_Addons {
	const META_KEY = '_g2_wc_product_addon_ids';
	const CART_KEY = 'g2_wc_product_addon_ids';
	const PARENT_KEY = 'g2_wc_addon_parent_key';
	const GITHUB_REPOSITORY = 'OliverJarvis97/woocommerce-product-accordion-addons';

	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'woocommerce_product_options_related', array( $this, 'add_admin_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_admin_field' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_addons' ), 10 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_addons' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'store_addons_in_cart_item' ), 10, 3 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_selected_products' ), 10, 6 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_addon_quantity_display' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'lock_addon_quantity_update' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
	}

	public function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	private function github_release() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::GITHUB_REPOSITORY . '/releases/latest', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ) ) ) );
		$release = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) && ! empty( $decoded['tag_name'] ) && ! empty( $decoded['assets'] ) ) {
				foreach ( $decoded['assets'] as $asset ) {
					if ( ! empty( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', (string) $asset['name'] ) ) { $release = $decoded; break; }
				}
			}
		}
		return $release;
	}

	public function check_for_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) { return $transient; }
		$plugin_file = plugin_basename( __FILE__ );
		$current_version = isset( $transient->checked[ $plugin_file ] ) ? $transient->checked[ $plugin_file ] : '1.6.3';
		$release = $this->github_release();
		if ( empty( $release['tag_name'] ) ) { return $transient; }
		$version = ltrim( (string) $release['tag_name'], 'vV' );
		$package = '';
		foreach ( $release['assets'] as $asset ) { if ( ! empty( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', (string) $asset['name'] ) ) { $package = esc_url_raw( $asset['browser_download_url'] ); break; } }
		if ( $package && version_compare( $version, $current_version, '>' ) ) { $transient->response[ $plugin_file ] = (object) array( 'slug' => 'woo-product-accordion-addons', 'plugin' => $plugin_file, 'new_version' => $version, 'url' => 'https://github.com/' . self::GITHUB_REPOSITORY, 'package' => $package ); }
		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'woo-product-accordion-addons' !== $args->slug ) { return $result; }
		$release = $this->github_release();
		if ( empty( $release['tag_name'] ) ) { return $result; }
		return (object) array( 'name' => 'WooCommerce Product Accordion Add-ons', 'slug' => 'woo-product-accordion-addons', 'version' => ltrim( (string) $release['tag_name'], 'vV' ), 'homepage' => 'https://github.com/' . self::GITHUB_REPOSITORY, 'download_link' => ! empty( $release['assets'][0]['browser_download_url'] ) ? esc_url_raw( $release['assets'][0]['browser_download_url'] ) : '', 'sections' => array( 'description' => 'Optional WooCommerce product add-ons displayed as compact accordions.', 'changelog' => ! empty( $release['body'] ) ? wp_kses_post( $release['body'] ) : '' ) );
	}

	public function add_admin_field() {
		global $post;
		$ids = $this->get_addon_ids( $post ? $post->ID : 0 );
		echo '<p class="form-field ' . esc_attr( self::META_KEY ) . '_field">';
		echo '<label for="' . esc_attr( self::META_KEY ) . '">' . esc_html__( 'Product add-ons', 'wc-product-accordion-addons' ) . '</label>';
		echo '<select id="' . esc_attr( self::META_KEY ) . '" name="' . esc_attr( self::META_KEY ) . '[]" class="wc-product-search" multiple="multiple" style="width:50%;" data-placeholder="' . esc_attr__( 'Search for products…', 'wc-product-accordion-addons' ) . '" data-action="woocommerce_json_search_products_and_variations">';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( $product->get_formatted_name() ) . '</option>';
			}
		}
		echo '</select>';
		echo wc_help_tip( __( 'Choose optional products displayed above this product’s Add to Cart button. Customers select them individually; each add-on is added with a quantity of 1.', 'wc-product-accordion-addons' ) );
		echo '</p>';
	}

	public function save_admin_field( $post_id ) {
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META_KEY ] ) ) {
			return;
		}
		$raw_ids = isset( $_POST[ self::META_KEY ] ) ? (array) wp_unslash( $_POST[ self::META_KEY ] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
		$ids = array_values( array_diff( $ids, array( absint( $post_id ) ) ) );
		update_post_meta( $post_id, self::META_KEY, $ids );
	}

	private function get_addon_ids( $product_id ) {
		$ids = get_post_meta( $product_id, self::META_KEY, true );
		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
	}

	private function get_valid_addons( $product_id, $requested_ids = null ) {
		$allowed = $this->get_addon_ids( $product_id );
		$ids = null === $requested_ids ? $allowed : array_intersect( $allowed, array_map( 'absint', (array) $requested_ids ) );
		$valid = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product && ! $product->is_type( array( 'grouped', 'external' ) ) && ( $product->is_type( 'variable' ) ? $product->has_purchasable_variations() : ( $product->is_purchasable() && $product->is_in_stock() ) ) ) {
				$valid[] = $id;
			}
		}
		return array_values( array_unique( $valid ) );
	}

	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product || empty( $this->get_valid_addons( $product->get_id() ) ) ) {
			return;
		}
		wp_register_style( 'g2-wc-product-accordion-addons', false, array(), '1.6.2' );
		wp_enqueue_style( 'g2-wc-product-accordion-addons' );
		wp_add_inline_style( 'g2-wc-product-accordion-addons', '.g2-wc-addons{margin:0 0 1.2em}.g2-wc-addons__title{margin:0 0 .45em;font-size:1em;font-weight:700}.g2-wc-addon{border-top:1px solid #e6e6e6}.g2-wc-addon:last-child{border-bottom:1px solid #e6e6e6}.g2-wc-addon summary{padding:.65em 0;cursor:pointer;list-style:none}.g2-wc-addon summary::-webkit-details-marker{display:none}.g2-wc-addon__summary{display:flex;align-items:center;gap:.7em}.g2-wc-addon__choice{display:flex;align-items:center;gap:.55em;flex:1;min-width:0;cursor:pointer;margin:0}.g2-wc-addon__choice input{width:20px;height:20px;min-width:20px;margin:0;cursor:pointer;accent-color:currentColor}.g2-wc-addon__name{font-weight:600;line-height:1.3}.g2-wc-addon__price{margin-left:auto;white-space:nowrap;font-weight:700}.g2-wc-addon__content{display:flex;align-items:flex-start;gap:.8em;padding:0 2.25em .85em 0;font-size:.92em;color:#59636d}.g2-wc-addon__image{display:block;flex:0 0 68px;width:68px;height:68px;overflow:hidden;cursor:zoom-in;line-height:0}.g2-wc-addon__image img{display:block;width:100%;height:100%;object-fit:cover}.g2-wc-addon__description{margin:0;line-height:1.5}.g2-wc-addon__summary .g2-wc-addon__variation-wrap{display:none;flex:0 1 230px;margin-left:auto}.g2-wc-addon--selected .g2-wc-addon__variation-wrap{display:block}.g2-wc-addon--selected .g2-wc-addon__price{display:none}.g2-wc-addon__variation-wrap select{width:100%;height:34px;padding:.25em .4em}.g2-wc-addon__toggle{width:10px;height:10px;flex-basis:10px;border:solid currentColor;border-width:0 2px 2px 0;transform:rotate(45deg);opacity:.85}.g2-wc-addon[open] .g2-wc-addon__toggle{transform:rotate(225deg)}.g2-wc-lightbox[hidden]{display:none}.g2-wc-lightbox{position:fixed;z-index:999999;inset:0;display:grid;place-items:center;padding:24px}.g2-wc-lightbox__backdrop{position:absolute;inset:0;background:rgba(9,18,28,.78)}.g2-wc-lightbox__dialog{position:relative;z-index:1;max-width:min(90vw,900px);max-height:88vh}.g2-wc-lightbox__dialog img{display:block;max-width:100%;max-height:88vh;border-radius:4px}.g2-wc-lightbox__close{box-sizing:border-box;display:flex;align-items:center;justify-content:center;min-width:34px;max-width:34px;width:34px;min-height:34px;max-height:34px;height:34px;padding:0;border:0;border-radius:50%;background:#fff;color:#1d2935;font:normal 26px/1 Arial,sans-serif;cursor:pointer}@media(max-width:480px){.g2-wc-addon__summary{gap:.5em}.g2-wc-addon__price{font-size:.92em}.g2-wc-addon__content{padding-right:0}.g2-wc-addon__image{flex-basis:58px;width:58px;height:58px}}' );
		wp_register_script( 'g2-wc-product-accordion-addons', false, array(), '1.6.2', true );
		wp_enqueue_script( 'g2-wc-product-accordion-addons' );
		wp_add_inline_script( 'g2-wc-product-accordion-addons', "document.addEventListener('change',function(event){if(!event.target.matches('.g2-wc-addon__choice input'))return;var item=event.target.closest('.g2-wc-addon');var select=item&&item.querySelector('.g2-wc-addon__variation-wrap select');if(select){item.classList.toggle('g2-wc-addon--selected',event.target.checked);select.disabled=!event.target.checked;if(!event.target.checked)select.value='';}});document.addEventListener('click',function(event){var trigger=event.target.closest('[data-g2-lightbox-image]');var box=document.querySelector('.g2-wc-lightbox');if(trigger&&box){event.preventDefault();event.stopPropagation();var image=box.querySelector('img');image.src=trigger.getAttribute('data-g2-lightbox-image');image.alt=trigger.getAttribute('data-g2-lightbox-alt')||'';box.hidden=false;box.setAttribute('aria-hidden','false');box.querySelector('.g2-wc-lightbox__close').focus();return;}if(event.target.closest('[data-g2-lightbox-close]')&&box){box.hidden=true;box.setAttribute('aria-hidden','true');box.querySelector('img').src='';}});" );
	}

	public function render_addons() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$addons = $this->get_valid_addons( $product->get_id() );
		if ( empty( $addons ) ) {
			return;
		}
		wp_nonce_field( 'g2_wc_addons_' . $product->get_id(), 'g2_wc_addons_nonce' );
		echo '<section class="g2-wc-addons" aria-label="' . esc_attr__( 'Optional add-ons', 'wc-product-accordion-addons' ) . '">';
		echo '<p class="g2-wc-addons__title">' . esc_html__( 'Optional add-ons', 'wc-product-accordion-addons' ) . '</p>';
		foreach ( $addons as $addon_id ) {
			$addon = wc_get_product( $addon_id );
			$description = $addon->get_short_description() ? $addon->get_short_description() : $addon->get_description();
			$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
			$image_id = $addon->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : false;
			echo '<details class="g2-wc-addon">';
			echo '<summary><span class="g2-wc-addon__summary">';
			echo '<label class="g2-wc-addon__choice"><input type="checkbox" name="g2_wc_addons[]" value="' . esc_attr( $addon_id ) . '"><span class="g2-wc-addon__name">' . esc_html( $addon->get_name() ) . '</span></label>';
			$price_html = $addon->is_type( 'variable' ) ? sprintf( __( 'From %s', 'wc-product-accordion-addons' ), wc_price( $addon->get_variation_price( 'min', true ) ) ) : $addon->get_price_html();
			echo '<strong class="g2-wc-addon__price">' . wp_kses_post( $price_html ) . '</strong>';
			if ( $addon->is_type( 'variable' ) ) {
				$attributes = array_keys( $addon->get_variation_attributes() );
				$placeholder = ! empty( $attributes ) ? wc_attribute_label( str_replace( 'attribute_', '', $attributes[0] ), $addon ) : __( 'Select an option', 'wc-product-accordion-addons' );
				echo '<span class="g2-wc-addon__variation-wrap"><select name="g2_wc_addon_variations[' . esc_attr( $addon_id ) . ']" disabled required><option value="">' . esc_html( $placeholder ) . '</option>';
				foreach ( $addon->get_available_variations() as $variation_data ) { $variation_product = wc_get_product( $variation_data['variation_id'] ); if ( $variation_product && $variation_product->is_purchasable() && $variation_product->is_in_stock() ) { $label = trim( str_replace( $addon->get_name(), '', $variation_product->get_name() ), " -–—" ); if ( '' === $label ) { $label = wc_get_formatted_variation( $variation_product, true, false, true ); } echo '<option value="' . esc_attr( $variation_product->get_id() ) . '">' . esc_html( $label . ' — ' . wp_strip_all_tags( $variation_product->get_price_html() ) ) . '</option>'; } }
				echo '</select></span>';
			}
			echo '<span class="g2-wc-addon__toggle" aria-hidden="true"></span>';
			echo '</span></summary>';
			if ( $description || $image_url ) {
				echo '<div class="g2-wc-addon__content">';
				if ( $image_url ) {
					echo '<a class="g2-wc-addon__image" href="' . esc_url( $image_url ) . '" data-g2-lightbox-image="' . esc_url( $image_url ) . '" data-g2-lightbox-alt="' . esc_attr( $addon->get_name() ) . '" aria-label="' . esc_attr( sprintf( __( 'View a larger image of %s', 'wc-product-accordion-addons' ), $addon->get_name() ) ) . '">';
					echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'alt' => $addon->get_name(), 'loading' => 'lazy' ) );
					echo '</a>';
				}
				if ( $description ) {
					echo '<p class="g2-wc-addon__description">' . esc_html( $description ) . '<br><a href="' . esc_url( get_permalink( $addon_id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Find out more', 'wc-product-accordion-addons' ) . '</a></p>';
				}
				echo '</div>';
			}
			echo '</details>';
		}
		echo '</section>';
		echo '<div class="g2-wc-lightbox" hidden aria-hidden="true"><div class="g2-wc-lightbox__backdrop" data-g2-lightbox-close></div><div class="g2-wc-lightbox__dialog" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Product image', 'wc-product-accordion-addons' ) . '"><button type="button" class="g2-wc-lightbox__close" data-g2-lightbox-close aria-label="' . esc_attr__( 'Close image', 'wc-product-accordion-addons' ) . '">&times;</button><img src="" alt=""></div></div>';
	}

	private function requested_addon_ids( $product_id ) {
		if ( empty( $_POST['g2_wc_addons_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['g2_wc_addons_nonce'] ) ), 'g2_wc_addons_' . $product_id ) ) {
			return array();
		}
		$requested = isset( $_POST['g2_wc_addons'] ) ? (array) wp_unslash( $_POST['g2_wc_addons'] ) : array();
		return $this->get_valid_addons( $product_id, $requested );
	}

	private function requested_addon_variation( $addon_id ) {
		$requested = isset( $_POST['g2_wc_addon_variations'][ $addon_id ] ) ? absint( wp_unslash( $_POST['g2_wc_addon_variations'][ $addon_id ] ) ) : 0;
		$variation = $requested ? wc_get_product( $requested ) : false;
		return ( $variation && $variation->is_type( 'variation' ) && absint( $variation->get_parent_id() ) === absint( $addon_id ) && $variation->is_purchasable() && $variation->is_in_stock() ) ? $variation : false;
	}

	public function validate_addons( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		// Validation intentionally occurs only during WooCommerce's main add-to-cart request.
		foreach ( $this->requested_addon_ids( $product_id ) as $addon_id ) {
			$addon = wc_get_product( $addon_id );
			if ( $addon && $addon->is_type( 'variable' ) && ! $this->requested_addon_variation( $addon_id ) ) { wc_add_notice( __( 'Please choose an option for each selected add-on.', 'wc-product-accordion-addons' ), 'error' ); return false; }
			if ( ! $addon || ! $addon->is_purchasable() || ! $addon->is_in_stock() ) {
				wc_add_notice( __( 'One of the selected add-ons is no longer available.', 'wc-product-accordion-addons' ), 'error' );
				return false;
			}
		}
		return $passed;
	}

	public function store_addons_in_cart_item( $cart_item_data, $product_id, $variation_id ) {
		$addons = $this->requested_addon_ids( $product_id );
		if ( $addons ) {
			$cart_item_data[ self::CART_KEY ] = $addons;
			// Keep differently configured parent lines separate, so add-ons map to the correct line.
			$cart_item_data['g2_wc_addons_configuration'] = md5( implode( ',', $addons ) . '|' . wp_generate_uuid4() );
		}
		return $cart_item_data;
	}

	public function add_selected_products( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( empty( $cart_item_data[ self::CART_KEY ] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		foreach ( (array) $cart_item_data[ self::CART_KEY ] as $addon_id ) {
			$addon = wc_get_product( $addon_id ); $variation = $addon && $addon->is_type( 'variable' ) ? $this->requested_addon_variation( $addon_id ) : false;
			WC()->cart->add_to_cart( absint( $addon_id ), 1, $variation ? $variation->get_id() : 0, $variation ? $variation->get_variation_attributes() : array(), array( self::PARENT_KEY => $cart_item_key ) );
		}
	}

	public function lock_addon_quantity_display( $product_quantity, $cart_item_key, $cart_item ) {
		if ( ! empty( $cart_item[ self::PARENT_KEY ] ) ) {
			return '1';
		}
		return $product_quantity;
	}

	public function lock_addon_quantity_update( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! empty( $values[ self::PARENT_KEY ] ) && 1 !== absint( $quantity ) ) {
			WC()->cart->set_quantity( $cart_item_key, 1, false );
			wc_add_notice( __( 'Add-ons are limited to a quantity of 1.', 'wc-product-accordion-addons' ), 'notice' );
		}
		return $passed;
	}
}

add_action( 'plugins_loaded', static function() {
	if ( class_exists( 'WooCommerce' ) ) {
		new G2_WC_Product_Accordion_Addons();
	}
} );
