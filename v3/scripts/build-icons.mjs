import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const icons = [
  'calendar-days', 'check', 'chevron-down', 'circle-alert', 'credit-card',
  'dumbbell', 'eye', 'eye-off', 'history', 'layout-dashboard', 'log-out',
  'menu', 'monitor', 'moon', 'scan-line', 'sun', 'user-round-cog', 'users',
  'wallet-cards', 'x'
];
const symbols = [];
for (const name of icons) {
  const source = await readFile(resolve(root, `node_modules/lucide-static/icons/${name}.svg`), 'utf8');
  const viewBox = source.match(/viewBox="([^"]+)"/u)?.[1] ?? '0 0 24 24';
  const body = source.replace(/^.*?<svg[^>]*>/su, '').replace(/<\/svg>\s*$/su, '').trim();
  symbols.push(`<symbol id="icon-${name}" viewBox="${viewBox}">${body}</symbol>`);
}
const sprite = `<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">${symbols.join('')}</svg>\n`;
const target = resolve(root, 'assets/icons/lucide.svg');
await mkdir(dirname(target), { recursive: true });
await writeFile(target, sprite, 'utf8');
