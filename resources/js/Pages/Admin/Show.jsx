import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { router } from '@inertiajs/react';
import '../../../css/profile/admin.css';
import NFTCard from '@/Components/Product/ProductCard';


export default function AdminUserShow({ auth, user, nfts = [] }) {
  const deleteNft = (nftId) => {
    if (confirm('Удалить NFT?')) {
      router.delete(route('admin.nfts.destroy', nftId), {
        preserveScroll: true,
      });
    }
  };

  const toggleBlockUser = (userId) => {
    if (confirm('Вы уверены, что хотите изменить статус блокировки этого пользователя?')) {

      router.put(route('admin.users.block', userId), {
        preserveScroll: true,
        onSuccess: () => {
          console.log('Статус блокировки изменен');
          router.reload({ only: ['users'] }); // Перезагружает users
        },
        onError: (errors) => {
          console.error('Ошибка при изменении статуса:', errors);
        },
      });
    }
  };
  return (
    <AuthenticatedLayout>
      <Head title={`Пользователь: ${user.name}`} />

      <div className="container showUser__Block">
        <section className="profiles ">
          <div className="profiles_block__all">
            {/* === ОСНОВНОЙ БЛОК ПОЛЬЗОВАТЕЛЯ === */}
            <div className={`profiles_block ${user.is_blocked == 1 && "isBlockAcrive"}`}>
              <img
                className="profiles_avatar"
                src={user.avatar || '/admin/img/avatar.svg'}
                alt="avatar"
              />

              <h1 className="profiles_name" title={user.name}>
                {user.name.length > 25 ? user.name.substring(0, 25) + '...' : user.name}
                {user.phone && (
                  <img src="/img/profiles/check.png" alt="Верифицирован" className="icon" />
                )}
              </h1>
              <p className="profiles_description">
                ID: {user.id} | Роль: <strong>{user.role}</strong>
              </p>
            </div>

            {/* === ИНФОРМАЦИЯ === */}
            <div className="myBlock__block">
              <h4 className={`myVerify ${user.is_blocked == 1 ? 'isBlockAcrive' : ''}`}>
                Статус: {user.is_blocked ? 'Заблокирован' : 'Активен'}
              </h4>
            </div>
            <div className="myBlock__block">
              <h4 className="myVerify">Email: <span className="profiles_description">{user.email || 'Не указано'}</span></h4>
            </div>
            <div className="myBlock__block">
              <h4 className="myVerify">Телефон: <span className="profiles_description">{user.phone || 'Не указано'}</span></h4>
            </div>

            <div className="myBlock__block">
              <h4 className="myVerify">
                Создан: <span className="profiles_description">
                  {new Date(user.created_at).toLocaleDateString('ru-RU')}
                </span>
              </h4>
            </div>

            <div className="myBlock__block">
              <h4 className="myVerify"
                onClick={() => toggleBlockUser(user.id)}
              >
                {user.is_blocked ? 'Разблокировать' : 'Заблокировать'}
              </h4>
            </div>
            <div className="myBlock__block">
              <h4 className="myVerify" onClick={() => router.visit('/admins')}>
                Назад к списку
              </h4>
            </div>
          </div>
        </section>

        {/* === NFT ПОЛЬЗОВАТЕЛЯ === */}
        <div className="nfts_header">
          <h2 className="profiles_name" style={{ margin: '20px 0 10px' }}>
            NFT пользователя
          </h2>
        </div>

        <div className="nfts_block">
          {nfts.length > 0 ? (nfts.map(nft => <NFTCard key={nft.id} nft={nft} />)) : (
            <p className="nftNones">У пользователя нет NFT</p>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}