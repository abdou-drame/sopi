import { useEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { L } from '../i18n/libelles'
import type { FiltresUrl } from '../lib/useFiltresUrl'
import { BoutonFiltre, Recherche } from './composants'

export type DefinitionFiltre =
  | { cle: string; type: 'select'; label: string; options: { valeur: string; libelle: string }[] }
  | { cle: string; type: 'recherche'; label: string; placeholder?: string }
  | { cle: string; type: 'texte' | 'nombre'; label: string; placeholder?: string }

/** Liste déroulante d'un filtre : bouton de 42 px (BoutonFiltre) qui ouvre un menu de choix, comme le menu « Plus ». */
function FiltreMenu({ label, valeur, options, onChoisir }: { label: string; valeur: string; options: { valeur: string; libelle: string }[]; onChoisir: (v: string) => void }) {
  const [ouvert, setOuvert] = useState(false)
  const racine = useRef<HTMLDivElement>(null)
  const choisi = options.find((o) => o.valeur === valeur && o.valeur !== '')
  useEffect(() => {
    if (!ouvert) return
    const dehors = (e: MouseEvent) => !racine.current?.contains(e.target as Node) && setOuvert(false)
    document.addEventListener('mousedown', dehors)
    return () => document.removeEventListener('mousedown', dehors)
  }, [ouvert])
  return (
    <div
      ref={racine}
      className="relative"
      onKeyDown={(e) => {
        if (e.key === 'Escape' && ouvert) {
          e.stopPropagation()
          setOuvert(false)
          racine.current?.querySelector('button')?.focus()
        }
      }}
    >
      <BoutonFiltre aria-haspopup="menu" aria-expanded={ouvert} aria-label={label} onClick={() => setOuvert((o) => !o)}>
        {choisi ? `${label} : ${choisi.libelle}` : label}
      </BoutonFiltre>
      {ouvert && (
        <div role="menu" aria-label={label} className="absolute top-[50px] right-0 z-20 box-border min-w-[220px] rounded-[26px] bg-white p-2 shadow-[0_18px_44px_rgba(18,23,43,0.18)]">
          {options.map((o) => {
            const actif = o.valeur === valeur
            return (
              <button
                key={o.valeur}
                type="button"
                role="menuitemradio"
                aria-checked={actif}
                onClick={() => {
                  onChoisir(o.valeur)
                  setOuvert(false)
                }}
                className={`mq-rond flex h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-[22px] border-none px-3.5 text-left text-[13px] font-semibold whitespace-nowrap text-[#12172b] ${actif ? 'bg-[#f3f4f7] font-bold' : 'bg-transparent'}`}
              >
                {o.libelle}
                {actif && <span aria-hidden="true" className="size-[7px] rounded-full bg-[#1d4ed8]" />}
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}

/** Champ texte ou nombre d'un filtre : s'applique à Entrée ou en quittant le champ, pour ne pas interroger l'API à chaque frappe. */
function FiltreChamp({ f, valeur, onValider }: { f: Extract<DefinitionFiltre, { type: 'texte' | 'nombre' }>; valeur: string; onValider: (v: string) => void }) {
  const [saisie, setSaisie] = useState(valeur)
  const [origine, setOrigine] = useState(valeur)
  // L'adresse change (retour arrière, réinitialisation) : la saisie la suit.
  if (origine !== valeur) {
    setOrigine(valeur)
    setSaisie(valeur)
  }
  const valider = () => saisie.trim() !== valeur && onValider(saisie.trim())
  return (
    <input
      type={f.type === 'nombre' ? 'number' : 'text'}
      min={f.type === 'nombre' ? 1 : undefined}
      value={saisie}
      placeholder={f.placeholder ?? f.label}
      aria-label={f.label}
      onChange={(e) => setSaisie(e.target.value)}
      onBlur={valider}
      onKeyDown={(e) => {
        if (e.key === 'Enter') {
          e.preventDefault()
          valider()
        }
      }}
      className="mq-champ box-border h-[42px] w-[170px] rounded-[21px] border-none bg-[#f3f4f7] px-4 text-[13px] text-[#12172b] placeholder:text-[#9aa0b2]"
    />
  )
}

/**
 * Rangée de filtres des listes (maquettes v3), synchronisée avec l'adresse : recherche à gauche, listes déroulantes à droite.
 * Les listes s'appliquent tout de suite ; les champs texte à Entrée ou à la sortie du champ.
 * `avant` accueille des onglets : la recherche passe alors à droite, comme sur Retraits.
 */
export default function Filtres({ filtres, etat, avant }: { filtres: DefinitionFiltre[]; etat: FiltresUrl; avant?: ReactNode }) {
  const recherche = filtres.find((f) => f.type === 'recherche')
  const autres = filtres.filter((f) => f !== recherche)
  const champRecherche = recherche && (
    <Recherche
      key={recherche.cle}
      valeur={etat.valeurs[recherche.cle] ?? ''}
      onChange={(v) => etat.definir({ [recherche.cle]: v })}
      placeholder={(recherche as { placeholder?: string }).placeholder ?? recherche.label}
      libelle={recherche.label}
    />
  )
  return (
    <div className="flex flex-wrap items-center gap-3">
      {avant}
      {!avant && champRecherche}
      <div className="flex-1" />
      {avant && champRecherche}
      {autres.map((f) => {
        const valeur = etat.valeurs[f.cle] ?? ''
        return f.type === 'select' ? (
          <FiltreMenu key={f.cle} label={f.label} valeur={valeur} options={f.options} onChoisir={(v) => etat.definir({ [f.cle]: v })} />
        ) : f.type === 'recherche' ? null : (
          <FiltreChamp key={f.cle} f={f} valeur={valeur} onValider={(v) => etat.definir({ [f.cle]: v })} />
        )
      })}
      {etat.modifies && (
        <button type="button" onClick={etat.reinitialiser} className="mq-btn-gris h-[42px] cursor-pointer rounded-[21px] border-none px-4 text-[13px] font-semibold">
          {L.commun.reinitialiser}
        </button>
      )}
    </div>
  )
}
