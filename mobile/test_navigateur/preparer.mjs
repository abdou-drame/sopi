// Crée les comptes de test dans le backend local jetable (via l'API et le compte administrateur local).
// Mots de passe générés au hasard, écrits seulement dans .jetable/comptes.json (ignoré par Git) et jamais affichés.
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api, connexion, ADMIN, ICI, jourDans, motDePasseJetable, PNG } from './lib.mjs';

const adminJeton = await connexion(ADMIN.identifiant, ADMIN.password);
const metiers = (await api('/metiers')).data ?? (await api('/metiers'));
const quartiers = [];
const codeMetier = (nom) => metiers.find((m) => (m.nom ?? m.libelle).toLowerCase().startsWith(nom))?.code ?? nom;
const nomQuartier = 'Keur Massar Nord'; // la table des quartiers est vide à neuf : le premier dossier la remplit

const comptes = { metiers, quartiers, prestataires: {} };

// --- Client déjà inscrit (les parcours inscrivent aussi un autre client par l'interface).
comptes.client = { email: 'awa.client@sopi.invalid', telephone: '+221771000001', password: motDePasseJetable() };
await api('/auth/inscription', {
  methode: 'POST',
  corps: { nom: 'Ndiaye', prenom: 'Awa', telephone: comptes.client.telephone, email: comptes.client.email, password: comptes.client.password },
});

// --- Prestataires actifs (dossier déposé puis accepté par l'administrateur local).
async function creerPrestataire(cle, { entreprise, metier, tel, prenom, nom }) {
  const motDePasse = motDePasseJetable();
  const email = `${cle}@sopi.invalid`;
  const f = new FormData();
  const champs = { nom, prenom, telephone: tel, email, mot_de_passe: motDePasse, mot_de_passe_confirmation: motDePasse,
    nom_entreprise: entreprise, metier: codeMetier(metier), quartier: nomQuartier, adresse: 'Rue 10, Keur Massar' };
  for (const [k, v] of Object.entries(champs)) f.append(k, v);
  f.append('justificatifs[0][type_document]', 'cni');
  f.append('justificatifs[0][fichier]', new Blob([PNG], { type: 'image/png' }), 'cni.png');
  f.append('justificatifs[1][type_document]', 'diplome');
  f.append('justificatifs[1][fichier]', new Blob([PNG], { type: 'image/png' }), 'diplome.png');
  await api('/prestataires/inscription', { methode: 'POST', formulaire: f });

  const demandes = await api('/admin/prestataires/demandes', { jeton: adminJeton });
  const liste = demandes.data ?? demandes;
  const dossier = liste.find((d) => d.nomEntreprise === entreprise);
  if (!dossier) throw new Error(`Dossier ${email} introuvable côté administrateur.`);
  await api(`/admin/prestataires/${dossier.id}/accepter`, { methode: 'POST', jeton: adminJeton, corps: {} });

  const jeton = await connexion(email, motDePasse);
  return { email, telephone: tel, password: motDePasse, entreprise, jeton, idDossier: dossier.id };
}

const defs = {
  atelier: { entreprise: 'Garage Moussa', metier: 'mécanique', tel: '+221772000001', prenom: 'Moussa', nom: 'Diop' },
  salon: { entreprise: 'Salon Fatou', metier: 'coiffure', tel: '+221772000002', prenom: 'Fatou', nom: 'Sow' },
  pressing: { entreprise: 'Pressing Ibou', metier: 'pressing', tel: '+221772000003', prenom: 'Ibrahima', nom: 'Fall' },
};
for (const [cle, d] of Object.entries(defs)) {
  const p = await creerPrestataire(`presta.${cle}`, d);
  // Services et créneaux sur les 5 prochains jours.
  const prestations = cle === 'atelier'
    ? [['Vidange', 45, 15000], ['Diagnostic', 30, 8000], ['Freinage', 90, 25000]]
    : cle === 'salon' ? [['Coupe homme', 30, 3000], ['Tresses', 120, 12000]] : [['Chemise', 15, 1500], ['Couette', 60, 6000]];
  for (const [nom, duree, prix] of prestations) {
    await api('/prestataire/prestations', { methode: 'POST', jeton: p.jeton, corps: { nom, duree_minutes: duree, prix } });
  }
  for (let j = 1; j <= 5; j++) {
    await api('/prestataire/agenda/plages', { methode: 'POST', jeton: p.jeton, corps: { plages: [
      { date: jourDans(j), heure_debut: '09:00', heure_fin: '13:00', duree_creneau_minutes: 60 },
    ] } });
  }
  delete p.jeton;
  comptes.prestataires[cle] = p;
}

mkdirSync(join(ICI, '.jetable'), { recursive: true });
writeFileSync(join(ICI, '.jetable', 'comptes.json'), JSON.stringify(comptes, null, 2));
console.log(`Comptes créés : 1 client, ${Object.keys(defs).length} prestataires actifs avec services et créneaux.`);
