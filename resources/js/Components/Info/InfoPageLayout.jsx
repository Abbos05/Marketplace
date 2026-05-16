import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/info-page.css';

export default function InfoPageLayout({ title, lead, children }) {
  return (
    <MainLayout>
      <Head title={title} />
      <div className="info-page container">
        <article className="info-page__card">
          <h1 className="info-page__title">{title}</h1>
          {lead && <p className="info-page__lead">{lead}</p>}
          {children}
        </article>
      </div>
    </MainLayout>
  );
}

export function InfoSection({ title, children }) {
  return (
    <section className="info-page__section">
      {title && <h2>{title}</h2>}
      {children}
    </section>
  );
}

export function InfoLink({ href, children }) {
  return (
    <Link href={href} className="info-page__link">
      {children}
    </Link>
  );
}
