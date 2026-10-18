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
  /** Attend que l'arbre d'accessibilité contienne [motif] (texte ou expression). */
  async texte(motif, timeout = 8000) {
    const fin = Date.now() + timeout;
    const ok = (a) => (motif instanceof RegExp ? motif.test(a) : a.includes(motif));
    while (Date.now() < fin) {
      if (ok(await this.arbre())) return true;
      await this.attendre(400);
    }
    return false;
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

/** Ajoute (ou remplace) le fichier du groupe nommé [groupe] (ex. /Diplôme/) via l'option [option] (Galerie ou Fichier) du feuillet. */
Session.prototype.ajouterFichier = async function (groupe, option, chemin) {
  await this.page.getByRole('group', { name: groupe }).getByRole('button').click();
  await this.attendre(700);
  const [choix] = await Promise.all([this.page.waitForEvent('filechooser', { timeout: 8000 }), this.bouton(option).first().click()]);
  await choix.setFiles(chemin);
  await this.attendre(1000);
};

/** Dans le sélecteur de date Material ouvert : choisit le jour d'aujourd'hui + [n] jours, puis OK. */
Session.prototype.choisirDate = async function (n) {
  const cible = new Date(Date.now() + n * 86400000);
  const jour = cible.getUTCDate();
  const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][cible.getUTCMonth()];
  const motif = new RegExp(`^${jour}, [a-zé]+ ${jour} ${mois} ${cible.getUTCFullYear()}`);
  for (let i = 0; i < 3; i++) {
    const b = this.page.getByRole('button', { name: motif });
    if (await b.count()) { await b.first().click(); break; }
    await this.page.getByRole('button', { name: 'Mois suivant' }).click();
    await this.attendre(300);
  }
  await this.page.getByRole('button', { name: 'OK' }).click();
  await this.attendre(600);
};

/** Passe par l'onglet Profil puis ouvre l'entrée [nom]. */
Session.prototype.profil = async function (nom) {
  await this.cliquer(/^Profil/);
  await this.attendre(800);
  await this.cliquer(nom);
  await this.attendre(1800);
};
Session.prototype.retour = async function () {
  await this.cliquer(/^Retour/);
  await this.attendre(1000);
};

/** Clique l'icône crayon située à droite d'une ligne (seule zone tactile de la ligne). */
Session.prototype.cliquerCrayon = async function (nom) {
  const boite = await this.page.getByRole('button', { name: nom }).first().boundingBox();
  await this.page.mouse.click(boite.x + boite.width - 36, boite.y + boite.height / 2);
  await this.attendre(1300);
};

/** Fait défiler la page (les listes Flutter ne construisent que les éléments proches de l'écran). */
Session.prototype.defiler = async function (dy = 1500) {
  await this.page.mouse.move(195, 420);
  await this.page.mouse.wheel(0, dy);
  await this.attendre(900);
};
