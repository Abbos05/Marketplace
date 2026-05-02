// resources/js/components/Dropdown.js
import React, { useState } from 'react';

const Dropdown = () => {
    const [isOpen, setIsOpen] = useState(false);
    const csrfToken = document.getElementById('app').dataset.csrf; 

    const toggleDropdown = () => {
        setIsOpen(!isOpen);
    };

    const handleLogout = (event) => {
        event.preventDefault(); // Предотвращаем стандартное поведение ссылки
        const form = document.getElementById('logout-form');
        const data = new FormData(form);
        data.append('_token', csrfToken); // Добавляем CSRF-токен

        fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(response => {
            if (response.ok) {
                window.location.reload(); // Перезагрузите страницу после выхода
            } else {
                console.error('Ошибка при выходе');
            }
        });
    };

    return (
        <footer>
        <div class="container">
            <div class="footer__block">
                <div class="footer__block__item">
                    <img src="img/footer.png" alt="" />
                </div>
                <div class="footer__block__item">
                    <ul>
                        <li><a href="">О нас</a></li>
                        <li><a href="">Заказы</a></li>
                        <li><a href="">Обратная связь</a></li>
                    </ul>
                    <ul>
                        <li>© 2025 Фриланс </li>
                    </ul>
                </div>
                <div class="footer__block__item imgfooter">
                        <a href=""><img src="img/footer/image.png" alt="" /></a>
                        <a href=""><img src="img/footer/image 1.png" alt="" /></a>
                </div>
            </div>
        </div>
    </footer>
    );
};

export default Dropdown;
