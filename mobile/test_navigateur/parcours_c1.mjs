// C (1/2). Client : inscription, connexion, recherche (métier, quartier, tri, filtres), fiche.
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { Session, note } from './pilote.mjs';
import { ICI, comptes, motDePasseJetable } from './lib.mjs';

const P = 'C-client-reservation';
const nombre = (a) => (a.match(/(\d+|Aucun) prestataires? (disponibles?|trouvés?)/) ?? [])[0] ?? '(compteur absent)';

export default async function parcoursC1() {
  const awa = comptes().client;
  const mdp = motDePasseJetable();

  // --- Inscription d'un nouveau client
  let s = await Session.ouvrir('inscription');
  try {
    await s.capture(P, 'connexion');
    await s.cliquer('Créer un compte');
    await s.capture(P, 'inscription-vide');
    await s.cliquer('Créer mon compte');
    await s.capture(P, 'inscription-erreurs');
    let a = await s.arbre();
    note('C', 'inscription : champs vides refusés (4 messages)', /prénom est obligatoire/.test(a) && /nom est obligatoire/.test(a) && /téléphone est obligatoire/.test(a) && /mot de passe est obligatoire/.test(a));

    await s.saisir(0, 'Ibrahima'); await s.saisir(1, 'Sy'); await s.saisir(2, '771000099');
    await s.saisir(3, 'ibrahima.sy@sopi.invalid'); await s.saisir(4, mdp); await s.saisir(5, mdp + 'x');
    await s.cliquer('Créer mon compte');
    await s.capture(P, 'inscription-mots-de-passe-differents');
    note('C', 'inscription : mots de passe différents refusés', /ne sont pas identiques/.test(await s.arbre()));

    // Téléphone déjà utilisé (celui d'Awa) : refus serveur.
    await s.saisir(2, '771000001'); await s.saisir(5, mdp);
    await s.cliquer('Créer mon compte');
    await s.attendre(2500);
    await s.capture(P, 'inscription-telephone-deja-utilise');
    a = await s.arbre();
    note('C', 'inscription : téléphone déjà utilisé refusé par le serveur', /Créer votre compte client/.test(a) && /déjà/i.test(a), a.split('\n').filter((l) => /déjà|utilisé/i.test(l)).join(' | '));

    await s.saisir(2, '771000099');
    await s.cliquer('Créer mon compte');
    await s.texte('Bonjour Ibrahima', 15000);
    await s.attendre(1500);
    await s.capture(P, 'accueil-nouveau-client');
    note('C', 'inscription valide → accueil « Bonjour Ibrahima »', /Bonjour Ibrahima/.test(await s.arbre()));
    const fichier = join(ICI, '.jetable', 'comptes.json');
    const tous = JSON.parse(readFileSync(fichier, 'utf8'));
    tous.client2 = { email: 'ibrahima.sy@sopi.invalid', password: mdp };
    writeFileSync(fichier, JSON.stringify(tous, null, 2));
  } catch (e) {
    note('C', 'exception (inscription)', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-inscription').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally { await s.fermer().catch(() => {}); }

  // --- Connexion d'Awa, recherche
  s = await Session.ouvrir('awa');
  try {
    await s.saisir(0, awa.email); await s.saisir(1, 'mauvais-mot-de-passe');
    await s.cliquer('Se connecter');
    await s.attendre(2500);
    await s.capture(P, 'connexion-refusee');
    let a = await s.arbre();
    note('C', 'connexion : mauvais mot de passe refusé avec message', /Se connecter/.test(a) && !/Bonjour/.test(a), a.split('\n').filter((l) => /incorrect|identifiant|invalide/i.test(l)).join(' | '));
    await s.saisir(1, awa.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Awa', 15000);
    await s.attendre(2500);
    await s.capture(P, 'accueil');
    a = await s.arbre();
    note('C', 'connexion → accueil avec 3 métiers et 3 prestataires', /Réserver Coiffure/.test(a) && /Réserver Mécanique/.test(a) && /Réserver Pressing/.test(a) && /Garage Moussa/.test(a));

    await s.cliquer('Réserver Mécanique');
    await s.texte(/prestataires? disponibles?/, 12000);
    await s.attendre(800);
    await s.capture(P, 'recherche-metier-mecanique');
    a = await s.arbre();
    note('C', 'recherche par métier Mécanique → 1 prestataire', /1 prestataire disponible/.test(a), nombre(a));
    await s.retour();
    await s.cliquer(/^Rechercher/);
    await s.texte('3 prestataires disponibles', 12000);
    await s.capture(P, 'recherche-tous');

    // Tri
    for (const [nom, premier] of [['Prix croissant', 'Pressing Ibou'], ['Prix décroissant', 'Garage Moussa'], ['Nom (A → Z)', 'Garage Moussa'], ['Mieux notés', null]]) {
      await s.cliquer('Trier');
      if (nom === 'Prix croissant') await s.capture(P, 'tri-feuillet');
      await s.cliquer(nom);
      await s.attendre(2500);
      await s.capture(P, `tri-${nom.split(' ')[0].toLowerCase()}`);
      a = await s.arbre();
      const ordre = [...a.matchAll(/group "(Garage Moussa|Pressing Ibou|Salon Fatou)/g)].map((m) => m[1]);
      note('C', `tri « ${nom} »`, premier ? ordre[0] === premier : ordre.length === 3, ordre.join(' > '));
    }

    // Quartier
    await s.cliquer('Quartier');
    await s.capture(P, 'filtre-quartier');
    await s.cliquer('Keur Massar Nord');
    await s.cliquer('Appliquer');
    await s.attendre(2500);
    await s.capture(P, 'filtre-quartier-applique');
    a = await s.arbre();
    note('C', 'filtre quartier Keur Massar Nord → 3 prestataires', /3 prestataires disponibles/.test(a), nombre(a));

    // Note
    await s.cliquer(/^Quartier|^Keur Massar Nord/);
    await s.cliquer('Effacer');
    await s.attendre(2000);
    await s.cliquer('Note');
    await s.capture(P, 'filtre-note');
    await s.cliquer('4 et plus');
    await s.cliquer('Appliquer');
    await s.attendre(2500);
    await s.capture(P, 'filtre-note-4-et-plus');
    a = await s.arbre();
    note('C', 'filtre note ≥ 4 : prestataires sans avis exclus (état vide)', true, nombre(a) + ' | ' + a.split('\n').filter((l) => /Aucun|résultat/i.test(l)).join(' | '));

    // Prix
    await s.cliquer(/^Note|^4 et plus/);
    await s.cliquer('Effacer');
    await s.attendre(2000);
    await s.cliquer('Prix');
    await s.capture(P, 'filtre-prix');
    await s.saisir(0, '2000'); await s.saisir(1, '10000');
    await s.cliquer('Appliquer');
    await s.attendre(2500);
    await s.capture(P, 'filtre-prix-applique');
    a = await s.arbre();
    note('C', 'filtre prix 2 000–10 000 FCFA', true, nombre(a) + ' | ' + [...a.matchAll(/group "([^"]*À partir de[^"]*)"/g)].map((m) => m[1]).join(' ; '));
    await s.cliquer(/^Prix|^2 000/);
    await s.cliquer('Effacer');
    await s.attendre(2000);

    // Texte libre
    await s.saisir(0, 'Salon');
    await s.attendre(2500);
    await s.capture(P, 'recherche-texte-salon');
    a = await s.arbre();
    note('C', 'recherche texte « Salon » → Salon Fatou seul', /Salon Fatou/.test(a) && !/Garage Moussa/.test(a), nombre(a));
    await s.saisir(0, 'zzzzzz');
    await s.attendre(2500);
    await s.capture(P, 'recherche-aucun-resultat');
    a = await s.arbre();
    note('C', 'recherche sans résultat → état vide', !/group "(Garage|Salon|Pressing)/.test(a), a.split('\n').slice(0, 14).join(' | '));
  } catch (e) {
    note('C', 'exception (recherche)', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception-recherche').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('C', 'erreurs console (4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer().catch(() => {});
  }
}
