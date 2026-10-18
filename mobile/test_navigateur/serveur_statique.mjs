// Serveur statique minimal pour build/web (repli sur index.html), uniquement sur 127.0.0.1.
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';

const [dossier, port] = [process.argv[2], Number(process.argv[3])];
const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.mjs': 'text/javascript', '.json': 'application/json',
  '.png': 'image/png', '.ico': 'image/x-icon', '.wasm': 'application/wasm', '.otf': 'font/otf', '.ttf': 'font/ttf', '.css': 'text/css' };

createServer(async (req, res) => {
  const chemin = normalize(decodeURIComponent(new URL(req.url, 'http://x').pathname)).replace(/^[\\/]+/, '');
  if (chemin.includes('..')) { res.writeHead(400).end(); return; }
  for (const f of [join(dossier, chemin), join(dossier, 'index.html')]) {
    try {
      const corps = await readFile(f);
      res.writeHead(200, { 'Content-Type': types[extname(f)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' }).end(corps);
      return;
    } catch { /* essai suivant */ }
  }
  res.writeHead(404).end();
}).listen(port, '127.0.0.1', () => console.log(`Serveur statique : http://127.0.0.1:${port}`));
