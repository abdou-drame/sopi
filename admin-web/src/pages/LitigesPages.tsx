import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { messageDErreur } from '../api/client'
import { api } from '../api/endpoints'
import type { CibleSanction, DecisionLitige, Litige, LitigeDetail } from '../api/typesAdmin'
import { useAction } from '../api/useAction'
import { useRetours } from '../components/ui/Alertes'
import FichierProtege, { cheminApi } from '../components/ui/FichierProtege'
import { ChampJour, ChampSelect } from '../design/champs'
import Fenetre from '../design/Fenetre'
import { Avatar, Badge, Carte as CarteMq, FOND_MARQUE, EnteteCarte, EtapeChrono, LienRond, LigneLien, Onglets, PaginationRonde, TitreCarte, TitreDetail, TitrePage } from '../design/composants'
import { IHorloge, IImage, IMessage, IRecherche } from '../design/icones'
import { CarteBleue, CarteStat, ICoche, IFiltre, Pastille, sansAccent } from '../design/synthese'
import PageDetailMq from '../design/PageDetailMq'
import { CartesSquelette } from '../design/etats'
import TableauDonnees from '../design/tableau'
import { EtatsListe, useListe } from '../design/useListe'
import { L, dureeMinutes } from '../i18n/libelles'
import { aujourdhui, formatDate, formatHeureDuJour, formatMontant, formatNombre } from '../lib/format'
import { useFiltresUrl } from '../lib/useFiltresUrl'

const T = L.litiges
const MOTIF_MIN = 10
const MOTIF_MAX = 1000

const OPTIONS_STATUT = [
  { valeur: 'ouvert', libelle: T.ouverts },
  { valeur: 'cloture', libelle: T.clotures },
  { valeur: 'tous', libelle: T.tous },
]

const nomComplet = (p: { prenom: string; nom: string }) => `${p.prenom} ${p.nom}`


/** « Clôturé · rejet » quand la clôture est un rejet de la plainte, « Clôturé » pour toute autre décision. */
const libelleStatut = (statut: string, decision: DecisionLitige | null) => (statut === 'cloture' && decision === 'rejet' ? T.clotureRejet : (L.statuts[statut] ?? statut))

const PASTILLE_DECISION: Record<string, { fond: string; point: string }> = {
  ouvert: { fond: 'bg-[#fff0d4] text-[#983705]', point: '#f59e0b' },
  rejet: { fond: 'bg-[#eef1f6] text-[#4f566b]', point: '#6b7287' },
  avertissement: { fond: 'bg-[#fff0d4] text-[#983705]', point: '#f59e0b' },
  suspension_temporaire: { fond: 'bg-[#ffe6d5] text-[#9a3412]', point: '#f97316' },
  bannissement_definitif: { fond: 'bg-[#fde9e9] text-[#b0161b]', point: '#e5484d' },
  rembourse: { fond: 'bg-neutre-claire text-neutre', point: 'var(--color-neutre)' },
}

/** Code d'affichage de la décision : « remboursé » si un paiement du litige l'est, « ouvert » tant qu'aucune décision n'est prise. */
function codeDecision(l: Litige): string {
  if (l.decision === null) return l.statut === 'ouvert' ? 'ouvert' : 'rejet'
  if (l.paiements.some((p) => p.statut === 'rembourse')) return 'rembourse'
  return l.decision
}
const libelleDecision = (code: string) => (code === 'ouvert' ? 'En cours' : code === 'rembourse' ? 'Remboursé' : (T.decisionsCourt[code] ?? code))

/** Un acompte est bloqué tant que le paiement est encaissé et pas remboursé. */
const acompteBloque = (l: Litige) => l.paiements.filter((p) => !['rembourse', 'echoue', 'en_attente'].includes(p.statut)).reduce((somme, p) => somme + p.montantAcompte, 0)

const ICO = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true } as const
const IBalance = () => (
  <svg {...ICO}>
    <path d="M12 4v16M6 20h12M5 7h14M5 7l-3 7a3 3 0 006 0L5 7zM19 7l-3 7a3 3 0 006 0l-3-7z" />
  </svg>
)
const IPortefeuille = () => (
  <svg {...ICO}>
    <rect x="4" y="6" width="16" height="13" rx="3" />
    <path d="M4 10h16M15 14.5h2" />
  </svg>
)
const IPourcent = () => (
  <svg {...ICO}>
    <path d="M19 5L5 19M7.5 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
  </svg>
)

export function LitigesListePage() {
  const etat = useFiltresUrl(['statut'], { statut: 'ouvert' })
  const liste = useListe('litiges', api.litiges, etat)
  const { data: tableau } = useQuery({ queryKey: ['tableau-de-bord'], queryFn: api.tableauDeBord, retry: false })
  // L'API n'expose pas le total des acomptes bloqués : on somme les litiges ouverts de la première page (20 au plus).
  const { data: ouverts } = useQuery({ queryKey: ['litiges-ouverts-acomptes'], queryFn: () => api.litiges({ statut: 'ouvert' }), retry: false })
  const [recherche, setRecherche] = useState('')
  const [decision, setDecision] = useState('')

  const nbOuverts = tableau?.litiges.ouverts
  const nbClos = tableau?.litiges.clos
  const nbLitiges = tableau?.litiges.total
  const nbReservations = tableau?.reservations.total
  const compteurs: Record<string, number | undefined> = { ouvert: nbOuverts, cloture: nbClos, tous: nbLitiges }
  const pct = (n: number | undefined, d: number | undefined) => (n === undefined || !d ? undefined : Math.round((n / d) * 100))
  const partClos = pct(nbClos, nbLitiges)
  const taux = pct(nbLitiges, nbReservations)
  const acomptes = ouverts ? ouverts.data.reduce((somme, l) => somme + acompteBloque(l), 0) : undefined
  const acomptesPartiels = ouverts !== undefined && ouverts.meta.total > ouverts.data.length
  const infosTaux = nbLitiges === undefined || nbReservations === undefined ? undefined : { nbLitiges, nbReservations }

  const lignes = liste.lignes.filter(
    (l) => (!decision || codeDecision(l) === decision) && sansAccent(`${nomComplet(l.client)} ${nomComplet(l.prestataire)} ${l.prestataire.nomEntreprise ?? ''}`).includes(sansAccent(recherche.trim())),
  )
  const filtre = recherche.trim() !== '' || decision !== ''

  return (
    <div className="flex flex-col gap-3">
      <TitrePage compact titre="Litiges" suite="à trancher" sousTitre="Examinez chaque litige, puis tranchez : rejet, avertissement, suspension ou bannissement." />

      {liste.chargement && <CartesSquelette n={4} />}
      <div hidden={liste.chargement} className="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))]">
        <CarteBleue
          icone={<IBalance />}
          titre="Litiges ouverts"
          valeur={nbOuverts}
          unite="à trancher"
          aJour={nbOuverts === 0}
          note={nbOuverts === 0 ? 'Aucune décision en attente' : `${nbOuverts ?? '—'} décision${nbOuverts === 1 ? '' : 's'} en attente`}
        />
        <CarteStat
          icone={<ICoche />}
          teinte="bg-[#d7f7e4] text-[#16a34a]"
          badge={partClos === undefined ? '—' : `${partClos} %`}
          badgeTeinte="bg-[#d7f7e4] text-[#0e6245]"
          titre="Clôturés"
          valeur={nbClos}
          pourcentage={partClos ?? 0}
          couleurBarre="#16a34a"
        />
        <CarteStat
          icone={<IPortefeuille />}
          teinte="bg-[#fff0d4] text-[#d97706]"
          badge="Gelés"
          badgeTeinte="bg-[#eef1f6] text-[#4f566b]"
          titre="Acomptes bloqués"
          valeur={acomptes}
          formater={formatNombre}
          note={acomptes === undefined ? undefined : acomptesPartiels ? `FCFA · sur les ${ouverts?.data.length} premiers litiges` : acomptes === 0 ? 'Aucun acompte en litige' : 'FCFA'}
        />
        <CarteStat
          icone={<IPourcent />}
          teinte="bg-marque-claire text-marque"
          badge={infosTaux ? `${infosTaux.nbLitiges} / ${infosTaux.nbReservations}` : '—'}
          badgeTeinte="bg-[#fff0d4] text-[#983705]"
          titre="Taux de litige"
          valeur={taux}
          note={infosTaux ? `% · ${infosTaux.nbLitiges} litige${infosTaux.nbLitiges > 1 ? 's' : ''} pour ${infosTaux.nbReservations} réservation${infosTaux.nbReservations > 1 ? 's' : ''}` : undefined}
        />
      </div>

      <CarteMq className="flex flex-col gap-2 !p-3 !rounded-[24px]">
        <div className="flex flex-wrap items-center gap-3">
          <Onglets
            pastille
            libelle={T.statut}
            onglets={OPTIONS_STATUT.map((o) => ({
              cle: o.valeur,
              libelle: o.libelle,
              compteur: compteurs[o.valeur] ?? '—',
              actif: etat.valeurs.statut === o.valeur,
              onSelect: () => etat.definir({ statut: o.valeur }),
            }))}
          />
          <div className="ml-auto flex flex-wrap items-center gap-2.5">
            <label className="flex h-8 w-[260px] max-w-full items-center gap-2.5 rounded-[18px] border border-[#e6e8ee] bg-[#f6f7fa] px-4 text-[#9aa0b2] focus-within:border-marque">
              <IRecherche taille={16} />
              <input
                type="search"
                value={recherche}
                onChange={(e) => setRecherche(e.target.value)}
                placeholder="Client ou prestataire…"
                aria-label="Rechercher un client ou un prestataire"
                className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-[#12172b] outline-none placeholder:text-[#9aa0b2]"
              />
            </label>
            <label className="relative flex h-8 items-center gap-2 rounded-[18px] border border-[#e6e8ee] bg-white pr-4 pl-4 text-[13px] font-semibold text-[#12172b]">
              <IFiltre />
              <select value={decision} onChange={(e) => setDecision(e.target.value)} aria-label="Filtrer par décision" className="cursor-pointer appearance-none border-none bg-transparent pr-1 text-[13px] font-semibold outline-none">
                <option value="">{T.decisionChamp}</option>
                {Object.keys(PASTILLE_DECISION).map((code) => (
                  <option key={code} value={code}>
                    {libelleDecision(code)}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <TableauDonnees
          variante="compact"
          legende={T.legende}
          lignes={lignes}
          largeurMin={860}
          actionsColonne="Action"
          libelleActions="Actions"
          masque={liste.chargement}
          colonnes={[{ titre: 'Litige', tri: (l) => l.reservation.prestation }, { titre: T.client, tri: (l) => nomComplet(l.client) }, { titre: T.prestataire, tri: (l) => l.prestataire.nomEntreprise ?? nomComplet(l.prestataire) }, { titre: T.acompte, tri: (l) => (l.paiements.length === 0 ? null : l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0)) }, { titre: T.ouvertLe, tri: (l) => l.dateCreation }, { titre: T.decisionChamp, tri: (l) => libelleDecision(codeDecision(l)) }]}
        >
              {(triees) => triees.map((l) => {
                const acompte = l.paiements.length === 0 ? null : l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0)
                const code = codeDecision(l)
                const style = PASTILLE_DECISION[code] ?? PASTILLE_DECISION.rejet
                return (
                  <LigneLien key={l.id} vers={`/litiges/${l.id}`} libelle={`Ouvrir le litige ${l.motif}`} texte="Voir" discret>
                    <td>
                      <span className="block font-semibold text-[#12172b]">{l.reservation.prestation}</span>
                      <span className="mt-0.5 block text-xs font-normal text-[#9aa0b2]">{l.motif}</span>
                    </td>
                    <td>
                      <div className="flex items-center gap-2.5">
                        <Avatar nom={nomComplet(l.client)} taille={30} />
                        <span className="font-semibold text-[#12172b]">{nomComplet(l.client)}</span>
                      </div>
                    </td>
                    <td className="text-[#6b7287]">{l.prestataire.nomEntreprise ?? nomComplet(l.prestataire)}</td>
                    <td>
                      <span className="font-semibold text-[#12172b] tabular-nums">{acompte === null ? '—' : formatMontant(acompte)}</span>
                    </td>
                    <td className="text-[#6b7287]">{formatDate(l.dateCreation)}</td>
                    <td>
                      <Pastille fond={style.fond} point={style.point} libelle={libelleDecision(code)} />
                    </td>
                  </LigneLien>
                )
              })}
        </TableauDonnees>
        <EtatsListe
          chargement={liste.chargement}
          erreur={liste.erreur}
          vide={liste.donneesPresentes && lignes.length === 0}
          messageVide={T.vide}
          onReessayer={liste.recharger}
          colonnes={[{ titre: 'Litige' }, { titre: T.client }, { titre: T.prestataire }, { titre: T.acompte }, { titre: T.ouvertLe }, { titre: T.decisionChamp }]}
          largeurMin={860}
          compact
          filtresActifs={filtre || etat.modifies}
          messageFiltres={filtre && liste.lignes.length > 0 ? 'Aucun litige de cette page ne correspond à la recherche.' : undefined}
          onReinitialiser={() => {
            setRecherche('')
            setDecision('')
            etat.reinitialiser()
          }}
        />
        <PaginationRonde
          resume={
            <>
              Affichage de <strong className="font-semibold text-[#12172b]">{liste.premier}–{liste.dernier}</strong> sur {liste.total} {liste.total > 1 ? 'litiges' : 'litige'}
            </>
          }
          page={liste.page}
          totalPages={liste.totalPages}
          onPage={etat.allerPage}
        />
      </CarteMq>
    </div>
  )
}

function ActionTrancher({ litige, onFait }: { litige: LitigeDetail; onFait: (message: string) => void }) {
  const [ouverte, setOuverte] = useState(false)
  const [decision, setDecision] = useState<DecisionLitige | ''>('')
  const [cible, setCible] = useState<CibleSanction | ''>('')
  const [dateFin, setDateFin] = useState('')
  const trancher = useAction((corps: Parameters<typeof api.trancherLitige>[1]) => api.trancherLitige(litige.id, corps))

  const suspension = decision === 'suspension_temporaire'
  const sanction = decision !== '' && decision !== 'rejet'

  const fermer = () => {
    setOuverte(false)
    setDecision('')
    setCible('')
    setDateFin('')
    trancher.reset()
  }

  const champs = (
    <>
      <ChampSelect
        label={T.decisionChamp}
        value={decision}
        onChange={(e) => setDecision(e.target.value as DecisionLitige | '')}
        options={[{ valeur: '', libelle: T.choisir }, ...Object.entries(T.decisions).map(([valeur, libelle]) => ({ valeur, libelle }))]}
      />
      {sanction && (
        <ChampSelect
          label={T.cible}
          value={cible}
          onChange={(e) => setCible(e.target.value as CibleSanction | '')}
          options={[
            { valeur: '', libelle: T.cibleDefaut },
            { valeur: 'prestataire', libelle: T.ciblePrestataire },
            { valeur: 'client', libelle: T.cibleClient },
          ]}
        />
      )}
      {suspension && <ChampJour label={T.dateFin} value={dateFin} onChange={(e) => setDateFin(e.target.value)} min={aujourdhui()} />}
    </>
  )

  return (
    <>
      <button
        type="button"
        onClick={() => setOuverte(true)}
        className="mq-btn-bleu h-12 cursor-pointer rounded-3xl border-none bg-marque px-6 text-[13px] font-bold text-white shadow-[0_8px_18px_rgba(45,106,79,0.30)]"
      >
        {T.trancher}
      </button>
      <Fenetre
        ouverte={ouverte}
        titre={T.trancherTitre}
        description={T.trancherTexte}
        libelleConfirmer={T.trancher}
        libelleMotif={T.motifDecisionLibelle}
        danger={sanction}
        champs={champs}
        confirmerDesactive={decision === '' || (suspension && dateFin === '')}
        motifObligatoire
        motifMin={MOTIF_MIN}
        motifMax={MOTIF_MAX}
        enCours={trancher.isPending}
        erreur={trancher.isError ? messageDErreur(trancher.error) : undefined}
        onAnnuler={fermer}
        onConfirmer={(motif) => {
          if (decision === '') return
          trancher.mutate(
            {
              decision,
              motif,
              ...(sanction && cible ? { cible } : {}),
              // Dakar est à UTC+0 : la suspension court jusqu'à la fin du jour choisi.
              ...(suspension ? { dateFin: `${dateFin}T23:59:59+00:00` } : {}),
            },
            {
              onSuccess: () => {
                fermer()
                onFait(T.tranche)
              },
            },
          )
        }}
      />
    </>
  )
}

const ABSENT = '—'
const heureDe = (iso: string) => formatHeureDuJour(iso)

/** Ligne « libellé / valeur » du bloc Réservation : une ligne sur deux grisée. */
function LigneInfo({ libelle, children, grise }: { libelle: string; children: ReactNode; grise: boolean }) {
  return (
    <div className={`flex justify-between gap-3 rounded-2xl px-4 py-3 ${grise ? 'bg-[#f3f4f7]' : ''}`}>
      <span className="font-medium text-[#6b7287]">{libelle}</span>
      <span className="text-right font-semibold">{children}</span>
    </div>
  )
}

function Personne({ nom, role, couleur, lien, libelleLien }: { nom: string; role: string; couleur: string; lien: string; libelleLien: string }) {
  const mots = nom.trim().split(/\s+/)
  const initiales = ((mots[0]?.[0] ?? '') + (mots.length > 1 ? (mots[mots.length - 1][0] ?? '') : (mots[0]?.[1] ?? ''))).toUpperCase()
  return (
    <div className="flex items-center gap-3 rounded-[22px] bg-[#f3f4f7] px-3.5 py-3">
      <span aria-hidden="true" className={`grid size-11 shrink-0 place-items-center rounded-full text-[13px] font-bold ${couleur}`}>
        {initiales}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-semibold">{nom}</span>
        <span className="mt-px block text-xs text-[#8a90a2]">{role}</span>
      </span>
      <LienRond vers={lien} libelle={libelleLien} blanc />
    </div>
  )
}

export function LitigeDetailPage() {
  const { id = '' } = useParams()
  const requete = useQuery({ queryKey: ['litige', id], queryFn: () => api.litige(id) })
  const retour = useRetours()
  const [maintenant] = useState(() => Date.now())
  return (
    <PageDetailMq requete={requete} retour="/litiges" libelleRetour="Retour aux litiges">
      {(l) => {
        const paiement = l.paiements[0]
        const acompte = l.paiements.length === 0 ? null : l.paiements.reduce((somme, p) => somme + p.montantAcompte, 0)
        const depuis = Math.max(0, (maintenant - new Date(l.dateCreation).getTime()) / 60000)
        const ouvert = l.statut === 'ouvert'
        const reponse = l.reponsePrestataire
          ? `Réponse du prestataire${l.reponseLe ? ` le ${formatDate(l.reponseLe)}` : ''} : « ${l.reponsePrestataire} »`
          : 'Aucune réponse écrite du prestataire pour le moment'
        const cloture =
          [
            l.clotureLe ? formatDate(l.clotureLe) : null,
            l.decision ? (T.decisionsCourt[l.decision] ?? l.decision) : null,
            l.motifDecision ? `« ${l.motifDecision} »` : null,
          ]
            .filter(Boolean)
            .join(' · ') || ABSENT
        const nomPrestataire = l.prestataire.nomEntreprise ?? nomComplet(l.prestataire)
        return (
          <>
            <TitreDetail
              retour="/litiges"
              libelleRetour="Retour aux litiges"
              titre="Litige"
              suite={ABSENT}
              badge={
                <Badge ton={ouvert ? 'danger' : l.decision === 'rejet' ? 'neutre' : 'succes'} gras>
                  {libelleStatut(l.statut, l.decision)}
                </Badge>
              }
              infos={`Ouvert le ${formatDate(l.dateCreation)} à ${heureDe(l.dateCreation)}${ouvert ? ` · sans décision depuis ${dureeMinutes(depuis)}` : ''}`}
            >
              {ouvert && <ActionTrancher litige={l} onFait={retour.succes} />}
            </TitreDetail>
            <div className="flex flex-wrap items-start gap-3.5">
              <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-3.5">
                <CarteMq>
                  <EnteteCarte icone={<IMessage />} titre="Motif du client" sousTitre={l.motif} />
                  <p className="mt-[18px] mb-0 rounded-[22px] bg-[#f3f4f7] px-5 py-[18px] text-sm leading-[22px] whitespace-pre-line text-[#3c4257]">« {l.description} »</p>
                </CarteMq>
                <CarteMq>
                  <EnteteCarte icone={<IHorloge />} titre="Chronologie" sousTitre="Du début de la réservation à aujourd'hui" />
                  <div className="mt-5 flex flex-col">
                    <EtapeChrono grand couleur="var(--color-neutre)" titre="Réservation confirmée" detail={paiement?.payeLe ? `${formatDate(paiement.payeLe)} · acompte payé` : ABSENT} />
                    <EtapeChrono grand couleur="var(--color-neutre)" titre="Prestation terminée par le prestataire" detail={ABSENT} />
                    <EtapeChrono grand couleur="#e5484d" titre="Litige ouvert par le client" detail={`${formatDate(l.dateCreation)} · ${heureDe(l.dateCreation)}`} />
                    {ouvert ? (
                      <EtapeChrono
                        grand
                        derniere
                        couleur="#CBD5E1"
                        titre="En attente d'une décision"
                        detail={reponse}
                      />
                    ) : (
                      <EtapeChrono grand derniere couleur="var(--color-neutre)" titre="Litige clôturé" detail={cloture} />
                    )}
                  </div>
                </CarteMq>
                <CarteMq>
                  <EnteteCarte icone={<IImage taille={18} trait={1.9} />} titre="Preuves jointes" sousTitre="Envoyées par le client" />
                  <div className="mt-[18px] grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                    {l.preuves.length > 0
                      ? l.preuves.map((preuve, i) => (
                          <div key={preuve.id} className="flex h-[150px] items-center justify-center overflow-hidden rounded-[22px] bg-marque-claire">
                            <FichierProtege chemin={cheminApi(preuve.url)} alt={`Preuve ${i + 1} du client`} className="h-[150px] w-full rounded-[22px]!" />
                          </div>
                        ))
                      : [1, 2].map((n) => (
                          <div key={n} className="flex h-[150px] flex-col items-center justify-center gap-2 rounded-[22px] bg-marque-claire text-xs font-semibold text-[#5f8f7a]">
                            <IImage taille={24} />
                            Photo {n} · {ABSENT}
                          </div>
                        ))}
                  </div>
                </CarteMq>
              </div>
              <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-3.5">
                <CarteMq>
                  <TitreCarte titre="Acompte concerné" sousTitre="Retenu en attendant la décision" />
                  <div className="mt-[18px] box-border rounded-[22px] p-5 text-white" style={{ background: FOND_MARQUE }}>
                    <div className="text-xs font-semibold opacity-80">Montant de l'acompte</div>
                    <div className="mt-1.5 flex items-baseline gap-2">
                      <span className="text-[38px] leading-[44px] font-bold tracking-[-1px] tabular-nums">{acompte === null ? ABSENT : formatNombre(acompte)}</span>
                      <span className="text-sm font-semibold opacity-85">FCFA</span>
                    </div>
                    <div className="mt-[18px] flex items-center justify-between text-xs">
                      <span className="opacity-80">Réservation {ABSENT}</span>
                      <span className="rounded-full bg-white/20 px-2.5 py-1 font-bold">{paiement ? (L.statuts[paiement.statut] ?? paiement.statut) : ABSENT}</span>
                    </div>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Réservation" />
                  <div className="mt-3.5 flex flex-col gap-1 text-[13px]">
                    <LigneInfo libelle="Service" grise>
                      {l.reservation.prestation}
                    </LigneInfo>
                    <LigneInfo libelle="Métier" grise={false}>
                      {l.prestataire.metier ? (L.metiers[l.prestataire.metier] ?? l.prestataire.metier) : ABSENT}
                    </LigneInfo>
                    <LigneInfo libelle="Date prévue" grise>{`${formatDate(l.reservation.dateDebut)} · ${heureDe(l.reservation.dateDebut)}`}</LigneInfo>
                    <LigneInfo libelle="Quartier" grise={false}>
                      {l.prestataire.quartier ?? ABSENT}
                    </LigneInfo>
                  </div>
                </CarteMq>
                <CarteMq>
                  <TitreCarte titre="Personnes concernées" />
                  <div className="mt-3.5 flex flex-col gap-2.5">
                    <Personne nom={nomComplet(l.client)} role="Client" couleur="bg-neutre-claire text-neutre" lien={`/utilisateurs/${l.client.id}`} libelleLien="Voir le profil du client" />
                    <Personne nom={nomPrestataire} role={`Prestataire · note ${l.prestataire.noteMoyenne == null ? ABSENT : `${String(l.prestataire.noteMoyenne).replace('.', ',')} / 5`}`} couleur="bg-[#fff0d4] text-[#983705]" lien={`/utilisateurs/${l.prestataire.id}`} libelleLien="Voir le profil du prestataire" />
                  </div>
                </CarteMq>
              </div>
            </div>
          </>
        )
      }}
    </PageDetailMq>
  )
}
