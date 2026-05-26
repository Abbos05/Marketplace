const ALLOWED_TAGS = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'UL', 'OL', 'LI', 'P', 'BR', 'DIV', 'SPAN']);

export const DESCRIPTION_MAX_PLAIN = 500;

export function plainTextLength(html = '') {
  if (!html) return 0;
  const div = document.createElement('div');
  div.innerHTML = html;
  return (div.textContent || '').replace(/\u00a0/g, ' ').trim().length;
}

export function sanitizeRichHtml(html = '') {
  if (!html) return '';

  const doc = new DOMParser().parseFromString(html, 'text/html');
  const walk = (node) => {
    [...node.childNodes].forEach((child) => {
      if (child.nodeType === Node.ELEMENT_NODE) {
        if (!ALLOWED_TAGS.has(child.tagName)) {
          const fragment = document.createDocumentFragment();
          while (child.firstChild) fragment.appendChild(child.firstChild);
          child.replaceWith(fragment);
          walk(node);
          return;
        }
        [...child.attributes].forEach((attr) => child.removeAttribute(attr.name));
        walk(child);
      }
    });
  };

  walk(doc.body);
  return doc.body.innerHTML.trim();
}

export function truncateRichHtml(html = '', maxPlain = DESCRIPTION_MAX_PLAIN) {
  const clean = sanitizeRichHtml(html);
  if (plainTextLength(clean) <= maxPlain) return clean;

  const doc = new DOMParser().parseFromString(clean, 'text/html');
  let count = 0;
  const trimNode = (node) => {
    [...node.childNodes].forEach((child) => {
      if (count >= maxPlain) {
        child.remove();
        return;
      }
      if (child.nodeType === Node.TEXT_NODE) {
        const remaining = maxPlain - count;
        const text = child.textContent || '';
        if (text.length > remaining) {
          child.textContent = text.slice(0, remaining);
          count = maxPlain;
        } else {
          count += text.length;
        }
        return;
      }
      if (child.nodeType === Node.ELEMENT_NODE) {
        trimNode(child);
      }
    });
  };
  trimNode(doc.body);
  return doc.body.innerHTML.trim();
}
