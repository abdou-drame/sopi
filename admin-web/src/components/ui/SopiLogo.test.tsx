import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import SopiLogo from './SopiLogo'

describe('SopiLogo', () => {
  it('affiche le logo avec le texte alternatif « Sopi » et la taille demandée', () => {
    render(<SopiLogo taille={56} />)
    const image = screen.getByRole('img', { name: 'Sopi' })
    expect(image).toHaveAttribute('src', '/logo/sopi_icon_rounded.svg')
    expect(image).toHaveAttribute('width', '56')
    expect(image).toHaveAttribute('height', '56')
  })
})
