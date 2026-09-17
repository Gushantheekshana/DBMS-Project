import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = resolve(root, 'node_modules/flowbite/dist/flowbite.min.js');
const target = resolve(root, 'assets/vendor/flowbite/flowbite.min.js');

await mkdir(dirname(target), { recursive: true });
await copyFile(source, target);
const bundled = (await readFile(target, 'utf8')).replace(/\n?\/\/# sourceMappingURL=.*$/u, '');
await writeFile(target, bundled, 'utf8');
