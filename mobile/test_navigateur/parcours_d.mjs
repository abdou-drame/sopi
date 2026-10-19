// D. Prestataire : réception de la demande, acceptation, refus avec motif, annulation avec motif. Client : annulation (acompte non remboursé).
import { Session, note } from './pilote.mjs';
import { api, comptes, connexion, confirmerViaApi, reserverViaApi } from './lib.mjs';

const P = 'D-reponses-annulations';

export default async function parcoursD() {
  const C = comptes();
  const tc = await connexion(C.client.email, C.client.password);
  const tp = await connexion(C.prestataires.atelier.email, C.prestataires.atelier.password);

  // Préparation par l'API : 2 demandes à traiter, 2 réservations confirmées (créneaux libres, jours J+1 et J+2).
  const r1 = await reserverViaApi(tc, 'Garage Moussa', 'Diagnostic', 1, '09');
  const r2 = await reserverViaApi(tc, 'Garage Moussa', 'Freinage', 2, '09');
  const r3 = await reserverViaApi(tc, 'Garage Moussa', 'Vidange', 1, '12');
  const r4 = await reserverViaApi(tc, 'Garage Moussa', 'Vidange', 2, '11');
  await confirmerViaApi(tc, tp, r3);
  await confirmerViaApi(tc, tp, r4);
  const statut = async (id, jeton = tc) => {
    const d = await api(`/reservations/${id}`, { jeton });
    return { statut: d.statut ?? d.data?.statut, paiement: d.paiement?.statut ?? d.data?.paiement?.statut, brut: d };
  };

  // --- Prestataire
  let s = await Session.ouvrir('atelier');
  try {
    await s.saisir(0, C.prestataires.atelier.email);
    await s.saisir(1, C.prestataires.atelier.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Moussa', 15000);
    await s.attendre(3000);
    await s.capture(P, 'tableau-de-bord-demandes');
    let a = await s.arbre();
    note('D', 'tableau de bord : 2 demandes à traiter signalées', /Demandes à traiter 2/.test(a), (a.match(/Demandes à traiter \d+/) ?? [''])[0]);

    await s.cliquer('Tout voir');
    await s.texte('Mes réservations', 8000);
    await s.attendre(2500);
    await s.capture(P, 'liste-a-traiter');
    a = await s.arbre();
    note('D', 'liste « À traiter » : 2 demandes avec délai de réponse et boutons Refuser / Accepter', (a.match(/En attente/g) ?? []).length === 2 && /Il reste 1[12] h/.test(a) && /button "Accepter"/.test(a));
    note('D', 'confidentialité : le client n\'apparaît que sous la forme « Prénom N. » avant confirmation', /Awa N\./.test(a) && !/Awa [A-Z][a-zé]{2,}/.test(a), (a.match(/Awa \S+/) ?? [''])[0]);

    // Détail et acceptation de R1 (Diagnostic, Lun. 19 09h00)
    await s.page.getByRole('group', { name: /Awa N\. En attente Diagnostic/ }).click({ position: { x: 100, y: 20 } });
    await s.texte('Tarif du service', 8000);
    await s.attendre(1000);
    await s.capture(P, 'demande-detail');
    a = await s.arbre();
    note('D', 'détail demande : tarif 8 000, acompte 2 400, commission − 800, crédit 1 600', /8 000 FCFA/.test(a) && /2 400 FCFA/.test(a) && /800 FCFA/.test(a) && /1 600 FCFA/.test(a) && /Il vous reste 1[12] h/.test(a));
    await s.cliquer('Accepter la demande');
    await s.attendre(2500);
    await s.capture(P, 'demande-acceptee');
    a = await s.arbre();
    console.log('APRÈS ACCEPTATION\n' + a.split('\n').slice(0, 12).join('\n'));
    const e1 = await statut(r1);
    note('D', 'acceptation : réservation passée à « acceptée » côté serveur', e1.statut === 'acceptee', e1.statut);

    // Refus de R2 (Freinage) avec motif
    if (/Retour/.test(a)) await s.retour();
    a = await s.arbre();
    if (!/Mes réservations/.test(a)) { await s.cliquer(/^Accueil/); await s.cliquer('Tout voir'); await s.attendre(2000); }
    await s.cliquer('Refuser');
    await s.texte('Motif du refus', 8000);
    await s.attendre(800);
    await s.capture(P, 'refus-formulaire');
    a = await s.arbre();
    note('D', 'refus : bouton « Confirmer le refus » désactivé sans motif', /Confirmer le refus" \[disabled\]/.test(a));
    await s.page.getByRole('textbox').first().click();
    await s.page.keyboard.type('ab');
    await s.attendre(500);
    note('D', 'refus : motif de 2 caractères insuffisant (3 minimum)', /Confirmer le refus" \[disabled\]/.test(await s.arbre()));
    await s.page.keyboard.type('c, indisponible pour ce service');
    await s.attendre(500);
    await s.capture(P, 'refus-motif-saisi');
    await s.cliquer('Confirmer le refus');
    await s.attendre(2500);
    await s.capture(P, 'refus-envoye');
    const e2 = await statut(r2);
    note('D', 'refus : réservation « refusée » avec le motif côté serveur', e2.statut === 'refusee' && /indisponible/.test(JSON.stringify(e2.brut)), e2.statut);

    // Annulation par le prestataire de R3 (confirmée, remboursement intégral)
    a = await s.arbre();
    console.log('APRÈS REFUS\n' + a.split('\n').slice(0, 10).join('\n'));
  } catch (e) {
    note('D', 'exception (prestataire)', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-prestataire').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('D', 'erreurs console (prestataire, 4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer().catch(() => {});
  }
}
