<?php
/*
Plugin Name: Munt WooCommerce plugin
Plugin URI: https://getmunt.com
Description: WooCommerce cryptocurrency gateway by Munt
Author: Munt
Version: 1.0
Author URI: https://getmunt.com
Text Domain: Munt
Copyright: © 2018 Munt
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	include_once dirname( __FILE__ ) . "/includes/munt.php";

	function init_gateway() {

		class Munt_Gateway extends WC_Payment_Gateway {

			function __construct() {

				$this->id = 'woo_munt';
				$this->icon = 'https://getmunt.com/images/logo.png';
				$this->method_title = __("Munt for WooCommerce", "munt_payment");
				$this->method_description = __("Munt cryptocurrency payment gateway for WooCommerce", "woo_munt");
				$this->title = __("Munt Cryptocurrency Gateway", "woo_munt");
				$this->description = __("Pay with multiple cryptocurrencies including Bitcoin, Bitcoin Cash, Litecoin, Ripple, Ethereum and Dash.", "woo_munt");
				$this->order_button_text = __("Pay With Crypto", 'munt_payment');
				$this->enabled = $this->get_option("enabled", "yes");
				$this->has_fields = false;
				$this->supports = array(
					"products"
				);

				$this->supported_currencies = array("USD", "EUR", "GBP", "AUD", "BRL", "CAD", "CHF", "CLP", "CNY", "DKK", "HKD", "INR", "ISK", "JPY", "KRW", "NZD", "PLN", "RUB", "SEK", "SGD", "THB", "TWD");

				$this->init_form_fields();
				$this->init_settings();

				add_action('woocommerce_api_munt_form', array($this, 'form_callback'));
				add_action('woocommerce_api_munt_server', array($this, 'server_callback'));
				add_action("admin_notices", array($this, "plugin_notices"));

				if (is_admin()) {

					add_action("woocommerce_update_options_payment_gateways_" . $this->id, array($this, "process_admin_options"));

				}

			}

			public function init_form_fields() {

				$this->form_fields = array(
					"enabled" => array(
						"title" => __("Enable", "woo_munt"),
						"type" => "checkbox",
						"label" => __("Enable Munt", "woo_munt"),
						"default" => "yes"
					),
					"api_key" => array(
						"title" => __("API Key", "woo_munt"),
						"type" => "text",
						"default" => ""
					),
					"checkout_text" => array(
						"title" => __("Checkout text", "woo_munt"),
						"type" => "text",
						"default" => "Cart checkout"
					),
					"background_color" => array(
						"title" => __("Background color", "woo_munt"),
						"type" => "text",
						"default" => "FABD58"
					)
				);

			}

			public function process_payment($order_id) {

				$order = wc_get_order($order_id);

				$email_address = $order->get_billing_email();

				$bearer = $this->get_option("api_key");

				if(isset($bearer) && $bearer != "") {

					$label = $this->get_option("checkout_text", "Cart checkout");
					$amount = $order->get_total();
					$currency = strtoupper(get_woocommerce_currency());
					$background = $this->get_option("background_color", "FABD58");
					$return_url = str_replace('https:', 'http:', home_url('/wc-api/munt_form?order_id='.$order_id));
					$server_callback = str_replace('https:', 'http:', home_url('/wc-api/munt_server'));

					$checkout = Munt::checkout($label, $amount, $currency, $background, $return_url, $server_callback, $order_id, $email_address, $bearer);

					if(!$checkout["error"]) {

						return array(
							"result" => "success",
							"redirect" => $checkout["redirect"]
						);

					} else {

						wc_add_notice('An error occurred, please try again later', 'error');
						return;

					}

				} else {

					wc_add_notice('Unauthorized use', 'error');
					return;

				}

			}

			public function form_callback() {

				$order_id = $_GET["order_id"];

				$order = wc_get_order($order_id);

				if ($order) {

					$bearer = $this->get_option( "api_key" );

					if(isset($bearer)) {

						$result = Munt::checkPayment( $order_id, $bearer );

						if($result["error"] == false) {

							if($result["payment"] == true) {

								if($result["confirmed"] == "complete") {

									$order->update_status("completed");

								} else {

									$order->update_status("processing");

								}

								header("Location: " . $this->get_return_url($order));

							} else {

								wp_die("No payments were found", "Payment Error");

							}

						} else {

							wp_die("An error occurred, please try again", "Plugin Error");

						}

					} else {

						wp_die( "Unauthorized, please enter the API key in Munt config", "Plugin Error" );

					}

				}

			}

			public function server_callback() {

			    $response = array();

				$order_id = $_POST["order_id"];

				$order = wc_get_order($order_id);

				if($order) {

					$bearer = $this->get_option("api_key");

					if(isset($bearer)) {

						$result = Munt::checkPayment( $order_id, $bearer );

						if ( $result["error"] == false ) {

							if ( $result["payment"] == true ) {

								if ( $result["confirmed"] == "complete" ) {

									$order->update_status( "completed" );

								} else {

									$order->update_status( "processing" );

								}

								$response["error"] = false;

							} else {

                                $response["error"] = true;
                                $response["message"] = "No payment found";

                            }

						} else {

						    $response["error"] = true;
						    $response["error"] = $result["message"];

						}

					} else {

					    $response["error"] = true;
					    $response["message"] = "No authentication token added";

					}

				} else {

				    $response["error"] = true;
				    $response["message"] = "No order found";

				}

				wp_send_json($response);

			}

			public function plugin_notices() {

				$api_key = $this->get_option("api_key");

				if(!isset($api_key) || $api_key == "") {

					?>
					<div class="notice notice-warning">
						<p>Your API key from Munt is missing, please add one. <a class="install-now button" href="https://getmunt.com/dashboard/account" target="_blank">Get API key</a></p>
					</div>
					<?php

				}

			}

		}

	}

	add_action("plugins_loaded", "init_gateway");

	function woo_add_gateway_class($methods) {

		$methods[] = 'Munt_Gateway';
		return $methods;

	}

	add_filter("woocommerce_payment_gateways", "woo_add_gateway_class");

}