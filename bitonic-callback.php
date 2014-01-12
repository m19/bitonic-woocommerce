<?php

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
    function bitonic_callback()
    {
        if(isset($_GET['bitonic_callback']) && isset($_GET['transaction_id'])) {

            $WC_Bitonic = new WC_Bitonic();

            $WC_Bitonic->callback($_GET['transaction_id']);
        }
    }

    add_action('init', 'bitonic_callback');
}