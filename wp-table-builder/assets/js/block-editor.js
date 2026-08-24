/**
 * Editor-side behavior for the "wtb/table" block.
 *
 * The block stores nothing but the picked table id; everything shown
 * in the canvas comes back through ServerSideRender, which calls the
 * same WTB_Block::render() the frontend uses. The inspector select is
 * filled from the admin REST route - the editor user already passed
 * manage_options to get here, and wp.apiFetch attaches the REST nonce.
 *
 * Every wp submodule this file touches is dependency-declared on the
 * script handle; the guard below only keeps a load-order hiccup from
 * throwing deep inside Gutenberg.
 */
( function ( wp ) {
	'use strict';

	if (
		! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor
		|| ! wp.components || ! wp.apiFetch || ! wp.serverSideRender
		|| ! wp.i18n
	) {
		return;
	}

	var el        = wp.element.createElement;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __        = wp.i18n.__;

	function fetchTables() {
		return wp.apiFetch( { path: '/wtb/v1/tables' } )
			.then( function ( list ) {
				return Array.isArray( list ) ? list : [];
			} )
			.catch( function () {
				return [];
			} );
	}

	function Edit( props ) {
		var tableId      = props.attributes.tableId;
		var setAttributes = props.setAttributes;

		var state   = useState( null );
		var tables  = state[ 0 ];
		var setTables = state[ 1 ];

		useEffect( function () {
			fetchTables().then( setTables );
		}, [] );

		function pickTable( value ) {
			setAttributes( { tableId: parseInt( value, 10 ) || 0 } );
		}

		var options = [ {
			value: '',
			label: __( '— Select a table —', 'wp-table-builder' )
		} ];

		( tables || [] ).forEach( function ( table ) {
			options.push( {
				value: String( table.id ),
				label: table.title || __( '(no title)', 'wp-table-builder' )
			} );
		} );

		var inspector = el(
			wp.blockEditor.InspectorControls,
			{},
			el(
				wp.components.PanelBody,
				{
					title:     __( 'Table settings', 'wp-table-builder' ),
					initialOpen: true
				},
				el( wp.components.SelectControl, {
					label:   __( 'Table', 'wp-table-builder' ),
					value:   tableId ? String( tableId ) : '',
					options: options,
					onChange: pickTable
				} ),
				tables && ! tables.length
					? el(
						'p',
						{},
						__( 'No tables found.', 'wp-table-builder' )
					)
					: null
			)
		);

		var body;

		if ( null === tables ) {
			body = el(
				'p',
				{},
				__( 'Loading…', 'wp-table-builder' )
			);
		} else if ( ! tableId ) {
			body = el(
				'p',
				{ className: 'components-placeholder__label' },
				__( 'Pick a table in the sidebar.', 'wp-table-builder' )
			);
		} else {
			body = el( wp.serverSideRender, {
				block:       'wtb/table',
				attributes:  { tableId: tableId }
			} );
		}

		return el(
			'div',
			wp.blockEditor.useBlockProps(),
			inspector,
			body
		);
	}

	wp.blocks.registerBlockType( 'wtb/table', {
		title:       __( 'Data Table', 'wp-table-builder' ),
		description: __(
			'Render a table built in WP Tables.',
			'wp-table-builder'
		),
		icon:     'grid-view',
		category: 'widgets',
		keywords: [ 'table', 'data', 'rows' ],
		supports: { html: false },

		attributes: {
			tableId: {
				type:    'integer',
				default: 0
			}
		},

		edit: Edit,

		// Dynamic block: PHP's render_callback owns all output.
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
