<?php
/**
 * @package Daan\EDD\DeleteAccount
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\EDD\DeleteAccount;

class Plugin {
	/**
	 * Build class.
	 */
	public function __construct() {
		if ( ! function_exists( 'EDD' ) ) {
			return;
		}
		
		new Block();
		new Account();
		new Erasers\Brevo();
	}
}
