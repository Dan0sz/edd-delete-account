/**
 * The Delete Account block's button is only enabled once its checkbox is checked. Without JavaScript, the checkbox's
 * required attribute does the same.
 *
 * @see \Daan\EDD\DeleteAccount\Block
 */
document.querySelectorAll( '.wp-block-edd-delete-account-delete-account form' ).forEach( ( form ) => {
	const checkbox = form.querySelector( 'input[name="edd_delete_account_confirm"]' );
	const button = form.querySelector( '.edd-delete-account-button' );
	const toggle = () => ( button.disabled = ! checkbox.checked );

	checkbox.addEventListener( 'change', toggle );
	toggle();
} );
