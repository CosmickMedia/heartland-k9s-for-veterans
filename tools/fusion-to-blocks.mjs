#!/usr/bin/env node
/**
 * fusion-to-blocks.mjs — convert Avada/Fusion Builder rendered page HTML from the
 * live site (https://heartlandk9s.org, WP REST `content.rendered`) into clean core
 * block markup with import tokens ({{media:live:media:<id>}}, {{post_url:live:page:<id>}}).
 *
 * Usage:
 *   node tools/fusion-to-blocks.mjs                 # convert the scoped pages (PAGE_RULES)
 *   node tools/fusion-to-blocks.mjs --all           # convert every page in the input
 *   node tools/fusion-to-blocks.mjs --id 16 --id 3  # only these page ids
 *   node tools/fusion-to-blocks.mjs --pages path/to/pages.json
 *   node tools/fusion-to-blocks.mjs --fetch         # fetch live REST pages (cached to payload-src/source/live-pages.json)
 *   node tools/fusion-to-blocks.mjs --stdout --id 16
 *
 * Inputs : live page JSON (REST shape: id, slug, title.rendered, content.rendered …),
 *          discovery/live/media-manifest.json, discovery/live/inventory.json
 * Outputs: payload-src/source/live__page__<id>.html         original rendered HTML (traceability)
 *          payload-src/source/live__page__<id>.blocks.html  full, uncurated block conversion
 *          payload-src/content/live__page__<id>.html        curated block content (pages whose record attaches content)
 *          payload-src/source/fusion-to-blocks-report.json  per-page notes, unknown media/links, dropped shortcodes
 *
 * The module also exports convertPage() for other tools (extract-registry.mjs reuses the media lookup).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as cheerio from 'cheerio';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
export const ROOT = path.resolve( __dirname, '..' );

const LIVE_HOSTS = [ 'heartlandk9s.org', 'www.heartlandk9s.org', 'heartlandk9s.wpengine.com' ];
const LIVE_REST = 'https://heartlandk9s.org/wp-json/wp/v2/pages?per_page=100&status=publish';

/** Live page ids that are replaced by reference pages authored elsewhere (links are re-pointed). */
const PAGE_KEY_OVERRIDES = {
	6: 'ref:page:home',
	2032: 'ref:page:contact',
	2944: 'ref:page:barkode',
	2491: 'ref:page:stories', // /success/ becomes a draft; links follow the redirect target
	2492: 'ref:page:stories',
};

/* ------------------------------------------------------------------------- */
/* Context: media + page lookups                                              */
/* ------------------------------------------------------------------------- */

export function loadContext( opts = {} ) {
	const manifestPath = opts.manifest || path.join( ROOT, 'discovery/live/media-manifest.json' );
	const inventoryPath = opts.inventory || path.join( ROOT, 'discovery/live/inventory.json' );
	const manifest = JSON.parse( fs.readFileSync( manifestPath, 'utf8' ) );
	const inventory = JSON.parse( fs.readFileSync( inventoryPath, 'utf8' ) );

	const mediaById = new Map();
	const mediaByFile = new Map();
	const mediaByBasename = new Map();
	for ( const item of manifest.items ) {
		mediaById.set( item.id, item );
		const files = new Set();
		if ( item.file ) {
			files.add( item.file.toLowerCase() );
		}
		if ( item.original_image && item.file ) {
			files.add( path.posix.join( path.posix.dirname( item.file ), item.original_image ).toLowerCase() );
		}
		if ( item.source_url ) {
			const rel = uploadsRelativePath( item.source_url );
			if ( rel ) {
				files.add( rel.toLowerCase() );
			}
		}
		for ( const f of files ) {
			if ( ! mediaByFile.has( f ) ) {
				mediaByFile.set( f, item );
			}
			const base = path.posix.basename( f );
			if ( ! mediaByBasename.has( base ) ) {
				mediaByBasename.set( base, item );
			}
		}
	}

	const pagesByPath = new Map();
	for ( const p of inventory.pages ) {
		pagesByPath.set( normalizePath( p.slug_path ), p );
	}

	return { manifest, inventory, mediaById, mediaByFile, mediaByBasename, pagesByPath };
}

function uploadsRelativePath( url ) {
	const m = String( url ).match( /\/wp-content\/uploads\/(.+?)(?:[?#].*)?$/ );
	return m ? decodeURIComponent( m[ 1 ] ) : null;
}

/** Strip WordPress size suffixes: "-300x225", "-scaled", "-1024x934-1" is a real file name (kept). */
function candidateFiles( rel ) {
	const out = [ rel ];
	const noQuery = rel.replace( /[?#].*$/, '' );
	out.push( noQuery );
	const sizeStripped = noQuery.replace( /-\d+x\d+(\.[a-z0-9]+)$/i, '$1' );
	out.push( sizeStripped );
	out.push( sizeStripped.replace( /-scaled(\.[a-z0-9]+)$/i, '$1' ) );
	out.push( noQuery.replace( /-scaled(\.[a-z0-9]+)$/i, '$1' ) );
	// FooGallery cache thumbnails: uploads/cache/2020/02/name/123.jpg -> unknown ext; handled by caller via href.
	return [ ...new Set( out ) ];
}

/**
 * Resolve an uploads URL (any size variant) to a manifest item.
 * Returns { item, matchedBy } or null.
 */
export function findMedia( ctx, url, hintId = 0 ) {
	if ( hintId && ctx.mediaById.has( hintId ) ) {
		return { item: ctx.mediaById.get( hintId ), matchedBy: 'wp-image-class' };
	}
	const rel = uploadsRelativePath( url );
	if ( ! rel ) {
		return null;
	}
	for ( const cand of candidateFiles( rel ) ) {
		const hit = ctx.mediaByFile.get( cand.toLowerCase() );
		if ( hit ) {
			return { item: hit, matchedBy: 'file-path' };
		}
	}
	// cache thumbnails: /uploads/cache/2020/02/new-pinnacle/3720556105.jpg -> basename of the folder
	const cache = rel.match( /^cache\/(\d{4}\/\d{2})\/([^/]+)\/\d+\.([a-z0-9]+)$/i );
	if ( cache ) {
		for ( const ext of [ cache[ 3 ], 'jpg', 'jpeg', 'png', 'webp' ] ) {
			const hit = ctx.mediaByFile.get( `${ cache[ 1 ] }/${ cache[ 2 ] }.${ ext }`.toLowerCase() );
			if ( hit ) {
				return { item: hit, matchedBy: 'cache-path' };
			}
		}
	}
	const base = path.posix.basename( candidateFiles( rel ).at( -2 ) );
	const byBase = ctx.mediaByBasename.get( base.toLowerCase() );
	if ( byBase ) {
		return { item: byBase, matchedBy: 'basename' };
	}
	return null;
}

export function normalizePath( p ) {
	let s = String( p || '' ).trim();
	try {
		s = decodeURIComponent( s );
	} catch {
		/* keep as-is */
	}
	s = s.toLowerCase().replace( /[?#].*$/, '' );
	if ( ! s.startsWith( '/' ) ) {
		s = '/' + s;
	}
	if ( ! s.endsWith( '/' ) ) {
		s += '/';
	}
	return s;
}

function isLiveUrl( href ) {
	try {
		const u = new URL( href, 'https://heartlandk9s.org/' );
		return LIVE_HOSTS.includes( u.hostname.toLowerCase() );
	} catch {
		return false;
	}
}

export function pageKeyForId( id ) {
	return PAGE_KEY_OVERRIDES[ id ] || `live:page:${ id }`;
}

/**
 * Rewrite an href: live page URL -> {{post_url:K}}, uploads file -> {{media_url:K}}, external kept.
 * Returns { href, kind: 'page'|'media'|'external'|'unknown-internal'|'anchor', key?, item? }
 */
export function rewriteHref( ctx, href, report ) {
	const raw = String( href || '' ).trim();
	if ( ! raw || raw.startsWith( '#' ) ) {
		return { href: raw, kind: 'anchor' };
	}
	if ( /^(mailto|tel):/i.test( raw ) ) {
		return { href: raw, kind: 'external' };
	}
	if ( ! isLiveUrl( raw ) && /^[a-z]+:/i.test( raw ) ) {
		return { href: raw, kind: 'external' };
	}
	const u = new URL( raw, 'https://heartlandk9s.org/' );
	if ( u.pathname.includes( '/wp-content/uploads/' ) ) {
		const found = findMedia( ctx, u.href );
		if ( found ) {
			const key = `live:media:${ found.item.id }`;
			return { href: `{{media_url:${ key }}}`, kind: 'media', key, item: found.item };
		}
		report.unknownMedia.push( raw );
		return { href: raw, kind: 'unknown-internal' };
	}
	if ( u.pathname === '/' && ! u.search ) {
		return { href: `{{post_url:${ pageKeyForId( 6 ) }}}`, kind: 'page', key: pageKeyForId( 6 ) };
	}
	const page = ctx.pagesByPath.get( normalizePath( u.pathname ) );
	if ( page && ! u.search ) {
		const key = pageKeyForId( page.id );
		return { href: `{{post_url:${ key }}}`, kind: 'page', key };
	}
	report.unknownLinks.push( raw );
	return { href: raw, kind: 'unknown-internal' };
}

/* ------------------------------------------------------------------------- */
/* HTML helpers                                                               */
/* ------------------------------------------------------------------------- */

const escText = ( s ) => String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
const escAttr = ( s ) => escText( s ).replace( /"/g, '&quot;' );
/** Serialize block attrs; media/post id tokens must be emitted unquoted so the importer can substitute integers. */
function attrsJson( o ) {
	const json = JSON.stringify( o );
	return json.replace( /"(\{\{(?:media|post):[^}]+\}\})"/g, '$1' );
}

/** Alt text auto-generated from a camera/Facebook file name carries no meaning for readers. */
function isJunkAlt( alt ) {
	const a = cleanSpace( alt );
	return !! a && ( /^[\d_\s-]+n?$/.test( a ) || /^(img|dsc|image|received|screenshot)[\s_-]*\d*/i.test( a ) || /^[a-z0-9]{20,}/i.test( a ) || /^\d{5,}/.test( a ) );
}

function cleanSpace( s ) {
	return String( s ).replace( / /g, ' ' ).replace( /[ \t\r\n]+/g, ' ' ).trim();
}

const INLINE_KEEP = new Set( [ 'a', 'strong', 'b', 'em', 'i', 'br', 'u', 'sub', 'sup', 'code', 's', 'del', 'mark', 'span', 'font', 'small' ] );

/** Serialize inline children of a node into clean HTML (a/strong/em/br/u/sub/sup/code kept, everything else unwrapped). */
function inlineHtml( $, node, ctx, report ) {
	let out = '';
	for ( const child of node.childNodes || [] ) {
		if ( child.type === 'text' ) {
			out += escText( child.data.replace( / /g, ' ' ) );
			continue;
		}
		if ( child.type === 'comment' ) {
			continue;
		}
		if ( child.type !== 'tag' ) {
			continue;
		}
		const name = child.name.toLowerCase();
		if ( name === 'br' ) {
			out += '<br>';
			continue;
		}
		if ( name === 'img' ) {
			// inline images are hoisted by the caller (paragraph handler); ignore here
			continue;
		}
		if ( name === 'a' ) {
			const href = $( child ).attr( 'href' ) || '';
			const rw = rewriteHref( ctx, href, report );
			const inner = inlineHtml( $, child, ctx, report );
			if ( ! rw.href ) {
				out += inner;
				continue;
			}
			let attrs = ` href="${ escAttr( rw.href ) }"`;
			const target = rw.kind === 'page' ? '' : $( child ).attr( 'target' ); // internal page links never open a new tab
			const isExternal = rw.kind === 'external';
			if ( target === '_blank' || isExternal ) {
				const rel = new Set( String( $( child ).attr( 'rel' ) || '' ).split( /\s+/ ).filter( Boolean ) );
				rel.delete( 'noreferrer' );
				if ( target === '_blank' ) {
					rel.add( 'noopener' );
					attrs += ' target="_blank"';
				}
				rel.delete( 'nofollow' );
				if ( rel.size ) {
					attrs += ` rel="${ escAttr( [ ...rel ].join( ' ' ) ) }"`;
				}
			}
			out += `<a${ attrs }>${ inner }</a>`;
			continue;
		}
		if ( name === 'strong' || name === 'b' ) {
			out += `<strong>${ inlineHtml( $, child, ctx, report ) }</strong>`;
			continue;
		}
		if ( name === 'em' || name === 'i' ) {
			out += `<em>${ inlineHtml( $, child, ctx, report ) }</em>`;
			continue;
		}
		if ( [ 'u', 'sub', 'sup', 'code', 's', 'del', 'mark' ].includes( name ) ) {
			out += `<${ name }>${ inlineHtml( $, child, ctx, report ) }</${ name }>`;
			continue;
		}
		// span/font/small/div-in-inline-context/etc: unwrap
		out += inlineHtml( $, child, ctx, report );
	}
	return out;
}

function tidyInline( html ) {
	// collapse whitespace but keep <br>; trim leading/trailing breaks
	let s = html.replace( /[ \t\r\n]+/g, ' ' ).replace( / ?<br> ?/g, '<br>' ).trim();
	s = s.replace( /^(<br>)+/, '' ).replace( /(<br>)+$/, '' );
	// strip empty inline wrappers
	s = s.replace( /<(strong|em|u)>\s*<\/\1>/g, '' );
	return s.trim();
}

const stripTags = ( html ) => cleanSpace( html.replace( /<br>/g, ' ' ).replace( /<[^>]+>/g, '' ).replace( /&amp;/g, '&' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&quot;/g, '"' ) );

/* ------------------------------------------------------------------------- */
/* Block builders                                                             */
/* ------------------------------------------------------------------------- */

const B = {
	paragraph( html, extra = {} ) {
		const attrs = Object.keys( extra ).length ? ' ' + attrsJson( extra ) : '';
		const cls = extra.align ? ` class="has-text-align-${ extra.align }"` : '';
		return { type: 'paragraph', text: stripTags( html ), html: `<!-- wp:paragraph${ attrs } -->\n<p${ cls }>${ html }</p>\n<!-- /wp:paragraph -->` };
	},
	heading( html, level = 2, align = '' ) {
		const attrs = {};
		if ( level !== 2 ) {
			attrs.level = level;
		}
		if ( align ) {
			attrs.textAlign = align;
		}
		const a = Object.keys( attrs ).length ? ' ' + attrsJson( attrs ) : '';
		const cls = 'wp-block-heading' + ( align ? ` has-text-align-${ align }` : '' );
		return { type: 'heading', level, text: stripTags( html ), html: `<!-- wp:heading${ a } -->\n<h${ level } class="${ cls }">${ html }</h${ level }>\n<!-- /wp:heading -->` };
	},
	list( items, ordered = false ) {
		const tag = ordered ? 'ol' : 'ul';
		const attrs = ordered ? ' {"ordered":true}' : '';
		const inner = items.map( ( it ) => `<!-- wp:list-item -->\n<li>${ it }</li>\n<!-- /wp:list-item -->` ).join( '\n\n' );
		return { type: 'list', items, text: items.map( stripTags ).join( ' | ' ), html: `<!-- wp:list${ attrs } -->\n<${ tag } class="wp-block-list">${ inner }</${ tag }>\n<!-- /wp:list -->` };
	},
	separator() {
		return { type: 'separator', text: '', html: '<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->' };
	},
	image( key, alt, link = null, align = '' ) {
		const attrs = { id: `{{media:${ key }}}`, sizeSlug: 'large', linkDestination: link ? 'custom' : 'none' };
		if ( align ) {
			attrs.align = align;
		}
		const cls = 'wp-block-image' + ( align ? ` align${ align }` : '' ) + ' size-large';
		let img = `<img src="{{media_url:${ key }}}" alt="${ escAttr( alt || '' ) }" class="wp-image-{{media:${ key }}}"/>`;
		if ( link ) {
			const extra = link.external ? ' target="_blank" rel="noopener"' : '';
			img = `<a href="${ escAttr( link.href ) }"${ extra }>${ img }</a>`;
		}
		return { type: 'image', key, alt: alt || '', link: link ? link.href : '', text: alt || '', html: `<!-- wp:image ${ attrsJson( attrs ) } -->\n<figure class="${ cls }">${ img }</figure>\n<!-- /wp:image -->` };
	},
	imageUnknown( src, alt ) {
		return { type: 'image', key: '', src, alt: alt || '', text: alt || '', html: `<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->\n<figure class="wp-block-image size-large"><img src="${ escAttr( src ) }" alt="${ escAttr( alt || '' ) }"/></figure>\n<!-- /wp:image -->` };
	},
	file( key, label, fileName = '' ) {
		const href = `{{media_url:${ key }}}`;
		const attrs = { id: `{{media:${ key }}}`, href, displayPreview: false };
		return { type: 'file', key, text: label, html: `<!-- wp:file ${ attrsJson( attrs ) } -->\n<div class="wp-block-file"><a href="${ href }">${ escText( label || fileName || 'Download' ) }</a><a href="${ href }" class="wp-block-file__button wp-element-button" download>Download</a></div>\n<!-- /wp:file -->` };
	},
	video( key ) {
		return { type: 'video', key, text: '', html: `<!-- wp:video {"id":{{media:${ key }}}} -->\n<figure class="wp-block-video"><video controls preload="metadata" src="{{media_url:${ key }}}"></video></figure>\n<!-- /wp:video -->` };
	},
	buttons( buttons, align = '' ) {
		const attrs = align === 'center' ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '';
		const inner = buttons.map( ( b ) => {
			const battrs = {};
			let extra = '';
			if ( b.external ) {
				battrs.linkTarget = '_blank';
				battrs.rel = 'noopener';
				extra = ' target="_blank" rel="noopener"';
			}
			const a = Object.keys( battrs ).length ? ' ' + attrsJson( battrs ) : '';
			return `<!-- wp:button${ a } -->\n<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="${ escAttr( b.href ) }"${ extra }>${ b.label }</a></div>\n<!-- /wp:button -->`;
		} ).join( '\n\n' );
		return { type: 'buttons', buttons, text: buttons.map( ( b ) => stripTags( b.label ) ).join( ' | ' ), html: `<!-- wp:buttons${ attrs } -->\n<div class="wp-block-buttons">${ inner }</div>\n<!-- /wp:buttons -->` };
	},
	gallery( images, { columns = 3, linkTo = 'media' } = {} ) {
		const inner = images.map( ( im ) => {
			const attrs = { id: `{{media:${ im.key }}}`, sizeSlug: 'large', linkDestination: linkTo === 'media' ? 'media' : 'none' };
			let img = `<img src="{{media_url:${ im.key }}}" alt="${ escAttr( im.alt || '' ) }" class="wp-image-{{media:${ im.key }}}"/>`;
			if ( linkTo === 'media' ) {
				img = `<a href="{{media_url:${ im.key }|original}}">${ img }</a>`;
			}
			const cap = im.caption ? `<figcaption class="wp-element-caption">${ im.caption }</figcaption>` : '';
			return `<!-- wp:image ${ attrsJson( attrs ) } -->\n<figure class="wp-block-image size-large">${ img }${ cap }</figure>\n<!-- /wp:image -->`;
		} ).join( '\n\n' );
		const gattrs = { columns, linkTo, sizeSlug: 'large' };
		return { type: 'gallery', images, text: `gallery(${ images.length })`, html: `<!-- wp:gallery ${ attrsJson( gattrs ) } -->\n<figure class="wp-block-gallery has-nested-images columns-${ columns } is-cropped">${ inner }</figure>\n<!-- /wp:gallery -->` };
	},
	socialLinks( links ) {
		const inner = links.map( ( l ) => `<!-- wp:social-link ${ attrsJson( { url: l.url, service: l.service } ) } /-->` ).join( '' );
		return { type: 'social-links', text: links.map( ( l ) => l.service ).join( ' ' ), html: `<!-- wp:social-links -->\n<ul class="wp-block-social-links">${ inner }</ul>\n<!-- /wp:social-links -->` };
	},
};

/* ------------------------------------------------------------------------- */
/* Walker                                                                     */
/* ------------------------------------------------------------------------- */

const HEADING_TO_PARAGRAPH_CHARS = 140;

function classList( $el ) {
	return String( $el.attr( 'class' ) || '' ).split( /\s+/ ).filter( Boolean );
}
function hasClass( $el, prefix ) {
	return classList( $el ).some( ( c ) => c === prefix || c.startsWith( prefix + '-' ) );
}

function socialService( url ) {
	const h = new URL( url, 'https://x.invalid/' ).hostname.replace( /^www\./, '' );
	const map = { 'facebook.com': 'facebook', 'instagram.com': 'instagram', 'youtube.com': 'youtube', 'linkedin.com': 'linkedin', 'x.com': 'x', 'twitter.com': 'x', 'tiktok.com': 'tiktok' };
	return map[ h ] || 'chain';
}

function convertHtml( html, ctx, report ) {
	// strip HTML comments (Figma paste metadata etc.) before parsing
	const cleaned = String( html || '' ).replace( /<!--[\s\S]*?-->/g, '' );
	const $ = cheerio.load( cleaned, null, false );
	const blocks = [];

	const push = ( b ) => {
		if ( ! b ) {
			return;
		}
		if ( b.type === 'separator' && blocks.length && blocks.at( -1 ).type === 'separator' ) {
			return; // collapse duplicates
		}
		if ( b.type === 'buttons' && blocks.length && blocks.at( -1 ).type === 'buttons' && blocks.at( -1 ).align === b.align ) {
			const prev = blocks.pop();
			const merged = B.buttons( [ ...prev.buttons, ...b.buttons ], b.align );
			merged.align = b.align;
			blocks.push( merged );
			return;
		}
		blocks.push( b );
	};

	const shortcodeRe = /\[(\/?)([a-z0-9_]+)([^\]]*)\]/gi;
	function handleTextNode( text ) {
		let t = text;
		const found = [ ...t.matchAll( shortcodeRe ) ];
		for ( const m of found ) {
			report.shortcodesRemoved.push( m[ 0 ] );
		}
		t = t.replace( shortcodeRe, '' );
		t = cleanSpace( t );
		if ( t ) {
			push( B.paragraph( escText( t ) ) );
		}
	}

	function paragraphFrom( $el ) {
		// hoist images inside the paragraph
		$el.find( 'img' ).each( ( _, img ) => push( imageFrom( $( img ) ) ) );
		$el.find( 'img' ).remove();
		let html = tidyInline( inlineHtml( $, $el.get( 0 ), ctx, report ) );
		// remove shortcodes inside paragraphs
		const found = [ ...html.matchAll( shortcodeRe ) ];
		for ( const m of found ) {
			report.shortcodesRemoved.push( m[ 0 ] );
		}
		html = tidyInline( html.replace( shortcodeRe, '' ) );
		if ( ! html || ! stripTags( html ) ) {
			return null;
		}
		const styleAlign = /text-align:\s*center/i.test( $el.attr( 'style' ) || '' ) ? 'center' : '';
		return B.paragraph( html, styleAlign ? { align: styleAlign } : {} );
	}

	function listFrom( $el, ordered ) {
		const items = [];
		$el.children( 'li' ).each( ( _, li ) => {
			const $li = $( li );
			const nested = $li.children( 'ul, ol' );
			nested.remove();
			const html = tidyInline( inlineHtml( $, li, ctx, report ) );
			if ( html ) {
				items.push( html );
			}
			// nested lists are flattened after the parent item (rare on this site)
			nested.each( ( __, n ) => {
				$( n ).children( 'li' ).each( ( ___, nli ) => {
					const h = tidyInline( inlineHtml( $, nli, ctx, report ) );
					if ( h ) {
						items.push( h );
					}
				} );
			} );
		} );
		return items.length ? B.list( items, ordered ) : null;
	}

	function headingFrom( $h, forcedLevel = 0 ) {
		const tag = $h.get( 0 ).name.toLowerCase();
		let level = forcedLevel || parseInt( tag.slice( 1 ), 10 ) || 2;
		// the page title is the only h1 (rendered by the hero); demote h1 to h2
		if ( level === 1 ) {
			level = 2;
		}
		// invalid nesting: block children inside the heading (Fusion allows <h5><p>..</p></h5>)
		const blockKids = $h.children( 'p, ul, ol, h1, h2, h3, h4, h5, h6, div' );
		if ( blockKids.length ) {
			blockKids.each( ( _, kid ) => walk( kid ) );
			return;
		}
		const html = tidyInline( inlineHtml( $, $h.get( 0 ), ctx, report ) );
		const text = stripTags( html );
		if ( ! text ) {
			report.notes.push( `dropped empty <${ tag }> spacer` );
			return;
		}
		const align = /title-heading-center|text-align:\s*center/.test( ( $h.attr( 'class' ) || '' ) + ( $h.attr( 'style' ) || '' ) ) ? 'center' : '';
		if ( text.length > HEADING_TO_PARAGRAPH_CHARS ) {
			report.notes.push( `long <${ tag }> converted to paragraph: "${ text.slice( 0, 60 ) }…"` );
			push( B.paragraph( html, align ? { align } : {} ) );
			return;
		}
		push( B.heading( html, level, align ) );
	}

	function imageFrom( $img, $anchor = null, align = '' ) {
		const src = $img.attr( 'src' ) || $img.attr( 'data-src' ) || '';
		if ( ! src ) {
			return null;
		}
		const hint = parseInt( ( classList( $img ).find( ( c ) => /^wp-image-\d+$/.test( c ) ) || '' ).replace( 'wp-image-', '' ), 10 ) || 0;
		const found = findMedia( ctx, src, hint );
		let alt = cleanSpace( $img.attr( 'alt' ) || '' );
		if ( ! found ) {
			report.unknownMedia.push( src );
			return B.imageUnknown( src, alt );
		}
		const item = found.item;
		if ( ! alt && item.alt_text ) {
			alt = cleanSpace( item.alt_text );
		}
		if ( isJunkAlt( alt ) ) {
			report.notes.push( `blanked file-name alt text "${ alt }"` );
			alt = '';
		}
		const key = `live:media:${ item.id }`;
		// image that is really a document preview (PDF thumbnail) -> file block
		if ( ! /^image\//.test( item.mime_type || '' ) ) {
			return B.file( key, alt || ( $anchor && cleanSpace( $anchor.attr( 'aria-label' ) || '' ) ) || item.title || 'Download', item.title );
		}
		let link = null;
		if ( $anchor ) {
			const rw = rewriteHref( ctx, $anchor.attr( 'href' ) || '', report );
			if ( rw.kind === 'media' && rw.item && ! /^image\//.test( rw.item.mime_type || '' ) ) {
				// image linking to a document: emit the file block instead
				return B.file( rw.key, alt || rw.item.title || 'Download', rw.item.title );
			}
			if ( rw.kind === 'media' && rw.item && rw.item.id === item.id ) {
				link = null; // link to itself (lightbox) -> plain image
			} else if ( rw.href && rw.kind !== 'anchor' ) {
				link = { href: rw.href, external: rw.kind === 'external' };
			}
		}
		return B.image( key, alt, link, align );
	}

	function buttonFrom( $a ) {
		const href = $a.attr( 'href' ) || '';
		const label = tidyInline( inlineHtml( $, $a.get( 0 ), ctx, report ) );
		if ( ! stripTags( label ) ) {
			return null;
		}
		const rw = rewriteHref( ctx, href, report );
		if ( rw.kind === 'media' && rw.item && ! /^image\//.test( rw.item.mime_type || '' ) ) {
			return B.file( rw.key, stripTags( label ), rw.item.title );
		}
		return { href: rw.href, label, external: rw.kind === 'external', kind: rw.kind, key: rw.key || '' };
	}

	function walk( node ) {
		if ( ! node ) {
			return;
		}
		if ( node.type === 'text' ) {
			handleTextNode( node.data );
			return;
		}
		if ( node.type !== 'tag' ) {
			return;
		}
		const $el = $( node );
		const name = node.name.toLowerCase();
		const cls = classList( $el );

		if ( [ 'script', 'style', 'noscript', 'template', 'svg' ].includes( name ) ) {
			return;
		}
		if ( name === 'iframe' ) {
			report.notes.push( `dropped iframe ${ $el.attr( 'src' ) || '' }` );
			return;
		}

		// --- Fusion title ---------------------------------------------------
		if ( cls.includes( 'fusion-title' ) ) {
			const heads = $el.find( 'h1, h2, h3, h4, h5, h6' ).toArray();
			// parse5 splits <h1><h2>x</h2></h1> into an empty h1 followed by h2: use every heading, empty ones are dropped
			for ( const h of heads ) {
				if ( $( h ).parents( 'h1, h2, h3, h4, h5, h6' ).length ) {
					continue; // handled by the outer heading (nested block kids)
				}
				headingFrom( $( h ) );
			}
			return;
		}

		// --- Fusion text / generic containers -------------------------------
		if ( cls.includes( 'fusion-text' ) || cls.includes( 'content-container' ) ) {
			node.childNodes.forEach( walk );
			return;
		}

		// --- Buttons ----------------------------------------------------------
		if ( name === 'a' && cls.includes( 'fusion-button' ) ) {
			const b = buttonFrom( $el );
			if ( b && b.type === 'file' ) {
				push( b );
			} else if ( b ) {
				const parentCls = classList( $el.parent() );
				const align = parentCls.includes( 'fusion-aligncenter' ) || parentCls.includes( 'fusion-align-center' ) ? 'center' : '';
				const bb = B.buttons( [ b ], align );
				bb.align = align;
				push( bb );
			}
			return;
		}

		// --- Images -----------------------------------------------------------
		if ( cls.includes( 'fusion-imageframe' ) || cls.includes( 'fusion-image-element' ) ) {
			const $img = $el.find( 'img' ).first();
			if ( ! $img.length ) {
				return;
			}
			const $a = $img.closest( 'a' );
			const align = cls.includes( 'fusion-image-align-center' ) || $el.find( '.imageframe-align-center' ).length ? 'center' : '';
			push( imageFrom( $img, $a.length ? $a : null, align ) );
			// captions (Fusion image element captions)
			const $cap = $el.find( '.awb-imageframe-caption, .fusion-imageframe-caption' ).first();
			if ( $cap.length ) {
				const cap = tidyInline( inlineHtml( $, $cap.get( 0 ), ctx, report ) );
				if ( cap ) {
					push( B.paragraph( `<em>${ cap }</em>` ) );
				}
			}
			return;
		}

		// --- Separator --------------------------------------------------------
		if ( cls.includes( 'fusion-separator' ) ) {
			if ( $el.find( '.fusion-separator-border' ).length ) {
				push( B.separator() );
			}
			return;
		}

		// --- FooGallery -------------------------------------------------------
		if ( cls.includes( 'foogallery' ) && cls.includes( 'foogallery-container' ) ) {
			const images = [];
			$el.find( '.fg-item' ).each( ( _, it ) => {
				const $it = $( it );
				const href = $it.find( 'a.fg-thumb' ).attr( 'href' ) || '';
				const $img = $it.find( 'img' ).first();
				const found = findMedia( ctx, href ) || findMedia( ctx, $img.attr( 'src' ) || '' );
				if ( ! found ) {
					report.unknownMedia.push( href || $img.attr( 'src' ) || '' );
					return;
				}
				const title = cleanSpace( $it.find( '.fg-caption-title' ).text() );
				const desc = cleanSpace( $it.find( '.fg-caption-desc' ).text() );
				let caption = '';
				if ( title && desc ) {
					caption = `<strong>${ escText( title ) }</strong> — ${ escText( desc ) }`;
				} else if ( title || desc ) {
					caption = escText( title || desc );
				}
				let alt = cleanSpace( $img.attr( 'alt' ) || found.item.alt_text || '' );
				if ( isJunkAlt( alt ) ) {
					alt = '';
				}
				images.push( { key: `live:media:${ found.item.id }`, alt, caption } );
			} );
			if ( images.length ) {
				const g = B.gallery( images, { columns: 3, linkTo: 'media' } );
				g.galleryId = ( $el.attr( 'id' ) || '' ).replace( 'foogallery-gallery-', '' );
				push( g );
			}
			return;
		}

		// --- Image carousel ---------------------------------------------------
		if ( cls.includes( 'fusion-image-carousel' ) ) {
			const images = [];
			$el.find( '.swiper-slide img' ).each( ( _, img ) => {
				const $img = $( img );
				const found = findMedia( ctx, $img.attr( 'src' ) || '' );
				if ( ! found ) {
					report.unknownMedia.push( $img.attr( 'src' ) || '' );
					return;
				}
				images.push( { key: `live:media:${ found.item.id }`, alt: cleanSpace( $img.attr( 'alt' ) || found.item.alt_text || '' ), caption: '' } );
			} );
			if ( images.length ) {
				push( B.gallery( images, { columns: Math.min( 3, images.length ), linkTo: 'none' } ) );
			}
			return;
		}

		// --- Video ------------------------------------------------------------
		if ( name === 'video' || cls.includes( 'wp-video' ) || cls.includes( 'fusion-video' ) ) {
			const $v = name === 'video' ? $el : $el.find( 'video' ).first();
			const src = $v.attr( 'src' ) || $v.find( 'source' ).attr( 'src' ) || '';
			const found = src ? findMedia( ctx, src ) : null;
			if ( found ) {
				push( B.video( `live:media:${ found.item.id }` ) );
			} else {
				report.unknownMedia.push( src );
			}
			return;
		}

		// --- Social links -----------------------------------------------------
		if ( cls.includes( 'fusion-social-links' ) ) {
			const links = [];
			$el.find( 'a[href]' ).each( ( _, a ) => {
				const url = $( a ).attr( 'href' );
				if ( /^https?:/.test( url ) ) {
					links.push( { url, service: socialService( url ) } );
				}
			} );
			if ( links.length ) {
				push( B.socialLinks( links ) );
			}
			return;
		}

		// --- PayPal hosted button form --------------------------------------
		if ( name === 'form' ) {
			const hosted = $el.find( 'input[name="hosted_button_id"]' ).attr( 'value' );
			if ( hosted ) {
				const url = `https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=${ encodeURIComponent( hosted ) }`;
				push( B.paragraph( `<a href="${ escAttr( url ) }" target="_blank" rel="noopener">Donate with PayPal</a>` ) );
				report.notes.push( `PayPal hosted-button form converted to link (${ url })` );
			} else {
				report.notes.push( 'dropped non-PayPal <form>' );
			}
			return;
		}

		// --- Fusion content boxes (icon/heading/text cards) -------------------
		if ( cls.includes( 'fusion-content-boxes' ) ) {
			$el.find( '.content-box-wrapper' ).each( ( _, box ) => {
				const $box = $( box );
				const $img = $box.find( '.heading img' ).first();
				if ( $img.length ) {
					push( imageFrom( $img ) );
				}
				const $h = $box.find( '.content-box-heading' ).first();
				if ( $h.length ) {
					headingFrom( $h, 3 );
				}
				$box.find( '.content-container' ).each( ( __, c ) => c.childNodes.forEach( walk ) );
			} );
			return;
		}

		// --- Plain HTML elements -------------------------------------------
		if ( /^h[1-6]$/.test( name ) ) {
			headingFrom( $el );
			return;
		}
		if ( name === 'p' ) {
			push( paragraphFrom( $el ) );
			return;
		}
		if ( name === 'ul' || name === 'ol' ) {
			push( listFrom( $el, name === 'ol' ) );
			return;
		}
		if ( name === 'blockquote' ) {
			const inner = [];
			$el.children( 'p' ).each( ( _, p ) => {
				const b = paragraphFrom( $( p ) );
				if ( b ) {
					inner.push( b.html );
				}
			} );
			if ( inner.length ) {
				push( { type: 'quote', text: inner.map( stripTags ).join( ' ' ), html: `<!-- wp:quote -->\n<blockquote class="wp-block-quote">${ inner.join( '\n\n' ) }</blockquote>\n<!-- /wp:quote -->` } );
			}
			return;
		}
		if ( name === 'img' ) {
			const $a = $el.closest( 'a' );
			push( imageFrom( $el, $a.length ? $a : null ) );
			return;
		}
		if ( name === 'hr' ) {
			push( B.separator() );
			return;
		}
		if ( name === 'br' ) {
			return;
		}

		// --- Containers: div/span/section/a(not button)/etc. ----------------
		// A container whose children are only inline nodes is treated as a paragraph
		// (e.g. Fusion's <div dir="auto"> lines); otherwise recurse.
		const kids = node.childNodes || [];
		const hasBlockKid = kids.some( ( k ) => k.type === 'tag' && ! INLINE_KEEP.has( k.name.toLowerCase() ) && k.name.toLowerCase() !== 'img' );
		const hasButtonKid = kids.some( ( k ) => k.type === 'tag' && k.name.toLowerCase() === 'a' && classList( $( k ) ).includes( 'fusion-button' ) );
		const hasText = kids.some( ( k ) => ( k.type === 'text' && cleanSpace( k.data ) ) || ( k.type === 'tag' && INLINE_KEEP.has( k.name.toLowerCase() ) && cleanSpace( $( k ).text() ) ) );
		if ( ! hasBlockKid && ! hasButtonKid && hasText ) {
			if ( name === 'a' && $el.attr( 'href' ) ) {
				// a bare link container (not a fusion button): paragraph holding the link itself
				const $wrap = $( '<div></div>' );
				$wrap.append( $el.clone() );
				push( paragraphFrom( $wrap ) );
				return;
			}
			push( paragraphFrom( $el ) );
			return;
		}
		// unknown fusion-* wrappers are recursed into; note the exotic ones
		const exotic = cls.find( ( c ) => /^(fusion|awb)-/.test( c ) && ! /^(fusion-(fullwidth|builder-row|row|layout-column|column|column-wrapper|column-content|column-content-centered|clearfix|sep-clear|bg-parallax|flex-container|flex-column|content-layout-column|flex-justify-content|flex-align-items|flex-content-wrap|no-small-visibility|no-medium-visibility|no-large-visibility|animated|one-full|one-half|one-third|two-third|one-sixth|two-fifth|three-fifth|column-first|column-last|column-has-shadow|flex-column-wrapper-legacy|parallax|equal-height-columns|builder-column|builder-column-\d+|fullwidth-box|nonhundred-percent-fullwidth|non-hundred-percent-height-scrolling|parallax-none|parallax-up|parallax-fixed|button-wrapper|aligncenter|alignleft|alignright|align-block|imageframe-align|image-align-center|column-wrapper-legacy|fusion_builder_column|fusion_builder_column_\d+_\d+|fusion_builder_column_\d+)|awb-(carousel|swiper|title-spacer))/.test( c ) && ! /^fusion-builder-column-\d+$/.test( c ) && ! /^fusion_builder_column(_\d+_\d+)?$/.test( c ) && ! /^fusion-builder-row-\d+$/.test( c ) );
		if ( exotic ) {
			report.unknownWrappers.add( exotic );
		}
		kids.forEach( walk );
	}

	$.root().get( 0 ).childNodes.forEach( walk );
	return blocks;
}

/* ------------------------------------------------------------------------- */
/* Page conversion                                                            */
/* ------------------------------------------------------------------------- */

export function decodeEntities( s ) {
	return cheerio.load( `<x>${ s }</x>`, null, false )( 'x' ).text();
}

export function cleanTitle( title ) {
	return cleanSpace( decodeEntities( title ) );
}

export function newReport() {
	return { unknownMedia: [], unknownLinks: [], shortcodesRemoved: [], notes: [], unknownWrappers: new Set() };
}

/**
 * Convert one live page (REST object) to blocks.
 * Returns { id, slug, title, blocks: [{type, html, text, ...}], report }
 */
export function convertPage( page, ctx ) {
	const report = newReport();
	const title = cleanTitle( page.title?.rendered ?? page.title ?? '' );
	const blocks = convertHtml( page.content?.rendered ?? page.content ?? '', ctx, report );
	report.unknownWrappers = [ ...report.unknownWrappers ];
	return { id: page.id, slug: page.slug, title, blocks, report };
}

export const blocksToHtml = ( blocks ) => blocks.map( ( b ) => b.html ).join( '\n\n' ) + '\n';

/* ------------------------------------------------------------------------- */
/* Per-page curation rules (what the page record actually attaches)           */
/* ------------------------------------------------------------------------- */

const norm = ( s ) => cleanSpace( String( s || '' ) ).toLowerCase().replace( /[“”"’']/g, '' );
const textIs = ( text ) => ( b ) => norm( b.text ) === norm( text );
const textStarts = ( text ) => ( b ) => norm( b.text ).startsWith( norm( text ) );
const buttonLabelled = ( label ) => ( b ) => b.type === 'buttons' && b.buttons.some( ( x ) => norm( stripTags( x.label ) ) === norm( label ) );
const headingIs = ( text ) => ( b ) => b.type === 'heading' && norm( b.text ) === norm( text );

/** Blocks used by the obedience-training price sheet (text extracted from the live .docx; see docs/migration-map.md). */
function obediencePriceSheetBlocks() {
	return [
		B.heading( 'Heartland Obedience Training price sheet', 3 ),
		B.paragraph( 'Serving Joplin, Webb City, Carthage, Pittsburg &amp; Surrounding Areas. All proceeds benefit Heartland Canines for Veterans.' ),
		B.heading( 'At the Farm Training (Oronogo)', 4 ),
		B.list( [
			'<strong>Basic Obedience – $60/hr</strong>: Sit, stay, come, leash manners, and focus',
			'<strong>Puppy Foundations – $50/hr</strong>: Confidence, crate intro, and early manners',
			'<strong>Advanced Focus &amp; Public Manners – $75/hr</strong>: For dogs preparing for therapy, service, or community settings',
		] ),
		B.heading( 'Travel-to-You Training', 4 ),
		B.list( [
			'Available within 25 miles of Oronogo',
			'Add $25 travel fee per visit',
			'Beyond 25 miles: +$1 per mile',
		] ),
		B.heading( 'Multi-Session Packages', 4 ),
		B.list( [
			'3-Session Package (Farm): $150 donation total',
			'3-Session Package (Travel): $200 donation total',
			'Includes personalized progress tracking and a ‘Heartland Graduate’ certificate',
		] ),
		B.file( 'live:media:3745', 'Download the price sheet (Heartland Obedience Training, .docx)', 'HEARTLAND-OBEDIENCE-TRAINING.docx' ),
	];
}

/**
 * Curation per page id: which blocks of the full conversion end up in the record's content file.
 * `attach:false` pages are decomposed into records / sections (content is kept only under source/ for traceability).
 * Every rule that removes or changes live text is mirrored in docs/migration-map.md.
 */
export const PAGE_RULES = {
	16: { attach: true, dropTitleHeading: true, drop: [ headingIs( 'Still think a Service Dog is for you? Click the button below to apply.' ), buttonLabelled( 'Submit an Application' ) ] },
	1989: { attach: true, drop: [] },
	1995: {
		attach: true,
		drop: [
			// the 4 DOJ Q/A items move into the landing `faq` section
			( b ) => b.type === 'heading' && /^question:/i.test( b.text ),
			textStarts( 'Under the ADA, a service animal is defined' ),
			textStarts( 'The dog must be trained to take a specific action' ),
			textStarts( 'No. These terms are used to describe animals' ),
			textStarts( 'No. A service animal may not be excluded' ),
			( b ) => b.type === 'separator',
		],
		replaceHref: [ [ 'https://archive.ada.gov/', 'https://www.ada.gov/resources/service-animals-faqs/' ] ],
	},
	3: { attach: true },
	2120: { attach: false },
	2072: { attach: false },
	2124: { attach: true, drop: [ buttonLabelled( 'Contact Us' ) ] },
	2455: { attach: true, keep: [ ( b ) => b.type === 'gallery' ] },
	2483: { attach: false },
	2505: {
		attach: true,
		dropTitleHeading: true,
		replaceText: [
			[ 'Mail to: Heartland Canines for Veterans<br>PO Box 101<br>Saginaw, MO 64864', 'Mail to: Heartland Canines for Veterans<br>12651 Gateway Dr<br>Neosho, MO 64850' ],
			[ 'Click The Image to Print or Download the Medical History Form', 'Print or Download the Medical History Form' ],
		],
	},
	2800: { attach: false },
	2856: { attach: false },
	3011: { attach: false },
	3496: { attach: false },
	3084: { attach: true, drop: [ textStarts( 'Does your K9 meet the requirements listed above?' ), buttonLabelled( 'Contact Us' ) ] },
	3093: { attach: true, replaceText: [ [ 'guidlines', 'guidelines' ] ] },
	3408: {
		attach: true,
		replaceText: [ [ 'spread the world about HK9', 'spread the word about HK9' ] ],
		drop: [
			textStarts( 'Thank you for helping HK9! To order, simply click the button below! Your donation will go through paypal' ),
			textStarts( 'Not local to the 4 state area?' ),
			buttonLabelled( 'BROWSE NOW' ),
		],
	},
	3627: {
		attach: true,
		mergeImageRuns: 3,
		drop: [
			// sponsor tiers move into the landing `tiers` section; CTA buttons into `cta`
			textStarts( 'Hk9 Educational Coloring Book Sponsor Opportunities' ),
			textStarts( 'This educational coloring book will be featured on a major book review' ),
			textStarts( 'Tier Price Impact Sponsor Benefits' ),
			textStarts( 'Bronze $500' ), textStarts( 'Silver $1,000' ), textStarts( 'Gold $4,000' ), textStarts( 'Platinum $10,000' ),
			textStarts( 'PAGE PARTNER' ), textStarts( 'FEATURED PAGE SPONSOR' ), textStarts( 'LEGACY PAGE SPONSOR' ),
			textStarts( 'Classroom Sponsorship' ), textStarts( 'Help us educate an entire classroom!' ),
			buttonLabelled( "Sign us up! Let's change a life!" ), buttonLabelled( 'Sponsor a class!' ),
		],
	},
	3742: {
		attach: true,
		drop: [ textStarts( 'Interested in having your dog trained?' ), buttonLabelled( 'Contact Us' ), ( b ) => b.type === 'file' && b.key === 'live:media:3745' ],
		insertAfter: [ [ textStarts( 'Feel free to download our price sheet' ), obediencePriceSheetBlocks ] ],
	},
	2491: { attach: true },
	2492: { attach: true },
};

export function curate( converted, rules ) {
	let blocks = [ ...converted.blocks ];
	const notes = [];
	if ( rules.keep ) {
		blocks = blocks.filter( ( b ) => rules.keep.some( ( fn ) => fn( b ) ) );
	}
	if ( rules.dropTitleHeading ) {
		const idx = blocks.findIndex( ( b ) => b.type === 'heading' && norm( b.text ).replace( /!$/, '' ) === norm( converted.title ).replace( /!$/, '' ) );
		if ( idx >= 0 ) {
			notes.push( `dropped heading duplicating the page title: "${ blocks[ idx ].text }"` );
			blocks.splice( idx, 1 );
		}
	}
	for ( const fn of rules.drop || [] ) {
		const before = blocks.length;
		blocks = blocks.filter( ( b ) => ! fn( b ) );
		if ( blocks.length === before ) {
			notes.push( 'WARNING: a drop rule matched nothing' );
		}
	}
	if ( rules.mergeImageRuns ) {
		const merged = [];
		for ( let i = 0; i < blocks.length; i++ ) {
			const run = [];
			while ( i < blocks.length && blocks[ i ].type === 'image' && blocks[ i ].key && ! blocks[ i ].link ) {
				run.push( blocks[ i ] );
				i++;
			}
			if ( run.length >= rules.mergeImageRuns ) {
				merged.push( B.gallery( run.map( ( im ) => ( { key: im.key, alt: im.alt, caption: '' } ) ), { columns: Math.min( 3, run.length ), linkTo: 'none' } ) );
				notes.push( `merged ${ run.length } consecutive images into a gallery` );
			} else {
				merged.push( ...run );
			}
			if ( i < blocks.length ) {
				merged.push( blocks[ i ] );
			}
		}
		blocks = merged;
	}
	// no leading/trailing separators
	while ( blocks.length && blocks[ 0 ].type === 'separator' ) blocks.shift();
	while ( blocks.length && blocks.at( -1 ).type === 'separator' ) blocks.pop();
	for ( const [ pred, factory ] of rules.insertAfter || [] ) {
		const idx = blocks.findIndex( pred );
		if ( idx < 0 ) {
			notes.push( 'WARNING: insertAfter anchor not found' );
			continue;
		}
		blocks.splice( idx + 1, 0, ...factory() );
	}
	let html = blocksToHtml( blocks );
	for ( const [ from, to ] of rules.replaceText || [] ) {
		if ( ! html.includes( from ) ) {
			notes.push( `WARNING: replaceText source not found: ${ from.slice( 0, 50 ) }` );
		}
		html = html.split( from ).join( to );
	}
	for ( const [ from, to ] of rules.replaceHref || [] ) {
		const needle = `href="${ escAttr( from ) }"`;
		if ( ! html.includes( needle ) ) {
			notes.push( `WARNING: replaceHref source not found: ${ from }` );
		}
		html = html.split( needle ).join( `href="${ escAttr( to ) }"` );
	}
	return { html, blocks, notes };
}

/* ------------------------------------------------------------------------- */
/* CLI                                                                        */
/* ------------------------------------------------------------------------- */

function parseArgs( argv ) {
	const args = { ids: [], all: false, fetch: false, stdout: false, pages: '', out: path.join( ROOT, 'payload-src' ) };
	for ( let i = 0; i < argv.length; i++ ) {
		const a = argv[ i ];
		if ( a === '--all' ) args.all = true;
		else if ( a === '--fetch' ) args.fetch = true;
		else if ( a === '--stdout' ) args.stdout = true;
		else if ( a === '--id' ) args.ids.push( parseInt( argv[ ++i ], 10 ) );
		else if ( a === '--pages' ) args.pages = argv[ ++i ];
		else if ( a === '--out' ) args.out = argv[ ++i ];
		else if ( a === '--help' || a === '-h' ) {
			console.log( fs.readFileSync( fileURLToPath( import.meta.url ), 'utf8' ).split( '*/' )[ 0 ] );
			process.exit( 0 );
		}
	}
	return args;
}

async function loadPages( args ) {
	const cache = path.join( args.out, 'source', 'live-pages.json' );
	if ( args.pages ) {
		return JSON.parse( fs.readFileSync( args.pages, 'utf8' ) );
	}
	if ( ! args.fetch && fs.existsSync( cache ) ) {
		return JSON.parse( fs.readFileSync( cache, 'utf8' ) );
	}
	console.error( `Fetching ${ LIVE_REST } …` );
	const res = await fetch( LIVE_REST, { headers: { 'user-agent': 'hk9-fusion-to-blocks/1.0' } } );
	if ( ! res.ok ) {
		throw new Error( `live fetch failed: HTTP ${ res.status }` );
	}
	const pages = await res.json();
	fs.mkdirSync( path.dirname( cache ), { recursive: true } );
	fs.writeFileSync( cache, JSON.stringify( pages, null, 1 ) );
	console.error( `cached ${ pages.length } pages → ${ path.relative( ROOT, cache ) }` );
	return pages;
}

async function main() {
	const args = parseArgs( process.argv.slice( 2 ) );
	const ctx = loadContext();
	const pages = await loadPages( args );
	const scope = args.ids.length ? pages.filter( ( p ) => args.ids.includes( p.id ) ) : args.all ? pages : pages.filter( ( p ) => PAGE_RULES[ p.id ] );
	if ( ! scope.length ) {
		throw new Error( 'no pages selected' );
	}
	const srcDir = path.join( args.out, 'source' );
	const contentDir = path.join( args.out, 'content' );
	fs.mkdirSync( srcDir, { recursive: true } );
	fs.mkdirSync( contentDir, { recursive: true } );

	const summary = {};
	for ( const page of scope.sort( ( a, b ) => a.id - b.id ) ) {
		const conv = convertPage( page, ctx );
		const rules = PAGE_RULES[ page.id ] || { attach: false };
		const cur = curate( conv, rules );
		const full = blocksToHtml( conv.blocks );
		if ( args.stdout ) {
			process.stdout.write( `\n===== ${ page.id } /${ page.slug }/ (${ conv.title }) — ${ rules.attach ? 'curated content' : 'full conversion (not attached)' }\n` );
			process.stdout.write( rules.attach ? cur.html : full );
			continue;
		}
		fs.writeFileSync( path.join( srcDir, `live__page__${ page.id }.html` ), page.content?.rendered ?? '' );
		fs.writeFileSync( path.join( srcDir, `live__page__${ page.id }.blocks.html` ), full );
		if ( rules.attach ) {
			fs.writeFileSync( path.join( contentDir, `live__page__${ page.id }.html` ), cur.html );
		}
		summary[ page.id ] = {
			slug: page.slug,
			title: conv.title,
			attached: !! rules.attach,
			blocks: conv.blocks.length,
			curatedBlocks: rules.attach ? cur.blocks.length : 0,
			types: conv.blocks.reduce( ( acc, b ) => ( ( acc[ b.type ] = ( acc[ b.type ] || 0 ) + 1 ), acc ), {} ),
			unknownMedia: [ ...new Set( conv.report.unknownMedia ) ],
			unknownLinks: [ ...new Set( conv.report.unknownLinks ) ],
			shortcodesRemoved: conv.report.shortcodesRemoved,
			unknownWrappers: conv.report.unknownWrappers,
			notes: [ ...conv.report.notes, ...cur.notes ],
		};
	}
	if ( ! args.stdout ) {
		const reportPath = path.join( srcDir, 'fusion-to-blocks-report.json' );
		fs.writeFileSync( reportPath, JSON.stringify( summary, null, 1 ) );
		let warn = 0;
		for ( const [ id, s ] of Object.entries( summary ) ) {
			const flags = [];
			if ( s.unknownMedia.length ) flags.push( `unknown media ${ s.unknownMedia.length }` );
			if ( s.unknownLinks.length ) flags.push( `unknown links ${ s.unknownLinks.length }` );
			if ( s.shortcodesRemoved.length ) flags.push( `shortcodes removed ${ s.shortcodesRemoved.length }` );
			if ( s.notes.some( ( n ) => n.startsWith( 'WARNING' ) ) ) flags.push( 'WARNINGS' );
			warn += flags.length;
			console.log( `${ String( id ).padStart( 5 ) }  ${ s.slug.padEnd( 38 ) } ${ String( s.blocks ).padStart( 3 ) } blocks${ s.attached ? ` → content (${ s.curatedBlocks })` : '           ' }  ${ flags.join( ', ' ) }` );
		}
		console.log( `report → ${ path.relative( ROOT, reportPath ) }${ warn ? ` (${ warn } flagged)` : '' }` );
	}
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	main().catch( ( e ) => {
		console.error( e );
		process.exit( 1 );
	} );
}
