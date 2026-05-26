import React, { useEffect, useRef, useState } from 'react';
import '../../css/scroll-reveal.css';

/**
 * Анимация появления при прокрутке (scroll reveal).
 * @param {number} delay — задержка в мс для каскада (stagger)
 */
export default function ScrollReveal({ children, delay = 0, className = '' }) {
  const ref = useRef(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return undefined;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setVisible(true);
      return undefined;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true);
          observer.disconnect();
        }
      },
      {
        threshold: 0.08,
        rootMargin: '0px 0px -32px 0px',
      }
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const classes = ['scroll-reveal', visible && 'scroll-reveal--visible', className]
    .filter(Boolean)
    .join(' ');

  return (
    <div ref={ref} className={classes} style={{ transitionDelay: `${delay}ms` }}>
      {children}
    </div>
  );
}

/** Задержка для каскадного появления в сетке */
export function staggerDelay(index, stepMs = 65, maxStagger = 8) {
  return (index % maxStagger) * stepMs;
}
