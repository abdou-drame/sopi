import { useState } from 'react'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import BadgeStatut from '../components/ui/BadgeStatut'
import BarreFiltres from '../components/ui/BarreFiltres'
import BarreProgression from '../components/ui/BarreProgression'
import Carte from '../components/ui/Carte'
import Infobulle from '../components/ui/Infobulle'
import Bouton from '../components/ui/Bouton'
import CarteChiffre from '../components/ui/CarteChiffre'
import { Champ, Selecteur, ZoneTexte } from '../components/ui/Champs'
import { GraphiqueBarres, GraphiqueCourbe } from '../components/ui/Graphiques'
import { IconeAvis, IconePaiement, IconeReservation, IconeUtilisateurs } from '../components/ui/Icones'
import ModaleConfirmation from '../components/ui/ModaleConfirmation'
import Squelette from '../components/ui/Squelette'
import Tableau from '../components/ui/Tableau'
import type { Tri } from '../components/ui/Tableau'
import VisionneuseImage from '../components/ui/VisionneuseImage'
import { L } from '../i18n/libelles'
import { formatMontant } from '../lib/format'

// Page interne de démonstration du système de design. Enregistrée uniquement en développement (voir App.tsx).

interface Ligne {
  id: string
  reference: string
  statut: string
  montant: number
}

const LIGNES: Ligne[] = [
  { id: '1', reference: 'Vidange — Garage Keur Massar', statut: 'terminee', montant: 15000 },
  { id: '2', reference: 'Coupe homme — Salon Jaxaay', statut: 'en_attente', montant: 3000 },
  { id: '3', reference: 'Nettoyage à sec — Pressing Malika', statut: 'annulee', montant: 6500 },
  { id: '4', reference: 'Freinage — Garage Yeumbeul', statut: 'confirmee', montant: 42000 },
]

const IMAGE_EXEMPLE =
  "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='640' height='400'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%231f4f95'/><stop offset='1' stop-color='%235a89d3'/></linearGradient></defs><rect width='640' height='400' fill='url(%23g)'/><text x='320' y='210' font-size='32' fill='white' text-anchor='middle' font-family='sans-serif'>Justificatif (exemple)</text></svg>"

function Section({ titre, children }: { titre: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-4">
      <h2 className="text-lg font-bold text-primaire-800">{titre}</h2>
      {children}
    </section>
  )
}

export default function DemoPage() {
  const [modale, setModale] = useState<'motif' | 'simple' | null>(null)
  const [dernier, setDernier] = useState('')
  const [tri, setTri] = useState<Tri>({ cle: 'reference', sens: 'asc' })
  const [page, setPage] = useState(1)
  const [etat, setEtat] = useState<'normal' | 'chargement' | 'erreur' | 'vide'>('normal')

  const lignes = [...LIGNES].sort((a, b) => {
    const s = tri.sens === 'asc' ? 1 : -1
    return tri.cle === 'montant' ? (a.montant - b.montant) * s : a.reference.localeCompare(b.reference) * s
  })

  return (
    <main className="mx-auto flex max-w-5xl flex-col gap-10 p-6">
      <header>
        <h1 className="text-titre-page text-titre"><span className="font-bold">{L.demo.titre}</span></h1>
        <p className="mt-1 text-sm text-texte-doux">{L.demo.avertissement}</p>
      </header>

      <Section titre="Couleurs">
        <div className="grid grid-cols-5 gap-2 sm:grid-cols-10">
          {[50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((n) => (
            <div key={n} className="text-center text-[10px] text-texte-doux">
              <div className="h-10 rounded-petit" style={{ background: `var(--color-primaire-${n})` }} />
              {n}
            </div>
          ))}
        </div>
        <div className="flex flex-wrap gap-3 text-xs">
          {(['succes', 'attention', 'erreur', 'info'] as const).map((t) => (
            <span key={t} className="flex items-center gap-2 rounded-petit px-3 py-2" style={{ background: `var(--color-${t}-fond)`, color: `var(--color-${t}-texte)` }}>
              <span className="size-3 rounded-full" style={{ background: `var(--color-${t})` }} />
              {t}
            </span>
          ))}
        </div>
      </Section>

      <Section titre="Boutons">
        <div className="flex flex-wrap items-center gap-3">
          <Bouton>Primaire</Bouton>
          <Bouton variante="secondaire">Secondaire</Bouton>
          <Bouton variante="danger">Danger</Bouton>
          <Bouton variante="fantome">Fantôme</Bouton>
          <Bouton enCours>En cours</Bouton>
          <Bouton disabled>Désactivé</Bouton>
          <Bouton taille="p">Petit</Bouton>
        </div>
      </Section>

      <Section titre="Champs et sélecteur">
        <div className="grid gap-4 sm:grid-cols-2">
          <Champ label="Téléphone" placeholder="+221770000000" aide="Format international." />
          <Champ label="Champ en erreur" defaultValue="abc" erreur="Ce champ est invalide." />
          <Selecteur
            label="Statut"
            options={[
              { valeur: '', libelle: 'Tous' },
              { valeur: 'actif', libelle: 'Actif' },
              { valeur: 'suspendu', libelle: 'Suspendu' },
            ]}
          />
          <ZoneTexte label="Commentaire" placeholder="Saisie libre" />
        </div>
      </Section>

      <Section titre="Badges de statut">
        <div className="flex flex-wrap gap-2">
          {['en_attente', 'confirmee', 'terminee', 'refusee', 'annulee', 'actif', 'suspendu', 'banni', 'reussi', 'echoue', 'rembourse', 'ouvert'].map((s) => (
            <BadgeStatut key={s} statut={s} />
          ))}
        </div>
      </Section>

      <Section titre="Cartes de chiffres clés">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <CarteChiffre titre="Clients" valeur="120" aide="115 actifs" icone={<IconeUtilisateurs />} teinte="violet" />
          <CarteChiffre titre="Réservations" valeur="340" aide="25 terminées cette semaine" icone={<IconeReservation />} teinte="cyan" delai={80} />
          <CarteChiffre titre="Acomptes" valeur={formatMontant(1350000)} icone={<IconePaiement />} teinte="rose" delai={160} />
          <CarteChiffre titre="Chargement" valeur="" chargement icone={<IconeAvis />} delai={240} />
        </div>
      </Section>

      <Section titre="Bannières d’alerte">
        <div className="flex flex-col gap-3">
          <BanniereAlerte gravite="critique" titre="2 litiges ouverts sont à traiter." detail="Plus ancien élément : 3 j 3 h" vers="/litiges" />
          <BanniereAlerte gravite="attention" titre="1 retrait est bloqué." vers="/retraits" delai={80} />
          <BanniereAlerte gravite="info" titre="1 suspension temporaire se termine bientôt." delai={160} />
        </div>
      </Section>

      <Section titre="Barre de filtres et tableau paginé">
        <BarreFiltres onAppliquer={() => undefined} onReinitialiser={() => undefined}>
          <Champ label="Recherche" placeholder="Nom, téléphone…" />
          <Selecteur label="Statut" options={[{ valeur: '', libelle: 'Tous' }, { valeur: 'actif', libelle: 'Actif' }]} />
        </BarreFiltres>
        <div className="flex gap-2 text-xs">
          {(['normal', 'chargement', 'erreur', 'vide'] as const).map((e) => (
            <Bouton key={e} taille="p" variante={etat === e ? 'primaire' : 'secondaire'} onClick={() => setEtat(e)}>
              {e}
            </Bouton>
          ))}
        </div>
        <Tableau
          legende="Exemple de réservations"
          cleLigne={(l) => l.id}
          lignes={etat === 'vide' ? [] : lignes}
          chargement={etat === 'chargement'}
          erreur={etat === 'erreur' ? L.commun.erreurChargement : null}
          onReessayer={() => setEtat('normal')}
          tri={tri}
          onTri={(cle) => setTri((t) => ({ cle, sens: t.cle === cle && t.sens === 'asc' ? 'desc' : 'asc' }))}
          page={page}
          totalPages={3}
          total={58}
          onPage={setPage}
          colonnes={[
            { cle: 'reference', titre: 'Prestation', triable: true, rendu: (l) => l.reference },
            { cle: 'statut', titre: 'Statut', rendu: (l) => <BadgeStatut statut={l.statut} /> },
            { cle: 'montant', titre: 'Montant', triable: true, alignement: 'droite', rendu: (l) => formatMontant(l.montant) },
          ]}
        />
      </Section>

      <Section titre="Graphiques">
        <div className="grid gap-6 lg:grid-cols-2">
          <Carte titre="Courbe lissée" sousTitre="Survolez la courbe pour déplacer la pastille">
            <GraphiqueCourbe
              description="Exemple de courbe sur 7 jours"
              libelles={['1 janv.', '2 janv.', '3 janv.', '4 janv.', '5 janv.', '6 janv.', '7 janv.']}
              series={[
                { nom: 'Acomptes', couleur: 'var(--color-primaire-500)', valeurs: [12000, 30000, 18000, 45000, 39000, 60000, 52000] },
                { nom: 'Commission', couleur: 'var(--color-succes)', valeurs: [4000, 10000, 6000, 15000, 13000, 20000, 17000] },
              ]}
              formatValeur={formatMontant}
            />
          </Carte>
          <Carte titre="Barres en gélule" sousTitre="Barre active foncée, autres hachurées" delai={80}>
            <GraphiqueBarres
              description="Exemple de barres par domaine"
              donnees={[
                { libelle: 'Mécanique', valeur: 150 },
                { libelle: 'Coiffure', valeur: 120 },
                { libelle: 'Pressing', valeur: 70 },
              ]}
            />
          </Carte>
        </div>
      </Section>

      <Section titre="Cartes, barres de progression et infobulles">
        <div className="grid gap-6 lg:grid-cols-2">
          <Carte titre="Charge par domaine" sousTitre="Part des réservations">
            <div className="flex flex-col gap-4">
              <BarreProgression libelle="Mécanique" valeur={44} />
              <BarreProgression libelle="Coiffure" valeur={35} teinte="succes" delai={100} />
              <BarreProgression libelle="Pressing" valeur={21} teinte="attention" delai={200} />
            </div>
          </Carte>
          <Carte titre="Infobulles" sousTitre="Au survol et au focus clavier" delai={80}>
            <div className="flex items-center gap-8 py-4">
              <Infobulle texte="À droite (barre latérale)">
                <Bouton variante="secondaire">Survolez-moi</Bouton>
              </Infobulle>
              <Infobulle texte="Au-dessus" cote="haut">
                <Bouton variante="secondaire">Ou moi</Bouton>
              </Infobulle>
            </div>
          </Carte>
        </div>
      </Section>

      <Section titre="Squelettes de chargement">
        <div className="flex flex-col gap-2 rounded-carte bg-surface p-6 shadow-carte">
          <Squelette className="h-5 w-1/3" />
          <Squelette className="h-4 w-full" />
          <Squelette className="h-4 w-2/3" />
        </div>
      </Section>

      <Section titre="Visionneuse d’image">
        <VisionneuseImage src={IMAGE_EXEMPLE} alt="Justificatif d’exemple" className="h-24 w-40" />
      </Section>

      <Section titre="Modale de confirmation">
        <div className="flex flex-wrap items-center gap-3">
          <Bouton variante="danger" onClick={() => setModale('motif')}>
            Refuser (motif obligatoire)
          </Bouton>
          <Bouton variante="secondaire" onClick={() => setModale('simple')}>
            Confirmer (sans motif)
          </Bouton>
          {dernier && <p className="text-sm text-texte-doux">Dernière action : {dernier}</p>}
        </div>
        <ModaleConfirmation
          ouverte={modale === 'motif'}
          titre="Refuser ce dossier ?"
          description="Cette action est irréversible. Le prestataire sera informé du motif."
          danger
          motifObligatoire
          libelleConfirmer="Refuser le dossier"
          onAnnuler={() => setModale(null)}
          onConfirmer={(m) => {
            setDernier(`refus avec le motif « ${m} »`)
            setModale(null)
          }}
        />
        <ModaleConfirmation
          ouverte={modale === 'simple'}
          titre="Confirmer l’action ?"
          onAnnuler={() => setModale(null)}
          onConfirmer={() => {
            setDernier('confirmation simple')
            setModale(null)
          }}
        />
      </Section>
    </main>
  )
}
