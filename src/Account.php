<?php
/**
 * @package Daan\EDD\DeleteAccount
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\EDD\DeleteAccount;

/**
 * Deletes a customer's own account (GDPR's right to erasure), when the Delete Account block's form is sent.
 *
 * Uses WordPress' personal data erasers (Tools > Erase Personal Data), which Easy Digital Downloads and its add-ons
 * hook into:
 * - The customer is anonymized and their addresses and email logs are removed.
 * - Orders are anonymized (or deleted), depending on Downloads > Settings > Policies > Privacy > Order Statuses. What
 *   was bought, when, for how much and in which country is kept, for the tax administration.
 * - Reviews, comments and AffiliateWP's customer records are anonymized. And the contact is deleted from Brevo, if
 *   Brevo for EDD is set up (@see Erasers\Brevo).
 *
 * Before that, their subscriptions are cancelled (at the gateway, so they're not charged again). EDD Recurring only does
 * that when a customer is anonymized in wp-admin. If that fails, the account isn't deleted.
 *
 * And then:
 * - License keys are detached from the account, so they keep working until they expire, and can still be renewed with
 *   the license key at checkout.
 * - The WordPress user is deleted, and the site's admin is notified by email.
 *
 * Only for customers (@see self::get_customer_roles()), not for staff or affiliates: those contact the site owner.
 */
class Account {
	/**
	 * The form's action.
	 */
	const ACTION = 'edd_delete_account';

	/**
	 * The form's confirmation checkbox.
	 */
	const CONFIRM = 'edd_delete_account_confirm';

	/**
	 * Added to the redirect after the account was deleted.
	 */
	const DELETED = 'account-deleted';

	/**
	 * Added to the redirect if a subscription couldn't be cancelled.
	 */
	const FAILED = 'account-not-deleted';

	/**
	 * Build class.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_delete' ] );
	}

	/**
	 * Only users with no other roles than these can delete their own account.
	 *
	 * @return string[]
	 */
	public static function get_customer_roles() {
		/**
		 * Filters the roles of users who can delete their own account. Users with any other role can't.
		 *
		 * @param string[] $roles Default: subscriber, edd_subscriber and customer.
		 */
		return (array) apply_filters( 'edd_delete_account_customer_roles', [ 'subscriber', 'edd_subscriber', 'customer' ] );
	}

	/**
	 * @param \WP_User $user
	 *
	 * @return bool Whether the user is only a customer, and not staff.
	 */
	public static function is_customer( $user ) {
		return $user->exists() && $user->roles && ! array_diff( $user->roles, self::get_customer_roles() ) && ! is_super_admin( $user->ID );
	}

	/**
	 * Why the account can't be deleted by the customer.
	 *
	 * @param \WP_User $user
	 *
	 * @return string Empty if it can.
	 */
	public static function get_blocked_reason( $user ) {
		$reason = '';

		// Their referrals and (unpaid) earnings need the site owner's attention.
		if ( function_exists( 'affwp_get_affiliate_id' ) && affwp_get_affiliate_id( $user->ID ) ) {
			$reason = __( 'Because you\'re an affiliate, your account can\'t be deleted here.', 'edd-delete-account' );
		}

		$customer = self::get_customer( $user );

		// EDD doesn't anonymize a customer with orders it may not change (e.g. a payment that's still being processed).
		if ( ! $reason && $customer && function_exists( '_edd_privacy_get_payment_action' ) ) {
			foreach ( edd_get_orders( [ 'customer_id' => $customer->id, 'number' => 9999 ] ) as $order ) {
				if ( _edd_privacy_get_payment_action( $order ) === 'none' ) {
					$reason = __( 'Your account can\'t be deleted automatically, because of one of your orders.', 'edd-delete-account' );

					break;
				}
			}
		}

		/**
		 * Filters why a customer can't delete their own account.
		 *
		 * @param string   $reason Empty if they can.
		 * @param \WP_User $user
		 */
		return (string) apply_filters( 'edd_delete_account_blocked_reason', $reason, $user );
	}

	/**
	 * Deletes the account when its form was sent.
	 *
	 * @return void
	 */
	public function maybe_delete() {
		if ( ( $_POST[ 'action' ] ?? '' ) !== self::ACTION || ! is_user_logged_in() ) {
			return;
		}

		// Always the current user's own account, and only from a form shown to them (no cross-site requests).
		check_admin_referer( self::ACTION );

		$user = wp_get_current_user();

		if ( ! self::is_customer( $user ) || self::get_blocked_reason( $user ) || empty( $_POST[ self::CONFIRM ] ) ) {
			wp_safe_redirect( edd_get_current_page_url() );
			exit;
		}

		$customer      = self::get_customer( $user );
		$emails        = array_unique( array_filter( [ $user->user_email, $customer->email ?? '' ] ) );
		$subscriptions = $this->cancel_subscriptions( $user );

		// Don't delete the account of someone who may still be charged.
		if ( $subscriptions[ 'failed' ] ) {
			$this->notify_failed( $user, $emails, $subscriptions[ 'failed' ] );

			wp_safe_redirect( add_query_arg( self::FAILED, 1, edd_get_current_page_url() ) . '#' . Block::ID );
			exit;
		}

		/**
		 * Fires before a customer's account is deleted.
		 *
		 * @param \WP_User           $user
		 * @param \EDD_Customer|null $customer
		 */
		do_action( 'edd_delete_account_before', $user, $customer );

		$licenses = $this->detach_licenses( $user );
		$messages = [];
		$failed   = [];

		foreach ( $emails as $email ) {
			$erased   = $this->erase_personal_data( $email );
			$messages = array_merge( $messages, $erased[ 'messages' ] );
			$failed   = array_merge( $failed, $erased[ 'failed' ] );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		wp_logout();
		wp_delete_user( $user->ID );

		// The account is deleted anyway: by now, its subscriptions are cancelled and its customer may be anonymized.
		$this->notify( $user, $customer, $emails, $subscriptions[ 'cancelled' ], $licenses, $messages, $failed );

		/**
		 * Fires after a customer deleted their account.
		 *
		 * @param \WP_User $user   The deleted user.
		 * @param string[] $emails Their email addresses.
		 */
		do_action( 'edd_delete_account_deleted', $user, $emails );

		wp_safe_redirect( add_query_arg( self::DELETED, 1, get_permalink() ?: home_url() ) );
		exit;
	}

	/**
	 * Cancels active subscriptions at their gateway, like EDD Recurring does when a customer is anonymized in wp-admin.
	 *
	 * @param \WP_User $user
	 *
	 * @return array{cancelled: int, failed: int[]} The number of cancelled subscriptions, and the IDs of those that
	 *                                             couldn't be cancelled.
	 */
	private function cancel_subscriptions( $user ) {
		$result = [ 'cancelled' => 0, 'failed' => [] ];

		foreach ( self::get_active_subscriptions( $user ) as $subscription ) {
			if ( $subscription->can_cancel() ) {
				$subscription->cancel();
			}

			// The gateway may have refused, e.g. while it's unreachable.
			$subscription = new \EDD_Subscription( $subscription->id );

			if ( $subscription->status !== 'cancelled' ) {
				$result[ 'failed' ][] = (int) $subscription->id;

				continue;
			}

			$subscription->add_note( __( 'Subscription cancelled, because the customer deleted their account.', 'edd-delete-account' ) );

			$result[ 'cancelled' ] ++;
		}

		return $result;
	}

	/**
	 * Runs all personal data erasers (Tools > Erase Personal Data) for an email address.
	 *
	 * @param string $email
	 *
	 * @return array{messages: string[], failed: string[]} Their messages (e.g. what was kept and why), and the erasers
	 *                                                    which failed or didn't finish, so personal data may remain.
	 */
	private function erase_personal_data( $email ) {
		$messages = [];
		$failed   = [];

		foreach ( apply_filters( 'wp_privacy_personal_data_erasers', [] ) as $eraser ) {
			if ( ! is_callable( $eraser[ 'callback' ] ?? null ) ) {
				continue;
			}

			$name = $eraser[ 'eraser_friendly_name' ] ?? '';
			$done = false;

			// Erasers work in pages, until they're done.
			for ( $page = 1; $page <= 100; $page ++ ) {
				$response = call_user_func( $eraser[ 'callback' ], $email, $page );

				if ( ! is_array( $response ) || ! is_array( $response[ 'messages' ] ?? [] ) ) {
					$failed[] = $name . ': ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'invalid response' );

					continue 2;
				}

				foreach ( $response[ 'messages' ] ?? [] as $message ) {
					$messages[] = $name . ': ' . $message;
				}

				if ( ! empty( $response[ 'done' ] ) ) {
					$done = true;

					break;
				}
			}

			if ( ! $done ) {
				$failed[] = $name . ': not finished after 100 pages';
			}
		}

		return [ 'messages' => $messages, 'failed' => $failed ];
	}

	/**
	 * Licenses stay valid, but no longer belong to the deleted user: otherwise they can't be renewed by license key.
	 *
	 * @param \WP_User $user
	 *
	 * @return string[] The detached license keys.
	 */
	private function detach_licenses( $user ) {
		$keys = [];

		foreach ( self::get_licenses( $user ) as $license ) {
			$license->update( [ 'user_id' => 0 ] );

			$keys[] = $license->key;
		}

		return $keys;
	}

	/**
	 * Lets the site's admin know, e.g. what couldn't be erased.
	 *
	 * @param \WP_User           $user
	 * @param \EDD_Customer|null $customer
	 * @param string[]           $emails
	 * @param int                $subscriptions
	 * @param string[]           $licenses
	 * @param string[]           $messages
	 * @param string[]           $failed   Erasers which failed: their personal data may remain.
	 *
	 * @return void
	 */
	private function notify( $user, $customer, $emails, $subscriptions, $licenses, $messages, $failed ) {
		$lines = [
			sprintf( 'A customer deleted their account on %s.', home_url() ),
			'',
			'Email: ' . implode( ', ', $emails ),
			'Name: ' . $user->display_name,
			'User ID: ' . $user->ID,
			'Customer ID: ' . ( $customer->id ?? '-' ),
			'Subscriptions cancelled: ' . $subscriptions,
			'License keys detached: ' . ( $licenses ? implode( ', ', $licenses ) : '-' ),
		];

		if ( $failed ) {
			$lines[] = '';
			$lines[] = 'These erasers FAILED, so personal data may remain (Tools > Erase Personal Data):';

			foreach ( $failed as $failure ) {
				$lines[] = '- ' . $failure;
			}
		}

		if ( $messages ) {
			$lines[] = '';
			$lines[] = 'Erasers:';

			foreach ( $messages as $message ) {
				$lines[] = '- ' . $message;
			}
		}

		/**
		 * Filters the email to the site's admin after a customer deleted their account, e.g. to add what's kept elsewhere.
		 *
		 * @param string[] $lines
		 * @param \WP_User $user  The deleted user.
		 */
		$lines = (array) apply_filters( 'edd_delete_account_notification_lines', $lines, $user );

		$this->mail_admin( $failed ? 'Account deleted, but personal data may remain' : 'Account deleted', $lines );
	}

	/**
	 * Lets the site's admin know an account wasn't deleted, because a subscription couldn't be cancelled.
	 *
	 * @param \WP_User $user
	 * @param string[] $emails
	 * @param int[]    $subscription_ids
	 *
	 * @return void
	 */
	private function notify_failed( $user, $emails, $subscription_ids ) {
		$lines = [
			sprintf( 'A customer tried to delete their account on %s, but it wasn\'t deleted: these subscriptions couldn\'t be cancelled.', home_url() ),
			'',
			'Email: ' . implode( ', ', $emails ),
			'User ID: ' . $user->ID,
			'Subscription IDs: ' . implode( ', ', $subscription_ids ),
		];

		$this->mail_admin( 'Account not deleted', $lines );
	}

	/**
	 * The account is gone by the time the admin is notified, so if the email can't be sent, the report goes to PHP's
	 * error log.
	 *
	 * @param string   $subject
	 * @param string[] $lines
	 *
	 * @return void
	 */
	private function mail_admin( $subject, $lines ) {
		$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $subject );
		$message = implode( "\n", $lines );

		/**
		 * Filters who's notified when a customer deleted their account (or couldn't).
		 *
		 * @param string|string[] $to Default: the site's admin email address.
		 */
		$to = apply_filters( 'edd_delete_account_notification_recipient', get_option( 'admin_email' ) );

		if ( ! wp_mail( $to, $subject, $message ) ) {
			error_log( 'EDD Delete Account: couldn\'t email this report. ' . $subject . "\n" . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * @param \WP_User $user
	 *
	 * @return \EDD_Customer|null
	 */
	public static function get_customer( $user ) {
		return edd_get_customer_by( 'user_id', $user->ID ) ?: null;
	}

	/**
	 * @param \WP_User $user
	 *
	 * @return \EDD_Subscription[]
	 */
	public static function get_active_subscriptions( $user ) {
		$customer = self::get_customer( $user );

		if ( ! $customer || ! class_exists( '\EDD_Recurring_Subscriber' ) ) {
			return [];
		}

		$subscriptions = ( new \EDD_Recurring_Subscriber( $customer->id ) )->get_subscriptions( 0, [ 'active', 'trialling' ] );

		return is_array( $subscriptions ) ? $subscriptions : [];
	}

	/**
	 * @param \WP_User $user
	 *
	 * @return \EDD_SL_License[]
	 */
	public static function get_licenses( $user ) {
		if ( ! function_exists( 'edd_software_licensing' ) ) {
			return [];
		}

		return (array) edd_software_licensing()->licenses_db->get_licenses( [ 'user_id' => $user->ID, 'number' => - 1 ] );
	}
}
