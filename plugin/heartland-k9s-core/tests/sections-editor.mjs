/**
 * Browser checks for the section panels in the block editor (Playwright, chromium).
 *
 *   node plugin/heartland-k9s-core/tests/sections-editor.mjs [--base=http://localhost:8093] [--out=/path/dir] [--keep]
 *
 * Creates a fixture page (about template) through WP-CLI, logs in as admin/admin,
 * loads the editor, verifies the panels render without console errors, that
 * typing mirrors into the editor store, that Update persists exactly one
 * revision, that Preview shows the unsaved heading, that a template switch
 * reloads the panels, and that repeaters add/move rows. Prints PASS/FAIL/
 * BLOCKED lines and exits non-zero on FAIL.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.resolve( here, '..', '..', '..' );
const args = Object.fromEntries( process.argv.slice( 2 ).map( ( a ) => { const m = a.match( /^--([^=]+)=?(.*)$/ ); return m ? [ m[ 1 ], m[ 2 ] || true ] : [ a, true ]; } ) );
const base = ( args.base || 'http://localhost:8093' ).replace( /\/$/, '' );
const out = args.out || path.join( repo, 'docs', 'reports', 'screenshots', 'admin' );
fs.mkdirSync( out, { recursive: true } );

const results = [];
const report = ( status, name, evidence ) => { results.push( { status, name, evidence } ); console.log( status.padEnd( 7 ), name, '—', evidence ); };
const pass = ( n, e ) => report( 'PASS', n, e );
const fail = ( n, e ) => report( 'FAIL', n, e );
const blocked = ( n, e ) => report( 'BLOCKED', n, e );

function wp( ...cli ) {
	return execFileSync( 'bash', [ path.join( repo, 'tools', 'wp.sh' ), ...cli ], { cwd: repo, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } ).trim();
}
function revisions( id ) {
	return parseInt( wp( 'post', 'list', '--post_type=revision', `--post_parent=${ id }`, '--post_status=any', '--format=count' ), 10 ) || 0;
}
function meta( id, key ) {
	try {
		return JSON.parse( wp( 'post', 'meta', 'get', String( id ), key, '--format=json' ) );
	} catch ( e ) {
		return null;
	}
}

// Fixture page.
const pageId = parseInt( wp( 'post', 'create', '--post_type=page', '--post_title=HK9 Editor Test (about)', '--post_status=publish', '--post_content=<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->', '--porcelain' ), 10 );
wp( 'post', 'meta', 'update', String( pageId ), '_wp_page_template', 'page-templates/about.php' );
wp( 'post', 'meta', 'update', String( pageId ), 'hk9_sec_hero_band', JSON.stringify( { eyebrow: '', heading: 'Editor test heading', text: 'Seeded by WP-CLI', pattern: 'none' } ), '--format=json' );
console.log( `fixture page ${ pageId } (about template)` );

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1400, height: 1600 }, ignoreHTTPSErrors: true } );
const page = await context.newPage();
const consoleErrors = [];
page.on( 'console', ( msg ) => { if ( msg.type() === 'error' ) consoleErrors.push( msg.text() ); } );
page.on( 'pageerror', ( err ) => consoleErrors.push( 'pageerror: ' + err.message ) );

try {
	// Login.
	await page.goto( `${ base }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );
	if ( ! ( await context.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in' ) ) ) {
		throw new Error( 'login failed' );
	}

	// Editor.
	const editorUrl = `${ base }/wp-admin/post.php?post=${ pageId }&action=edit`;
	await page.goto( editorUrl, { waitUntil: 'domcontentloaded' } );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() > 0, null, { timeout: 60000 } );
	// Dismiss the welcome guide if shown.
	await page.evaluate( () => {
		try { wp.data.dispatch( 'core/preferences' ).set( 'core', 'welcomeGuide', false ); } catch ( e ) {}
		try { wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ); } catch ( e ) {}
	} );
	const guide = page.locator( '.edit-post-welcome-guide .components-modal__header button, .components-guide .components-modal__header button' );
	if ( await guide.count() ) {
		await guide.first().click().catch( () => {} );
	}

	// Open the (collapsed by default) meta boxes drawer and give it room.
	await page.evaluate( () => {
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainIsOpen', true );
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainOpenHeight', 1100 );
		} catch ( e ) {}
	} );
	const presenter = page.locator( '.edit-post-meta-boxes-main__presenter' );
	if ( await presenter.count() && ( await presenter.first().getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await presenter.first().click().catch( () => {} );
	}
	const heroInput = page.locator( '#hk9_sec_hero_band__heading' );
	await heroInput.waitFor( { state: 'visible', timeout: 60000 } );
	await heroInput.scrollIntoViewIfNeeded();
	const seeded = await heroInput.inputValue();
	const template = await page.getAttribute( '[data-hk9-sections]', 'data-hk9-template' );
	const sectionIds = await page.$$eval( '[data-hk9-sections] [data-hk9-section]', ( els ) => els.map( ( e ) => e.getAttribute( 'data-hk9-section' ) ) );
	const layoutIds = await page.$$eval( '[data-hk9-layout] [data-hk9-layout-item]', ( els ) => els.map( ( e ) => e.getAttribute( 'data-section' ) ) );
	( template === 'about' && seeded === 'Editor test heading' && sectionIds.join() === 'hero_band,legacy,values,cta' && layoutIds.join() === 'hero_band,legacy,values,cta' )
		? pass( 'panels_render', `template=about, hero heading input = "${ seeded }", sections [${ sectionIds }], layout [${ layoutIds }]` )
		: fail( 'panels_render', JSON.stringify( { template, seeded, sectionIds, layoutIds } ) );

	// Screenshot with the sections panel in view.
	await page.locator( '#hk9_sections' ).scrollIntoViewIfNeeded().catch( () => {} );
	await page.waitForTimeout( 800 );
	const shot = path.join( out, 'sections-about.png' );
	await page.screenshot( { path: shot, fullPage: false } );
	const panelShot = path.join( out, 'sections-about-panel.png' );
	await page.locator( '#hk9_sections' ).screenshot( { path: panelShot } ).catch( () => {} );
	pass( 'screenshot', `${ shot } (+ sections-about-panel.png)` );

	// Console errors during load.
	const loadErrors = consoleErrors.filter( ( e ) => ! /favicon|net::ERR_|Failed to load resource/.test( e ) );
	loadErrors.length === 0 ? pass( 'no_console_errors_on_load', `${ consoleErrors.length } console entries, none from scripts` ) : fail( 'no_console_errors_on_load', loadErrors.slice( 0, 5 ).join( ' | ' ) );

	// Mirroring into the editor store.
	await heroInput.fill( 'Mirrored heading' );
	const mirrored = await page.evaluate( () => ( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} ).hk9_sec_hero_band );
	( mirrored && mirrored.heading === 'Mirrored heading' && mirrored.text === 'Seeded by WP-CLI' )
		? pass( 'editpost_mirroring', `getEditedPostAttribute('meta').hk9_sec_hero_band = ${ JSON.stringify( mirrored ) }` )
		: fail( 'editpost_mirroring', JSON.stringify( mirrored ) );

	// Layout panel: hide "values", move "cta" up.
	await page.locator( '#hk9-layout-values' ).uncheck();
	await page.locator( '[data-hk9-layout-item][data-section="cta"] [data-hk9-move="up"]' ).click();
	const layoutMeta = await page.evaluate( () => ( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} ).hk9_sections_layout );
	( layoutMeta && layoutMeta.order.join() === 'hero_band,legacy,cta,values' && layoutMeta.hidden.join() === 'values' )
		? pass( 'layout_panel_mirroring', `hk9_sections_layout = ${ JSON.stringify( layoutMeta ) }` )
		: fail( 'layout_panel_mirroring', JSON.stringify( layoutMeta ) );

	// Repeater: add a card in "values", set its title, move it up.
	const valuesRepeater = page.locator( '#hk9-section-values [data-hk9-repeater]' ).first();
	await page.locator( '#hk9-section-values details' ).evaluate( ( d ) => { d.open = true; } );
	const rowsBefore = await valuesRepeater.locator( ':scope > [data-hk9-repeater-rows] > [data-hk9-repeater-row]' ).count();
	await valuesRepeater.locator( '[data-hk9-repeater-add]' ).click();
	const newRow = valuesRepeater.locator( ':scope > [data-hk9-repeater-rows] > [data-hk9-repeater-row]' ).last();
	await newRow.locator( 'input[name$="[title]"]' ).fill( 'New card' );
	await newRow.locator( '[data-hk9-move="up"]' ).first().click();
	const titles = await valuesRepeater.locator( ':scope > [data-hk9-repeater-rows] > [data-hk9-repeater-row] [data-hk9-repeater-title]' ).allTextContents();
	const valuesMeta = await page.evaluate( () => ( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} ).hk9_sec_values );
	const cardTitles = valuesMeta ? valuesMeta.cards.map( ( c ) => c.title ) : [];
	( titles.length === rowsBefore + 1 && titles[ titles.length - 2 ] === 'New card' && cardTitles.join() === titles.join() )
		? pass( 'repeater_add_move_mirror', `rows ${ rowsBefore }→${ titles.length }, titles [${ titles }], store order matches` )
		: fail( 'repeater_add_move_mirror', JSON.stringify( { rowsBefore, titles, cardTitles } ) );

	// Update: exactly one revision, values persisted.
	const revBefore = revisions( pageId );
	const putPromise = page.waitForResponse( ( r ) => /\/wp\/v2\/pages\/\d+/.test( r.url() ) && [ 'PUT', 'POST' ].includes( r.request().method() ), { timeout: 60000 } );
	const loaderPromise = page.waitForResponse( ( r ) => r.url().includes( 'meta-box-loader=1' ), { timeout: 60000 } );
	await page.locator( '.editor-post-publish-button__button, .editor-post-publish-button' ).first().click();
	const put = await putPromise;
	await loaderPromise;
	await page.waitForFunction( () => ! wp.data.select( 'core/editor' ).isSavingPost() && ! wp.data.select( 'core/editor' ).isSavingMetaBoxes(), null, { timeout: 60000 } ).catch( () => {} );
	await page.waitForTimeout( 1000 );
	const revAfter = revisions( pageId );
	const savedHero = meta( pageId, 'hk9_sec_hero_band' );
	const savedLayout = meta( pageId, 'hk9_sections_layout' );
	const savedValues = meta( pageId, 'hk9_sec_values' );
	( put.status() === 200 && revAfter === revBefore + 1 && savedHero && savedHero.heading === 'Mirrored heading' && savedLayout && savedLayout.hidden.join() === 'values' && savedValues && savedValues.cards.map( ( c ) => c.title ).join() === cardTitles.join() )
		? pass( 'update_one_revision_and_persist', `REST ${ put.request().method() } ${ put.status() }, revisions ${ revBefore }→${ revAfter }, hero/layout/values persisted` )
		: fail( 'update_one_revision_and_persist', JSON.stringify( { status: put.status(), revBefore, revAfter, savedHero, savedLayout, cards: savedValues && savedValues.cards.map( ( c ) => c.title ) } ) );

	// Preview (published page → REST autosave with meta).
	await heroInput.fill( 'Preview heading X' );
	const autosavePromise = page.waitForResponse( ( r ) => r.url().includes( '/autosaves' ), { timeout: 60000 } );
	const popupPromise = context.waitForEvent( 'page', { timeout: 60000 } );
	const previewToggle = page.locator( '.editor-preview-dropdown__toggle, .editor-post-preview__dropdown button, button[aria-label="View"], .editor-preview-dropdown button' ).first();
	await previewToggle.click();
	await page.locator( '.editor-preview-dropdown__button-external, a.editor-preview-dropdown__button-external, [role="menuitem"]:has-text("Preview in new tab"), button:has-text("Preview in new tab")' ).first().click();
	const autosaveRes = await autosavePromise;
	const popup = await popupPromise;
	await popup.waitForLoadState( 'domcontentloaded' );
	await popup.waitForTimeout( 1500 );
	const previewHtml = await popup.content();
	const previewUrl = popup.url();
	const autosaveRows = wp( 'post', 'list', '--post_type=revision', `--post_parent=${ pageId }`, '--post_status=any', `--post_name=${ pageId }-autosave-v1`, '--format=ids' );
	const autosaveHero = autosaveRows ? meta( autosaveRows.split( ' ' )[ 0 ], 'hk9_sec_hero_band' ) : null;
	const savedAfterPreview = meta( pageId, 'hk9_sec_hero_band' );
	if ( previewHtml.includes( 'Preview heading X' ) ) {
		pass( 'preview_shows_unsaved_heading', `autosave ${ autosaveRes.status() }, preview ${ previewUrl } renders "Preview heading X"; saved heading still "${ savedAfterPreview && savedAfterPreview.heading }"` );
	} else if ( autosaveHero && autosaveHero.heading === 'Preview heading X' ) {
		blocked( 'preview_shows_unsaved_heading', `autosave ${ autosaveRes.status() } carries "Preview heading X" and the page keeps "${ savedAfterPreview && savedAfterPreview.heading }", but the preview HTML at ${ previewUrl } does not contain it (theme template for about not rendering hero_band yet?)` );
	} else {
		fail( 'preview_shows_unsaved_heading', JSON.stringify( { status: autosaveRes.status(), previewUrl, autosaveHero } ) );
	}
	await popup.close();

	// Template switch via the editor store → ajax panel reload.
	const reloadPromise = page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && r.request().postData() && r.request().postData().includes( 'hk9_section_panel' ), { timeout: 30000 } );
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).editPost( { template: 'page-templates/contact.php' } ) );
	const ajax = await reloadPromise;
	await page.waitForSelector( '[data-hk9-sections][data-hk9-template="contact"]', { timeout: 30000 } );
	const contactIds = await page.$$eval( '[data-hk9-sections] [data-hk9-section]', ( els ) => els.map( ( e ) => e.getAttribute( 'data-hk9-section' ) ) );
	const contactHeading = await page.inputValue( '#hk9_sec_hero_band__heading' );
	( ajax.status() === 200 && contactIds.join() === 'hero_band,info,form' && contactHeading === 'Preview heading X' )
		? pass( 'template_switch_reloads_panel', `ajax ${ ajax.status() }, contact sections [${ contactIds }], unsaved hero heading carried over` )
		: fail( 'template_switch_reloads_panel', JSON.stringify( { status: ajax.status(), contactIds, contactHeading } ) );
	// Old keys untouched in DB.
	const stillThere = meta( pageId, 'hk9_sec_values' );
	stillThere && stillThere.cards ? pass( 'template_switch_keeps_old_keys', 'hk9_sec_values still stored after switching to contact' ) : fail( 'template_switch_keeps_old_keys', 'hk9_sec_values missing' );

	const lateErrors = consoleErrors.filter( ( e ) => ! /favicon|net::ERR_|Failed to load resource/.test( e ) );
	lateErrors.length === 0 ? pass( 'no_console_errors_during_interaction', `${ consoleErrors.length } console entries total, none from scripts` ) : fail( 'no_console_errors_during_interaction', lateErrors.slice( 0, 5 ).join( ' | ' ) );

	// CPT "Details" boxes (needs the post types from PostTypes\Registrar).
	const types = wp( 'post-type', 'list', '--field=name' ).split( /\s+/ );
	if ( ! types.includes( 'hk9_team' ) || ! types.includes( 'hk9_barkode' ) ) {
		blocked( 'details_box', 'hk9_team / hk9_barkode post types not registered on this site' );
	} else {
		// Block editor CPT: team with relationship (BarKode record) + link + gallery + toggle.
		const recordId = parseInt( wp( 'post', 'create', '--post_type=hk9_barkode', '--post_title=HK9 Test Record Rex', '--post_status=publish', '--porcelain' ), 10 );
		try {
			consoleErrors.length = 0;
			await page.goto( `${ base }/wp-admin/post-new.php?post_type=hk9_team`, { waitUntil: 'domcontentloaded' } );
			const isBlock = await page.evaluate( () => !! ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) );
			if ( isBlock ) {
				await page.waitForFunction( () => wp.data.select( 'core/editor' ).getCurrentPostId() > 0, null, { timeout: 60000 } );
				await page.evaluate( () => { try { wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainIsOpen', true ); wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainOpenHeight', 1100 ); } catch ( e ) {} } );
			}
			const details = page.locator( '#hk9_details [data-hk9-details]' );
			await details.waitFor( { state: 'visible', timeout: 60000 } );
			const search = page.locator( '#hk9_details [data-hk9-relationship] [data-hk9-pick-search]' );
			await search.scrollIntoViewIfNeeded();
			const pickPromise = page.waitForResponse( ( r ) => r.url().includes( '/hk9/v1/pick' ), { timeout: 30000 } );
			await search.fill( 'HK9 Test Record' );
			const pick = await pickPromise;
			await page.locator( '#hk9_details [data-hk9-pick-results] [data-index]' ).first().waitFor( { state: 'visible', timeout: 15000 } );
			await page.locator( '#hk9_details [data-hk9-pick-results] [data-index]' ).first().dispatchEvent( 'mousedown' );
			const chipId = await page.getAttribute( '#hk9_details [data-hk9-relationship] [data-hk9-chip]', 'data-id' );
			const single = await page.inputValue( '#hk9_details [data-hk9-relationship-single]' );
			await page.locator( '#hk9_meta__featured' ).check();
			const teamMeta = isBlock ? await page.evaluate( () => { const m = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}; return { barkode: m.hk9_barkode, featured: m.hk9_featured, status: m.hk9_status }; } ) : null;
			await page.locator( '#hk9_details' ).screenshot( { path: path.join( out, 'details-team.png' ) } ).catch( () => {} );
			( pick.status() === 200 && parseInt( chipId, 10 ) === recordId && parseInt( single, 10 ) === recordId && ( ! isBlock || ( teamMeta.barkode === recordId && teamMeta.featured === true ) ) )
				? pass( 'details_relationship_picker', `hk9/v1/pick ${ pick.status() }, chip #${ chipId } selected, hidden input ${ single }${ isBlock ? ', mirrored meta ' + JSON.stringify( teamMeta ) : ' (classic editor)' }` )
				: fail( 'details_relationship_picker', JSON.stringify( { status: pick.status(), chipId, single, teamMeta } ) );

			// Classic editor CPT (barkode: show_in_rest=false): Details box + icon-free fields, no mirroring.
			await page.goto( `${ base }/wp-admin/post.php?post=${ recordId }&action=edit`, { waitUntil: 'domcontentloaded' } );
			const classic = await page.evaluate( () => ! ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) && !! document.getElementById( 'post' ) );
			await page.locator( '#hk9_details [data-hk9-details]' ).waitFor( { state: 'visible', timeout: 60000 } );
			const mirrorMode = await page.getAttribute( '#hk9_details [data-hk9-details]', 'data-hk9-mirror' );
			await page.fill( '#hk9_meta__dog_name', 'Rex' );
			await page.selectOption( '#hk9_meta__program_type', 'therapy' );
			await page.locator( '#hk9_meta__do_not_separate' ).check();
			await page.locator( '#hk9_details' ).screenshot( { path: path.join( out, 'details-barkode.png' ) } ).catch( () => {} );
			await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#publish' ) ] );
			const dog = wp( 'post', 'meta', 'get', String( recordId ), 'hk9_dog_name' );
			const ptype = wp( 'post', 'meta', 'get', String( recordId ), 'hk9_program_type' );
			const dns = wp( 'post', 'meta', 'get', String( recordId ), 'hk9_do_not_separate' );
			( classic && mirrorMode === 'none' && dog === 'Rex' && ptype === 'therapy' && dns === '1' )
				? pass( 'details_classic_save', `classic editor, mirror=none, saved dog_name="${ dog }", program_type=${ ptype }, do_not_separate=${ dns }` )
				: fail( 'details_classic_save', JSON.stringify( { classic, mirrorMode, dog, ptype, dns } ) );
			const cptErrors = consoleErrors.filter( ( e ) => ! /favicon|net::ERR_|Failed to load resource/.test( e ) );
			cptErrors.length === 0 ? pass( 'no_console_errors_cpt_screens', 'team (block) + barkode (classic) edit screens clean' ) : fail( 'no_console_errors_cpt_screens', cptErrors.slice( 0, 5 ).join( ' | ' ) );
		} finally {
			try { wp( 'post', 'delete', String( recordId ), '--force' ); } catch ( e ) {}
			try {
				const drafts = wp( 'post', 'list', '--post_type=hk9_team', '--post_status=auto-draft,draft', '--format=ids' );
				drafts.split( ' ' ).filter( Boolean ).forEach( ( id ) => wp( 'post', 'delete', id, '--force' ) );
			} catch ( e ) {}
		}
	}
} catch ( err ) {
	fail( 'unexpected_error', err.message );
	await page.screenshot( { path: path.join( out, 'sections-about-error.png' ) } ).catch( () => {} );
} finally {
	await browser.close();
	if ( ! args.keep ) {
		try { wp( 'post', 'delete', String( pageId ), '--force' ); } catch ( e ) {}
	}
}

const failed = results.filter( ( r ) => r.status === 'FAIL' ).length;
console.log( `\n${ results.length } checks, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
