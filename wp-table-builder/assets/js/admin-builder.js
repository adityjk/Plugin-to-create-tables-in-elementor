/**
 * The table builder views: all-tables list actions and the single-
 * table editor grid, plus editor load/save and boot.
 *
 * Server-side contract (WTB_Admin_Menu / WTB_Admin_Table_Editor):
 * - .wtb-admin wrapper carries data-rest + data-nonce (read by
 *   admin-base.js).
 * - Controls declare intent via data-wtb-action with optional
 *   data-id / data-confirm; plain links may carry data-confirm alone
 *   (the debug-log clear link) and get the same confirm guard.
 * - On views with #wtb-editor, its data-config JSON carries the
 *   table id and the sanitizer whitelists to build editors from;
 *   #wtb-grid and #wtb-settings are this file's mount points.
 *
 * State lives in `state` below; cell widgets and the settings panel
 * stay decoupled from it via callbacks (admin-cells.js /
 * admin-settings-panel.js).
 */
( function () {
	'use strict';

	var A = window.WTB_ADMIN;

	/**
	 * Editor working state once loaded: { config, columns, rows,
	 * settings }. Null on the all-tables view.
	 */
	var state = null;

	/**
	 * New columns get throwaway ids ("tmp1", ...) that storage swaps
	 * for real auto-increment ids during save_structure; the save
	 * response then becomes the new working state, so every id the
	 * UI holds after a save is canonical.
	 */
	var tempKeys = 0;

	/* ---- Action dispatch (both views) -------------------------------- */

	function onClick( event ) {
		var target = window.jQuery( event.currentTarget );
		var question = target.attr( 'data-confirm' );

		if ( ! target.is( '[data-wtb-action]' ) ) {
			// Plain link with a confirm guard (debug-log clearing):
			// navigation proceeds unless the operator declines.
			if ( question && ! window.confirm( question ) ) {
				event.preventDefault();
			}
			return;
		}

		event.preventDefault();

		if ( question && ! window.confirm( question ) ) {
			return;
		}

		runAction( target );
	}

	function runAction( button ) {
		var action = button.attr( 'data-wtb-action' );

		if ( 'save' === action && state ) {
			save();
			return;
		}

		if ( ! state ) {
			runListAction( action, button );
		}
	}

	function runListAction( action, button ) {
		var id = parseInt( button.attr( 'data-id' ), 10 ) || 0;

		if ( 'create' === action ) {
			A.request( 'POST', '/tables', {} )
				.done( function ( created ) {
					window.location = A.editUrl( created.id );
				} )
				.fail( A.failAlert );
			return;
		}

		if ( ! id ) {
			return;
		}

		if ( 'duplicate' === action ) {
			A.request( 'POST', '/tables/' + id + '/duplicate', {} )
				.done( function () { window.location.reload(); } )
				.fail( A.failAlert );
		}

		if ( 'delete' === action ) {
			A.request( 'DELETE', '/tables/' + id )
				.done( function () { window.location.reload(); } )
				.fail( A.failAlert );
		}
	}

	/* ---- Grid: columns -------------------------------------------------- */

	function typeOptions() {
		return state.config.dataTypes.map( function ( type ) {
			return { value: type, label: type };
		} );
	}

	function appendTypeControls( head, column ) {
		var settings = column.settings;
		var config   = state.config;

		function choices( values, prefix ) {
			return values.map( function ( value ) {
				return { value: value, label: prefix + ': ' + value };
			} );
		}

		if ( 'image' === column.data_type ) {
			head.appendChild( A.select(
				choices( config.imageSizes, 'size' ),
				settings.image_size,
				function () { settings.image_size = this.value; }
			) );
		}

		if ( 'post' === column.data_type ) {
			head.appendChild( A.select(
				choices( config.postFields, 'field' ),
				settings.post_field,
				function () { settings.post_field = this.value; }
			) );
		}

		head.appendChild( A.select(
			choices( config.filterTypes, 'filter' ),
			settings.filter_type,
			function () { settings.filter_type = this.value; }
		) );

		head.appendChild( A.tag( 'label', {}, [
			A.checkbox( settings.is_unique, function () {
				settings.is_unique = this.checked;
			} ),
			' unique'
		] ) );
	}

	function columnHead( column ) {
		var index = state.columns.indexOf( column );
		var head  = A.tag( 'th', { class: 'wtb-col-head' } );

		var labelInput = A.tag( 'input', {
			type:         'text',
			placeholder:  'Label',
			'aria-label': 'Column label'
		} );

		labelInput.value = column.label;
		labelInput.addEventListener( 'change', function () {
			column.label = labelInput.value;
		} );
		head.appendChild( labelInput );

		head.appendChild( A.select(
			typeOptions(),
			column.data_type,
			function () {
				column.data_type = this.value;
				renderGrid();
			}
		) );

		appendTypeControls( head, column );

		head.appendChild( A.tag( 'div', { class: 'wtb-col-tools' }, [
			A.button( '\u2190', 'button-link', function () {
				A.moveItem( state.columns, index, -1 );
				renderGrid();
			} ),
			A.button( '\u2192', 'button-link', function () {
				A.moveItem( state.columns, index, 1 );
				renderGrid();
			} ),
			A.button( '\u00d7', 'button-link', function () {
				state.columns.splice( index, 1 );
				renderGrid();
			} )
		] ) );

		return head;
	}

	function addColumn() {
		tempKeys += 1;

		state.columns.push( {
			id:         'tmp' + tempKeys,
			label:      '',
			data_type:  'text',
			settings:   {
				post_field:  '',
				image_size:  'thumbnail',
				filter_type: 'none',
				is_unique:   false
			},
			sort_order: 0
		} );

		renderGrid();
	}

	/* ---- Grid: rows -------------------------------------------------------- */

	function storeCell( row, columnId ) {
		return function ( value ) {
			row.cells_data[ String( columnId ) ] = value;
		};
	}

	function cellValue( row, column ) {
		var key = String( column.id );

		return Object.prototype.hasOwnProperty.call( row.cells_data, key )
			? row.cells_data[ key ]
			: '';
	}

	function bodyCell( row, column ) {
		var td    = document.createElement( 'td' );
		var type  = column.data_type;
		var value = cellValue( row, column );

		if ( 'image' === type ) {
			td.appendChild( window.WTB_CELLS.image(
				value, storeCell( row, column.id ) ) );
		} else if ( 'post' === type ) {
			td.appendChild( window.WTB_CELLS.post(
				value, storeCell( row, column.id ) ) );
		} else {
			td.appendChild( window.WTB_CELLS.text(
				type, value, storeCell( row, column.id ) ) );
		}

		return td;
	}

	function statusCell( row ) {
		var td = A.tag( 'td', { class: 'wtb-status-cell' } );
		td.appendChild( window.WTB_CELLS.status(
			row.status,
			function ( value ) { row.status = value; }
		) );
		return td;
	}

	function rowToolsCell( row ) {
		var index = state.rows.indexOf( row );
		var td    = A.tag( 'td', { class: 'wtb-row-tools' } );

		td.appendChild( A.button( '\u2191', 'button-link', function () {
			A.moveItem( state.rows, index, -1 );
			renderGrid();
		} ) );
		td.appendChild( A.button( '\u2193', 'button-link', function () {
			A.moveItem( state.rows, index, 1 );
			renderGrid();
		} ) );
		td.appendChild( A.button( '\u00d7', 'button-link', function () {
			state.rows.splice( index, 1 );
			renderGrid();
		} ) );

		return td;
	}

	function bodyRow( row ) {
		var tr = document.createElement( 'tr' );

		tr.appendChild( statusCell( row ) );
		state.columns.forEach( function ( column ) {
			tr.appendChild( bodyCell( row, column ) );
		} );
		tr.appendChild( rowToolsCell( row ) );

		return tr;
	}

	function addRow() {
		state.rows.push( {
			id:         0,
			cells_data: {},
			status:     'pending'
		} );
		renderGrid();
	}

	/* ---- Grid assembly ------------------------------------------------------- */

	function renderGrid() {
		var grid  = document.getElementById( 'wtb-grid' );
		var table = A.tag( 'table', { class: 'wtb-builder' } );
		var thead = document.createElement( 'thead' );
		var tbody = document.createElement( 'tbody' );
		var tfoot = document.createElement( 'tfoot' );
		var headRow = document.createElement( 'tr' );

		grid.textContent = '';

		headRow.appendChild( A.tag( 'th', {}, [ 'Status' ] ) );
		state.columns.forEach( function ( column ) {
			headRow.appendChild( columnHead( column ) );
		} );
		headRow.appendChild( A.tag( 'th', {} ) );
		thead.appendChild( headRow );

		state.rows.forEach( function ( row ) {
			tbody.appendChild( bodyRow( row ) );
		} );

		tfoot.appendChild( A.tag( 'tr', {}, [ A.tag(
			'td',
			{ colspan: String( state.columns.length + 2 ) },
			[
				A.button( '+ Add column', 'button', addColumn ),
				' ',
				A.button( '+ Add row', 'button', addRow )
			]
		) ] ) );

		table.appendChild( thead );
		table.appendChild( tbody );
		table.appendChild( tfoot );
		grid.appendChild( table );
	}

	/* ---- Load + save ------------------------------------------------------------ */

	function renderEditor() {
		window.WTB_SETTINGS_PANEL.render(
			document.getElementById( 'wtb-settings' ),
			state.settings
		);
		renderGrid();
	}

	function initEditor() {
		var host = document.getElementById( 'wtb-editor' );

		if ( ! host ) {
			return;
		}

		try {
			var config =
				JSON.parse( host.getAttribute( 'data-config' ) );
		} catch ( error ) {
			config = null;
		}

		if ( ! config || ! config.tableId ) {
			return;
		}

		config.dataTypes   = config.dataTypes || [];
		config.imageSizes  = config.imageSizes || [];
		config.postFields  = config.postFields || [];
		config.filterTypes = config.filterTypes || [];

		state = {
			config:   config,
			columns:  [],
			rows:     [],
			settings: {}
		};

		A.request( 'GET', '/tables/' + config.tableId )
			.done( function ( table ) {
				state.columns  = table.columns || [];
				state.rows     = table.rows || [];
				state.settings = table.settings || {};
				renderEditor();
			} )
			.fail( function ( xhr ) {
				// Keep the shell intact; report inside the grid area.
				document.getElementById( 'wtb-grid' ).textContent =
					A.errorText( xhr );
			} );
	}

	function save() {
		var statusEl = document.getElementById( 'wtb-save-status' );

		if ( ! statusEl ) {
			return;
		}

		statusEl.className   = '';
		statusEl.textContent = 'Saving\u2026';

		/*
		 * Columns and rows must travel together: storage treats the
		 * payload as the complete desired state and deletes anything
		 * absent from it, so a partial save would wipe content (the
		 * same rule WTB_Rest_Tables::save_table enforces server-side).
		 */
		A.request( 'POST', '/tables/' + state.config.tableId, {
			settings: state.settings,
			columns:  state.columns,
			rows:     state.rows
		} )
			.done( function ( saved ) {
				state.columns  = saved.columns || [];
				state.rows     = saved.rows || [];
				state.settings = saved.settings || {};
				renderEditor();

				statusEl.className   = 'is-ok';
				statusEl.textContent = 'Saved.';
			} )
			.fail( function ( xhr ) {
				statusEl.className   = 'is-error';
				statusEl.textContent = A.errorText( xhr );
			} );
	}

	/* ---- Boot ----------------------------------------------------------------- */

	window.jQuery( function () {
		if ( ! A.init() ) {
			return;
		}

		window.jQuery( '.wtb-admin' ).on(
			'click',
			'[data-wtb-action], [data-confirm]',
			onClick
		);

		initEditor();
	} );
}() );
