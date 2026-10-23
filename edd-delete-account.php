<?php
/**
 * Plugin Name: Easy Digital Downloads - Delete Account
 * Description: Lets customers delete their own account (GDPR's right to erasure) with a block, e.g. on your Account page.
 * Version: 1.0.1
 * Author: Daan from Daan.dev
 * Author URI: https://daan.dev
 * GitHub Plugin URI: Dan0sz/edd-delete-account
 * Primary Branch: master
 * License: MIT
 * Text Domain: edd-delete-account
 */

require_once __DIR__ . '/vendor/autoload.php';

define( 'EDD_DELETE_ACCOUNT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * @return \Daan\EDD\DeleteAccount\Plugin
 */
function daan_load_edd_delete_account() {
	static $delete_account;
	
	if ( $delete_account === null ) {
		$delete_account = new \Daan\EDD\DeleteAccount\Plugin();
	}
	
	return $delete_account;
}

add_action( 'plugins_loaded', 'daan_load_edd_delete_account' );
