// Inventaire des éléments interactifs de chaque écran Dart (partie A de VERIFICATION-2).
//
// Usage (depuis mobile/) :  node audit/inventaire_interactions.mjs   -> audit/inventaire-interactions.md (+ .json)
//
// Pour chaque écran : toutes les occurrences de gestes (onPressed, onTap, onChanged, onSubmitted, onRefresh, onLongPress,
// onDismissed, onSelected, onRetour…), avec le numéro de ligne, la cible (méthode appelée) et, pour chaque méthode
// appelée, les appels d'API qu'elle contient (méthodes de dépôt), la présence d'une garde de double envoi (variable
// d'état « en cours » testée avant l'appel), d'un message d'erreur affiché, et d'une navigation après succès.
// Le script ne COMPREND pas le code : il repère des motifs. Chaque ligne marquée « à vérifier » est relue à la main.
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const racine = join(dirname(fileURLToPath(import.meta.url)), '..');
const lib = join(racine, 'lib');

function lister(d) {
  const sortie = [];
  for (const e of readdirSync(join(lib, d), { withFileTypes: true })) {
    const p = `${d}/${e.name}`;
    if (e.isDirectory()) sortie.push(...lister(p));
    else if (e.name.endsWith('.dart')) sortie.push(p);
  }
  return sortie;
}

const GESTES = /\b(onPressed|onTap|onChanged|onSubmitted|onFieldSubmitted|onRefresh|onLongPress|onDismissed|onSelected|onRetour|onDoubleTap|onHorizontalDragEnd|onVerticalDragEnd|onDestinationSelected|onPageChanged|onValider|onConfirmer|onAction|surTap|surChoix|onChoisir)\s*:/;
const DEPOTS = /\b(?:depot\w*|ref\.(?:read|watch)\(depot\w*Provider\)|_depot|session)\s*\.\s*(\w+)\s*\(|\bawait\s+(?:ref\.read\(\w+\)\.|_?\w+\.)(\w+)\(/g;
const API = /(depot\w*|_depot\w*|ref\.read\(depot\w*Provider\))\.(\w+)\(/g;
const GARDE = /(_envoi\w*|_enCours|_en_cours|enCours|_occupe|_chargement|_traitement|_soumission|_enregistrement|_verrou|_envoye|_paye|_verifie|_annulation\w*|envoiEnCours|estEnCours|isLoading|_attente\w*)\b[^;\n]*(return|\?|&&|\|\||!)/;
const ERREUR = /(ErreurApi|ErreurValidation|messageErreur|erreurAffichee|_erreur|\.message\b|errorText|erreur:|AfficherErreur|afficherErreur|SnackBar|BandeauErreur|messageApi)/;
const NAV = /(context\.(go|push|pop|replace|goNamed|pushReplacement)\w*\(|Navigator\.|\.go\(|\.pop\()/;

function methodesDe(texte) {
  // Corps des méthodes/fonctions nommées : nom -> [debutLigne, finLigne]
  const lignes = texte.split(/\r?\n/);
  const methodes = new Map();
  const re = /^\s{2,4}(?:Future<[^>]*>|Future|void|bool|String|Widget|[A-Za-z_<>?,. ]+)\s+(_?\w+)\s*\([^)]*\)\s*(?:async\s*)?(?:\{|=>)/;
  for (let i = 0; i < lignes.length; i++) {
    const m = re.exec(lignes[i]);
    if (!m) continue;
    let profondeur = 0;
    let fin = i;
    let vu = false;
    for (let j = i; j < lignes.length; j++) {
      for (const c of lignes[j]) {
        if (c === '{') { profondeur++; vu = true; }
        if (c === '}') profondeur--;
      }
      fin = j;
      if (vu && profondeur <= 0) break;
      if (!vu && lignes[j].includes(';')) break;
    }
    methodes.set(m[1], [i, fin]);
  }
  return { lignes, methodes };
}

const ecrans = lister('features').filter((p) => /_page\.dart$|annulation_reservation\.dart$|actions_demande\.dart$|aiguillage/.test(p));
const resultat = [];
for (const chemin of ecrans) {
  const texte = readFileSync(join(lib, chemin), 'utf8');
  const { lignes, methodes } = methodesDe(texte);
  const elements = [];
  for (let i = 0; i < lignes.length; i++) {
    const g = GESTES.exec(lignes[i]);
    if (!g) continue;
    const apresDeuxPoints = lignes.slice(i, Math.min(i + 12, lignes.length)).join(' ');
    let cible = null;
    const m1 = /:\s*(?:\(\)\s*(?:async\s*)?=>\s*)?(_\w+)\s*(?:\(|,|$)/.exec(lignes[i]);
    if (m1) cible = m1[1];
    if (!cible) {
      const m2 = /=>\s*(?:await\s+)?(_?\w+)\s*\(/.exec(apresDeuxPoints) ?? /(\w+)\(\s*\)\s*;/.exec(apresDeuxPoints);
      if (m2) cible = m2[1];
    }
    const corps = cible && methodes.has(cible) ? lignes.slice(methodes.get(cible)[0], methodes.get(cible)[1] + 1).join('\n') : apresDeuxPoints;
    const apis = new Set();
    for (const m of corps.matchAll(API)) apis.add(m[2]);
    for (const m of corps.matchAll(/ref\.read\((\w+Provider)(?:\.notifier)?\)\.(\w+)\(/g)) apis.add(`${m[1]}.${m[2]}`);
    elements.push({
      ligne: i + 1,
      geste: g[1],
      cible: cible ?? '(inline)',
      apis: [...apis],
      appelApi: apis.size > 0,
      garde: GARDE.test(corps),
      erreur: ERREUR.test(corps),
      navigation: NAV.test(corps),
    });
  }
  resultat.push({ chemin, elements });
}

const sortie = [];
sortie.push('# Inventaire automatique des éléments interactifs (partie A)');
sortie.push('');
sortie.push('Généré par `node audit/inventaire_interactions.mjs`. Les colonnes « garde », « erreur » et « navigation » sont des **motifs repérés dans le corps de la méthode appelée**, pas une preuve de comportement : voir la matrice relue à la main dans `VERIFICATION-2.md`.');
sortie.push('');
let total = 0;
let avecApi = 0;
let sansGarde = 0;
sortie.push('| Écran | Gestes | Gestes qui appellent l’API | Sans garde de double envoi (à vérifier) | Sans traitement d’erreur repéré (à vérifier) |');
sortie.push('|---|---:|---:|---:|---:|');
for (const r of resultat) {
  const a = r.elements.filter((e) => e.appelApi);
  const sg = a.filter((e) => !e.garde);
  const se = a.filter((e) => !e.erreur);
  total += r.elements.length;
  avecApi += a.length;
  sansGarde += sg.length;
  sortie.push(`| \`${r.chemin}\` | ${r.elements.length} | ${a.length} | ${sg.length} | ${se.length} |`);
}
sortie.push(`| **Total** | **${total}** | **${avecApi}** | **${sansGarde}** | |`);
sortie.push('');
sortie.push('## Détail');
for (const r of resultat) {
  if (!r.elements.length) continue;
  sortie.push('');
  sortie.push(`### \`${r.chemin}\``);
  sortie.push('');
  sortie.push('| Ligne | Geste | Cible | Appels d’API repérés | Garde | Erreur | Navigation |');
  sortie.push('|---:|---|---|---|:-:|:-:|:-:|');
  for (const e of r.elements) {
    sortie.push(`| ${e.ligne} | ${e.geste} | ${e.cible} | ${e.apis.join(', ') || '—'} | ${e.appelApi ? (e.garde ? 'oui' : '**non**') : '—'} | ${e.appelApi ? (e.erreur ? 'oui' : '**non**') : '—'} | ${e.navigation ? 'oui' : '—'} |`);
  }
}
writeFileSync(join(racine, 'audit', 'inventaire-interactions.md'), sortie.join('\n'));
writeFileSync(join(racine, 'audit', 'inventaire-interactions.json'), JSON.stringify(resultat, null, 2));
console.log(`Écrans : ${resultat.length} ; gestes : ${total} ; appelant l'API : ${avecApi} ; sans garde repérée : ${sansGarde}`);
