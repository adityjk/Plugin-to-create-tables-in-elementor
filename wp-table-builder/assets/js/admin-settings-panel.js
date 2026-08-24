/**
 * Settings panel for the table editor.
 *
 * The panel is described by a declarative spec (group > field > kind)
 * bound by path onto the settings object, rather than hand-built DOM:
 * the settings blob shape is owned by WTB_Sanitizer::table_settings()
 * on the PHP side, and this file mirrors it in one place. Unknown or
 * missing paths simply render empty controls; the sanitizer drops
 * anything it does not recognize on save.
 *
 * Depends on the WTB_ADMIN namespace from admin-base.js.
 */
( function ( WTB_ADMIN ) {
	'use strict';

	var tag      = WTB_ADMIN.tag;
	var select   = WTB_ADMIN.select;
	var checkbox = WTB_ADMIN.checkbox;
	var number   = WTB_ADMIN.numberInput;

	function field( path, label, kind, choices ) {
		return { path: path, label: label, kind: kind, choices: choices };
	}

	var GROUPS = [
		{
			legend: 'Colors',
			fields: [
				field( [ 'colors', 'header_background' ],
					'Header background', 'color' ),
				field( [ 'colors', 'header_text' ],
					'Header text', 'color' ),
				field( [ 'colors', 'body_text' ],
					'Body text', 'color' ),
				field( [ 'colors', 'border' ],
					'Border color', 'color' ),
				field( [ 'colors', 'row_even' ],
					'Even row color', 'color' ),
				field( [ 'colors', 'row_odd' ],
					'Odd row color', 'color' )
			]
		},
		{
			legend: 'Layout',
			fields: [
				field( [ 'layout', 'cell_padding' ],
					'Cell padding (px)', 'int' ),
				field( [ 'layout', 'border_width' ],
					'Border width (px)', 'int' )
			]
		},
		{
			legend: 'Features',
			fields: [
				field( [ 'features', 'search' ], 'Search box', 'bool' ),
				field( [ 'features', 'sorting' ], 'Sorting', 'bool' ),
				field( [ 'features', 'pagination' ],
					'Pagination', 'bool' ),
				field( [ 'features', 'page_length' ],
					'Rows per page', 'int' ),
				field( [ 'features', 'server_side_threshold' ],
					'Server-side mode above this many published rows',
					'int' )
			]
		},
		{
			legend: 'Data source',
			fields: [
				field( [ 'data_source' ], 'Where rows come from',
					'enum', [
						{ value: 'manual', label: 'Manual rows' },
						{
							value: 'submissions',
							label: 'Visitor submissions'
						}
					] )
			]
		},
		{
			legend: 'Submission form',
			fields: [
				field( [ 'form', 'enabled' ], 'Enable form', 'bool' ),
				field( [ 'form', 'require_approval' ],
					'Submissions await approval', 'bool' ),
				field( [ 'form', 'rate_limit' ],
					'Max submissions per IP per hour', 'int' )
			]
		}
	];

	function readPath( object, path ) {
		return path.reduce( function ( level, key ) {
			return level && undefined !== level[ key ]
				? level[ key ]
				: '';
		}, object );
	}

	function writePath( object, path, value ) {
		var level = object;
		var i;

		for ( i = 0; i < path.length - 1; i += 1 ) {
			if ( ! level[ path[ i ] ] ) {
				level[ path[ i ] ] = {};
			}
			level = level[ path[ i ] ];
		}

		level[ path[ path.length - 1 ] ] = value;
	}

	function controlFor( settings, spec ) {
		var bind = function ( value ) {
			writePath( settings, spec.path, value );
		};

		if ( 'bool' === spec.kind ) {
			return checkbox( readPath( settings, spec.path ),
				function () { bind( this.checked ); } );
		}

		if ( 'int' === spec.kind ) {
			return number( readPath( settings, spec.path ),
				function () {
					bind( parseInt( this.value, 10 ) || 0 );
				} );
		}

		if ( 'enum' === spec.kind ) {
			return select( spec.choices, readPath( settings, spec.path ),
				function () { bind( this.value ); } );
		}

		return colorControl( settings, spec );
	}

	function colorControl( settings, spec ) {
		var input = document.createElement( 'input' );

		input.type  = 'color';
		input.value = readPath( settings, spec.path );

		// An unset color stays '' in state: the change event only
		// fires on real user picks, never for the programmatic
		// .value assignment above.
		input.addEventListener( 'change', function () {
			writePath( settings, spec.path, this.value );
		} );

		return input;
	}

	/**
	 * Rebuilds the whole panel from scratch; called whenever editor
	 * state is replaced (load, save response).
	 */
	function render( host, settings ) {
		host.textContent = '';

		GROUPS.forEach( function ( group ) {
			var fieldset = tag(
				'fieldset',
				{ class: 'wtb-group' },
				[ tag( 'legend', {}, [ group.legend ] ) ]
			);

			group.fields.forEach( function ( spec ) {
				fieldset.appendChild(
					tag( 'p', { class: 'wtb-setting' }, [
						tag( 'label', {}, [ spec.label ] ),
						controlFor( settings, spec )
					] )
				);
			} );

			host.appendChild( fieldset );
		} );
	}

	window.WTB_SETTINGS_PANEL = {
		render: render
	};
}( window.WTB_ADMIN ) );
