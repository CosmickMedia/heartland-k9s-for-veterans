#!/usr/bin/env node
/**
 * Generates the three fixture images with sharp and writes media-index.json
 * (same shape tools/fetch-media.mjs produces). Run once; outputs are committed.
 *
 *   node tools/fixtures/payload-mini/generate.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const here = path.dirname(fileURLToPath(import.meta.url));

const images = [
  // 2800x1400 exceeds big_image_size_threshold (2560) so WordPress creates a -scaled copy + original_image.
  { key: 'mini:asset:hero', file: 'media/mini__asset__hero/mini-hero.jpg', w: 2800, h: 1400, bg: '#1c2f4a', fg: '#fbfaf9', label: 'HERO 2800x1400', fmt: 'jpeg',
    title: 'Mini hero', alt: 'Navy banner with the word hero', caption: 'Hero caption', description: 'Generated fixture image (hero).', date: '2024-03-01T10:00:00Z' },
  { key: 'mini:asset:card', file: 'media/mini__asset__card/mini-card.png', w: 400, h: 300, bg: '#b82e45', fg: '#ffffff', label: 'CARD 400x300', fmt: 'png',
    title: 'Mini card', alt: 'Crimson card image', caption: '', description: '', date: '2024-03-02T10:00:00Z' },
  { key: 'mini:asset:portrait', file: 'media/mini__asset__portrait/mini-portrait.png', w: 600, h: 400, bg: '#f3f0eb', fg: '#15191f', label: 'PORTRAIT 600x400', fmt: 'png',
    title: 'Handler portrait (must be scrubbed)', alt: 'Named person alt text (must be scrubbed)', caption: 'Caption with a name (must be scrubbed)', description: '', date: '2024-03-03T10:00:00Z', sensitive: true },
];

const items = {};
for (const img of images) {
  const abs = path.join(here, img.file);
  fs.mkdirSync(path.dirname(abs), { recursive: true });
  const svg = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${img.w}" height="${img.h}">` +
      `<rect width="100%" height="100%" fill="${img.bg}"/>` +
      `<text x="50%" y="50%" font-family="sans-serif" font-size="${Math.round(img.h / 8)}" fill="${img.fg}" text-anchor="middle" dominant-baseline="middle">${img.label}</text>` +
    `</svg>`
  );
  let pipeline = sharp(svg);
  pipeline = img.fmt === 'jpeg' ? pipeline.jpeg({ quality: 70 }) : pipeline.png({ compressionLevel: 9 });
  await pipeline.toFile(abs);
  const buf = fs.readFileSync(abs);
  const meta = await sharp(buf).metadata();
  items[img.key] = {
    file: img.file,
    sha256: crypto.createHash('sha256').update(buf).digest('hex'),
    bytes: buf.length,
    mime: img.fmt === 'jpeg' ? 'image/jpeg' : 'image/png',
    width: meta.width,
    height: meta.height,
    title: img.title,
    alt: img.alt,
    caption: img.caption,
    description: img.description,
    date: img.date,
    ...(img.sensitive ? { sensitive: true } : {}),
  };
  console.log(`${img.key}: ${img.file} ${buf.length} bytes ${meta.width}x${meta.height}`);
}
fs.writeFileSync(path.join(here, 'media-index.json'), JSON.stringify({ generated_at: new Date().toISOString(), items }, null, 2) + '\n');
console.log('wrote media-index.json');
