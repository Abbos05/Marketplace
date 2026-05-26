import React, { useEffect, useRef, useState } from 'react';
import {
  DESCRIPTION_MAX_PLAIN,
  plainTextLength,
  sanitizeRichHtml,
  truncateRichHtml,
} from '@/lib/richTextUtils';

const TOOLBAR = [
  { cmd: 'bold', label: 'Ж', title: 'Жирный', className: 'is-bold' },
  { cmd: 'italic', label: 'К', title: 'Курсив', className: 'is-italic' },
  { cmd: 'underline', label: 'Ч', title: 'Подчёркнутый', className: 'is-underline' },
  { cmd: 'insertUnorderedList', label: '•', title: 'Маркированный список' },
  { cmd: 'insertOrderedList', label: '1.', title: 'Нумерованный список' },
];

export default function RichTextEditor({ value = '', onChange, placeholder = 'Расскажите о себе...', maxPlain = DESCRIPTION_MAX_PLAIN, error = null }) {
  const editorRef = useRef(null);
  const [length, setLength] = useState(() => plainTextLength(value));

  useEffect(() => {
    if (!editorRef.current) return;
    const current = editorRef.current.innerHTML;
    const next = value || '';
    if (current !== next) {
      editorRef.current.innerHTML = next;
    }
    setLength(plainTextLength(next));
  }, [value]);

  const sync = () => {
    if (!editorRef.current) return;
    let html = sanitizeRichHtml(editorRef.current.innerHTML);
    if (plainTextLength(html) > maxPlain) {
      html = truncateRichHtml(html, maxPlain);
      editorRef.current.innerHTML = html;
    }
    setLength(plainTextLength(html));
    onChange?.(html);
  };

  const runCommand = (cmd) => {
    editorRef.current?.focus();
    document.execCommand(cmd, false, null);
    sync();
  };

  const handlePaste = (e) => {
    e.preventDefault();
    const text = e.clipboardData.getData('text/plain');
    document.execCommand('insertText', false, text);
    sync();
  };

  return (
    <div className={`rich-text-editor${error ? ' has-error' : ''}`}>
      <div className="rich-text-toolbar" role="toolbar" aria-label="Форматирование текста">
        {TOOLBAR.map((btn) => (
          <button
            key={btn.cmd}
            type="button"
            className={`rich-text-tool${btn.className ? ` ${btn.className}` : ''}`}
            title={btn.title}
            aria-label={btn.title}
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => runCommand(btn.cmd)}
          >
            {btn.label}
          </button>
        ))}
      </div>
      <div
        ref={editorRef}
        className="rich-text-area settings-input"
        contentEditable
        role="textbox"
        aria-multiline="true"
        data-placeholder={placeholder}
        onInput={sync}
        onBlur={sync}
        onPaste={handlePaste}
        suppressContentEditableWarning
      />
      <div className="rich-text-meta">
        <span className="settings-hint">{length}/{maxPlain} символов</span>
        <span className="settings-hint">Можно выделить текст и сделать жирным, курсивом или списком</span>
      </div>
      {error && <p className="settings-error">{error}</p>}
    </div>
  );
}
