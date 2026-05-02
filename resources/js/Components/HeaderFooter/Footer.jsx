import React from 'react';
import {Link} from '@inertiajs/react';
const Footer = () => {
  const handleSubmit = (e) => {
    e.preventDefault();
    console.log('Подписка отправлена');
  };

  return (
    <footer className="footer">
      <div className="container">
        <div className="footer-block">
          <div className="footer-info">
            <br />
            <div className="footer-logo">
              <Link href="/" className="header-logo-name">
                <svg class="logo" viewBox="0 0 400 100" width="100%" height="100%">
                  <text x="50%" y="62%" text-anchor="middle" dominant-baseline="middle"
                    fill="currentColor">
                    Alvora
                  </text>
                </svg>
              </Link>
            </div>
            <div className="footer-info-item">
              <p className="footer-info-course">Оставайтесь в курсе</p>
              <p className="footer-info-course-info">
                Подпишитесь на нашу рассылку, чтобы быть в курсе наших новейших выпусков функций, обновлений товара, а также
                советов и рекомендаций по навигации в ALVORA.
              </p>
              <form onSubmit={handleSubmit}>
                <div className="footer-form-course">
                  <img src="/img/footer/mail.png" alt="mail" />
                  <input type="email" placeholder="Введите адрес эл. почты" />
                  <button type="submit">Отправить</button>
                </div>
                <div className="footer-social">
                  <a href="#">
                    <img src="/img/footer/Vk.png" alt="VK" />
                  </a>
                  <a href="#">
                    <img src="/img/footer/telegram.png" alt="telegram" />
                  </a>
                  <a href="#">
                    <img src="/img/footer/discord.png" alt="discord" />
                  </a>
                  <a href="#">
                    <img src="/img/footer/max.png" alt="max" />
                  </a>
                </div>
              </form>
            </div>
          </div>
          <div className="footer-link">
            <div className="footer-link-category">
              <div className="footer-link-category-item">
                <p>Категория</p>
                <a href={`/category/1`}>NFT Art</a>
                <a href={`/category/2`}>Sports Memorabilia</a>
                <a href={`/category/3`}>Collectibles</a>
                <a href={`/category/4`}>Digital Photography</a>
                <a href={`/category/5`}>Domain Names</a>
                <a href={`/category/6`}>Virtual Fashion</a>
                <a href={`/category/7`}>Game Assets</a>
                <a href={`/category/8`}>Music & Audio</a>
                <a href={`/category/9`}>Metaverse Real Estate</a>
                <a href={`/category/10`}>AI Generations</a>
              </div>
              <div className="footer-link-category-item">
                <p>Профиль</p>
                <a href="/profile">Профиль</a>
                <a href="/profiles?filter=myFavorites">Избранное</a>
                <a href="/profiles?filter=myNfts">Мои nft</a>
                <a href="/profiles?filter=myHistory">История покупики</a>
              </div>
              <div className="footer-link-category-item">
                <p>Ресурсы</p>
                <a href="#">Помощь</a>
                <a href="#">Достижение</a>
                <a href="#">Партнерство</a>
              </div>
              <div className="footer-link-category-item">
                <p>О компании</p>
                <a href="/about">О проекте</a>
                <a href="/contacts">Контакты</a>
              </div>
            </div>
          </div>
        </div>
        <div className="footer-security">
          <div className="footer-security-info">
            <p>©2025 ALVORA Inc</p>
          </div>
          <div className="footer-security-link">
            <a href="#">Политика безопасности</a>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;