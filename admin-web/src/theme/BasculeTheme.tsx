import { IconeLune, IconeSoleil } from '../components/ui/Icones'
import { L } from '../i18n/libelles'
import { useTheme } from './ThemeContext'

/** Interrupteur clair/sombre (rôle « switch ») : bouton rond avec soleil ou lune. */
export default function BasculeTheme() {
  const { theme, basculer } = useTheme()
  const sombre = theme === 'sombre'
  return (
    <button
      type="button"
      role="switch"
      aria-checked={sombre}
      aria-label={L.coque.modeSombre}
      onClick={basculer}
      className="grid size-10 place-items-center rounded-full bg-fond text-texte-doux transition-colors hover:text-primaire-700"
    >
      {sombre ? <IconeLune width={18} height={18} /> : <IconeSoleil width={18} height={18} />}
    </button>
  )
}
