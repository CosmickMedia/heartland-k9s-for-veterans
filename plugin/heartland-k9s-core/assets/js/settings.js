/**
 * Heartland → Settings screen behaviour (no jQuery; wp.media for image pickers).
 *
 * - Image fields: wp.media picker with preview / replace / remove.
 * - Link fields: page-or-URL mode switch.
 * - Color fields: native <input type="color"> mirrored into the hex text input.
 * - Repeaters: add / remove / move rows from a <template>.
 * - Unsaved-changes guard.
 */
( function () {
	'use strict';

	var config = ( window.HK9 && window.HK9.settings ) || { i18n: {} };
	var i18n = config.i18n || {};

	function speak( text ) {
		if ( window.wp && wp.a11y && typeof wp.a11y.speak === 'function' ) {
			wp.a11y.speak( text );
		}
	}

	/* ---------------------------------------------------------------- images */
	function initImageField( field ) {
		var input = field.querySelector( '[data-hk9-image-id]' );
		var preview = field.querySelector( '[data-hk9-image-preview]' );
		var selectBtn = field.querySelector( '[data-hk9-image-select]' );
		var removeBtn = field.querySelector( '[data-hk9-image-remove]' );
		var frame = null;

		function setImage( id, url, alt ) {
			input.value = id ? String( id ) : '0';
			preview.innerHTML = '';
			if ( id && url ) {
				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = alt || '';
				preview.appendChild( img );
				field.classList.add( 'has-image' );
			} else {
				field.classList.remove( 'has-image' );
			}
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		selectBtn.addEventListener( 'click', function () {
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.selectImage || 'Select image',
					button: { text: i18n.useImage || 'Use this image' },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					var sizes = att.sizes || {};
					var url = ( sizes.medium && sizes.medium.url ) || ( sizes.full && sizes.full.url ) || att.url;
					setImage( att.id, url, att.alt );
					selectBtn.focus();
				} );
			}
			frame.open();
		} );

		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				setImage( 0, '', '' );
				selectBtn.focus();
				speak( i18n.remove || 'Removed' );
			} );
		}
	}

	/* ----------------------------------------------------------------- links */
	function initLinkField( field ) {
		var radios = field.querySelectorAll( '[data-hk9-link-mode]' );
		function apply() {
			var mode = 'url';
			radios.forEach( function ( r ) {
				if ( r.checked ) {
					mode = r.value;
				}
			} );
			field.setAttribute( 'data-mode', mode );
		}
		radios.forEach( function ( r ) {
			r.addEventListener( 'change', apply );
		} );
		apply();
	}

	/* ---------------------------------------------------------------- colors */
	function initColorField( swatch ) {
		var hex = document.getElementById( swatch.getAttribute( 'data-hk9-color-for' ) );
		if ( ! hex ) {
			return;
		}
		function normalise( value ) {
			value = ( value || '' ).trim();
			if ( value && value.charAt( 0 ) !== '#' ) {
				value = '#' + value;
			}
			if ( /^#[0-9a-fA-F]{3}$/.test( value ) ) {
				value = '#' + value.charAt( 1 ) + value.charAt( 1 ) + value.charAt( 2 ) + value.charAt( 2 ) + value.charAt( 3 ) + value.charAt( 3 );
			}
			return value.toLowerCase();
		}
		swatch.addEventListener( 'input', function () {
			hex.value = swatch.value;
			hex.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		hex.addEventListener( 'input', function () {
			var v = normalise( hex.value );
			if ( /^#[0-9a-f]{6}$/.test( v ) ) {
				swatch.value = v;
			}
		} );
		hex.addEventListener( 'blur', function () {
			var v = normalise( hex.value );
			if ( /^#[0-9a-f]{6}$/.test( v ) ) {
				hex.value = v;
			}
		} );
	}

	/* ------------------------------------------------------------- repeaters */
	function initRepeater( wrap ) {
		var rows = wrap.querySelector( '[data-hk9-repeater-rows]' );
		var tpl = wrap.querySelector( '[data-hk9-repeater-template]' );
		var empty = wrap.querySelector( '[data-hk9-repeater-empty]' );
		var addBtn = wrap.querySelector( '[data-hk9-repeater-add]' );
		var name = wrap.getAttribute( 'data-name' );

		function reindex() {
			var items = rows.querySelectorAll( '.hk9-repeater__row' );
			items.forEach( function ( row, i ) {
				row.querySelectorAll( '[name]' ).forEach( function ( el ) {
					el.name = el.name.replace( new RegExp( '^' + name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '\\[[^\\]]*\\]' ), name + '[' + i + ']' );
					if ( el.id ) {
						el.id = el.id.replace( /-(\d+|__INDEX__)-([a-z_]+)$/, '-' + i + '-$2' );
						var label = row.querySelector( 'label[for="' + el.id.replace( /-\d+-([a-z_]+)$/, '-__X__-$1' ) + '"]' );
						if ( label ) {
							label.setAttribute( 'for', el.id );
						}
					}
				} );
				row.querySelectorAll( 'label[for]' ).forEach( function ( label ) {
					label.setAttribute( 'for', label.getAttribute( 'for' ).replace( /-(\d+|__INDEX__)-([a-z_]+)$/, '-' + i + '-$2' ) );
				} );
			} );
			if ( empty ) {
				empty.hidden = items.length > 0;
			}
		}

		function addRow() {
			var fragment = tpl.content.cloneNode( true );
			var row = fragment.querySelector( '.hk9-repeater__row' );
			rows.appendChild( row );
			reindex();
			var first = row.querySelector( 'input' );
			if ( first ) {
				first.focus();
			}
			markDirty();
		}

		addBtn.addEventListener( 'click', addRow );

		rows.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn ) {
				return;
			}
			var row = btn.closest( '.hk9-repeater__row' );
			if ( btn.hasAttribute( 'data-hk9-repeater-remove' ) ) {
				var next = row.nextElementSibling || row.previousElementSibling;
				row.remove();
				reindex();
				markDirty();
				( next ? next.querySelector( 'input' ) : addBtn ).focus();
				speak( i18n.remove || 'Removed' );
			} else if ( btn.hasAttribute( 'data-hk9-repeater-up' ) && row.previousElementSibling ) {
				rows.insertBefore( row, row.previousElementSibling );
				reindex();
				markDirty();
				btn.focus();
			} else if ( btn.hasAttribute( 'data-hk9-repeater-down' ) && row.nextElementSibling ) {
				rows.insertBefore( row.nextElementSibling, row );
				reindex();
				markDirty();
				btn.focus();
			}
		} );

		reindex();
	}

	/* ------------------------------------------------------- unsaved guard */
	var dirty = false;
	var form = document.querySelector( '.hk9-settings-form' );

	function markDirty() {
		dirty = true;
	}

	if ( form ) {
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = i18n.unsaved || '';
			}
		} );
	}

	document.querySelectorAll( '[data-hk9-image]' ).forEach( initImageField );
	document.querySelectorAll( '[data-hk9-link]' ).forEach( initLinkField );
	document.querySelectorAll( '[data-hk9-color-for]' ).forEach( initColorField );
	document.querySelectorAll( '[data-hk9-repeater]' ).forEach( initRepeater );
}() );
