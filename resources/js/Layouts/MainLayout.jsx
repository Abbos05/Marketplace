// src/layouts/MainLayout.jsx
import React, { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import Header from '@/Components/HeaderFooter/Header';
import Footer from '@/Components/HeaderFooter/Footer';
import PhoneAuthModal from '@/Components/PhoneAuthModal';
import MessagesFloatingWidget from '@/Components/MessagesFloatingWidget';
import '../../css/MainLayout.css';
import '../../css/messages-widget.css';

const MainLayout = ({
  children, auth, flash, showModal = null, }) => {
  const { props } = usePage();
  const authedUser = props.auth?.user;
  const pageFlash = props.flash ?? flash ?? {};
  // Ping для поддержания сессии
  useEffect(() => {
    const pingInterval = setInterval(() => {
      fetch('/ping', { method: 'GET', credentials: 'same-origin' }).catch(() => { });
    }, 5000);
    return () => clearInterval(pingInterval);
  }, []);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLogin, setIsLogin] = useState(true);
  const [showScrollTop, setShowScrollTop] = useState(false);
  useEffect(() => {
    if (showModal === 'login' || showModal === 'register' || showModal === 'phone_auth') {
      setIsModalOpen(true);
    }
  }, [showModal]);

  useEffect(() => {
    const handleScroll = () => {
      setShowScrollTop(window.scrollY > 500);
    };

    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };


  const handleCloseModal = () => {
    setIsModalOpen(false);
  };



  const [position, setPosition] = useState({ x: 0, y: 0 });
  const orbitRef = useRef(null);

  useEffect(() => {
    const handleMouseMove = (e) => {
      // Плавное следование с задержкой
      const targetX = e.clientX;
      const targetY = e.clientY;

      // Используем requestAnimationFrame для плавности
      requestAnimationFrame(() => {
        setPosition({ x: targetX, y: targetY });
      });
    };

    window.addEventListener('mousemove', handleMouseMove);
    return () => window.removeEventListener('mousemove', handleMouseMove);
  }, []);
  return (
    <>
      <div className="main-layout container">
        {(pageFlash.success || pageFlash.error) && (
          <div
            className={`main-flash ${pageFlash.error ? 'main-flash--error' : 'main-flash--success'}`}
            role="alert"
          >
            {pageFlash.error || pageFlash.success}
          </div>
        )}
        <Header
          setIsModalOpen={setIsModalOpen}
          flash={flash}
        />
        <div
          ref={orbitRef}
          className="golden-orbit"
          style={{
            transform: `translate(${position.x - 250}px, ${position.y - 250}px)`,
          }}
        >
        </div>
        {children}

        <PhoneAuthModal
          isOpen={isModalOpen}
          onClose={handleCloseModal}
        />


      </div>
      <Footer />
      {authedUser && <MessagesFloatingWidget />}
      <button
        type="button"
        className={`scroll-top-btn ${showScrollTop ? 'visible' : ''}`}
        onClick={scrollToTop}
        aria-label="Наверх"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 15l6-7 6 7" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </button>
    </>

  );
};

export default MainLayout;