import { useEffect, useId, useRef, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { L } from '../../i18n/libelles'
import Bouton from './Bouton'
import { ZoneTexte } from './Champs'

interface Props {
  ouverte: boolean
  titre: string
  description?: ReactNode
  libelleConfirmer?: string
  /** Action irréversible : bouton de confirmation en rouge. */
  danger?: boolean
  /** Si vrai, un motif non vide est exigé avant de confirmer. */
  motifObligatoire?: boolean
  /** Motif proposé mais non exigé (s'il est saisi, il doit respecter les bornes de longueur). */
  motifFacultatif?: boolean
  /** Bornes de longueur du motif, vérifiées avant l'envoi (le serveur reste la référence). */
  motifMin?: number
  motifMax?: number
  libelleMotif?: string
  /** Champs supplémentaires, affichés avant le motif (ex. décision d'un litige). */
  champs?: ReactNode
  /** Bloque la confirmation tant que les champs supplémentaires sont incomplets. */
  confirmerDesactive?: boolean
  enCours?: boolean
  /** Message d'erreur renvoyé par le serveur (ex. 422 sur le motif). */
  erreur?: string
  onConfirmer: (motif: string) => void
  onAnnuler: () => void
}

const FOCALISABLES = 'button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [href]'

/**
 * Modale de confirmation explicite pour les actions irréversibles. Piège le focus, se ferme avec Échap,
 * rend le focus à l'élément qui l'a ouverte, et exige un motif quand `motifObligatoire` est vrai.
 */
export default function ModaleConfirmation({
  ouverte,
  titre,
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
  onConfirmer,
  onAnnuler,
}: Props) {
  const idTitre = useId()
  const idDescription = useId()
  const [motif, setMotif] = useState('')
  const [tente, setTente] = useState(false)
  const boite = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!ouverte) return
    const precedent = document.activeElement as HTMLElement | null
    const premier = boite.current?.querySelector<HTMLElement>('textarea, button')
    premier?.focus()
    return () => {
      precedent?.focus()
      setMotif('')
      setTente(false)
    }
  }, [ouverte])

  if (!ouverte) return null

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

  const surClavier = (e: React.KeyboardEvent) => {
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
    <div className="fixed inset-0 z-50 grid place-items-center bg-[#12172b]/50 p-4 backdrop-blur-[2px]" onKeyDown={surClavier}>
      <div
        ref={boite}
        role="dialog"
        aria-modal="true"
        aria-labelledby={idTitre}
        aria-describedby={description ? idDescription : undefined}
        className="animate-apparition w-full max-w-md rounded-carte border border-bordure bg-surface p-7 shadow-eleve"
      >
        <form onSubmit={soumettre} noValidate className="flex flex-col gap-4">
          <h2 id={idTitre} className="text-xl font-bold">
            {titre}
          </h2>
          {description && (
            <div id={idDescription} className="text-sm text-texte-doux">
              {description}
            </div>
          )}
          {champs}
          {afficherMotif && (
            <ZoneTexte
              label={libelleMotif}
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              erreur={(tente ? erreurMotif : null) ?? erreur}
              aide={motifMin !== undefined && motifMax !== undefined ? L.commun.motifBornes(motifMin, motifMax) : undefined}
              required={motifObligatoire}
            />
          )}
          {!afficherMotif && erreur && (
            <p role="alert" className="text-sm font-medium text-erreur-texte">
              {erreur}
            </p>
          )}
          <div className="flex justify-end gap-2">
            <Bouton variante="secondaire" onClick={onAnnuler} disabled={enCours}>
              {L.commun.annuler}
            </Bouton>
            <Bouton type="submit" variante={danger ? 'danger' : 'primaire'} enCours={enCours} disabled={confirmerDesactive}>
              {libelleConfirmer}
            </Bouton>
          </div>
        </form>
      </div>
    </div>,
    document.body,
  )
}
