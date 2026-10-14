import { useQuery } from '@tanstack/react-query'
import { api } from '../api/endpoints'
import { messageDErreur } from '../api/client'
import type { Alerte, Gravite, TableauDeBord } from '../api/types'
import { lienDeLAlerte, useAlertes } from '../api/useAlertes'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import Bloc from '../components/ui/Bloc'
import Carte from '../components/ui/Carte'
import CarteChiffre from '../components/ui/CarteChiffre'
import { GraphiqueBarres } from '../components/ui/Graphiques'
import {
  IconeAvis,
  IconeLitige,
  IconePaiement,
  IconeReservation,
  IconeSanction,
  IconeUtilisateurs,
} from '../components/ui/Icones'
import NombreAnime from '../components/ui/NombreAnime'
import { L } from '../i18n/libelles'
import { formatDateHeure, formatMontant, formatNombre } from '../lib/format'

const T = L.tableauDeBord
const ORDRE_GRAVITE: Record<Gravite, number> = { critique: 0, attention: 1, info: 2 }
const STATUTS_RESERVATION = ['en_attente', 'acceptee', 'confirmee', 'en_cours', 'terminee', 'refusee', 'annulee'] as const
const STATUTS_PAIEMENT = ['en_attente', 'reussi', 'echoue', 'rembourse'] as const

/** Alertes de la plus grave à la moins grave, puis les plus nombreuses d'abord. */
export function trierAlertes(alertes: Alerte[]): Alerte[] {
  return [...alertes].sort((a, b) => ORDRE_GRAVITE[a.gravite] - ORDRE_GRAVITE[b.gravite] || b.compteur - a.compteur)
}

function detailAlerte(a: Alerte): string | undefined {
  if (a.echeanceDansMinutes !== null) return T.echeanceMinutes(a.echeanceDansMinutes)
  if (a.ageMinutes !== null) return T.ancienneteMinutes(a.ageMinutes)
  return undefined
}

const nb = (n: number) => <NombreAnime valeur={n} />

function BlocAlertes() {
  const { data, isPending, isError, error, refetch } = useAlertes()
  const alertes = data ? trierAlertes(data.alertes) : []
  return (
    <Bloc
      titre={T.alertes}
      chargement={isPending}
      erreur={isError ? messageDErreur(error) : null}
      onReessayer={() => void refetch()}
      hauteur="h-20"
    >
      {alertes.length === 0 ? (
        <p role="status" className="rounded-grand bg-succes-fond px-5 py-4 text-sm font-medium text-succes-texte">
          {T.aucuneAlerte}
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {alertes.map((a, i) => (
            <BanniereAlerte
              key={a.type}
              gravite={a.gravite}
              titre={a.message}
              detail={detailAlerte(a)}
              vers={lienDeLAlerte(a)}
              delai={i * 70}
            />
          ))}
        </div>
      )}
    </Bloc>
  )
}

function Effectifs({ d }: { d: TableauDeBord }) {
  const { clients, prestataires, administrateurs } = d.comptes
  const detail = (p: typeof clients.parStatut) =>
    `${formatNombre(p.actif)} actifs · ${formatNombre(p.suspendu)} suspendus · ${formatNombre(p.banni)} bannis`
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <CarteChiffre titre={T.clients} valeur={nb(clients.total)} aide={detail(clients.parStatut)} icone={<IconeUtilisateurs />} teinte="violet" />
      <CarteChiffre titre={T.prestataires} valeur={nb(prestataires.total)} aide={detail(prestataires.parStatut)} icone={<IconeUtilisateurs />} teinte="cyan" delai={70} />
      <CarteChiffre titre={T.dossiersEnAttente} valeur={nb(prestataires.dossiersEnAttente)} icone={<IconeReservation />} teinte="rose" delai={140} />
      <CarteChiffre titre={T.administrateurs} valeur={nb(administrateurs)} icone={<IconeUtilisateurs />} delai={210} />
    </div>
  )
}

function Reservations({ d }: { d: TableauDeBord }) {
  const r = d.reservations
  const domaines = Object.entries(r.parDomaine)
  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 lg:grid-cols-3">
        <Carte className="lg:col-span-2">
          <p className="mb-2 text-sm font-medium text-texte-doux">
            {T.reservationsTotal} : <strong className="text-texte">{formatNombre(r.total)}</strong> — {T.parStatut.toLowerCase()}
          </p>
          <GraphiqueBarres
            description={`Réservations ${T.parStatut.toLowerCase()}`}
            donnees={STATUTS_RESERVATION.map((s) => ({ libelle: L.statuts[s], valeur: r.parStatut[s] }))}
          />
        </Carte>
        <div className="grid gap-4">
          <CarteChiffre
            titre={`${T.creees} · ${T.sept}`}
            valeur={nb(r.activiteRecente.derniers7Jours.creees)}
            aide={`${formatNombre(r.activiteRecente.derniers7Jours.terminees)} ${T.terminees.toLowerCase()}`}
            icone={<IconeReservation />}
          />
          <CarteChiffre
            titre={`${T.creees} · ${T.trente}`}
            valeur={nb(r.activiteRecente.derniers30Jours.creees)}
            aide={`${formatNombre(r.activiteRecente.derniers30Jours.terminees)} ${T.terminees.toLowerCase()}`}
            icone={<IconeReservation />}
            teinte="cyan"
            delai={70}
          />
        </div>
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        {domaines.map(([cle, dom], i) => (
          <CarteChiffre
            key={cle}
            titre={T.domaines[cle] ?? cle}
            valeur={nb(dom.total)}
            aide={`${formatNombre(dom.parStatut.terminee)} ${T.terminees.toLowerCase()} · ${formatNombre(dom.parStatut.annulee)} annulées`}
            teinte={(['bleu', 'violet', 'rose'] as const)[i % 3]}
            delai={i * 70}
          />
        ))}
      </div>
    </div>
  )
}

function Paiements({ d }: { d: TableauDeBord }) {
  const p = d.paiements
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <CarteChiffre titre={T.paiementsTotal} valeur={nb(p.total.nombre)} aide={formatMontant(p.total.montant)} icone={<IconePaiement />} teinte="rose" />
      {STATUTS_PAIEMENT.map((s, i) => (
        <CarteChiffre key={s} titre={L.statuts[s]} valeur={nb(p.parStatut[s].nombre)} aide={formatMontant(p.parStatut[s].montant)} delai={(i + 1) * 70} />
      ))}
    </div>
  )
}

function LitigesAvisSanctions({ d }: { d: TableauDeBord }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <CarteChiffre titre={`${T.litiges} · ${T.litigesOuverts.toLowerCase()}`} valeur={nb(d.litiges.ouverts)} aide={`${formatNombre(d.litiges.clos)} ${T.litigesClos.toLowerCase()} sur ${formatNombre(d.litiges.total)}`} icone={<IconeLitige />} teinte="rose" />
      <CarteChiffre titre={T.avisSignales} valeur={nb(d.avis.signales)} aide={`${formatNombre(d.avis.total)} ${T.avisTotal.toLowerCase()} · ${formatNombre(d.avis.masques)} ${T.avisMasques.toLowerCase()}`} icone={<IconeAvis />} teinte="violet" delai={70} />
      <CarteChiffre
        titre={T.noteMoyenne}
        valeur={d.avis.noteMoyenne === null ? '—' : `${d.avis.noteMoyenne.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} / 5`}
        icone={<IconeAvis />}
        teinte="cyan"
        delai={140}
      />
      <CarteChiffre titre={T.suspensionsEnCours} valeur={nb(d.sanctions.suspensionsEnCours)} aide={`${formatNombre(d.sanctions.bannissements)} ${T.bannissements.toLowerCase()}`} icone={<IconeSanction />} delai={210} />
    </div>
  )
}

export default function TableauDeBordPage() {
  const requete = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const { data, isPending, isError, error, refetch } = requete
  const etat = {
    chargement: isPending,
    erreur: isError ? messageDErreur(error) : null,
    onReessayer: () => void refetch(),
  }

  return (
    <div className="flex flex-col gap-8">
      <header className="animate-apparition">
        <h1 className="text-titre-page font-bold text-primaire-800">{T.titre}</h1>
        <p className="mt-1 text-sm text-texte-doux">
          {T.sousTitre}
          {data && ` ${T.misAJour(formatDateHeure(data.dateGeneration))}.`}
        </p>
      </header>

      <BlocAlertes />

      <Bloc titre={T.effectifs} {...etat} hauteur="h-32">
        {data && <Effectifs d={data} />}
      </Bloc>
      <Bloc titre={T.reservations} {...etat} hauteur="h-72">
        {data && <Reservations d={data} />}
      </Bloc>
      <Bloc titre={T.paiements} {...etat} hauteur="h-32">
        {data && <Paiements d={data} />}
      </Bloc>
      <Bloc titre={`${T.litiges}, ${T.avis.toLowerCase()} et ${T.sanctions.toLowerCase()}`} {...etat} hauteur="h-32">
        {data && <LitigesAvisSanctions d={data} />}
      </Bloc>
    </div>
  )
}
