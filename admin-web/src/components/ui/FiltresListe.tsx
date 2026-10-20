import { useState } from 'react'
import { L } from '../../i18n/libelles'
import type { FiltresUrl } from '../../lib/useFiltresUrl'
import BarreFiltres from './BarreFiltres'
import { Champ, ChampDate, Selecteur } from './Champs'

export type DefinitionFiltre =
  | { cle: string; type: 'select'; label: string; options: { valeur: string; libelle: string }[]; /** Présente les choix en onglets segmentés. */ onglets?: boolean }
  | { cle: string; type: 'texte' | 'nombre'; label: string; placeholder?: string }
  | { cle: string; type: 'date'; label: string }

interface Props {
  legende: string
  filtres: DefinitionFiltre[]
  etat: FiltresUrl
}

/** Champ texte/nombre : s'applique à la touche Entrée ou en quittant le champ, pour ne pas interroger l'API à chaque frappe. */
function ChampTexte({ f, valeur, onValider }: { f: Extract<DefinitionFiltre, { type: 'texte' | 'nombre' }>; valeur: string; onValider: (v: string) => void }) {
  const [saisie, setSaisie] = useState(valeur)
  const [origine, setOrigine] = useState(valeur)
  // L'adresse change (retour arrière, réinitialisation) : la saisie la suit.
  if (origine !== valeur) {
    setOrigine(valeur)
    setSaisie(valeur)
  }
  const valider = () => {
    if (saisie.trim() !== valeur) onValider(saisie.trim())
  }
  return (
    <Champ
      label={f.label}
      type={f.type === 'nombre' ? 'number' : 'text'}
      min={f.type === 'nombre' ? 1 : undefined}
      placeholder={f.placeholder}
      value={saisie}
      onChange={(e) => setSaisie(e.target.value)}
      onBlur={valider}
      onKeyDown={(e) => {
        if (e.key === 'Enter') {
          e.preventDefault()
          valider()
        }
      }}
    />
  )
}

/** Filtre à choix restreint (4 au plus) présenté en onglets segmentés : mêmes valeurs que la liste déroulante. */
function Onglets({ label, valeur, options, onChoisir }: { label: string; valeur: string; options: { valeur: string; libelle: string }[]; onChoisir: (v: string) => void }) {
  return (
    <div role="tablist" aria-label={label} className="flex max-w-full overflow-x-auto rounded-gelule bg-fond p-1">
      {options.map((o) => {
        const actif = o.valeur === valeur
        return (
          <button
            key={o.valeur}
            type="button"
            role="tab"
            aria-selected={actif}
            onClick={() => onChoisir(o.valeur)}
            className={`h-9 shrink-0 rounded-gelule px-4 text-[13px] font-semibold whitespace-nowrap transition-colors ${actif ? 'bg-primaire-600 text-white' : 'text-texte-doux hover:text-texte'}`}
          >
            {o.libelle}
          </button>
        )
      })}
    </div>
  )
}

/**
 * Barre de filtres d'une liste, synchronisée avec l'adresse. Listes déroulantes et dates s'appliquent
 * immédiatement ; les champs texte à Entrée ou à la sortie du champ.
 */
export default function FiltresListe({ legende, filtres, etat }: Props) {
  return (
    <BarreFiltres legende={legende} onReinitialiser={etat.modifies ? etat.reinitialiser : undefined}>
      {filtres.map((f) => {
        const valeur = etat.valeurs[f.cle] ?? ''
        if (f.type === 'select' && f.onglets)
          return <Onglets key={f.cle} label={f.label} valeur={valeur} options={f.options} onChoisir={(v) => etat.definir({ [f.cle]: v })} />
        if (f.type === 'select')
          return (
            <Selecteur
              key={f.cle}
              label={f.label}
              value={valeur}
              options={f.options}
              onChange={(e) => etat.definir({ [f.cle]: e.target.value })}
            />
          )
        if (f.type === 'date')
          return <ChampDate key={f.cle} label={f.label} value={valeur} onChange={(jour) => etat.definir({ [f.cle]: jour })} />
        return <ChampTexte key={f.cle} f={f} valeur={valeur} onValider={(v) => etat.definir({ [f.cle]: v })} />
      })}
    </BarreFiltres>
  )
}

export const OPTION_TOUS = { valeur: '', libelle: L.commun.tous }
