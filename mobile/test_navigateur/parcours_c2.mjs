// C (2/2). Client : fiche, choix du créneau, réservation, paiement (URL, webhook signé simulé, retour dans l'app, statut).
import { writeFileSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHmac } from 'node:crypto';
import { Session, note } from './pilote.mjs';
import { API, etiqJour, rx, ICI, SECRET_WEBHOOK, api, comptes, connexion, webhookPaiement } from './lib.mjs';

const P = 'C-client-reservation';

export default async function parcoursC2() {
  const C = comptes();
  const s = await Session.ouvrir('awa');
  let reference = null;
  let montant = null;
  let reservationId = null;
  const onglets = [];
  s.ctx.on('page', (p) => onglets.push(p));
  s.page.on('response', async (r) => {
    if (/\/reservations\/[^/]+\/payer$/.test(r.url()) && r.request().method() === 'POST') {
      try {
        const j = await r.json();
        reference = j.urlPaiement.split('/').pop();
        montant = j.paiement.montantAcompte;
        reservationId = j.reservation.id;
      } catch { /* corps absent */ }
    }
  });
  try {
    await s.saisir(0, C.client.email);
    await s.saisir(1, C.client.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Awa', 15000);
    await s.attendre(2500);

    // --- Fiche
    await s.cliquer(/^Rechercher/);
    await s.texte('3 prestataires', 12000);
    await s.page.getByRole('group', { name: /Garage Moussa/ }).getByRole('button', { name: 'Réserver' }).click();
    await s.texte('Fiche prestataire', 10000);
    await s.attendre(2500);
    await s.capture(P, 'fiche-prestataire');
    let a = await s.arbre();
    note('C', 'fiche : 3 services avec durée, prix et acompte', /Diagnostic Durée : 30 min 8 000 FCFA Acompte 2 400/.test(a) && /Vidange Durée : 45 min 15 000 FCFA Acompte 4 500/.test(a) && /Freinage/.test(a));
    note('C', 'fiche : « Choisir un créneau » désactivé tant qu\'aucun service n\'est choisi', /button "Choisir un créneau" \[disabled\]/.test(a));
    await s.cliquer(/^Vidange/);
    await s.capture(P, 'fiche-service-choisi');
    a = await s.arbre();
    note('C', 'fiche : service choisi → bouton actif', /Vidange 15 000 FCFA/.test(a) && !/Choisir un créneau" \[disabled\]/.test(a));

    // --- Créneau
    await s.cliquer('Choisir un créneau');
    await s.texte('Heures disponibles', 10000);
    await s.attendre(1500);
    await s.capture(P, 'creneau-ecran');
    await s.cliquer(etiqJour(0));
    await s.attendre(1500);
    await s.capture(P, 'creneau-jour-sans-creneau');
    a = await s.arbre();
    note('C', 'créneau : jour sans créneau → message, « Confirmer la réservation » désactivé', /Aucun créneau libre ce jour-là/.test(a) && /Confirmer la réservation" \[disabled\]/.test(a));
    await s.cliquer(etiqJour(1));
    await s.attendre(1500);
    await s.cliquer('10h00');
    await s.attendre(900);
    await s.capture(P, 'creneau-retenu');
    a = await s.arbre();
    note('C', 'créneau : créneau retenu avec compte à rebours (verrou)', /Créneau retenu pour vous \d\d:\d\d/.test(a) && /Lun\. 19 oct\. 10h00/.test(a), (a.match(/Créneau retenu pour vous \d\d:\d\d/) ?? [''])[0]);
    await s.cliquer('Confirmer la réservation');
    await s.texte('Demande envoyée', 12000);
    await s.attendre(1000);
    await s.capture(P, 'demande-envoyee');
    a = await s.arbre();
    note('C', 'réservation : « Demande envoyée », aucun paiement maintenant', /Demande envoyée/.test(a) && /Aucun paiement maintenant/.test(a) && /15 000 FCFA/.test(a));
    await s.cliquer('Voir ma réservation');
    await s.texte('En attente de la réponse', 10000);
    await s.attendre(1000);
    await s.capture(P, 'reservation-en-attente');
    a = await s.arbre();
    note('C', 'détail : en attente, acompte 4 500, reste 10 500, bouton « Annuler la demande »', /En attente/.test(a) && /4 500 FCFA/.test(a) && /10 500 FCFA/.test(a) && /Annuler la demande/.test(a));

    // --- Acceptation par le prestataire (via l'API : l'écran prestataire est testé au parcours D)
    const t = await connexion(C.prestataires.atelier.email, C.prestataires.atelier.password);
    const liste = await api('/prestataire/reservations', { jeton: t });
    const demande = (liste.data ?? liste).find((r) => r.statut === 'en_attente');
    await api(`/prestataire/reservations/${demande.id}/accepter`, { methode: 'POST', jeton: t });
    await s.retour();
    await s.cliquer(/^Réservations/);
    await s.texte('Payer', 8000);
    await s.attendre(1500);
    await s.capture(P, 'reservations-acceptee-a-payer');
    a = await s.arbre();
    note('C', 'liste : réservation « Acceptée », acompte 4 500 FCFA à payer', /Acceptée/.test(a) && /Acompte 4 500 FCFA à payer/.test(a));

    // --- Paiement : page de récapitulatif
    await s.cliquer('Payer');
    await s.texte('Confirmez et payez', 10000);
    await s.attendre(1000);
    await s.capture(P, 'paiement-recapitulatif');
    a = await s.arbre();
    note('C', 'paiement : récapitulatif, montants du serveur, délai « Il reste 1 h 5x »', /Acompte à payer maintenant \(30 %\) 4 500 FCFA/.test(a) && /Il reste 1 h/.test(a));
    await s.cliquer(/^Payer \d/);
    await s.attendre(3500);
    await s.capture(P, 'paiement-apres-clic');
    note('C', 'paiement : URL de paiement reçue du serveur (https://paiement.fake.invalid/…)', !!reference && montant === '4500.00', `référence ${reference ? 'reçue' : 'absente'}, montant ${montant}`);
    // Observation, pas une assertion : url_launcher web lance après une requête asynchrone (sans geste utilisateur), le navigateur
    // headless n'ouvre alors aucun onglet. L'ouverture réelle de Wave/Orange Money relève du test sur téléphone.
    const ouverts = onglets.filter((p) => !p.isClosed());
    note('C', 'paiement : ouverture de la page de paiement en navigateur (observation)', true, ouverts.length ? ouverts.map((p) => p.url()).join(' ') : 'aucun onglet ouvert par Chrome headless (lancement sans geste utilisateur) : non vérifiable ici');

    // Signature invalide : refusée.
    const corps = JSON.stringify({ reference, statut: 'reussi', montant: '4500.00' });
    const mauvais = await fetch(`${API}/webhooks/paiement`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Webhook-Signature': createHmac('sha256', 'mauvais-secret').update(corps).digest('hex'), Accept: 'application/json' }, body: corps });
    note('C', 'webhook : signature invalide refusée', mauvais.status >= 400 && mauvais.status < 500, `HTTP ${mauvais.status}`);
    a = await s.arbre();
    note('C', 'paiement : après signature invalide, l\'app n\'affiche pas de succès', !/réussi/.test(a));

    // --- Échec du paiement (webhook « echoue »), retour dans l'app
    const retourApp = async () => {
      for (const p of s.ctx.pages()) if (p !== s.page && !p.isClosed()) await p.close().catch(() => {});
      await s.page.bringToFront();
      // Simule « l'utilisateur quitte puis revient dans l'app » : Flutter web lit document.visibilityState.
      await s.page.evaluate(() => {
        const def = (v) => Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => v });
        def('hidden'); document.dispatchEvent(new Event('visibilitychange'));
        setTimeout(() => { def('visible'); document.dispatchEvent(new Event('visibilitychange')); }, 300);
      });
      await s.attendre(4000);
    };
    note('C', 'webhook « echoue » accepté', (await webhookPaiement(reference, montant, 'echoue')) === 200);
    await retourApp();
    await s.texte(/Paiement|Réessayer|non abouti/, 8000);
    await s.capture(P, 'paiement-echec');
    a = await s.arbre();
    console.log('APRÈS ÉCHEC\n' + a.split('\n').slice(0, 14).join('\n'));
    note('C', 'paiement échoué : « non abouti » et bouton « Réessayer le paiement »', /non abouti/.test(a) && /Réessayer le paiement/.test(a));
    await s.cliquer('Réessayer le paiement');
    await s.attendre(2000);
    await s.capture(P, 'paiement-reessai');
    const ancienne = reference;
    await s.cliquer(/^Payer \d/);
    await s.attendre(3500);
    note('C', 'réessai : nouvelle URL de paiement', !!reference && reference !== ancienne, `${ancienne === reference ? 'même' : 'nouvelle'} référence`);

    // --- Succès du paiement
    note('C', 'webhook « reussi » signé accepté', (await webhookPaiement(reference, montant, 'reussi')) === 200);
    note('C', 'webhook rejoué : traité sans effet de bord (idempotent)', [200, 409].includes(await webhookPaiement(reference, montant, 'reussi')));
    await retourApp();
    await s.texte('Paiement', 10000);
    await s.attendre(1500);
    await s.capture(P, 'paiement-reussi');
    a = await s.arbre();
    note('C', 'paiement réussi : écran « Paiement réussi ! » avec acompte payé', /réussi/.test(a) && /Acompte payé/.test(a), a.split('\n').slice(0, 6).join(' | '));
    await s.cliquer('Voir ma réservation');
    await s.attendre(3000);
    await s.capture(P, 'reservation-confirmee');
    a = await s.arbre();
    note('C', 'détail : statut Confirmée et code de réservation affiché', /Confirmée/.test(a) && /Code de démarrage/.test(a), a.split('\n').slice(0, 5).join(' | '));
    const detail = await api(`/reservations/${reservationId}`, { jeton: await connexion(C.client.email, C.client.password) });
    const dossier = join(ICI, '.jetable');
    writeFileSync(join(dossier, 'etat.json'), JSON.stringify({ reservationId, code: detail.codeReservation ?? detail.code ?? null, statut: detail.statut ?? detail.data?.statut }, null, 2));
    note('C', 'API : réservation confirmée côté serveur', (detail.statut ?? detail.data?.statut) === 'confirmee', JSON.stringify(detail).slice(0, 200).replace(/"code[^,]*,?/i, '"code":"…",'));
  } catch (e) {
    note('C', 'exception', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-c2').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('C', 'erreurs console (4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]|ERR_NAME_NOT_RESOLVED/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer().catch(() => {});
  }
}
