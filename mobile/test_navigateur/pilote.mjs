// Pilote Playwright pour l'app Flutter web : 390x844, sémantique activée, captures numérotées par parcours.
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { APP, ICI } from './lib.mjs';

export const journal = [];
export const note = (parcours, etape, ok, detail = '') => {
  journal.push({ parcours, etape, ok, detail });
  console.log(`${ok ? 'OK  ' : 'ÉCHEC'} [${parcours}] ${etape}${detail ? ' — ' + detail : ''}`);
};

export class Session {
  static async ouvrir(nom, { headless = true } = {}) {
    const s = new Session(nom);
    s.navigateur = await chromium.launch({ channel: 'chrome', headless });
    s.ctx = await s.navigateur.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, locale: 'fr-FR', acceptDownloads: true });
    s.page = await s.ctx.newPage();
    s.erreursConsole = [];
    s.page.on('console', (m) => { if (m.type() === 'error') s.erreursConsole.push(m.text().slice(0, 300)); });
    s.page.on('request', (r) => { if (r.url().includes('duckdns.org')) throw new Error('Appel vers la production détecté : ' + r.url()); });
    await s.page.goto(APP);
    await s.page.waitForSelector('flt-semantics-placeholder', { state: 'attached', timeout: 30000 });
    await s.page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click());
    await s.attendre(1500);
    return s;
  }
  constructor(nom) { this.nom = nom; this.n = 0; }
  attendre(ms) { return this.page.waitForTimeout(ms); }
  async arbre() { return (await this.page.locator('body').ariaSnapshot()).replace(/^(\s*- textbox):.*$/gm, '$1: [valeur masquée]'); }
  bouton(nom, opts = {}) { return this.page.getByRole('button', { name: nom, ...opts }); }
  champs() { return this.page.getByRole('textbox'); }
  /** Saisit dans le n-ième champ de texte visible. */
  async saisir(n, valeur) {
    const c = this.champs().nth(n);
    await c.click();
    await this.page.keyboard.press('Control+A');
    await this.page.keyboard.type(valeur, { delay: 5 });
  }
  async cliquer(nom, opts = {}) { await this.bouton(nom, opts).first().click({ timeout: 10000 }); await this.attendre(700); }
  async texte(motif, timeout = 8000) {
    try { await this.page.getByText(motif).first().waitFor({ timeout }); return true; } catch { return false; }
  }
  async capture(parcours, ecran) {
    const dossier = join(ICI, 'captures', parcours);
    mkdirSync(dossier, { recursive: true });
    this.n++;
    const fichier = join(dossier, `${String(this.n).padStart(2, '0')}-${ecran}-${this.nom}.png`);
    await this.page.screenshot({ path: fichier });
    return fichier;
  }
  async fermer() { await this.navigateur.close(); }
}
