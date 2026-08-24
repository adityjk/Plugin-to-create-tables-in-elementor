/**
 * Foundation for the admin scripts (list view + table builder).
 *
 * Owns the pieces every view needs: the .wtb-admin wrapper contract
 * (data-rest / data-nonce), REST calls with the X-WP-Nonce header,
 * safe DOM construction, and the media picker. Everything is exposed
 * on the WTB_ADMIN namespace; admin-builder.js boots the stack.
 *
 * Dynamic content must enter the DOM through tag() (element APIs),
 * never concatenated HTML, so table content cannot inject markup
 * into the admin page.
 */
/* global wp */
( function () {
	'use strict';

	var wrapper;
	var api = {};

	/* ---- REST plumbing ---------------------------------------------- */

	function request( method, path, payload ) {
		return window.jQuery.ajax( {
			url:         api.rest + path,
			method:      method,
			data:        undefined === payload ? null : JSON.stringify( payload ),
			headers:     { 'X-WP-Nonce': api.nonce },
			contentType: 'application/json'
		} );
	}

	/**
	 * Read-only /wp/v2 lookups need no nonce. Handles both pretty
	 * (.../wp-json/wtb/v1) and plain (?rest_route=/wtb/v1) roots -
	 * stripping the suffix off the wrong shape would produce garbage
	 * URLs that fail silently in the grid previews.
	 */
	function coreUrl( path ) {
		if ( -1 !== api.rest.indexOf( 'rest_route=' ) ) {
			return api.rest.replace( /rest_route=.*$/, 'rest_route=' + path );
		}
		return api.rest.replace( /\/wtb\/v1\/?$/, '' ) + path;
	}

	function errorText( xhr ) {
		if ( xhr && xhr.responseJSON && xhr.responseJSON.message ) {
			return String( xhr.responseJSON.message );
		}
		return 'Request failed (' + ( xhr && xhr.status || '?' ) + ').';
	}

	function failAlert( xhr ) {
		window.alert( errorText( xhr ) );
	}

	function editUrl( tableId ) {
		var params = new URLSearchParams( window.location.search );
		params.set( 'table', String( tableId ) );
		return window.location.pathname + '?' + params.toString();
	}

	/* ---- DOM construction --------------------------------------------- */

	/**
	 * createElement plus attributes plus string/element children;
	 * the single funnel every dynamic node passes through.
	 */
	function tag( name, attributes, children ) {
		var node = document.createElement( name );
		var key;

		for ( key in attributes ) {
			if ( Object.prototype.hasOwnProperty.call( attributes, key ) ) {
				node.setAttribute( key, attributes[ key ] );
			}
		}

		( children || [] ).forEach( function ( child ) {
			if ( null === child || undefined === child ) {
				return;
			}
			node.appendChild(
				'string' === typeof child
					? document.createTextNode( child )
					: child
			);
		} );

		return node;
	}

	function button( label, className, onClick ) {
		var el = tag( 'button', { type: 'button', class: className }, [
			label
		] );
		el.addEventListener( 'click', onClick );
		return el;
	}

	function select( options, value, onChange ) {
		var el = document.createElement( 'select' );

		options.forEach( function ( option ) {
			var opt = document.createElement( 'option' );
			opt.value       = option.value;
			opt.textContent = option.label;
			opt.selected    =
				String( option.value ) === String( value || '' );
			el.appendChild( opt );
		} );

		el.addEventListener( 'change', onChange );
		return el;
	}

	function checkbox( checked, onChange ) {
		var el = document.createElement( 'input' );
		el.type    = 'checkbox';
		el.checked = !! checked;
		el.addEventListener( 'change', onChange );
		return el;
	}

	function numberInput( value, onChange ) {
		var el = tag( 'input', { type: 'number', min: '0', step: '1' } );
		el.value = value;
		el.addEventListener( 'change', onChange );
		return el;
	}

	/**
	 * Swap-with-neighbor for reorder buttons; out-of-range moves are
	 * ignored rather than wrapping around.
	 */
	function moveItem( list, from, delta ) {
		var to    = from + delta;
		var moved;

		if (
			from < 0 || from >= list.length
			|| to < 0 || to >= list.length
		) {
			return;
		}

		moved = list.splice( from, 1 )[ 0 ];
		list.splice( to, 0, moved );
	}

	/* ---- Core REST lookups ---------------------------------------------- */

	var lookupCache = {};

	function cachedLookup( key, fetcher ) {
		if ( ! lookupCache[ key ] ) {
			lookupCache[ key ] = fetcher().catch( function () {
				return '';
			} );
		}
		return lookupCache[ key ];
	}

	function attachmentUrl( attachmentId ) {
		return cachedLookup( 'media-' + attachmentId, function () {
			return window.jQuery
				.getJSON( coreUrl( '/wp/v2/media/' + attachmentId ) )
				.then( function ( media ) {
					var details = media.media_details || {};
					var sizes   = details.sizes || {};
					return ( sizes.medium && sizes.medium.source_url )
						|| media.source_url
						|| '';
				} );
		} );
	}

	function postTitle( postId ) {
		return cachedLookup( 'post-' + postId, function () {
			return window.jQuery
				.getJSON( coreUrl( '/wp/v2/posts/' + postId ) )
				.then( function ( post ) {
					var rendered = post.title && post.title.rendered;
					return rendered
						? window.jQuery( '<div>' )
							.html( rendered ).text()
						: '(untitled)';
				} )
				.catch( function () {
					return '(not found)';
				} );
		} );
	}

	/* ---- Media picker ------------------------------------------------------ */

	var mediaFrame  = null;
	var pendingPick = null;

	function pickAttachment( callback ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		if ( ! mediaFrame ) {
			mediaFrame = window.wp.media( {
				title:    'Choose image',
				multiple: false,
				library:  { type: 'image' }
			} );
			mediaFrame.on( 'select', function () {
				var chosen  = mediaFrame.state()
					.get( 'selection' ).first();
				var handler = pendingPick;

				pendingPick = null;

				if ( chosen && handler ) {
					handler( chosen );
				}
			} );
		}

		// Held outside the frame: the frame is built once, but each
		// open targets a different cell, so the callback cannot be
		// captured at construction time.
		pendingPick = callback;
		mediaFrame.open();
	}

	/* ---- Boot + namespace ------------------------------------------------ */

	function init() {
		wrapper   = window.jQuery( '.wtb-admin' ).first();
		api.rest  = wrapper.attr( 'data-rest' ) || '';
		api.nonce = wrapper.attr( 'data-nonce' ) || '';

		return Boolean( api.rest && api.nonce );
	}

	window.WTB_ADMIN = {
		init:           init,
		request:        request,
		coreUrl:        coreUrl,
		errorText:      errorText,
		failAlert:      failAlert,
		editUrl:        editUrl,
		tag:            tag,
		button:         button,
		select:         select,
		checkbox:       checkbox,
		numberInput:    numberInput,
		moveItem:       moveItem,
		attachmentUrl:  attachmentUrl,
		postTitle:      postTitle,
		pickAttachment: pickAttachment
	};
}() );
