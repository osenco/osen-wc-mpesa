<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Functions
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

//add_filter('manage_edit-shop_order_columns', 'wcmpesa_new_order_column');
function wcmpesa_new_order_column($columns)
{
	$columns['mpesa'] = 'Reinitiate Mpesa';
	return $columns;
}

/**
 * Render custom column content within edit.php table on event post types.
 * 
 * @access public
 * @param string $column The name of the column being acted upon
 * @return void
 */
add_action('manage_shop_order_custom_column', 'shop_order_payments_table_column_content', 10);
function shop_order_payments_table_column_content($column_id, $post_id)
{
	$order_id = get_post_meta($post_id, '_order_id', true);
	switch ($column_id) {

		case 'mpesa':
			$statuses = array(
				"processing" => "This Order Is Processing",
				"on-hold" => "This Order Is On Hold",
				"complete" => "This Order Is Complete",
				"cancelled" => "This Order Is Cancelled",
				"refunded" => "This Order Is Refunded",
				"failed" => "This Order Failed"
			);

			echo ($value = get_post_meta($post_id, '_order_status', true)) ? '<a href="' . admin_url('post.php?post=' . esc_attr(trim($order_id)) . '&action=edit">' . esc_attr($statuses[$value]) . '</a>') : '<a href="' . admin_url('post.php?post=' . esc_attr(trim($order_id)) . '&action=edit"') . '>Set Status</a>';
			break;
	}
}

add_filter('woocommerce_email_attachments', 'woocommerce_emails_attach_downloadables', 10, 3);
function woocommerce_emails_attach_downloadables($attachments, $status, $order)
{
	if (!is_object($order) || !isset($status)) {
		return $attachments;
	}
	if (empty($order)) {
		return $attachments;
	}
	if (method_exists($order, 'has_downloadable_item')) {
		if (!$order->has_downloadable_item()) {
			return $attachments;
		}
		$allowed_statuses = array('customer_invoice', 'customer_completed_order');
		if (isset($status) && in_array($status, $allowed_statuses)) {
			foreach ($order->get_items() as $item_id => $item) {
				foreach ($order->get_item_downloads($item) as $download) {
					$attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
				}
			}
		}
	}

	return $attachments;
}

/**
 * @since 1.20.79
 */
add_action('woocommerce_thankyou_mpesa', function ($order_id) {
	$c2b = get_option('woocommerce_mpesa_settings');

	if (($c2b['debug'] ?? 'no') == 'yes') {
		echo '
		<section class="woocommerce-order-details">
		<p>Mpesa request body</p>
			<code>'.WC()->session->get('mpesa_request').'</code>
		</section>';
	}
});