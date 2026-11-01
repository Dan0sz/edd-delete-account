<?php
/**
 * @package Daan\EDD\DeleteAccount
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\EDD\DeleteAccount;

/**
 * The Delete Account block: what deleting the account does, and the form to do it. Its heading, introduction and texts
 * are edited in the block editor.
 */
class Block {
	/**
	 * The block's ID, e.g. to return to it after a failed attempt.
	 */
	const ID = 'edd-delete-account';

	/**
	 * Replaced with the site's name in the block's texts.
	 */
	const SITE_NAME = '{site_name}';

	/**
	 * Build class.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * @return void
	 */
	public function register() {
		register_block_type( EDD_DELETE_ACCOUNT_PLUGIN_DIR . 'blocks/delete-account', [ 'render_callback' => [ $this, 'render' ] ] );
	}

	/**
	 * The block: its heading and introduction (inner blocks), what deleting the account does, and the form to do it.
	 * Only shown to logged-in customers.
	 *
	 * @param array  $attributes The texts edited in the block editor.
	 * @param string $content    The inner blocks.
	 *
	 * @return string
	 */
	public function render( $attributes, $content ) {
		$user = wp_get_current_user();

		// After deleting their account, the visitor is logged out and returns to this page.
		if ( ! $user->exists() ) {
			return isset( $_GET[ Account::DELETED ] ) ? $this->wrap( $this->replace_site_name( '<div class="edd-alert edd-alert-success"><p>' . wp_kses_post( $attributes[ 'deletedText' ] ?? '' ) . '</p></div>' ) ) : '';
		}

		// Administrators can't delete their account here, but see a preview of the block.
		if ( ! Account::is_customer( $user ) ) {
			return current_user_can( 'manage_options' ) ? $this->render_preview( $attributes, $content, $user ) : '';
		}

		$blocked = Account::get_blocked_reason( $user );

		if ( isset( $_GET[ Account::FAILED ] ) ) {
			$blocked = __( 'Something went wrong while cancelling your subscription, so your account wasn\'t deleted. The site owner has been notified.', 'edd-delete-account' );
		}

		ob_start();

		if ( $blocked ) :
			?>
			<p><?php echo wp_kses( $this->get_contact_text( $blocked, $attributes[ 'contactUrl' ] ?? '' ), [ 'a' => [ 'href' => [] ] ] ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $this->get_consequences( $user, $attributes ) as $consequence ) : ?>
					<li><?php echo wp_kses_post( $consequence ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
			/**
			 * Fires before the form, e.g. to add a message.
			 *
			 * @param \WP_User $user
			 * @param array    $attributes The block's attributes.
			 */
			do_action( 'edd_delete_account_before_form', $user, $attributes );
			?>
			<?php echo $this->get_form( $user, $attributes ); ?>
			<?php
			/**
			 * Fires after the form.
			 *
			 * @param \WP_User $user
			 * @param array    $attributes The block's attributes.
			 */
			do_action( 'edd_delete_account_after_form', $user, $attributes );
		endif;

		return $this->wrap( $this->replace_site_name( $content . ob_get_clean() ) );
	}

	/**
	 * What administrators see: a notice, and the block as customers see it, but not interactive.
	 *
	 * @param array    $attributes
	 * @param string   $content
	 * @param \WP_User $user
	 *
	 * @return string
	 */
	private function render_preview( $attributes, $content, $user ) {
		ob_start();
		?>
		<div class="edd-alert edd-alert-info"><p><?php esc_html_e( 'Administrators can\'t delete their account using this block. This is a preview.', 'edd-delete-account' ); ?></p></div>
		<?php echo $content; ?>
		<ul>
			<?php foreach ( $this->get_consequences( $user, $attributes, true ) as $consequence ) : ?>
				<li><?php echo wp_kses_post( $consequence ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
		echo $this->get_form( $user, $attributes, true );

		return $this->wrap( $this->replace_site_name( ob_get_clean() ) );
	}

	/**
	 * The form to delete the account. In a preview, it's disabled.
	 *
	 * @param \WP_User $user
	 * @param array    $attributes
	 * @param bool     $preview
	 *
	 * @return string
	 */
	private function get_form( $user, $attributes, $preview = false ) {
		ob_start();
		?>
			<?php if ( $preview ) : ?>
			<form inert>
			<?php else : ?>
			<form method="post" action="<?php echo esc_url( edd_get_current_page_url() ); ?>">
				<?php wp_nonce_field( Account::ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Account::ACTION ); ?>">
			<?php endif; ?>
				<?php
				/**
				 * Fires inside the form, before the confirmation checkbox, e.g. to add a field asking why they're leaving.
				 * What's submitted is passed to the edd_delete_account_before action.
				 *
				 * @param \WP_User $user
				 * @param array    $attributes The block's attributes.
				 */
				do_action( 'edd_delete_account_form_fields', $user, $attributes );
				?>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Account::CONFIRM ); ?>" value="1" <?php echo $preview ? 'disabled' : 'required'; ?>>
						<?php echo wp_kses_post( $attributes[ 'confirmLabel' ] ?? '' ); ?>
					</label>
				</p>
				<p>
					<button type="submit" class="button edd-delete-account-button"<?php echo $preview ? ' disabled' : ''; ?>><?php echo wp_kses_post( $attributes[ 'buttonText' ] ?? '' ); ?></button>
				</p>
			</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * @param string $html
	 *
	 * @return string
	 */
	private function replace_site_name( $html ) {
		return str_replace( self::SITE_NAME, esc_html( get_bloginfo( 'name' ) ), $html );
	}

	/**
	 * Why the account can't be deleted, and to contact the site owner instead.
	 *
	 * @param string $reason
	 * @param string $contact_url
	 *
	 * @return string HTML.
	 */
	private function get_contact_text( $reason, $contact_url ) {
		if ( ! $contact_url ) {
			/* translators: %s: why the account can't be deleted. */
			return sprintf( __( '%s To delete your account, please contact us.', 'edd-delete-account' ), esc_html( $reason ) );
		}

		return sprintf(
			/* translators: 1: why the account can't be deleted, 2: contact page URL. */
			__( '%1$s To delete your account, please <a href="%2$s">contact us</a>.', 'edd-delete-account' ),
			esc_html( $reason ),
			esc_url( $contact_url )
		);
	}

	/**
	 * What deleting this account does: the block's texts which apply to this customer.
	 *
	 * @param \WP_User $user
	 * @param array    $attributes
	 * @param bool     $all        All of them, e.g. in a preview.
	 *
	 * @return string[]
	 */
	private function get_consequences( $user, $attributes, $all = false ) {
		$texts = [ 'accountText', 'ordersText' ];

		if ( $all || Account::get_active_subscriptions( $user ) ) {
			$texts[] = 'subscriptionsText';
		}

		if ( $all || Account::get_licenses( $user ) ) {
			$texts[] = 'licensesText';
		}

		$texts[] = 'newsletterText';

		return array_filter( array_map( fn( $text ) => $attributes[ $text ] ?? '', $texts ) );
	}

	/**
	 * The block's wrapper, with its border, colors and spacing.
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function wrap( $html ) {
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( [ 'id' => self::ID ] ), $html );
	}
}
