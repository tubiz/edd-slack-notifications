<?php
/*
    Plugin Name: Easy Digital Downloads - Slack Notifications
    Plugin URL: http://bosun.me/edd-slack-notifications
    Description: Easy Digital Downloads Slack Notifications
    Version: 1.0.0
    Author: Tunbosun Ayinla
    Author URI: http://bosun.me
    License: GPL-2.0+
    License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

function tbz_edd_slack_settings_tab( $tabs ){
    $slack_tab = array();

    $slack_tab['slack'] = 'Slack';

    return array_merge( $tabs , $slack_tab );
}
add_filter( 'edd_settings_tabs', 'tbz_edd_slack_settings_tab' );

function tbz_edd_slack_settings(  $edd_settings ){

    $slack_settings = array(
        'slack' => array(
            'tbz_slack_header' => array(
                'id' => 'tbz_slack_header',
                'name' => '<strong>Slack Notifications Settings</strong>',
                'desc' => 'Configure Slack Notifications',
                'type' => 'header'
            ),
            'tbz_enable_slack_notification' => array(
                'id'    => 'tbz_enable_slack_notification',
                'name'  => 'Enable Slack Notifications',
                'desc'  => 'Check this to turn on Slack notifications',
                'type'  => 'checkbox',
                'std'   => '1'
            ),
            'tbz_slack_channel' => array(
                'id'        => 'tbz_slack_channel',
                'name'      => 'Channel Name',
                'desc'      => 'Enter the name of the Channel notifications should be sent to e.g. #edd',
                'type'      => 'text',
                'size'      => 'all-options'
            ),
            'tbz_slack_webhook_url' => array(
                'id'        => 'tbz_slack_webhook_url',
                'name'      => 'Webhook URL',
                'desc'      => '<br />Enter the url of the webhook created for the channel above. This can be created <a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">here</a>',
                'type'      => 'text',
                'size'      => 'large'
            ),
        )
    );

    return array_merge( $edd_settings, $slack_settings );
}
add_filter( 'edd_registered_settings', 'tbz_edd_slack_settings' );

function tbz_edd_notify_slack( $payment_id ){

    global $edd_options;

    $enable_slack   = isset( $edd_options['tbz_enable_slack_notification'] ) ? $edd_options['tbz_enable_slack_notification'] : '';
    $slack_channel  = isset( $edd_options['tbz_slack_channel'] ) ? $edd_options['tbz_slack_channel'] : '';
    $webhook_url    = isset( $edd_options['tbz_slack_webhook_url'] )? $edd_options['tbz_slack_webhook_url'] : '';

    if( ! ( $enable_slack && $slack_channel && $webhook_url ) ){
        return;
    }

    $site_name      = get_bloginfo('name');

    $order_amount   = esc_attr( edd_format_amount( edd_get_payment_amount( $payment_id ) ) );
    $curreny_symbol = edd_currency_symbol( $payment_meta['currency'] );

    $payment_meta   = edd_get_payment_meta( $payment_id );

    $cart_items     = edd_get_payment_meta_cart_details( $payment_id );

    $items_sold     = "";

    foreach ( $cart_items as $key => $cart_item ){
        $name   = $cart_item['name'];
        $price  = $cart_item['price'];
        $items_sold .= "*Name:* $name | *Price:* $curreny_symbol$price \n";
    }

    $gateway        = edd_get_payment_gateway( $payment_id );
    $payment_method = edd_get_gateway_admin_label( $gateway );

    $message = "A new sale has occurred on $site_name \n\n";
    $message .= "*ITEM(S):* \n";
    $message .= $items_sold;
    $message .= "\n *Order Total:* $curreny_symbol$order_amount \n";
    $message .= "*Payment Method:* $payment_method \n";

    $attachment = array();

    $attachment[] = array(
        'fallback'  => "New sale notification of $curreny_symbol$price on $site_name",
        'title'     => 'New Sale Notification',
        'text'      => $message,
        'color'     => 'good',
        'mrkdwn_in' => array( 'text' ),
    );

    $payload = array(
        'username'      => "$site_name Sales Bot",
        'attachments'   => $attachment,
        'icon_emoji'    => ':moneybag:',
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
