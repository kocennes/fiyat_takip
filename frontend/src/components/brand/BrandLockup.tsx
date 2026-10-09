import classNames from 'classnames';

interface Props {
  collapsed?: boolean;
}

export function BrandLockup({ collapsed = false }: Props) {
  return (
    <div
      className={classNames('flex items-center overflow-hidden', {
        'h-10 w-10': collapsed,
        'h-12 w-full': !collapsed,
      })}
    >
      <img
        src="/brand/bisavunma-console.svg"
        alt="BİSAVUNMA Fiyat İstihbarat Platformu"
        className={classNames('max-w-none', {
          'h-10 w-auto': collapsed,
          'h-12 w-auto': !collapsed,
        })}
      />
    </div>
  );
}
