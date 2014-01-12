<?php
/**
 * Plugin Name: Bitonic Woocommerce
 * Plugin URI: https://github.com/m19/bitonic-woocommerce
 * Description: Bitonic payments for Woocommerce
 * Version: 0.1
 * Author: Martijn Buurman
 * Author URI: http://m19.nl
 * License: WTFPL http://www.wtfpl.net/
 */

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{

    function declareWooBitonic()
    {
        if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
            return;
        }

        require 'bitonic-api-wrapper.php';

        class WC_Bitonic extends WC_Payment_Gateway {

            protected $bitonic;

            public function __construct()
            {
                $this->id = 'bitonic';
                $this->has_fields = false;

                $this->init_form_fields();

                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->bitonic = new BitonicApiWrapper($this->get_option('merchant_key'));

                add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));

                add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);

            }

            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woothemes'),
                        'type' => 'checkbox',
                        'label' => __('Enable Bitonic Payment', 'woothemes'),
                        'default' =>  'yes'
                    ),
                    'title' => array(
                        'title' => __('Title', 'woothemes'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                        'default' => __('Bitcoins', 'woothemes')
                    ),
                    'description' => array(
                        'title' => __( 'Customer Message', 'woothemes' ),
                        'type' => 'textarea',
                        'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'woothemes' ),
                        'default' => 'You will be redirected to bitonic.nl to complete your purchase.'
                    ),
                    'merchant_key' => array(
                        'title' => __('Merchant Key', 'woothemes'),
                        'type' => 'text',
                        'description' => __('Enter the Merchant Key you created at bitonic.nl'),
                    )
                );
            }

            public function admin_options()
            {
                echo '<h3>' . _e('Bitcoin Payment', 'woothemes') . '</h3>';
                echo '<p>' . _e('Allows bitcoin payments via bitonic.nl', 'woothemes') . '</p>';
                echo '<table class="form-table">';
                    $this->generate_settings_html();
                echo '</table>';
            }

            public function email_instructions( $order, $sent_to_admin ) {
                return;
            }

            function payment_fields() {
                if ($this->description) echo wpautop(wptexturize($this->description));
            }

            function thankyou_page() {
                if ($this->description) echo wpautop(wptexturize($this->description));
            }

            function process_payment($order_id) {

                global $woocommerce;

                $order = new WC_Order($order_id);
                // Mark as on-hold (we're awaiting the coins)
                $order->update_status('on-hold', __('Awaiting payment notification from bitonic.nl', 'woothemes'));

                // redirect to the order confirmation page after leaving Bitonic.nl
                $redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))));

                // Bitonic should report transaction updates to this url
                $report_url = get_option('siteurl')."/?bitonic_callback=1";

                // options for the API call
                $options = array(
                    'description' => get_bloginfo('name') . ' order #' . $order->id,
                    'return_url' => $redirect,
                    'report_url' => $report_url,
                    'euro' => $order->order_total * 100
                );

                // call the Bitonic API and start the payment
                $bitonicOrder = $this->bitonic->startPayment($options);

                if($bitonicOrder['result'] = 'success') {

                    // save the bitonic transaction id with this order
                    update_post_meta (
                        $order_id,
                        'bitonic_transaction_id',
                        $bitonicOrder['transaction_id']
                    );

                    return array(
                        'result' => 'success',
                        'redirect' => $bitonicOrder['url']
                    );

                } else {
                    $woocommerce->add_error(__('Error creating Bitonic invoice. Please try again or try another payment method.'));
                }
            }

            function callback($transaction_id)
            {
                global $post;

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

                $bitonicTransactionStatus = $this->bitonic->checkTransactionStatus($transaction_id);

                if($bitonicTransactionStatus['result'] == 'success') {
                    switch($bitonicTransactionStatus['status']) {
                        case 'open':
                            break;
                        case 'paid':
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
    }

    include plugin_dir_path(__FILE__).'bitonic-callback.php';

    function add_bitonic_gateway( $methods ) {
        $methods[] = 'WC_Bitonic';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_bitonic_gateway');

    add_action('plugins_loaded', 'declareWooBitonic', 0);

}
