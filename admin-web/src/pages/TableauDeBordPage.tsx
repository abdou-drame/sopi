import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/endpoints'
import { lienDeLAlerte, useAlertes } from '../api/useAlertes'
import { useAuth } from '../auth/AuthContext'
import { BanniereAlerteMq } from '../design/bannieres'
import { Avatar, Carte, DEGRADE_BLEU, TitrePage } from '../design/composants'
import { ICalendrier, IFlecheHautDroite, IGraphique, ILitiges } from '../design/icones'
import { L, dureeMinutes } from '../i18n/libelles'
import { ajouterJours, aujourdhui, formatDate, formatHeureDuJour, formatJourCalendaire, formatNombre } from '../lib/format'

const T = L.tableauDeBord
const LR = L.revenus
const ABSENT = '—'

/** Couleur du point de statut d'une réservation (statuts du backend). */
const POINT_STATUT: Record<string, string> = {
  en_attente: '#f59e0b',
  acceptee: '#1d4ed8',
  confirmee: '#1d4ed8',
  en_cours: '#1d4ed8',
  terminee: '#16a34a',
  refusee: '#e5484d',
  annulee: '#9aa0b2',
}

const COULEURS_METIER = ['#1d4ed8', '#5b83ef', '#a9bdf6']

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
}: {
  degrade?: boolean
  titre: string
  valeur?: number
  note: string
  pastille: string
}) {
  const clair = degrade ? 'opacity-80' : 'text-[#9aa0b2]'
  return (
    <section
      className={`box-border rounded-[28px] px-5 py-[18px] ${degrade ? 'text-white' : 'bg-white'}`}
      style={degrade ? { background: DEGRADE_BLEU } : undefined}
    >
      <div className="flex items-center justify-between">
        <span className={`text-xs font-semibold ${degrade ? 'opacity-85' : 'text-[#8a90a2]'}`}>{titre}</span>
        <span className={`rounded-full px-[9px] py-[3px] text-[11px] font-bold ${degrade ? 'bg-white/20' : 'bg-[#f3f4f7] text-[#9aa0b2]'}`}>{pastille}</span>
      </div>
      <div className="mt-2 text-[30px] leading-[34px] font-bold tracking-[-0.8px] tabular-nums">
        {valeur === undefined ? ABSENT : formatNombre(valeur)} <span className={`text-sm font-medium ${clair}`}>FCFA</span>
      </div>
      <div className={`mt-1.5 text-xs ${degrade ? 'opacity-80' : 'text-[#8a90a2]'}`}>{note}</div>
    </section>
  )
}

/** Six derniers mois (libellés du calendrier) : aucune donnée par mois n'existe dans l'API, les valeurs restent à « — ». */
function derniersMois(): string[] {
  const maintenant = new Date()
  const format = new Intl.DateTimeFormat('fr-FR', { month: 'short' })
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(maintenant.getFullYear(), maintenant.getMonth() - (5 - i), 1)
    const m = format.format(d)
    return m.charAt(0).toUpperCase() + m.slice(1)
  })
}

function CarteReservations() {
  const [annuel, setAnnuel] = useState(true)
  const mois = derniersMois()
  return (
    <section className="box-border flex min-h-[435px] min-w-0 flex-[2_1_560px] flex-col rounded-[32px] bg-white p-[18px]">
      <div className="flex flex-wrap items-center gap-3.5">
        <span className="grid size-11 place-items-center rounded-[22px] bg-[#f3f4f7] text-[#12172b]">
          <IGraphique />
        </span>
        <span className="min-w-[140px] flex-1">
          <span className="block text-[15px] font-bold">{T.reservations}</span>
          <span className="mt-0.5 block text-xs text-[#8a90a2]">Nombre de réservations par mois</span>
        </span>
        <div role="tablist" aria-label="Période du graphique" className="flex rounded-full bg-[#f3f4f7] p-1">
          <button
            type="button"
            role="tab"
            aria-selected={!annuel}
            onClick={() => setAnnuel(false)}
            className={`h-[34px] cursor-pointer rounded-full border-none px-4 text-xs font-semibold ${annuel ? 'bg-transparent text-[#8a90a2]' : 'bg-[#1d4ed8] text-white'}`}
          >
            Mensuel
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={annuel}
            onClick={() => setAnnuel(true)}
            className={`h-[34px] cursor-pointer rounded-full border-none px-[18px] text-xs font-semibold ${annuel ? 'bg-[#1d4ed8] text-white' : 'bg-transparent text-[#8a90a2]'}`}
          >
            Annuel
          </button>
        </div>
        <LienFleche vers="/reservations" libelle="Ouvrir" />
      </div>
      <div className="mt-[18px] flex min-h-[230px] flex-1 gap-3">
        <div className="flex flex-col justify-between pb-[26px] text-right text-[11px] text-[#9aa0b2] tabular-nums" aria-hidden="true">
          {[0, 1, 2, 3, 4, 5].map((i) => (
            <span key={i}>{ABSENT}</span>
          ))}
        </div>
        <div className="relative min-w-0 flex-1">
          <div className="absolute inset-x-0 top-1.5 bottom-8 flex flex-col justify-between" aria-hidden="true">
            {[0, 1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="border-t border-dashed border-[#e6e8ee]" />
            ))}
          </div>
          <div className="relative box-border flex h-full justify-around gap-[clamp(8px,2.5vw,36px)] px-2 pt-11">
            {mois.map((m) => (
              <div key={m} className="flex h-full max-w-[84px] min-w-0 flex-[1_1_0] flex-col items-center">
                <div className="flex w-full flex-1 items-end">
                  <div className="grid h-full w-full place-items-center rounded-full bg-[#f3f4f7] text-[11px] text-[#9aa0b2]">{ABSENT}</div>
                </div>
                <div className="flex h-[26px] items-end text-[11px] font-medium text-[#9aa0b2]">{m}</div>
              </div>
            ))}
          </div>
        </div>
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
      <div className="mt-[18px] box-border rounded-[22px] p-5 text-white" style={{ background: DEGRADE_BLEU }}>
        <div className="text-xs font-semibold opacity-80">En attente de décision</div>
        <div className="mt-1.5 flex items-baseline gap-2">
          <span className="text-[44px] leading-[48px] font-bold tracking-[-1px]">{nombre === undefined ? ABSENT : nombre}</span>
          <span className="text-sm font-semibold opacity-85">{nombre === 1 ? 'dossier' : 'dossiers'}</span>
        </div>
        <div className="mt-[22px] flex items-center justify-between text-xs">
          <span className="opacity-80">Le plus ancien : {age == null ? ABSENT : dureeMinutes(age)}</span>
          <span className="rounded-full bg-white/20 px-2.5 py-1 font-bold">{ABSENT} prioritaires</span>
        </div>
      </div>
    </Carte>
  )
}

function CarteMetiers() {
  const { data } = useQuery({ queryKey: ['metiers'], queryFn: api.metiers, retry: false })
  const metiers = (data?.data ?? []).filter((m) => m.actif).slice(0, 3)
  return (
    <Carte>
      <div className="text-[15px] leading-[normal] font-bold">Revenus par métier</div>
      <div className="mt-0.5 text-xs leading-[normal] text-[#8a90a2]">Commissions Sopi, 30 derniers jours</div>
      {metiers.map((m, i) => (
        <div key={m.id} className="mt-3">
          <div className="flex justify-between text-xs leading-[normal]">
            <span className="font-semibold">{m.libelle}</span>
            <span className="text-[#6b7287] tabular-nums">{ABSENT}</span>
          </div>
          <div className="mt-1.5 h-2.5 rounded-full bg-[#f3f4f7]">
            <div className="h-full rounded-full" style={{ width: 0, background: COULEURS_METIER[i] }} />
          </div>
        </div>
      ))}
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
      <div className="mt-[18px] flex items-baseline gap-2.5">
        <span className="text-[40px] leading-[44px] font-bold tracking-[-1px]">{data ? data.litiges.ouverts : ABSENT}</span>
        <span className="rounded-full bg-[#fde9e9] px-2.5 py-1 text-[11px] font-bold text-[#b0161b]">{ABSENT} sans réponse depuis 48 h</span>
      </div>
    </Carte>
  )
}

const COULEURS_PASTILLE = ['bg-[#dbeafe] text-[#1d4ed8]', 'bg-[#fde7f3] text-[#a3206b]', 'bg-[#d7f7e4] text-[#0e6245]', 'bg-[#fff0d4] text-[#983705]']

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
      <div className="mt-[18px] flex items-center rounded-[22px] bg-[#f3f4f7] px-4 py-3.5">
        {data === undefined && <span className="text-sm text-[#9aa0b2]">{ABSENT}</span>}
        {visibles.map((u, i) => (
          <span key={u.id} aria-hidden="true" className={`${rond} ${i > 0 ? '-ml-2.5' : ''} ${COULEURS_PASTILLE[i % 4]}`}>
            {`${u.prenom.charAt(0)}${u.nom.charAt(0)}`.toUpperCase()}
          </span>
        ))}
        {reste > 0 && (
          <span className={`${rond} -ml-2.5 bg-[#1d4ed8] text-white`}>+{reste}</span>
        )}
      </div>
    </Carte>
  )
}

const GRAVITE_BANNIERE = { critique: 'danger', attention: 'attention', info: 'info' } as const

/** Bannières d'alerte de l'administrateur : une par alerte réelle de l'API, avec le lien de l'écran concerné. */
function AlertesTableau() {
  const { data } = useAlertes()
  const alertes = data?.alertes ?? []
  if (alertes.length === 0) return null
  return (
    <div className="flex flex-col gap-2.5" aria-label={T.alertes}>
      {alertes.map((a) => (
        <BanniereAlerteMq key={a.type} gravite={GRAVITE_BANNIERE[a.gravite]} action={{ libelle: L.commun.voirCourt, vers: lienDeLAlerte(a) }}>
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
  const periode = `${formatJourCalendaire(debut, true)} – ${formatJourCalendaire(fin)}`

  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre={`${T.bienvenue}`} suite={utilisateur?.prenom}>
        <span className="flex h-12 items-center gap-2.5 rounded-3xl bg-white pr-5 pl-4 text-[13px] font-semibold text-[#12172b]">
          <ICalendrier />
          {periode}
        </span>
      </TitrePage>

      <AlertesTableau />

      <div className="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        <CarteChiffre degrade titre="Revenus Sopi (commissions)" valeur={t?.revenus} pastille={ABSENT} note="30 derniers jours" />
        <CarteChiffre titre={LR.acomptesEncaisses} valeur={t?.acomptesEncaisses} pastille={ABSENT} note="30 derniers jours" />
        <CarteChiffre titre="Acomptes remboursés" valeur={t?.remboursements} pastille={ABSENT} note="Refus, annulations, litiges" />
        <CarteChiffre titre="Reversé aux prestataires" valeur={t?.retraitsVerses} pastille={ABSENT} note="Acomptes − remboursés − commissions" />
      </div>

      <div className="flex flex-wrap items-stretch gap-3.5">
        <CarteReservations />
        <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
          <CarteDossiers />
          <CarteMetiers />
        </div>
      </div>

      <div className="flex flex-wrap items-stretch gap-3.5">
        <CarteActivite />
        <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
          <CarteLitiges />
          <CartePrestataires />
        </div>
      </div>
    </div>
  )
}
