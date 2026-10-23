/**
 * Delete Account block, in the editor: its heading and introduction are inner blocks, the rest of its texts are edited
 * in place. What's shown to whom, the form and deleting the account are done in PHP.
 *
 * @see \Daan\EDD\DeleteAccount\Block
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InnerBlocks, InspectorControls, RichText, useBlockProps, useInnerBlocksProps } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	const TEMPLATE = [
		[ 'core/heading', { level: 3, content: __( 'Delete account', 'edd-delete-account' ) } ],
		[ 'core/paragraph', { content: __( 'Deleting your account removes your personal data from {site_name}. This can\'t be undone.', 'edd-delete-account' ) } ],
	];

	// The list's items, in order. Items with a note are only shown to customers they apply to.
	const ITEMS = [
		{ attribute: 'accountText' },
		{ attribute: 'ordersText' },
		{ attribute: 'subscriptionsText', note: __( 'Only shown to customers with an active subscription.', 'edd-delete-account' ) },
		{ attribute: 'licensesText', note: __( 'Only shown to customers with license keys.', 'edd-delete-account' ) },
		{ attribute: 'newsletterText' },
	];

	registerBlockType( 'edd-delete-account/delete-account', {
		edit: function ( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const innerBlocksProps = useInnerBlocksProps( {}, { template: TEMPLATE } );
			const text = ( attribute, props ) =>
				el( RichText, Object.assign( {
					value: attributes[ attribute ],
					onChange: ( value ) => setAttributes( { [ attribute ]: value } ),
					allowedFormats: [],
				}, props ) );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'About this block', 'edd-delete-account' ) },
						el(
							'p',
							null,
							__( 'Only logged-in customers see this block. Staff, and visitors who aren\'t logged in, don\'t. After deleting their account, customers see a confirmation here instead.', 'edd-delete-account' )
						),
						el(
							'p',
							null,
							__( 'Items marked with an asterisk (*) are only shown to customers they apply to.', 'edd-delete-account' )
						),
						el(
							'p',
							null,
							__( '{site_name} is replaced with the name of your site, in all of this block\'s texts.', 'edd-delete-account' )
						)
					),
					el(
						PanelBody,
						{ title: __( 'Settings', 'edd-delete-account' ) },
						el( TextControl, {
							label: __( 'Contact page URL', 'edd-delete-account' ),
							help: __( 'Customers who can\'t delete their account here (e.g. affiliates) are asked to contact you, with a link to this page.', 'edd-delete-account' ),
							type: 'url',
							value: attributes.contactUrl,
							onChange: ( value ) => setAttributes( { contactUrl: value } ),
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'Message after deleting', 'edd-delete-account' ),
							value: attributes.deletedText,
							onChange: ( value ) => setAttributes( { deletedText: value } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( 'div', innerBlocksProps ),
				el(
					'ul',
					null,
					ITEMS.map( ( item ) =>
						el(
							'li',
							{ key: item.attribute, title: item.note },
							text( item.attribute, { tagName: 'span' } ),
							item.note ? ' *' : null
						)
					)
				),
				el(
					'p',
					null,
					el(
						'label',
						null,
						el( 'input', { type: 'checkbox', disabled: true } ),
						' ',
						text( 'confirmLabel', { tagName: 'span' } )
					)
				),
				el(
					'p',
					null,
					text( 'buttonText', { tagName: 'span', className: 'button edd-delete-account-button' } )
				)
			);
		},

		// The heading and introduction. The rest is rendered by PHP.
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );
} )( window.wp );
