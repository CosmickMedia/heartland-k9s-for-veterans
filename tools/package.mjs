#!/usr/bin/env node
/**
 * Build the distributable ZIPs:
 *   dist/heartland-k9s.zip               (theme; compiled assets, fonts, icons, screenshot — no tests/maps)
 *   dist/heartland-k9s-core.zip          (companion plugin; no tests)
 *   dist/heartland-k9s-payload.zip       (manifest + content + every media file; large — packaged separately)
 *   dist/heartland-k9s-payload-lite.zip  (content-only payload for the live site: manifest + content + the
 *                                         ref assets only, ≈1.5 MB — built from payload-lite/, see
 *                                         `node tools/build-payload.mjs --lite`; uploadable on the admin screen)
 * plus dist/SHA256SUMS. Requires the `zip` CLI. Run `npm run build` first.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const dist = path.join(root, 'dist');
fs.mkdirSync(dist, { recursive: true });

function zip(name, cwd, dir, excludes) {
  const out = path.join(dist, name);
  if (fs.existsSync(out)) fs.unlinkSync(out);
  const ex = excludes.flatMap(e => ['-x', e]);
  execFileSync('zip', ['-r', '-X', '-q', out, dir, ...ex], { cwd, stdio: 'inherit' });
  const bytes = fs.statSync(out).size;
  console.log(`${name}: ${(bytes / 1024 / 1024).toFixed(1)} MB`);
  return out;
}

const commonEx = ['*/.DS_Store', '*/node_modules/*', '*.map', '*/.git*'];
const theme = zip('heartland-k9s.zip', path.join(root, 'theme'), 'heartland-k9s', [...commonEx, 'heartland-k9s/tests/*']);
const plugin = zip('heartland-k9s-core.zip', path.join(root, 'plugin'), 'heartland-k9s-core', [...commonEx, 'heartland-k9s-core/tests/*']);
let payload = null;
if (fs.existsSync(path.join(root, 'payload', 'manifest.json'))) {
  payload = zip('heartland-k9s-payload.zip', root, 'payload', [...commonEx, 'payload/media-index.json.bak']);
} else {
  console.warn('payload/manifest.json missing — run npm run payload:build first; payload ZIP skipped');
}
// The lite ZIP is uploaded through the admin screen, whose archive pre-scan refuses dotfiles and
// script/SVG entries: exclude the sha256 sidecars and anything hidden so the upload is never refused.
let lite = null;
if (fs.existsSync(path.join(root, 'payload-lite', 'manifest.json'))) {
  const manifest = JSON.parse(fs.readFileSync(path.join(root, 'payload-lite', 'manifest.json'), 'utf8'));
  if (manifest.lite !== true) {
    console.warn('payload-lite/manifest.json is not a lite manifest — rebuild it with node tools/build-payload.mjs --lite; lite ZIP skipped');
  } else {
    lite = zip('heartland-k9s-payload-lite.zip', root, 'payload-lite', [...commonEx, '*.sha256', '*.svg', 'payload-lite/media-index.json']);
  }
} else {
  console.warn('payload-lite/manifest.json missing — run node tools/build-payload.mjs --lite first; lite payload ZIP skipped');
}
const sums = [theme, plugin, payload, lite].filter(Boolean).map(f => `${crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex')}  ${path.basename(f)}`);
fs.writeFileSync(path.join(dist, 'SHA256SUMS'), sums.join('\n') + '\n');
console.log(sums.join('\n'));
