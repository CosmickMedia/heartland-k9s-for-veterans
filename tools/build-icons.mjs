#!/usr/bin/env node
/**
 * Build the heartland-k9s icon sprite from lucide-static.
 *
 * Reads node_modules/lucide-static/icons/<name>.svg for every icon in the
 * site list below plus every name found in an `icons` array anywhere inside
 * discovery/ref/reference-content.json, and emits:
 *
 *   theme/heartland-k9s/assets/dist/icons.svg   symbol sprite (#hk9-icon-<name>)
 *   theme/heartland-k9s/assets/dist/icons.json  sorted array of icon names
 *   docs/licenses/LICENSE-lucide.txt            ISC notice from the package
 *
 * Names that do not exist in lucide-static are reported and skipped; the
 * script exits 1 if a name from the *reference* list is missing (those are
 * required by the design), and 0 otherwise.
 *
 * Usage: node tools/build-icons.mjs [--quiet]
 */

import { createRequire } from 'node:module';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);
const LUCIDE_DIR = path.dirname(require.resolve('lucide-static/package.json'));
const LUCIDE_PKG = require('lucide-static/package.json');
const ICONS_DIR = path.join(LUCIDE_DIR, 'icons');

const REFERENCE_JSON = path.join(ROOT, 'discovery', 'ref', 'reference-content.json');
const DIST_DIR = path.join(ROOT, 'theme', 'heartland-k9s', 'assets', 'dist');
const SPRITE_OUT = path.join(DIST_DIR, 'icons.svg');
const LIST_OUT = path.join(DIST_DIR, 'icons.json');
const LICENSE_OUT = path.join(ROOT, 'docs', 'licenses', 'LICENSE-lucide.txt');

/** Icons used by the reference design (also re-derived from reference-content.json). */
const REFERENCE_ICONS = [
	'arrow-right', 'calendar', 'check', 'circle-alert', 'circle-check', 'clipboard-check',
	'clock', 'dog', 'dollar-sign', 'file-text', 'graduation-cap', 'hand-heart', 'heart',
	'heart-handshake', 'heart-pulse', 'mail', 'map-pin', 'menu', 'phone', 'phone-call',
	'qr-code', 'quote', 'shield-alert', 'shield-check', 'users', 'x',
];

/** Extra icons useful for the WordPress build (footer, listings, admin pickers). */
const EXTRA_ICONS = [
	'facebook', 'instagram', 'youtube', 'linkedin', 'external-link', 'chevron-left',
	'chevron-right', 'chevron-down', 'search', 'image', 'download', 'ticket', 'building-2',
	'paw-print', 'star', 'info', 'alert-triangle',
];

const QUIET = process.argv.includes('--quiet');
const log = (...args) => { if (!QUIET) console.log('build-icons:', ...args); };

/** Normalise a free-form icon reference to a lucide slug, or null if it is not one. */
function toSlug(value) {
	if (typeof value !== 'string') return null;
	const slug = value.trim().toLowerCase().replace(/\s*\(.*\)\s*$/, ''); // "circle-alert (red)" -> "circle-alert"
	return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) ? slug : null;
}

/** Collect every string in any `icons` array, recursively. */
function collectReferenceIcons(node, found = new Set()) {
	if (Array.isArray(node)) {
		for (const item of node) collectReferenceIcons(item, found);
	} else if (node && typeof node === 'object') {
		for (const [key, value] of Object.entries(node)) {
			if (key === 'icons' && Array.isArray(value)) {
				for (const entry of value) {
					const slug = toSlug(typeof entry === 'object' && entry ? (entry.name ?? entry.icon) : entry);
					if (slug) found.add(slug);
				}
			}
			collectReferenceIcons(value, found);
		}
	}
	return found;
}

/**
 * Extract the inner markup of a lucide SVG file and collapse whitespace.
 * lucide-static files are `<!-- license --><svg ...>\n  <path .../>\n</svg>`.
 */
function innerSvg(source, name) {
	const match = source.match(/<svg\b[^>]*>([\s\S]*?)<\/svg>/i);
	if (!match) throw new Error(`${name}.svg: no <svg> element found`);
	const inner = match[1]
		.replace(/<!--[\s\S]*?-->/g, '')
		.replace(/\s*\n\s*/g, '')
		.replace(/\s{2,}/g, ' ')
		.replace(/\s+\/>/g, '/>')
		.trim();
	if (!inner) throw new Error(`${name}.svg: empty icon body`);
	if (/<(script|foreignObject|image|use)\b/i.test(inner)) throw new Error(`${name}.svg: unexpected element`);
	if (/\bon[a-z]+\s*=/i.test(inner)) throw new Error(`${name}.svg: inline event handler`);
	const viewBox = source.match(/viewBox="([^"]+)"/)?.[1];
	if (viewBox !== '0 0 24 24') throw new Error(`${name}.svg: unexpected viewBox ${viewBox}`);
	return inner;
}

async function main() {
	const wanted = new Set(REFERENCE_ICONS);
	let fromReference = new Set();
	if (existsSync(REFERENCE_JSON)) {
		fromReference = collectReferenceIcons(JSON.parse(await readFile(REFERENCE_JSON, 'utf8')));
		for (const name of fromReference) wanted.add(name);
		log(`reference-content.json icons: ${[...fromReference].sort().join(', ')}`);
	} else {
		log(`reference file not found, skipping: ${path.relative(ROOT, REFERENCE_JSON)}`);
	}
	const required = new Set(wanted);
	for (const name of EXTRA_ICONS) wanted.add(name);

	const names = [...wanted].sort();
	const symbols = [];
	const included = [];
	const missing = [];

	for (const name of names) {
		const file = path.join(ICONS_DIR, `${name}.svg`);
		if (!existsSync(file)) {
			missing.push(name);
			continue;
		}
		const body = innerSvg(await readFile(file, 'utf8'), name);
		symbols.push(
			`<symbol id="hk9-icon-${name}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${body}</symbol>`,
		);
		included.push(name);
	}

	const sprite = [
		'<?xml version="1.0" encoding="UTF-8"?>',
		`<!-- Icon sprite for heartland-k9s. GENERATED by tools/build-icons.mjs from lucide-static v${LUCIDE_PKG.version} (ISC, see docs/licenses/LICENSE-lucide.txt). Do not edit. -->`,
		'<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden">',
		...symbols,
		'</svg>',
		'',
	].join('\n');

	await mkdir(DIST_DIR, { recursive: true });
	await mkdir(path.dirname(LICENSE_OUT), { recursive: true });
	await writeFile(SPRITE_OUT, sprite, 'utf8');
	await writeFile(LIST_OUT, `${JSON.stringify(included, null, '\t')}\n`, 'utf8');

	const license = await readFile(path.join(LUCIDE_DIR, 'LICENSE'), 'utf8');
	await writeFile(
		LICENSE_OUT,
		`Lucide Icons (lucide-static v${LUCIDE_PKG.version}) — https://lucide.dev\nUsed for the heartland-k9s theme icon sprite (assets/dist/icons.svg).\n\n${license}`,
		'utf8',
	);

	log(`wrote ${path.relative(ROOT, SPRITE_OUT)} (${Buffer.byteLength(sprite)} bytes, ${included.length} symbols)`);
	log(`wrote ${path.relative(ROOT, LIST_OUT)}`);
	log(`wrote ${path.relative(ROOT, LICENSE_OUT)}`);

	const missingRequired = missing.filter((name) => required.has(name));
	const missingExtra = missing.filter((name) => !required.has(name));
	if (missingExtra.length) console.warn(`build-icons: not in lucide-static (skipped): ${missingExtra.join(', ')}`);
	if (missingRequired.length) {
		console.error(`build-icons: REQUIRED icons missing from lucide-static: ${missingRequired.join(', ')}`);
		process.exitCode = 1;
	}
}

main().catch((error) => {
	console.error('build-icons: failed:', error.message);
	process.exitCode = 1;
});
