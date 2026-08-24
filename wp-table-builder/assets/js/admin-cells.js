/**
 * Cell input widgets for the builder grid, one per data type.
 *
 * Pure factories: value in, widget out, changes reported through a
 * callback. They never touch editor state directly - admin-builder.js
 * owns the row/column objects and wires the callbacks - so these can
 * be re-rendered freely without coupling to how state is stored.
 *
 * Depends on the WTB_ADMIN namespace from admin-base.js.
 */
( function ( WTB_ADMIN ) {
	'use strict';

	var tag      = WTB_ADMIN.tag;
	var button   = WTB_ADMIN.button;
	var select   = WTB_ADMIN.select;
	var pick     = WTB_ADMIN.pickAttachment;

	/* ---- text / number / date / url ---------------------------------- */

	function text( dataType, value, onChange ) {
		var input = tag( 'input', {
			type:  'number' === dataType ? 'number' : 'text',
			step:  'any',
			class: 'wtb-cell',
			'aria-label': 'Cell value'
		} );

		if ( 'url' === dataType ) {
			input.placeholder = 'https://';
		}

		input.value = value;
		input.addEventListener( 'change', function () {
			onChange( input.value );
		} );

		return input;
	}

	/* ---- image --------------------------------------------------------- */

	function image( value, onChange ) {
		var box    = tag( 'div', { class: 'wtb-media-cell is-empty' } );
		var img    = tag( 'img', { alt: '' } );
		var hidden = tag( 'input', {
			type:         'hidden',
			'aria-label': 'Attachment ID'
		} );

		function paint( url ) {
			if ( url ) {
				img.src = url;
				box.classList.remove( 'is-empty' );
			} else {
				img.removeAttribute( 'src' );
				box.classList.add( 'is-empty' );
			}
		}

		function store( id, previewUrl ) {
			hidden.value = id;
			onChange( id );
			paint( previewUrl );
		}

		box.appendChild( img );
		box.appendChild( hidden );

		box.appendChild( button( 'Choose', 'button', function () {
			pick( function ( attachment ) {
				var sizes = attachment.get( 'sizes' ) || {};
				store(
					String( attachment.get( 'id' ) ),
					( sizes.medium && sizes.medium.url )
						|| attachment.get( 'url' )
						|| ''
				);
			} );
		} ) );

		box.appendChild( button( '\u00d7', 'button-link', function () {
			store( '', '' );
		} ) );

		if ( String( value ) ) {
			WTB_ADMIN.attachmentUrl( String( value ) ).then( paint );
		}
		hidden.value = String( value || '' );

		return box;
	}

	/* ---- post ------------------------------------------------------------ */

	function post( value, onChange ) {
		var box   = tag( 'div', {} );
		var title = tag( 'span', { class: 'wtb-post-title' } );
		var input = tag( 'input', {
			type: 'number',
			min:  '0',
			step: '1',
			class: 'wtb-cell',
			'aria-label': 'Post ID'
		} );

		function refresh() {
			// Strip noise so "12abc" and " 12 " both become a clean id.
			var id = input.value.replace( /[^0-9]/g, '' );

			input.value = id;
			onChange( id );
			title.textContent = '';

			if ( ! id ) {
				return;
			}

			WTB_ADMIN.postTitle( id ).then( function ( found ) {
				// A slow lookup must not label a since-changed id.
				if ( id === input.value ) {
					title.textContent = found;
				}
			} );
		}

		input.value = String( value || '' );
		input.addEventListener( 'change', refresh );

		if ( input.value ) {
			WTB_ADMIN.postTitle( input.value ).then( function ( found ) {
				title.textContent = found;
			} );
		}

		box.appendChild( input );
		box.appendChild( title );

		return box;
	}

	/* ---- row status ---------------------------------------------------------- */

	function status( value, onChange ) {
		return select(
			[
				{ value: 'published', label: 'Published' },
				{ value: 'pending', label: 'Pending' }
			],
			value,
			function () { onChange( this.value ); }
		);
	}

	window.WTB_CELLS = {
		text:   text,
		image:  image,
		post:   post,
		status: status
	};
}( window.WTB_ADMIN ) );
