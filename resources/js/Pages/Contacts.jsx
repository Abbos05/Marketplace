// resources/js/Pages/Contacts.jsx
import React from 'react';
import { Link } from '@inertiajs/react';
import '../../css/about.css';
import MainLayout from '@/Layouts/MainLayout';

export default function Contacts() {
  return (
    <MainLayout>
      <div className="about-page">
        
        <div className="container mx-auto px-6 py-20 relative z-10">
          <h1 className="text-6xl md:text-8xl font-bold text-center golden-text glow-title mb-20">
            Связаться с нами
          </h1>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 max-w-6xl mx-auto">
            {/* Левая часть */}
            <div>
              <div className="glass-card p-12">
                <h2 className="text-4xl font-bold golden-gradient-text mb-10">
                  Мы всегда на связи
                </h2>
                <div className="space-y-8">
                  <div className="contact-item">
                    <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <div>
                      <p className="text-yellow-100 font-semibold">Email</p>
                      <a href="mailto:support@altchain.io" className="text-2xl hover:golden-text transition">support@altchain.io</a>
                    </div>
                  </div>

                  <div className="contact-item">
                    <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h12v16H6V4zm6 17h1v-1h-1v1z"/>
                    </svg>
                    <div>
                      <p className="text-yellow-100 font-semibold">Telegram</p>
                      <a href="https://t.me/altchain" className="text-2xl hover:golden-text transition">@altchain</a>
                    </div>
                  </div>

                  <div className="contact-item">
                    <svg className="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M12 2A10 10 0 0 0 2 12a10 10 0 0 0 6.84 9.49c.5 0 .68-.3.68-.66v-2.34c-2.71.6-3.28-1.3-3.28-1.3-.44-1.1-1.07-1.4-1.07-1.4-.88-.6.06-.6.06-.6 1 .07 1.53 1 1.53 1 .88 1.5 2.3 1.07 2.86.82.1-.63.34-1.07.62-1.32-2.16-.24-4.43-1.08-4.43-4.8 0-1.06.38-1.93 1-2.61-.1-.24-.43-1.24.1-2.58 0 0 .82-.26 2.7 1a9.4 9.4 0 0 1 2.45-.33c.83 0 1.67.11 2.45.33 1.88-1.27 2.7-1 2.7-1 .54 1.34.2 2.34.1 2.58.62.68 1 1.55 1 2.61 0 3.73-2.27 4.56-4.43 4.8.35.3.66.9.66 1.81v2.67c0 .37.18.67.69.66A10 10 0 0 0 12 2z"/>
                    </svg>
                    <div>
                      <p className="text-yellow-100 font-semibold">Twitter / X</p>
                      <a href="https://twitter.com/altchain" className="text-2xl hover:golden-text transition">@altchain</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Правая часть — форма обратной связи (можно потом подключить) */}
            <div className="glass-card p-12">
              <h2 className="text-4xl font-bold golden-gradient-text mb-10">
                Написать нам
              </h2>
              <form className="space-y-8">
                <input type="text" placeholder="Ваше имя" className="input-golden" />
                <input type="email" placeholder="Email" className="input-golden" />
                <textarea rows="6" placeholder="Сообщение..." className="input-golden"></textarea>
                <button type="submit" className="btn-golden w-full text-xl py-5">
                  Отправить сообщение
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}