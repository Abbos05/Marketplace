import React, { useState, useMemo } from "react";
import { Head, useForm, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/product_page.css';
import '../../../css/product/NftCreate.css';

export default function NftCreate({ auth, categories }) {
  const [preview, setPreview] = useState(null);

  const { data, setData, post, processing, errors } = useForm({
    title: '',
    description: '',
    image: null,
    price: '',
    tags: '',
    category_id: '',
  });


  const handleImageChange = (e) => {
    const file = e.target.files?.[0] || null;
    setData('image', file);
    if (file) {
      setPreview(URL.createObjectURL(file));
    } else {
      setPreview(null);
    }
  };

  const isFormValid = useMemo(() => {

    if (!data.title.trim() || data.title.length > 100) return false;


    if (!data.image) return false;


    const price = parseFloat(data.price);
    if (isNaN(price) || price <= 0 || price > 9999999999.99) return false;


    if (!data.category_id) return false;

    return true;
  }, [data]);

  // Отправка формы
  const handleSubmit = (e) => {
    e.preventDefault();
    if (!isFormValid) {
      return;
    }

    post('/nft', {
      preserveState: true,
      onSuccess: () => {
        router.visit('/profile?filter=myNfts');
      }
    });
  };

  return (
    <MainLayout>
      <Head title="Создать NFT" />

      <div className="create-nft-wrapper">
        <div className="create-nft-card">
          <h1 className="create-nft-title">Создать NFT</h1>

          <form onSubmit={handleSubmit} className="create-nft-form">


            <div className="create-nft-preview">
              {preview ? (
                <img src={preview} alt="Превью" className="preview-img" />
              ) : (
                <div className="preview-placeholder">
                  <span>Загрузите изображение</span>
                </div>
              )}
            </div>

            <div className="create-nft-field">
              <label>Изображение *</label>
              <input
                type="file"
                accept="image/*"
                onChange={handleImageChange}
                className="file-input"
                disabled={processing}
              />
              {errors.image && <span className="error">{errors.image}</span>}
            </div>


            <div className="create-nft-field">
              <label>Название *</label>
              <input
                type="text"
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder="Мой крутой NFT"
                disabled={processing}
              />
              {errors.title && <span className="error">{errors.title}</span>}
            </div>


            <div className="create-nft-field">
              <label>Описание</label>
              <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows="3"
                maxLength={300}
                placeholder="Расскажите о своём шедевре..."
                disabled={processing}
              />
            </div>

            <div className="create-nft-field">
              <label>Цена (₽) *</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                value={data.price}
                onChange={(e) => setData('price', e.target.value)}
                placeholder="5000"
                disabled={processing}
              />
              {errors.price && <span className="error">{errors.price}</span>}
            </div>

            <div className="create-nft-field">
              <label>Теги</label>
              <input
                type="text"
                value={data.tags}
                onChange={(e) => setData('tags', e.target.value)}
                placeholder="арт, редкий, 3D"
                disabled={processing}
              />
            </div>

            <div className="create-nft-field">
              <label>Категория *</label>
              <select
                value={data.category_id}
                onChange={(e) => setData('category_id', e.target.value)}
                disabled={processing}
              >
                <option value="">Выберите...</option>
                {categories.map(cat => (
                  <option key={cat.id} value={cat.id}>{cat.name}</option>
                ))}
              </select>
              {errors.category_id && <span className="error">{errors.category_id}</span>}
            </div>


            <button
              type="submit"
              disabled={processing || !isFormValid}
              className="create-nft-btn"
            >
              {processing ? 'Создаём...' : 'Создать NFT'}
            </button>

          </form>
        </div>
      </div>
    </MainLayout>
  );
}