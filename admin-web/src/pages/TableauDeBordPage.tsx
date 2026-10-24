import { useQueries, useQuery } from '@tanstack/react-query'
import { AnimatedCard } from '../design/animation/composants'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/endpoints'
import type { Revenus } from '../api/types'
import { lienDeLAlerte, useAlertes } from '../api/useAlertes'
import { useAuth } from '../auth/AuthContext'
import NombreAnime from '../components/ui/NombreAnime'
import JaugeMetier from '../components/ui/JaugeMetier'
import GraphiqueGroupe from '../components/ui/GraphiqueGroupe'
import { BanniereAlerteMq } from '../design/bannieres'
import { Avatar, Carte, FOND_MARQUE, TitrePage } from '../design/composants'
import { ICalendrier, IFlecheHautDroite, IGraphique, ILitiges } from '../design/icones'
import { L, dureeMinutes } from '../i18n/libelles'
import { ajouterJours, aujourdhui, formatDate, formatHeureDuJour, formatJourCalendaire, formatNombre } from '../lib/format'

const T = L.tableauDeBord
const ABSENT = '—'

/** Couleur du point de statut d'une réservation (statuts du backend). */
const POINT_STATUT: Record<string, string> = {
  en_attente: '#f59e0b',
  acceptee: 'var(--color-neutre)',
  confirmee: 'var(--color-neutre)',
  en_cours: 'var(--color-neutre)',
  terminee: '#16a34a',
  refusee: '#e5484d',
  annulee: '#9aa0b2',
}

function LienFleche({ vers, libelle }: { vers: string; libelle: string }) {
  return (
    <Link to={vers} aria-label={libelle} className="mq-rond grid size-11 shrink-0 place-items-center rounded-[22px] bg-[#f3f4f7] text-[#12172b]">
      <IFlecheHautDroite taille={16} trait={2} />
    </Link>
  )
}

function EnTeteSimple({ titre, sousTitre, vers, libelle, icone }: { titre: string; sousTitre: string; vers: string; libelle: string; icone?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3">
      {icone}
      <span className="min-w-0 flex-1">
        <span className="block text-[15px] font-bold">{titre}</span>
        <span className="mt-0.5 block text-xs text-[#8a90a2]">{sousTitre}</span>
      </span>
      <LienFleche vers={vers} libelle={libelle} />
    </div>
  )
}

function CarteChiffre({
  degrade = false,
  titre,
  valeur,
  note,
  pastille,
  unite,
}: {
  degrade?: boolean
  titre: string
  valeur?: number
  note: string
  pastille?: string
  /** Unité affichée après la valeur (« FCFA » pour un montant, rien pour un effectif). */
  unite?: string
}) {
  const clair = degrade ? 'opacity-80' : 'text-[#9aa0b2]'
  return (
    <AnimatedCard>
    <section
      className={`box-border rounded-[24px] px-5 py-3 ${degrade ? 'text-white' : 'bg-white'}`}
      style={degrade ? { background: FOND_MARQUE } : undefined}
    >
      <div className="flex items-center justify-between">
        <span className={`text-xs font-semibold ${degrade ? 'opacity-85' : 'text-[#8a90a2]'}`}>{titre}</span>
        {pastille && <span className={`rounded-full px-[9px] py-[3px] text-[11px] font-bold ${degrade ? 'bg-white/20' : 'bg-[#f3f4f7] text-[#9aa0b2]'}`}>{pastille}</span>}
      </div>
      <div className="mt-1 text-[26px] leading-[30px] font-bold tracking-[-0.8px] tabular-nums">
        {valeur === undefined ? ABSENT : <NombreAnime valeur={valeur} />}
        {unite && <span className={`text-sm font-medium ${clair}`}> {unite}</span>}
      </div>
      <div className={`mt-0.5 text-xs ${degrade ? 'opacity-80' : 'text-[#8a90a2]'}`}>{note}</div>
    </section>
    </AnimatedCard>
  )
}

interface Tranche {
  libelle: string
  debut: string
  fin: string
}

const MOIS_COURTS = new Intl.DateTimeFormat('fr-FR', { month: 'short' })
const deuxChiffres = (n: number) => String(n).padStart(2, '0')

/** Six derniers mois civils (du premier au dernier jour de chaque mois), le mois en cours en dernier. */
function derniersMois(): Tranche[] {
  const maintenant = new Date()
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(maintenant.getFullYear(), maintenant.getMonth() - (5 - i), 1)
    const a = d.getFullYear()
    const m = d.getMonth() + 1
    const m3 = MOIS_COURTS.format(d)
    return { libelle: m3.charAt(0).toUpperCase() + m3.slice(1), debut: `${a}-${deuxChiffres(m)}-01`, fin: `${a}-${deuxChiffres(m)}-${deuxChiffres(new Date(a, m, 0).getDate())}` }
  })
}

/** Six dernières semaines de 7 jours, la semaine en cours (qui finit aujourd'hui) en dernier. */
function dernieresSemaines(): Tranche[] {
  const fin = aujourdhui()
  return Array.from({ length: 6 }, (_, i) => {
    const finSemaine = ajouterJours(fin, -7 * (5 - i))
    const debut = ajouterJours(finSemaine, -6)
    return { libelle: formatJourCalendaire(debut, true), debut, fin: finSemaine }
  })
}

/** Séries du graphique des réservations, dans l'ordre des colonnes ; `undefined` = toutes les réservations. */
const STATUTS_GRAPHIQUE = ['annulee', 'terminee', undefined] as const
const SERIES_RESERVATIONS = [
  { libelle: 'Annulées', couleur: '#e0a830' },
  { libelle: 'Terminées', couleur: '#2d9d6a' },
  { libelle: 'Réservations', couleur: '#4b6fd6' },
]

/** Réservations par période : le total de la pagination de chaque tranche (créneaux commençant dans la tranche). */
function CarteReservations() {
  const [annuel, setAnnuel] = useState(true)
  const tranches = useMemo(() => (annuel ? derniersMois() : dernieresSemaines()), [annuel])
  // Trois totaux par tranche : annulées, terminées et toutes les réservations (le total de la pagination suffit).
  const resultats = useQueries({
    queries: tranches.flatMap((t) =>
      STATUTS_GRAPHIQUE.map((statut) => ({
        queryKey: ['reservations-tranche', t.debut, t.fin, statut ?? 'toutes'],
        queryFn: () => api.reservations({ debut: t.debut, fin: t.fin, ...(statut ? { statut } : {}) }),
        retry: false,
        staleTime: 60_000,
      })),
    ),
  })
  const groupes = tranches.map((t, i) => ({
    libelle: t.libelle,
    valeurs: STATUTS_GRAPHIQUE.map((_, k) => resultats[i * STATUTS_GRAPHIQUE.length + k].data?.meta.total ?? 0),
  }))
  return (
    <section className="box-border flex min-w-0 flex-[1_1_340px] flex-col rounded-[32px] bg-white p-[18px]">
      <div className="flex flex-wrap items-center gap-3.5">
        <span className="grid size-11 place-items-center rounded-[22px] bg-[#f3f4f7] text-[#12172b]">
          <IGraphique />
        </span>
        <span className="min-w-[96px] flex-1">
          <span className="block text-[15px] font-bold">{T.reservations}</span>
          <span className="mt-0.5 block text-xs text-[#8a90a2]">{annuel ? 'Nombre de réservations par mois' : 'Nombre de réservations par semaine'}</span>
        </span>
        <div role="tablist" aria-label="Période du graphique" className="flex rounded-full bg-[#f3f4f7] p-1">
          <button
            type="button"
            role="tab"
            aria-selected={!annuel}
            onClick={() => setAnnuel(false)}
            className={`h-[34px] cursor-pointer rounded-full border-none px-4 text-xs font-semibold transition-colors duration-200 ${annuel ? 'bg-transparent text-[#8a90a2]' : 'bg-marque text-white'}`}
          >
            Mensuel
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={annuel}
            onClick={() => setAnnuel(true)}
            className={`h-[34px] cursor-pointer rounded-full border-none px-[18px] text-xs font-semibold transition-colors duration-200 ${annuel ? 'bg-marque text-white' : 'bg-transparent text-[#8a90a2]'}`}
          >
            Annuel
          </button>
        </div>
        <LienFleche vers="/reservations" libelle="Ouvrir" />
      </div>
      <div className="mt-[18px] flex-1">
        {/* Les colonnes poussent depuis la base à l'arrivée, puis glissent vers leurs nouvelles valeurs quand on change de période. */}
        <GraphiqueGroupe description={T.reservations} series={SERIES_RESERVATIONS} groupes={groupes} formatValeur={formatNombre} />
      </div>
    </section>
  )
}

function CarteDossiers() {
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const { data: alertes } = useAlertes()
  const nombre = tableau?.comptes.prestataires.dossiersEnAttente
  const age = alertes?.alertes.find((a) => a.type === 'dossiers_en_attente')?.ageMinutes
  return (
    <Carte>
      <EnTeteSimple titre="Dossiers à traiter" sousTitre="Demandes d'inscription" vers="/dossiers-prestataires" libelle="Ouvrir les dossiers" />
      <div className="mt-3 box-border rounded-[22px] p-4 text-white" style={{ background: FOND_MARQUE }}>
        <div className="text-xs font-semibold opacity-80">En attente de décision</div>
        <div className="mt-1.5 flex items-baseline gap-2">
          <span className="text-[34px] leading-[38px] font-bold tracking-[-1px]">{nombre === undefined ? ABSENT : nombre}</span>
          <span className="text-sm font-semibold opacity-85">{nombre === 1 ? 'dossier' : 'dossiers'}</span>
        </div>
        <div className="mt-3 flex items-center justify-between text-xs">
          <span className="opacity-80">Le plus ancien : {age == null ? ABSENT : dureeMinutes(age)}</span>
        </div>
      </div>
    </Carte>
  )
}

function CarteMetiers({ parMetier }: { parMetier: Revenus['parMetier'] | undefined }) {
  const { data } = useQuery({ queryKey: ['metiers'], queryFn: api.metiers, retry: false })
  const montants = new Map((parMetier ?? []).map((l) => [l.metier, l.revenus]))
  const metiers = (data?.data ?? [])
    .filter((m) => m.actif || montants.has(m.code))
    .map((m) => ({ ...m, revenus: parMetier === undefined ? undefined : (montants.get(m.code) ?? 0) }))
    .sort((a, b) => (b.revenus ?? 0) - (a.revenus ?? 0))
    .slice(0, 3)
  // Part de chaque métier dans les commissions de la période (tous métiers confondus, pas seulement les trois affichés).
  const total = (parMetier ?? []).reduce((somme, l) => somme + l.revenus, 0)
  return (
    <Carte>
      <div className="text-[15px] leading-[normal] font-bold">Revenus par métier</div>
      <div className="mt-0.5 text-xs leading-[normal] text-[#8a90a2]">Part des commissions Sopi, 30 derniers jours</div>
      <div className="mt-4 flex gap-2.5">
        {metiers.map((m) => (
          <JaugeMetier
            key={m.id}
            libelle={m.libelle}
            part={m.revenus === undefined ? undefined : total > 0 ? m.revenus / total : 0}
            detail={m.revenus === undefined ? ABSENT : `${formatNombre(m.revenus)} FCFA`}
          />
        ))}
      </div>
    </Carte>
  )
}

const STATUTS_RESERVATION = ['en_attente', 'acceptee', 'confirmee', 'en_cours', 'terminee', 'refusee', 'annulee'] as const

function LigneCompte({ libelle, valeur, point }: { libelle: string; valeur: number | undefined; point?: string }) {
  return (
    <li className="flex items-center justify-between gap-3 py-2 text-sm">
      <span className="inline-flex items-center gap-2 font-medium">
        {point && <span aria-hidden="true" className="size-1.5 rounded-full" style={{ background: point }} />}
        {libelle}
      </span>
      <span className="font-bold tabular-nums">{valeur === undefined ? ABSENT : formatNombre(valeur)}</span>
    </li>
  )
}

/** Réservations par statut (RF13) : une ligne par statut, avec pastille et nombre. */
function CarteParStatut({ parStatut }: { parStatut: Record<string, number> | undefined }) {
  return (
    <Carte>
      <div className="text-[15px] leading-[normal] font-bold">Réservations par statut</div>
      <div className="mt-0.5 text-xs leading-[normal] text-[#8a90a2]">Toutes les réservations de la plateforme</div>
      <ul className="mt-3 list-none p-0">
        {STATUTS_RESERVATION.map((s) => (
          <LigneCompte key={s} libelle={L.statuts[s]} valeur={parStatut?.[s]} point={POINT_STATUT[s]} />
        ))}
      </ul>
    </Carte>
  )
}

/** Réservations par domaine (métier) : une ligne par métier avec son total. */
function CarteParDomaine({ parDomaine }: { parDomaine: Record<string, { total: number }> | undefined }) {
  const { data } = useQuery({ queryKey: ['metiers'], queryFn: api.metiers, retry: false })
  const libelles = new Map((data?.data ?? []).map((m) => [m.code, m.libelle]))
  const lignes = Object.entries(parDomaine ?? {})
  return (
    <Carte>
      <div className="text-[15px] leading-[normal] font-bold">Réservations par domaine</div>
      <div className="mt-0.5 text-xs leading-[normal] text-[#8a90a2]">Total par métier</div>
      <ul className="mt-3 list-none p-0">
        {parDomaine === undefined && <LigneCompte libelle={ABSENT} valeur={undefined} />}
        {lignes.map(([code, d]) => (
          <LigneCompte key={code} libelle={libelles.get(code) ?? T.domaines[code] ?? code} valeur={d.total} />
        ))}
      </ul>
    </Carte>
  )
}

function CarteActivite() {
  const { data } = useQuery({ queryKey: ['activite-reservations'], queryFn: () => api.reservations({ page: 1 }), retry: false })
  const { data: paiements } = useQuery({ queryKey: ['derniers-paiements'], queryFn: api.derniersPaiements, retry: false })
  const acomptes = new Map((paiements?.data ?? []).map((p) => [p.reservationId, p.montantAcompte]))
  const lignes = (data?.data ?? []).slice(0, 4)
  return (
    <section className="box-border min-w-0 flex-[2_1_560px] rounded-[32px] bg-white p-[18px]">
      <EnTeteSimple titre="Activité récente" sousTitre="Dernières réservations de la plateforme" vers="/reservations" libelle="Tout voir" />
      <div className="mt-3.5 overflow-x-auto">
        <table className="mq-table min-w-[620px]">
          <thead>
            <tr className="text-left text-xs text-[#9aa0b2]">
              <th>Réservation</th>
              <th>Date</th>
              <th>Heure</th>
              <th>Statut</th>
              <th style={{ textAlign: "right" }}>Acompte</th>
            </tr>
          </thead>
          <tbody>
            {lignes.map((r) => {
              const acompte = acomptes.get(r.id)
              return (
                <tr key={r.id} className="rang">
                  <td>
                    <div className="flex items-center gap-3">
                      <Avatar nom={r.prestataire.nomEntreprise} taille={36} />
                      <span>
                        <span className="block font-semibold">{r.prestation.nom}</span>
                        <span className="mt-px block text-[11px] text-[#9aa0b2]">
                          {r.prestataire.nomEntreprise} · {r.client.prenom} {r.client.initialeNom}.
                        </span>
                      </span>
                    </div>
                  </td>
                  <td>{formatDate(r.creneau.debut)}</td>
                  <td className="tabular-nums">{formatHeureDuJour(r.creneau.debut)}</td>
                  <td>
                    <span className="inline-flex items-center gap-1.5 font-medium">
                      <span className="size-1.5 rounded-full" style={{ background: POINT_STATUT[r.statut] ?? '#9aa0b2' }} />
                      {L.statuts[r.statut as keyof typeof L.statuts] ?? r.statut}
                    </span>
                  </td>
                  <td><span className="font-semibold tabular-nums">{acompte === undefined ? ABSENT : `${formatNombre(acompte)} FCFA`}</span></td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </section>
  )
}

function CarteLitiges() {
  const { data } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const { data: alertes } = useAlertes()
  const ageLitige = alertes?.alertes.find((a) => a.type === 'litiges_ouverts')?.ageMinutes
  return (
    <Carte>
      <EnTeteSimple
        titre="Litiges ouverts"
        sousTitre="Décisions à rendre"
        vers="/litiges"
        libelle="Ouvrir les litiges"
        icone={
          <span className="grid size-11 place-items-center rounded-[22px] bg-[#fde9e9] text-[#b0161b]">
            <ILitiges taille={18} trait={1.9} />
          </span>
        }
      />
      <div className="mt-3 flex items-baseline gap-2.5">
        <span className="text-[32px] leading-[36px] font-bold tracking-[-1px]">{data ? data.litiges.ouverts : ABSENT}</span>
        {ageLitige != null && (
          <span className="rounded-full bg-[#fde9e9] px-2.5 py-1 text-[11px] font-bold text-[#b0161b]">Le plus ancien : {dureeMinutes(ageLitige)}</span>
        )}
      </div>
    </Carte>
  )
}

const COULEURS_PASTILLE = ['bg-marque-claire text-marque', 'bg-[#fde7f3] text-[#a3206b]', 'bg-[#d7f7e4] text-[#0e6245]', 'bg-[#fff0d4] text-[#983705]']

function CartePrestataires() {
  const { data } = useQuery({
    queryKey: ['prestataires-actifs'],
    queryFn: () => api.utilisateurs({ role: 'prestataire', statut: 'actif' }),
    retry: false,
  })
  const visibles = (data?.data ?? []).slice(0, 4)
  const reste = data ? Math.max(0, data.meta.total - visibles.length) : 0
  const rond = 'box-border grid size-11 place-items-center rounded-[22px] border-[3px] border-[#f3f4f7] text-xs font-bold'
  return (
    <Carte className="flex-1">
      <EnTeteSimple titre="Prestataires actifs" sousTitre="Inscrits et validés" vers="/utilisateurs" libelle="Ouvrir les utilisateurs" />
      <div className="mt-3 flex items-center rounded-[22px] bg-[#f3f4f7] px-4 py-2.5">
        {data === undefined && <span className="text-sm text-[#9aa0b2]">{ABSENT}</span>}
        {visibles.map((u, i) => (
          <span key={u.id} aria-hidden="true" className={`${rond} ${i > 0 ? '-ml-2.5' : ''} ${COULEURS_PASTILLE[i % 4]}`}>
            {`${u.prenom.charAt(0)}${u.nom.charAt(0)}`.toUpperCase()}
          </span>
        ))}
        {reste > 0 && (
          <span className={`${rond} -ml-2.5 bg-marque text-white`}>+{reste}</span>
        )}
      </div>
    </Carte>
  )
}

const GRAVITE_BANNIERE = { critique: 'danger', attention: 'attention', info: 'info' } as const

const CLE_MASQUEES = 'sopi.admin.alertes-masquees'

function lireMasquees(): Record<string, number> {
  try {
    const valeur: unknown = JSON.parse(localStorage.getItem(CLE_MASQUEES) ?? '{}')
    return valeur && typeof valeur === 'object' ? (valeur as Record<string, number>) : {}
  } catch {
    return {}
  }
}

function ecrireMasquees(masquees: Record<string, number>): Record<string, number> {
  try {
    localStorage.setItem(CLE_MASQUEES, JSON.stringify(masquees))
  } catch {
    /* stockage indisponible : la bannière reste masquée pour cette visite */
  }
  return masquees
}

/** Bannières d'alerte de l'administrateur : une par alerte réelle de l'API, avec le lien de l'écran concerné. */
function AlertesTableau() {
  const { data } = useAlertes()
  // La croix masque la bannière durablement (mémorisé dans le navigateur) ; elle revient si le nombre d'éléments en alerte augmente.
  const [masquees, setMasquees] = useState<Record<string, number>>(lireMasquees)
  const alertes = (data?.alertes ?? []).filter((a) => !(a.type in masquees) || a.compteur > masquees[a.type])
  if (alertes.length === 0) return null
  return (
    <div className="flex flex-col gap-2.5" aria-label={T.alertes}>
      {alertes.map((a) => (
        <BanniereAlerteMq
          key={a.type}
          gravite={GRAVITE_BANNIERE[a.gravite]}
          action={{ libelle: L.commun.voirCourt, vers: lienDeLAlerte(a) }}
          onFermer={() => setMasquees((m) => ecrireMasquees({ ...m, [a.type]: a.compteur }))}
        >
          {a.message}
        </BanniereAlerteMq>
      ))}
    </div>
  )
}

export default function TableauDeBordPage() {
  const { utilisateur } = useAuth()
  const fin = aujourdhui()
  const debut = ajouterJours(fin, -29)
  const { data } = useQuery({
    queryKey: ['tableau-de-bord-revenus', debut, fin],
    queryFn: () => api.revenus({ debut, fin, regroupement: 'jour' }),
    retry: false,
  })
  const t = data?.totaux
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  // Effectifs : le total de la pagination suffit, aucune route supplémentaire.
  const { data: clients } = useQuery({ queryKey: ['clients-actifs'], queryFn: () => api.utilisateurs({ role: 'client', statut: 'actif' }), retry: false })
  const { data: artisans } = useQuery({ queryKey: ['prestataires-actifs'], queryFn: () => api.utilisateurs({ role: 'prestataire', statut: 'actif' }), retry: false })
  const periode = `${formatJourCalendaire(debut, true)} – ${formatJourCalendaire(fin)}`

  return (
    <div className="flex flex-col gap-4">
      <TitrePage compact titre={`${T.bienvenue}`} suite={utilisateur?.prenom}>
        <span className="flex h-10 items-center gap-2.5 rounded-3xl bg-white pr-5 pl-4 text-[13px] font-semibold text-[#12172b]">
          <ICalendrier />
          {periode}
        </span>
      </TitrePage>

      <AlertesTableau />

      <div className="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        <CarteChiffre degrade titre="Revenus Sopi (commissions)" valeur={t?.revenus} unite="FCFA" pastille="30 j" note="30 derniers jours" />
        <CarteChiffre titre="Acomptes encaissés" valeur={t?.acomptesEncaisses} unite="FCFA" pastille="30 j" note="30 derniers jours" />
        <CarteChiffre titre="Montants retirés" valeur={t?.retraitsVerses} unite="FCFA" pastille="30 j" note="Retraits versés, 30 derniers jours" />
        <CarteChiffre titre="Utilisateurs" valeur={clients?.meta.total} pastille="Clients" note="Clients actifs" />
        <CarteChiffre titre="Artisans" valeur={artisans?.meta.total} pastille="Prestataires" note="Prestataires actifs" />
        <CarteChiffre
          titre="Réservations"
          valeur={tableau?.reservations.total}
          pastille="30 j"
          note={tableau ? `${formatNombre(tableau.reservations.activiteRecente.derniers30Jours.creees)} créées, ${formatNombre(tableau.reservations.activiteRecente.derniers30Jours.terminees)} terminées` : '30 derniers jours'}
        />
      </div>

      <div className="flex flex-wrap items-stretch gap-3.5">
        <CarteReservations />
        <div className="flex min-w-0 flex-[1_1_300px] flex-col gap-3.5">
          <CarteDossiers />
          <CarteLitiges />
        </div>
        <div className="flex min-w-0 flex-[1_1_300px] flex-col gap-3.5">
          <CarteMetiers parMetier={data?.parMetier} />
          <CartePrestataires />
        </div>
      </div>

      <div className="flex flex-wrap items-stretch gap-3.5">
        <CarteActivite />
        <div className="flex min-w-0 flex-[1_1_300px] flex-col gap-3.5">
          <CarteParStatut parStatut={tableau?.reservations.parStatut} />
          <CarteParDomaine parDomaine={tableau?.reservations.parDomaine} />
        </div>
      </div>
    </div>
  )
}
