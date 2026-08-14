<?php
/**
 * Plugin Name: WooCommerce Product Accordion Add-ons
 * Description: Select WooCommerce products as optional add-ons. They appear as compact accordions above the main Add to Cart button and are only added when that main product is submitted.
 * Version: 1.7.6
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
	const CONFIG_KEY = '_g2_wc_product_addon_config';
	const TITLE_KEY = '_g2_wc_product_addons_title';
	const REMOVE_KEY = '_g2_wc_product_addons_remove_with_parent';
	const SETTINGS_KEY = 'g2_wc_product_addons_settings';
	const CART_KEY = 'g2_wc_product_addons';
	const PARENT_KEY = 'g2_wc_addon_parent_key';
	const PRICE_KEY = 'g2_wc_addon_price_rule';
	const GITHUB_REPOSITORY = 'OliverJarvis97/woocommerce-product-accordion-addons';

	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'woocommerce_product_options_related', array( $this, 'add_admin_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_admin_field' ) );
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_addons' ), 10 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_addons' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'store_addons_in_cart_item' ), 10, 3 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_selected_products' ), 10, 6 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_addon_quantity_display' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'lock_addon_quantity_update' ), 10, 4 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_linked_addons' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_addon_price_rules' ), 20 );
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

	public function add_settings_section( $sections ) {
		$sections['g2_product_addons'] = __( 'Accordion add-ons', 'wc-product-accordion-addons' );
		return $sections;
	}

	public function add_settings( $settings, $section ) {
		if ( 'g2_product_addons' !== $section ) { return $settings; }
		return array(
			array( 'title' => __( 'Product accordion add-ons', 'wc-product-accordion-addons' ), 'type' => 'title', 'desc' => __( 'Defaults used for configured add-ons unless a product-level override is supplied.', 'wc-product-accordion-addons' ), 'id' => self::SETTINGS_KEY ),
			array( 'title' => __( 'Default section title', 'wc-product-accordion-addons' ), 'id' => self::SETTINGS_KEY . '_title', 'type' => 'text', 'default' => __( 'Optional add-ons', 'wc-product-accordion-addons' ) ),
			array( 'title' => __( 'Remove add-ons with parent', 'wc-product-accordion-addons' ), 'id' => self::SETTINGS_KEY . '_remove_with_parent', 'type' => 'checkbox', 'default' => 'no', 'desc' => __( 'Automatically remove linked add-ons when their parent is removed from the cart.', 'wc-product-accordion-addons' ) ),
			array( 'title' => __( 'Default price adjustment', 'wc-product-accordion-addons' ), 'id' => self::SETTINGS_KEY . '_price_type', 'type' => 'select', 'default' => 'none', 'options' => array( 'none' => __( 'Use product price', 'wc-product-accordion-addons' ), 'fixed' => __( 'Set a fixed add-on price', 'wc-product-accordion-addons' ), 'percent' => __( 'Apply a percentage discount', 'wc-product-accordion-addons' ) ) ),
			array( 'title' => __( 'Default amount', 'wc-product-accordion-addons' ), 'id' => self::SETTINGS_KEY . '_price_value', 'type' => 'number', 'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ), 'desc' => __( 'Fixed price in shop currency, or discount percentage from 0 to 100.', 'wc-product-accordion-addons' ) ),
			array( 'type' => 'sectionend', 'id' => self::SETTINGS_KEY ),
		);
	}

	private function settings() {
		return array( 'title' => get_option( self::SETTINGS_KEY . '_title', __( 'Optional add-ons', 'wc-product-accordion-addons' ) ), 'remove_with_parent' => get_option( self::SETTINGS_KEY . '_remove_with_parent', 'no' ), 'price_type' => get_option( self::SETTINGS_KEY . '_price_type', 'none' ), 'price_value' => get_option( self::SETTINGS_KEY . '_price_value', '' ) );
	}

	private function configs( $product_id ) { $configs = get_post_meta( $product_id, self::CONFIG_KEY, true ); return is_array( $configs ) ? $configs : array(); }
	private function config( $product_id, $addon_id ) { $configs = $this->configs( $product_id ); return isset( $configs[ $addon_id ] ) && is_array( $configs[ $addon_id ] ) ? $configs[ $addon_id ] : array(); }
	private function price_rule( $parent_id, $addon_id ) { $config = $this->config( $parent_id, $addon_id ); $settings = $this->settings(); $type = $config['price_type'] ?? 'inherit'; $value = 'inherit' === $type ? $settings['price_value'] : ( $config['price_value'] ?? '' ); $type = 'inherit' === $type ? $settings['price_type'] : $type; return array( 'type' => in_array( $type, array( 'fixed', 'percent' ), true ) ? $type : 'none', 'value' => max( 0, (float) $value ) ); }
	private function adjusted_price( $price, $rule ) { return 'fixed' === $rule['type'] ? $rule['value'] : ( 'percent' === $rule['type'] ? max( 0, $price * ( 1 - min( 100, $rule['value'] ) / 100 ) ) : $price ); }
	private function variation_label( $variation, $parent ) { $label = trim( str_replace( $parent->get_name(), '', $variation->get_name() ), " -–—" ); return '' !== $label ? $label : wc_get_formatted_variation( $variation, true, false, true ); }
	private function allowed_variations( $parent_id, $addon ) { $config = $this->config( $parent_id, $addon->get_id() ); $ids = ! empty( $config['variation_ids'] ) ? array_map( 'absint', (array) $config['variation_ids'] ) : array_map( 'absint', $addon->get_children() ); $valid = array(); foreach ( $ids as $id ) { $variation = wc_get_product( $id ); if ( $variation && $variation->is_type( 'variation' ) && absint( $variation->get_parent_id() ) === absint( $addon->get_id() ) && $variation->is_purchasable() && $variation->is_in_stock() ) { $valid[] = $variation; } } return $valid; }

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
		echo '<p class="form-field"><label for="' . esc_attr( self::TITLE_KEY ) . '">' . esc_html__( 'Add-ons section title', 'wc-product-accordion-addons' ) . '</label><input type="text" class="short" id="' . esc_attr( self::TITLE_KEY ) . '" name="' . esc_attr( self::TITLE_KEY ) . '" value="' . esc_attr( get_post_meta( $post ? $post->ID : 0, self::TITLE_KEY, true ) ) . '" placeholder="' . esc_attr__( 'Optional add-ons', 'wc-product-accordion-addons' ) . '"> ' . wc_help_tip( __( 'Optional. Overrides the default heading for this product only.', 'wc-product-accordion-addons' ) ) . '</p>';
		$remove = get_post_meta( $post ? $post->ID : 0, self::REMOVE_KEY, true );
		echo '<p class="form-field"><label for="' . esc_attr( self::REMOVE_KEY ) . '">' . esc_html__( 'Remove linked add-ons', 'wc-product-accordion-addons' ) . '</label><select id="' . esc_attr( self::REMOVE_KEY ) . '" name="' . esc_attr( self::REMOVE_KEY ) . '"><option value="inherit"' . selected( $remove, '', false ) . '>' . esc_html__( 'Use global setting', 'wc-product-accordion-addons' ) . '</option><option value="yes"' . selected( $remove, 'yes', false ) . '>' . esc_html__( 'Yes', 'wc-product-accordion-addons' ) . '</option><option value="no"' . selected( $remove, 'no', false ) . '>' . esc_html__( 'No', 'wc-product-accordion-addons' ) . '</option></select></p>';
		if ( $ids ) { echo '<div class="options_group"><p><strong>' . esc_html__( 'Individual add-on options', 'wc-product-accordion-addons' ) . '</strong><br><span class="description">' . esc_html__( 'Save after choosing add-ons to configure them. All overrides are optional.', 'wc-product-accordion-addons' ) . '</span></p>'; foreach ( $ids as $id ) { $addon = wc_get_product( $id ); if ( $addon ) { $this->render_admin_config( $post->ID, $addon ); } } echo '</div>'; }
	}

	private function render_admin_config( $parent_id, $addon ) {
		$id = $addon->get_id(); $config = $this->config( $parent_id, $id ); $prefix = self::CONFIG_KEY . '[' . $id . ']'; $type = $config['price_type'] ?? 'inherit';
		echo '<div style="padding:12px 0;border-top:1px solid #eee"><p><strong>' . esc_html( $addon->get_name() ) . '</strong></p>';
		echo '<p class="form-field"><label>' . esc_html__( 'Display name', 'wc-product-accordion-addons' ) . '</label><input type="text" class="short" name="' . esc_attr( $prefix ) . '[name]" value="' . esc_attr( $config['name'] ?? '' ) . '" placeholder="' . esc_attr( $addon->get_name() ) . '"></p>';
		echo '<p class="form-field"><label>' . esc_html__( 'Display description', 'wc-product-accordion-addons' ) . '</label><textarea class="short" name="' . esc_attr( $prefix ) . '[description]" placeholder="' . esc_attr__( 'Use product description', 'wc-product-accordion-addons' ) . '">' . esc_textarea( $config['description'] ?? '' ) . '</textarea></p>';
		echo '<p class="form-field"><label>' . esc_html__( 'Quantity selection', 'wc-product-accordion-addons' ) . '</label><input type="checkbox" name="' . esc_attr( $prefix ) . '[allow_quantity]" value="yes"' . checked( ! empty( $config['allow_quantity'] ), true, false ) . '> ' . esc_html__( 'Let customers choose quantity', 'wc-product-accordion-addons' ) . '</p>';
		echo '<p class="form-field"><label>' . esc_html__( 'Price adjustment', 'wc-product-accordion-addons' ) . '</label><select name="' . esc_attr( $prefix ) . '[price_type]"><option value="inherit"' . selected( $type, 'inherit', false ) . '>' . esc_html__( 'Use global setting', 'wc-product-accordion-addons' ) . '</option><option value="none"' . selected( $type, 'none', false ) . '>' . esc_html__( 'Use product price', 'wc-product-accordion-addons' ) . '</option><option value="fixed"' . selected( $type, 'fixed', false ) . '>' . esc_html__( 'Set fixed price', 'wc-product-accordion-addons' ) . '</option><option value="percent"' . selected( $type, 'percent', false ) . '>' . esc_html__( 'Percentage discount', 'wc-product-accordion-addons' ) . '</option></select> <input type="number" min="0" step="0.01" class="short" name="' . esc_attr( $prefix ) . '[price_value]" value="' . esc_attr( $config['price_value'] ?? '' ) . '" placeholder="' . esc_attr__( 'Amount', 'wc-product-accordion-addons' ) . '"></p>';
		if ( $addon->is_type( 'variable' ) ) { $selected = array_map( 'absint', (array) ( $config['variation_ids'] ?? array() ) ); echo '<p class="form-field"><label>' . esc_html__( 'Allowed variations', 'wc-product-accordion-addons' ) . '</label><select multiple style="min-width:300px" name="' . esc_attr( $prefix ) . '[variation_ids][]">'; foreach ( $addon->get_children() as $variation_id ) { $variation = wc_get_product( $variation_id ); if ( $variation ) { echo '<option value="' . esc_attr( $variation_id ) . '"' . selected( in_array( absint( $variation_id ), $selected, true ), true, false ) . '>' . esc_html( $this->variation_label( $variation, $addon ) ) . '</option>'; } } echo '</select> ' . wc_help_tip( __( 'Leave empty to allow all. With one allowed variation, no dropdown is shown.', 'wc-product-accordion-addons' ) ) . '</p>'; }
		echo '</div>';
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
		$title = isset( $_POST[ self::TITLE_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TITLE_KEY ] ) ) : '';
		$title ? update_post_meta( $post_id, self::TITLE_KEY, $title ) : delete_post_meta( $post_id, self::TITLE_KEY );
		$remove = isset( $_POST[ self::REMOVE_KEY ] ) ? sanitize_key( wp_unslash( $_POST[ self::REMOVE_KEY ] ) ) : 'inherit';
		in_array( $remove, array( 'yes', 'no' ), true ) ? update_post_meta( $post_id, self::REMOVE_KEY, $remove ) : delete_post_meta( $post_id, self::REMOVE_KEY );
		$raw_configs = isset( $_POST[ self::CONFIG_KEY ] ) ? (array) wp_unslash( $_POST[ self::CONFIG_KEY ] ) : array(); $configs = array();
		foreach ( $ids as $addon_id ) { $item = isset( $raw_configs[ $addon_id ] ) && is_array( $raw_configs[ $addon_id ] ) ? $raw_configs[ $addon_id ] : array(); $type = isset( $item['price_type'] ) ? sanitize_key( $item['price_type'] ) : 'inherit'; $type = in_array( $type, array( 'inherit', 'none', 'fixed', 'percent' ), true ) ? $type : 'inherit'; $value = isset( $item['price_value'] ) ? (float) wc_format_decimal( $item['price_value'] ) : 0; $value = 'percent' === $type ? min( 100, max( 0, $value ) ) : max( 0, $value ); $variation_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $item['variation_ids'] ?? array() ) ) ) ) ); $addon = wc_get_product( $addon_id ); if ( ! $addon || ! $addon->is_type( 'variable' ) ) { $variation_ids = array(); } else { $variation_ids = array_values( array_intersect( $variation_ids, array_map( 'absint', $addon->get_children() ) ) ); } $configs[ $addon_id ] = array( 'name' => isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '', 'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '', 'allow_quantity' => ! empty( $item['allow_quantity'] ) ? 'yes' : 'no', 'price_type' => $type, 'price_value' => $value, 'variation_ids' => $variation_ids ); }
		update_post_meta( $post_id, self::CONFIG_KEY, $configs );
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
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof WC_Product || empty( $this->get_valid_addons( $product->get_id() ) ) ) {
			return;
		}
		wp_enqueue_style( 'g2-wc-product-accordion-addons', plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css', array(), '1.7.6' );
		wp_enqueue_script( 'g2-wc-product-accordion-addons', plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js', array(), '1.7.6', true );
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
		$title = get_post_meta( $product->get_id(), self::TITLE_KEY, true );
		if ( '' === $title ) { $title = $this->settings()['title']; }
		echo '<section class="g2-wc-addons" aria-label="' . esc_attr( $title ) . '">';
		echo '<p class="g2-wc-addons__title">' . esc_html( $title ) . '</p>';
		foreach ( $addons as $addon_id ) {
			$addon = wc_get_product( $addon_id );
			$config = $this->config( $product->get_id(), $addon_id );
			$name = ! empty( $config['name'] ) ? $config['name'] : $addon->get_name();
			$description = ! empty( $config['description'] ) ? $config['description'] : ( $addon->get_short_description() ? $addon->get_short_description() : $addon->get_description() );
			$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
			$image_id = $addon->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : false;
			echo '<details class="g2-wc-addon">';
			echo '<summary><span class="g2-wc-addon__summary">';
			$variations = $addon->is_type( 'variable' ) ? $this->allowed_variations( $product->get_id(), $addon ) : array();
			$single_variation = 1 === count( $variations ) ? $variations[0] : false;
			$display_name = $single_variation ? $name . ' — ' . $this->variation_label( $single_variation, $addon ) : $name;
			echo '<label class="g2-wc-addon__choice"><input type="checkbox" name="g2_wc_addons[]" value="' . esc_attr( $addon_id ) . '"><span class="g2-wc-addon__name">' . esc_html( $display_name ) . '</span></label>';
			$rule = $this->price_rule( $product->get_id(), $addon_id );
			if ( 'none' === $rule['type'] ) { $price_html = $addon->is_type( 'variable' ) && ! $single_variation ? sprintf( __( 'From %s', 'wc-product-accordion-addons' ), wc_price( $addon->get_variation_price( 'min', true ) ) ) : ( $single_variation ? $single_variation->get_price_html() : $addon->get_price_html() ); } else { $base = $single_variation ? (float) $single_variation->get_price() : ( $addon->is_type( 'variable' ) ? (float) $addon->get_variation_price( 'min', true ) : (float) $addon->get_price() ); $price_html = wc_price( $this->adjusted_price( $base, $rule ) ); }
			echo '<strong class="g2-wc-addon__price">' . wp_kses_post( $price_html ) . '</strong>';
			if ( ! empty( $config['allow_quantity'] ) && 'yes' === $config['allow_quantity'] ) { echo '<input class="g2-wc-addon__quantity" type="number" name="g2_wc_addon_quantities[' . esc_attr( $addon_id ) . ']" value="1" min="1" step="1" disabled>'; }
			if ( $addon->is_type( 'variable' ) && ! $single_variation ) {
				$attributes = array_keys( $addon->get_variation_attributes() );
				$placeholder = ! empty( $attributes ) ? wc_attribute_label( str_replace( 'attribute_', '', $attributes[0] ), $addon ) : __( 'Select an option', 'wc-product-accordion-addons' );
				echo '<span class="g2-wc-addon__variation-wrap"><select name="g2_wc_addon_variations[' . esc_attr( $addon_id ) . ']" disabled required><option value="">' . esc_html( $placeholder ) . '</option>';
				foreach ( $variations as $variation_product ) { echo '<option value="' . esc_attr( $variation_product->get_id() ) . '">' . esc_html( $this->variation_label( $variation_product, $addon ) . ' — ' . wp_strip_all_tags( 'none' === $rule['type'] ? $variation_product->get_price_html() : wc_price( $this->adjusted_price( (float) $variation_product->get_price(), $rule ) ) ) ) . '</option>'; }
				echo '</select></span>';
			}
			echo '<span class="g2-wc-addon__toggle" aria-hidden="true"></span>';
			echo '</span></summary>';
			if ( $description || $image_url ) {
				echo '<div class="g2-wc-addon__content">';
				if ( $image_url ) {
					echo '<a class="g2-wc-addon__image" href="' . esc_url( $image_url ) . '" data-g2-lightbox-image="' . esc_url( $image_url ) . '" data-g2-lightbox-alt="' . esc_attr( $name ) . '" aria-label="' . esc_attr( sprintf( __( 'View a larger image of %s', 'wc-product-accordion-addons' ), $name ) ) . '">';
					echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'alt' => $name, 'loading' => 'lazy' ) );
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

	private function requested_addon_variation( $parent_id, $addon_id ) {
		$requested = isset( $_POST['g2_wc_addon_variations'][ $addon_id ] ) ? absint( wp_unslash( $_POST['g2_wc_addon_variations'][ $addon_id ] ) ) : 0;
		$addon = wc_get_product( $addon_id ); $allowed = $addon && $addon->is_type( 'variable' ) ? $this->allowed_variations( $parent_id, $addon ) : array();
		if ( 1 === count( $allowed ) ) { return $allowed[0]; }
		foreach ( $allowed as $variation ) { if ( $requested === $variation->get_id() ) { return $variation; } }
		return false;
	}

	public function validate_addons( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		// Validation intentionally occurs only during WooCommerce's main add-to-cart request.
		foreach ( $this->requested_addon_ids( $product_id ) as $addon_id ) {
			$addon = wc_get_product( $addon_id );
			if ( $addon && $addon->is_type( 'variable' ) && ! $this->requested_addon_variation( $product_id, $addon_id ) ) { wc_add_notice( __( 'Please choose an option for each selected add-on.', 'wc-product-accordion-addons' ), 'error' ); return false; }
			if ( ! $addon || ( ! $addon->is_type( 'variable' ) && ( ! $addon->is_purchasable() || ! $addon->is_in_stock() ) ) ) {
				wc_add_notice( __( 'One of the selected add-ons is no longer available.', 'wc-product-accordion-addons' ), 'error' );
				return false;
			}
		}
		return $passed;
	}

	public function store_addons_in_cart_item( $cart_item_data, $product_id, $variation_id ) {
		$addons = $this->requested_addon_ids( $product_id );
		if ( $addons ) {
			$items = array(); foreach ( $addons as $addon_id ) { $config = $this->config( $product_id, $addon_id ); $items[] = array( 'id' => $addon_id, 'variation_id' => ( $variation = $this->requested_addon_variation( $product_id, $addon_id ) ) ? $variation->get_id() : 0, 'quantity' => ! empty( $config['allow_quantity'] ) && 'yes' === $config['allow_quantity'] ? max( 1, absint( $_POST['g2_wc_addon_quantities'][ $addon_id ] ?? 1 ) ) : 1, 'rule' => $this->price_rule( $product_id, $addon_id ), 'allow_quantity' => ! empty( $config['allow_quantity'] ) && 'yes' === $config['allow_quantity'] ); }
			$cart_item_data[ self::CART_KEY ] = $items;
			// Keep differently configured parent lines separate, so add-ons map to the correct line.
			$cart_item_data['g2_wc_addons_configuration'] = md5( implode( ',', $addons ) . '|' . wp_generate_uuid4() );
		}
		return $cart_item_data;
	}

	public function add_selected_products( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( empty( $cart_item_data[ self::CART_KEY ] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		foreach ( (array) $cart_item_data[ self::CART_KEY ] as $item ) {
			$addon_id = absint( $item['id'] ?? 0 ); $variation = ! empty( $item['variation_id'] ) ? wc_get_product( absint( $item['variation_id'] ) ) : false;
			WC()->cart->add_to_cart( $addon_id, max( 1, absint( $item['quantity'] ?? 1 ) ), $variation ? $variation->get_id() : 0, $variation ? $variation->get_variation_attributes() : array(), array( self::PARENT_KEY => $cart_item_key, self::PRICE_KEY => $item['rule'] ?? array( 'type' => 'none', 'value' => 0 ), 'g2_wc_addon_original_price' => (float) ( $variation ? $variation->get_price() : wc_get_product( $addon_id )->get_price() ), 'g2_wc_addon_allow_quantity' => ! empty( $item['allow_quantity'] ) ) );
		}
	}

	public function lock_addon_quantity_display( $product_quantity, $cart_item_key, $cart_item ) {
		if ( ! empty( $cart_item[ self::PARENT_KEY ] ) && empty( $cart_item['g2_wc_addon_allow_quantity'] ) ) {
			return '1';
		}
		return $product_quantity;
	}

	public function lock_addon_quantity_update( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! empty( $values[ self::PARENT_KEY ] ) && empty( $values['g2_wc_addon_allow_quantity'] ) && 1 !== absint( $quantity ) ) {
			WC()->cart->set_quantity( $cart_item_key, 1, false );
			wc_add_notice( __( 'Add-ons are limited to a quantity of 1.', 'wc-product-accordion-addons' ), 'notice' );
		}
		return $passed;
	}

	public function apply_addon_price_rules( $cart ) {
		if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) { return; }
		foreach ( $cart->get_cart() as $item ) { if ( empty( $item[ self::PARENT_KEY ] ) || empty( $item[ self::PRICE_KEY ] ) || empty( $item['data'] ) ) { continue; } $base = isset( $item['g2_wc_addon_original_price'] ) ? (float) $item['g2_wc_addon_original_price'] : (float) $item['data']->get_price(); $item['data']->set_price( $this->adjusted_price( $base, $item[ self::PRICE_KEY ] ) ); }
	}

	public function remove_linked_addons( $parent_key, $cart ) {
		$removed = $cart->removed_cart_contents[ $parent_key ] ?? array(); $parent_id = absint( $removed['product_id'] ?? 0 ); $setting = get_post_meta( $parent_id, self::REMOVE_KEY, true ); $should_remove = '' === $setting ? 'yes' === $this->settings()['remove_with_parent'] : 'yes' === $setting;
		if ( ! $parent_id || ! $should_remove ) { return; }
		foreach ( $cart->get_cart() as $key => $item ) { if ( isset( $item[ self::PARENT_KEY ] ) && hash_equals( (string) $parent_key, (string) $item[ self::PARENT_KEY ] ) ) { $cart->remove_cart_item( $key ); } }
	}
}

add_action( 'plugins_loaded', static function() {
	if ( class_exists( 'WooCommerce' ) ) {
		new G2_WC_Product_Accordion_Addons();
	}
} );
