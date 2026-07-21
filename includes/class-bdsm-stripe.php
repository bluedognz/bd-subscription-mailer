<?php
/**
 * Stripe API lookup for live card expiry.
 *
 * WooCommerce stores a snapshot of the card taken when the token was saved.
 * Stripe silently refreshes cards via the Visa/Mastercard account updater, so
 * that snapshot drifts out of date. This class fetches the real expiry and
 * caches it on the subscription so nothing has to hit the API on page load.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin read-only Stripe API client.
 */
class BDSM_Stripe {

	const META_EXPIRY  = '_bdsm_stripe_card_expiry';
	const META_LAST4   = '_bdsm_stripe_card_last4';
	const META_BRAND   = '_bdsm_stripe_card_brand';
	const META_CHECKED = '_bdsm_stripe_checked_at';

	/**
	 * Secret key from the WooCommerce Stripe gateway settings.
	 *
	 * @return string Empty when unavailable.
	 */
	public static function get_secret_key() {
		$settings = get_option( 'woocommerce_stripe_settings', array() );
		if ( ! is_array( $settings ) ) {
			return '';
		}

		$testmode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];
		$key      = $testmode ? ( $settings['test_secret_key'] ?? '' ) : ( $settings['secret_key'] ?? '' );

		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Can we talk to Stripe?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return '' !== self::get_secret_key();
	}

	/**
	 * Fetch the live card for a subscription straight from Stripe.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array|null month, year, last4, brand — or null.
	 */
	public static function fetch_card( $subscription ) {
		$key = self::get_secret_key();
		if ( '' === $key ) {
			return null;
		}

		$payment_method = '';
		foreach ( array( '_stripe_source_id', '_stripe_payment_method_id' ) as $meta_key ) {
			$value = (string) $subscription->get_meta( $meta_key );
			if ( '' !== $value ) {
				$payment_method = $value;
				break;
			}
		}

		if ( '' === $payment_method ) {
			return null;
		}

		$customer_id = (string) $subscription->get_meta( '_stripe_customer_id' );

		if ( str_starts_with( $payment_method, 'pm_' ) ) {
			$data = self::request( 'payment_methods/' . rawurlencode( $payment_method ), $key );
			$card = $data['card'] ?? null;
		} elseif ( '' !== $customer_id ) {
			// Legacy source / card object hanging off the customer.
			$data = self::request( 'customers/' . rawurlencode( $customer_id ) . '/sources/' . rawurlencode( $payment_method ), $key );
			if ( isset( $data['exp_month'] ) ) {
				$card = $data;
			} else {
				$card = $data['card'] ?? null;
			}
		} else {
			return null;
		}

		if ( ! is_array( $card ) || empty( $card['exp_month'] ) || empty( $card['exp_year'] ) ) {
			return null;
		}

		return array(
			'month' => (int) $card['exp_month'],
			'year'  => (int) $card['exp_year'],
			'last4' => (string) ( $card['last4'] ?? '' ),
			'brand' => (string) ( $card['brand'] ?? '' ),
		);
	}

	/**
	 * Fetch from Stripe and cache the result on the subscription.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array|null The fetched card, or null on failure.
	 */
	public static function refresh( $subscription ) {
		$card = self::fetch_card( $subscription );

		$subscription->update_meta_data( self::META_CHECKED, time() );

		if ( $card ) {
			$subscription->update_meta_data( self::META_EXPIRY, sprintf( '%04d-%02d', $card['year'], $card['month'] ) );
			$subscription->update_meta_data( self::META_LAST4, $card['last4'] );
			$subscription->update_meta_data( self::META_BRAND, $card['brand'] );
		}

		$subscription->save();

		return $card;
	}

	/**
	 * Previously fetched Stripe card data stored on the subscription.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array|null
	 */
	public static function get_stored( $subscription ) {
		$raw = (string) $subscription->get_meta( self::META_EXPIRY );

		if ( ! preg_match( '#^(\d{4})-(\d{2})$#', $raw, $matches ) ) {
			return null;
		}

		return array(
			'month'   => (int) $matches[2],
			'year'    => (int) $matches[1],
			'last4'   => (string) $subscription->get_meta( self::META_LAST4 ),
			'brand'   => (string) $subscription->get_meta( self::META_BRAND ),
			'checked' => (int) $subscription->get_meta( self::META_CHECKED ),
		);
	}

	/**
	 * GET a Stripe API resource.
	 *
	 * @param string $path Path after /v1/.
	 * @param string $key  Secret key.
	 * @return array|null Decoded body, or null on any failure.
	 */
	private static function request( $path, $key ) {
		$response = wp_remote_get(
			'https://api.stripe.com/v1/' . $path,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}
}
