import { useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { erreursParChamp, messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { PointRevenus, Regroupement } from '../api/types'
import { GraphiqueCourbe } from '../components/ui/Graphiques'
import { BoutonAction } from '../design/boutons'
import { ChampJour, ChampSelect } from '../design/champs'
import { Carte, FOND_MARQUE, EnteteCarte, PiedListe, TitrePage } from '../design/composants'
import { EtatChargement, EtatErreur, EtatVide } from '../design/etats'
import { IGraphique } from '../design/icones'
import { L } from '../i18n/libelles'
import { ajouterJours, aujourdhui, formatDateHeure, formatJourCalendaire, formatMontant, formatNombre, nombreDeJours } from '../lib/format'

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

/** Chiffre de la maquette Revenus : libellé 12/600, montant 30/34 avec « FCFA » en petit, légende 12. */
function Chiffre({ libelle, montant, legende, principal = false }: { libelle: string; montant: number; legende?: string; principal?: boolean }) {
  return (
    <section className={`box-border rounded-[28px] px-5 py-[18px] ${principal ? 'text-white' : 'bg-white'}`} style={principal ? { background: FOND_MARQUE } : undefined} aria-label={libelle}>
      <div className={`text-xs leading-[normal] font-semibold ${principal ? 'opacity-85' : 'text-[#8a90a2]'}`}>{libelle}</div>
      <div className="mt-2 text-[30px] leading-[34px] font-bold tracking-[-0.8px] tabular-nums">
        <span aria-label={formatMontant(montant)}>
          {formatNombre(montant)} <span className={`text-sm font-medium ${principal ? 'opacity-80' : 'text-[#9aa0b2]'}`}>FCFA</span>
        </span>
      </div>
      {legende && <div className={`mt-1.5 text-xs leading-[normal] ${principal ? 'opacity-80' : 'text-[#8a90a2]'}`}>{legende}</div>}
    </section>
  )
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
  const periode = data ? `${formatJourCalendaire(data.periode.debut, true)} – ${formatJourCalendaire(data.periode.fin, true)}` : undefined

  /** Chargement, erreur ou vide d'un bloc, au style des états des maquettes ; `null` quand les données sont là. */
  const etat = (vide = false): ReactNode =>
    isPending ? (
      <EtatChargement lignes={3} />
    ) : isError ? (
      <EtatErreur titre={L.commun.erreurChargement} texte={messageDErreur(error)} onReessayer={() => void refetch()} />
    ) : vide ? (
      <EtatVide texte={L.commun.aucuneDonnee} />
    ) : null

  return (
    <div className="flex flex-col gap-4">
      <TitrePage titre="Revenus" suite="de la plateforme" sousTitre={`${T.sousTitre}${data ? ` ${L.tableauDeBord.misAJour(formatDateHeure(data.dateGeneration))}.` : ''}`} />

      <Carte>
        <form
          aria-label={T.titre}
          className="flex flex-wrap items-start gap-3.5"
          onSubmit={(e) => {
            e.preventDefault()
            appliquer()
          }}
        >
          <div className="w-[200px] max-w-full">
            <ChampJour label={T.debut} value={saisie.debut} max={saisie.fin || undefined} onChange={(e) => modifier({ debut: e.target.value })} erreur={erreursApi.debut} />
          </div>
          <div className="w-[200px] max-w-full">
            <ChampJour label={T.fin} value={saisie.fin} min={saisie.debut || undefined} onChange={(e) => modifier({ fin: e.target.value })} erreur={erreurSaisie ?? erreursApi.fin} />
          </div>
          <div className="w-[200px] max-w-full">
            <ChampSelect
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
          </div>
          <div className="flex items-center gap-2.5 self-end">
            <BoutonAction type="submit">{T.appliquer}</BoutonAction>
            <BoutonAction variante="gris" onClick={reinitialiser}>
              {L.commun.reinitialiser}
            </BoutonAction>
          </div>
        </form>
      </Carte>

      <div aria-busy={isFetching || undefined} className="flex flex-col gap-4">
        <section aria-label={T.totaux}>
          {etat() ?? (
            data && (
              <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3.5">
                <Chiffre principal libelle={T.revenus} montant={data.totaux.revenus} legende={periode} />
                <Chiffre libelle={T.acomptesEncaisses} montant={data.totaux.acomptesEncaisses} legende={periode} />
                <Chiffre libelle={T.remboursements} montant={data.totaux.remboursements} legende={periode} />
                <Chiffre libelle={T.retraitsVerses} montant={data.totaux.retraitsVerses} legende={periode} />
                <Chiffre libelle={T.soldePrestataires} montant={data.soldePrestataires} />
              </div>
            )
          )}
        </section>

        <Carte aria-label={T.courbe}>
          <EnteteCarte icone={<IGraphique />} titre={T.courbe} />
          <div className="mt-3.5">
            {etat(series.length === 0) ??
              (data && (
                <GraphiqueCourbe
                  description={T.graphiqueDescription}
                  libelles={series.map((p) => libelleAxe(p, regroupement))}
                  series={[
                    { nom: T.legendeAcomptes, couleur: 'var(--color-marque)', valeurs: series.map((p) => p.acomptesEncaisses) },
                    { nom: T.legendeRevenus, couleur: '#16a34a', valeurs: series.map((p) => p.revenus) },
                  ]}
                  formatValeur={formatMontant}
                />
              ))}
          </div>
        </Carte>

        <Carte className="flex flex-col gap-4">
          <div>
            <div className="text-[15px] font-bold">{T.tableau}</div>
          </div>
          {etat(series.length === 0) ?? (
            <>
              <div className="overflow-x-auto">
                <table className="mq-table" style={{ minWidth: 760 }} aria-label={T.tableau}>
                  <thead>
                    <tr>
                      <th>{T.periode}</th>
                      <th>{T.acomptesEncaisses}</th>
                      <th>{T.legendeRevenus}</th>
                      <th>{T.remboursements}</th>
                      <th>{T.retraitsVerses}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {seriePage.map((p) => (
                      <tr key={p.debut}>
                        <td className="font-semibold">{libellePeriode(p, regroupement)}</td>
                        <td>{formatMontant(p.acomptesEncaisses)}</td>
                        <td>{formatMontant(p.revenus)}</td>
                        <td>{formatMontant(p.remboursements)}</td>
                        <td style={{ textAlign: 'right' }}>{formatMontant(p.retraitsVerses)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PiedListe
                resume={L.commun.resultats(series.length)}
                precedent={pageCourante > 1}
                suivant={pageCourante < totalPages}
                onPrecedent={() => setPage(pageCourante - 1)}
                onSuivant={() => setPage(pageCourante + 1)}
              />
            </>
          )}
        </Carte>
      </div>
    </div>
  )
}
