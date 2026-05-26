import React, { useMemo } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { FOOTER_COLUMNS, FOOTER_LEGAL } from '@/lib/footerLinks';
import { isStaff } from '@/lib/staffAccess';
import '../../../css/footer.css';

function FooterLink({ href, label, auth }) {
  const { auth: pageAuth } = usePage().props;
  const resolvedHref = auth && !pageAuth?.user ? '/login' : href;

  return (
    <li>
      <Link href={resolvedHref} className="site-footer__link">
        {label}
      </Link>
    </li>
  );
}

function FooterColumn({ title, links }) {
  return (
    <div className="site-footer__col site-footer__desktop-only">
      <p className="site-footer__col-title">{title}</p>
      <ul className="site-footer__links">
        {links.map((item) => (
          <FooterLink key={item.href + item.label} {...item} />
        ))}
      </ul>
    </div>
  );
}

function FooterAccordion({ title, links }) {
  return (
    <details className="site-footer__accordion site-footer__mobile-only">
      <summary>{title}</summary>
      <ul className="site-footer__links">
        {links.map((item) => (
          <FooterLink key={item.href + item.label} {...item} />
        ))}
      </ul>
    </details>
  );
}

const Footer = () => {
  const { categories = [], footerSocial = [], auth, staffAccess } = usePage().props;
  const isStaffUser = staffAccess?.isStaff ?? isStaff(auth?.user);

  const footerColumns = useMemo(() => {
    if (!isStaffUser) return FOOTER_COLUMNS;
    return {
      ...FOOTER_COLUMNS,
      sellers: {
        ...FOOTER_COLUMNS.sellers,
        links: FOOTER_COLUMNS.sellers.links.filter((l) => l.href !== '/profile?tab=company'),
      },
    };
  }, [isStaffUser]);
  const catalogLinks = categories.slice(0, 8).map((c) => ({
    label: c.name,
    href: `/category/${c.id}`,
  }));

  const year = new Date().getFullYear();

  return (
    <footer className="site-footer">
      <div className="site-footer__inner">
        <div className="site-footer__top">
          <div className="site-footer__brand">
            <Link href="/" className="header-logo-name">
              <svg className="logo" viewBox="0 0 400 100" width="100%" height="100%">
                <text x="50%" y="62%" textAnchor="middle" dominantBaseline="middle" fill="currentColor">
                  Alvora
                </text>
              </svg>
            </Link>
            <p className="site-footer__tagline">
              Маркетплейс для покупателей и продавцов: каталог, заказы, доставка в пункты выдачи.
            </p>
            {footerSocial.length > 0 && (
              <div className="site-footer__social">
                {footerSocial.map((item) => (
                  <a
                    key={item.label}
                    href={item.url}
                    className="site-footer__social-link"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {item.label}
                  </a>
                ))}
              </div>
            )}
          </div>

          <FooterColumn {...footerColumns.buyers} />
          <FooterColumn {...footerColumns.sellers} />
          <FooterColumn {...footerColumns.company} />

          <div className="site-footer__col site-footer__desktop-only">
            <p className="site-footer__col-title">Каталог</p>
            <ul className="site-footer__links">
              <FooterLink href="/category" label="Все категории" />
              {catalogLinks.map((item) => (
                <FooterLink key={item.href} {...item} />
              ))}
            </ul>
          </div>

          <div className="site-footer__mobile-only">
            <FooterAccordion {...footerColumns.buyers} />
            <FooterAccordion {...footerColumns.sellers} />
            <FooterAccordion {...footerColumns.company} />
            <FooterAccordion
              title="Каталог"
              links={[{ label: 'Все категории', href: '/category' }, ...catalogLinks]}
            />
          </div>
        </div>

        <div className="site-footer__bottom">
          <p className="site-footer__copy">© {year} ALVORA</p>
          <nav className="site-footer__legal" aria-label="Юридическая информация">
            {FOOTER_LEGAL.map((item) => (
              <Link key={item.label} href={item.href} className="site-footer__legal-link">
                {item.label}
              </Link>
            ))}
          </nav>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
