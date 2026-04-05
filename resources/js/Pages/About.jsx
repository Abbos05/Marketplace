// resources/js/Pages/About.jsx
import React from 'react';
import { Link } from '@inertiajs/react';
import '../../css/about.css'; // ← создадим ниже
import '../../css/app.css';
import MainLayout from '@/Layouts/MainLayout';


export default function About() {
  return (
    <MainLayout>
      <div className="about-page">
        {/* Анимированный фон */}
        <div className="bg-animation">
          <div className="golden-orbit orbit-1"></div>
          <div className="golden-orbit orbit-2"></div>
        </div>

        <div className="container mx-auto px-6 py-20 relative z-10">
          {/* Заголовок */}
          <div className="text-center mb-20">
            <h1 className="text-6xl md:text-8xl font-bold golden-text glow-title">
              AltChain
            </h1>
            <p className="text-2xl md:text-4xl text-yellow-100 mt-6 opacity-90">
              NFT-маркетплейс нового поколения
            </p>
          </div>

          {/* Главная карточка */}
          <div className="glass-card max-w-5xl mx-auto p-12 md:p-20 text-center">
            <h2 className="text-4xl md:text-6xl font-bold golden-gradient-text mb-8">
              Мы создаём будущее цифрового искусства
            </h2>
            <p className="text-xl md:text-2xl text-gray-200 leading-relaxed max-w-4xl mx-auto">
              AltChain — это не просто маркетплейс. Это экосистема, где художники, коллекционеры и инвесторы встречаются в мире премиальных NFT.
              Низкие комиссии, мгновенные транзакции, полная прозрачность и стиль, от которого невозможно отвести взгляд.
            </p>
          </div>

          {/* Статистика */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-10 mt-20 max-w-6xl mx-auto">
            {[
              { num: "50K+", label: "Активных пользователей" },
              { num: "250K+", label: "Проданных NFT" },
              { num: "1.5%", label: "Комиссия платформы" }
            ].map((stat, i) => (
              <div key={i} className="stat-card text-center" data-aos="zoom-in" data-aos-delay={i * 200}>
                <div className="text-6xl md:text-7xl font-bold golden-text glow-num">
                  {stat.num}
                </div>
                <p className="text-xl text-yellow-100 mt-4">{stat.label}</p>
              </div>
            ))}
          </div>

          {/* Особенности */}
          <div className="mt-32 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10 max-w-7xl mx-auto">
            {[
              { icon: "Lightning", title: "Мгновенные сделки", desc: "Покупка и продажа за секунды" },
              { icon: "Shield", title: "100% безопасность", desc: "Аудит смарт-контрактов" },
              { icon: "Palette", title: "Для авторов", desc: "До 98.5% с продаж — вам" },
              { icon: "Infinity", title: "Кросс-чейн", desc: "Polygon, Ethereum, BSC" },
              { icon: "Star", title: "Эксклюзивные дропы", desc: "Только у нас" },
              { icon: "Heart", title: "Сообщество", desc: "50K+ единомышленников" }
            ].map((feature, i) => (
              <div key={i} className="feature-card" data-aos="fade-up" data-aos-delay={i * 100}>
                <div className="feature-icon text-6xl  golden-text glow-title">{feature.icon}</div>
                <h3 className="text-2xl font-bold golden-text mt-6">{feature.title}</h3>
                <p className="text-gray-300 mt-4">{feature.desc}</p>
              </div>
            ))}
          </div>

          <div className="text-center mt-32">
            <Link href="/" className="btn-golden text-2xl px-16 py-6">
              Начать коллекционировать
            </Link>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}