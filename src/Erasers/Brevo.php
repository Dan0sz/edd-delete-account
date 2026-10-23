<?php
/**
 * @package Daan\EDD\DeleteAccount
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\EDD\DeleteAccount\Erasers;

/**
 * Deletes the contact from Brevo, if Brevo for Easy Digital Downloads is set up. A personal data eraser, so it also
 * runs from Tools > Erase Personal Data.
 */
class Brevo {
	/**
	 * Brevo for EDD's API key, in EDD's settings.
	 */
	const API_KEY = 'edd_brevo_api_key';

	/**
	 * Build class.
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register' ] );
	}

	/**
	 * @param array $erasers
	 *
	 * @return array
	 */
	public function register( $erasers ) {
		if ( ! $this->get_api_key() ) {
			return $erasers;
		}

		$erasers[ 'edd-delete-account-brevo' ] = [
			'eraser_friendly_name' => __( 'Brevo Contact', 'edd-delete-account' ),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 *
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		$response = wp_remote_request(
			'https://api.brevo.com/v3/contacts/' . rawurlencode( $email ),
			[
				'method'  => 'DELETE',
				'timeout' => 10,
				'headers' => [ 'api-key' => $this->get_api_key(), 'accept' => 'application/json' ],
			]
		);
		$code     = wp_remote_retrieve_response_code( $response );

		// 404: they weren't a contact.
		if ( in_array( $code, [ 204, 404 ], true ) ) {
			return [ 'items_removed' => $code === 204, 'items_retained' => false, 'messages' => [], 'done' => true ];
		}

		return [
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => [
				sprintf(
					/* translators: 1: email address, 2: error. */
					__( 'Contact %1$s not deleted from Brevo (%2$s).', 'edd-delete-account' ),
					$email,
					is_wp_error( $response ) ? $response->get_error_message() : $code . ' ' . wp_remote_retrieve_body( $response )
				),
			],
			'done'           => true,
		];
	}

	/**
	 * @return string
	 */
	private function get_api_key() {
		return (string) edd_get_option( self::API_KEY, '' );
	}
}
