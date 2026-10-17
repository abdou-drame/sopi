// Compare les valeurs numériques des maquettes (.dc.html) au code Dart de l'écran correspondant.
//
// Usage (depuis mobile/) :  node audit/comparer_maquettes.mjs        -> audit/ecarts-visuels.md et audit/ecarts-visuels.json
//
// Méthode : pour chaque maquette, on extrait des attributs `style="..."` les valeurs de border-radius, padding, gap,
// font-size, font-weight, height, width (composants ≤ 120 px) et les couleurs hexadécimales. Pour chaque valeur on
// cherche si le code Dart de l'écran (puis les fichiers partagés core/) contient la même valeur dans un contexte
// compatible (fontSize: 30, FontWeight.w700, circular(18), EdgeInsets…, Color(0xFF1D4ED8)).
// Une valeur « absente » n'est qu'un SIGNAL à vérifier : voir « Limites » dans le rapport.
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const racine = join(dirname(fileURLToPath(import.meta.url)), '..');
const dossierMaquettes = join(racine, 'maquettes', 'maquettes');
const lib = join(racine, 'lib');
const f = (p) => `features/${p}`;

// Maquette -> fichiers Dart de l'écran (même rapprochement que AUDIT-MOBILE.md, partie 2.1).
const correspondance = {
  Connexion: [f('auth/connexion_page.dart')],
  ConnexionBloquee: [f('auth/connexion_page.dart'), f('auth/compte_restreint_page.dart')],
  Inscription: [f('auth/inscription_page.dart')],
  MotDePasse: [f('auth/mot_de_passe_oublie_page.dart')],
  Suspendu: [f('auth/compte_restreint_page.dart')],
  PrestaInscription1: [f('inscription_prestataire/etape_activite_page.dart')],
  PrestaInscription2: [f('inscription_prestataire/etape_justificatifs_page.dart')],
  PrestaCompte: [f('inscription_prestataire/etape_informations_page.dart')],
  SuiviDossier: [f('inscription_prestataire/suivi_dossier_page.dart')],
  DossierRefuse: [f('inscription_prestataire/suivi_dossier_page.dart')],
  Main: [f('reservation/accueil_client_page.dart')],
  Prestataires: [f('reservation/liste_prestataires_page.dart')],
  Fiche: [f('reservation/fiche_prestataire_page.dart')],
  AvisPrestataire: [f('reservation/avis_prestataire_page.dart')],
  Creneau: [f('reservation/choix_creneau_page.dart')],
  DemandeEnvoyee: [f('reservation/demande_envoyee_page.dart')],
  Reservations: [f('reservation/mes_reservations_page.dart')],
  DetailEnAttente: [f('reservation/detail_reservation_page.dart')],
  DetailAPayer: [f('reservation/detail_reservation_page.dart')],
  DetailReservation: [f('reservation/detail_reservation_page.dart')],
  Paiement: [f('reservation/paiement_page.dart')],
  PaiementOk: [f('reservation/paiement_page.dart')],
  PaiementEchec: [f('reservation/paiement_page.dart')],
  Annulation: [f('reservation/annulation_reservation.dart')],
  Avis: [f('reservation/avis_page.dart')],
  Profil: [f('compte/profil_client_page.dart')],
  ModifierProfil: [f('compte/modifier_profil_page.dart')],
  ChangerMotDePasse: [f('compte/changer_mot_de_passe_page.dart')],
  Notifications: [f('compte/notifications_page.dart')],
  Aide: [f('compte/aide_page.dart')],
  Litige: [f('compte/litige_page.dart')],
  Tableau: [f('prestataire/tableau_page.dart')],
  Agenda: [f('prestataire/agenda_page.dart')],
  PrestaReservations: [f('prestataire/reservations_prestataire_page.dart')],
  PrestaDetailDemande: [f('prestataire/detail_demande_page.dart')],
  RefusMotif: [f('prestataire/refus_motif_page.dart')],
  Prestation: [f('prestataire/prestation_page.dart')],
  CodeVerification: [f('prestataire/prestation_page.dart')],
  CodeValide: [f('prestataire/prestation_page.dart')],
  CodeFaux: [f('prestataire/prestation_page.dart')],
  PrestaTerminer: [f('prestataire/terminer_prestation_page.dart')],
  Services: [f('prestataire/services_page.dart')],
  ServiceForm: [f('prestataire/service_form_page.dart')],
  Disponibilites: [f('prestataire/disponibilites_page.dart')],
  PlageHoraire: [f('prestataire/plage_horaire_page.dart')],
  Conge: [f('prestataire/indisponibilite_page.dart')],
  Portefeuille: [f('prestataire/portefeuille_page.dart')],
  Retrait: [f('prestataire/retrait_page.dart')],
  RetraitEchec: [f('prestataire/retrait_page.dart')],
  PrestaAvis: [f('prestataire/avis_recus_page.dart')],
  PrestaLitiges: [f('prestataire/litiges_recus_page.dart')],
  PrestaLitigeReponse: [f('prestataire/litige_reponse_page.dart')],
  PrestaPhotos: [f('prestataire/photos_page.dart')],
  PrestaProfil: [f('prestataire/profil_prestataire_page.dart')],
};

function lireDart(chemin) {
  try {
    return readFileSync(join(lib, chemin), 'utf8');
  } catch {
    return null;
  }
}

function listerDart(dossier) {
  const sortie = [];
  for (const e of readdirSync(join(lib, dossier), { withFileTypes: true })) {
    const p = `${dossier}/${e.name}`;
    if (e.isDirectory()) sortie.push(...listerDart(p));
    else if (e.name.endsWith('.dart')) sortie.push(p);
  }
  return sortie;
}

// Fichiers partagés (thème, widgets communs) et dossier de la fonctionnalité (widgets/ et modèles voisins).
const partages = [...listerDart('core')].map((p) => ({ p, t: lireDart(p) }));

function extraire(html) {
  const valeurs = [];
  const re = /style="([^"]*)"/g;
  let m;
  while ((m = re.exec(html))) {
    const style = m[1].replace(/\s+/g, ' ');
    for (const d of style.split(';')) {
      const i = d.indexOf(':');
      if (i < 0) continue;
      const prop = d.slice(0, i).trim().toLowerCase();
      const val = d.slice(i + 1).trim();
      if (prop === 'font-size') valeurs.push(['font-size', num(val)]);
      else if (prop === 'font-weight') valeurs.push(['font-weight', num(val)]);
      else if (prop === 'border-radius') for (const n of nums(val)) valeurs.push(['border-radius', n]);
      else if (prop === 'padding') for (const n of nums(val)) valeurs.push(['padding', n]);
      else if (prop === 'gap') valeurs.push(['gap', num(val)]);
      else if (prop === 'height') { const n = num(val); if (n >= 24 && n <= 120) valeurs.push(['height', n]); }
      else if (prop === 'width') { const n = num(val); if (n >= 16 && n <= 120) valeurs.push(['width', n]); }
      if (/(^|-)color$|^background$|^border/.test(prop) || prop === 'background-image' || prop === 'box-shadow') {
        for (const c of val.match(/#[0-9a-fA-F]{6}\b/g) ?? []) valeurs.push(['couleur', c.toUpperCase()]);
      }
    }
  }
  return valeurs.filter(([, v]) => v !== null && !Number.isNaN(v));
}
const num = (s) => { const m = /^-?\d+(\.\d+)?/.exec(s.trim()); return m ? Number(m[0]) : null; };
const nums = (s) => (s.match(/-?\d+(\.\d+)?/g) ?? []).map(Number).filter((n) => n > 0);

const motif = (n) => `(?<![\\d.])${String(n).replace('.', '\\.')}(?:\\.0)?(?![\\d])`;
const CONTEXTE = {
  padding: /padding|EdgeInsets|symmetric|fromLTRB|horizontal|vertical|marge|espace/i,
  gap: /gap|spacing|SizedBox|espace|Gap|runSpacing/i,
  height: /height|hauteur|SizedBox|Size\(|minimumSize|toolbar/i,
  width: /width|largeur|SizedBox|Size\(|minimumSize/i,
  'border-radius': /radius|circular|rayon|BorderRadius/i,
};

function presente(texte, [type, valeur]) {
  if (texte == null) return false;
  switch (type) {
    case 'font-size':
      return new RegExp(`fontSize\\s*:\\s*${motif(valeur)}|fontSize\\w*\\s*=\\s*${motif(valeur)}|(?:taille|size)\\w*\\s*[:=]\\s*${motif(valeur)}`, 'i').test(texte);
    case 'font-weight':
      return new RegExp(`FontWeight\\.w${valeur}\\b`).test(texte);
    case 'couleur': {
      const hex = valeur.slice(1);
      return new RegExp(`0x(?:FF)?${hex}`, 'i').test(texte);
    }
    default: {
      const re = new RegExp(motif(valeur));
      return texte.split(/\r?\n/).some((l) => re.test(l) && CONTEXTE[type].test(l));
    }
  }
}

const gravite = {
  couleur: 'VISIBLE',
  'font-size': 'VISIBLE',
  'font-weight': 'MINEUR',
  'border-radius': 'MINEUR',
  padding: 'MINEUR',
  gap: 'MINEUR',
  height: 'MINEUR',
  width: 'MINEUR',
};
// Ces valeurs ne sont des écarts BLOQUANTS que si une valeur de typographie/couleur de premier plan manque : ce script ne
// peut pas le décider seul, donc il ne classe BLOQUANT que l'absence totale du fichier Dart de l'écran.

const resultats = [];
for (const fichier of readdirSync(dossierMaquettes).filter((n) => n.endsWith('.dc.html')).sort()) {
  const nom = fichier.replace('.dc.html', '');
  const html = readFileSync(join(dossierMaquettes, fichier), 'utf8');
  const valeurs = extraire(html);
  const uniques = [...new Map(valeurs.map((v) => [v.join('|'), v])).values()];
  const cibles = correspondance[nom];
  if (!cibles) {
    resultats.push({ nom, erreur: 'AUCUN ÉCRAN ASSOCIÉ', gravite: 'BLOQUANT', manques: [], total: uniques.length });
    continue;
  }
  const textesEcran = cibles.map(lireDart);
  if (textesEcran.some((t) => t == null)) {
    resultats.push({ nom, erreur: `fichier Dart introuvable : ${cibles.join(', ')}`, gravite: 'BLOQUANT', manques: [], total: uniques.length });
    continue;
  }
  // Dossier de la fonctionnalité (widgets voisins) = même répertoire que l'écran.
  const dossierFonction = cibles[0].split('/').slice(0, -1).join('/');
  const voisins = listerDart(dossierFonction).filter((p) => !cibles.includes(p)).map(lireDart);
  const ecran = textesEcran.join('\n');
  const voisinsT = voisins.join('\n');
  const manques = [];
  let dansEcran = 0;
  let dansPartage = 0;
  for (const v of uniques) {
    if (presente(ecran, v)) dansEcran++;
    else if (presente(voisinsT, v) || partages.some(({ t }) => presente(t, v))) dansPartage++;
    else manques.push({ type: v[0], valeur: v[1], gravite: gravite[v[0]] });
  }
  resultats.push({ nom, ecran: cibles.join(' + '), total: uniques.length, dansEcran, dansPartage, manques });
}

const ordre = { BLOQUANT: 0, VISIBLE: 1, MINEUR: 2 };
const lignes = [];
lignes.push('# Écarts visuels mesurables — maquettes `.dc.html` contre code Dart');
lignes.push('');
lignes.push('Généré par `node audit/comparer_maquettes.mjs` (ne pas éditer à la main). Chaque ligne « absent » est un **signal à vérifier**, pas une preuve : voir les limites dans `VERIFICATION-2.md`.');
lignes.push('');
lignes.push('| Maquette | Écran Dart | Valeurs | Trouvées dans l’écran | Trouvées ailleurs (core/ ou dossier) | Absentes | VISIBLE | MINEUR |');
lignes.push('|---|---|---:|---:|---:|---:|---:|---:|');
let t = { total: 0, ecran: 0, partage: 0, abs: 0, vis: 0, min: 0 };
for (const r of resultats) {
  if (r.erreur) {
    lignes.push(`| ${r.nom} | **${r.erreur}** | ${r.total} | — | — | — | — | — |`);
    continue;
  }
  const vis = r.manques.filter((m) => m.gravite === 'VISIBLE').length;
  const min = r.manques.filter((m) => m.gravite === 'MINEUR').length;
  lignes.push(`| ${r.nom} | \`${r.ecran}\` | ${r.total} | ${r.dansEcran} | ${r.dansPartage} | ${r.manques.length} | ${vis} | ${min} |`);
  t = { total: t.total + r.total, ecran: t.ecran + r.dansEcran, partage: t.partage + r.dansPartage, abs: t.abs + r.manques.length, vis: t.vis + vis, min: t.min + min };
}
lignes.push(`| **Total** | | **${t.total}** | **${t.ecran}** | **${t.partage}** | **${t.abs}** | **${t.vis}** | **${t.min}** |`);
lignes.push('');
lignes.push('## Détail des valeurs absentes');
lignes.push('');
for (const r of resultats.filter((x) => !x.erreur && x.manques.length)) {
  const tries = [...r.manques].sort((a, b) => ordre[a.gravite] - ordre[b.gravite] || a.type.localeCompare(b.type));
  lignes.push(`### ${r.nom}`);
  lignes.push('');
  lignes.push(tries.map((m) => `- ${m.gravite} · ${m.type} = ${m.valeur}${m.type === 'font-size' || m.type.match(/^(padding|gap|height|width|border-radius)$/) ? ' px' : ''}`).join('\n'));
  lignes.push('');
}
writeFileSync(join(racine, 'audit', 'ecarts-visuels.md'), lignes.join('\n'));
writeFileSync(join(racine, 'audit', 'ecarts-visuels.json'), JSON.stringify(resultats, null, 2));
console.log(`Maquettes : ${resultats.length} ; valeurs : ${t.total} ; dans l'écran : ${t.ecran} ; ailleurs : ${t.partage} ; absentes : ${t.abs} (VISIBLE ${t.vis}, MINEUR ${t.min})`);
