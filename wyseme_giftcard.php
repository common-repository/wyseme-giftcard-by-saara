<?php
/**
 * @package Wyseme_giftcard
 */
/*
Plugin Name: Wyseme Giftcard By Saara
Plugin URI: https://wyse.me/
Description: Wyseme Giftcard By Saara.
Version: 1.1.2
Author: Saara INC
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'WYSEME_VERSION', '1.1.2' );
define( 'WYSEME__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WYSEME_DELETE_LIMIT', 100000 );

require_once( WYSEME__PLUGIN_DIR . 'class.wyseme-status-api.php' );

// Function to register our new routes from the controller.
function wyseme_register_routes() {
    $controller = new WYSEME_STATUS_API();
    $controller->init();
}

function wyseme_woocommerce_new_order_item( $item_id, $item, $order_id ) {
    
    if ( get_class( $item ) == 'WC_Order_Item_Coupon' ) {

        $coupon_code = $item->get_code();
        $the_coupon = new WC_Coupon( $coupon_code, '' );
        $coupon_id = $the_coupon->get_id();
        if ( isset( $coupon_id ) ) {
            $rate = 1;
            // price based on country.
            if ( class_exists( 'WCPBC_Pricing_Zone' ) ) {

                if ( wcpbc_the_zone() != null && wcpbc_the_zone() ) {

                    $rate = wcpbc_the_zone()->get_exchange_rate();
                }
            }

            $giftcardcoupon = get_post_meta( $coupon_id, 'wyse_me_gift_card', true);
            if ( ! empty( $giftcardcoupon ) ) {

                $wps_wgm_discount     = $item->get_discount();
                $wps_wgm_discount_tax = $item->get_discount_tax();
                $amount               = get_post_meta( $coupon_id, 'coupon_amount', true );
                $decimal_separator    = get_option( 'woocommerce_price_decimal_sep' );
                $amount               = floatval( str_replace( $decimal_separator, '.', $amount ) );

                $total_discount = wyseme_calculate_coupon_discount( $wps_wgm_discount, $wps_wgm_discount_tax );
                $total_discount = $total_discount / $rate;

                if ( $amount < $total_discount ) {
                    $remaining_amount = 0;
                } else {
                    $remaining_amount = $amount - $total_discount;
                    $remaining_amount = round( $remaining_amount, 2 );
                }
                update_post_meta( $coupon_id, 'coupon_amount', $remaining_amount );
            } 
        }
    }
}

/**
 * This function is used to return the remaining coupon amount according to Tax setting you have in your system.
 */

function wyseme_calculate_coupon_discount( $wps_wgm_discount, $wps_wgm_discount_tax ) {
    $price_in_ex_option = get_option( 'woocommerce_prices_include_tax' );
    $tax_display_shop = get_option( 'woocommerce_tax_display_shop', 'excl' );
    $tax_display_cart = get_option( 'woocommerce_tax_display_cart', 'excl' );

    if ( isset( $tax_display_shop ) && isset( $tax_display_cart ) ) {
        if ( 'excl' == $tax_display_cart && 'excl' == $tax_display_shop ) {

            if ( 'yes' == $price_in_ex_option || 'on' == $price_in_ex_option ) {

                return $wps_wgm_discount;
            }
        } elseif ( 'incl' == $tax_display_cart && 'incl' == $tax_display_shop ) {

            if ( 'yes' == $price_in_ex_option || 'no' == $price_in_ex_option ) {
                $total_discount = $wps_wgm_discount + $wps_wgm_discount_tax;
                return $total_discount;
            }
        } else {
            return $wps_wgm_discount;
        }
    }
    return $wps_wgm_discount;
}

/**
 * This function is used to manage coupon amount when order status will be cancelled or failed.
 *
 * @param int    $order_id id.
 * @param string $old_status old status.
 * @param string $new_status new status.
 * @return void
 */
function wyseme_manage_coupon_amount_on_refund( $order_id, $old_status, $new_status ) {
    $order       = new WC_Order( $order_id );
    $coupon_code = $order->get_coupon_codes();

    if ( ! empty( $coupon_code ) ) {
        $the_coupon = new WC_Coupon( $coupon_code[0] );
        $coupon_id  = $the_coupon->get_id();
        $orderid    = get_post_meta( $coupon_id);
        if ( isset( $orderid ) && ! empty( $orderid ) ) {
            if ( ! metadata_exists( 'post', $order_id, 'coupon_used' ) ) {
                $coupon_used = 1;
                update_post_meta( $order_id, 'coupon_used', $coupon_used );

            } else {
                $coupon_used = get_post_meta( $order_id, 'coupon_used' )[0];
            }

            if ( ( 'cancelled' == $new_status || 'failed' == $new_status ) && 1 == $coupon_used ) {

                $amount         = get_post_meta( $coupon_id, 'coupon_amount', true );
                $total_discount = get_post_meta( $order_id, '_cart_discount', true );
                if ( wc_prices_include_tax() ) {
                    $total_discount = $total_discount + get_post_meta( $order_id, '_cart_discount_tax', true );
                }

                $remaining_amount = $amount + $total_discount;
                $remaining_amount = round( $remaining_amount, 2 );
                update_post_meta( $coupon_id, 'coupon_amount', $remaining_amount );
                $coupon_used = 0;
                update_post_meta( $order_id, 'coupon_used', $coupon_used );

            } elseif ( ( 'pending' == $new_status || 'processing' == $new_status || 'on-hold' == $new_status || 'completed' == $new_status ) && 0 == $coupon_used ) {
                $amount         = get_post_meta( $coupon_id, 'coupon_amount', true );
                $total_discount = get_post_meta( $order_id, '_cart_discount', true );
                if ( wc_prices_include_tax() ) {
                    $total_discount = $total_discount + get_post_meta( $order_id, '_cart_discount_tax', true );
                }

                if ( $amount < $total_discount ) {
                    $remaining_amount = 0;
                } else {
                    $remaining_amount = $amount - $total_discount;
                    $remaining_amount = round( $remaining_amount, 2 );
                }
                update_post_meta( $coupon_id, 'coupon_amount', $remaining_amount );
                $coupon_used = 1;
                update_post_meta( $order_id, 'coupon_used', $coupon_used );
            }
        }
    }
}

add_action( 'woocommerce_new_order_item', 'wyseme_woocommerce_new_order_item', 10, 3 );
add_action( 'woocommerce_order_status_changed', 'wyseme_manage_coupon_amount_on_refund', 10, 3 );
add_action( 'rest_api_init', 'wyseme_register_routes' );