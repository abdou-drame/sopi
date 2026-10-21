import { useEffect, useId, useRef, useState } from 'react'
import type { FormEvent, KeyboardEvent, ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { L } from '../i18n/libelles'
import { BoutonAction } from './boutons'
import { ChampZone } from './champs'
import { usePresence } from './animation/usePresence'

interface Props {
  ouverte: boolean
  titre: string
  /** Ligne grise sous le titre (ex. nom du prestataire, référence du litige). */
  sousTitre?: string
  description?: ReactNode
  libelleConfirmer?: string
  /** Action irréversible : bouton de confirmation en rouge doux. */
  danger?: boolean
  /** Si vrai, un motif non vide est exigé avant de confirmer. */
  motifObligatoire?: boolean
  /** Motif proposé mais non exigé (s'il est saisi, il doit respecter les bornes de longueur). */
  motifFacultatif?: boolean
  /** Bornes de longueur du motif, vérifiées avant l'envoi (le serveur reste la référence). */
  motifMin?: number
  motifMax?: number
  libelleMotif?: string
  /** Champs supplémentaires, affichés avant le motif (ex. décision d'un litige, nom d'un métier). */
  champs?: ReactNode
  /** Bloque la confirmation tant que les champs supplémentaires sont incomplets. */
  confirmerDesactive?: boolean
  enCours?: boolean
  /** Message d'erreur renvoyé par le serveur (ex. 422 sur le motif). */
  erreur?: string
  /** Largeur de la fenêtre : 520 px (sanction, litige) ou 460 px (métier). */
  largeur?: 460 | 520
  onConfirmer: (motif: string) => void
  onAnnuler: () => void
}

const FOCALISABLES = 'button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [href]'

/**
 * Fenêtre des maquettes (NouvelleSanction, Trancher, MetierFormulaires) : fond marine à 50 %, boîte blanche de rayon 28.
 * Piège le focus, se ferme avec Échap, rend le focus à l'élément qui l'a ouverte, et exige un motif quand `motifObligatoire` est vrai.
 */
export default function Fenetre({
  ouverte,
  titre,
  sousTitre,
  description,
  libelleConfirmer = L.commun.confirmer,
  danger = false,
  motifObligatoire = false,
  motifFacultatif = false,
  motifMin,
  motifMax,
  libelleMotif = L.commun.motif,
  champs,
  confirmerDesactive = false,
  enCours = false,
  erreur,
  largeur = 520,
  onConfirmer,
  onAnnuler,
}: Props) {
  const idTitre = useId()
  const idDescription = useId()
  const [motif, setMotif] = useState('')
  const [tente, setTente] = useState(false)
  const boite = useRef<HTMLDivElement>(null)
  const { monte, sortie } = usePresence(ouverte)

  useEffect(() => {
    if (!ouverte) return
    const precedent = document.activeElement as HTMLElement | null
    const premier = boite.current?.querySelector<HTMLElement>('input, textarea, select, button')
    premier?.focus()
    return () => {
      precedent?.focus()
      setMotif('')
      setTente(false)
    }
  }, [ouverte])

  if (!monte) return null

  const afficherMotif = motifObligatoire || motifFacultatif
  const longueur = motif.trim().length
  const motifManquant = motifObligatoire && longueur === 0
  const motifTropCourt = motifMin !== undefined && longueur > 0 && longueur < motifMin
  const motifTropLong = motifMax !== undefined && longueur > motifMax
  const erreurMotif = motifManquant
    ? L.commun.motifObligatoire
    : motifTropCourt
      ? L.commun.motifTropCourt(motifMin!)
      : motifTropLong
        ? L.commun.motifTropLong(motifMax!)
        : null

  const surClavier = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.stopPropagation()
      if (!enCours) onAnnuler()
      return
    }
    if (e.key !== 'Tab' || !boite.current) return
    const elements = Array.from(boite.current.querySelectorAll<HTMLElement>(FOCALISABLES))
    if (elements.length === 0) return
    const premier = elements[0]
    const dernier = elements[elements.length - 1]
    if (e.shiftKey && document.activeElement === premier) {
      e.preventDefault()
      dernier.focus()
    } else if (!e.shiftKey && document.activeElement === dernier) {
      e.preventDefault()
      premier.focus()
    }
  }

  const soumettre = (e: FormEvent) => {
    e.preventDefault()
    setTente(true)
    if (erreurMotif || confirmerDesactive) return
    onConfirmer(motif.trim())
  }

  return createPortal(
    <div data-sortie={sortie || undefined} className="anim-fond fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[#12172b]/50 px-4 py-14 data-[sortie]:pointer-events-none sm:items-start" onKeyDown={surClavier}>
      <div
        ref={boite}
        role="dialog"
        aria-modal="true"
        aria-labelledby={idTitre}
        aria-describedby={description ? idDescription : undefined}
        style={{ maxWidth: largeur }}
        className="anim-modale box-border w-full rounded-[28px] bg-white p-6 shadow-[0_24px_60px_rgba(18,23,43,0.25)]"
      >
        <form onSubmit={soumettre} noValidate>
          <h2 id={idTitre} className="m-0 text-xl leading-[normal] font-bold">
            {titre}
          </h2>
          {sousTitre && <div className="mt-1 text-[13px] leading-[normal] text-[#8a90a2]">{sousTitre}</div>}
          {description && (
            <div id={idDescription} className="mt-3 text-[13px] leading-5 text-[#6b7287]">
              {description}
            </div>
          )}
          {(champs || afficherMotif) && (
            <div className="mt-[18px] flex flex-col gap-3.5">
              {champs}
              {afficherMotif && (
                <ChampZone
                  label={libelleMotif}
                  value={motif}
                  onChange={(e) => setMotif(e.target.value)}
                  erreur={(tente ? erreurMotif : null) ?? erreur}
                  aide={motifMin !== undefined && motifMax !== undefined ? L.commun.motifBornes(motifMin, motifMax) : undefined}
                  required={motifObligatoire}
                  placeholder=""
                />
              )}
            </div>
          )}
          {!afficherMotif && erreur && (
            <p role="alert" className="mt-3.5 text-[13px] leading-[normal] font-semibold text-[#b0161b]">
              {erreur}
            </p>
          )}
          <div className="mt-[22px] flex flex-wrap justify-end gap-2.5">
            <BoutonAction variante="gris" onClick={onAnnuler} disabled={enCours}>
              {L.commun.annuler}
            </BoutonAction>
            <BoutonAction type="submit" variante={danger ? 'rouge' : 'bleu'} enCours={enCours} disabled={confirmerDesactive}>
              {libelleConfirmer}
            </BoutonAction>
          </div>
        </form>
      </div>
    </div>,
    document.body,
  )
}
