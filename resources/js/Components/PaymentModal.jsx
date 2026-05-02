import { useState, useEffect } from 'react';
import axios from 'axios';
import '../../css/product/PaymentModal.css';

export default function PaymentModal({ isOpen, onClose, nft, user }) {
    const [step, setStep] = useState('method');
    const [method, setMethod] = useState('');
    const [selectedBank, setSelectedBank] = useState('');
    const [cardNumber, setCardNumber] = useState('');
    const [topupAmount, setTopupAmount] = useState('');
    const [message, setMessage] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const banks = [
        { id: 'sber', name: 'Сбер', icon: 'sber' },
        { id: 'alfa', name: 'Альфа-Банк', icon: 'alfa' },
        { id: 'tinkoff', name: 'Т-Банк', icon: 'tinkoff' },
        { id: 'vtb', name: 'ВТБ', icon: 'vtb' },
    ];

    useEffect(() => {
        if (!isOpen) {
            setStep('method');
            setMethod('');
            setSelectedBank('');
            setCardNumber('');
            setMessage('');
        }
    }, [isOpen]);

    const handleWalletPay = async () => {
        if (user.balance < nft.price) {
            setStep('topup');
            setMessage('Недостаточно средств. Пополните кошелёк.');
            return;
        }

        setIsProcessing(true);
        setMessage('Оплата с кошелька...');

        try {
            const res = await axios.post('/api/payment/wallet', {
                product_id: nft.id,
            });
            setMessage('Оплата прошла успешно!');
            setStep('success');
        } catch (err) {
            setMessage('Ошибка оплаты');
            setStep('fail');
        } finally {
            setIsProcessing(false);
        }
    };

    const handleCardPay = async () => {
        if (!cardNumber || cardNumber.length < 16) {
            setMessage('Введите корректный номер карты');
            return;
        }

        setIsProcessing(true);
        setMessage('Оплата картой...');

        setTimeout(() => {
            setMessage('Оплата прошла успешно!');
            setStep('success');
            setIsProcessing(false);
        }, 2500);
    };

    const handleTopup = async () => {
        if (!topupAmount || topupAmount <= 0) return;

        setIsProcessing(true);
        setMessage('Пополнение...');

        setTimeout(() => {
            setMessage(`Кошелёк пополнен на ${topupAmount} ₽`);
            setStep('success');
            setIsProcessing(false);
        }, 2000);
    };

    useEffect(() => {
        console.log('PaymentModal: isOpen changed to', isOpen);
    }, [isOpen]);
    if (!isOpen || !nft || !user) return null;
    return (
        <div
            className="modal-overlay"
            onClick={(e) => {
                if (e.target === e.currentTarget) {
                    onClose();
                }
            }}
        >
            <div className="modal-content">
                <div className="modal-header">
                    <div className="bank-logo">
                        <div className="logo-circle"></div>
                    </div>
                    <button className="close-btn" onClick={onClose}>×</button>
                </div>

                {step === 'method' && (
                    <div className="payment-method">
                        <h3>Сумма к оплате</h3>
                        <div className="amount">{nft.price} ₽</div>

                        <div className="methods">
                            <button
                                className={`method-btn ${method === 'wallet' ? 'selected' : ''}`}
                                onClick={() => setMethod('wallet')}
                            >
                                <span className="icon wallet-icon"></span>
                                <div>
                                    <div>Кошелёк</div>
                                    <small>{user.balance} ₽</small>
                                </div>
                            </button>

                            <button
                                className={`method-btn ${method === 'card' ? 'selected' : ''}`}
                                onClick={() => setMethod('card')}
                            >
                                <span className="icon card-icon"></span>
                                <div>
                                    <div>Банковская карта</div>
                                    <small>Visa, Мир, Mastercard</small>
                                </div>
                            </button>
                        </div>

                        <button
                            className="pay-btn"
                            disabled={!method || isProcessing}
                            onClick={() => method === 'wallet' ? handleWalletPay() : setStep('bank')}
                        >
                            {isProcessing ? 'Обработка...' : 'Оплатить'}
                        </button>
                    </div>
                )}

                {step === 'bank' && (
                    <div className="bank-select">
                        <h3>Выберите банк</h3>
                        <div className="banks-grid">
                            {banks.map(bank => (
                                <button
                                    key={bank.id}
                                    className={`bank-btn ${selectedBank === bank.id ? 'selected' : ''}`}
                                    onClick={() => setSelectedBank(bank.id)}
                                >
                                    <span className={`bank-icon ${bank.icon}`}></span>
                                    <span>{bank.name}</span>
                                </button>
                            ))}
                        </div>

                        {selectedBank && (
                            <div className="card-input">
                                <input
                                    type="text"
                                    placeholder="Номер карты"
                                    value={cardNumber}
                                    onChange={e => setCardNumber(e.target.value.replace(/\D/g, '').slice(0, 16))}
                                    maxLength="16"
                                />
                            </div>
                        )}

                        <button
                            className="pay-btn"
                            disabled={!selectedBank || !cardNumber || isProcessing}
                            onClick={handleCardPay}
                        >
                            {isProcessing ? 'Оплата...' : 'Оплатить картой'}
                        </button>
                    </div>
                )}

                {step === 'topup' && (
                    <div className="topup">
                        <h3>Пополнение кошелька</h3>
                        <input
                            type="number"
                            placeholder="Сумма"
                            value={topupAmount}
                            onChange={e => setTopupAmount(e.target.value)}
                        />
                        <button className="pay-btn" onClick={handleTopup} disabled={isProcessing}>
                            {isProcessing ? 'Пополнение...' : 'Пополнить'}
                        </button>
                    </div>
                )}

                {(step === 'success' || step === 'fail') && (
                    <div className="result">
                        <div className={`status ${step}`}>
                            <span className="icon"></span>
                            <p>{message}</p>
                        </div>
                        <button className="done-btn" onClick={onClose}>
                            Готово
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}