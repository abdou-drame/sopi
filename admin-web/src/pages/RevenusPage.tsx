import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../api/endpoints'
import { erreursParChamp, messageDErreur } from '../api/client'
import type { PointRevenus, Regroupement } from '../api/types'
import BarreFiltres from '../components/ui/BarreFiltres'
import Bloc from '../components/ui/Bloc'
import EnTetePage from '../components/ui/EnTetePage'
import CarteSopi from '../components/ui/CarteSopi'
import Carte from '../components/ui/Carte'
import CarteChiffre from '../components/ui/CarteChiffre'
import { ChampDate, Selecteur } from '../components/ui/Champs'
import { GraphiqueCourbe } from '../components/ui/Graphiques'
import { IconeGraphique, IconePaiement, IconeRetrait, IconeRevenus } from '../components/ui/Icones'
import NombreAnime from '../components/ui/NombreAnime'
import Tableau from '../components/ui/Tableau'
import { L } from '../i18n/libelles'
import { ajouterJours, aujourdhui, formatDateHeure, formatJourCalendaire, formatMontant, nombreDeJours } from '../lib/format'

const T = L.revenus
const MAX_JOURS = 366
const PAR_PAGE = 10

interface Filtres {
  debut: string
  fin: string
  regroupement: Regroupement
}

function filtresParDefaut(): Filtres {
  const fin = aujourdhui()
  return { debut: ajouterJours(fin, -29), fin, regroupement: 'jour' }
}

/** Vérifie la période côté écran pour guider la saisie ; l'API reste la référence (elle répond 422 sinon). */
export function erreurPeriode(f: Filtres): string | null {
  if (!f.debut || !f.fin || f.fin < f.debut) return T.periodeInvalide
  if (nombreDeJours(f.debut, f.fin) > MAX_JOURS) return T.periodeTropLongue
  return null
}

/** Libellé court d'un point de la série selon le regroupement. */
function libelleAxe(p: PointRevenus, regroupement: Regroupement): string {
  if (regroupement === 'mois') {
    const [a, m] = p.debut.split('-').map(Number)
    return new Intl.DateTimeFormat('fr-FR', { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(new Date(Date.UTC(a, m - 1, 15)))
  }
  return formatJourCalendaire(p.debut, true)
}

function libellePeriode(p: PointRevenus, regroupement: Regroupement): string {
  if (regroupement === 'jour' || p.debut === p.fin) return formatJourCalendaire(p.debut)
  return `${formatJourCalendaire(p.debut, true)} – ${formatJourCalendaire(p.fin)}`
}

export default function RevenusPage() {
  const [saisie, setSaisie] = useState<Filtres>(filtresParDefaut)
  const [appliques, setAppliques] = useState<Filtres>(saisie)
  const [page, setPage] = useState(1)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['revenus', appliques],
    queryFn: () => api.revenus(appliques),
    retry: false,
  })

  const erreurSaisie = erreurPeriode(saisie)
  const erreursApi = erreursParChamp(error)
  const appliquer = () => {
    if (!erreurSaisie) {
      setAppliques(saisie)
      setPage(1)
    }
  }
  const reinitialiser = () => {
    const defaut = filtresParDefaut()
    setSaisie(defaut)
    setAppliques(defaut)
    setPage(1)
  }
  const modifier = (changement: Partial<Filtres>) => setSaisie((s) => ({ ...s, ...changement }))

  const series = data?.series ?? []
  const totalPages = Math.max(1, Math.ceil(series.length / PAR_PAGE))
  const pageCourante = Math.min(page, totalPages)
  const seriePage = series.slice((pageCourante - 1) * PAR_PAGE, pageCourante * PAR_PAGE)
  const regroupement = data?.periode.regroupement ?? appliques.regroupement
  const etat = {
    chargement: isPending,
    erreur: isError ? messageDErreur(error) : null,
    onReessayer: () => void refetch(),
  }

  return (
    <div className="flex flex-col gap-5">
      <EnTetePage titre={T.titre} sousTitre={`${T.sousTitre}${data ? ` ${L.tableauDeBord.misAJour(formatDateHeure(data.dateGeneration))}.` : ''}`} />

      <BarreFiltres legende={T.titre} onAppliquer={appliquer} libelleAppliquer={T.appliquer} onReinitialiser={reinitialiser}>
        <ChampDate
          label={T.debut}
          value={saisie.debut}
          max={saisie.fin || undefined}
          onChange={(debut) => modifier({ debut })}
          erreur={erreursApi.debut}
        />
        <ChampDate
          label={T.fin}
          value={saisie.fin}
          min={saisie.debut || undefined}
          onChange={(fin) => modifier({ fin })}
          erreur={erreurSaisie ?? erreursApi.fin}
        />
        <Selecteur
          label={T.regroupement}
          value={saisie.regroupement}
          onChange={(e) => modifier({ regroupement: e.target.value as Regroupement })}
          options={[
            { valeur: 'jour', libelle: T.jour },
            { valeur: 'semaine', libelle: T.semaine },
            { valeur: 'mois', libelle: T.mois },
          ]}
          erreur={erreursApi.regroupement}
        />
      </BarreFiltres>

      <div aria-busy={isFetching || undefined} className="grid gap-5 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.75fr)_minmax(0,1fr)]">
        <Carte titre={T.revenus} sousTitre={T.sousTitre} icone={<IconeRevenus width={16} height={16} />}>
          <CarteSopi
            libelle={T.revenus}
            montant={data ? formatMontant(data.totaux.revenus) : ''}
            lignes={[
              { libelle: T.acomptesEncaisses, valeur: data ? formatMontant(data.totaux.acomptesEncaisses) : '' },
              { libelle: T.soldePrestataires, valeur: data ? formatMontant(data.soldePrestataires) : '' },
            ]}
            note={data ? `${formatJourCalendaire(data.periode.debut, true)} – ${formatJourCalendaire(data.periode.fin, true)}` : undefined}
            {...etat}
          />
          {data && (
            <p className="mt-4 text-sm text-texte-doux">
              {T.soldePrestataires} : <strong className="text-texte">{formatMontant(data.soldePrestataires)}</strong>
            </p>
          )}
        </Carte>

        <div className="md:col-span-2 xl:col-span-1 [&>section]:h-full">
          <Bloc titre={T.courbe} {...etat} hauteur="h-72" vide={!isPending && !isError && series.length === 0} titreVisible={false}>
            {data && (
              <Carte titre={T.courbe} icone={<IconeGraphique width={16} height={16} />} className="h-full">
                <GraphiqueCourbe
                  description={T.graphiqueDescription}
                  libelles={series.map((p) => libelleAxe(p, regroupement))}
                  series={[
                    { nom: T.legendeAcomptes, couleur: 'var(--color-primaire-500)', valeurs: series.map((p) => p.acomptesEncaisses) },
                    { nom: T.legendeRevenus, couleur: 'var(--color-succes)', valeurs: series.map((p) => p.revenus) },
                  ]}
                  formatValeur={formatMontant}
                />
              </Carte>
            )}
          </Bloc>
        </div>

        <div className="md:col-span-2 xl:col-span-1 [&>section]:h-full">
          <Bloc titre={T.totaux} {...etat} hauteur="h-32" titreVisible={false}>
            {data && (
              <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                <CarteChiffre titre={T.revenus} valeur={<NombreAnime valeur={data.totaux.revenus} format={formatMontant} />} icone={<IconeRevenus />} />
                <CarteChiffre titre={T.acomptesEncaisses} valeur={<NombreAnime valeur={data.totaux.acomptesEncaisses} format={formatMontant} />} icone={<IconePaiement />} teinte="violet" delai={70} />
                <CarteChiffre titre={T.retraitsVerses} valeur={<NombreAnime valeur={data.totaux.retraitsVerses} format={formatMontant} />} icone={<IconeRetrait />} teinte="cyan" delai={140} />
                <CarteChiffre titre={T.remboursements} valeur={<NombreAnime valeur={data.totaux.remboursements} format={formatMontant} />} icone={<IconePaiement />} teinte="rose" delai={210} />
              </div>
            )}
          </Bloc>
        </div>

        <div className="md:col-span-2 xl:col-span-3">
        <Bloc titre={T.tableau} {...etat} hauteur="h-48" titreVisible={false} vide={!isPending && !isError && series.length === 0}>
          <Tableau
            legende={T.tableau}
            cleLigne={(p) => p.debut}
            lignes={seriePage}
            page={pageCourante}
            totalPages={totalPages}
            total={series.length}
            onPage={setPage}
            colonnes={[
              { cle: 'periode', titre: T.periode, rendu: (p) => libellePeriode(p, regroupement) },
              { cle: 'revenus', titre: T.legendeRevenus, alignement: 'droite', rendu: (p) => formatMontant(p.revenus) },
              { cle: 'acomptes', titre: T.acomptesEncaisses, alignement: 'droite', rendu: (p) => formatMontant(p.acomptesEncaisses) },
              { cle: 'remboursements', titre: T.remboursements, alignement: 'droite', rendu: (p) => formatMontant(p.remboursements) },
              { cle: 'retraits', titre: T.retraitsVerses, alignement: 'droite', rendu: (p) => formatMontant(p.retraitsVerses) },
            ]}
          />
        </Bloc>
        </div>
      </div>
    </div>
  )
}
