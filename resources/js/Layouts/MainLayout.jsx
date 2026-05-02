// src/layouts/MainLayout.jsx
import React, { useState, useEffect, useRef } from 'react';
import Header from '@/Components/HeaderFooter/Header';
import Footer from '@/Components/HeaderFooter/Footer';
import AuthModal from '@/Components/AuthModal'; // ← новый компонент
import '../../css/MainLayout.css';

const MainLayout = ({
  children, auth, flash, showModal = null, }) => {
  // Ping для поддержания сессии
  useEffect(() => {
    const pingInterval = setInterval(() => {
      fetch('/ping', { method: 'GET', credentials: 'same-origin' }).catch(() => { });
    }, 5000);
    return () => clearInterval(pingInterval);
  }, []);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLogin, setIsLogin] = useState(true);
  useEffect(() => {
    if (showModal === 'login') {
      setIsModalOpen(true);
      setIsLogin(true);
    } else if (showModal === 'register') {
      setIsModalOpen(true);
      setIsLogin(false);
    }
  }, [showModal]);


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
      <Header
        setIsModalOpen={setIsModalOpen}
        setIsLogin={setIsLogin}
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

      <AuthModal
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        isLogin={isLogin}
        setIsLogin={setIsLogin}
        onLoginSuccess={() => {
          setIsModalOpen(false);
        }}
        onRegisterSuccess={() => {
          setIsModalOpen(false);
        }}
      />


    </div>
      <Footer />
   </>

  );
};

export default MainLayout;