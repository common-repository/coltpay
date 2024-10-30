<?php

class WC_Gateway_ColtPay extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'coltpay';
        $this->has_fields = true;
        $this->method_title = __('ColtPay', 'coltpay');
        $this->method_description = '<p>' .
            __( 'A payment gateway that sends your customers to ColtPay to pay with cryptocurrency.', 'coltpay' )
            . '</p><p>' .
            sprintf(
                __( 'If you do not currently have a coltpay account, you can set one up here: %s', 'coltpay' ),
                '<a target="_blank" href="https://coltpay.com/">https://coltpay.com/</a>'
            ). '<p>Make sure you have your public and private API key setup under Settings > Coltpay';

        $this->order_button_text = __('Proceed to Pay with Bitcoin', 'coltpay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->debug = 'yes' === $this->get_option('debug', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    }

    public function init_form_fields()
    {
        $page_id_arr = array();
        if ($pages = get_pages()) {
            foreach ($pages as $page) {
                $page_id_arr[$page->ID] = $page->post_title;
            }
        }
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable ColtPay Commerce Payment', 'coltpay'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with Bitcoin', 'coltpay'),
                'desc_tip' => false,
            ),
            'coltpay_description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'default' => '',
            )
        );
    }

    public function process_payment($order_id)
    {
		

	 $coltpay_public_api_key = get_option('coltpay_public_api_key');
     $coltpay_private_api_key = get_option('coltpay_private_api_key');
        if ( empty($coltpay_public_api_key) ) return array( 'result' => 'fail', 'reason'=> 'missing api key' );
        $order = wc_get_order($order_id);
        $order_data = $order->get_data();
        $total_amount = $order->get_total();

        $thankyou_page =  $this->get_return_url($order) ;

        $invoice_url = 'https://api.coltpay.com/v1/invoice/create?public_api_token=' . $coltpay_public_api_key;

        $webhook_url = WC()->api_request_url( 'WC_Gateway_ColtPay' );

		$secret = mt_rand(1000,10000000);
		
		
        $fields_items = array(
            'price' => $total_amount,
            'currency' => $order_data['currency'],
            'order_id' => $order_id,
            'notification_url' => admin_url( 'admin-post.php?action=coltpay_update&secret='.$secret ),
			'source'=> 'woocommerce'
        );
		

        $remote_args = array(
                'body' => $fields
            );

        $response = wp_remote_post( $invoice_url, array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'        => json_encode($fields_items),
			'method'      => 'POST',
			'data_format' => 'body',
			) 
		);

        if ( is_wp_error( $response ) ) {		

            return array('result' => 'fail', 'data'=> $response);
        }
        $raw_result = wp_remote_retrieve_body( $response );

        $result_obj = json_decode($raw_result, true);
        if (empty($result_obj) || !isset($result_obj['success']) || $result_obj['success'] == false) {
            return array('result' => 'fail', 'message'=> 'result_obj error');
        }
		$invoice = $result_obj['data'];

		$code = $result_obj['result']['id'];
        $invoice_arr = $result_obj['data'];
        $order->update_meta_data('_coltpay_invoice_id', $code);
        $order->update_meta_data('_coltpay_invoice_secret', $secret);
        $order->save();

		$cancel_url = urlencode(esc_url_raw($order->get_cancel_order_url_raw()));
		$success_url = urlencode($thankyou_page);


		$url = "https://coltpay.com/checkout/".$invoice['id']."/".$invoice['guid']."?cancel_url=".$cancel_url."&success_url=".$success_url;
        return array(
            'result' => 'success',
            'redirect' => $url
        );
    }

    public function payment_fields()
    {
        $description = $this->get_option('coltpay_description');
        $text = '<p>' . $description . '</p>';
        echo $text;
    }

}