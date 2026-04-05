import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import NFTCard from '@/Components/Product/ProductCard';
import '../../../css/profile/style.css';

// ← Все модалки
import ProfileEditModal from '@/Components/Profile/ProfileEditModal';
import PhoneVerificationModal from '@/Components/Profile/PhoneVerificationModal';
import BlockedAccountModal from '@/Components/Profile/BlockedAccountModal';
import DeleteAccountModal from '@/Components/Profile/DeleteAccountModal';

// ← Вынесенная логика
import useProfileLogic from '@/Components/Profile/useProfileLogic';

export default function Profile({ auth, nfts: initialNfts, activeFilter }) {
  const { message, setMessage, formatName } = useProfileLogic({ auth, initialNfts });

  // Состояния модалок
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isPhoneOpen, setIsPhoneOpen] = useState(false);
  const [isBlockedOpen, setIsBlockedOpen] = useState(auth.user.is_blocked === 1);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);

  // Показываем модалку блокировки при загрузке, если аккаунт заблокирован
  useEffect(() => {
    if (auth.user.is_blocked === 1) {
      setIsBlockedOpen(true);
    }
  }, [auth.user.is_blocked]);

  return (
    <MainLayout>
      <Head title="Профиль" />

      <div className="container">
        <section className="profiles">
          <div className="profiles_block__all">
            {/* Основной блок профиля */}
            <div className={`profiles_block ${auth.user.is_blocked === 1 && 'isBlockAcrive'}`}>
              <img
                className="profiles_avatar"
                src={auth.user.avatar || '/img/profiles/profile.png'}
                alt="avatar"
              />
              <h1 className="profiles_name" title={auth.user.name}>
                {formatName(auth.user.name)}
                {auth.user.phone && <img src="/img/profiles/check.png" alt="Верифицирован" className="icon" />}
              </h1>
              <p className="profiles_description">
                {auth.user.description || 'Описание о вас отсутствует.'}
              </p>
            </div>

            {/* Кнопки действий */}
            {!auth.user.phone && auth.user.is_blocked === 0 && (
              <div className="myVerify__block">
                <h4 onClick={() => setIsPhoneOpen(true)} className="myVerify">
                  Пройти верификацию
                </h4>
              </div>
            )}

            <div className="myBlock__block">
              <h4
                onClick={() => (auth.user.is_blocked === 1 ? setIsBlockedOpen(true) : setIsEditOpen(true))}
                className={`myVerify ${auth.user.is_blocked === 1 && 'isBlockAcrive'}`}
              >
                Редактировать профиль
              </h4>
            </div>

            {auth.user?.role !== 'admin' && (
              <div className="myBlock__block">
                <h4 onClick={() => setIsDeleteOpen(true)} className="myVerify">
                  Удалить аккаунт
                </h4>
              </div>
            )}
          </div>
        </section>

        {/* Фильтры и кнопки */}
        <div className="nfts_header">
          <ul className="profiles_list">
            <li><Link href={route('profile.filter')} className={activeFilter == null ? 'list_active' : ''}>Активные</Link></li>
            <li><Link href={`${route('profile.filter')}?filter=myNfts`} className={activeFilter === 'myNfts' ? 'list_active' : ''}>Мои NFT</Link></li>
            <li><Link href={`${route('profile.filter')}?filter=myHistory`} className={activeFilter === 'myHistory' ? 'list_active' : ''}>История покупки</Link></li>
            <li><Link href={`${route('profile.filter')}?filter=myCheck`} className={activeFilter === 'myCheck' ? 'list_active' : ''}>На проверке</Link></li>
            <li><Link href={`${route('profile.filter')}?filter=myFavorites`} className={activeFilter === 'myFavorites' ? 'list_active' : ''}>Избранные</Link></li>
            <li><Link href={`${route('profile.filter')}?filter=myCart`} className={activeFilter === 'myCart' ? 'list_active' : ''}>Корзина</Link></li>
          </ul>

          <div className="nfts_controls">
            <ul className="profiles_btn">
              <li>
                <Link
                  href={auth.user.is_blocked === 0 && auth.user.phone ? route('nft.create') : '/profile'}
                  onClick={(e) => {
                    if (!(auth.user.is_blocked === 0 && auth.user.phone)) {
                      e.preventDefault();
                      sessionStorage.setItem('flashMessage', 'Пройдите верификацию или разблокируйте аккаунт');
                      window.location.href = '/profile';
                    }
                  }}
                  className="HeaderWallet"
                >
                  <p>Добавить NFT</p>
                </Link>
              </li>

              {auth.user.role === 'admin' && (
                <li className="HeaderWallet" onClick={() => router.visit('/admins')}>
                  Управление пользователями
                </li>
              )}
            </ul>
          </div>
        </div>

        {/* Уведомление */}
        {message && (
          <div className="alert" onClick={() => setMessage(null)}>
            {message}
          </div>
        )}

        {/* Список NFT или история */}
        <div className="nfts_block">
          {initialNfts?.length > 0 ? (
            activeFilter === 'myHistory' ? (
              <div className="history-table-container">
                <table className="history-table">
                  <thead>
                    <tr>
                      <th>Тип</th><th>NFT</th><th>Клиент</th><th>Цена</th><th>Статус</th><th>Дата</th><th>Отчет</th>
                    </tr>
                  </thead>
                  <tbody>
                    {initialNfts.map((tx) => (
                      <tr key={tx.id} className={tx.type === 'buy' ? 'row-buy' : 'row-sell'}>
                        <td><span className={`tag ${tx.type === 'buy' ? 'tag-buy' : 'tag-sell'}`}>
                          {tx.type === 'buy' ? 'Купил' : 'Продал'}
                        </span></td>
                        <td><div className="nft-info"><img src={tx.nft.image} alt="" /><span>{tx.nft.title}</span></div></td>
                        <td><div className="user-info"><img src={tx.counterparty?.avatar || '/img/profiles/profile.png'} alt="" />
                          <span>{tx.counterparty?.trashed ? 'Удалён' : tx.counterparty?.name}</span></div></td>
                        <td className="price">{tx.price} ₽</td>
                        <td className="price">{tx.status}</td>
                        <td>{new Date(tx.created_at).toLocaleDateString('ru-RU')}</td>
                        <td>
                          <a href={`/download-certificate/${tx.id}`} className="download-pdf-btn" target="_blank">
                            Скачать
                          </a>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              initialNfts.map((nft) => <NFTCard key={nft.id} nft={nft} />)
            )
          ) : (
            <p className="nftNones">Пока ничего нет</p>
          )}
        </div>
      </div>

      {/* Все модалки */}
      <ProfileEditModal isOpen={isEditOpen} onClose={() => setIsEditOpen(false)} auth={auth} />
      <PhoneVerificationModal isOpen={isPhoneOpen} onClose={() => setIsPhoneOpen(false)} auth={auth} />
      <BlockedAccountModal isOpen={isBlockedOpen} onClose={() => setIsBlockedOpen(false)} />
      <DeleteAccountModal isOpen={isDeleteOpen} onClose={() => setIsDeleteOpen(false)} />
    </MainLayout>
  );
}