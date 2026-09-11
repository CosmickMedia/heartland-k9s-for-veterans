#!/usr/bin/env node
/**
 * Bundle the theme's frontend JavaScript with esbuild.
 *
 *   node tools/build-js.mjs            # one-off build
 *   node tools/build-js.mjs --watch    # rebuild on change
 *
 * assets/src/js/theme.js -> assets/dist/theme.js (IIFE, minified, ES2019, no jQuery)
 */

import * as esbuild from 'esbuild';
import { mkdirSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const themeDir = resolve(root, 'theme/heartland-k9s');
const entry = resolve(themeDir, 'assets/src/js/theme.js');
const outfile = resolve(themeDir, 'assets/dist/theme.js');

mkdirSync(dirname(outfile), { recursive: true });

/** @type {esbuild.BuildOptions} */
const options = {
	entryPoints: [entry],
	outfile,
	bundle: true,
	minify: true,
	format: 'iife',
	target: ['es2019'],
	platform: 'browser',
	sourcemap: false,
	legalComments: 'none',
	logLevel: 'info',
	banner: { js: '/*! heartland-k9s theme.js — vanilla, no jQuery */' },
};

if (process.argv.includes('--watch')) {
	const ctx = await esbuild.context(options);
	await ctx.watch();
	process.stdout.write('[build-js] watching for changes…\n');
} else {
	try {
		await esbuild.build(options);
		const size = statSync(outfile).size;
		process.stdout.write(`[build-js] ${outfile.replace(root + '/', '')}  ${(size / 1024).toFixed(1)} kB\n`);
	} catch (err) {
		process.stderr.write(`[build-js] FAILED\n${err.message}\n`);
		process.exit(1);
	}
}
