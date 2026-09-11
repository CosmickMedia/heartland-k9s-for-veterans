#!/usr/bin/env node
/**
 * HK9 media fetcher — downloads every live attachment original plus the
 * reference-bundle assets into payload/media/, verifies them, hashes them,
 * measures images and writes payload/media-index.json + two docs.
 *
 * Inputs
 *   discovery/live/media-manifest.json   (244 live attachments, REST-derived)
 *   discovery/ref/assets/*               (7 reference assets, already downloaded)
 *
 * Outputs
 *   payload/media/<key-dir>/<original file>           (+ <file>.sha256 sidecar)
 *   payload/media-index.json                          ({generated_at, items:{key:{…}}})
 *   docs/media-manifest.md                            (table, one row per key)
 *   docs/reports/media-fetch-report.md                (counts, failures, retries, duration)
 *
 * Usage
 *   node tools/fetch-media.mjs [--concurrency=4] [--retries=3] [--timeout=30000]
 *        [--delay=150] [--only=key1,key2|id,id] [--force] [--verify] [--dry-run]
 *
 * Resumable: an item is skipped when its file and .sha256 sidecar exist and the
 * file size matches the previously recorded size (or, without a prior index
 * record, the recomputed sha256 matches the sidecar). --force re-downloads,
 * --verify re-hashes every existing file against its sidecar.
 */

import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { setTimeout as sleep } from 'node:timers/promises';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const MANIFEST = path.join(ROOT, 'discovery/live/media-manifest.json');
const REF_ASSETS_DIR = path.join(ROOT, 'discovery/ref/assets');
const PAYLOAD_DIR = path.join(ROOT, 'payload');
const MEDIA_DIR = path.join(PAYLOAD_DIR, 'media');
const INDEX_FILE = path.join(PAYLOAD_DIR, 'media-index.json');
const DOC_MANIFEST = path.join(ROOT, 'docs/media-manifest.md');
const DOC_REPORT = path.join(ROOT, 'docs/reports/media-fetch-report.md');

const USER_AGENT = 'HK9-migration/1.0 (+https://heartlandk9s.org)';
const REF_ORIGIN = 'https://heartland-canines-for-veterans.replit.app';
const HIDDEN_LIVE_ID = 2061; // not readable via anonymous REST; file URL is public

/** Reference assets: key name → file in discovery/ref/assets and its public URL path. */
const REF_ASSETS = [
	{ name: 'heartland-k9s-logo', file: 'heartland-k9s-logo.png', url: '/heartland-k9s-logo.png', title: 'Heartland K9s logo', alt: 'Heartland Canines for Veterans' },
	{ name: 'favicon', file: 'favicon.svg', url: '/favicon.svg', title: 'Favicon (reference placeholder)', alt: '' },
	{ name: 'hero-home', file: 'hero-home.jpg', url: '/assets/hero-home.jpg', title: 'Home hero background', alt: '' },
	{ name: 'about-dog', file: 'about-dog.jpg', url: '/assets/about-dog.jpg', title: 'About split-card image', alt: '' },
	{ name: 'training', file: 'training.jpg', url: '/assets/training.jpg', title: 'Program hero background', alt: '' },
	{ name: 'barkode', file: 'barkode.jpg', url: '/assets/barkode.jpg', title: 'BarKode hero / tile background', alt: '' },
	{ name: 'veteran-story', file: 'veteran-story.jpg', url: '/assets/veteran-story.jpg', title: 'Veteran story testimonial image', alt: '' },
];

/** External media/links on the live or reference site that are deliberately NOT downloaded. */
const EXTERNAL_NOT_DOWNLOADED = [
	{ host: 'widgets.guidestar.org', example: 'https://widgets.guidestar.org/prod/v1/pdp/transparency-seal/9494475/svg', what: 'Candid/GuideStar transparency seal (SVG)', why: 'Third-party trust seal that must stay live so it reflects the current profile status; rendered by the theme from settings (contact.show_guidestar_seal), never re-hosted.' },
	{ host: 'www.paypalobjects.com', example: 'https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif', what: 'PayPal hosted "Donate" button image (GIF)', why: 'PayPal branding asset; the donate page renders its own button and the PayPal hosted-button form uses links.paypal_hosted_button_id.' },
	{ host: 'www.paypal.com', example: 'https://www.paypal.com/en_US/i/scr/pixel.gif', what: 'PayPal tracking pixel (GIF)', why: 'Tracking pixel, not content.' },
	{ host: 'www.zeffy.com', example: 'https://www.zeffy.com/en-US/donation-form/donate-to-heartland-k9s-it-will-change-lives', what: 'Zeffy donation form', why: 'External link target only (links.donate_external); nothing to download.' },
	{ host: 'app.candid.org', example: 'https://app.candid.org/profile/9494475/heartland-canines-for-veterans-inc-47-4991572/', what: 'Candid nonprofit profile', why: 'External link target only (contact.candid_url); nothing to download.' },
	{ host: 'cdn.usefathom.com', example: 'https://cdn.usefathom.com/script.js', what: 'Fathom analytics script', why: 'Loaded at runtime only when analytics.fathom_site_id is set; not a media asset.' },
	{ host: 'fonts.googleapis.com', example: 'https://fonts.googleapis.com/css2?family=Fraunces…&family=Inter…', what: 'Google Fonts CSS (reference bundle)', why: 'Fonts are built locally by tools/fonts/build-fonts.py (WOFF2 + OFL); no runtime CDN.' },
];

const ALLOWED_MIMES = new Set([
	'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
	'application/pdf', 'video/mp4',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

const EXT_MIME = {
	'.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp',
	'.gif': 'image/gif', '.svg': 'image/svg+xml', '.pdf': 'application/pdf', '.mp4': 'video/mp4',
	'.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
};

/* ------------------------------------------------------------------ */
/* CLI                                                                 */
/* ------------------------------------------------------------------ */

function parseArgs(argv) {
	const opts = { concurrency: 4, retries: 3, timeout: 30000, delay: 150, only: null, force: false, verify: false, dryRun: false, quiet: false };
	for (const a of argv) {
		const m = a.match(/^--([a-z-]+)(?:=(.*))?$/i);
		if (!m) continue;
		const [, k, v] = m;
		switch (k) {
			case 'concurrency': opts.concurrency = Math.max(1, parseInt(v, 10) || 4); break;
			case 'retries': opts.retries = Math.max(0, parseInt(v, 10) || 0); break;
			case 'timeout': opts.timeout = Math.max(1000, parseInt(v, 10) || 30000); break;
			case 'delay': opts.delay = Math.max(0, parseInt(v, 10) || 0); break;
			case 'only': opts.only = (v || '').split(',').map((s) => s.trim()).filter(Boolean); break;
			case 'force': opts.force = true; break;
			case 'verify': opts.verify = true; break;
			case 'dry-run': opts.dryRun = true; break;
			case 'quiet': opts.quiet = true; break;
			case 'help': console.log(fs.readFileSync(fileURLToPath(import.meta.url), 'utf8').split('*/')[0]); process.exit(0);
			default: console.warn(`Unknown option --${k}`);
		}
	}
	return opts;
}

const opts = parseArgs(process.argv.slice(2));
const log = (...a) => { if (!opts.quiet) console.log(...a); };

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', hellip: '…', mdash: '—', ndash: '–', lsquo: '‘', rsquo: '’', ldquo: '“', rdquo: '”', copy: '©', reg: '®', trade: '™' };

/** Rendered REST HTML → plain text (tags stripped, entities decoded, whitespace collapsed). */
function htmlToText(html) {
	if (!html) return '';
	return String(html)
		.replace(/<br\s*\/?>/gi, '\n')
		.replace(/<\/p>/gi, '\n')
		.replace(/<[^>]+>/g, '')
		.replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(parseInt(n, 10)))
		.replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)))
		.replace(/&([a-z]+);/gi, (m, n) => (n.toLowerCase() in ENTITIES ? ENTITIES[n.toLowerCase()] : m))
		.replace(/[ \t]+/g, ' ')
		.replace(/\s*\n\s*/g, '\n')
		.trim();
}

const keyToDir = (key) => key.replace(/[:/]/g, '__');
const sha256File = async (file) => new Promise((resolve, reject) => {
	const h = crypto.createHash('sha256');
	fs.createReadStream(file).on('data', (c) => h.update(c)).on('error', reject).on('end', () => resolve(h.digest('hex')));
});
const fmtBytes = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : n >= 1024 ? `${(n / 1024).toFixed(1)} KB` : `${n} B`);
const fmtDur = (ms) => (ms >= 60000 ? `${Math.floor(ms / 60000)}m ${((ms % 60000) / 1000).toFixed(0)}s` : `${(ms / 1000).toFixed(1)}s`);
const mdCell = (s) => String(s ?? '').replace(/\|/g, '\\|').replace(/\n/g, ' ');

/** Sniff the mime type from the first bytes of a buffer. Returns null when unknown. */
function sniffMime(buf) {
	if (!buf || buf.length < 4) return null;
	if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'image/jpeg';
	if (buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'image/png';
	const s = buf.subarray(0, 12).toString('latin1');
	if (s.startsWith('GIF87a') || s.startsWith('GIF89a')) return 'image/gif';
	if (s.startsWith('RIFF') && s.slice(8, 12) === 'WEBP') return 'image/webp';
	if (s.startsWith('%PDF')) return 'application/pdf';
	if (s.slice(4, 8) === 'ftyp') return 'video/mp4';
	if (s.startsWith('PK')) return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
	const head = buf.subarray(0, 512).toString('utf8').replace(/^﻿/, '').trimStart().toLowerCase();
	if (head.startsWith('<?xml') || head.startsWith('<svg')) return head.includes('<svg') ? 'image/svg+xml' : null;
	if (head.startsWith('<!doctype html') || head.startsWith('<html')) return 'text/html';
	return null;
}

async function readHead(file, n = 512) {
	const fh = await fsp.open(file, 'r');
	try {
		const buf = Buffer.alloc(n);
		const { bytesRead } = await fh.read(buf, 0, n, 0);
		return buf.subarray(0, bytesRead);
	} finally {
		await fh.close();
	}
}

let sharpMod = null;
async function loadSharp() {
	if (sharpMod !== null) return sharpMod;
	try { sharpMod = (await import('sharp')).default; } catch (e) { sharpMod = false; console.warn(`sharp unavailable (${e.message}); dimensions will be null`); }
	return sharpMod;
}

/** Image dimensions via sharp (null for non-images or on failure). Honors EXIF orientation. */
async function measure(file, mime) {
	if (!mime.startsWith('image/')) return { width: null, height: null };
	const sharp = await loadSharp();
	if (!sharp) return { width: null, height: null };
	try {
		const md = await sharp(file, { limitInputPixels: false }).metadata();
		let { width, height } = md;
		if (md.orientation && md.orientation >= 5) [width, height] = [height, width];
		return { width: width ?? null, height: height ?? null };
	} catch (e) {
		return { width: null, height: null, error: `sharp: ${e.message}` };
	}
}

/* ------------------------------------------------------------------ */
/* Job construction                                                    */
/* ------------------------------------------------------------------ */

function liveJob(item) {
	const key = `live:media:${item.id}`;
	const src = new URL(item.source_url);
	const srcDir = src.pathname.replace(/\/[^/]*$/, '');
	let filename; let url; let unscaled = false;
	if (item.original_image) {
		filename = path.posix.basename(item.original_image);
		url = `${src.origin}${srcDir}/${encodeURIComponent(filename)}`;
		unscaled = true;
	} else {
		filename = decodeURIComponent(path.posix.basename(src.pathname));
		url = item.source_url;
	}
	const mime = item.mime_type || EXT_MIME[path.extname(filename).toLowerCase()] || 'application/octet-stream';
	const referenced = [...(item._referenced_by_pages || [])];
	if (item._referenced_by_home_html && !referenced.includes('home')) referenced.push('home');
	const notes = [];
	if (item.id === HIDDEN_LIVE_ID || item._note) notes.push(item._note || 'Hidden from anonymous REST; fetched directly from the public upload URL.');
	const captionText = htmlToText(item.caption);
	// REST description.rendered = prepend_attachment() markup (<p class="attachment">…</p>, the image or a
	// link to the file titled with the attachment title) + the real post_content; drop the auto block.
	const descText = htmlToText((item.description || '').replace(/<p class="attachment">[\s\S]*?<\/p>\s*/i, ''));
	const isFileUrl = (t) => /^https?:\/\/heartlandk9s\.org\/wp-content\/uploads\//i.test(t) && !/\s/.test(t);
	let caption = captionText; let description = descText;
	if (caption && isFileUrl(caption)) { caption = ''; notes.push('caption was the auto-generated file URL; blanked (raw kept in caption_html)'); }
	if (description && isFileUrl(description)) { description = ''; notes.push('description was the auto-generated file URL; blanked (raw kept in description_html)'); }
	return {
		key, kind: 'live', dir: keyToDir(key), filename, url, mime,
		expected_bytes: unscaled ? item.original_image_filesize ?? null : item.filesize ?? null,
		record: {
			source_url: url,
			served_full_url: item.source_url,
			unscaled_original: unscaled,
			title: item.title || filename.replace(/\.[^.]+$/, ''),
			alt: item.alt_text || '',
			caption, caption_html: item.caption || '',
			description, description_html: item.description || '',
			date: item.date || null,
			modified: item.modified || null,
			live_id: item.id,
			live_slug: item.slug || null,
			live_parent: item.post ?? null,
			live_width: item.width ?? null,
			live_height: item.height ?? null,
			referenced_by: referenced,
			referenced_by_home_html: !!item._referenced_by_home_html,
			is_featured: !!item._is_featured,
			disposition: referenced.length || item._is_featured ? 'referenced' : 'unreferenced-retained',
			notes,
		},
	};
}

function refJob(asset) {
	const key = `ref:asset:${asset.name}`;
	return {
		key, kind: 'ref', dir: keyToDir(key), filename: asset.file,
		url: `${REF_ORIGIN}${asset.url}`,
		local_source: path.join(REF_ASSETS_DIR, asset.file),
		mime: EXT_MIME[path.extname(asset.file).toLowerCase()],
		expected_bytes: null,
		record: {
			source_url: `${REF_ORIGIN}${asset.url}`,
			local_source: path.relative(ROOT, path.join(REF_ASSETS_DIR, asset.file)),
			title: asset.title, alt: asset.alt, caption: '', description: '',
			date: null, live_id: null, live_parent: null,
			referenced_by: [], disposition: 'reference-asset', notes: ['Copied from the reference bundle download in discovery/ref/assets (no network).'],
		},
	};
}

/* ------------------------------------------------------------------ */
/* Download                                                            */
/* ------------------------------------------------------------------ */

class DownloadError extends Error {
	constructor(message, { retryable = true, status = null } = {}) { super(message); this.retryable = retryable; this.status = status; }
}

/** Stream url → destPart with an idle timeout; returns {bytes, sha256, headers}. */
async function downloadOnce(url, destPart, idleMs) {
	const ac = new AbortController();
	let idle = setTimeout(() => ac.abort(new DownloadError(`idle timeout after ${idleMs} ms (headers)`)), idleMs);
	const bump = (label) => { clearTimeout(idle); idle = setTimeout(() => ac.abort(new DownloadError(`idle timeout after ${idleMs} ms (${label})`)), idleMs); };
	let res;
	try {
		res = await fetch(url, { signal: ac.signal, redirect: 'follow', headers: { 'user-agent': USER_AGENT, accept: '*/*', 'accept-encoding': 'identity' } });
	} catch (e) {
		clearTimeout(idle);
		throw e instanceof DownloadError ? e : new DownloadError(`network: ${e.cause?.code || e.message}`);
	}
	if (!res.ok) {
		clearTimeout(idle);
		res.body?.cancel().catch(() => {});
		const retryable = res.status === 408 || res.status === 429 || res.status >= 500;
		throw new DownloadError(`HTTP ${res.status} ${res.statusText}`.trim(), { retryable, status: res.status });
	}
	const headers = {
		content_type: res.headers.get('content-type'),
		content_length: res.headers.get('content-length') ? parseInt(res.headers.get('content-length'), 10) : null,
		content_encoding: res.headers.get('content-encoding'),
		last_modified: res.headers.get('last-modified'),
		etag: res.headers.get('etag'),
		final_url: res.url,
	};
	const hash = crypto.createHash('sha256');
	let bytes = 0; let firstChunk = null;
	const out = fs.createWriteStream(destPart);
	try {
		bump('body');
		for await (const chunk of res.body) {
			bump('body');
			if (!firstChunk) firstChunk = Buffer.from(chunk.subarray(0, 512));
			hash.update(chunk); bytes += chunk.length;
			if (!out.write(chunk)) await new Promise((r) => out.once('drain', r));
		}
		await new Promise((resolve, reject) => out.end((err) => (err ? reject(err) : resolve())));
	} catch (e) {
		out.destroy();
		throw e instanceof DownloadError ? e : (ac.signal.reason instanceof DownloadError ? ac.signal.reason : new DownloadError(`stream: ${e.message}`));
	} finally {
		clearTimeout(idle);
	}
	return { bytes, sha256: hash.digest('hex'), headers, head: firstChunk || Buffer.alloc(0) };
}

/** Validate a downloaded/copied file against the expected mime and sizes. Returns {sniffed, checks[]} or throws DownloadError. */
function validate({ mime, headers, bytes, head, expected_bytes }) {
	const checks = [];
	if (bytes <= 0) throw new DownloadError('empty response (0 bytes)');
	if (headers?.content_length != null && !headers.content_encoding && headers.content_length !== bytes) {
		throw new DownloadError(`size mismatch: Content-Length ${headers.content_length} vs received ${bytes}`);
	}
	if (headers?.content_length != null && headers.content_length === bytes) checks.push('content-length');
	const sniffed = sniffMime(head);
	if (sniffed === 'text/html') throw new DownloadError('received an HTML document instead of the file', { retryable: false });
	const headerMime = (headers?.content_type || '').split(';')[0].trim().toLowerCase() || null;
	if (!ALLOWED_MIMES.has(mime)) throw new DownloadError(`expected mime ${mime} is not in the allow-list`, { retryable: false });
	if (headerMime === mime) checks.push('content-type');
	else if (sniffed === mime) checks.push(headers ? `magic-bytes (server sent ${headerMime || 'no content-type'})` : 'magic-bytes (local file, no HTTP headers)');
	else throw new DownloadError(`content-type mismatch: expected ${mime}, server sent ${headerMime || 'none'}, sniffed ${sniffed || 'unknown'}`, { retryable: false });
	if (sniffed && sniffed !== mime) throw new DownloadError(`magic bytes (${sniffed}) contradict expected mime ${mime}`, { retryable: false });
	if (expected_bytes != null) checks.push(expected_bytes === bytes ? 'manifest-filesize' : `manifest-filesize differs (${expected_bytes} listed, ${bytes} received)`);
	return { sniffed, checks };
}

async function fetchWithRetries(job, dest, stats) {
	const part = `${dest}.part`;
	let attempt = 0; let lastErr = null;
	while (attempt <= opts.retries) {
		attempt += 1;
		try {
			const r = await downloadOnce(job.url, part, opts.timeout);
			const v = validate({ mime: job.mime, headers: r.headers, bytes: r.bytes, head: r.head, expected_bytes: job.expected_bytes });
			await fsp.rename(part, dest);
			return { ...r, ...v, attempts: attempt };
		} catch (e) {
			lastErr = e;
			await fsp.rm(part, { force: true });
			const retryable = e instanceof DownloadError ? e.retryable : true;
			if (!retryable || attempt > opts.retries) break;
			stats.retries += 1;
			const backoff = Math.min(30000, 1000 * 2 ** (attempt - 1)) + Math.floor(Math.random() * 400);
			log(`  retry ${attempt}/${opts.retries} ${job.key}: ${e.message} — waiting ${backoff} ms`);
			await sleep(backoff);
		}
	}
	throw Object.assign(lastErr, { attempts: attempt });
}

/* ------------------------------------------------------------------ */
/* Per-item processing                                                 */
/* ------------------------------------------------------------------ */

async function resumeCheck(job, dest, prev) {
	const sidecar = `${dest}.sha256`;
	if (opts.force) return null;
	if (!fs.existsSync(dest) || !fs.existsSync(sidecar)) return null;
	const st = await fsp.stat(dest);
	if (st.size <= 0) return null;
	const sidecarHash = (await fsp.readFile(sidecar, 'utf8')).trim().split(/\s+/)[0];
	if (!/^[0-9a-f]{64}$/.test(sidecarHash)) return null;
	if (opts.verify || !prev || prev.bytes !== st.size || prev.sha256 !== sidecarHash) {
		const h = await sha256File(dest);
		if (h !== sidecarHash) return null;
		return { sha256: h, bytes: st.size, how: 'rehashed' };
	}
	return { sha256: sidecarHash, bytes: st.size, how: 'size+sidecar' };
}

async function processJob(job, prevItems, stats) {
	const dir = path.join(MEDIA_DIR, job.dir);
	const dest = path.join(dir, job.filename);
	const relFile = path.posix.join('media', job.dir, job.filename);
	const prev = prevItems[job.key];
	const base = {
		file: relFile, sha256: null, bytes: null, mime: job.mime, width: null, height: null,
		...job.record,
		status: 'failed', error: null, fetched_at: null, attempts: 0, validation: [],
	};
	if (opts.dryRun) {
		log(`[dry] ${job.key} ← ${job.kind === 'ref' ? job.local_source : job.url}`);
		return { ...base, status: prev?.status === 'ok' ? 'ok' : 'failed', ...(prev || {}), error: prev?.error ?? 'dry-run' };
	}
	await fsp.mkdir(dir, { recursive: true });

	const resumed = await resumeCheck(job, dest, prev);
	if (resumed) {
		stats.skipped += 1;
		const dims = prev?.width != null || !job.mime.startsWith('image/') ? { width: prev?.width ?? null, height: prev?.height ?? null } : await measure(dest, job.mime);
		log(`[skip] ${job.key} ${job.filename} ${fmtBytes(resumed.bytes)} (resume: ${resumed.how})`);
		const rec = { ...base, ...(prev || {}), ...job.record, file: relFile, mime: job.mime, sha256: resumed.sha256, bytes: resumed.bytes, ...dims, status: 'ok', error: null, resumed: resumed.how };
		if (!rec.date && prev?.date) rec.date = prev.date; // e.g. Last-Modified-derived date for the hidden item
		rec.notes = [...new Set([...(job.record.notes || []), ...(prev?.notes || [])])];
		return rec;
	}

	try {
		let result;
		if (job.kind === 'ref') {
			await fsp.copyFile(job.local_source, dest);
			const bytes = (await fsp.stat(dest)).size;
			const head = await readHead(dest);
			const v = validate({ mime: job.mime, headers: null, bytes, head, expected_bytes: null });
			result = { bytes, sha256: await sha256File(dest), headers: null, attempts: 1, ...v };
		} else {
			if (opts.delay) await sleep(opts.delay);
			result = await fetchWithRetries(job, dest, stats);
			stats.downloaded += 1; stats.bytes_downloaded += result.bytes;
		}
		await fsp.writeFile(`${dest}.sha256`, `${result.sha256}  ${job.filename}\n`);
		const dims = await measure(dest, job.mime);
		if (dims.error) base.notes = [...(base.notes || []), dims.error];
		stats.ok += 1;
		const rec = {
			...base, sha256: result.sha256, bytes: result.bytes, width: dims.width, height: dims.height,
			status: 'ok', error: null, fetched_at: new Date().toISOString(), attempts: result.attempts, validation: result.checks,
		};
		if (result.headers) {
			rec.http = { content_type: result.headers.content_type, last_modified: result.headers.last_modified, etag: result.headers.etag };
			if (!rec.date && result.headers.last_modified) { rec.date = new Date(result.headers.last_modified).toISOString().slice(0, 19); rec.notes = [...(rec.notes || []), 'date taken from the HTTP Last-Modified header (no REST record)']; }
		}
		log(`[ok] ${job.key} ${job.filename} ${fmtBytes(result.bytes)}${dims.width ? ` ${dims.width}x${dims.height}` : ''}${result.attempts > 1 ? ` (${result.attempts} attempts)` : ''}`);
		return rec;
	} catch (e) {
		stats.failed += 1;
		const attempts = e.attempts || 1;
		stats.failures.push({ key: job.key, url: job.kind === 'ref' ? job.local_source : job.url, error: e.message, attempts });
		console.error(`[FAIL] ${job.key} ${job.filename}: ${e.message} (${attempts} attempt${attempts === 1 ? '' : 's'})`);
		return { ...base, status: 'failed', error: e.message, attempts, fetched_at: new Date().toISOString() };
	}
}

/* ------------------------------------------------------------------ */
/* Docs                                                                */
/* ------------------------------------------------------------------ */

function writeManifestDoc(index) {
	const rows = Object.entries(index.items).sort(([a], [b]) => {
		const ka = a.startsWith('ref:') ? 1 : 0; const kb = b.startsWith('ref:') ? 1 : 0;
		if (ka !== kb) return ka - kb;
		return (index.items[a].live_id ?? 0) - (index.items[b].live_id ?? 0) || a.localeCompare(b);
	});
	const counts = { ok: 0, failed: 0, referenced: 0, 'unreferenced-retained': 0, 'reference-asset': 0, bytes: 0 };
	for (const [, it] of rows) { counts[it.status] += 1; counts[it.disposition] += 1; counts.bytes += it.bytes || 0; }
	const lines = [];
	lines.push('# Media manifest');
	lines.push('');
	lines.push(`Generated ${index.generated_at} by \`tools/fetch-media.mjs\` from \`discovery/live/media-manifest.json\` (244 live attachments) and \`discovery/ref/assets/\` (7 reference assets). Machine-readable version: \`payload/media-index.json\`. Files live under \`payload/media/<key-dir>/<original filename>\` with a \`<file>.sha256\` sidecar (sha256sum format).`);
	lines.push('');
	lines.push(`- Items: **${rows.length}** (${counts.ok} ok, ${counts.failed} failed) · total ${fmtBytes(counts.bytes)} (${counts.bytes.toLocaleString('en-US')} bytes)`);
	lines.push(`- Disposition: ${counts.referenced} referenced · ${counts['unreferenced-retained']} unreferenced-retained · ${counts['reference-asset']} reference-asset`);
	lines.push('- Live keys are `live:media:<attachment id>`; reference keys are `ref:asset:<basename>`. For the 68 attachments WordPress had downscaled (`-scaled` files) the **unscaled original** (`media_details.original_image`) was fetched; the source URL column shows the file actually downloaded.');
	lines.push('- *Referenced by* lists live page slugs whose rendered content, `srcset`, background or gallery markup uses the attachment (`home` also covers the rendered homepage `<head>`/slider); `featured` marks a page featured image. Unreferenced attachments are retained in the payload so nothing in the live library is lost.');
	lines.push('- Not downloaded (external, kept live): see `docs/reports/media-fetch-report.md`.');
	lines.push('');
	lines.push('| Key | Source URL | Local file | MIME | Dimensions | Bytes | sha256 | Referenced by | Disposition |');
	lines.push('|---|---|---|---|---|---|---|---|---|');
	for (const [key, it] of rows) {
		const dims = it.width && it.height ? `${it.width}×${it.height}` : (it.mime === 'video/mp4' ? '(video)' : '—');
		const refs = [...(it.referenced_by || [])]; if (it.is_featured) refs.push('featured');
		const status = it.status === 'ok' ? '' : ` ⚠ ${mdCell(it.error)}`;
		lines.push(`| \`${key}\` | ${mdCell(it.source_url)} | \`${mdCell(it.file)}\`${status} | ${it.mime} | ${dims} | ${it.bytes?.toLocaleString('en-US') ?? '—'} | \`${(it.sha256 || '').slice(0, 12) || '—'}\` | ${mdCell(refs.join(', ')) || '—'} | ${it.disposition} |`);
	}
	lines.push('');
	fs.mkdirSync(path.dirname(DOC_MANIFEST), { recursive: true });
	fs.writeFileSync(DOC_MANIFEST, lines.join('\n'));
}

function writeReportDoc(index, run) {
	const items = Object.values(index.items);
	const totals = { items: items.length, ok: 0, failed: 0, bytes: 0, live: 0, ref: 0, unscaled: 0, byMime: {} };
	for (const it of items) {
		totals[it.status] += 1; totals.bytes += it.bytes || 0;
		if (it.live_id != null) totals.live += 1; else totals.ref += 1;
		if (it.unscaled_original) totals.unscaled += 1;
		totals.byMime[it.mime] = (totals.byMime[it.mime] || 0) + 1;
	}
	// duplicate groups by sha256
	const bySha = new Map();
	for (const [k, it] of Object.entries(index.items)) if (it.sha256) bySha.set(it.sha256, [...(bySha.get(it.sha256) || []), k]);
	const dupes = [...bySha.entries()].filter(([, ks]) => ks.length > 1).map(([sha, ks]) => ({ sha, keys: ks, file: index.items[ks[0]].file, bytes: index.items[ks[0]].bytes }));
	// manifest filesize discrepancies
	const sizeNotes = items.filter((it) => (it.validation || []).some((v) => v.startsWith('manifest-filesize differs'))).map((it) => `\`${it.live_id != null ? `live:media:${it.live_id}` : it.file}\` — ${(it.validation || []).find((v) => v.startsWith('manifest-filesize differs'))}`);
	const magicNotes = items.filter((it) => (it.validation || []).some((v) => v.startsWith('magic-bytes (server'))).map((it) => `\`live:media:${it.live_id}\` (${it.mime}) — ${(it.validation || []).find((v) => v.startsWith('magic-bytes'))}`);

	const L = [];
	L.push('# Media fetch report');
	L.push('');
	L.push(`Tool: \`tools/fetch-media.mjs\` (\`npm run media:fetch\`). Index: \`payload/media-index.json\`. Manifest table: \`docs/media-manifest.md\`. User-Agent \`${USER_AGENT}\`, concurrency ${run.options.concurrency}, ${run.options.retries} retries with exponential backoff (1 s → 2 s → 4 s + jitter), ${run.options.timeout} ms idle timeout per response, ${run.options.delay} ms politeness delay before each request.`);
	L.push('');
	L.push('## Latest run');
	L.push('');
	L.push(`- Started ${run.started_at}, finished ${run.finished_at}, duration **${fmtDur(run.duration_ms)}**${run.options.only ? ` (filtered: \`--only=${run.options.only.join(',')}\`)` : ''}${run.options.force ? ' (`--force`)' : ''}${run.options.verify ? ' (`--verify`)' : ''}`);
	L.push(`- Processed ${run.processed} items: **${run.ok} ok** (${run.downloaded} downloaded, ${run.copied} copied from the reference bundle, ${run.skipped} skipped by resume), **${run.failed} failed**, ${run.retries} retries`);
	L.push(`- Bytes downloaded this run: ${fmtBytes(run.bytes_downloaded)} (${run.bytes_downloaded.toLocaleString('en-US')})`);
	L.push('');
	const fullRun = [...index.runs].filter((r) => !r.options.only).sort((a, b) => b.downloaded - a.downloaded)[0];
	if (fullRun && fullRun !== run) {
		L.push('## Initial full fetch');
		L.push('');
		L.push(`- Started ${fullRun.started_at}, duration **${fmtDur(fullRun.duration_ms)}**: ${fullRun.processed} processed, **${fullRun.ok} ok** (${fullRun.downloaded} downloaded, ${fullRun.copied} copied, ${fullRun.skipped} skipped), **${fullRun.failed} failed**, ${fullRun.retries} retries, ${fmtBytes(fullRun.bytes_downloaded)} (${fullRun.bytes_downloaded.toLocaleString('en-US')} bytes) downloaded`);
		if (fullRun.failures?.length) for (const f of fullRun.failures) L.push(`  - \`${f.key}\`: ${mdCell(f.error)} (${f.attempts} attempts)`);
		L.push('');
	}
	L.push('## Totals (index state)');
	L.push('');
	L.push(`- Items expected: **251** (244 live + 7 reference) · in index: **${totals.items}** (${totals.live} live, ${totals.ref} reference)`);
	L.push(`- Status: **${totals.ok} ok / ${totals.failed} failed**`);
	L.push(`- Total bytes on disk: **${fmtBytes(totals.bytes)}** (${totals.bytes.toLocaleString('en-US')} bytes)`);
	L.push(`- Unscaled originals fetched instead of the served \`-scaled\` file: ${totals.unscaled}`);
	L.push(`- By MIME: ${Object.entries(totals.byMime).sort((a, b) => b[1] - a[1]).map(([m, n]) => `${m} ${n}`).join(' · ')}`);
	L.push('');
	L.push('## Failures');
	L.push('');
	const failed = Object.entries(index.items).filter(([, it]) => it.status !== 'ok');
	if (!failed.length) L.push('None — every item in the index is `ok`.');
	else { L.push('| Key | Source | Error | Attempts |'); L.push('|---|---|---|---|'); for (const [k, it] of failed) L.push(`| \`${k}\` | ${mdCell(it.source_url)} | ${mdCell(it.error)} | ${it.attempts ?? '—'} |`); }
	L.push('');
	L.push('## Validation notes');
	L.push('');
	L.push('Every file was checked for: non-empty body, received bytes = `Content-Length` (identity encoding requested), response `Content-Type` = expected MIME **or** file magic bytes = expected MIME, magic bytes never contradicting the expected MIME, and (where the discovery manifest recorded a size) the manifest filesize.');
	L.push('');
	L.push(magicNotes.length ? `Accepted on magic bytes because the server sent a different \`Content-Type\` (reference assets are local copies and are always validated by magic bytes):\n\n${magicNotes.map((s) => `- ${s}`).join('\n')}` : 'All downloaded files matched on `Content-Type`; reference assets (local copies) are validated by magic bytes.');
	L.push('');
	L.push(sizeNotes.length ? `Manifest filesize discrepancies (file kept; the download is the authoritative byte count):\n\n${sizeNotes.map((s) => `- ${s}`).join('\n')}` : 'Every downloaded file matched the filesize recorded in the discovery manifest.');
	L.push('');
	L.push('## Duplicate files (identical sha256)');
	L.push('');
	if (!dupes.length) L.push('None.');
	else { L.push('Kept as separate attachments (the live library has separate records); the importer may dedupe by sha256.'); L.push(''); L.push('| sha256 | Bytes | Keys |'); L.push('|---|---|---|'); for (const d of dupes) L.push(`| \`${d.sha.slice(0, 12)}\` | ${d.bytes?.toLocaleString('en-US')} | ${d.keys.map((k) => `\`${k}\``).join(', ')} |`); }
	L.push('');
	L.push('## External media not downloaded');
	L.push('');
	L.push('These hosts appear in the live pages or the reference bundle but are deliberately left external (they are third-party seals, buttons, scripts or link targets, not site content):');
	L.push('');
	L.push('| Host | Asset | Example | Why not downloaded |');
	L.push('|---|---|---|---|');
	for (const x of EXTERNAL_NOT_DOWNLOADED) L.push(`| \`${x.host}\` | ${x.what} | ${mdCell(x.example)} | ${x.why} |`);
	L.push('');
	L.push('## Special cases');
	L.push('');
	L.push(`- \`live:media:${HIDDEN_LIVE_ID}\` (Homepage-Hero-1.jpg) is hidden from the anonymous REST API (401 rest_forbidden) but its upload URL is public; it was fetched directly and its date comes from the HTTP \`Last-Modified\` header.`);
	L.push('- PDF attachments expose a JPEG preview as their `full` size in the REST API; the tool downloads the `source_url` (the actual PDF), never the preview.');
	L.push('- `ref:asset:heartland-k9s-logo` is byte-identical to `live:media:3028` (see duplicate table). `ref:asset:favicon` is the Replit placeholder (orange rounded square), retained only for traceability — not to be used as the site icon.');
	L.push('- For `video/mp4` only bytes are recorded (no dimension probe); PDF/DOCX have no dimensions.');
	L.push('');
	L.push('## Run history');
	L.push('');
	L.push('| Started | Duration | Processed | OK | Downloaded | Copied | Skipped (resume) | Failed | Retries | Bytes downloaded | Options |');
	L.push('|---|---|---|---|---|---|---|---|---|---|---|');
	for (const r of index.runs) L.push(`| ${r.started_at} | ${fmtDur(r.duration_ms)} | ${r.processed} | ${r.ok} | ${r.downloaded} | ${r.copied} | ${r.skipped} | ${r.failed} | ${r.retries} | ${fmtBytes(r.bytes_downloaded)} | ${[r.options.only ? `only=${r.options.only.length}` : '', r.options.force ? 'force' : '', r.options.verify ? 'verify' : '', r.options.dryRun ? 'dry-run' : ''].filter(Boolean).join(' ') || '—'} |`);
	L.push('');
	fs.mkdirSync(path.dirname(DOC_REPORT), { recursive: true });
	fs.writeFileSync(DOC_REPORT, L.join('\n'));
}

/* ------------------------------------------------------------------ */
/* Main                                                                */
/* ------------------------------------------------------------------ */

async function main() {
	const startedAt = new Date();
	const manifest = JSON.parse(await fsp.readFile(MANIFEST, 'utf8'));
	const liveItems = manifest.items || [];
	let jobs = [...liveItems.map(liveJob), ...REF_ASSETS.map(refJob)];
	if (opts.only) {
		const want = new Set(opts.only.flatMap((s) => (/^\d+$/.test(s) ? [`live:media:${s}`] : [s])));
		jobs = jobs.filter((j) => want.has(j.key));
		if (!jobs.length) { console.error('No jobs match --only'); process.exit(2); }
	}
	for (const j of jobs) if (j.kind === 'ref' && !fs.existsSync(j.local_source)) console.warn(`Reference asset missing: ${j.local_source}`);

	let prev = { items: {}, runs: [] };
	try { prev = JSON.parse(await fsp.readFile(INDEX_FILE, 'utf8')); } catch { /* first run */ }
	prev.items ||= {}; prev.runs ||= [];

	const stats = { ok: 0, failed: 0, skipped: 0, downloaded: 0, copied: 0, retries: 0, bytes_downloaded: 0, failures: [] };
	log(`HK9 media fetch: ${jobs.length} items (${jobs.filter((j) => j.kind === 'live').length} live, ${jobs.filter((j) => j.kind === 'ref').length} ref) → ${path.relative(ROOT, MEDIA_DIR)}/  [concurrency ${opts.concurrency}, retries ${opts.retries}, idle timeout ${opts.timeout} ms]`);
	await fsp.mkdir(MEDIA_DIR, { recursive: true });

	const results = {};
	let cursor = 0;
	const worker = async () => {
		while (cursor < jobs.length) {
			const job = jobs[cursor++];
			const rec = await processJob(job, prev.items, stats);
			if (job.kind === 'ref' && rec.status === 'ok' && !rec.resumed && !opts.dryRun) stats.copied += 1;
			results[job.key] = rec;
		}
	};
	await Promise.all(Array.from({ length: Math.min(opts.concurrency, jobs.length) }, worker));

	const finishedAt = new Date();
	const run = {
		started_at: startedAt.toISOString(), finished_at: finishedAt.toISOString(), duration_ms: finishedAt - startedAt,
		processed: jobs.length, ok: stats.ok + stats.skipped, downloaded: stats.downloaded, copied: stats.copied, skipped: stats.skipped,
		failed: stats.failed, retries: stats.retries, bytes_downloaded: stats.bytes_downloaded, failures: stats.failures,
		options: { concurrency: opts.concurrency, retries: opts.retries, timeout: opts.timeout, delay: opts.delay, only: opts.only, force: opts.force, verify: opts.verify, dryRun: opts.dryRun },
	};

	if (opts.dryRun) {
		log(`\nDry run: ${jobs.length} items would be processed. Nothing written.`);
		return;
	}

	// Merge: keep previous records for keys not processed in a filtered run; order live by id then ref.
	const merged = { ...prev.items, ...results };
	const orderedKeys = Object.keys(merged).sort((a, b) => {
		const ra = a.startsWith('ref:') ? 1 : 0; const rb = b.startsWith('ref:') ? 1 : 0;
		if (ra !== rb) return ra - rb;
		return (merged[a].live_id ?? 0) - (merged[b].live_id ?? 0) || a.localeCompare(b);
	});
	const index = {
		generated_at: finishedAt.toISOString(),
		tool: 'tools/fetch-media.mjs',
		source_manifest: path.relative(ROOT, MANIFEST),
		user_agent: USER_AGENT,
		summary: { items: orderedKeys.length, ok: orderedKeys.filter((k) => merged[k].status === 'ok').length, failed: orderedKeys.filter((k) => merged[k].status !== 'ok').length, bytes: orderedKeys.reduce((n, k) => n + (merged[k].bytes || 0), 0) },
		runs: [...prev.runs, run].slice(-20),
		items: Object.fromEntries(orderedKeys.map((k) => [k, merged[k]])),
	};
	await fsp.writeFile(INDEX_FILE, `${JSON.stringify(index, null, '\t')}\n`);
	writeManifestDoc(index);
	writeReportDoc(index, run);

	log('');
	log(`Done in ${fmtDur(run.duration_ms)}: ${run.ok} ok (${run.downloaded} downloaded, ${run.copied} copied, ${run.skipped} skipped), ${run.failed} failed, ${run.retries} retries, ${fmtBytes(run.bytes_downloaded)} downloaded.`);
	log(`Index: ${index.summary.items} items, ${index.summary.ok} ok, ${index.summary.failed} failed, ${fmtBytes(index.summary.bytes)} on disk.`);
	if (run.failed) { for (const f of run.failures) console.error(`  ✗ ${f.key}: ${f.error}`); process.exitCode = 1; }
}

export { opts, sniffMime, htmlToText, validate, downloadOnce, fetchWithRetries, DownloadError, liveJob, refJob };

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	main().catch((e) => { console.error(e); process.exit(1); });
}
