<?php
/**
 * @package WPay C2B
 * @subpackage Admin Settings Page
 * @author Osen Concepts <hi@osen.co.ke>
 * @version 1.8
 * @since 1.8
 * @license See LICENSE
 */

add_action( 'admin_init', 'wpayb2c_settings_init' );
function wpayb2c_settings_init() {
    register_setting( 'wpayb2c', 'wpayb2c_options' );
    
    add_settings_section( 'wpayb2c_section_mpesa', __( 'Settings for Mpesa B2C', 'wpayb2c' ), 'wpayb2c_section_wpayb2c_mpesa_cb', 'wpayb2c' );

    add_settings_field(
        'env',
        __( 'Environment', 'wpayb2c' ),
        'wpayb2c_fields_env_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'env',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'type',
        __( 'Identifier Type', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'type',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'shortcode',
        __( 'Mpesa Shortcode', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_shortcode_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'shortcode',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'username',
        __( 'Mpesa Username', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_username_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'username',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'password',
        __( 'Mpesa Password', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_password_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'password',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'appkey',
        __( 'App Consumer Key', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_ck_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'appkey',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );

    add_settings_field(
        'appsecret',
        __( 'App Consumer Secret', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_cs_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'appsecret',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'passkey',
        __( 'Online Passkey', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_pk_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'passkey',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'instructions',
        __( 'Withdrawal Instructions', 'wpayb2c' ),
        'wpayb2c_fields_wpayb2c_mpesa_w_cb',
        'wpayb2c',
        'wpayb2c_section_mpesa',
        [
        'label_for' => 'instructions',
        'class' => 'wpayb2c_row',
        'wpayb2c_custom_data' => 'custom',
        ]
    );
    
}

function wpayb2c_section_wpayb2c_mpesa_cb( $args ) {
    $options = get_option( 'wpayb2c_options', ['env'=>'sandbox'] );
    if ( !in_array( 'osen-wc-mpesa/osen-wc-mpesa.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ): ?>
        <p id="<?php echo esc_attr( $args['id'] ); ?>">
            <h5 style="color: red;">Before You Proceed,</h5>
            <li>Please <a href="https://developer.safaricom.co.ke/" target="_blank" >create an app on Daraja</a> if you haven't. Fill in the app's consumer key and secret below.</li><li>For security purposes, and for the MPesa Instant Transaction Notification to work, ensure your site is running over https(SSL).</li>
            <li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank" >generate sandbox test credentials here</a>.</li>
            <li>Click here to <a href="<?php echo home_url( '/?wpayb2c_ipn_register='.esc_attr( $options['env'] ) ); ?>" target="_blank">register confirmation & validation URLs for <?php echo esc_attr( $options['env'] ); ?> </a></li>
        </p>
    <?php endif;
}

function wpayb2c_fields_env_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <select id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    >
        <option value="sandbox" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'sandbox', false ) ) : ( '' ); ?>>
        <?php esc_html_e( 'Sandbox', 'wpayb2c' ); ?>
        </option>
        <option value="live" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'live', false ) ) : ( '' ); ?>>
        <?php esc_html_e( 'Live', 'wpayb2c' ); ?>
        </option>
    </select>
    <p class="description">
    <?php esc_html_e( 'Environment', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_shortcode_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    value="<?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?>"
    class="regular-text"
    >
    <p class="description">
    <?php esc_html_e( 'Paybill/Till or phone number', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_username_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    value="<?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?>"
    class="regular-text"
    >
    <p class="description">
    <?php esc_html_e( 'MPesa Portal Username', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_password_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    value="<?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?>"
    class="regular-text"
    >
    <p class="description">
    <?php esc_html_e( 'MPesa Portal Password', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <select id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    >
        <option value="4" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], '4', false ) ) : ( '' ); ?>>
        <?php esc_html_e( 'Paybill Number', 'wpayb2c' ); ?>
        </option>
        <option value="2" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], '2', false ) ) : ( '' ); ?>>
        <?php esc_html_e( 'Till Number', 'wpayb2c' ); ?>
        </option>
    </select>
    <p class="description">
    <?php esc_html_e( 'Business identifier type', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_ck_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    value="<?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?>"
    class="regular-text"
    >
    <p class="description">
    <?php esc_html_e( 'Daraja application consumer key.', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_cs_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wpayb2c_custom_data'] ); ?>"
    name="wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    value="<?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?>"
    class="regular-text"
    >
    <p class="description">
    <?php esc_html_e( 'Daraja application consumer secret', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_pk_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
        name='wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]' 
        rows='1' 
        cols='50' 
        type='textarea'
        class="large-text code"><?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?></textarea>
    <p class="description">
    <?php esc_html_e( 'Online Pass Key', 'wpayb2c' ); ?>
    </p>
    <?php
}

function wpayb2c_fields_wpayb2c_mpesa_w_cb( $args ) {
    $options = get_option( 'wpayb2c_options' );
    ?>
    <textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
        name='wpayb2c_options[<?php echo esc_attr( $args['label_for'] ); ?>]' 
        rows='2' 
        cols='50' 
        type='textarea'
        class="large-text code"><?php echo esc_attr( isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '' ); ?></textarea>
    <p class="description">
    <?php esc_html_e( 'Instructions to show on Withdrawal Page', 'wpayb2c' ); ?>
    </p>
    <?php
}
 
/**
 * top level menu:
 * callback functions
 */
function wc_mpesa_b2c_settings() {
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
    return;
    }
    
    // add error/update messages
    
    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if ( isset( $_GET['settings-updated'] ) ) {
    // add settings saved message with the class of "updated"
    add_settings_error( 'wpayb2c_messages', 'wpayb2c_message', __( 'WPay C2B Settings Updated', 'wpayb2c' ), 'updated' );
    }
    
    // show error/update messages
    settings_errors( 'wpayb2c_messages' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "wpayb2c"
            settings_fields( 'wpayb2c' );
            // output setting sections and their fields
            // (sections are registered for "wpayb2c", each field is registered to a specific section)
            do_settings_sections( 'wpayb2c' );
            // output save settings button
            submit_button( 'Save C2B Settings' );
            ?>
        </form>
    </div>
    <?php
}