#!/usr/bin/env node
/**
 * Cut a release: bump the theme + plugin (lockstep version), update CHANGELOG.md, build, package into build/,
 * commit, tag vX.Y.Z, push, and create the GitHub release with the two installable ZIPs as assets — which is
 * what the sites' update checkers (plugin-update-checker) look for.
 *
 *   node tools/release.mjs 1.2.1 [--notes="What changed"] [--no-push] [--dry-run]
 *   npm run release -- 1.2.1 --notes="…"
 *
 * Requires a clean working tree, the `gh` CLI authenticated for the repo, and network access.
 */
import { execFileSync, execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const args = process.argv.slice(2);
const version = args.find(a => !a.startsWith('--'));
const opt = Object.fromEntries(args.filter(a => a.startsWith('--')).map(a => { const [k, ...v] = a.slice(2).split('='); return [k, v.length ? v.join('=') : true]; }));
if (!version || !/^\d+\.\d+\.\d+$/.test(version)) { console.error('Usage: node tools/release.mjs X.Y.Z [--notes="…"] [--no-push] [--dry-run]'); process.exit(1); }
const sh = (cmd, opts = {}) => execSync(cmd, { cwd: root, stdio: opts.quiet ? 'pipe' : 'inherit', encoding: 'utf8', ...opts });
const out = (cmd) => execSync(cmd, { cwd: root, encoding: 'utf8' }).trim();

const REPO = 'CosmickMedia/heartland-k9s-for-veterans';
const tag = `v${version}`;

if (out('git status --porcelain')) { console.error('Working tree is not clean — commit or stash first.'); process.exit(1); }
if (out(`git tag -l ${tag}`)) { console.error(`Tag ${tag} already exists.`); process.exit(1); }

const bump = (file, re, replacement) => {
  const p = path.join(root, file); const s = fs.readFileSync(p, 'utf8'); const n = s.replace(re, replacement);
  if (n === s) { console.error(`Version pattern not found in ${file}`); process.exit(1); }
  fs.writeFileSync(p, n); console.log(`bumped ${file}`);
};
bump('theme/heartland-k9s/style.css', /^Version:\s*.+$/m, `Version: ${version}`);
bump('theme/heartland-k9s/functions.php', /define\( 'HK9_THEME_VERSION', '[^']+' \)/, `define( 'HK9_THEME_VERSION', '${version}' )`);
bump('theme/heartland-k9s/readme.txt', /^Stable tag:\s*.+$/m, `Stable tag: ${version}`);
bump('plugin/heartland-k9s-core/heartland-k9s-core.php', /^(\s*\*\s*Version:\s*).+$/m, `$1${version}`);
bump('plugin/heartland-k9s-core/heartland-k9s-core.php', /define\( 'HK9_CORE_VERSION', '[^']+' \)/, `define( 'HK9_CORE_VERSION', '${version}' )`);
bump('package.json', /"version":\s*"[^"]+"/, `"version": "${version}"`);

// Changelog: explicit notes, else commit subjects since the last tag.
const lastTag = (() => { try { return out('git describe --tags --abbrev=0'); } catch { return ''; } })();
let notes = typeof opt.notes === 'string' ? opt.notes : '';
if (!notes) {
  const range = lastTag ? `${lastTag}..HEAD` : 'HEAD';
  notes = out(`git log --no-merges --pretty=format:"- %s" ${range}`).split('\n').filter(l => l && !/^- Release v/.test(l)).slice(0, 40).join('\n') || '- Maintenance release';
}
const date = new Date().toISOString().slice(0, 10);
const clPath = path.join(root, 'CHANGELOG.md');
const cl = fs.existsSync(clPath) ? fs.readFileSync(clPath, 'utf8') : '# Changelog\n\nTheme and plugin share one version number and are released together; each GitHub release carries `heartland-k9s.zip` and `heartland-k9s-core.zip`, which the sites\' update checkers install.\n\n';
fs.writeFileSync(clPath, cl.replace(/\n\n(?=## |$)/, `\n\n## ${tag} — ${date}\n\n${notes.trim()}\n\n`).replace(/\n{3,}/g, '\n\n'));
console.log('changelog updated');

sh('npm run build');
sh('node tools/build-payload.mjs --lite');
sh('node tools/package.mjs');

if (opt['dry-run']) { console.log('dry run: stopping before commit/tag/push'); process.exit(0); }
sh(`git add -A && git commit -q -m "Release ${tag}"`);
sh(`git tag -a ${tag} -m "Release ${tag}"`);
if (!opt['no-push']) {
  const branch = out('git rev-parse --abbrev-ref HEAD');
  sh(`git push origin ${branch} && git push origin ${tag}`);
  const notesFile = path.join(root, 'tools/.cache/release-notes.md'); fs.mkdirSync(path.dirname(notesFile), { recursive: true });
  fs.writeFileSync(notesFile, `${notes.trim()}\n\n---\nInstall/update: WordPress picks these assets up automatically through the built-in update checker (Dashboard → Updates). Manual: upload \`heartland-k9s-core.zip\` under Plugins and \`heartland-k9s.zip\` under Appearance → Themes.\n`);
  execFileSync('gh', ['release', 'create', tag, 'build/heartland-k9s.zip', 'build/heartland-k9s-core.zip', 'build/heartland-k9s-payload-lite.zip', '--repo', REPO, '--title', tag, '--notes-file', notesFile], { cwd: root, stdio: 'inherit' });
  console.log(`\nReleased ${tag}: https://github.com/${REPO}/releases/tag/${tag}`);
} else {
  console.log(`Committed and tagged ${tag} (not pushed). Push with: git push origin HEAD && git push origin ${tag}; then: gh release create ${tag} build/heartland-k9s.zip build/heartland-k9s-core.zip --repo ${REPO}`);
}
