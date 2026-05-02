// Слайдер — лента с горизонтальным скольжением
const sliderContainer = document.querySelector('.slider-container');
const slides = document.querySelectorAll('.slide');
const currentPhoto__line = document.getElementById('line__hover');
const currentPhoto__line__grey = document.getElementById('line__grey');
const currentPhoto__line__grey__two = document.getElementById('line__grey__two');

let currentImageIndex = 0;
const totalSlides = slides.length;

// Функция переключения слайда
function switchImage(index) {
    // Вычисляем смещение: -100% * index (каждый слайд — 100% ширины)
    const translateX = -index * 100;
    sliderContainer.style.transform = `translateX(${translateX}%)`;

    // Обновляем активные линии
    [currentPhoto__line, currentPhoto__line__grey, currentPhoto__line__grey__two].forEach((line, i) => {
        line.src = i === index
            ? "img/header/Line__hover.png"
            : "img/header/Line.png";
    });

    // Убираем активный класс со всех
    slides.forEach(slide => slide.classList.remove('active'));
    // Добавляем активный класс текущему
    slides[index].classList.add('active');

    currentImageIndex = index;
}

// Автоматическое переключение
setInterval(() => {
    currentImageIndex = (currentImageIndex + 1) % totalSlides;
    switchImage(currentImageIndex);
}, 5000);

// Ручное переключение по клику
currentPhoto__line.addEventListener('click', () => switchImage(0));
currentPhoto__line__grey.addEventListener('click', () => switchImage(1));
currentPhoto__line__grey__two.addEventListener('click', () => switchImage(2));

// Инициализация
switchImage(0);