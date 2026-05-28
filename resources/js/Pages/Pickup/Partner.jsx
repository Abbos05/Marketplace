import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/pickup/cooperate.css';

const STEPS = [
    { n: 1, title: 'Анкета', text: 'Заполните данные организации и помещения пункта выдачи' },
    { n: 2, title: 'Проверка', text: 'Администрация проверяет заявку в течение нескольких дней' },
    { n: 3, title: 'Пункт на карте', text: 'После одобрения пункт появляется в каталоге и открывается панель оператора' },
    { n: 4, title: 'Выдача и отчёты', text: 'Принимайте заказы, выдавайте покупателям, получайте вознаграждение по отчётам' },
];

export default function Partner({ auth, pendingApplication = null, needsVerification = false }) {
    const { flash, errors } = usePage().props;
    const role = auth?.user?.role;
    const roleBlocked = role && ['seller', 'admin', 'moderator', 'pvz'].includes(role);

    return (
        <MainLayout auth={auth}>
            <Head title="Партнёрство ПВЗ" />

            <div className="pickup-cooperate pickup-partner">
                <h1>Партнёрство: пункт выдачи ALVORA</h1>
                <p className="pickup-cooperate__lead">
                    Откройте официальный пункт выдачи маркетплейса. Мы проверяем каждую заявку — как на Ozon Pickup:
                    полные данные организации, адрес и помещение.
                </p>

                {errors?.form && <div className="pickup-cooperate__flash pickup-cooperate__flash--err">{errors.form}</div>}

                <div className="pickup-partner__steps">
                    {STEPS.map((s) => (
                        <div key={s.n} className="pickup-partner__step">
                            <span className="pickup-partner__step-n">{s.n}</span>
                            <div>
                                <strong>{s.title}</strong>
                                <p>{s.text}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {!auth?.user && (
                    <div className="pickup-cooperate__card">
                        <p>Войдите в аккаунт, чтобы подать заявку.</p>
                        <Link href="/login" className="pickup-cooperate__btn">Войти</Link>
                    </div>
                )}

                {auth?.user && needsVerification && (
                    <div className="pickup-cooperate__card pickup-cooperate__card--pending">
                        <h2>Сначала пройдите верификацию профиля</h2>
                        <p>
                            Укажите имя, email и подтвердите телефон в профиле — без этого заявку на пункт выдачи
                            подать нельзя (как и для заказов и продавца).
                        </p>
                        <Link href="/profile" className="pickup-cooperate__btn">Перейти в профиль</Link>
                    </div>
                )}

                {auth?.user && roleBlocked && role !== 'user' && !needsVerification && (
                    <div className="pickup-cooperate__card pickup-cooperate__card--pending">
                        <h3>Другая роль на аккаунте</h3>
                        <p>
                            {role === 'seller' && 'Вы уже зарегистрированы как продавец. Продавец и оператор ПВЗ — разные роли: завершите работу компании или используйте отдельный аккаунт.'}
                            {role === 'pvz' && 'У вас уже открыт пункт выдачи. Управление — в панели ПВЗ.'}
                            {(role === 'admin' || role === 'moderator') && (
                                <>
                                    Для сотрудников платформы заявка на ПВЗ недоступна. Создайте пункт и назначьте оператора в разделе «Пункты выдачи» панели администратора.
                                </>
                            )}
                        </p>
                        {role === 'pvz' && <Link href="/pvz" className="pickup-cooperate__btn">Панель ПВЗ</Link>}
                        {role === 'seller' && <Link href="/profile" className="pickup-cooperate__btn">Мой профиль</Link>}
                        {(role === 'admin' || role === 'moderator') && (
                            <Link href="/admin/pickup-points" className="pickup-cooperate__btn">Пункты выдачи (админ)</Link>
                        )}
                    </div>
                )}

                {auth?.user && pendingApplication && (
                    <div className="pickup-cooperate__card pickup-cooperate__card--pending">
                        <h2>Заявка на рассмотрении</h2>
                        <p><strong>{pendingApplication.proposed_title}</strong></p>
                        <p>{pendingApplication.proposed_address}</p>
                        {pendingApplication.legal_name && <p>Организация: {pendingApplication.legal_name}</p>}
                        {pendingApplication.inn && <p>ИНН: {pendingApplication.inn}</p>}
                        <p className="pickup-cooperate__muted">
                            Отправлена: {new Date(pendingApplication.created_at).toLocaleString('ru-RU')}
                        </p>
                    </div>
                )}

                {auth?.user && !pendingApplication && !needsVerification && role === 'user' && (
                    <div className="pickup-cooperate__card">
                        <p>Готовы начать? Заполните полную анкету партнёра — без неё заявка не рассматривается.</p>
                        <Link href="/pickup/apply" className="pickup-cooperate__btn">Подать заявку</Link>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
