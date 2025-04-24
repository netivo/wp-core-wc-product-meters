<?php
/**
 * Created by PhpStorm.
 * User: michal
 * Date: 19.11.18
 * Time: 14:01
 *
 * @package Netivo\Elazienki\Theme\Woocommerce\Product\Type
 */

namespace Netivo\Module\WooCommerce\Meters;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Class Meters
 */
class ProductType extends \Netivo\Woocommerce\Product\Type {

	/**
	 * Registers new product type.
	 */
	public static function register(): void {

		add_filter( 'woocommerce_product_class', [ self::class, 'product_class' ], 10, 4 );
		add_filter( 'woocommerce_product_get_price', [ self::class, 'change_price' ], 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', [ self::class, 'change_price' ], 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', [ self::class, 'change_price' ], 10, 2 );
		add_action( 'woocommerce_meters_add_to_cart', [ self::class, 'add_to_cart' ] );
		add_action( 'woocommerce_checkout_create_order', [ self::class, 'save_order' ] );
		add_filter( 'product_type_selector', [ self::class, 'add_to_select' ] );
		add_filter( 'woocommerce_structured_data_product_offer', [ self::class, 'change_product_schema' ], 10, 2 );

		if ( is_admin() ) {
			add_filter( 'woocommerce_product_data_tabs', [ self::class, 'modify_pricing_tab' ] );
			add_filter( 'woocommerce_hidden_order_itemmeta', [ self::class, 'hide_order_item_meta' ] );
			add_action( 'admin_footer', [ self::class, 'admin_footer_js' ] );

			add_action( 'woocommerce_product_options_general_product_data', [ self::class, 'display_price_options' ] );
			add_action( 'save_post', [ self::class, 'product_data_save' ] );
		}

	}

	/**
	 * Add product type to select.
	 *
	 * @param array $types Types of the products.
	 *
	 * @return array
	 */
	public static function add_to_select( array $types ): array {
		$types['meters'] = __( 'Produkt na metry', 'elazienki' );

		return $types;
	}

	public static function change_product_schema( $data, $product ): array {
		if ( $product->get_type() == 'meters' ) {
			if ( array_key_exists( 0, $data['priceSpecification'] ) ) {
				if ( $product->is_on_sale() ) {
					$data['priceSpecification'][0]['price'] = wc_format_decimal( $product->get_regular_price( 'normal' ), wc_get_price_decimals() );
					$data['priceSpecification'][1]['price'] = wc_format_decimal( $product->get_sale_price( 'normal' ), wc_get_price_decimals() );
				} else {
					$data['priceSpecification'][0]['price'] = wc_format_decimal( $product->get_price( 'normal' ), wc_get_price_decimals() );
				}
			} else {
				$data['priceSpecification']['price'] = wc_format_decimal( $product->get_price( 'normal' ), wc_get_price_decimals() );
			}
		}

		return $data;
	}

	/**
	 * Add classes to special tabs.
	 *
	 * @param array $tabs Current tabs in product data metabox.
	 *
	 * @return mixed
	 */
	public static function modify_pricing_tab( array $tabs ): mixed {
		$tabs['inventory']['class'][] = 'show_if_meters';

		return $tabs;
	}

	/**
	 * Gets the price for product.
	 *
	 * @param string $price Price to get from.
	 * @param \WC_Product $product Product to get price from.
	 *
	 * @return string
	 */
	public static function change_price( string $price, \WC_Product $product ): string {
		if ( ! empty( $price ) ) {
			if ( $product->get_type() == 'meters' && ! is_admin() ) {
				$meters = $product->get_meta( '_meters_in_box' );
				if ( ! empty( $meters ) ) {
					$meters = str_replace( ',', '.', $meters );
					$price  = (float) $price;
					$meters = (float) $meters;
//				    var_dump( $price * $meters );
					$price = $price * $meters;
				}
			}
		}

		return $price;
	}

	/**
	 * Sets the class name for product type.
	 *
	 * @param string $class_name Generated class name for type.
	 * @param string $product_type Product type.
	 * @param string $variation Is product a variation.
	 * @param int $product_id Product id.
	 *
	 * @return string
	 */
	public static function product_class( string $class_name, string $product_type, string $variation, int $product_id ): string {
		if ( $product_type === 'meters' ) {
			return self::class;
		}

		return $class_name;
	}

	/**
	 * Add script to enable pricing in product type.
	 */
	public static function admin_footer_js(): void {
		if ( 'product' != get_post_type() ) :
			return;
		endif;

		?>
        <script type='text/javascript'>
            jQuery('.options_group.pricing').addClass('show_if_meters');
            jQuery('.form-field._manage_stock_field').addClass('show_if_meters');
        </script><?php

	}

	/**
	 * Add meters settings to price tab in general product data.
	 */
	public static function display_price_options(): void {
		global $post, $thepostid, $product_object;

		$filename = __DIR__ . '/../../views/admin/product/meters-settings.phtml';
		wp_nonce_field( 'save_product_meters', 'product_meters_nonce' );

		include $filename;
	}


	/**
	 * Save meters data.
	 *
	 * @param int $post_id Saved post id.
	 *
	 * @return int
	 */
	public static function product_data_save( int $post_id ): int {
		if ( ! isset( $_POST['product_meters_nonce'] ) ) {
			return $post_id;
		}
		if ( ! wp_verify_nonce( $_POST['product_meters_nonce'], 'save_product_meters' ) ) {
			return $post_id;
		}
		if ( ! in_array( $_POST['post_type'], [ 'product' ] ) ) {
			return $post_id;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}
		if ( ! empty( $_POST['_meters_in_box'] ) ) {
			update_post_meta( $post_id, '_meters_in_box', $_POST['_meters_in_box'] );
		} else {
			delete_post_meta( $post_id, '_meters_in_box' );
		}
		if ( ! empty( $_POST['_items_in_box'] ) ) {
			update_post_meta( $post_id, '_items_in_box', $_POST['_items_in_box'] );
		} else {
			delete_post_meta( $post_id, '_items_in_box' );
		}

		return $post_id;
	}

	/**
	 * Hides meters and items from meta.
	 *
	 * @param array $items Current items.
	 *
	 * @return array
	 */
	public static function hide_order_item_meta( array $items ): array {
		$items[] = '_meters_in_box';
		$items[] = '_items_in_box';

		return $items;
	}

	/**
	 * Add to cart view for meters product type.
	 */
	public static function add_to_cart(): void {
		wc_get_template( 'single-product/add-to-cart/simple.php' );
	}

	/**
	 * Saves pickup place when shipping method is local pickup.
	 *
	 * @param WC_Order $order Order to save.
	 */
	public static function save_order( WC_Order $order ): void {
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_type() == 'line_item' ) {
				/**
				 * @var $item WC_Order_Item_Product
				 */
				$pr = $item->get_product();
				if ( $pr->get_type() == 'meters' ) {
					$item->add_meta_data( '_meters_in_box', $pr->get_meta( '_meters_in_box' ) );
					$item->add_meta_data( '_items_in_box', $pr->get_meta( '_items_in_box' ) );
					$item->save_meta_data();
				}
			}
		}
	}

	/**
	 * Gets the product type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'meters';
	}


}