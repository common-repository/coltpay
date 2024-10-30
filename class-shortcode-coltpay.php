<?php
class ColtPay_Shortcode {
	public function __construct()
    {
		add_shortcode( 'coltpay-payment', array( $this, 'shortcode_markup' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_coltpay_button', array( $this, 'process_payment' ) );
		add_action( 'wp_ajax_noprive_coltpay_button', array( $this, 'process_payment' ) );

		add_action('admin_menu', array( $this, 'admin_menu') );
		add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts') );
		$plugin = plugin_basename(__FILE__);
    	add_filter("plugin_action_links_$plugin", 'admin_settings_link');
    }

	function shortcode_markup( $atts ){
		$atts = shortcode_atts( array(
			'id' => 'coltpay_payment_button',
			'class' => '',
			'amount' => '1',
			'currency' => 'USD',
		), $atts );

		extract( $atts );

		$text = '<button id="' . $id . '" class="colt-pay-button ' . $class . '" type="button" data-currency="' . $currency . '" data-amount="' . $amount . '">' . __('Pay With Bitcoin', 'coltpay') . ' <i class="fa fa-refresh fa-spin"></i></button>';
		return $text;
	}

	function enqueue_scripts() {
	    wp_enqueue_style( 'rsm_coltpay_v1', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/css/coltpay.css' );
	    wp_register_script( 'rsm_coltpay_v1', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/coltpay.js', array( 'jquery' ), '1.0', true );
	    wp_localize_script( 'rsm_coltpay_v1', 'ajax_url', admin_url('admin-ajax.php') );
	    wp_enqueue_script('rsm_coltpay_v1');
	}

	function process_payment() {
		// validation
		$amount = isset($_POST['amount']) ? floatval( $_POST['amount'] ) : 1;
		$currency = isset($_POST['currency']) ? sanitize_text_field( $_POST['currency'] ) : 'usd';
		$notification_url = isset($_POST['notification_url']) ? sanitize_text_field( $_POST['notification_url'] ) : '';
		$cancel_url = isset($_POST['cancel_url']) ? sanitize_text_field( $_POST['cancel_url'] ) : get_site_url();
		$success_url = isset($_POST['success_url']) ? sanitize_text_field( $_POST['success_url'] ) : get_site_url();
		
	    $coltpay_public_api_key = get_option('coltpay_public_api_key');

		$api_key = get_option('coltpay_api_key');
        $redirect_url = 'https://coltpay.com/checkout?public_api_token='.$coltpay_public_api_key."&currency=".$currency."&price=".$amount."&cancel_url=".$cancel_url."&success_url=".$success_url."&notification_url=".$notification_url;

        $result = array(
            'result' => array(
                            'status' => 'OK',
                            'redirect' => $redirect_url
                        ),
        );
    	wp_send_json($result);
	}

	
	function admin_menu() {
	    add_options_page("Coltpay", "Coltpay", 'administrator', "coltpay", array( $this, 'admin_page') );
	}

	function admin_page(){
		include('admin/coltpay-admin.php');
	}

	function admin_enqueue_scripts($hook) {
	    if ($hook != 'settings_page_coltpay') {
	        return;
	    }
	    wp_enqueue_style('coltpay-admin', plugins_url('assets/admin.css', __FILE__));
	}

	function admin_settings_link($links) {
	    $settings_link = '<a href="options-general.php?page=coltpay">' . __('Settings', 'coltpay') . '</a>';
	    array_unshift($links, $settings_link);
	    return $links;
	}

}

new ColtPay_Shortcode();