<?php

namespace Osen\Woocommerce\Admin;

use Osen\Woocommerce\Mpesa\B2C;

/**
 * @package WPay C2B
 * @subpackage Admin Settings Page
 * @author Osen Concepts <hi@osen.co.ke>
 * @version 2.0.0
 * @since 1.8
 * @license See LICENSE
 */
class Withdraw
{

    public function __construct()
    {
        add_action('admin_init', [$this, 'wcmpesab2cw_settings_init']);
        add_action('wp_ajax_process_wcmpesab2cw_form', [$this, 'process_wcmpesab2cw_form']);
        add_action('wp_ajax_nopriv_process_wcmpesab2cw_form', [$this, 'process_wcmpesab2cw_form']);
    }

    public function wcmpesab2cw_settings_init()
    {
        register_setting('wcmpesab2cw', 'wcmpesab2cw_options');

        add_settings_section('wcmpesab2cw_section_mpesa', __('Withdraw Money To MPesa.', 'woocommerce'), [$this, 'wcmpesab2cw_section_wcmpesab2cw_mpesa_cb'], 'wcmpesab2cw');

        add_settings_field(
            'phone',
            __('Phone Number', 'woocommerce'),
            [$this, 'wcmpesab2cw_fields_wcmpesab2cw_mpesa_shortcode_cb'],
            'wcmpesab2cw',
            'wcmpesab2cw_section_mpesa',
            [
                'label_for'               => 'phone',
                'class'                   => 'wcmpesab2cw_row',
                'wcmpesab2cw_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'amount',
            __('amount', 'woocommerce'),
            [$this, 'wcmpesab2cw_fields_wcmpesab2cw_mpesa_username_cb'],
            'wcmpesab2cw',
            'wcmpesab2cw_section_mpesa',
            [
                'label_for'               => 'amount',
                'class'                   => 'wcmpesab2cw_row',
                'wcmpesab2cw_custom_data' => 'custom',
            ]
        );
    }

    public function wcmpesab2cw_section_wcmpesab2cw_mpesa_cb($args)
    {
        $options      = get_option('b2c_wcmpesa_options');
        $instructions = isset($options['instructions']) ? $options['instructions'] : 'Crosscheck values before submission'; ?>
        <p id="<?php echo esc_attr($args['id']); ?>">
            <p><?php echo esc_attr($instructions); ?></p>
        </p>
    <?php
    }

    public function wcmpesab2cw_fields_wcmpesab2cw_mpesa_shortcode_cb($args)
    {
        $options = get_option('wcmpesab2cw_options');
    ?>
        <input type="tel" id="<?php echo esc_attr($args['label_for']); ?>" data-custom="<?php echo esc_attr($args['wcmpesab2cw_custom_data']); ?>" name="<?php echo esc_attr($args['label_for']); ?>" class="regular-text" required value="2547">
        <p class="description">
            <?php esc_html_e('Phone Number to send funds to.', 'wcmpesab2cw'); ?>
        </p>
    <?php
    }

    public function wcmpesab2cw_fields_wcmpesab2cw_mpesa_username_cb($args)
    {
        $options = get_option('wcmpesab2cw_options');
    ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" data-custom="<?php echo esc_attr($args['wcmpesab2cw_custom_data']); ?>" name="<?php echo esc_attr($args['label_for']); ?>" class="regular-text" required>
        <p class="description">
            <?php esc_html_e('Amount of funds to withdraw.', 'wcmpesab2cw'); ?>
        </p>
    <?php
    }

    /**
     * top level menu:
     * callback functions
     */
    public function wcmpesab2cw_options_page_html()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        } ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form id="wcmpesab2cw_ajax_form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="POST">
                <?php
                do_settings_sections('wcmpesab2cw');

                wp_nonce_field('process_wcmpesab2cw_form', 'wcmpesab2cw_form_nonce');
                ?>
                <input type="hidden" name="action" value="process_wcmpesab2cw_form">
                <input type="hidden" name="reference" value="Withdrawal on <?php echo date('Y-m-d \a\t H:i'); ?>">
                <button class="button button-primary">Withdraw</button>
            </form>
            <?php
            //add_settings_error('wcmpesab2cw_messages', 'wcmpesab2cw_message', __('WPay C2B Settings Updated', 'woocommerce'), 'updated');
            //settings_errors('wcmpesab2cw_messages');
            ?>
            <script id="wcmpesab2cw-ajax" type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#wcmpesab2cw_ajax_form').submit(function(e) {
                        e.preventDefault();

                        var form = $(this);

                        $.post(form.attr('action'), form.serialize(), function(data) {
                            if (data['errorCode']) {
                                $('#wpbody-content .wrap h1').after(
                                    '<div class="error settings-error notice is-dismissible"><p>Error: ' +
                                    data['errorMessage'] +
                                    '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
                                );
                            } else if (data['requestID']) {
                                $('#wpbody-content .wrap h1').after(
                                    '<div class="updated settings-error notice is-dismissible"><p>' +
                                    data['success'] +
                                    '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
                                );
                            } else {
                                $('#wpbody-content .wrap h1').after(
                                    '<div class="error settings-error notice is-dismissible"><p>Sorry, could not connect to Daraja.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
                                );
                            }
                        }, 'json');
                    });
                });
            </script>
        </div>
<?php
    }

    public function process_wcmpesab2cw_form()
    {
        if (!isset($_POST['wcmpesab2cw_form_nonce']) || !wp_verify_nonce($_POST['wcmpesab2cw_form_nonce'], 'process_wcmpesab2cw_form')) {
            exit(wp_send_json(['errorCode' => 'The form is not valid']));
        }

        $amount    = sanitize_text_field($_POST['amount']);
        $phone     = sanitize_text_field($_POST['phone']);
        $reference = sanitize_text_field($_POST['reference']);
        $phone     = str_replace("+", "", $phone);
        $phone     = preg_replace('/^0/', '254', $phone);

        exit(wp_send_json((new B2C)->request($phone, $amount, $reference)));
    }
}
