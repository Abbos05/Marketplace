// src/components/Slider.jsx
import React, { useState, useEffect, useRef } from 'react';
import '../../../css/Slider.css';

// Данные для слайдов (можно вынести в отдельный файл)
const slidesData = [
  {
    title: 'Самые лучшие техники ',
    description:
      'Нажмите «Мои коллекции» и настройте свою коллекцию. Опишите: описание, изображения профиля и баннера, а также установите комиссию за вторичные продажи',
    buttonText: 'Посмотреть',
    image: 'img/header/slider1.png',
  },
  {
    title: 'Продавайте свои NFT без комиссии',
    description:
      'Установите свою комиссию за каждую вторичную продажу и получайте доход без усилий. Подключите смарт-контракт и начните зарабатывать.',
    buttonText: 'Настроить продажу',
    image: 'img/header/slider2.png',
  },
  {
    title: 'Создайте свою уникальную коллекцию',
    description:
      'Загрузите свои произведения, добавьте описание, теги и настройте роялти. Ваша коллекция — ваше наследие.',
    buttonText: 'Создать коллекцию',
    image: 'img/header/slider3.png',
  },
];

const Slider = () => {
  const [currentImageIndex, setCurrentImageIndex] = useState(0);
  const sliderContainerRef = useRef(null);
  const totalSlides = slidesData.length;

  // Функция переключения слайда
  const switchImage = (index) => {
    setCurrentImageIndex(index);
    if (sliderContainerRef.current) {
      const translateX = -index * 100;
      sliderContainerRef.current.style.transform = `translateX(${translateX}%)`;
    }
  };

  // Автоматическое переключение
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentImageIndex((prevIndex) => (prevIndex + 1) % totalSlides);
    }, 15000);

    return () => clearInterval(interval); // Очистка интервала при размонтировании
  }, [totalSlides]);

  // Обновление transform при изменении индекса
  useEffect(() => {
    switchImage(currentImageIndex);
  }, [currentImageIndex]);
  const click = () => {
    window.location.href = route('nft.create');
  }
  return (
    <section className="sectionSlider">
        <div className="slider">
          <div className="slider-container" ref={sliderContainerRef}>
            {slidesData.map((slide, index) => (
              <div
                key={index}
                className={`slide ${index === currentImageIndex ? 'active' : ''}`}
              >
                <div className="sliderTitle">
                  <h1>{slide.title}</h1>
                  <p>{slide.description}</p>
                  <button onClick={() => click()}>{slide.buttonText}</button>
                </div>
                <img
                  src={slide.image}
                  alt={`Слайд ${index + 1}`}
                  className="slider-image"
                />
              </div>
            ))}
          </div>
        </div>
        <div className="slider__line">
          {slidesData.map((_, index) => (
            <img
              key={index}
              src={
                index === currentImageIndex
                  ? 'img/header/Line__hover.png'
                  : 'img/header/Line.png'
              }
              alt="Line"
              onClick={() => switchImage(index)}
            />
          ))}
        </div>
    </section>
  );
};

export default Slider;