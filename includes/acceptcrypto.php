<?php
/**
 * Created by PhpStorm.
 * User: Sven
 * Date: 3/19/2018
 * Time: 22:05
 */

class acceptcrypto {

	public static function checkout($name, $amount, $currency, $background, $wc_return_url, $wc_server_callback, $wc_order_id, $email_address, $bearer) {

		$response = array();

		$url = "https://acceptcryp.to/api/v1/form";

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, "label=".$name."&amount=".$amount. "&currency=".$currency."&background=".$background."&wc_return_url=".$wc_return_url."&wc_server_callback=".$wc_server_callback."&wc_order_id=".$wc_order_id);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer " . $bearer
		));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($curl);

		if($result) {

			$result = json_decode($result, true);

			if(!$result["error"]) {

				$token = $result["token"];

				$url2 = "https://acceptcryp.to/api/v1/payment/".$token;

				$curl2 = curl_init($url2);
				curl_setopt($curl2, CURLOPT_POST, 1);
				curl_setopt($curl2, CURLOPT_POSTFIELDS, "email_address=".$email_address);
				curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
				$result2 = curl_exec($curl2);

				if($result2) {

					$result2 = json_decode($result2, true);

					if(!$result2["error"]) {

						$paymentToken = $result2["payment_id"];

						$response["error"] = false;
						$response["redirect"] = "https://acceptcryp.to/checkout/".$paymentToken;

					} else {

						$response["error"] = true;
						$response["message"] = $result2["message"];

					}

				} else {

					$response["error"] = true;
					$response["message"] = "An error occurred, please try again";

				}

			} else {

				$response["error"] = true;
				$response["message"] = $result["message"];

			}

		} else {

			$response["error"] = true;
			$response["message"] = "An error occurred, please try again";

		}

		return $response;

	}

	public static function checkPayment($wc_order_id, $bearer) {

		$response = array();

		$url = "https://acceptcryp.to/api/v1/woo/".$wc_order_id;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer " . $bearer
		));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		if($result) {

			$result = json_decode( $result, true );

			if ($result["error"] == false) {

				$response = $result;

			} else {

				$response["error"] = true;

			}

		} else {

			$response["error"] = true;

		}

		return $response;

	}

}