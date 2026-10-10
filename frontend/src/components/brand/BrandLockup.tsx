import classNames from 'classnames';
import { useColorScheme } from '$app/common/colors';

interface Props {
  collapsed?: boolean;
  /**
   * Tone of the surface behind the lockup. `auto` follows the active
   * light/dark colour scheme; the sidebars always sit on a dark surface.
   */
  background?: 'auto' | 'light' | 'dark';
}

const BRAND_NAME = 'BISAVUNMA Fiyat Takip';

export function BrandLockup({ collapsed = false, background = 'auto' }: Props) {
  const colors = useColorScheme();

  const isDarkSurface =
    background === 'dark' || (background === 'auto' && colors.$0 === 'dark');

  let src = isDarkSurface
    ? '/brand/bisavunma-logo-white.png'
    : '/brand/bisavunma-logo.png';

  if (collapsed) {
    src = isDarkSurface
      ? '/brand/bisavunma-mark-white.png'
      : '/brand/bisavunma-mark.png';
  }

  return (
    <div
      className={classNames('flex items-center min-w-0', {
        'h-10 w-10 justify-center': collapsed,
        'h-12': !collapsed,
      })}
    >
      <img
        src={src}
        alt={BRAND_NAME}
        draggable={false}
        className={classNames('object-contain', {
          'h-10 w-10': collapsed,
          'h-12 w-auto max-w-full': !collapsed,
        })}
      />
    </div>
  );
}
