#!/usr/bin/env node
/**
 * Build the heartland-k9s icon sprite from lucide-static plus a small set of
 * embedded brand glyphs (Simple Icons, CC0).
 *
 * Reads node_modules/lucide-static/icons/<name>.svg for every icon in the
 * site list below plus every name found in an `icons` array anywhere inside
 * discovery/ref/reference-content.json, appends the BRAND_ICONS below, and emits:
 *
 *   theme/heartland-k9s/assets/dist/icons.svg   symbol sprite (#hk9-icon-<name>)
 *   theme/heartland-k9s/assets/dist/icons.json  sorted array of icon names
 *   docs/licenses/LICENSE-lucide.txt            ISC notice from the package
 *   docs/licenses/LICENSE-simple-icons.txt      CC0 notice + provenance of the brand paths
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
const BRAND_LICENSE_OUT = path.join(ROOT, 'docs', 'licenses', 'LICENSE-simple-icons.txt');

/** Icons used by the reference design (also re-derived from reference-content.json). */
const REFERENCE_ICONS = [
	'arrow-right', 'calendar', 'check', 'circle-alert', 'circle-check', 'clipboard-check',
	'clock', 'dog', 'dollar-sign', 'file-text', 'graduation-cap', 'hand-heart', 'heart',
	'heart-handshake', 'heart-pulse', 'mail', 'map-pin', 'menu', 'phone', 'phone-call',
	'qr-code', 'quote', 'shield-alert', 'shield-check', 'users', 'x',
];

/** Extra icons useful for the WordPress build (footer, listings, admin pickers). */
const EXTRA_ICONS = [
	'external-link', 'chevron-left', 'chevron-right', 'chevron-down', 'search', 'image',
	'download', 'ticket', 'building-2', 'paw-print', 'star', 'info', 'alert-triangle',
];

/**
 * Brand glyphs for the footer social links (facebook, instagram, youtube, linkedin,
 * x-social, tiktok). lucide-static >= 1.0 ships no brand icons,
 * so these are the 24x24 filled paths from Simple Icons (https://simpleicons.org,
 * CC0 1.0 — docs/licenses/LICENSE-simple-icons.txt), embedded verbatim so the build
 * needs no extra dependency. Emitted as filled symbols (fill="currentColor",
 * stroke="none"), unlike the stroked lucide set.
 *
 * The X logo is deliberately named `x-social`: `x` is lucide's close glyph.
 * LinkedIn was removed from Simple Icons in v14.0.0 at LinkedIn's request; its path
 * is the one shipped by the last release that carried it (v13.21.0, same CC0 terms).
 */
const BRAND_ICONS_SOURCE = {
	package: 'simple-icons',
	version: '16.30.0',
	url: 'https://github.com/simple-icons/simple-icons',
	license: 'CC0-1.0',
};
const BRAND_ICONS = {
	facebook: 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
	instagram: 'M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077',
	youtube: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
	linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
	'x-social': 'M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z',
	tiktok: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
};
const BRAND_ICON_VERSIONS = { linkedin: '13.21.0' };

/** CC0 1.0 Universal, verbatim from the simple-icons package LICENSE.md. */
const CC0_TEXT = `CC0 1.0 Universal

Statement of Purpose

The laws of most jurisdictions throughout the world automatically confer exclusive Copyright and Related Rights (defined below) upon the creator and subsequent owner(s) (each and all, an “owner”) of an original work of authorship and/or a database (each, a “Work”).

Certain owners wish to permanently relinquish those rights to a Work for the purpose of contributing to a commons of creative, cultural and scientific works (“Commons”) that the public can reliably and without fear of later claims of infringement build upon, modify, incorporate in other works, reuse and redistribute as freely as possible in any form whatsoever and for any purposes, including without limitation commercial purposes. These owners may contribute to the Commons to promote the ideal of a free culture and the further production of creative, cultural and scientific works, or to gain reputation or greater distribution for their Work in part through the use and efforts of others.

For these and/or other purposes and motivations, and without any expectation of additional consideration or compensation, the person associating CC0 with a Work (the “Affirmer”), to the extent that he or she is an owner of Copyright and Related Rights in the Work, voluntarily elects to apply CC0 to the Work and publicly distribute the Work under its terms, with knowledge of his or her Copyright and Related Rights in the Work and the meaning and intended legal effect of CC0 on those rights.

1. Copyright and Related Rights. A Work made available under CC0 may be protected by copyright and related or neighboring rights (“Copyright and Related Rights”). Copyright and Related Rights include, but are not limited to, the following:
    1. the right to reproduce, adapt, distribute, perform, display, communicate, and translate a Work;
    2. moral rights retained by the original author(s) and/or performer(s);
    3. publicity and privacy rights pertaining to a person’s image or likeness depicted in a Work;
    4. rights protecting against unfair competition in regards to a Work, subject to the limitations in paragraph 4(i), below;
    5. rights protecting the extraction, dissemination, use and reuse of data in a Work;
    6. database rights (such as those arising under Directive 96/9/EC of the European Parliament and of the Council of 11 March 1996 on the legal protection of databases, and under any national implementation thereof, including any amended or successor version of such directive); and
    7. other similar, equivalent or corresponding rights throughout the world based on applicable law or treaty, and any national implementations thereof.

2. Waiver. To the greatest extent permitted by, but not in contravention of, applicable law, Affirmer hereby overtly, fully, permanently, irrevocably and unconditionally waives, abandons, and surrenders all of Affirmer’s Copyright and Related Rights and associated claims and causes of action, whether now known or unknown (including existing as well as future claims and causes of action), in the Work (i) in all territories worldwide, (ii) for the maximum duration provided by applicable law or treaty (including future time extensions), (iii) in any current or future medium and for any number of copies, and (iv) for any purpose whatsoever, including without limitation commercial, advertising or promotional purposes (the “Waiver”). Affirmer makes the Waiver for the benefit of each member of the public at large and to the detriment of Affirmer’s heirs and successors, fully intending that such Waiver shall not be subject to revocation, rescission, cancellation, termination, or any other legal or equitable action to disrupt the quiet enjoyment of the Work by the public as contemplated by Affirmer’s express Statement of Purpose.

3. Public License Fallback. Should any part of the Waiver for any reason be judged legally invalid or ineffective under applicable law, then the Waiver shall be preserved to the maximum extent permitted taking into account Affirmer’s express Statement of Purpose. In addition, to the extent the Waiver is so judged Affirmer hereby grants to each affected person a royalty-free, non transferable, non sublicensable, non exclusive, irrevocable and unconditional license to exercise Affirmer’s Copyright and Related Rights in the Work (i) in all territories worldwide, (ii) for the maximum duration provided by applicable law or treaty (including future time extensions), (iii) in any current or future medium and for any number of copies, and (iv) for any purpose whatsoever, including without limitation commercial, advertising or promotional purposes (the “License”). The License shall be deemed effective as of the date CC0 was applied by Affirmer to the Work. Should any part of the License for any reason be judged legally invalid or ineffective under applicable law, such partial invalidity or ineffectiveness shall not invalidate the remainder of the License, and in such case Affirmer hereby affirms that he or she will not (i) exercise any of his or her remaining Copyright and Related Rights in the Work or (ii) assert any associated claims and causes of action with respect to the Work, in either case contrary to Affirmer’s express Statement of Purpose.

4. Limitations and Disclaimers.
    1. No trademark or patent rights held by Affirmer are waived, abandoned, surrendered, licensed or otherwise affected by this document.
    2. Affirmer offers the Work as-is and makes no representations or warranties of any kind concerning the Work, express, implied, statutory or otherwise, including without limitation warranties of title, merchantability, fitness for a particular purpose, non infringement, or the absence of latent or other defects, accuracy, or the present or absence of errors, whether or not discoverable, all to the greatest extent permissible under applicable law.
    3. Affirmer disclaims responsibility for clearing rights of other persons that may apply to the Work or any use thereof, including without limitation any person’s Copyright and Related Rights in the Work. Further, Affirmer disclaims responsibility for obtaining any necessary consents, permissions or other rights required for any use of the Work.
    4. Affirmer understands and acknowledges that Creative Commons is not a party to this document and has no duty or obligation with respect to this CC0 or use of the Work.

For more information, please see <https://creativecommons.org/publicdomain/zero/1.0>.
`;

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

	for (const [name, d] of Object.entries(BRAND_ICONS).sort(([a], [b]) => a.localeCompare(b))) {
		if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name)) throw new Error(`brand icon ${name}: invalid name`);
		if (included.includes(name)) throw new Error(`brand icon ${name}: clashes with a lucide icon`);
		if (!/^[MmZzLlHhVvCcSsQqTtAa0-9 .,\-]+$/.test(d)) throw new Error(`brand icon ${name}: unexpected characters in path data`);
		symbols.push(`<symbol id="hk9-icon-${name}" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="${d}"/></symbol>`);
		included.push(name);
	}
	included.sort();

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

	const brandList = Object.keys(BRAND_ICONS)
		.sort()
		.map((name) => `  ${name.padEnd(12)} simple-icons v${BRAND_ICON_VERSIONS[name] ?? BRAND_ICONS_SOURCE.version} icons/${name === 'x-social' ? 'x' : name}.svg`)
		.join('\n');
	await writeFile(
		BRAND_LICENSE_OUT,
		`Simple Icons — https://simpleicons.org (${BRAND_ICONS_SOURCE.url})\n` +
			`Brand glyphs embedded in tools/build-icons.mjs and emitted into the heartland-k9s theme icon sprite\n` +
			`(assets/dist/icons.svg) as filled <symbol> elements for the footer social links:\n\n${brandList}\n\n` +
			`Paths are copied verbatim from the package release named on each line. Simple Icons releases its icon\n` +
			`data under CC0 1.0 Universal (text below). Brand names and logos remain trademarks of their respective\n` +
			`owners; the theme uses them only to link to the organisation's own profiles on those services.\n` +
			`LinkedIn was removed from Simple Icons in v14.0.0 at the brand owner's request; the path above is from the\n` +
			`last release that shipped it, published under the same CC0 terms.\n\n${CC0_TEXT}`,
		'utf8',
	);

	log(`wrote ${path.relative(ROOT, SPRITE_OUT)} (${Buffer.byteLength(sprite)} bytes, ${included.length} symbols)`);
	log(`wrote ${path.relative(ROOT, LIST_OUT)}`);
	log(`wrote ${path.relative(ROOT, LICENSE_OUT)}`);
	log(`wrote ${path.relative(ROOT, BRAND_LICENSE_OUT)}`);

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
