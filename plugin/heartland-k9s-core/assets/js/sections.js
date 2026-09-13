/**
 * Heartland K9s Core — section panels (admin).
 *
 * - Mirrors section forms into the block editor store synchronously on every
 *   input/change (wp.data.dispatch('core/editor').editPost({meta})) so REST
 *   saves, autosaves and previews carry the values (no debounce, by design).
 * - "Page sections" layout panel: reorder (drag + buttons) and show/hide.
 * - Template switch (block editor store or classic #page_template): reloads
 *   both panels through admin-ajax `hk9_section_panel`, passing the editor's
 *   unsaved meta so in-progress values survive the reload.
 */
( function () {
	'use strict';

	var cfg = window.HK9Fields || {};
	var i18n = cfg.i18n || {};
	var HK9 = ( window.HK9 = window.HK9 || {} );
	var F = HK9.fields;

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}
	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	/* ------------------------------------------------------------------ */
	/* Block editor bridge                                                  */
	/* ------------------------------------------------------------------ */

	function editorStore() {
		if ( ! window.wp || ! wp.data || typeof wp.data.select !== 'function' ) {
			return null;
		}
		var sel = wp.data.select( 'core/editor' );
		if ( ! sel || typeof sel.getEditedPostAttribute !== 'function' ) {
			return null;
		}
		return { select: sel, dispatch: wp.data.dispatch( 'core/editor' ) };
	}

	/** Serializes a mirrored container into { metaKey: value } pairs. */
	function metaFor( box ) {
		var mode = box.getAttribute( 'data-hk9-mirror' );
		var out = {};
		if ( mode === 'object' ) {
			var key = box.getAttribute( 'data-hk9-meta-key' );
			var body = $( '.hk9-section__body', box ) || box;
			out[ key ] = F.serialize( F.fieldsOf( body ) );
		} else if ( mode === 'fields' ) {
			var prefix = box.getAttribute( 'data-hk9-meta-prefix' ) || 'hk9_';
			var fields = F.fieldsOf( box );
			if ( fields ) {
				$$( ':scope > .hk9-field', fields ).forEach( function ( field ) {
					if ( field.getAttribute( 'data-hk9-private' ) ) {
						return; // Admin-only fields are not REST meta; the form post saves them.
					}
					out[ prefix + field.getAttribute( 'data-hk9-key' ) ] = F.serializeField( field );
				} );
			}
		} else if ( mode === 'layout' ) {
			out[ box.getAttribute( 'data-hk9-meta-key' ) || 'hk9_sections_layout' ] = layoutValue( box );
		}
		return out;
	}

	function mirror( box ) {
		var store = editorStore();
		if ( ! store ) {
			return;
		}
		var meta = metaFor( box );
		if ( Object.keys( meta ).length ) {
			store.dispatch.editPost( { meta: meta } );
		}
	}

	function onFormEvent( e ) {
		var box = e.target && e.target.closest ? e.target.closest( '[data-hk9-mirror]' ) : null;
		if ( ! box || box.getAttribute( 'data-hk9-mirror' ) === 'none' ) {
			return;
		}
		mirror( box );
	}
	document.addEventListener( 'input', onFormEvent, true );
	document.addEventListener( 'change', onFormEvent, true );
	document.addEventListener( 'hk9:change', onFormEvent, true );

	/* ------------------------------------------------------------------ */
	/* Layout panel                                                         */
	/* ------------------------------------------------------------------ */

	function layoutValue( box ) {
		var order = [];
		var hidden = [];
		$$( '[data-hk9-layout-item]', box ).forEach( function ( li ) {
			var id = li.getAttribute( 'data-section' );
			order.push( id );
			var check = $( 'input[type="checkbox"]', li );
			if ( li.getAttribute( 'data-can-hide' ) === '1' && check && ! check.checked ) {
				hidden.push( id );
			}
		} );
		// Editor-content position (select on section templates, hidden input elsewhere).
		var position = $( '[name$="[content_position]"]', box );
		return { order: order, hidden: hidden, content_position: position && position.value ? position.value : 'after' };
	}

	/** Reflect layout in the content panel: order the section panels + hidden badges. */
	function applyLayoutToSections( layoutBox ) {
		var sections = $( '[data-hk9-sections]' );
		if ( ! sections ) {
			return;
		}
		var value = layoutValue( layoutBox );
		var current = $$( '[data-hk9-section]', sections ).map( function ( p ) { return p.getAttribute( 'data-hk9-section' ); } );
		if ( current.join() !== value.order.join() ) {
			// Reparenting a panel reloads any TinyMCE iframe inside it: detach, move, re-attach.
			F.detachEditors( sections );
			value.order.forEach( function ( id ) {
				var panel = $( '[data-hk9-section="' + id + '"]', sections );
				if ( panel ) {
					sections.appendChild( panel );
				}
			} );
			F.attachEditors( sections );
		}
		$$( '[data-hk9-section]', sections ).forEach( function ( panel ) {
			var isHidden = value.hidden.indexOf( panel.getAttribute( 'data-hk9-section' ) ) !== -1;
			panel.classList.toggle( 'is-section-hidden', isHidden );
			var badge = $( '[data-hk9-section-status]', panel );
			if ( badge ) {
				badge.hidden = ! isHidden;
			}
		} );
	}

	function initLayout( box ) {
		if ( box.getAttribute( 'data-hk9-ready' ) ) {
			return;
		}
		box.setAttribute( 'data-hk9-ready', '1' );
		var list = $( '[data-hk9-layout-list]', box );
		if ( ! list ) {
			return;
		}
		function changed() {
			applyLayoutToSections( box );
			F.notify( box );
		}
		box.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-hk9-move]' );
			if ( ! btn ) {
				return;
			}
			var li = btn.closest( '[data-hk9-layout-item]' );
			var dir = btn.getAttribute( 'data-hk9-move' );
			var sib = dir === 'up' ? li.previousElementSibling : li.nextElementSibling;
			// Skip over fixed items.
			while ( sib && sib.getAttribute( 'data-can-reorder' ) !== '1' ) {
				sib = dir === 'up' ? sib.previousElementSibling : sib.nextElementSibling;
			}
			if ( ! sib ) {
				return;
			}
			if ( dir === 'up' ) {
				list.insertBefore( li, sib );
			} else {
				list.insertBefore( sib, li );
			}
			btn.focus();
			F.announce( ( $( '.hk9-layout__label', li ) || {} ).textContent || '' );
			changed();
		} );
		box.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( 'input[type="checkbox"]' ) ) {
				applyLayoutToSections( box );
			}
		} );
		// Native drag between reorderable items only.
		var dragging = null;
		list.addEventListener( 'dragstart', function ( e ) {
			var li = e.target.closest( '[data-hk9-layout-item]' );
			if ( ! li || li.getAttribute( 'data-can-reorder' ) !== '1' ) {
				e.preventDefault();
				return;
			}
			dragging = li;
			li.classList.add( 'is-dragging' );
			try { e.dataTransfer.setData( 'text/plain', '' ); } catch ( err ) { /* noop */ }
		} );
		list.addEventListener( 'dragover', function ( e ) {
			if ( ! dragging ) {
				return;
			}
			var over = e.target.closest( '[data-hk9-layout-item]' );
			if ( ! over || over === dragging || over.getAttribute( 'data-can-reorder' ) !== '1' ) {
				return;
			}
			e.preventDefault();
			var rect = over.getBoundingClientRect();
			list.insertBefore( dragging, ( e.clientY - rect.top ) > rect.height / 2 ? over.nextSibling : over );
		} );
		list.addEventListener( 'drop', function ( e ) {
			if ( dragging ) {
				e.preventDefault();
			}
		} );
		list.addEventListener( 'dragend', function () {
			if ( dragging ) {
				dragging.classList.remove( 'is-dragging' );
				dragging = null;
				changed();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Template switch                                                      */
	/* ------------------------------------------------------------------ */

	var currentTemplate = null;

	function currentMeta() {
		var store = editorStore();
		if ( ! store ) {
			return {};
		}
		var meta = store.select.getEditedPostAttribute( 'meta' ) || {};
		var out = {};
		Object.keys( meta ).forEach( function ( k ) {
			if ( k.indexOf( 'hk9_sec' ) === 0 ) {
				out[ k ] = meta[ k ];
			}
		} );
		return out;
	}

	function replacePanels( data ) {
		var sectionsBox = $( '[data-hk9-sections-box]' );
		var layoutBox = $( '[data-hk9-layout-box]' );
		if ( sectionsBox ) {
			F.detachEditors( sectionsBox );
			sectionsBox.innerHTML = data.sections;
			F.init( sectionsBox );
		}
		if ( layoutBox ) {
			layoutBox.innerHTML = data.layout;
			$$( '[data-hk9-layout]', layoutBox ).forEach( initLayout );
		}
		var title = $( '#hk9_sections .hndle, #hk9_sections h2.hndle, #hk9_sections .postbox-header h2' );
		if ( title && data.label ) {
			title.textContent = title.textContent.replace( /—.*$/, '— ' + data.label );
		}
		F.announce( data.label || '' );
	}

	function reloadPanels( template ) {
		var sectionsBox = $( '[data-hk9-sections-box]' );
		if ( ! sectionsBox || ! cfg.ajaxUrl || ! cfg.postId ) {
			return;
		}
		sectionsBox.setAttribute( 'aria-busy', 'true' );
		var note = document.createElement( 'p');
		note.className = 'hk9-sections__loading';
		note.textContent = i18n.panelLoading || 'Loading…';
		sectionsBox.insertBefore( note, sectionsBox.firstChild );

		var body = new URLSearchParams();
		body.append( 'action', 'hk9_section_panel' );
		body.append( 'nonce', cfg.panelNonce || '' );
		body.append( 'post_id', String( cfg.postId ) );
		body.append( 'template', template || '' );
		body.append( 'meta', JSON.stringify( currentMeta() ) );

		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( json && json.data && json.data.message ? json.data.message : 'panel' );
				}
				replacePanels( json.data );
			} )
			.catch( function () {
				note.textContent = i18n.panelFailed || 'The section panel could not be reloaded.';
				note.className = 'hk9-sections__loading notice notice-error inline';
			} )
			.finally( function () {
				sectionsBox.removeAttribute( 'aria-busy' );
				if ( note.parentNode && note.className.indexOf( 'notice' ) === -1 ) {
					note.remove();
				}
			} );
	}

	function watchTemplate() {
		var store = editorStore();
		if ( store && window.wp.data.subscribe ) {
			// The post record loads asynchronously: the first defined value is the baseline, not a switch.
			var initial = store.select.getEditedPostAttribute( 'template' );
			currentTemplate = initial === undefined ? null : initial;
			wp.data.subscribe( function () {
				var s = editorStore();
				if ( ! s ) {
					return;
				}
				var t = s.select.getEditedPostAttribute( 'template' );
				if ( t === undefined ) {
					return;
				}
				if ( currentTemplate === null ) {
					currentTemplate = t;
					return;
				}
				if ( t === currentTemplate ) {
					return;
				}
				currentTemplate = t;
				reloadPanels( t );
			} );
			return;
		}
		var select = document.getElementById( 'page_template' );
		if ( select ) {
			select.addEventListener( 'change', function () {
				reloadPanels( select.value );
			} );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

	function boot() {
		if ( ! F ) {
			return;
		}
		$$( '[data-hk9-layout]' ).forEach( initLayout );
		watchTemplate();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	HK9.sections = { mirror: mirror, metaFor: metaFor, reloadPanels: reloadPanels, layoutValue: layoutValue };
} )();
