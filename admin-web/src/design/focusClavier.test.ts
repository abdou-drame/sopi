import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const css = readFileSync('src/index.css', 'utf8')
const maquette = readFileSync('src/maquette.css', 'utf8')

// jsdom ne dessine pas : on vérifie que les règles de focus clavier sont bien en place.
describe('focus clavier', () => {
  it('un anneau global :focus-visible aux couleurs de la marque, sans anneau au clic souris', () => {
    expect(css).toMatch(/--anneau-focus:\s*var\(--color-marque\)/)
    expect(css).toMatch(/:focus-visible\s*\{\s*outline:\s*2px solid var\(--anneau-focus\)/)
    expect(css).not.toMatch(/(^|\n)\*?:focus\s*\{/)
  })

  it('les champs de recherche et les filtres en gélule entourent la gélule, la barre sombre passe au blanc', () => {
    expect(css).toMatch(/label:has\(> input\.bg-transparent:focus-visible/)
    expect(css).toMatch(/\[role='toolbar'\] :focus-visible\s*\{\s*outline-color: #fff/)
  })

  it('les onglets tracent l’anneau à l’intérieur et les champs gardent un vrai anneau', () => {
    expect(css).toMatch(/\[role='tablist'\] > \[role='tab'\]:focus-visible\s*\{\s*outline-offset: -3px/)
    expect(maquette).toMatch(/\.mq-champ:focus-visible/)
    expect(maquette).not.toMatch(/\.mq-champ:focus\s*\{/)
  })
})
