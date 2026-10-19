// B. Prestataire : profil, services (créer, modifier, désactiver), créneaux, indisponibilité, photos, tableau de bord.
import { join } from 'node:path';
import { Session, note } from './pilote.mjs';
import { ICI, comptes, etiqDate, rx } from './lib.mjs';

const f = (n) => join(ICI, '.jetable', 'fichiers', n);
const P = 'B-prestataire-gestion';

export default async function parcoursB() {
  const c = comptes().prestataires.atelier;
  const s = await Session.ouvrir('atelier');
  try {
    await s.saisir(0, c.email);
    await s.saisir(1, c.password);
    await s.cliquer('Se connecter');
    await s.texte('Bonjour Moussa', 12000);
    await s.attendre(2000);
    await s.capture(P, 'tableau-de-bord');
    note('B', 'tableau de bord affiché (solde, demandes)', (await s.arbre()).includes('Bonjour Moussa'));

    // --- Profil
    await s.cliquer(/^Profil/);
    await s.attendre(1000);
    await s.capture(P, 'onglet-profil');
    await s.cliquer('Modifier mon profil');
    await s.attendre(1500);
    await s.capture(P, 'profil-formulaire');
    await s.saisir(0, 'Moussa-Alioune');
    await s.cliquer('Enregistrer');
    await s.attendre(2000);
    await s.capture(P, 'profil-enregistre');
    let a = await s.arbre();
    note('B', 'profil : prénom modifié et enregistré', /mis à jour/.test(a), a.split('\n').slice(0, 4).join(' | '));
    if (/Retour/.test(a)) await s.retour();

    // --- Services
    await s.profil('Mes services et tarifs');
    await s.capture(P, 'services-liste');
    note('B', 'services : 3 services listés', /Vidange/.test(await s.arbre()) && /Freinage/.test(await s.arbre()));

    await s.cliquer('Ajouter un service');
    await s.capture(P, 'service-formulaire');
    await s.cliquer('Enregistrer');
    await s.attendre(800);
    await s.capture(P, 'service-erreurs');
    note('B', 'service : formulaire vide refusé', /Ajouter un service/.test(await s.arbre()));

    await s.saisir(0, 'Révision générale');
    await s.cliquer('1 h');
    await s.saisir(1, '20000');
    await s.capture(P, 'service-rempli');
    await s.cliquer('Enregistrer');
    await s.attendre(2000);
    await s.capture(P, 'services-apres-creation');
    note('B', 'service créé et listé', await s.texte('Révision générale', 6000));

    await s.cliquerCrayon(/Modifier Révision générale/);
    await s.attendre(1200);
    await s.capture(P, 'service-modification');
    await s.saisir(1, '22000');
    await s.cliquer('Enregistrer');
    await s.attendre(2000);
    await s.capture(P, 'services-apres-modification');
    note('B', 'service modifié (prix 22 000)', await s.texte(/22 000/, 6000));

    await s.cliquerCrayon(/Modifier Révision générale/);
    await s.attendre(1200);
    await s.page.getByRole('switch', { name: 'Service actif' }).click();
    await s.capture(P, 'service-desactive');
    await s.cliquer('Enregistrer');
    await s.attendre(2000);
    await s.capture(P, 'services-apres-desactivation');
    a = await s.arbre();
    note('B', 'service désactivé', /Révision générale/.test(a), a.split('\n').filter((l) => /Révision/.test(l)).join(' | '));
    await s.retour();

    // --- Créneaux
    await s.profil('Mes disponibilités');
    await s.capture(P, 'disponibilites');
    await s.cliquer('Ajouter des créneaux');
    await s.capture(P, 'plage-formulaire');
    await s.page.getByRole('button', { name: /^Jour/ }).click();
    await s.attendre(800);
    await s.capture(P, 'plage-calendrier');
    await s.choisirDate(6);
    // Heure invalide d'abord (fin avant début), puis valide.
    await s.saisir(0, '16:00');
    await s.saisir(1, '14:00');
    await s.cliquer('Créer les créneaux');
    await s.attendre(1500);
    await s.capture(P, 'plage-fin-avant-debut');
    a = await s.arbre();
    note('B', 'plage : fin avant début refusée', /Ajouter des créneaux/.test(a), a.split('\n').filter((l) => /heure|fin|début/i.test(l)).join(' | '));
    await s.saisir(0, '14:00');
    await s.saisir(1, '16:00');
    await s.cliquer('1 h');
    await s.capture(P, 'plage-remplie');
    await s.cliquer('Créer les créneaux');
    await s.attendre(2500);
    await s.capture(P, 'plage-creee');
    await s.defiler(3000);
    await s.capture(P, 'plage-creee-bas');
    a = await s.arbre();
    note('B', 'plage créée → retour à la liste avec les nouveaux créneaux', /14h00/.test(a) && /15h00/.test(a), a.split('\n').filter((l) => rx(etiqDate(6)).test(l)).join(' | '));

    // Retrait d'un créneau libre.
    await s.page.getByRole('button', { name: 'Retirer le créneau de 15h00' }).last().click();
    await s.attendre(800);
    await s.capture(P, 'creneau-retrait-confirmation');
    await s.cliquer('Non');
    await s.page.getByRole('button', { name: 'Retirer le créneau de 15h00' }).last().click();
    await s.attendre(600);
    await s.cliquer('Oui, retirer');
    await s.attendre(1500);
    await s.capture(P, 'creneau-retire');
    a = await s.arbre();
    note('B', 'créneau libre retiré après confirmation (« Non » conserve)', /24 oct\. 14h00 Retirer/.test(a) && !/24 oct\. 14h00 15h00/.test(a), a.split('\n').filter((l) => rx(etiqDate(6)).test(l)).join(' | '));

    // Indisponibilité.
    await s.cliquer('Déclarer une indisponibilité');
    await s.capture(P, 'indisponibilite-formulaire');
    // Date saisie au format JJ/MM/AAAA.
    const d6 = new Date(Date.now() + 6 * 86400000);
    const jj = String(d6.getUTCDate()).padStart(2, '0');
    const mm = String(d6.getUTCMonth() + 1).padStart(2, '0');
    const fr = `${jj}/${mm}/${d6.getUTCFullYear()}`;
    await s.saisir(0, fr); await s.saisir(1, '15:00'); await s.saisir(2, fr); await s.saisir(3, '14:00');
    await s.cliquer("Déclarer l'indisponibilité");
    await s.attendre(1000);
    await s.capture(P, 'indisponibilite-confirmation-periode-inversee');
    await s.cliquer('Oui, déclarer');
    await s.attendre(1800);
    await s.capture(P, 'indisponibilite-periode-inversee-refusee');
    a = await s.arbre();
    note('B', 'indisponibilité : période inversée (fin avant début) refusée par le serveur', /Déclarer une indisponibilité/.test(a), a.split('\n').filter((l) => /heure|fin|début|après|période/i.test(l)).join(' | '));
    await s.saisir(1, '14:00'); await s.saisir(3, '15:00');
    await s.capture(P, 'indisponibilite-remplie');
    await s.cliquer("Déclarer l'indisponibilité");
    await s.attendre(800);
    await s.capture(P, 'indisponibilite-confirmation');
    await s.cliquer('Oui, déclarer');
    await s.attendre(2000);
    await s.capture(P, 'indisponibilite-declaree');
    a = await s.arbre();
    note('B', 'indisponibilité déclarée', !/Déclarer une indisponibilité Du/.test(a) && !/Déclarer l'indisponibilité/.test(a), a.split('\n').slice(0, 5).join(' | '));
    await s.defiler(3000);
    await s.capture(P, 'disponibilites-apres-indisponibilite');
    a = await s.arbre();
    note('B', 'créneau 14h00 bloqué par l\'indisponibilité', !rx(`${etiqDate(6)}. 14h00`).test(a) || /indispon/i.test(a), a.split('\n').filter((l) => rx(etiqDate(6)).test(l)).join(' | '));
    await s.defiler(-4000);
    await s.retour();

    // --- Photos
    await s.profil("Photos de l'entreprise");
    await s.capture(P, 'photos-vide');
    await s.cliquer('Ajouter une photo');
    await s.capture(P, 'photos-source');
    const [choix] = await Promise.all([s.page.waitForEvent('filechooser', { timeout: 8000 }), s.cliquer('Galerie')]);
    await choix.setFiles(f('photo.png'));
    await s.attendre(3000);
    await s.capture(P, 'photos-ajoutee');
    a = await s.arbre();
    note('B', 'photo ajoutée depuis la galerie', /button "(Supprimer|Retirer)[^"]*photo/i.test(a), a.split('\n').filter((l) => /button/.test(l)).join(' | '));
    await s.cliquer(/(Supprimer|Retirer).*photo/i);
    await s.attendre(800);
    await s.capture(P, 'photo-suppression-confirmation');
    await s.cliquer('Annuler');
    note('B', 'photo : « Annuler » conserve la photo', /Supprimer la photo/.test(await s.arbre()));
    await s.cliquer('Supprimer la photo');
    await s.page.getByRole('alertdialog').getByRole('button', { name: 'Supprimer' }).click();
    await s.attendre(1800);
    await s.capture(P, 'photo-supprimee');
    a = await s.arbre();
    note('B', 'photo supprimée après confirmation', !/Supprimer la photo/.test(a), a.split('\n').filter((l) => /button|text/.test(l)).join(' | '));
    await s.retour();

    // --- Tableau de bord final
    await s.cliquer(/^Accueil|^Tableau/);
    await s.attendre(1500);
    await s.capture(P, 'tableau-de-bord-final');
    note('B', 'retour au tableau de bord', /Bonjour/.test(await s.arbre()));
  } catch (e) {
    note('B', 'exception', false, e.message.split('\n')[0]);
    await s.capture(P, 'exception').catch(() => {});
    console.log(await s.arbre().catch(() => ''));
  } finally {
    if (s.erreursConsole.length) note('B', 'erreurs console (4xx attendues exclues)', [...new Set(s.erreursConsole)].every((x) => /status of 4[0-9][0-9]/.test(x)), [...new Set(s.erreursConsole)].join(' ; '));
    await s.fermer().catch(() => {});
  }
}
