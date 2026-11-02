import { chromium } from 'playwright-core';
import { mkdirSync, statSync } from 'node:fs';
import path from 'node:path';

const RAIZ = process.env.TGS_VIDEO_WORK || `${process.env.TEMP}\\tgs-video`;
const BASE = 'http://127.0.0.1:8099';
const OUT = path.join(RAIZ, 'capturas');
const CHROME = process.env.TGS_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const ANCHO = 1920;
const ALTO = 1080;

mkdirSync(OUT, { recursive: true });

const ROLES = {
  cliente: { email: 'carlos.gomez@gmail.com', password: 'Cliente123!' },
  gestor: { email: 'santiiuyy12@gmail.com', password: 'Gestor123!' },
  admin: { email: 'admin@bluetech.com', password: 'Admin123!' },
};

// Preparaciones especiales: dejan la pantalla en su estado mas interesante
// antes de tomar la captura.
const PREPARAR = {
  // Elegir una fecha para que la app calcule y muestre los horarios libres.
  async 'reservas.php' (page) {
    await page.evaluate(() => {
      const oculto = document.getElementById('fecha');
      const texto = oculto.closest('form').querySelector('input.tgs-fecha-texto');
      texto.value = '05/11/2026';
      texto.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.waitForTimeout(1800);
  },
};

// Capturas adicionales que se toman en el mismo estado.
const CAPTURAS_EXTRA = {
  'reservas.php': [['07b-reservas-disponibilidad', false]],
};

const PLAN = {
  publico: [
    ['index.php', '01-portada'],
    ['taller.php?id=1', '02-sede-centro'],
    ['taller.php?id=2', '03-sede-maldonado'],
    ['login.php', '04-login'],
    ['registro.php', '05-registro'],
  ],
  cliente: [
    ['panel_cliente.php', '06-panel-cliente'],
    ['reservas.php', '07-reservas'],
    ['pagos_cliente.php', '08-pagos-cliente'],
    ['presupuestos.php', '09-presupuestos-cliente'],
    ['historial_cliente.php', '10-historial'],
    ['factura.php?id=1', '11-factura'],
    ['perfil.php', '12-perfil'],
  ],
  gestor: [
    ['dashboard_gestor.php', '13-dashboard-gestor'],
    ['agenda_gestor.php?fecha=2026-11-03', '14-agenda-gestor'],
    ['detalle_reserva.php?id=16', '15-detalle-reserva'],
    ['administrar_reservas.php', '16-gestionar-reservas'],
    ['administrar_pagos.php', '17-gestionar-pagos'],
    ['administrar_presupuestos.php', '18-gestionar-presupuestos'],
    ['notificaciones.php', '19-notificaciones'],
  ],
  admin: [
    ['dashboard_admin.php', '20-dashboard-admin'],
    ['administrar_usuarios.php', '21-gestionar-usuarios'],
    ['administrar_talleres.php', '22-gestionar-talleres'],
    ['administrar_servicios.php', '23-gestionar-servicios'],
    ['permisos.php', '24-matriz-permisos'],
    ['datos_pago.php', '25-datos-pago'],
  ],
};

const browser = await chromium.launch({
  executablePath: CHROME,
  headless: true,
  args: ['--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1', '--font-render-hinting=none'],
});

const errores = [];

async function nuevaPestana() {
  const context = await browser.newContext({
    viewport: { width: ANCHO, height: ALTO },
    deviceScaleFactor: 1,
    locale: 'es-UY',
    timezoneId: 'America/Montevideo',
    reducedMotion: 'reduce',
  });
  const page = await context.newPage();
  page.on('pageerror', (e) => errores.push(`JS ${page.url()}: ${e.message}`));
  page.on('console', (m) => { if (m.type() === 'error') errores.push(`CONSOLA ${page.url()}: ${m.text()}`); });
  return { context, page };
}

async function esperar(page) {
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(700);
}

async function capturar(page, archivo, fullPage = false) {
  const destino = path.join(OUT, `${archivo}.png`);
  await page.screenshot({ path: destino, animations: 'disabled', caret: 'hide', fullPage });
  console.log(`  ${archivo.padEnd(30)} ${(statSync(destino).size / 1024).toFixed(0)} KB`);
}

async function iniciarSesion(page, rol) {
  const { email, password } = ROLES[rol];
  await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }),
    page.click('button[type=submit]'),
  ]);
  await esperar(page);
}

{
  const { context, page } = await nuevaPestana();
  for (const [url, archivo] of PLAN.publico) {
    await page.goto(`${BASE}/${url}`, { waitUntil: 'domcontentloaded' });
    await esperar(page);
    await capturar(page, archivo);
  }
  await context.close();
}

for (const rol of ['cliente', 'gestor', 'admin']) {
  const { context, page } = await nuevaPestana();
  await iniciarSesion(page, rol);
  console.log(`\n[${rol}] sesion iniciada -> ${page.url()}`);
  for (const [url, archivo] of PLAN[rol]) {
    await page.goto(`${BASE}/${url}`, { waitUntil: 'domcontentloaded' });
    await esperar(page);
    if (PREPARAR[url]) await PREPARAR[url](page);
    await capturar(page, archivo);
    for (const [extra, full] of CAPTURAS_EXTRA[url] || []) {
      await capturar(page, extra, full);
    }
  }
  await context.close();
}

await browser.close();

console.log(`\nCapturas en: ${OUT}`);
if (errores.length) {
  console.log('\n--- avisos del navegador ---');
  [...new Set(errores)].slice(0, 25).forEach((e) => console.log('  ' + e));
} else {
  console.log('sin errores de JS en el navegador');
}
