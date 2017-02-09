<?php
/**
 * Plugin Name: Moodle Auth Token Client
 * Plugin URI:https://github.com/Multisistemas/moodle-auth-token-client
 * Description: Make SSO into Moodle using auth token plugin
 * Author: Multisistemas
 * Author URI: http://multisistemas.com.sv/
 * Version: 1.0
 */

/**
 * Get all Products Successfully Ordered by the user
 *
 * @global type $wpdb
 * @param int $user_id
 * @return bool|array false if no products otherwise array of product ids
 */
function matc_get_all_products_ordered_by_user($user_id=false,$status='completed')
{
	$orders=matc_get_all_user_orders($user_id,$status);
	
	if(empty($orders))
	return false;
	
	$order_list='('.join(',', $orders).')';//let us make a list for query
	
	global $wpdb;
	$query_select_order_items="SELECT order_item_id as id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id IN {$order_list}";
	
	$query_select_product_ids="SELECT meta_value as product_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key=%s AND order_item_id IN ($query_select_order_items)";
	
	$ids=$wpdb->get_col($wpdb->prepare($query_select_product_ids,'_product_id'));
	
	$products = array();
	
	foreach($ids as $id) {
		$sku = get_post_meta($id, '_sku', true);
	    $products[]=$sku;
	}
	
	return $products;
}

/**
 * IMPORTANT NOTE: This function is also used by sso.php but placed here to allow orders count.
 *
 * Returns all the orders made by the user
 *
 * @param int $user_id
 * @param string $status (wc-completed|processing|canceled|on-hold etc)
 * @return array of order ids
 */
function matc_get_all_user_orders($user_id,$status='completed'){
    if(!$user_id)
        return false;

    $orders=array();//order ids

    $args = array(
        'numberposts'     => -1,
        'meta_key'        => '_customer_user',
        'meta_value'      => $user_id,
        'post_type'       => 'shop_order',
        'post_status'     => 'publish',
        'tax_query'=>array(
                array(
                    'taxonomy'  =>'shop_order_status',
                    'field'     => 'slug',
                    'terms'     => $status
                    )
        )
    );

    $posts=get_posts($args);
    //get the post ids as order ids
    $orders=wp_list_pluck( $posts, 'ID' );

    return $orders;

}