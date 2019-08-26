<?php
namespace Osen\Settings;

/**
 * @package M-PESA For WooCommerce
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.19.8
 * @since 1.8
 * @license See LICENSE
 */

class B2C
{
    public static function init()
    {
        add_action('admin_init', [new self, 'b2c_settings_init']);
        register_setting('wcmpesab2c', 'b2c_wcmpesa_options');
    }

    public function b2c_settings_init()
    {
        add_settings_section('b2c_section_mpesa', __('Settings for Mpesa Business to Customer', 'woocommerce'), 'b2c_section_b2c_mpesa_cb', 'wcmpesab2c');

        add_settings_field(
            'env',
            __('Environment', 'woocommerce'),
            [new self, 'b2c_fields_env_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'env',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'shortcode',
            __('Mpesa Shortcode', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_shortcode_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'shortcode',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'username',
            __('Mpesa Username', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_username_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'username',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'password',
            __('Mpesa Password', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_password_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'password',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'appkey',
            __('App Consumer Key', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_ck_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'appkey',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'appsecret',
            __('App Consumer Secret', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_cs_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'appsecret',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'passkey',
            __('Online Passkey', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_pk_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'passkey',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'instructions',
            __('Withdrawal Instructions', 'woocommerce'),
            [new self, 'b2c_fields_b2c_mpesa_w_cb'],
            'wcmpesab2c',
            'b2c_section_mpesa',
            [
                'label_for'       => 'instructions',
                'class'           => 'b2c_row',
                'b2c_custom_data' => 'custom',
            ]
        );

    }

    public function b2c_section_b2c_mpesa_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options', ['env' => 'sandbox']); ?>
        <div id="<?php echo esc_attr($args['id']); ?>">
            <h5 style="color: red;">Before You Proceed,</h5>
            <li>Please <a href="https://developer.safaricom.co.ke/" target="_blank">create an app on Daraja</a> if you haven't.
                Fill in the app's consumer key and secret below.</li>
            <li>For security purposes, and for the M-PESA Instant Transaction Notification to work, ensure your site is running
                over https(SSL).</li>
            <li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank">generate sandbox test
                    credentials here</a>.</li>
        </div><?php
    }

    public function b2c_fields_env_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]">
            <option value="sandbox"
                <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'sandbox', false)) : (''); ?>>
                <?php esc_html_e('Sandbox(Testing)', 'woocommerce');?>
            </option>
            <option value="live"
                <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'live', false)) : (''); ?>>
                <?php esc_html_e('Live(Production)', 'woocommerce');?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e('Environment', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_shortcode_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?>"
            class="regular-text">
        <p class="description">
            <?php esc_html_e('B2C Paybill number', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_username_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?>"
            class="regular-text">
        <p class="description">
            <?php esc_html_e('M-PESA Portal Username', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_password_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?>"
            class="regular-text">
        <p class="description">
            <?php esc_html_e('M-PESA Portal Password', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_ck_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?>"
            class="regular-text">
        <p class="description">
            <?php esc_html_e('Daraja application consumer key.', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_cs_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
            data-custom="<?php echo esc_attr($args['b2c_custom_data']); ?>"
            name="b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?>"
            class="regular-text">
        <p class="description">
            <?php esc_html_e('Daraja application consumer secret', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_pk_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>"
            name='b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]' rows='1' cols='50' type='textarea'
            class="large-text code"><?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?></textarea>
        <p class="description">
            <?php esc_html_e('Online Pass Key', 'woocommerce');?>
        </p><?php
    }

    public function b2c_fields_b2c_mpesa_w_cb($args)
    {
        $options = get_option('b2c_wcmpesa_options'); ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>"
            name='b2c_wcmpesa_options[<?php echo esc_attr($args['label_for']); ?>]' rows='5' cols='50' type='textarea'
            class="large-text code"><?php echo esc_attr(isset($options[$args['label_for']]) ? $options[$args['label_for']] : ''); ?></textarea>
        <p class="description">
            <?php esc_html_e('Instructions to show on Withdrawal Page', 'woocommerce');?>
        </p><?php
    }

    /**
     * top level menu:
     * callback functions
     */
    public function wc_mpesa_b2c_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('b2c_messages', 'b2c_message', __('WPay C2B Settings Updated', 'woocommerce'), 'updated');
        }

        settings_errors('b2c_messages'); ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post"><?php
                settings_fields('wcmpesab2c');
                do_settings_sections('wcmpesab2c');
                submit_button('Save C2B Settings'); ?>
            </form>
        </div><?php
    }
}