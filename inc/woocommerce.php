<?php
/**
 * @package M-PESA For WooCommerce
 * @subpackage WooCommerce Functions
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

/**
 * Add Kenyan counties to list of woocommerce states
 */
add_filter('woocommerce_states', 'mpesa_ke_woocommerce_counties');
function mpesa_ke_woocommerce_counties($counties)
{
    if (isset($counties['KE'])) { return; }

    $counties['KE'] = array(
        'BAR' => __('Baringo', 'woocommerce'),
        'BMT' => __('Bomet', 'woocommerce'),
        'BGM' => __('Bungoma', 'woocommerce'),
        'BSA' => __('Busia', 'woocommerce'),
        'EGM' => __('Elgeyo-Marakwet', 'woocommerce'),
        'EBU' => __('Embu', 'woocommerce'),
        'GSA' => __('Garissa', 'woocommerce'),
        'HMA' => __('Homa Bay', 'woocommerce'),
        'ISL' => __('Isiolo', 'woocommerce'),
        'KAJ' => __('Kajiado', 'woocommerce'),
        'KAK' => __('Kakamega', 'woocommerce'),
        'KCO' => __('Kericho', 'woocommerce'),
        'KBU' => __('Kiambu', 'woocommerce'),
        'KLF' => __('Kilifi', 'woocommerce'),
        'KIR' => __('Kirinyaga', 'woocommerce'),
        'KSI' => __('Kisii', 'woocommerce'),
        'KIS' => __('Kisumu', 'woocommerce'),
        'KTU' => __('Kitui', 'woocommerce'),
        'KLE' => __('Kwale', 'woocommerce'),
        'LKP' => __('Laikipia', 'woocommerce'),
        'LAU' => __('Lamu', 'woocommerce'),
        'MCS' => __('Machakos', 'woocommerce'),
        'MUE' => __('Makueni', 'woocommerce'),
        'MDA' => __('Mandera', 'woocommerce'),
        'MAR' => __('Marsabit', 'woocommerce'),
        'MRU' => __('Meru', 'woocommerce'),
        'MIG' => __('Migori', 'woocommerce'),
        'MBA' => __('Mombasa', 'woocommerce'),
        'MRA' => __('Muranga', 'woocommerce'),
        'NBO' => __('Nairobi', 'woocommerce'),
        'NKU' => __('Nakuru', 'woocommerce'),
        'NDI' => __('Nandi', 'woocommerce'),
        'NRK' => __('Narok', 'woocommerce'),
        'NYI' => __('Nyamira', 'woocommerce'),
        'NDR' => __('Nyandarua', 'woocommerce'),
        'NER' => __('Nyeri', 'woocommerce'),
        'SMB' => __('Samburu', 'woocommerce'),
        'SYA' => __('Siaya', 'woocommerce'),
        'TVT' => __('Taita Taveta', 'woocommerce'),
        'TAN' => __('Tana River', 'woocommerce'),
        'TNT' => __('Tharaka-Nithi', 'woocommerce'),
        'TRN' => __('Trans-Nzoia', 'woocommerce'),
        'TUR' => __('Turkana', 'woocommerce'),
        'USG' => __('Uasin Gishu', 'woocommerce'),
        'VHG' => __('Vihiga', 'woocommerce'),
        'WJR' => __('Wajir', 'woocommerce'),
        'PKT' => __('West Pokot', 'woocommerce'),
    );

    return $counties;
}

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
                "on-hold"    => "This Order Is On Hold",
                "complete"   => "This Order Is Complete",
                "cancelled"  => "This Order Is Cancelled",
                "refunded"   => "This Order Is Refunded",
                "failed"     => "This Order Failed",
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
    return $attachments;
}

//add_action('woocommerce_email_order_details', 'wcmpesa_email_order_mpesa_receipt', 1, 4);
function wcmpesa_email_order_mpesa_receipt($order, $admin, $plain, $email)
{
    $post    = get_post_id_by_meta_key_and_value('_order_id', $order);
    $receipt = get_post_meta($post, '_receipt', true);
    if (!empty($receipt)) {
        __('<strong>MPESA RECEIPT NUMBER: </strong> ' . $receipt, 'woocommerce');
    }
}

add_action('woocommerce_before_email_order', 'add_order_instruction_email', 10, 2);
function add_order_instruction_email($order, $sent_to_admin)
{
    if (!$sent_to_admin) {
        if ('mpesa' == $order->payment_method) {
            echo wpautop(wptexturize($instructions)) . PHP_EOL;
        }
    }
}
