import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';

export default function NftEdit({ auth, nft }) {
  const [form, setForm] = useState({
    price: nft.price || '',
    description: nft.description || '',
  });

  const handleSave = () => {
    router.put(route('nft.update', nft.id), {
      price: form.price,
      description: form.description,
      status: 'relevant',
    }, {
      preserveState: true,
      onSuccess: () => {
        router.visit(route('nft.show', nft.id));
      },
    });
  };

  const handleCancel = () => {
    router.visit(route('nft.show', nft.id));
  };

  return (
    <MainLayout auth={auth}>
      <Head title={`Редактировать: ${nft.title}`} />

      <div className="edit-nft-wrapper">
        <div className="edit-nft-card">

          <h1 className="edit-nft-title">Редактировать NFT</h1>
          <div className="edit-nft-image">
            <img src={nft.image} alt={nft.title} />
          </div>

          <div className="edit-nft-form">

            <div className="edit-nft-field">
              <label>Цена (₽)</label>
              <input
                type="number"
                value={form.price}
                onChange={e => setForm({ ...form, price: e.target.value })}
                placeholder="5000"
                min="0"
                step="0.01"
              />
            </div>

            <div className="edit-nft-field">
              <label>Описание</label>
              <textarea
                value={form.description}
                onChange={e => setForm({ ...form, description: e.target.value })}
                placeholder="Расскажите, почему этот NFT особенный..."
                maxLength={150}
              />
            </div>

            <div className="edit-nft-actions">
              <button onClick={handleSave} className="edit-nft-btn edit-nft-btn-primary">
                Опубликовать на продажу
              </button>
              <button onClick={handleCancel} className="edit-nft-btn edit-nft-btn-secondary">
                Отмена
              </button>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}