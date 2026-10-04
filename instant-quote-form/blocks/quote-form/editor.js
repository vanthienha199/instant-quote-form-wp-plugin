/* Editor side of the Instant Quote block. No build step: plain wp.* globals. */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'instant-quote-form/quote-form', {
		edit: function ( props ) {
			const a = props.attributes;
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form text', 'instant-quote-form' ) },
						el( TextControl, { label: __( 'Heading', 'instant-quote-form' ), value: a.heading, onChange: ( v ) => props.setAttributes( { heading: v } ) } ),
						el( TextareaControl, { label: __( 'Intro', 'instant-quote-form' ), value: a.intro, onChange: ( v ) => props.setAttributes( { intro: v } ) } ),
						el( 'p', { className: 'components-base-control__help' }, __( 'Rates and emails live under Settings, Instant Quote.', 'instant-quote-form' ) )
					)
				),
				el( 'div', useBlockProps(), el( ServerSideRender, { block: 'instant-quote-form/quote-form', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
