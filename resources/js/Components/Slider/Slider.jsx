import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from '@inertiajs/react';
import '../../../css/Slider.css';

/**
 * @typedef {Object} HomeSlide
 * @property {number} id
 * @property {string|null} title
 * @property {string|null} description
 * @property {string|null} button_text
 * @property {string} image
 * @property {string|null} href
 * @property {boolean} external
 */

/** @param {{ slides?: HomeSlide[] }} props */
const Slider = ({ slides = [] }) => {
  const [currentImageIndex, setCurrentImageIndex] = useState(0);
  /** Сбрасывает таймер автопрокрутки при ручном переключении */
  const [autoplayEpoch, setAutoplayEpoch] = useState(0);
  const sliderContainerRef = useRef(null);
  const totalSlides = slides.length;

  const bumpAutoplay = useCallback(() => {
    setAutoplayEpoch((n) => n + 1);
  }, []);

  useEffect(() => {
    if (totalSlides < 1) return undefined;
    const interval = setInterval(() => {
      setCurrentImageIndex((prevIndex) => (prevIndex + 1) % totalSlides);
    }, 15000);

    return () => clearInterval(interval);
  }, [totalSlides, autoplayEpoch]);

  useEffect(() => {
    if (totalSlides < 1 || !sliderContainerRef.current) return;
    const translateX = -currentImageIndex * 100;
    sliderContainerRef.current.style.transform = `translateX(${translateX}%)`;
  }, [currentImageIndex, totalSlides]);

  const goToSlide = useCallback(
    (index) => {
      bumpAutoplay();
      setCurrentImageIndex(index);
    },
    [bumpAutoplay]
  );

  const goPrev = useCallback(() => {
    bumpAutoplay();
    setCurrentImageIndex((prev) => (prev - 1 + totalSlides) % totalSlides);
  }, [bumpAutoplay, totalSlides]);

  const goNext = useCallback(() => {
    bumpAutoplay();
    setCurrentImageIndex((prev) => (prev + 1) % totalSlides);
  }, [bumpAutoplay, totalSlides]);

  if (totalSlides === 0) {
    return null;
  }

  const renderSlideBody = (slide) => (
    <>
      <div className="sliderTitle">
        {slide.title ? <h1>{slide.title}</h1> : null}
        {slide.description ? <p>{slide.description}</p> : null}
        {slide.button_text ? (
          <a key={slide.id ?? index} href={slide.href} className="sliderTitle-cta" tabIndex={-1} aria-hidden="true">
            {slide.button_text}
          </a>
        ) : null}
      </div>
      <img src={slide.image} alt="" className="slider-image" />
    </>
  );

  const wrapSlide = (slide, index, inner) => {
    const active = index === currentImageIndex ? 'active' : '';
    if (slide.href) {
      if (slide.external) {
        return (
          <a
            key={slide.id ?? index}
            href={slide.href}
            className={`slide slide-link ${active}`}
            target="_blank"
            rel="noopener noreferrer"
          >
            {inner}
          </a>
        );
      }
      return (
        <div  className={`slide slide-link ${active}`}>
          {inner}
        </div>
      );
    }
    return (
      <div key={slide.id ?? index} className={`slide ${active}`}>
        {inner}
      </div>
    );
  };

  const showArrows = totalSlides > 1;

  return (
    <section className="sectionSlider">
      <div className="slider">
        {showArrows && (
          <button
            type="button"
            className="slider__arrow slider__arrow--prev"
            aria-label="Предыдущий слайд"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              goPrev();
            }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M15 6l-6 6 6 6" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </button>
        )}
        <div className="slider-container" ref={sliderContainerRef}>
          {slides.map((slide, index) => wrapSlide(slide, index, renderSlideBody(slide)))}
        </div>
        {showArrows && (
          <button
            type="button"
            className="slider__arrow slider__arrow--next"
            aria-label="Следующий слайд"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              goNext();
            }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 6l6 6-6 6" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </button>
        )}
      </div>
      <div className="slider__line">
        {slides.map((_, index) => (
          <img
            key={index}
            src={index === currentImageIndex ? 'img/header/Line__hover.png' : 'img/header/Line.png'}
            alt=""
            role="presentation"
            onClick={() => goToSlide(index)}
          />
        ))}
      </div>
    </section>
  );
};

export default Slider;
