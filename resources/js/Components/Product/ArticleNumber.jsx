import { useCallback, useState } from 'react';

const ARTICLE_PREFIX = '000';

function CopyIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" strokeWidth="1.75" />
      <path
        d="M7 15H6a2 2 0 01-2-2V6a2 2 0 012-2h7a2 2 0 012 2v1"
        stroke="currentColor"
        strokeWidth="1.75"
      />
    </svg>
  );
}

/**
 * @param {{
 *   sku?: string | null,
 *   className?: string,
 *   display?: 'plain' | 'split',
 *   copyable?: boolean,
 * }} props
 */
export default function ArticleNumber({
  sku,
  className = '',
  display = 'split',
  copyable = false,
}) {
  const [copied, setCopied] = useState(false);

  const copySku = useCallback(async () => {
    if (!sku) return;
    try {
      await navigator.clipboard.writeText(sku);
    } catch {
      const el = document.createElement('textarea');
      el.value = sku;
      el.setAttribute('readonly', '');
      el.style.position = 'absolute';
      el.style.left = '-9999px';
      document.body.appendChild(el);
      el.select();
      document.execCommand('copy');
      document.body.removeChild(el);
    }
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1600);
  }, [sku]);

  if (!sku) {
    return null;
  }

  const rootClass = [
    'article-number',
    display === 'plain' ? 'article-number--plain' : 'article-number--split',
    copyable ? 'article-number--copyable' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  const valueNode =
    display === 'plain' ? (
      <span className="article-number__value">{sku}</span>
    ) : (
      <>
        <span className="article-number__prefix">{ARTICLE_PREFIX}</span>
        <span className="article-number__id">
          {sku.startsWith(ARTICLE_PREFIX) ? sku.slice(ARTICLE_PREFIX.length) : sku}
        </span>
      </>
    );

  if (!copyable) {
    return <span className={rootClass}>{valueNode}</span>;
  }

  return (
    <span className={rootClass}>
      <button
        type="button"
        className="article-number__copy-target"
        onClick={copySku}
        title="Скопировать артикул"
        aria-label={copied ? 'Артикул скопирован' : `Скопировать артикул ${sku}`}
      >
        {valueNode}
      </button>
      <button
        type="button"
        className={`article-number__copy-btn${copied ? ' is-copied' : ''}`}
        onClick={copySku}
        title={copied ? 'Скопировано' : 'Скопировать артикул'}
        aria-label={copied ? 'Скопировано' : 'Скопировать артикул'}
      >
        {copied ? (
          <span className="article-number__copy-done">✓</span>
        ) : (
          <CopyIcon />
        )}
      </button>
    </span>
  );
}
