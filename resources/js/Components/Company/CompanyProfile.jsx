// Components/Company/CompanyProfile.jsx
import React from 'react';
import { Link } from '@inertiajs/react';

export default function CompanyProfile({ company, onSwitchToSeller }) {
  if (!company) {
    return (
      <div className="company-empty">
        <div className="company-empty-icon">🏢</div>
        <h3>Нет активной компании</h3>
        <p>Добавьте компанию, чтобы стать продавцом</p>
      </div>
    );
  }

  return (
    <div className="company-profile-card">
      <div className="company-header">
        <div className="company-logo">
          {company.logo ? (
            <img src={company.logo} alt={company.name} />
          ) : (
            <div className="logo-placeholder">🏢</div>
          )}
        </div>
        <div className="company-info">
          <h3>{company.name}</h3>
          <p className="company-inn">ИНН: {company.inn}</p>
          <p className="company-status">
            <span className={`status-badge status-${company.status}`}>
              {company.status === 'active' ? 'Активна' : 'На проверке'}
            </span>
          </p>
        </div>
      </div>

      <div className="company-details">
        <div className="detail-row">
          <span className="label">Юридический адрес:</span>
          <span className="value">{company.legal_address}</span>
        </div>
        {company.actual_address && (
          <div className="detail-row">
            <span className="label">Фактический адрес:</span>
            <span className="value">{company.actual_address}</span>
          </div>
        )}
        <div className="detail-row">
          <span className="label">Директор:</span>
          <span className="value">{company.director_name || '—'}</span>
        </div>
      </div>

      {company.status === 'approved' && !company.is_seller && (
        <button className="become-seller-btn" onClick={onSwitchToSeller}>
          Стать продавцом
        </button>
      )}

      {company.is_seller && (
        <Link href="/seller/dashboard" className="seller-dashboard-link">
          Перейти в панель продавца →
        </Link>
      )}
    </div>
  );
}