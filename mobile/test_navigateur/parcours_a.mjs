// A. Prestataire : inscription en 3 étapes, suivi du dossier, connexion refusée en attente, connexion après activation.
import { join } from 'node:path';
import { Session, note } from './pilote.mjs';
import { ICI, api, connexion, ADMIN, motDePasseJetable } from './lib.mjs';
import { writeFileSync } from 'node:fs';

const f = (n) => join(ICI, '.jetable', 'fichiers', n);
const P = 'A-inscription-prestataire';

async function etape1(s, { entreprise, quartier, autre }) {
  await s.saisir(0, entreprise);
  await s.cliquer('Coiffure');
  await s.cliquer('Choisir un quartier');
  await s.capture(P, 'liste-quartiers');
  if (autre) { await s.cliquer('Autre quartier'); await s.saisir(1, quartier); await s.saisir(2, 'Rue 5'); }
  else { await s.cliquer(quartier); await s.saisir(1, 'Rue 5'); }
  await s.capture(P, 'etape1-remplie');
  await s.cliquer('Continuer');
}
async function etape2(s, d) {
  await s.saisir(0, d.prenom); await s.saisir(1, d.nom); await s.saisir(2, d.tel); await s.saisir(3, d.email);
  await s.saisir(4, d.mdp); await s.saisir(5, d.mdp);
  await s.capture(P, 'etape2-remplie');
  await s.cliquer('Continuer');
}

export default async function parcoursA(ctx) {
  const s = await Session.ouvrir('presta');
  try {
    await s.capture(P, 'connexion');
    await s.cliquer('Devenir prestataire');
    await s.capture(P, 'etape1-vide');

    // Validation : étape 1 vide.
    await s.cliquer('Continuer');
    await s.capture(P, 'etape1-erreurs');
    note('A', 'étape 1 vide refusée avec messages', !(await s.arbre()).includes('Étape 2 sur 3'));

    // A1 : quartier choisi dans la liste.
    await etape1(s, { entreprise: 'Beauté Ndeye', quartier: 'Keur Massar Nord', autre: false });
    note('A', 'étape 1 (quartier de la liste) → étape 2', (await s.arbre()).includes('Étape 2 sur 3'));
    await s.cliquer('Continuer');
    await s.capture(P, 'etape2-erreurs');
    note('A', 'étape 2 vide refusée', (await s.arbre()).includes('Étape 2 sur 3'));

    const mdp = motDePasseJetable();
    const d = { prenom: 'Ndeye', nom: 'Gueye', tel: '77' + String(Math.floor(1000000 + Math.random() * 8999999)), email: `ndeye.${Date.now()}@sopi.invalid`, mdp };
    await etape2(s, d);
    note('A', 'étape 2 → étape 3', (await s.arbre()).includes('Étape 3 sur 3'));

    await s.cliquer('Envoyer mon dossier');
    await s.capture(P, 'etape3-sans-justificatif');
    note('A', 'envoi sans justificatif refusé', (await s.arbre()).includes('Étape 3 sur 3'));

    await s.ajouterFichier(/Carte/, 'Galerie', f('cni.png'));
    await s.ajouterFichier(/Diplôme/, 'Fichier', f('diplome.pdf'));
    await s.capture(P, 'etape3-remplie');
    await s.cliquer('Envoyer mon dossier');
    const suivi = await s.texte(/Dossier en cours d'examen/, 15000);
    await s.capture(P, 'suivi-en-attente');
    note('A', 'dossier envoyé, écran de suivi « en cours d\'examen »', suivi);

    await s.cliquer('Actualiser');
    await s.capture(P, 'suivi-actualise');
    note('A', 'suivi : Actualiser sans erreur', await s.texte(/Dossier en cours d'examen/));

    // Rechargement de la page : le jeton de suivi doit survivre (stockage web).
    await s.page.reload(); await s.page.waitForSelector('flt-semantics-placeholder', { state: 'attached' });
    await s.page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click()); await s.attendre(3000);
    await s.capture(P, 'suivi-apres-rechargement');
    note('A', 'suivi retrouvé après rechargement (jeton conservé)', await s.texte(/Dossier en cours d'examen/, 5000));

    // Connexion refusée tant que le dossier est en attente.
    await s.cliquer('Retour à la connexion');
    await s.saisir(0, d.email); await s.saisir(1, d.mdp);
    await s.cliquer('Se connecter'); await s.attendre(2500);
    await s.capture(P, 'connexion-refusee-attente');
    const refus = await s.arbre();
    note('A', 'connexion refusée en attente de validation', !refus.includes('Mon profil') && !refus.includes('Tableau'), refus.split('\n').filter((l) => /text:/.test(l)).slice(0, 3).join(' | '));

    // Activation par l'administrateur local, puis connexion réussie.
    const admin = await connexion(ADMIN.identifiant, ADMIN.password);
    const liste = await api('/admin/prestataires/demandes', { jeton: admin });
    const dossier = (liste.data ?? liste).find((x) => x.nomEntreprise === 'Beauté Ndeye');
    await api(`/admin/prestataires/${dossier.id}/accepter`, { methode: 'POST', jeton: admin, corps: {} });
    await s.page.reload(); await s.page.waitForSelector('flt-semantics-placeholder', { state: 'attached' });
    await s.page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click()); await s.attendre(3000);
    await s.capture(P, 'apres-activation');
    let a = await s.arbre();
    note('A', 'suivi : « Dossier accepté » après activation', /Dossier accepté/.test(a));
    if (/Retour à la connexion/.test(a)) { await s.cliquer('Retour à la connexion'); await s.attendre(800); }
    await s.saisir(0, d.email); await s.saisir(1, d.mdp); await s.cliquer('Se connecter'); await s.attendre(4000);
    await s.capture(P, 'connecte');
    a = await s.arbre();
    note('A', 'connexion réussie après activation', /Réservations|Tableau|Agenda|Profil/.test(a), a.split('\n').slice(0, 6).join(' | '));
    ctx.beautéNdeye = { email: d.email, mdp: d.mdp, tel: '+221' + d.tel };
  } catch (e) {
    note('A', 'exception', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('A', 'erreurs console (réponses 4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer();
  }
}
