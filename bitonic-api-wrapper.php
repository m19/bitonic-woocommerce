<?php

require 'vendor/autoload.php';

use Guzzle\Http\Client;

class BitonicApiWrapper {

    protected $merchantKey;
    protected $url;
    protected $client;

    public function __construct($merchantKey)
    {
        $this->merchantKey = $merchantKey;
        $this->client = new Client('http://www.bitonic.nl/json/');
        $this->client->setDefaultOption('query/merchant_key', $this->merchantKey);
    }

    public function startPayment($options)
    {
        $request = $this->client->get('btcpay', array(), array(
                'query' => $options
        ));

        $response = $request->send()->json();

        return $response;
    }

    public function checkTransactionStatus($transaction_id)
    {
        $request = $this->client->get('btcpay/check', array(), array(
            'query' => array(
                'transaction_id' => $transaction_id
            )
        ));

        $response = $request->send()->json();

        return $response;
    }

}