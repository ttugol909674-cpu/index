import { chromium } from 'playwright-core';
import { mkdirSync, rmSync } from 'node:fs';
import path from 'node:path';

const RAIZ = process.env.TGS_VIDEO_WORK || `${process.env.TEMP}\\tgs-video`;
const CHROME = process.env.TGS_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = 'http://127.0.0.1:8098';
const SALIDA = path.join(RAIZ, 'frames');

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, v] = a.replace(/^--/, '').split('=');
    return [k, v === undefined ? true : v];
  })
);
const FPS = Number(args.fps || 30);
const LIMITE = args.max ? Number(args.max) : null;
const CALIDAD = Number(args.quality || 92);

if (args.limpiar) { rmSync(SALIDA, { recursive: true, force: true }); }
mkdirSync(SALIDA, { recursive: true });

const browser = await chromium.launch({
  executablePath: CHROME,
  headless: true,
  args: ['--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1', '--font-render-hinting=none', '--disable-lcd-text'],
});
const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'es-UY' });
const page = await context.newPage();
page.on('pageerror', (e) => console.log('  [error JS]', e.message));

await page.goto(`${BASE}/work/video.html`, { waitUntil: 'load' });
await page.waitForFunction(() => window.TGS_VIDEO && window.TGS_VIDEO.listo, { timeout: 30000 });
await page.evaluate(async () => {
  await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; }))));
});
await page.waitForTimeout(600);

const total = await page.evaluate(() => window.TGS_VIDEO.total);
const cuadroTotal = Math.ceil(total * FPS);
const hasta = LIMITE ? Math.min(cuadroTotal, LIMITE) : cuadroTotal;
console.log(`duracion ${total.toFixed(2)} s  |  ${cuadroTotal} cuadros a ${FPS} fps  |  render ${hasta}`);

const inicio = Date.now();
for (let f = 0; f < hasta; f++) {
  const t = f / FPS;
  await page.evaluate((tt) => window.TGS_VIDEO.seek(tt), t);
  const archivo = path.join(SALIDA, `f${String(f).padStart(5, '0')}.jpg`);
  await page.screenshot({ path: archivo, type: 'jpeg', quality: CALIDAD, animations: 'disabled' });
  if (f % 60 === 0 || f === hasta - 1) {
    const hecho = f + 1;
    const transcurrido = (Date.now() - inicio) / 1000;
    const ritmo = transcurrido / hecho;
    console.log(`  ${String(f).padStart(5)} / ${hasta}  t=${t.toFixed(2)}s  ${ritmo.toFixed(3)} s/cuadro  faltan ~${((hasta - hecho) * ritmo).toFixed(0)} s`);
  }
}

await browser.close();
console.log(`\n${hasta} cuadros en ${((Date.now() - inicio) / 1000).toFixed(1)} s -> ${SALIDA}`);
