/**
 * Heartland -> Setup & Import: REST-driven progress client (no jQuery, no build).
 *
 * Reads window.HK9Import = { root, nonce, status, preflight, logBase, steps, i18n } and
 * drives POST hk9/v1/import/{start,step,pause,resume,retry,rollback,reset} +
 * GET hk9/v1/import/status. While a run is "running" the page keeps calling
 * /step; every response is the full status snapshot and re-renders the screen.
 */
( function () {
	'use strict';

	const cfg = window.HK9Import;
	if ( ! cfg ) {
		return;
	}

	const $ = ( sel, root ) => ( root || document ).querySelector( sel );
	const el = {
		notice: $( '#hk9-import-notice' ),
		payload: $( '#hk9-import-payload' ),
		path: $( '#hk9-import-path' ),
		overwrite: $( '#hk9-import-overwrite' ),
		adopt: $( '#hk9-import-adopt' ),
		preflight: $( '#hk9-import-preflight' ),
		next: $( '#hk9-import-next' ),
		nextPayload: $( '#hk9-import-next-payload' ),
		dry: $( '#hk9-import-dry' ),
		start: $( '#hk9-import-start' ),
		pause: $( '#hk9-import-pause' ),
		resume: $( '#hk9-import-resume' ),
		retry: $( '#hk9-import-retry' ),
		reset: $( '#hk9-import-reset' ),
		badge: $( '#hk9-import-status .hk9-import__badge' ),
		stepline: $( '#hk9-import-status .hk9-import__stepline' ),
		progress: $( '#hk9-import-status .hk9-import__progress' ),
		bar: $( '#hk9-import-status .hk9-import__bar' ),
		meta: $( '#hk9-import-status .hk9-import__meta' ),
		log: $( '#hk9-import-status .hk9-import__log a' ),
		counts: $( '#hk9-import-counts tbody' ),
		errors: $( '#hk9-import-errors' ),
		errorCount: $( '#hk9-import-error-count' ),
		warnings: $( '#hk9-import-warnings' ),
		runs: $( '#hk9-import-runs' ),
		map: $( '#hk9-import-map' ),
	};

	let nonce = cfg.nonce;
	let snapshot = cfg.status;
	let preflight = cfg.preflight || null;
	let busy = false;
	let stopped = false;
	let lastReport = '';
	let adoptTouched = false;

	const speak = ( msg ) => {
		if ( window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( msg );
		}
	};

	const sprintf = ( fmt, ...args ) => {
		let i = 0;
		return fmt.replace( /%(\d+\$)?[sd]/g, ( m, pos ) => {
			const idx = pos ? parseInt( pos, 10 ) - 1 : i++;
			return String( args[ idx ] ?? '' );
		} );
	};

	const esc = ( s ) => String( s ?? '' ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	/* ------------------------------------------------------------ REST */

	async function call( action, body, method, query ) {
		// Works with both pretty (/wp-json/...) and plain (?rest_route=...) REST roots.
		const url = cfg.root + action + ( query ? ( cfg.root.indexOf( '?' ) === -1 ? '?' : '&' ) + query : '' );
		const res = await fetch( url, {
			method: method || 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: method === 'GET' ? undefined : JSON.stringify( body || {} ),
		} );
		const fresh = res.headers.get( 'X-WP-Nonce' );
		if ( fresh ) {
			nonce = fresh;
		}
		let data = null;
		try {
			data = await res.json();
		} catch ( e ) {
			data = null;
		}
		if ( ! res.ok ) {
			const err = new Error( ( data && data.message ) || res.statusText || 'Request failed' );
			err.status = res.status;
			err.code = data && data.code;
			throw err;
		}
		return data;
	}

	function notice( kind, msg ) {
		if ( ! el.notice ) {
			return;
		}
		if ( ! msg ) {
			el.notice.hidden = true;
			return;
		}
		el.notice.className = 'notice notice-' + kind;
		el.notice.querySelector( 'p' ).textContent = msg;
		el.notice.hidden = false;
		speak( msg );
	}

	/* ---------------------------------------------------------- render */

	function render() {
		const s = snapshot.state;
		const status = s.status || 'idle';
		const stepIdx = ( s.step_index || 0 ) + 1;
		const total = cfg.steps.length;

		el.badge.dataset.status = status;
		el.badge.textContent = cfg.i18n[ status ] || status;

		if ( status === 'idle' ) {
			el.stepline.textContent = '';
			el.meta.textContent = '';
		} else {
			el.stepline.textContent = sprintf( cfg.i18n.stepOf, stepIdx, total, s.step ) + ' · ' + sprintf( cfg.i18n.records, s.cursor || 0, s.step_total || 0 );
			const mb = ( ( s.bytes_copied || 0 ) / 1048576 ).toFixed( 1 );
			el.meta.textContent = ( s.mode && s.mode.dry_run ? cfg.i18n.dryRun : cfg.i18n.import ) + ( s.mode && s.mode.adopt ? ' + ' + cfg.i18n.adopt : '' ) + ( s.mode && s.mode.overwrite ? ' + ' + cfg.i18n.overwrite : '' ) + ' · ' + cfg.i18n.run + ' ' + s.run_id + ' · ' + mb + ' MB' + ( snapshot.lock ? ' · ' + cfg.i18n.locked : '' );
		}

		// Progress: steps completed + fraction of the current step.
		let pct = 0;
		if ( status === 'done' ) {
			pct = 100;
		} else if ( status !== 'idle' ) {
			const frac = s.step_total ? Math.min( 1, ( s.cursor || 0 ) / s.step_total ) : 0;
			pct = Math.round( ( ( s.step_index || 0 ) + frac ) / total * 100 );
		}
		el.bar.style.width = pct + '%';
		el.progress.setAttribute( 'aria-valuenow', String( pct ) );
		el.progress.classList.toggle( 'is-done', status === 'done' );
		el.progress.classList.toggle( 'is-failed', status === 'failed' );

		if ( el.log ) {
			if ( s.run_id ) {
				el.log.href = cfg.logBase + '&run=' + encodeURIComponent( s.run_id );
				el.log.hidden = false;
			} else {
				el.log.hidden = true;
			}
		}

		// Counts.
		const rows = cfg.steps.map( ( step, i ) => {
			const c = ( s.counts && s.counts[ step ] ) || {};
			const cur = status !== 'idle' && i === ( s.step_index || 0 ) && status !== 'done' ? ' class="is-current"' : '';
			return '<tr' + cur + '><td>' + esc( step ) + '</td>' +
				'<td>' + ( c.create || 0 ) + '</td><td class="' + ( c.adopt ? 'is-adopt' : '' ) + '">' + ( c.adopt || 0 ) + '</td><td>' + ( c.update || 0 ) + '</td><td>' + ( c.skip || 0 ) + '</td>' +
				'<td class="' + ( c.conflict ? 'is-conflict' : '' ) + '">' + ( c.conflict || 0 ) + '</td>' +
				'<td class="' + ( c.fail ? 'is-fail' : '' ) + '">' + ( c.fail || 0 ) + '</td></tr>';
		} );
		el.counts.innerHTML = rows.join( '' );

		// Errors.
		const errors = s.errors || [];
		el.errorCount.textContent = errors.length ? '(' + ( s.errors_total || errors.length ) + ')' : '';
		if ( ! errors.length ) {
			el.errors.innerHTML = '<p class="hk9-import__empty">' + esc( cfg.i18n.noErrors ) + '</p>';
		} else {
			el.errors.innerHTML = '<table class="widefat striped"><thead><tr><th scope="col">' + esc( cfg.i18n.colKey ) + '</th><th scope="col">' + esc( cfg.i18n.colStep ) + '</th><th scope="col">' + esc( cfg.i18n.colMessage ) + '</th></tr></thead><tbody>' +
				errors.map( ( e ) => '<tr class="' + ( e.fatal ? 'is-fatal' : '' ) + '"><td><code>' + esc( e.key || '—' ) + '</code></td><td>' + esc( e.step ) + '</td><td>' + esc( e.message ) + '</td></tr>' ).join( '' ) +
				'</tbody></table>';
		}

		// Warnings.
		const warnings = s.warnings || [];
		el.warnings.querySelector( '.hk9-import__count' ).textContent = warnings.length ? '(' + ( s.warnings_total || warnings.length ) + ')' : '';
		el.warnings.querySelector( 'ul' ).innerHTML = warnings.map( ( w ) => '<li><code>' + esc( w.key || w.step ) + '</code> ' + esc( w.message ) + '</li>' ).join( '' );
		el.warnings.hidden = ! warnings.length;

		// Next steps: after a completed real import.
		if ( el.next ) {
			const finished = status === 'done' && ! ( s.mode && s.mode.dry_run );
			el.next.hidden = ! finished;
			if ( el.nextPayload ) {
				el.nextPayload.hidden = ! ( s.payload_source === 'path' && ! s.payload_deleted );
			}
		}

		// Buttons.
		const running = status === 'running';
		const canStart = ! running && ! busy && ! snapshot.lock;
		el.dry.disabled = ! canStart || el.dry.dataset.locked === '1';
		el.start.disabled = ! canStart || el.start.dataset.locked === '1';
		el.pause.disabled = ! running || busy;
		el.resume.disabled = status !== 'paused' || busy || !! snapshot.lock;
		el.retry.disabled = ! ( status === 'done' || status === 'failed' ) || ! errors.length || busy;
		el.reset.disabled = running || busy;

		// Payload summary.
		if ( snapshot.payload ) {
			const p = snapshot.payload;
			el.payload.innerHTML = p.ok
				? '<dl class="hk9-import__dl"><dt>' + esc( cfg.i18n.directory ) + '</dt><dd><code>' + esc( p.dir ) + '</code></dd><dt>' + esc( cfg.i18n.source ) + '</dt><dd>' + esc( p.uploaded ? cfg.i18n.uploadedZip : cfg.i18n.serverPath ) + '</dd><dt>' + esc( cfg.i18n.generated ) + '</dt><dd>' + esc( p.generated_at ) + '</dd><dt>' + esc( cfg.i18n.recordsLabel ) + '</dt><dd>' + p.records + ' (' + p.posts + ' ' + esc( cfg.i18n.postsPages ) + ', ' + p.attachments + ' ' + esc( cfg.i18n.media ) + ', ' + ( p.bytes / 1048576 ).toFixed( 1 ) + ' MB)</dd></dl>'
				: '<p class="hk9-import__empty">' + esc( p.error ) + '</p>';
			if ( el.path && ! el.path.value ) {
				el.path.value = p.dir;
			}
		} else {
			el.payload.innerHTML = '<p class="hk9-import__empty">' + esc( cfg.i18n.noPayload ) + '</p>';
		}
		document.querySelectorAll( '.hk9-import__dev li' ).forEach( ( li ) => {
			const btn = li.querySelector( 'button' );
			li.classList.toggle( 'is-selected', !! btn && el.path && btn.dataset.path === el.path.value );
		} );

		// Runs.
		const runs = Object.values( snapshot.runs || {} );
		if ( ! runs.length ) {
			el.runs.innerHTML = '<p class="hk9-import__empty">' + esc( cfg.i18n.noRuns ) + '</p>';
		} else {
			el.runs.innerHTML = '<div class="hk9-import__runs"><table class="widefat striped"><thead><tr><th scope="col">' + esc( cfg.i18n.colRun ) + '</th><th scope="col">' + esc( cfg.i18n.colStarted ) + '</th><th scope="col">' + esc( cfg.i18n.colMode ) + '</th><th scope="col">' + esc( cfg.i18n.colStatus ) + '</th><th scope="col">' + esc( cfg.i18n.colErrors ) + '</th><th scope="col"><span class="screen-reader-text">' + esc( cfg.i18n.colActions ) + '</span></th></tr></thead><tbody>' +
				runs.map( ( r ) => {
					const mode = ( r.mode && r.mode.dry_run ? cfg.i18n.dryRun : cfg.i18n.import ) + ( r.mode && r.mode.adopt ? ' + ' + cfg.i18n.adopt : '' ) + ( r.mode && r.mode.overwrite ? ' + ' + cfg.i18n.overwrite : '' );
					const canRollback = ! ( r.mode && r.mode.dry_run ) && ! r.rolled_back && ! running;
					return '<tr data-run="' + esc( r.run_id ) + '"><td><code>' + esc( r.run_id ) + '</code></td><td>' + esc( ( r.started_at || '' ).replace( 'T', ' ' ).slice( 0, 19 ) ) + '</td><td>' + esc( mode ) + '</td><td>' + esc( r.status ) + ( r.rolled_back ? ' (' + esc( cfg.i18n.rolledBack ) + ')' : '' ) + '</td><td>' + ( r.errors || 0 ) + '</td>' +
						'<td>' + ( canRollback ? '<button type="button" class="button button-small hk9-import__rollback-btn" data-run="' + esc( r.run_id ) + '">' + esc( cfg.i18n.rollback ) + '</button>' : '' ) +
						( r.log_file ? ' <a class="button button-small" href="' + esc( cfg.logBase + '&run=' + encodeURIComponent( r.run_id ) ) + '">' + esc( cfg.i18n.log ) + '</a>' : '' ) + '</td></tr>';
				} ).join( '' ) + '</tbody></table></div>' +
				( lastReport ? '<div class="hk9-import__report" role="status">' + esc( lastReport ) + '</div>' : '' );
		}

		// Map summary.
		const map = snapshot.map || {};
		const parts = Object.keys( map ).map( ( k ) => k + ' = ' + map[ k ] );
		el.map.textContent = parts.length ? parts.join( ', ' ) : '—';
	}

	/* ------------------------------------------------------- pre-flight */

	function renderPreflight() {
		if ( ! el.preflight || ! preflight ) {
			return;
		}
		const label = ( st ) => ( st === 'pass' ? cfg.i18n.pass : ( st === 'warn' ? cfg.i18n.warn : cfg.i18n.failWord ) );
		el.preflight.innerHTML = '<ul class="hk9-import__checks">' + ( preflight.checks || [] ).map( ( c ) =>
			'<li class="hk9-import__check is-' + esc( c.status ) + '"><span class="hk9-import__check-status">' + esc( label( c.status ) ) + '</span><span class="hk9-import__check-body"><strong>' + esc( c.label ) + '</strong><span class="hk9-import__check-detail">' + esc( c.detail ) + '</span>' +
			( c.action && c.action.url ? ' <a class="button button-small" href="' + esc( c.action.url ) + '">' + esc( c.action.label ) + '</a>' : '' ) + '</span></li>'
		).join( '' ) + '</ul><p class="hk9-import__preflight-summary ' + ( preflight.ok ? 'is-ok' : 'is-bad' ) + '">' + esc( preflight.ok ? cfg.i18n.preflightOk : cfg.i18n.preflightBad ) + '</p>';
		// Pre-tick adoption when the payload matches content already on the site (unless the admin chose otherwise).
		if ( el.adopt && ! adoptTouched ) {
			el.adopt.checked = !! ( preflight.existing && preflight.existing.total > 0 );
		}
	}

	async function refreshPreflight() {
		try {
			const path = el.path && el.path.value ? 'path=' + encodeURIComponent( el.path.value ) : '';
			preflight = await call( 'preflight', null, 'GET', path );
			renderPreflight();
		} catch ( e ) {
			// The server-side panel stays.
		}
	}

	/* ------------------------------------------------------------ loop */

	async function loop() {
		if ( stopped ) {
			return;
		}
		if ( snapshot.state.status !== 'running' ) {
			return;
		}
		if ( snapshot.lock && ! busy ) {
			// Another process (CLI?) is ticking: just poll status.
			try {
				snapshot = await call( 'status', null, 'GET' );
			} catch ( e ) {
				notice( 'error', e.message );
			}
			render();
			setTimeout( loop, 2000 );
			return;
		}
		try {
			busy = true;
			render();
			snapshot = await call( 'step' );
			busy = false;
			render();
			if ( snapshot.state.status === 'running' ) {
				setTimeout( loop, 50 );
			} else {
				announceFinal();
				refreshPreflight();
			}
		} catch ( e ) {
			busy = false;
			if ( e.status === 423 ) {
				try {
					snapshot = await call( 'status', null, 'GET' );
				} catch ( e2 ) {
					// keep old snapshot
				}
				render();
				setTimeout( loop, 2000 );
				return;
			}
			notice( 'error', e.message );
			try {
				snapshot = await call( 'status', null, 'GET' );
			} catch ( e3 ) {
				// ignore
			}
			render();
		}
	}

	function announceFinal() {
		const s = snapshot.state;
		const errs = ( s.errors || [] ).length;
		if ( s.status === 'done' ) {
			notice( errs ? 'warning' : 'success', ( s.mode && s.mode.dry_run ? cfg.i18n.dryRun : cfg.i18n.import ) + ': ' + cfg.i18n.done + ( errs ? ' — ' + sprintf( cfg.i18n.errorsSuffix, errs ) : '' ) );
		} else if ( s.status === 'failed' ) {
			notice( 'error', cfg.i18n.failed + ': ' + ( ( s.errors || [] ).filter( ( e ) => e.fatal ).map( ( e ) => e.message ).join( ' ' ) || '' ) );
		} else if ( s.status === 'paused' ) {
			notice( 'info', cfg.i18n.paused );
		}
	}

	async function action( name, body ) {
		notice( '', '' );
		try {
			busy = true;
			render();
			const data = await call( name, body );
			snapshot = data.status ? data.status : data;
			busy = false;
			render();
			return data;
		} catch ( e ) {
			busy = false;
			render();
			notice( 'error', e.message );
			return null;
		}
	}

	async function start( dryRun ) {
		const body = {
			dry_run: !! dryRun,
			overwrite: !! el.overwrite.checked,
			adopt: !! ( el.adopt && el.adopt.checked ),
			path: el.path ? el.path.value : '',
			budget: 10,
			batch: 25,
		};
		const data = await action( 'start', body );
		if ( data ) {
			stopped = false;
			loop();
		}
	}

	/* ---------------------------------------------------------- events */

	el.dry.addEventListener( 'click', () => start( true ) );
	el.start.addEventListener( 'click', () => start( false ) );
	el.pause.addEventListener( 'click', async () => {
		await action( 'pause' );
		if ( snapshot.state.status === 'paused' ) {
			notice( 'info', cfg.i18n.paused );
		}
	} );
	el.resume.addEventListener( 'click', async () => {
		const data = await action( 'resume' );
		if ( data ) {
			stopped = false;
			loop();
		}
	} );
	el.retry.addEventListener( 'click', async () => {
		const data = await action( 'retry' );
		if ( data ) {
			stopped = false;
			loop();
		}
	} );
	el.reset.addEventListener( 'click', async () => {
		if ( ! window.confirm( cfg.i18n.confirmReset ) ) {
			return;
		}
		stopped = true;
		await action( 'reset' );
	} );

	if ( el.adopt ) {
		el.adopt.addEventListener( 'change', () => {
			adoptTouched = true;
		} );
	}

	document.addEventListener( 'click', ( ev ) => {
		const use = ev.target.closest( '.hk9-import__use-path' );
		if ( use && el.path ) {
			el.path.value = use.dataset.path;
			render();
			refreshPreflight();
			return;
		}
		const manual = ev.target.closest( '.hk9-import__use-manual' );
		if ( manual && el.path ) {
			const input = document.getElementById( 'hk9-import-path-manual' );
			el.path.value = input ? input.value.trim() : '';
			render();
			refreshPreflight();
			return;
		}
		const rb = ev.target.closest( '.hk9-import__rollback-btn' );
		if ( rb ) {
			openRollback( rb.dataset.run, rb.closest( 'tr' ) );
		}
	} );

	function openRollback( run, row ) {
		document.querySelectorAll( '.hk9-import__rollback' ).forEach( ( n ) => n.remove() );
		const box = document.createElement( 'div' );
		box.className = 'hk9-import__rollback';
		box.innerHTML = '<label>' + esc( cfg.i18n.typeRollback ) + ' <input type="text" autocomplete="off" aria-label="' + esc( cfg.i18n.confirmation ) + '"></label>' +
			'<label><input type="checkbox" class="hk9-import__force"> ' + esc( cfg.i18n.force ) + '</label>' +
			'<button type="button" class="button button-primary hk9-import__rollback-go">' + esc( sprintf( cfg.i18n.rollBackRun, run ) ) + '</button>' +
			'<button type="button" class="button hk9-import__rollback-cancel">' + esc( cfg.i18n.cancel ) + '</button>';
		const cell = document.createElement( 'td' );
		cell.colSpan = 6;
		cell.appendChild( box );
		const tr = document.createElement( 'tr' );
		tr.appendChild( cell );
		row.after( tr );
		const input = box.querySelector( 'input[type="text"]' );
		input.focus();
		box.querySelector( '.hk9-import__rollback-cancel' ).addEventListener( 'click', () => tr.remove() );
		box.querySelector( '.hk9-import__rollback-go' ).addEventListener( 'click', async () => {
			if ( input.value.trim() !== 'ROLLBACK' ) {
				notice( 'error', cfg.i18n.typeRollback );
				input.focus();
				return;
			}
			const data = await action( 'rollback', { run, confirm: 'ROLLBACK', force: box.querySelector( '.hk9-import__force' ).checked } );
			if ( data && data.report ) {
				const r = data.report;
				lastReport = [
					...r.deleted.map( ( d ) => cfg.i18n.deleted + '  ' + d.key + ( d.id ? ' (#' + d.id + ')' : '' ) ),
					...r.restored.map( ( d ) => cfg.i18n.restored + '  ' + d.key + ' [' + ( d.fields || [] ).join( ', ' ) + ']' ),
					...( r.unadopted || [] ).map( ( d ) => cfg.i18n.unadopted + '  ' + d.key + ( d.id ? ' (#' + d.id + ')' : '' ) ),
					...r.skipped.map( ( d ) => cfg.i18n.skipped + '  ' + d.key + ' — ' + d.reason ),
					...r.errors.map( ( d ) => cfg.i18n.errorWord + '  ' + d ),
				].join( '\n' );
				render();
				notice( r.skipped.length ? 'warning' : 'success', sprintf( cfg.i18n.rollbackDone, r.deleted.length, r.restored.length, r.skipped.length ) );
			}
		} );
	}

	/* ------------------------------------------------------------ boot */

	if ( el.dry.disabled ) {
		el.dry.dataset.locked = '1';
	}
	if ( el.start.disabled ) {
		el.start.dataset.locked = '1';
	}
	render();
	renderPreflight();
	if ( snapshot.state.status === 'running' ) {
		loop();
	}
} )();
