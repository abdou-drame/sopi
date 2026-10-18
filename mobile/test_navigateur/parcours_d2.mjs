// D (2/2). Prestataire : annulation avec motif (client remboursé). Client : annulation (acompte non remboursé).
import { Session, note } from './pilote.mjs';
import { api, comptes, connexion } from './lib.mjs';

const P = 'D-reponses-annulations';

export default async function parcoursD2() {
  const C = comptes();
  const tc = await connexion(C.client.email, C.client.password);
  const tp = await connexion(C.prestataires.atelier.email, C.prestataires.atelier.password);
  const liste = (await api('/prestataire/reservations', { jeton: tp })).data;
  const r3 = liste.find((r) => r.statut === 'confirmee' && r.creneau.dateDebut.includes('T12:00'));
  const r4 = liste.find((r) => r.statut === 'confirmee' && r.creneau.dateDebut.includes('T11:00'));
  const etat = async (id) => { const d = await api(`/reservations/${id}`, { jeton: tc }); return d.data ?? d; };

  // --- Prestataire annule R3 (Lun. 19 12h00)
  let s = await Session.ouvrir('atelier');
  try {
    await s.saisir(0, C.prestataires.atelier.email);
    await s.saisir(1, C.prestataires.atelier.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Moussa', 15000);
    await s.attendre(2500);
    await s.cliquer(/^Agenda/);
    await s.attendre(2500);
    await s.cliquer(/^12h00.*Confirmée/);
    await s.texte('Code du client', 8000);
    await s.attendre(1000);
    await s.capture(P, 'rdv-confirme-detail');
    let a = await s.arbre();
    note('D', 'rendez-vous confirmé : champ code, « Démarrer », « Annuler (le client est remboursé) »', /Démarrer la prestation/.test(a) && /Annuler \(le client est remboursé\)/.test(a));
    await s.cliquer(/Annuler/);
    await s.attendre(1200);
    await s.capture(P, 'annulation-prestataire-confirmation');
    await s.cliquer('Non');
    note('D', 'annulation : « Non » conserve le rendez-vous', /Démarrer la prestation/.test(await s.arbre()));
    await s.cliquer(/Annuler/);
    await s.cliquer('Oui, annuler');
    await s.texte('Motif de l', 8000);
    await s.attendre(800);
    await s.capture(P, 'annulation-prestataire-motif');
    a = await s.arbre();
    note('D', 'annulation : « Confirmer l\'annulation » désactivé sans motif', /Confirmer l'annulation" \[disabled\]/.test(a));
    await s.page.getByRole('textbox').first().click();
    await s.page.keyboard.type('Panne de matériel, atelier fermé ce jour-là');
    await s.attendre(500);
    await s.capture(P, 'annulation-prestataire-motif-saisi');
    await s.cliquer("Confirmer l'annulation");
    await s.attendre(3000);
    await s.capture(P, 'annulation-prestataire-faite');
    const e3 = await etat(r3.id);
    note('D', 'annulation prestataire : réservation annulée et acompte REMBOURSÉ', e3.statut === 'annulee' && /rembours/i.test(JSON.stringify(e3.paiement ?? e3)), `${e3.statut} / paiement ${JSON.stringify(e3.paiement?.statut ?? e3.paiement)}`);
  } catch (e) {
    note('D', 'exception (annulation prestataire)', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-annulation-prestataire').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally { await s.fermer().catch(() => {}); }

  // --- Client : annule R4 (Mar. 20 11h00), acompte non remboursé ; voit R2 refusée et R3 annulée
  s = await Session.ouvrir('awa');
  try {
    await s.saisir(0, C.client.email);
    await s.saisir(1, C.client.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Awa', 15000);
    await s.attendre(2500);
    await s.cliquer(/^Réservations/);
    await s.texte('Mes réservations', 8000);
    await s.attendre(2500);
    await s.capture(P, 'client-liste-a-venir');
    let a = await s.arbre();
    console.log('LISTE CLIENT\n' + a.split('\n').slice(0, 20).join('\n'));
    await s.page.getByRole('group', { name: /Mar\. 20 oct\., 11h00/ }).click({ position: { x: 100, y: 20 } });
    await s.texte(/Annuler la réservation|Annuler/, 8000);
    await s.attendre(1500);
    await s.capture(P, 'client-detail-confirmee');
    a = await s.arbre();
    console.log('DETAIL CLIENT\n' + a.split('\n').slice(0, 14).join('\n'));
    await s.cliquer(/^Annuler/);
    await s.attendre(1500);
    await s.capture(P, 'client-annulation-confirmation');
    a = await s.arbre();
    note('D', 'annulation client : la confirmation prévient que l\'acompte n\'est pas remboursé', /rembours/i.test(a), a.split('\n').filter((l) => /rembours|acompte/i.test(l)).join(' | '));
    await s.cliquer('Garder ma réservation');
    note('D', 'annulation client : « Garder ma réservation » conserve la réservation', (await etat(r4.id)).statut === 'confirmee');
    await s.cliquer(/^Annuler/);
    await s.cliquer('Oui, annuler');
    await s.attendre(3000);
    await s.capture(P, 'client-annulee');
    const e4 = await etat(r4.id);
    note('D', 'annulation client : réservation annulée, acompte NON remboursé', e4.statut === 'annulee' && !/rembours/i.test(JSON.stringify(e4.paiement?.statut ?? '')), `${e4.statut} / paiement ${JSON.stringify(e4.paiement?.statut)}`);
    a = await s.arbre();
    if (/Retour/.test(a)) await s.retour();
    await s.cliquer('Annulées');
    await s.attendre(2500);
    await s.capture(P, 'client-onglet-annulees');
    a = await s.arbre();
    note('D', 'onglet « Annulées » : annulations (prestataire et client) listées', (a.match(/Annulée/g) ?? []).length >= 2, (a.match(/group "[^"]*"/g) ?? []).join(' | '));
  } catch (e) {
    note('D', 'exception (annulation client)', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-annulation-client').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally { await s.fermer().catch(() => {}); }
}
