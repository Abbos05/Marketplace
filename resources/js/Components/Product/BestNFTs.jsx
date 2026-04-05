// src/components/BestNFTs.jsx
import React from 'react';
import '../../../css/product/BestNFTs.css';
import { router } from '@inertiajs/react';

const BestNFTs = ({ nftsData = [] }) => {
  // Если данных нет
  if (nftsData.length === 0) {
    return (
      <section className="bestNFT" id='bestNFT'>
        <div className="container">Нет данных о NFT</div>
      </section>
    );
  }

  return (
    <section className="bestNFT">
      <div className="container">
        <h2 className="bestTitle">
          Лучшие подборки <span>за 24 часа</span>
        </h2>
        <div className="bestNFT_block">
          {nftsData.map((nft, index) => (
            <div key={nft.id} className="bestNFT_item" onClick={() => router.visit(`/nft/${nft.id}`)}>
              <div className="bestNFT_item_left">
                <span>{index + 1}</span>
                <div className="bestNFT_img_block">
                  <img src={nft.image ?? '/img/nft/default.jpg'} alt={nft.title} />
                  <img src="/img/profiles/check.png" alt="check" />
                </div>
                <div className="bestNFT_title">
                  <p title={nft.title}>{nft.title}</p>
                  <p>
                    Владелец: <span title={nft.user?.name}>{nft.user?.name}</span>
                  </p>
                </div>
              </div>
              <div className="bestNFT_cash">
                <p title={` Старая цена: ${nft.previous_price}₽`}
                  style={{ color: nft.percentage < 0 ? 'red' : '#24bd5e' }}
                >
                  {nft.percentage}%
                </p>
                <p title={nft.price}>{nft.price}₽</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default BestNFTs;

