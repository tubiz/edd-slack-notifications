<?php
/*
    Plugin Name: Easy Digital Downloads - Slack Notifications
    Plugin URL: https://bosun.me/edd-slack-notifications
    Description: Easy Digital Downloads Slack Notifications
    Version: 2.0.0
    Author: Tunbosun Ayinla
    Author URI: http://bosun.me
    License: GPL-2.0+
    License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

function tbz_edd_slack_activation(){

    if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {

        // display notice
        add_action( 'admin_notices', 'tbz_edd_slack_admin_notices' );

        return;
    }

}
add_action( 'admin_init', 'tbz_edd_slack_activation' );


function tbz_edd_slack_admin_notices(){

    if ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
        echo '<div class="error"><p>You must install & activate <a href="http://wordpress.org/plugins/easy-digital-downloads/" title="Easy Digital Downloads" target="_blank"><strong>Easy Digital Downloads</strong></a> to use <strong>Easy Digital Downloads - Slack Notifications</strong></p></div>';
    }

}


function tbz_edd_slack_subsection( $sections ) {
    $sections['tbz-slack'] = 'Slack';
    return $sections;
}
add_filter( 'edd_settings_sections_extensions', 'tbz_edd_slack_subsection', 10, 1 );


function tbz_edd_slack_settings( $settings ){

    $slack_settings = array(
        array(
            'id' => 'tbz_slack_header',
            'name' => '<strong>Slack Notifications</strong>',
            'desc' => '',
            'type' => 'header',
            'size' => 'regular'
        ),
        array(
            'id'    => 'tbz_enable_slack_notification',
            'name'  => 'Enable Slack Notifications',
            'desc'  => 'Check this to turn on Slack notifications',
            'type'  => 'checkbox',
            'std'   => '1'
        ),
        array(
            'id'        => 'tbz_slack_bot_name',
            'name'      => 'Bot Name',
            'desc'      => 'Enter the name of your Bot, the default is: ' . get_bloginfo('name') . ' Sales Bot',
            'type'      => 'text',
            'size'      => 'all-options'
        ),
        array(
            'id'        => 'tbz_slack_icon_emoji',
            'name'      => 'Bot Icon',
            'desc'      => 'Enter the emoji icon for your bot. Click <a href="http://emoji-cheat-sheet.com" target="_blank">here</a> to view the list of available emoji icon. You are to enter only a single emoji icon. The default is :moneybag:',
            'type'      => 'text',
            'size'      => 'all-options'
        ),
        array(
            'id'        => 'tbz_slack_webhook_url',
            'name'      => 'Webhook URL',
            'desc'      => '<br />Enter the url of the webhook created for the channel the notifications will be sent to. This can be created <a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">here</a>',
            'type'      => 'text',
            'size'      => 'large'
        ),
        array(
            'id'        => 'tbz_slack_channel',
            'name'      => 'Channel Name',
            'desc'      => 'If you want to send the notifications to a different channel/user from the one the webhook url was configured to. To send to another channel use #channelname. To send to a specific user use @username. This field can be empty.',
            'type'      => 'text',
            'size'      => 'all-options'
        ),
    );

    if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
        $slack_settings = array( 'tbz-slack' => $slack_settings );
    }

    return array_merge( $settings, $slack_settings );
}
add_filter( 'edd_settings_extensions', 'tbz_edd_slack_settings' );


function tbz_edd_notify_slack( $payment_id ){

    $edd_options = edd_get_settings();

    $enable_slack   = isset( $edd_options['tbz_enable_slack_notification'] ) ? $edd_options['tbz_enable_slack_notification'] : '';
    $slack_channel  = isset( $edd_options['tbz_slack_channel'] ) ? $edd_options['tbz_slack_channel'] : '';
    $webhook_url    = isset( $edd_options['tbz_slack_webhook_url'] )? $edd_options['tbz_slack_webhook_url'] : '';

    if( ! ( $enable_slack && $webhook_url ) ){
        return;
    }

    $payment_meta       = edd_get_payment_meta( $payment_id );

    $emoji              = ! empty( $edd_options['tbz_slack_icon_emoji'] ) ? $edd_options['tbz_slack_icon_emoji'] : ':moneybag:';

    $bot_name           = ! empty( $edd_options['tbz_slack_bot_name'] ) ? $edd_options['tbz_slack_bot_name'] : get_bloginfo( 'name' ) . ' Sales Bot';

    $site_name          = get_bloginfo('name');

    $order_amount       = esc_attr( edd_format_amount( edd_get_payment_amount( $payment_id ) ) );
    $currency_symbol    = edd_currency_symbol( $payment_meta['currency'] );
    $currency_symbol    = html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' );

    $cart_items         = edd_get_payment_meta_cart_details( $payment_id );

    $items_sold         = "";

    foreach ( $cart_items as $key => $cart_item ){
        $name   = $cart_item['name'];
        $price  = $cart_item['price'];
        $items_sold .= "$name | $currency_symbol$order_amount \n";
    }

    $gateway        = edd_get_payment_gateway( $payment_id );
    $payment_method = edd_get_gateway_admin_label( $gateway );

    $fallback   = "New sale notification of $currency_symbol$order_amount on $site_name";

    $fields     = array();
    $attachment = array();

    $fields[]   = array(
        'title'     => 'ITEM(S)',
        'value'     => $items_sold,
        'short'     => false
    );

    $fields[] = array(
        'title'     => 'Order Total',
        'value'     => $currency_symbol.$order_amount,
        'short'     => false
    );

    $fields[] = array(
        'title'     => 'Payment Method',
        'value'     => $payment_method,
        'short'     => false
    );

    $payment_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $payment_id );

    $attachment[] = array(
        'fallback'  => $fallback,
        'title'     => 'View Order Details',
        'pretext'   => 'A new sale has occurred on ' . $site_name,
        'title_link'=> $payment_url,
        'fields'    => $fields,
        'color'     => 'good',
        'mrkdwn_in' => array( 'text' ),
    );

    $payload = array(
        'username'      => $bot_name,
        'attachments'   => $attachment,
        'icon_emoji'    => $emoji,
        'channel'       => $slack_channel,
    );

    $args = array(
        'body'      => json_encode( $payload ),
        'timeout'   => 30
    );

    $response = wp_remote_post( $webhook_url, $args );

    return;
}
add_action( 'edd_complete_purchase', 'tbz_edd_notify_slack' );

function tbz_edd_slack_settings_link( $links ){

    $plugin_links = array(
        '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions&section=tbz-slack' ) . '">Settings</a>',
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'tbz_edd_slack_settings_link', 10, 2 );
