// E (1/2). Prestataire : démarrage avec le code (mauvais, bon, 5 essais), prestation EN_COURS.
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { Session, note } from './pilote.mjs';
import { ICI, etiqJour, api, comptes, confirmerViaApi, connexion, reserverViaApi } from './lib.mjs';

const P = 'E-demarrage-fin-retrait';

/** Saisit un code de 6 caractères dans les six cases (le champ réel est le 2e textbox) : efface puis tape. */
export async function saisirCode(s, code) {
  const champ = s.page.getByRole('textbox').nth(1);
  // Après un mauvais code, l'app vide elle-même les cases : on attend, sinon on efface nous-mêmes.
  for (let i = 0; i < 10 && (await champ.inputValue().catch(() => 'x')) !== ''; i++) {
    if (i === 6) { await champ.focus(); await s.page.keyboard.press('Control+A'); await s.page.keyboard.press('Backspace'); }
    await s.attendre(400);
  }
  await champ.focus();
  await s.attendre(400);
  await s.page.keyboard.type(code, { delay: 60 });
  await s.attendre(300);
  const valeur = await champ.inputValue().catch(() => '?');
  if (valeur !== '' && valeur.length < 6) {
    // Garde-fou : le formateur de l'app retire 0, O, 1, I, L (alphabet du serveur) ; les codes d'essai doivent les éviter.
    console.log(`FRAPPE TRONQUÉE : ${valeur.length} caractère(s) sur 6 retenus après la saisie de « ${code} »`);
  }
  await s.attendre(2200);
}

export default async function parcoursE1() {
  const C = comptes();
  const etat = JSON.parse(readFileSync(join(ICI, '.jetable', 'etat.json'), 'utf8'));
  const tc = await connexion(C.client.email, C.client.password);
  const tp = await connexion(C.prestataires.atelier.email, C.prestataires.atelier.password);
  // Deuxième réservation confirmée (Mer. 21 oct. 09h00) pour l'essai des 5 mauvais codes.
  const r5 = await reserverViaApi(tc, 'Garage Moussa', 'Diagnostic', 3, '09');
  await confirmerViaApi(tc, tp, r5);
  const statutPresta = async (id) => (await api(`/prestataire/reservations/${id}`, { jeton: tp })).statut ?? null;

  const s = await Session.ouvrir('atelier');
  try {
    await s.saisir(0, C.prestataires.atelier.email);
    await s.saisir(1, C.prestataires.atelier.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Moussa', 15000);
    await s.attendre(2500);
    await s.cliquer(/^Agenda/);
    await s.attendre(2500);
    await s.cliquer(/^10h00.*Confirmée/);
    await s.texte('Code du client', 8000);
    await s.attendre(1000);

    await saisirCode(s, 'ZZZZZZ');
    await s.capture(P, 'code-mauvais');
    let a = await s.arbre();
    note('E', 'démarrage : mauvais code refusé (« Code de réservation incorrect. »)', /Code de réservation incorrect/.test(a));
    note('E', 'démarrage : la réservation reste confirmée après un mauvais code', (await statutPresta(etat.reservationId)) === 'confirmee');

    await saisirCode(s, etat.code);
    await s.capture(P, 'code-bon-animation');
    await s.texte(/Terminer/, 10000);
    await s.attendre(1000);
    await s.capture(P, 'prestation-en-cours');
    a = await s.arbre();
    console.log('APRÈS BON CODE\n' + a.split('\n').slice(0, 12).join('\n'));
    note('E', 'démarrage : bon code → prestation « en cours » côté serveur', (await statutPresta(etat.reservationId)) === 'en_cours', await statutPresta(etat.reservationId));
    note('E', 'démarrage : l\'écran passe à « En cours » (bouton Terminer)', /En cours/.test(a) && /Terminer/.test(a), a.split('\n').filter((l) => /button/.test(l)).join(' | '));

    // --- 5 mauvais codes sur la 2e réservation
    if (/Retour/.test(a)) await s.retour();
    await s.cliquer(/^Agenda/);
    await s.attendre(2000);
    await s.cliquer(etiqJour(3));
    await s.attendre(1500);
    await s.cliquer(/09h00.*Confirmée/);
    await s.texte('Code du client', 8000);
    await s.attendre(800);
    const messages = [];
    await s.attendre(2000);
    for (let i = 1; i <= 6; i++) {
      await saisirCode(s, `BAD${i + 1}${i + 1}${i + 1}`.slice(0, 6));
      await s.texte(/incorrect|Trop de tentatives/, 5000);
      a = await s.arbre();
      if (i === 1 && !/incorrect|Trop de tentatives/.test(a)) console.log('ESSAI 1 SANS MESSAGE\n' + a.split('\n').filter((l) => /textbox|button/.test(l)).join('\n'));
      const m = (a.match(/(Code de réservation incorrect\.|Trop de tentatives[^"|]*|[^"|]*réessay[^"|]*)/) ?? ['(aucun message)'])[0];
      messages.push(`${i}: ${m.trim()}`);
      if (i === 5) await s.capture(P, 'code-cinq-essais');
      if (i === 6) await s.capture(P, 'code-sixieme-essai');
    }
    console.log('ESSAIS\n' + messages.join('\n'));
    note('E', '5 essais de mauvais code : message d\'erreur à chaque essai', messages.slice(0, 5).every((m) => /incorrect/.test(m)) && !/Trop de tentatives/.test(messages.slice(0, 5).join()), messages.join(' ; '));
    note('E', 'au-delà de 5 essais par minute : blocage « Trop de tentatives »', /Trop de tentatives/.test(messages[5]), messages[5]);
    note('E', 'après 5 mauvais codes, la réservation n\'a pas démarré', (await statutPresta(r5)) === 'confirmee');
  } catch (e) {
    note('E', 'exception', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-e1').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('E', 'erreurs console (4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer().catch(() => {});
  }
}
