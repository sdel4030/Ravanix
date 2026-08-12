( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var Placeholder = components.Placeholder;
	var ServerSideRender = serverSideRender;

	var tests = ( window.RavanixBlockData && window.RavanixBlockData.tests ) || [];

	blocks.registerBlockType( 'ravanix/questionnaire', {
		title: __( 'Ravanix – Questionnaire', 'ravanix-lite' ),
		description: __( 'Display a specific questionnaire, or a list of all published questionnaires as a list or a grid.', 'ravanix-lite' ),
		icon: 'forms',
		category: 'widgets',
		attributes: {
			mode: { type: 'string', default: 'list' },
			testId: { type: 'number', default: 0 },
			layout: { type: 'string', default: 'grid' },
			columns: { type: 'number', default: 3 },
			showImage: { type: 'boolean', default: true },
			showExcerpt: { type: 'boolean', default: true },
			hideHeader: { type: 'boolean', default: false }
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var testOptions = [ { label: __( '— Select a questionnaire —', 'ravanix-lite' ), value: 0 } ].concat(
				tests.map( function ( t ) {
					return { label: t.title, value: t.id };
				} )
			);

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Display settings', 'ravanix-lite' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Display mode', 'ravanix-lite' ),
						value: attributes.mode,
						options: [
							{ label: __( 'List of all questionnaires', 'ravanix-lite' ), value: 'list' },
							{ label: __( 'A specific questionnaire', 'ravanix-lite' ), value: 'single' }
						],
						onChange: function ( value ) {
							setAttributes( { mode: value } );
						}
					} ),

					attributes.mode === 'single' &&
						el( SelectControl, {
							label: __( 'Questionnaire', 'ravanix-lite' ),
							value: attributes.testId,
							options: testOptions,
							onChange: function ( value ) {
								setAttributes( { testId: parseInt( value, 10 ) } );
							}
						} ),

					attributes.mode === 'single' &&
						el( ToggleControl, {
							label: __( 'Hide the title and description above the form', 'ravanix-lite' ),
							checked: attributes.hideHeader,
							onChange: function ( value ) {
								setAttributes( { hideHeader: value } );
							}
						} ),

					attributes.mode === 'list' &&
						el( SelectControl, {
							label: __( 'Layout', 'ravanix-lite' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Grid', 'ravanix-lite' ), value: 'grid' },
								{ label: __( 'List', 'ravanix-lite' ), value: 'list' }
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							}
						} ),

					attributes.mode === 'list' &&
						attributes.layout === 'grid' &&
						el( RangeControl, {
							label: __( 'Number of columns', 'ravanix-lite' ),
							value: attributes.columns,
							min: 2,
							max: 4,
							onChange: function ( value ) {
								setAttributes( { columns: value } );
							}
						} ),

					attributes.mode === 'list' &&
						el( ToggleControl, {
							label: __( 'Show featured image', 'ravanix-lite' ),
							checked: attributes.showImage,
							onChange: function ( value ) {
								setAttributes( { showImage: value } );
							}
						} ),

					attributes.mode === 'list' &&
						el( ToggleControl, {
							label: __( 'Show description excerpt', 'ravanix-lite' ),
							checked: attributes.showExcerpt,
							onChange: function ( value ) {
								setAttributes( { showExcerpt: value } );
							}
						} )
				)
			);

			var preview;
			if ( attributes.mode === 'single' && ! attributes.testId ) {
				preview = el( Placeholder, {
					icon: 'forms',
					label: __( 'Ravanix – Questionnaire', 'ravanix-lite' )
				}, __( 'Select a questionnaire from the settings panel (on the right).', 'ravanix-lite' ) );
			} else if ( tests.length === 0 && attributes.mode === 'list' ) {
				preview = el( Placeholder, {
					icon: 'forms',
					label: __( 'Ravanix – Questionnaire', 'ravanix-lite' )
				}, __( 'No questionnaire has been published yet.', 'ravanix-lite' ) );
			} else {
				preview = el( ServerSideRender, {
					block: 'ravanix/questionnaire',
					attributes: attributes
				} );
			}

			return el( 'div', blockProps, inspector, preview );
		},

		save: function () {
			// The block is fully dynamic; its whole output is built server-side (render_callback in PHP)
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
