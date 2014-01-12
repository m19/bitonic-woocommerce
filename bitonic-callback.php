<?php

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
    function bitonic_callback()
    {
        if(isset($_GET['bitonic_callback']) && isset($_GET['transaction_id'])) {

            global $post;

            require 'bitonic-api-wrapper.php';

            $bitonic = new BitonicApiWrapper($this->get_option('merchant_key'));

            $transaction_id = $_GET['transaction_id'];

            // search for the order_id with the corresponding bitonic transaction id
            $args = array(
                'post_type' => 'shop_order',
                'meta_key' => 'bitonic_transaction_id',
                'meta_value' => $transaction_id
            );

            $query = new WP_Query( $args );

            while ( $query->have_posts() ) : $query->the_post();
                $orderId =  $post->ID;
            endwhile;

            $order = new WC_Order( $orderId );

            $bitonicTransactionStatus = $bitonic->checkTransactionStatus($transaction_id);

            if($bitonicTransactionStatus['result'] == 'success') {
                switch($bitonicTransactionStatus['status']) {
                    case 'confirmed':
                        $order->payment_complete();
                        break;
                    case 'expired':
                    case 'failure':
                    case 'cancelled':
                        $order->cancel_order();
                        break;
                }
            }
        }
    }

    add_action('init', 'bitonic_callback');
}