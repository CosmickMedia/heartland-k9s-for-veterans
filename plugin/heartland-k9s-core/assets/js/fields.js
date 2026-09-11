/**
 * Heartland K9s Core — field framework (admin).
 *
 * Plain JS, no build step. Enhances server-rendered controls: media/gallery/
 * file pickers (wp.media), repeaters (add/remove/move/drag/collapse), link
 * and relationship pickers (hk9/v1/pick), icon previews, teeny TinyMCE
 * editors, datetime pairs and colour inputs. Also serializes a `.hk9-fields`
 * container into the canonical PHP value shape so sections.js can mirror it
 * into the block editor store.
 *
 * Public API: window.HK9.fields = { init(root), serialize(fieldsEl), notify(el) }.
 */
( function () {
	'use strict';

	var cfg = window.HK9Fields || {};
	var i18n = cfg.i18n || {};
	var HK9 = ( window.HK9 = window.HK9 || {} );

	/* ------------------------------------------------------------------ */
	/* Utilities                                                            */
	/* ------------------------------------------------------------------ */

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}
	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function debounce( fn, ms ) {
		var t;
		return function () {
			var args = arguments, ctx = this;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, ms );
		};
	}
	/** Direct child `.hk9-fields` of a container, or the container itself when it is one. */
	function fieldsOf( container ) {
		if ( ! container ) {
			return null;
		}
		if ( container.classList.contains( 'hk9-fields' ) ) {
			return container;
		}
		return container.querySelector( ':scope > .hk9-fields' );
	}
	/** Announce a programmatic change: bubbles so sections.js can mirror it. */
	function notify( el ) {
		if ( ! el ) {
			return;
		}
		el.dispatchEvent( new CustomEvent( 'hk9:change', { bubbles: true } ) );
	}
	function liveAnnounce( text ) {
		var region = document.getElementById( 'hk9-live' );
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.id = 'hk9-live';
			region.className = 'screen-reader-text';
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		region.textContent = '';
		setTimeout( function () { region.textContent = text; }, 30 );
	}
	function iconSvg( name ) {
		if ( ! cfg.spriteUrl || ! name ) {
			return '';
		}
		return '<svg class="hk9-icon" width="20" height="20" aria-hidden="true" focusable="false"><use href="' + esc( cfg.spriteUrl ) + '#' + esc( ( cfg.symbolPrefix || '' ) + name ) + '"></use></svg>';
	}

	/* ------------------------------------------------------------------ */
	/* Media pickers                                                        */
	/* ------------------------------------------------------------------ */

	function openMedia( opts, onSelect ) {
		if ( ! window.wp || ! wp.media ) {
			window.alert( 'The media library is not available on this screen.' );
			return;
		}
		var args = {
			title: opts.title || i18n.selectImage || 'Select',
			button: { text: opts.button || i18n.use || 'Use this' },
			multiple: opts.multiple || false
		};
		if ( opts.type ) {
			args.library = { type: opts.type };
		}
		var frame = wp.media( args );
		frame.on( 'select', function () {
			var selection = frame.state().get( 'selection' ).toJSON();
			onSelect( selection );
		} );
		frame.open();
	}
	function attachmentThumb( att, size ) {
		var sizes = att.sizes || {};
		var pick = sizes[ size ] || sizes.medium || sizes.thumbnail || sizes.full;
		var url = pick ? pick.url : att.url;
		if ( att.type && att.type !== 'image' ) {
			url = att.icon || url;
		}
		return url || '';
	}

	function initImageField( wrap ) {
		var input = $( 'input[type="hidden"]', wrap );
		var preview = $( '[data-hk9-media-preview]', wrap );
		var kind = wrap.getAttribute( 'data-hk9-media' ) || 'image';
		var size = wrap.getAttribute( 'data-hk9-size' ) || 'medium';
		var mimes = ( wrap.getAttribute( 'data-hk9-mimes' ) || '' ).split( ',' ).filter( Boolean );

		function set( att ) {
			input.value = att ? att.id : 0;
			wrap.classList.toggle( 'has-value', !! att );
			if ( ! att ) {
				preview.innerHTML = '';
			} else if ( kind === 'image' ) {
				preview.innerHTML = '<img class="hk9-media__img" src="' + esc( attachmentThumb( att, size ) ) + '" alt="' + esc( att.alt || att.title || '' ) + '" />';
			} else {
				preview.innerHTML = '<a href="' + esc( att.url ) + '" target="_blank" rel="noopener">' + esc( att.filename || att.title || ( '#' + att.id ) ) + '</a>';
			}
			notify( input );
		}
		$$( '[data-hk9-media-select]', wrap ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				openMedia( {
					title: kind === 'image' ? i18n.selectImage : i18n.selectFile,
					type: kind === 'image' ? 'image' : ( mimes.length ? mimes : undefined ),
					multiple: false
				}, function ( sel ) {
					if ( sel && sel[ 0 ] ) {
						set( sel[ 0 ] );
					}
				} );
			} );
		} );
		var remove = $( '[data-hk9-media-remove]', wrap );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				set( null );
			} );
		}
	}

	function galleryItemHtml( name, att ) {
		var title = att.title || '';
		return '<li class="hk9-gallery__item" draggable="true" data-hk9-gallery-item data-id="' + esc( att.id ) + '">' +
			'<input type="hidden" name="' + esc( name ) + '" value="' + esc( att.id ) + '" />' +
			'<span class="hk9-gallery__thumb"><img class="hk9-gallery__img" src="' + esc( attachmentThumb( att, 'thumbnail' ) ) + '" alt="' + esc( att.alt || title ) + '" /></span>' +
			'<span class="hk9-gallery__tools">' +
			'<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="' + esc( i18n.moveUp || 'Move up' ) + '">&#8593;</button>' +
			'<button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="' + esc( i18n.moveDown || 'Move down' ) + '">&#8595;</button>' +
			'<button type="button" class="hk9-iconbtn hk9-iconbtn--danger" data-hk9-gallery-remove aria-label="' + esc( i18n.remove || 'Remove' ) + '">&times;</button>' +
			'</span></li>';
	}

	function initGallery( wrap ) {
		var list = $( '[data-hk9-gallery-list]', wrap );
		var empty = $( '[data-hk9-gallery-empty]', wrap );
		var name = wrap.getAttribute( 'data-hk9-name' );
		var max = parseInt( wrap.getAttribute( 'data-hk9-max' ) || '0', 10 );

		function refresh() {
			var count = list.children.length;
			empty.hidden = count > 0;
			var add = $( '[data-hk9-gallery-add]', wrap );
			if ( add ) {
				add.disabled = max > 0 && count >= max;
			}
			notify( wrap );
		}
		$( '[data-hk9-gallery-add]', wrap ).addEventListener( 'click', function () {
			openMedia( { title: i18n.selectImages, type: 'image', multiple: 'add', button: i18n.add }, function ( sel ) {
				var have = $$( '[data-hk9-gallery-item]', list ).map( function ( li ) { return li.getAttribute( 'data-id' ); } );
				sel.forEach( function ( att ) {
					if ( have.indexOf( String( att.id ) ) !== -1 ) {
						return;
					}
					if ( max > 0 && list.children.length >= max ) {
						return;
					}
					list.insertAdjacentHTML( 'beforeend', galleryItemHtml( name, att ) );
					have.push( String( att.id ) );
				} );
				refresh();
			} );
		} );
		$( '[data-hk9-gallery-clear]', wrap ).addEventListener( 'click', function () {
			list.innerHTML = '';
			refresh();
		} );
		wrap.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '[data-hk9-gallery-remove]' );
			if ( rm ) {
				var item = rm.closest( '[data-hk9-gallery-item]' );
				var next = item.nextElementSibling || item.previousElementSibling;
				item.remove();
				refresh();
				( next ? $( 'button', next ) : $( '[data-hk9-gallery-add]', wrap ) ).focus();
				return;
			}
			var mv = e.target.closest( '[data-hk9-move]' );
			if ( mv && mv.closest( '[data-hk9-gallery-item]' ) ) {
				moveSibling( mv.closest( '[data-hk9-gallery-item]' ), mv.getAttribute( 'data-hk9-move' ) );
				mv.focus();
				refresh();
			}
		} );
		enableDrag( list, '[data-hk9-gallery-item]', refresh );
	}

	/* ------------------------------------------------------------------ */
	/* Ordering helpers (move up/down + drag)                                */
	/* ------------------------------------------------------------------ */

	function moveSibling( el, dir ) {
		if ( dir === 'up' && el.previousElementSibling ) {
			el.parentNode.insertBefore( el, el.previousElementSibling );
		} else if ( dir === 'down' && el.nextElementSibling ) {
			el.parentNode.insertBefore( el.nextElementSibling, el );
		}
	}

	function enableDrag( list, itemSel, onDrop, beforeMove, afterMove ) {
		var dragging = null;
		list.addEventListener( 'dragstart', function ( e ) {
			var item = e.target.closest ? e.target.closest( itemSel ) : null;
			if ( ! item || item.getAttribute( 'draggable' ) !== 'true' ) {
				return;
			}
			// Do not start a drag from inside text controls.
			if ( e.target.matches && e.target.matches( 'input, textarea, select, [contenteditable]' ) ) {
				e.preventDefault();
				return;
			}
			dragging = item;
			item.classList.add( 'is-dragging' );
			try {
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData( 'text/plain', '' );
			} catch ( err ) { /* IE */ }
			if ( beforeMove ) {
				beforeMove( item );
			}
		} );
		list.addEventListener( 'dragover', function ( e ) {
			if ( ! dragging ) {
				return;
			}
			var over = e.target.closest ? e.target.closest( itemSel ) : null;
			if ( ! over || over === dragging || over.parentNode !== list ) {
				return;
			}
			e.preventDefault();
			var rect = over.getBoundingClientRect();
			var vertical = rect.height >= rect.width;
			var after = vertical ? ( e.clientY - rect.top ) > rect.height / 2 : ( e.clientX - rect.left ) > rect.width / 2;
			list.insertBefore( dragging, after ? over.nextSibling : over );
		} );
		list.addEventListener( 'drop', function ( e ) {
			if ( dragging ) {
				e.preventDefault();
			}
		} );
		list.addEventListener( 'dragend', function () {
			if ( ! dragging ) {
				return;
			}
			var item = dragging;
			dragging = null;
			item.classList.remove( 'is-dragging' );
			if ( afterMove ) {
				afterMove( item );
			}
			if ( onDrop ) {
				onDrop();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Rich text (teeny TinyMCE via wp.editor.initialize)                    */
	/* ------------------------------------------------------------------ */

	function classicEditor() {
		if ( window.wp && wp.oldEditor && typeof wp.oldEditor.initialize === 'function' ) {
			return wp.oldEditor;
		}
		if ( window.wp && wp.editor && typeof wp.editor.initialize === 'function' ) {
			return wp.editor;
		}
		return null;
	}

	function initRichtext( textarea ) {
		var ed = classicEditor();
		if ( ! ed || ! textarea.id || textarea.getAttribute( 'data-hk9-editor-ready' ) ) {
			return;
		}
		textarea.setAttribute( 'data-hk9-editor-ready', '1' );
		var sync = function ( editor ) {
			editor.save();
			textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		};
		ed.initialize( textarea.id, {
			tinymce: {
				wpautop: false,
				toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
				block_formats: 'Paragraph=p;Heading 3=h3;Heading 4=h4',
				plugins: 'lists,link,paste,wordpress,wplink,wptextpattern',
				height: 180,
				menubar: false,
				setup: function ( editor ) {
					editor.on( 'change keyup SetContent undo redo', function () { sync( editor ); } );
				}
			},
			quicktags: { buttons: 'strong,em,link,ul,ol,li' },
			mediaButtons: false
		} );
		// Fallback wiring when `setup` is not honoured by this WP build.
		if ( window.tinymce ) {
			var inst = tinymce.get( textarea.id );
			if ( inst && ! inst.hk9Wired ) {
				inst.hk9Wired = true;
				inst.on( 'change keyup SetContent undo redo', function () { sync( inst ); } );
			}
		}
	}
	function removeRichtext( textarea ) {
		var ed = classicEditor();
		if ( ! ed || ! textarea.getAttribute( 'data-hk9-editor-ready' ) ) {
			return;
		}
		if ( window.tinymce && tinymce.get( textarea.id ) ) {
			tinymce.get( textarea.id ).save();
		}
		try {
			ed.remove( textarea.id );
		} catch ( e ) { /* ignore */ }
		textarea.removeAttribute( 'data-hk9-editor-ready' );
	}
	function detachEditors( root ) {
		$$( 'textarea[data-hk9-editor]', root ).forEach( removeRichtext );
	}
	function attachEditors( root ) {
		$$( 'textarea[data-hk9-editor]', root ).forEach( initRichtext );
	}

	/* ------------------------------------------------------------------ */
	/* Repeater                                                             */
	/* ------------------------------------------------------------------ */

	function rowTitle( row, itemLabel ) {
		if ( ! itemLabel ) {
			return '';
		}
		var body = $( ':scope > [data-hk9-repeater-body]', row );
		var fields = fieldsOf( body );
		var field = fields ? $( ':scope > .hk9-field[data-hk9-key="' + itemLabel + '"]', fields ) : null;
		if ( ! field ) {
			return '';
		}
		var type = field.getAttribute( 'data-hk9-type' );
		if ( type === 'link' ) {
			var l = $( '[data-hk9-link-label]', field );
			return l ? l.value : '';
		}
		var ctl = $( '[data-hk9-control]', field );
		if ( ! ctl ) {
			return '';
		}
		if ( ctl.tagName === 'SELECT' ) {
			return ctl.selectedIndex >= 0 ? ctl.options[ ctl.selectedIndex ].text : '';
		}
		if ( type === 'richtext' ) {
			var div = document.createElement( 'div' );
			div.innerHTML = ctl.value;
			return ( div.textContent || '' ).slice( 0, 60 );
		}
		return ctl.value || '';
	}

	function initRepeater( rep ) {
		var rows = $( '[data-hk9-repeater-rows]', rep );
		var empty = $( '[data-hk9-repeater-empty]', rep );
		var add = $( '[data-hk9-repeater-add]', rep );
		var tpl = $( 'template[data-hk9-repeater-template]', rep );
		var min = parseInt( rep.getAttribute( 'data-hk9-min' ) || '0', 10 );
		var max = parseInt( rep.getAttribute( 'data-hk9-max' ) || '0', 10 );
		var itemLabel = rep.getAttribute( 'data-hk9-item-label' ) || '';
		var next = parseInt( rep.getAttribute( 'data-hk9-next-index' ) || '0', 10 );

		function ownRows() {
			return $$( ':scope > [data-hk9-repeater-row]', rows );
		}
		function refresh() {
			var list = ownRows();
			empty.hidden = list.length > 0;
			add.disabled = max > 0 && list.length >= max;
			list.forEach( function ( row, i ) {
				var num = $( ':scope > .hk9-repeater__head [data-hk9-repeater-num]', row );
				if ( num ) {
					num.textContent = '#' + ( i + 1 );
				}
				var title = $( ':scope > .hk9-repeater__head [data-hk9-repeater-title]', row );
				if ( title ) {
					var t = rowTitle( row, itemLabel );
					title.textContent = t || ( ! itemLabel ? '' : ( i18n.itemLabel || 'Item' ) );
				}
				var rm = $( ':scope > .hk9-repeater__head [data-hk9-repeater-remove]', row );
				if ( rm ) {
					rm.disabled = min > 0 && list.length <= min;
				}
			} );
		}
		function addRow() {
			if ( max > 0 && ownRows().length >= max ) {
				return;
			}
			var html = tpl.innerHTML.split( '__INDEX__' ).join( String( next++ ) );
			rep.setAttribute( 'data-hk9-next-index', String( next ) );
			var frag = document.createElement( 'div' );
			frag.innerHTML = html.trim();
			var row = frag.firstElementChild;
			rows.appendChild( row );
			init( row );
			refresh();
			notify( rep );
			var first = $( 'input:not([type="hidden"]), select, textarea, button', $( ':scope > [data-hk9-repeater-body]', row ) );
			if ( first ) {
				first.focus();
			}
		}
		add.addEventListener( 'click', addRow );
		rep.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn ) {
				return;
			}
			var row = btn.closest( '[data-hk9-repeater-row]' );
			if ( ! row || row.parentNode !== rows ) {
				return; // belongs to a nested repeater
			}
			if ( btn.hasAttribute( 'data-hk9-repeater-remove' ) ) {
				var sib = row.nextElementSibling || row.previousElementSibling;
				detachEditors( row );
				row.remove();
				refresh();
				notify( rep );
				liveAnnounce( i18n.remove || 'Removed' );
				( sib ? $( ':scope > .hk9-repeater__head button', sib ) : add ).focus();
			} else if ( btn.hasAttribute( 'data-hk9-repeater-toggle' ) ) {
				var open = btn.getAttribute( 'aria-expanded' ) === 'true';
				btn.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				var body = $( ':scope > [data-hk9-repeater-body]', row );
				body.hidden = open;
				row.classList.toggle( 'is-collapsed', open );
			} else if ( btn.hasAttribute( 'data-hk9-move' ) && btn.closest( '.hk9-repeater__head' ) ) {
				detachEditors( row );
				moveSibling( row, btn.getAttribute( 'data-hk9-move' ) );
				attachEditors( row );
				refresh();
				notify( rep );
				btn.focus();
			}
		} );
		rep.addEventListener( 'input', function ( e ) {
			var row = e.target.closest( '[data-hk9-repeater-row]' );
			if ( row && row.parentNode === rows ) {
				var title = $( ':scope > .hk9-repeater__head [data-hk9-repeater-title]', row );
				if ( title ) {
					var t = rowTitle( row, itemLabel );
					title.textContent = t || ( ! itemLabel ? '' : ( i18n.itemLabel || 'Item' ) );
				}
			}
		} );
		enableDrag( rows, ':scope > [data-hk9-repeater-row]', function () { refresh(); notify( rep ); }, detachEditors, attachEditors );
		refresh();
	}

	/* ------------------------------------------------------------------ */
	/* Search picker (link + relationship)                                   */
	/* ------------------------------------------------------------------ */

	function normalizeResults( json ) {
		var list = Array.isArray( json ) ? json : ( json && ( json.items || json.results || json.data ) ) || [];
		if ( ! Array.isArray( list ) ) {
			return [];
		}
		return list.map( function ( it ) {
			return {
				id: parseInt( it.id != null ? it.id : it.ID, 10 ) || 0,
				title: it.title != null ? ( typeof it.title === 'object' ? ( it.title.rendered || '' ) : it.title ) : ( it.label || it.name || '' ),
				type: it.type_label || it.type || it.post_type || it.subtype || '',
				subtitle: it.subtitle || it.status || ''
			};
		} ).filter( function ( it ) { return it.id > 0; } );
	}

	function searchPosts( types, term ) {
		if ( ! cfg.pickUrl ) {
			return Promise.reject( new Error( 'no endpoint' ) );
		}
		var url = cfg.pickUrl + ( cfg.pickUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'type=' + encodeURIComponent( types ) + '&s=' + encodeURIComponent( term ) + '&per_page=20';
		return fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.restNonce || '' } } )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( normalizeResults );
	}

	/** Wires a search input + results listbox. onPick(item) is called on selection. */
	function initPicker( input, results, types, onPick ) {
		var items = [];
		var active = -1;
		function close() {
			results.hidden = true;
			results.innerHTML = '';
			input.setAttribute( 'aria-expanded', 'false' );
			active = -1;
		}
		function render() {
			results.innerHTML = items.length ? items.map( function ( it, i ) {
				return '<li class="hk9-pick__item" role="option" id="' + esc( results.id + '-' + i ) + '" data-index="' + i + '" aria-selected="' + ( i === active ) + '">' +
					'<span class="hk9-pick__title">' + esc( it.title ) + '</span>' +
					( it.type ? '<span class="hk9-chip__badge">' + esc( it.type ) + '</span>' : '' ) +
					( it.subtitle ? '<span class="hk9-pick__sub">' + esc( it.subtitle ) + '</span>' : '' ) +
					'</li>';
			} ).join( '' ) : '<li class="hk9-pick__note" role="option" aria-disabled="true">' + esc( i18n.noResults || 'No matches.' ) + '</li>';
			results.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			input.setAttribute( 'aria-activedescendant', active >= 0 ? results.id + '-' + active : '' );
		}
		var run = debounce( function () {
			var term = input.value.trim();
			if ( term.length < 2 ) {
				close();
				return;
			}
			results.innerHTML = '<li class="hk9-pick__note" role="option" aria-disabled="true">' + esc( i18n.searching || 'Searching…' ) + '</li>';
			results.hidden = false;
			searchPosts( types, term ).then( function ( list ) {
				if ( input.value.trim() !== term ) {
					return;
				}
				items = list;
				active = -1;
				render();
			} ).catch( function () {
				items = [];
				results.innerHTML = '<li class="hk9-pick__note" role="option" aria-disabled="true">' + esc( i18n.searchFailed || 'Search unavailable.' ) + '</li>';
				results.hidden = false;
			} );
		}, 250 );
		input.addEventListener( 'input', run );
		input.addEventListener( 'keydown', function ( e ) {
			if ( results.hidden ) {
				return;
			}
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				active = Math.min( active + 1, items.length - 1 );
				render();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				active = Math.max( active - 1, 0 );
				render();
			} else if ( e.key === 'Enter' ) {
				if ( active >= 0 && items[ active ] ) {
					e.preventDefault();
					onPick( items[ active ] );
					input.value = '';
					close();
				}
			} else if ( e.key === 'Escape' ) {
				close();
			}
		} );
		results.addEventListener( 'mousedown', function ( e ) {
			var li = e.target.closest( '[data-index]' );
			if ( ! li ) {
				return;
			}
			e.preventDefault();
			onPick( items[ parseInt( li.getAttribute( 'data-index' ), 10 ) ] );
			input.value = '';
			close();
		} );
		input.addEventListener( 'blur', function () {
			setTimeout( close, 150 );
		} );
	}

	function initLink( wrap ) {
		var postInput = $( '[data-hk9-link-post]', wrap );
		var chosen = $( '[data-hk9-link-chosen]', wrap );
		var chosenLabel = $( '[data-hk9-link-chosen-label]', wrap );
		var labelInput = $( '[data-hk9-link-label]', wrap );
		var search = $( '[data-hk9-link-search]', wrap );
		var results = $( '[data-hk9-link-results]', wrap );
		var types = wrap.getAttribute( 'data-hk9-post-types' ) || 'page';

		function setMode( mode ) {
			wrap.setAttribute( 'data-hk9-mode', mode );
			$$( '[data-hk9-link-tab]', wrap ).forEach( function ( tab ) {
				tab.setAttribute( 'aria-selected', tab.getAttribute( 'data-hk9-link-tab' ) === mode ? 'true' : 'false' );
			} );
			$$( '[data-hk9-link-panel]', wrap ).forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-hk9-link-panel' ) !== mode;
			} );
			if ( mode === 'external' && postInput && postInput.value !== '0' ) {
				postInput.value = '0';
				if ( chosen ) {
					chosen.hidden = true;
				}
				notify( wrap );
			}
		}
		$$( '[data-hk9-link-tab]', wrap ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				setMode( tab.getAttribute( 'data-hk9-link-tab' ) );
			} );
			tab.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
					e.preventDefault();
					var other = tab.getAttribute( 'data-hk9-link-tab' ) === 'internal' ? 'external' : 'internal';
					setMode( other );
					var t = $( '[data-hk9-link-tab="' + other + '"]', wrap );
					if ( t ) {
						t.focus();
					}
				}
			} );
		} );
		if ( search && results ) {
			initPicker( search, results, types, function ( item ) {
				postInput.value = String( item.id );
				chosenLabel.textContent = item.title;
				chosen.hidden = false;
				if ( labelInput && ! labelInput.value ) {
					labelInput.value = item.title;
				}
				notify( wrap );
			} );
		}
		var clear = $( '[data-hk9-link-clear]', wrap );
		if ( clear ) {
			clear.addEventListener( 'click', function () {
				postInput.value = '0';
				chosen.hidden = true;
				notify( wrap );
				if ( search ) {
					search.focus();
				}
			} );
		}
	}

	function chipHtml( name, item, orderable ) {
		return '<li class="hk9-chip" data-hk9-chip data-id="' + esc( item.id ) + '" draggable="' + ( orderable ? 'true' : 'false' ) + '">' +
			( name ? '<input type="hidden" name="' + esc( name ) + '" value="' + esc( item.id ) + '" />' : '' ) +
			'<span class="hk9-chip__label">' + esc( item.title ) + '</span>' +
			( item.type ? '<span class="hk9-chip__badge">' + esc( item.type ) + '</span>' : '' ) +
			( orderable ? '<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="' + esc( i18n.moveUp || 'Move up' ) + '">&#8593;</button><button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="' + esc( i18n.moveDown || 'Move down' ) + '">&#8595;</button>' : '' ) +
			'<button type="button" class="hk9-chip__remove" data-hk9-chip-remove aria-label="' + esc( i18n.remove || 'Remove' ) + '">&times;</button></li>';
	}

	function initRelationship( wrap ) {
		var chips = $( '[data-hk9-chips]', wrap );
		var search = $( '[data-hk9-pick-search]', wrap );
		var results = $( '[data-hk9-pick-results]', wrap );
		var single = $( '[data-hk9-relationship-single]', wrap );
		var multiple = wrap.getAttribute( 'data-hk9-multiple' ) === '1';
		var orderable = wrap.getAttribute( 'data-hk9-orderable' ) === '1';
		var max = parseInt( wrap.getAttribute( 'data-hk9-max' ) || '0', 10 );
		var name = multiple ? wrap.getAttribute( 'data-hk9-name' ) : '';
		var types = wrap.getAttribute( 'data-hk9-post-types' ) || 'post';

		function ids() {
			return $$( '[data-hk9-chip]', chips ).map( function ( c ) { return c.getAttribute( 'data-id' ); } );
		}
		function sync() {
			if ( single ) {
				var first = $( '[data-hk9-chip]', chips );
				single.value = first ? first.getAttribute( 'data-id' ) : '0';
			}
			search.disabled = max > 0 && ids().length >= max && multiple;
			notify( wrap );
		}
		initPicker( search, results, types, function ( item ) {
			if ( ! multiple ) {
				chips.innerHTML = '';
			} else if ( ids().indexOf( String( item.id ) ) !== -1 || ( max > 0 && ids().length >= max ) ) {
				return;
			}
			chips.insertAdjacentHTML( 'beforeend', chipHtml( name, item, orderable && multiple ) );
			sync();
		} );
		wrap.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '[data-hk9-chip-remove]' );
			if ( rm ) {
				rm.closest( '[data-hk9-chip]' ).remove();
				sync();
				search.focus();
				return;
			}
			var mv = e.target.closest( '[data-hk9-move]' );
			if ( mv && mv.closest( '[data-hk9-chip]' ) ) {
				moveSibling( mv.closest( '[data-hk9-chip]' ), mv.getAttribute( 'data-hk9-move' ) );
				mv.focus();
				sync();
			}
		} );
		if ( orderable ) {
			enableDrag( chips, '[data-hk9-chip]', sync );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Small controls                                                       */
	/* ------------------------------------------------------------------ */

	function initIcon( field ) {
		var select = $( 'select', field );
		var preview = $( '[data-hk9-icon-preview]', field );
		if ( ! select || ! preview ) {
			return;
		}
		select.addEventListener( 'change', function () {
			preview.innerHTML = iconSvg( select.value );
		} );
	}
	function initColor( wrap ) {
		var text = $( 'input[type="text"]', wrap );
		var picker = $( 'input[type="color"]', wrap );
		if ( ! text || ! picker ) {
			return;
		}
		picker.addEventListener( 'input', function () {
			text.value = picker.value;
			text.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		text.addEventListener( 'input', function () {
			if ( /^#[0-9a-fA-F]{6}$/.test( text.value ) ) {
				picker.value = text.value;
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Init                                                                 */
	/* ------------------------------------------------------------------ */

	function initOne( sel, root, fn ) {
		$$( sel, root ).forEach( function ( el ) {
			if ( el.closest( 'template' ) || el.getAttribute( 'data-hk9-ready' ) ) {
				return;
			}
			el.setAttribute( 'data-hk9-ready', '1' );
			fn( el );
		} );
	}

	/** Enhances every control inside root (idempotent). */
	function init( root ) {
		root = root || document;
		initOne( '[data-hk9-media]', root, initImageField );
		initOne( '[data-hk9-gallery]', root, initGallery );
		initOne( '[data-hk9-repeater]', root, initRepeater );
		initOne( '[data-hk9-link]', root, initLink );
		initOne( '[data-hk9-relationship]', root, initRelationship );
		initOne( '.hk9-field--icon', root, initIcon );
		initOne( '[data-hk9-color]', root, initColor );
		attachEditors( root );
	}

	/* ------------------------------------------------------------------ */
	/* Serialization (.hk9-fields → canonical object)                       */
	/* ------------------------------------------------------------------ */

	function serializeField( field ) {
		var type = field.getAttribute( 'data-hk9-type' );
		var ctl;
		switch ( type ) {
			case 'toggle':
				ctl = $( 'input[type="checkbox"][data-hk9-control]', field );
				return !! ( ctl && ctl.checked );
			case 'number':
				ctl = $( '[data-hk9-control]', field );
				var n = ctl ? parseFloat( ctl.value ) : NaN;
				if ( isNaN( n ) ) {
					return 0;
				}
				return ctl.getAttribute( 'data-hk9-float' ) ? n : Math.round( n );
			case 'select':
				ctl = $( 'select[data-hk9-control]', field );
				if ( ! ctl ) {
					return field.getAttribute( 'data-hk9-multiple' ) ? [] : '';
				}
				if ( ctl.multiple ) {
					return $$( 'option:checked', ctl ).map( function ( o ) { return o.value; } );
				}
				return ctl.value;
			case 'image':
			case 'file':
				ctl = $( 'input[type="hidden"][data-hk9-control]', field );
				return ctl ? ( parseInt( ctl.value, 10 ) || 0 ) : 0;
			case 'gallery':
				return $$( '[data-hk9-gallery-item]', field ).map( function ( li ) { return parseInt( li.getAttribute( 'data-id' ), 10 ) || 0; } ).filter( Boolean );
			case 'relationship':
				var idsList = $$( '[data-hk9-chip]', field ).map( function ( c ) { return parseInt( c.getAttribute( 'data-id' ), 10 ) || 0; } ).filter( Boolean );
				return field.getAttribute( 'data-hk9-multiple' ) ? idsList : ( idsList[ 0 ] || 0 );
			case 'link':
				var target = $( '[data-hk9-link-target]', field );
				var rel = $( '[data-hk9-link-rel]', field );
				return {
					label: ( $( '[data-hk9-link-label]', field ) || {} ).value || '',
					url: ( $( '[data-hk9-link-url]', field ) || {} ).value || '',
					post_id: parseInt( ( $( '[data-hk9-link-post]', field ) || {} ).value, 10 ) || 0,
					target: target && target.checked ? '_blank' : '_self',
					rel: rel ? rel.value : ''
				};
			case 'datetime':
				var pair = $( '[data-hk9-datetime]', field );
				if ( pair && pair.getAttribute( 'data-hk9-datetime' ) === 'pair' ) {
					var d = ( $( 'input[type="date"]', pair ) || {} ).value || '';
					var t = ( $( 'input[type="time"]', pair ) || {} ).value || '';
					return d ? ( t ? d + ' ' + t.slice( 0, 5 ) : d ) : '';
				}
				ctl = $( '[data-hk9-control]', field );
				return ctl && ctl.value ? ctl.value.replace( 'T', ' ' ).slice( 0, 16 ) : '';
			case 'repeater':
				var rows = $( '[data-hk9-repeater-rows]', field );
				if ( ! rows ) {
					return [];
				}
				return $$( ':scope > [data-hk9-repeater-row]', rows ).map( function ( row ) {
					return serialize( fieldsOf( $( ':scope > [data-hk9-repeater-body]', row ) ) );
				} );
			case 'group':
				return serialize( fieldsOf( $( '[data-hk9-group]', field ) ) );
			default:
				ctl = $( '[data-hk9-control]', field );
				return ctl ? ctl.value : '';
		}
	}

	/** Serializes a `.hk9-fields` element into an object keyed by field key. */
	function serialize( fieldsEl ) {
		var out = {};
		if ( ! fieldsEl ) {
			return out;
		}
		$$( ':scope > .hk9-field', fieldsEl ).forEach( function ( field ) {
			out[ field.getAttribute( 'data-hk9-key' ) ] = serializeField( field );
		} );
		return out;
	}

	HK9.fields = {
		init: init,
		serialize: serialize,
		serializeField: serializeField,
		fieldsOf: fieldsOf,
		notify: notify,
		iconSvg: iconSvg,
		detachEditors: detachEditors,
		attachEditors: attachEditors,
		announce: liveAnnounce
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { init( document ); } );
	} else {
		init( document );
	}
} )();
