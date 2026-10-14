import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { api } from '../api/endpoints'
import { messageDErreur } from '../api/client'
import type { Alerte, Gravite, PointRevenus, TableauDeBord } from '../api/types'
import { lienDeLAlerte, useAlertes } from '../api/useAlertes'
import BanniereAlerte from '../components/ui/BanniereAlerte'
import BasculeGelules from '../components/ui/BasculeGelules'
import Bloc from '../components/ui/Bloc'
import Bouton from '../components/ui/Bouton'
import BoutonRond from '../components/ui/BoutonRond'
import Carte from '../components/ui/Carte'
import CarteChiffre from '../components/ui/CarteChiffre'
import CarteSopi from '../components/ui/CarteSopi'
import DerniersPaiements from '../components/ui/DerniersPaiements'
import EnTetePage from '../components/ui/EnTetePage'
import { GraphiqueBarres, GraphiqueCourbe } from '../components/ui/Graphiques'
import { IconeAvis, IconeDossier, IconeFleche, IconeLitige } from '../components/ui/Icones'
import LienGelule from '../components/ui/LienGelule'
import NombreAnime from '../components/ui/NombreAnime'
import Squelette from '../components/ui/Squelette'
import { L } from '../i18n/libelles'
import { ajouterJours, aujourdhui, formatDateHeure, formatJourCalendaire, formatMontant, formatNombre } from '../lib/format'

const T = L.tableauDeBord
const ORDRE_GRAVITE: Record<Gravite, number> = { critique: 0, attention: 1, info: 2 }
const NOMBRE_BARRES_MAX = 7

/** Alertes de la plus grave à la moins grave, puis les plus nombreuses d'abord. */
export function trierAlertes(alertes: Alerte[]): Alerte[] {
  return [...alertes].sort((a, b) => ORDRE_GRAVITE[a.gravite] - ORDRE_GRAVITE[b.gravite] || b.compteur - a.compteur)
}

function detailAlerte(a: Alerte): string | undefined {
  if (a.echeanceDansMinutes !== null) return T.echeanceMinutes(a.echeanceDansMinutes)
  if (a.ageMinutes !== null) return T.ancienneteMinutes(a.ageMinutes)
  return undefined
}

/**
 * Regroupe les points journaliers pour garder au plus 7 barres lisibles : 7 jours = une barre par jour,
 * 30 jours = une barre par tranche de 5 jours (somme des acomptes encaissés, étiquetée par le dernier jour).
 */
export function regrouperAcomptes(series: PointRevenus[]): { libelle: string; valeur: number }[] {
  const taille = Math.max(1, Math.ceil(series.length / NOMBRE_BARRES_MAX))
  const barres: { libelle: string; valeur: number }[] = []
  for (let i = 0; i < series.length; i += taille) {
    const groupe = series.slice(i, i + taille)
    barres.push({
      libelle: formatJourCalendaire(groupe[groupe.length - 1].fin, true),
      valeur: groupe.reduce((somme, p) => somme + p.acomptesEncaisses, 0),
    })
  }
  return barres
}

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

/** Message d'erreur d'une carte, avec « Réessayer » (ne révèle rien de technique : le message vient du client d'API). */
function ErreurCarte({ message, onReessayer }: { message: string; onReessayer: () => void }) {
  return (
    <div role="alert" className="flex flex-col items-start gap-3 rounded-grand bg-erreur-fond p-5 text-erreur-texte">
      <p className="text-sm font-medium">{message}</p>
      <Bouton variante="secondaire" taille="p" onClick={onReessayer}>
        {L.commun.reessayer}
      </Bouton>
    </div>
  )
}

function CarteReservations({ d, erreur, chargement, onReessayer }: { d?: TableauDeBord; erreur: string | null; chargement: boolean; onReessayer: () => void }) {
  const activite = d?.reservations.activiteRecente
  return (
    <Carte
      titre={T.carteReservationsTitre}
      sousTitre={T.carteReservationsSousTitre}
      action={<BoutonRond libelle={T.ouvrirReservations} icone={<IconeFleche />} vers="/reservations" />}
    >
      <CarteSopi
        libelle={T.carteTerminees30}
        montant={activite ? formatNombre(activite.derniers30Jours.terminees) : ''}
        lignes={[
          { libelle: T.carteCreees7, valeur: activite ? formatNombre(activite.derniers7Jours.creees) : '' },
          { libelle: T.cartePaiementsReussis, valeur: d ? formatNombre(d.paiements.parStatut.reussi.nombre) : '' },
        ]}
        chargement={chargement}
        erreur={erreur}
        onReessayer={onReessayer}
      />
      <div className="mt-5 flex items-end justify-between gap-3">
        <div>
          <p className="text-micro text-texte-doux">{T.creeesSur30}</p>
          <p className="mt-0.5 text-xl font-bold text-texte">{activite ? <NombreAnime valeur={activite.derniers30Jours.creees} /> : '—'}</p>
        </div>
        <LienGelule vers="/reservations">{T.voirReservations}</LienGelule>
      </div>
    </Carte>
  )
}

type Periode = '7' | '30'

function CarteBarres() {
  const [periode, setPeriode] = useState<Periode>('7')
  const { data, isPending, isError, error, refetch } = useRevenus30Jours()
  const barres = useMemo(() => {
    const serie = data?.series ?? []
    return regrouperAcomptes(periode === '7' ? serie.slice(-7) : serie)
  }, [data, periode])
  const vide = !!data && barres.every((b) => b.valeur === 0)

  return (
    <Carte
      titre={T.barresTitre}
      sousTitre={T.barresSousTitre}
      action={
        <div className="flex items-center gap-2">
          <BasculeGelules
            legende={T.basculePeriode}
            valeur={periode}
            onChange={setPeriode}
            options={[
              { valeur: '7', libelle: T.sept30.sept },
              { valeur: '30', libelle: T.sept30.trente },
            ]}
          />
          <BoutonRond libelle={T.ouvrirPaiements} icone={<IconeFleche />} vers="/paiements" />
        </div>
      }
    >
      {isPending ? (
        <div role="status" aria-label={`${L.commun.chargement} ${T.barresTitre}`}>
          <Squelette className="h-64 w-full rounded-grand" />
        </div>
      ) : isError ? (
        <ErreurCarte message={messageDErreur(error)} onReessayer={() => void refetch()} />
      ) : vide ? (
        <p className="py-16 text-center text-sm text-texte-doux">{T.barresVide}</p>
      ) : (
        <GraphiqueBarres key={periode} description={T.barresDescription} donnees={barres} formatValeur={formatMontant} />
      )}
    </Carte>
  )
}

function CarteCommission() {
  const { data, isPending, isError, error, refetch } = useRevenus30Jours()
  const serie = data?.series ?? []
  const total = serie.reduce((somme, p) => somme + p.revenus, 0)
  return (
    <Carte
      titre={T.commissionTitre}
      sousTitre={T.commissionSousTitre}
      action={<BoutonRond libelle={T.ouvrirRevenus} icone={<IconeFleche />} vers="/revenus" />}
    >
      {isPending ? (
        <div role="status" aria-label={`${L.commun.chargement} ${T.commissionTitre}`}>
          <Squelette className="h-10 w-48" />
          <Squelette className="mt-4 h-32 w-full rounded-grand" />
        </div>
      ) : isError ? (
        <ErreurCarte message={messageDErreur(error)} onReessayer={() => void refetch()} />
      ) : (
        <>
          <p className="text-center text-chiffre font-bold tracking-tight text-texte">
            <NombreAnime valeur={total} format={formatMontant} />
          </p>
          <div className="mt-2">
            <GraphiqueCourbe
              description={T.courbeDescription}
              libelles={serie.map((p) => formatJourCalendaire(p.debut, true))}
              series={[{ nom: T.commissionTitre, couleur: 'var(--color-primaire-500)', valeurs: serie.map((p) => p.revenus) }]}
              formatValeur={formatMontant}
            />
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            <LienGelule vers="/revenus">{T.voirRevenus}</LienGelule>
            <LienGelule vers="/paiements" variante="claire">
              {T.voirPaiements}
            </LienGelule>
          </div>
        </>
      )}
    </Carte>
  )
}

function CarteDerniersPaiements() {
  const { data, isPending, isError, error, refetch } = useQuery({ queryKey: ['derniers-paiements'], queryFn: api.derniersPaiements, retry: false })
  return (
    <Carte
      titre={L.derniersPaiements.titre}
      sousTitre={L.derniersPaiements.sousTitre}
      action={<BoutonRond libelle={T.ouvrirPaiements} icone={<IconeFleche />} vers="/paiements" />}
    >
      <DerniersPaiements
        paiements={(data?.data ?? []).slice(0, 5)}
        chargement={isPending}
        erreur={isError ? messageDErreur(error) : null}
        onReessayer={() => void refetch()}
      />
    </Carte>
  )
}

function useRevenus30Jours() {
  const fin = aujourdhui()
  const debut = ajouterJours(fin, -29)
  return useQuery({
    queryKey: ['tableau-de-bord-revenus', debut, fin],
    queryFn: () => api.revenus({ debut, fin, regroupement: 'jour' }),
    retry: false,
  })
}

function CarteCompteur({
  titre,
  valeur,
  vers,
  icone,
  teinte,
  chargement,
  delai,
}: {
  titre: string
  valeur?: number
  vers: string
  icone: React.ReactNode
  teinte: 'rose' | 'violet' | 'cyan'
  chargement: boolean
  delai: number
}) {
  return (
    <CarteChiffre
      titre={titre}
      valeur={valeur === undefined ? '—' : <NombreAnime valeur={valeur} />}
      icone={icone}
      teinte={teinte}
      chargement={chargement}
      delai={delai}
      action={<LienGelule vers={vers} variante="claire">{`${T.ouvrir} · ${titre}`}</LienGelule>}
    />
  )
}

export default function TableauDeBordPage() {
  const requete = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  const { data, isPending, isError, error, refetch } = requete
  const erreur = isError ? messageDErreur(error) : null

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage titre={T.titre} sousTitre={`${T.sousTitre}${data ? ` ${T.misAJour(formatDateHeure(data.dateGeneration))}.` : ''}`} />

      <BlocAlertes />

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.75fr)_minmax(0,1fr)]">
        <CarteReservations d={data} chargement={isPending} erreur={erreur} onReessayer={() => void refetch()} />
        <CarteBarres />
        <CarteCommission />

        <div className="lg:col-span-2">
          <CarteDerniersPaiements />
        </div>
        <div className="flex flex-col gap-5">
          {erreur && !isPending ? (
            <ErreurCarte message={erreur} onReessayer={() => void refetch()} />
          ) : (
            <>
              <CarteCompteur titre={T.litigesOuvertsTitre} valeur={data?.litiges.ouverts} vers="/litiges" icone={<IconeLitige />} teinte="rose" chargement={isPending} delai={0} />
              <CarteCompteur titre={T.avisATraiterTitre} valeur={data?.avis.signales} vers="/avis?aTraiter=1" icone={<IconeAvis />} teinte="violet" chargement={isPending} delai={70} />
              <CarteCompteur titre={T.dossiersTitre} valeur={data?.comptes.prestataires.dossiersEnAttente} vers="/dossiers-prestataires" icone={<IconeDossier />} teinte="cyan" chargement={isPending} delai={140} />
            </>
          )}
        </div>
      </div>
    </div>
  )
}
