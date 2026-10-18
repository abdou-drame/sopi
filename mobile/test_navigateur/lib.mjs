// Outils partagés des scripts Playwright. Tout vise 127.0.0.1.
import { createHmac, randomBytes } from 'node:crypto';
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ICI = dirname(fileURLToPath(import.meta.url));
export const PORT_API = process.env.PORT_API ?? '8765';
export const PORT_WEB = process.env.PORT_WEB ?? '8099';
export const API = `http://127.0.0.1:${PORT_API}/api/v1`;
export const APP = `http://127.0.0.1:${PORT_WEB}`;
if (API.includes('duckdns.org')) throw new Error('Refus : URL de production.');

export const SECRET_WEBHOOK = 'secret-factice-local-uniquement';
export const ADMIN = { identifiant: 'admin-local@sopi.invalid', password: 'MotDePasseLocal-123' };

export const motDePasseJetable = () => `Sp-${randomBytes(9).toString('base64url')}1a`;
export const comptes = () => JSON.parse(readFileSync(join(ICI, '.jetable', 'comptes.json'), 'utf8'));

export async function api(chemin, { methode = 'GET', corps, jeton, formulaire } = {}) {
  const entetes = { Accept: 'application/json' };
  if (jeton) entetes.Authorization = `Bearer ${jeton}`;
  let body;
  if (formulaire) body = formulaire;
  else if (corps !== undefined) { entetes['Content-Type'] = 'application/json'; body = JSON.stringify(corps); }
  const r = await fetch(API + chemin, { method: methode, headers: entetes, body });
  const texte = await r.text();
  let json; try { json = JSON.parse(texte); } catch { json = texte; }
  if (!r.ok) throw Object.assign(new Error(`${methode} ${chemin} -> ${r.status} ${texte.slice(0, 300)}`), { statut: r.status, json });
  return json;
}

export async function connexion(identifiant, password) {
  return (await api('/auth/connexion', { methode: 'POST', corps: { identifiant, password } })).token;
}

/** Faux webhook signé HMAC-SHA256 (le faux prestataire de paiement local). */
export async function webhookPaiement(reference, montant, statut = 'reussi') {
  const corps = JSON.stringify({ reference, statut, montant: Number(montant).toFixed(2) });
  const signature = createHmac('sha256', SECRET_WEBHOOK).update(corps).digest('hex');
  const r = await fetch(`${API}/webhooks/paiement`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Webhook-Signature': signature, Accept: 'application/json' },
    body: corps,
  });
  return r.status;
}

export const jourDans = (n) => {
  const d = new Date(Date.now() + n * 86400000);
  return d.toISOString().slice(0, 10);
};

export const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
