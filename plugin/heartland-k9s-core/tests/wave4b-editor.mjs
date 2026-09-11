#!/usr/bin/env node
/**
 * Wave 4b browser checks (chromium, logs in as admin/admin):
 *   1. Home → block editor → Features repeater icon field: the preview <use> resolves the
 *      theme sprite symbol (#hk9-icon-<name>) and paints a non-empty box (screenshot).
 *   2. Heartland → Settings → Header: the destination field posts a label input; saving the
 *      tab keeps header.cta_link.label (read back with WP-CLI, compared with the value before).
 *   3. New Person (classic editor) / 4. New Team (block editor): post-new.php opens the auto-draft
 *      with menu_order = max + 1 and the Order help text is shown with the Order field. The
 *      auto-drafts are deleted afterwards.
 *
 *   node plugin/heartland-k9s-core/tests/wave4b-editor.mjs [--base=http://localhost:8093] [--out=<dir>]
 *
 * Exits non-zero on any FAIL. Never changes site content: the Header tab is saved with the
 * values it already holds.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const args = Object.fromEntries( process.argv.slice( 2 ).map( ( a ) => { const [ k, ...v ] = a.replace( /^--/, '' ).split( '=' ); return [ k, v.length ? v.join( '=' ) : true ]; } ) );
const base = String( args.base || 'http://localhost:8093' ).replace( /\/$/, '' );
const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../../..' );
const out = String( args.out || path.join( root, 'docs/reports/screenshots/wave4b' ) );
fs.mkdirSync( out, { recursive: true } );

const results = [];
const pass = ( n, e ) => { results.push( [ n, 'PASS', e ] ); console.log( `PASS    ${ n } — ${ e }` ); };
const fail = ( n, e ) => { results.push( [ n, 'FAIL', e ] ); console.log( `FAIL    ${ n } — ${ e }` ); };
const wp = ( ...a ) => execFileSync( path.join( root, 'tools/wp.sh' ), a, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } ).trim();
const settings = () => JSON.parse( wp( 'option', 'get', 'hk9_settings', '--format=json' ) );

const before = settings();
const homeId = parseInt( wp( 'option', 'get', 'page_on_front' ), 10 );
const autoDraftsBefore = wp( 'post', 'list', '--post_type=hk9_person', '--post_status=auto-draft', '--field=ID' ).split( /\s+/ ).filter( Boolean );

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
const page = await context.newPage();
const consoleErrors = [];
page.on( 'pageerror', ( err ) => consoleErrors.push( 'pageerror: ' + err.message ) );
page.on( 'dialog', ( d ) => d.accept().catch( () => {} ) ); // "leave page?" prompts: nothing is ever saved from an editor here.

async function openEditor( url ) {
	await page.goto( url, { waitUntil: 'domcontentloaded' } );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() > 0, null, { timeout: 60000 } );
	await page.evaluate( () => {
		try { wp.data.dispatch( 'core/preferences' ).set( 'core', 'welcomeGuide', false ); } catch ( e ) {}
		try { wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ); } catch ( e ) {}
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainIsOpen', true );
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainOpenHeight', 1000 );
		} catch ( e ) {}
	} );
	const guide = page.locator( '.edit-post-welcome-guide .components-modal__header button, .components-guide .components-modal__header button' );
	if ( await guide.count() ) {
		await guide.first().click().catch( () => {} );
	}
	const presenter = page.locator( '.edit-post-meta-boxes-main__presenter' );
	if ( await presenter.count() && ( await presenter.first().getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await presenter.first().click().catch( () => {} );
	}
}

try {
	await page.goto( `${ base }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );
	if ( ! ( await context.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in' ) ) ) {
		throw new Error( 'login failed' );
	}

	/* 1. Icon preview in the Home features repeater. */
	await openEditor( `${ base }/wp-admin/post.php?post=${ homeId }&action=edit` );
	const icon = page.locator( '[data-hk9-section="features"] .hk9-field--icon' ).first();
	await icon.waitFor( { state: 'visible', timeout: 60000 } );
	await icon.scrollIntoViewIfNeeded();
	await page.waitForTimeout( 600 );
	const info = await icon.evaluate( ( el ) => {
		const use = el.querySelector( '[data-hk9-icon-preview] use' );
		const select = el.querySelector( 'select' );
		const box = use ? use.getBoundingClientRect() : null;
		return { value: select ? select.value : null, href: use ? use.getAttribute( 'href' ) : null, w: box ? Math.round( box.width ) : 0, h: box ? Math.round( box.height ) : 0 };
	} );
	await icon.screenshot( { path: path.join( out, 'home-features-icon-field.png' ) } );
	const ok = info.href && info.href.includes( `#hk9-icon-${ info.value }` ) && info.w > 0 && info.h > 0;
	ok ? pass( 'icon_preview_renders', `select=${ info.value }, use href=…${ info.href.slice( info.href.indexOf( '#' ) ) }, painted ${ info.w }×${ info.h }px (home-features-icon-field.png)` )
		: fail( 'icon_preview_renders', JSON.stringify( info ) );
	// Changing the select swaps the preview live.
	const select = icon.locator( 'select' );
	const original = await select.inputValue();
	const other = await select.evaluate( ( s, cur ) => Array.from( s.options ).map( ( o ) => o.value ).find( ( v ) => v && v !== cur ), original );
	if ( other ) {
		await select.selectOption( other );
		await page.waitForTimeout( 200 );
		const swapped = await icon.evaluate( ( el ) => { const u = el.querySelector( '[data-hk9-icon-preview] use' ); const b = u ? u.getBoundingClientRect() : null; return { href: u ? u.getAttribute( 'href' ) : null, w: b ? Math.round( b.width ) : 0 }; } );
		await icon.screenshot( { path: path.join( out, 'home-features-icon-field-swapped.png' ) } );
		await select.selectOption( original );
		( swapped.href && swapped.href.endsWith( `#hk9-icon-${ other }` ) && swapped.w > 0 )
			? pass( 'icon_preview_follows_select', `→ ${ other }: href …${ swapped.href.slice( swapped.href.indexOf( '#' ) ) }, ${ swapped.w }px wide; reverted to ${ original } (not saved)` )
			: fail( 'icon_preview_follows_select', JSON.stringify( swapped ) );
	}
	// Leave without saving (the beforeunload prompt is auto-accepted).

	/* 2. Settings → Header keeps cta_link.label. */
	await page.goto( `${ base }/wp-admin/admin.php?page=hk9-settings&tab=header`, { waitUntil: 'domcontentloaded' } );
	const labelInput = page.locator( 'input[name="hk9_settings[header][cta_link][label]"]' );
	await labelInput.waitFor( { state: 'visible', timeout: 30000 } );
	const shownLabel = await labelInput.inputValue();
	const wantLabel = ( before.header && before.header.cta_link && before.header.cta_link.label ) || '';
	await page.locator( '.hk9-field--link' ).first().screenshot( { path: path.join( out, 'settings-header-destination.png' ) } );
	shownLabel === wantLabel ? pass( 'header_label_input_shows_saved_value', `label input = "${ shownLabel }"` ) : fail( 'header_label_input_shows_saved_value', `shown "${ shownLabel }", option "${ wantLabel }"` );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#submit' ) ] );
	const notice = await page.locator( '#setting-error-settings_updated, .notice-success' ).first().textContent().catch( () => '' );
	const after = settings();
	const keptLabel = ( after.header && after.header.cta_link && after.header.cta_link.label ) || '';
	keptLabel === wantLabel && keptLabel !== '' ? pass( 'header_save_keeps_cta_link_label', `header.cta_link.label = "${ keptLabel }" after save (${ ( notice || '' ).trim() })` ) : fail( 'header_save_keeps_cta_link_label', `before "${ wantLabel }", after "${ keptLabel }"` );
	JSON.stringify( after.header ) === JSON.stringify( before.header ) ? pass( 'header_group_unchanged_by_resave', 'header group byte-identical to the value before the save' ) : fail( 'header_group_unchanged_by_resave', JSON.stringify( { before: before.header, after: after.header } ) );
	const diffs = [];
	for ( const g of new Set( [ ...Object.keys( before ), ...Object.keys( after ) ] ) ) {
		for ( const k of new Set( [ ...Object.keys( before[ g ] || {} ), ...Object.keys( after[ g ] || {} ) ] ) ) {
			const a = JSON.stringify( ( before[ g ] || {} )[ k ] );
			const b = JSON.stringify( ( after[ g ] || {} )[ k ] );
			if ( a !== b ) {
				diffs.push( `${ g }.${ k }: ${ a } → ${ b }` );
			}
		}
	}
	diffs.length === 0 ? pass( 'settings_values_unchanged', 'every hk9_settings value identical before/after (key order ignored)' ) : fail( 'settings_values_unchanged', diffs.join( ' | ' ).slice( 0, 600 ) );

	/* 3. New Person (classic editor) → menu_order preset + help under the Order field. */
	const maxOrder = parseInt( wp( 'db', 'query', "SELECT COALESCE(MAX(menu_order),0) FROM wp_posts WHERE post_type='hk9_person' AND post_status<>'auto-draft'", '--skip-column-names' ), 10 );
	await page.goto( `${ base }/wp-admin/post-new.php?post_type=hk9_person`, { waitUntil: 'domcontentloaded' } );
	const orderInput = page.locator( '#menu_order' );
	await orderInput.waitFor( { state: 'visible', timeout: 30000 } );
	const newId = parseInt( await page.inputValue( '#post_ID' ), 10 );
	const order = parseInt( await orderInput.inputValue(), 10 );
	order === maxOrder + 1 ? pass( 'new_person_menu_order', `auto-draft #${ newId } opens with Order ${ order } (max existing ${ maxOrder })` ) : fail( 'new_person_menu_order', `Order ${ order }, expected ${ maxOrder + 1 }` );
	const help = page.locator( '#pageparentdiv #hk9-order-help' );
	( await help.count() ) ? pass( 'classic_order_help_shown', ( await help.textContent() ).trim().slice( 0, 90 ) + '…' ) : fail( 'classic_order_help_shown', 'no #hk9-order-help inside the Post Attributes box' );
	await page.locator( '#pageparentdiv' ).screenshot( { path: path.join( out, 'person-post-attributes-order.png' ) } ).catch( () => {} );
	await page.goto( `${ base }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
	if ( newId && ! autoDraftsBefore.includes( String( newId ) ) ) {
		wp( 'post', 'delete', String( newId ), '--force' );
		pass( 'auto_draft_removed', `deleted auto-draft #${ newId }` );
	}

	/* 4. New Team (block editor) → menu_order preset + help in the Summary panel. */
	const teamMax = parseInt( wp( 'db', 'query', "SELECT COALESCE(MAX(menu_order),0) FROM wp_posts WHERE post_type='hk9_team' AND post_status<>'auto-draft'", '--skip-column-names' ), 10 );
	const teamDraftsBefore = wp( 'post', 'list', '--post_type=hk9_team', '--post_status=auto-draft', '--field=ID' ).split( /\s+/ ).filter( Boolean );
	await openEditor( `${ base }/wp-admin/post-new.php?post_type=hk9_team` );
	const teamId = await page.evaluate( () => wp.data.select( 'core/editor' ).getCurrentPostId() );
	const teamOrder = await page.evaluate( () => wp.data.select( 'core/editor' ).getEditedPostAttribute( 'menu_order' ) );
	teamOrder === teamMax + 1 ? pass( 'new_team_menu_order', `auto-draft #${ teamId } loads with menu_order ${ teamOrder } (max existing ${ teamMax })` ) : fail( 'new_team_menu_order', `menu_order ${ teamOrder }, expected ${ teamMax + 1 }` );
	const blockHelp = page.locator( '#hk9-order-help' );
	await blockHelp.waitFor( { state: 'visible', timeout: 30000 } ).catch( () => {} );
	( await blockHelp.count() ) ? pass( 'block_editor_order_help_shown', ( await blockHelp.textContent() ).trim().slice( 0, 90 ) + '…' ) : fail( 'block_editor_order_help_shown', 'no #hk9-order-help in the block editor sidebar' );
	const orderField = page.locator( '.hk9-order-field input[type="number"]' );
	const shownOrder = ( await orderField.count() ) ? parseInt( await orderField.inputValue(), 10 ) : null;
	shownOrder === teamOrder ? pass( 'block_editor_order_field_shows_value', `Order field shows ${ shownOrder }` ) : fail( 'block_editor_order_field_shows_value', `field ${ shownOrder }, store ${ teamOrder }` );
	if ( await orderField.count() ) {
		await orderField.fill( String( teamOrder + 5 ) );
		await page.waitForTimeout( 300 );
		const edited = await page.evaluate( () => wp.data.select( 'core/editor' ).getEditedPostAttribute( 'menu_order' ) );
		edited === teamOrder + 5 ? pass( 'block_editor_order_field_edits_store', `typing ${ teamOrder + 5 } → editor store menu_order ${ edited } (not saved)` ) : fail( 'block_editor_order_field_edits_store', `store menu_order ${ edited }` );
	}
	const summary = page.locator( '.editor-post-summary, .editor-sidebar__panel' ).first();
	await summary.screenshot( { path: path.join( out, 'team-summary-order-help.png' ) } ).catch( () => page.screenshot( { path: path.join( out, 'team-summary-order-help.png' ) } ) );
	await page.goto( `${ base }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
	if ( teamId && ! teamDraftsBefore.includes( String( teamId ) ) ) {
		wp( 'post', 'delete', String( teamId ), '--force' );
		pass( 'team_auto_draft_removed', `deleted auto-draft #${ teamId }` );
	}

	consoleErrors.length === 0 ? pass( 'no_page_errors', 'no uncaught JS errors' ) : fail( 'no_page_errors', consoleErrors.slice( 0, 3 ).join( ' | ' ) );
} catch ( e ) {
	fail( 'run', e.message );
} finally {
	await browser.close();
}

const fails = results.filter( ( r ) => r[ 1 ] === 'FAIL' ).length;
console.log( `\n${ results.length } checks, ${ fails } failed (screenshots in ${ out })` );
process.exit( fails ? 1 : 0 );
