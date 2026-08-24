/**
 * Frontend behavior for rendered tables and submission forms.
 *
 * Reads its contract from the markup, not from globals: table
 * wrappers carry data-wtb-config (JSON from WTB_Table_Renderer) and
 * forms carry data-table-id plus data-rest-base. Everything here is
 * defensive - a table with a broken config must never take the whole
 * page's JavaScript down with it.
 */
( function () {
	'use strict';

	/* ---- helpers -------------------------------------------------- */

	function parseConfig( wrap ) {
		try {
			return JSON.parse(
				wrap.getAttribute( 'data-wtb-config' ) || '{}'
			);
		} catch ( error ) {
			return {};
		}
	}

	function escapeRegex( text ) {
		return text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	/* ---- tables ---------------------------------------------------- */

	function initTable( wrap ) {
		var table = wrap.querySelector( 'table.wtb-table' );

		if (
			! table
			|| ! window.jQuery
			|| ! window.jQuery.fn.DataTable
		) {
			return;
		}

		var config = parseConfig( wrap );
		var tableId = wrap.getAttribute( 'data-table-id' );

		if ( undefined === config.restBase || ! tableId ) {
			return;
		}

		var options = {
			searching: !! config.search,
			ordering: !! config.sorting,
			paging: !! config.pagination,
			pageLength: config.pageLength > 0 ? config.pageLength : 10,
			serverSide: !! config.serverSide,
			processing: !! config.serverSide,

			// Sort/filter against plain text even for cells whose HTML
			// is richer than text (links, images), so "12.5" sorts as
			// a number and select filters can match exactly.
			columnDefs: [
				{
					targets: '_all',
					render: function ( data, type ) {
						if ( type !== 'sort' && type !== 'filter' ) {
							return data;
						}
						var holder = document.createElement( 'div' );
						holder.innerHTML = data;
						return holder.textContent.trim();
					}
				}
			]
		};

		if ( config.serverSide ) {
			options.ajax = {
				url: config.restBase + '/data/' + tableId,
				type: 'GET'
			};
		}

		buildSelectFilters(
			window.jQuery( table ).DataTable( options ),
			config
		);
	}

	/**
	 * One dropdown per column flagged data-filter="select". In
	 * server-side mode only the loaded page's values are known, so
	 * those lists fill in progressively - acceptable because the
	 * threshold that turns server-side mode on implies big tables.
	 */
	function buildSelectFilters( api, config ) {
		api.columns( '[data-filter="select"]' ).every( function () {
			var column = this;
			var select = document.createElement( 'select' );
			var seen = {};

			select.add( new Option( 'All', '' ) );

			column.nodes().each( function ( node ) {
				var label = node.textContent.trim();
				if ( label && ! seen[ label ] ) {
					seen[ label ] = true;
					select.add( new Option( label, label ) );
				}
			} );

			select.addEventListener( 'click', function ( event ) {
				// A click on the control must not start a sort.
				event.stopPropagation();
			} );

			select.addEventListener( 'change', function () {
				var term = this.value
					? '^' + escapeRegex( this.value ) + '$'
					: '';
				column.search( term, true, false ).draw();
			} );

			column.header().appendChild( select );
		} );

		if ( config.pollSeconds > 0 ) {
			startPolling( api, config );
		}
	}

	/**
	 * Optional live row count; dormant until a config carries
	 * pollSeconds (no renderer emits it yet).
	 */
	function startPolling( api, config ) {
		var tableId = api.table().node()
			.closest( '.wtb-table-wrap' )
			.getAttribute( 'data-table-id' );

		window.setInterval( function () {
			window.fetch(
				config.restBase + '/row-count/' + tableId
			)
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.then( function ( payload ) {
					if ( payload && payload.count !== undefined ) {
						jQuery( api.table().node() ).trigger(
							'wtb:rowcount',
							payload.count
						);
					}
				} )
				.catch( function () {} );
		}, config.pollSeconds * 1000 );
	}

	/* ---- submission forms ------------------------------------------- */

	function initForm( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitForm( form );
		} );
	}

	async function submitForm( form ) {
		var restBase = form.getAttribute( 'data-rest-base' );
		var tableId = form.getAttribute( 'data-table-id' );
		var nonce = form.querySelector( '[name="wtb_form_nonce"]' );
		var button = form.querySelector( '.wtb-form-submit' );
		var status = ensureStatus( form );

		if ( ! restBase || ! tableId || ! nonce ) {
			showStatus( status, 'is-error', 'Missing form setup.' );
			return;
		}

		var cells = {};
		new FormData( form ).forEach( function ( value, key ) {
			var match = key.match( /^cells\[(.+)\]$/ );
			if ( match ) {
				cells[ match[ 1 ] ] = value;
			}
		} );

		button.disabled = true;

		try {
			var response = await window.fetch(
				restBase + '/submit/' + tableId,
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						cells: cells,
						wtb_form_nonce: nonce.value
					} )
				}
			);
			var payload = await response.json();

			if ( ! response.ok || ! payload.saved ) {
				throw new Error(
					payload.message || 'The submission failed.'
				);
			}

			if ( payload.status === 'pending' ) {
				showStatus(
					status,
					'is-pending',
					'Thank you — your submission is awaiting review.'
				);
			} else {
				showStatus(
					status,
					'is-ok',
					'Thank you — your submission has been received.'
				);
				form.reset();
			}
		} catch ( error ) {
			showStatus( status, 'is-error', error.message );
		} finally {
			button.disabled = false;
		}
	}

	function ensureStatus( form ) {
		var status = form.querySelector( '.wtb-form-status' );

		if ( ! status ) {
			status = document.createElement( 'div' );
			status.className = 'wtb-form-status';
			status.setAttribute( 'aria-live', 'polite' );
			form.appendChild( status );
		}

		return status;
	}

	function showStatus( status, variant, message ) {
		status.textContent = message;
		status.className = 'wtb-form-status is-visible ' + variant;
	}

	/* ---- boot -------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wtb-table-wrap' ).forEach(
			initTable
		);
		document.querySelectorAll( 'form.wtb-form' ).forEach( initForm );
	} );
}() );
