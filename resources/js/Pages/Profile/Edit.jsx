import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useForm, router, Head } from '@inertiajs/react';
import { useState } from 'react';
import '../../../css/profile/editProfile.css';

export default function Edit({ auth, mustVerifyEmail, status }) {
  const { user } = auth;
  const [activeTab, setActiveTab] = useState(0); // 0 - Общие, 1 - Профиль
  const [preview, setPreview] = useState(user.img || '/img/service/users/avatar.svg');

  // Форма для общих настроек (email, пароль)
  const { data: generalData, setData: setGeneralData, patch, put, errors: generalErrors } = useForm({
    email: user.email,
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  // Форма для профиля (имя, профессия, фото)
  const { data: profileData, setData: setProfileData, post, processing, errors: profileErrors } = useForm({
    name: user.name || '',
    profession: user.profession || '',
    country: user.country || '',
    city: user.city || '',
    description: user.description || '',
    img: null,
  });

  // Обработчик переключения вкладок
  const handleTabClick = (index) => {
    setActiveTab(index);
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setProfileData('img', file);
      setPreview(URL.createObjectURL(file));
    } else {
      setProfileData('img', null);
      setPreview(user.img || '/img/service/users/avatar.svg');
    }
  };

  const handleProfileSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('name', profileData.name);
    formData.append('profession', profileData.profession);
    formData.append('country', profileData.country);
    formData.append('city', profileData.city);
    formData.append('description', profileData.description);
    if (profileData.img) {
      formData.append('img', profileData.img); 
    }
    formData.append('_method', 'PATCH'); 

    console.log('Отправляем данные профиля:', Object.fromEntries(formData));

    post(route('profile.update'), {
      data: formData,
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        console.log('Профиль обновлен');
        setProfileData('img', null); // Сбрасываем поле img после успешного сохранения
        setPreview(user.img || '/img/service/users/avatar.svg'); // Возвращаем предпросмотр к текущему изображению
      },
      onError: (errors) => {
        console.error('Ошибка обновления:', errors);
      },
    });
  };

  // Обработка отправки формы общих настроек
  const handleGeneralSubmit = async (e) => {
    e.preventDefault();
    try {
      // Обновление email
      await patch(route('profile.email.update'), {
        email: generalData.email,
      }, {
        preserveState: true,
      });

      // Обновление пароля, если заполнен
      if (generalData.password) {
        if (!generalData.current_password) {
          throw new Error('Текущий пароль обязателен для смены пароля');
        }
        await put(route('password.update'), {
          current_password: generalData.current_password,
          password: generalData.password,
          password_confirmation: generalData.password_confirmation,
        }, {
          preserveState: true,
        });
      }

      console.log('Общие настройки успешно сохранены');
    } catch (error) {
      console.error('Ошибка сохранения:', error);
      console.log(error.message || 'Ошибка сохранения данных');
    }
  };

  // Обработка удаления аккаунта
  const handleDeleteAccount = () => {
    if (!generalData.current_password) {
      console.log('Введите текущий пароль для удаления аккаунта');
      return;
    }
    if (confirm('Вы уверены, что хотите удалить аккаунт?')) {
      router.delete(route('profile.destroy'), {
        data: { password: generalData.current_password },
        onSuccess: () => {
          console.log('Аккаунт удалён');
        },
        onError: (errors) => {
          console.error('Ошибка удаления:', errors);
          console.log('Ошибка удаления аккаунта: ' + JSON.stringify(errors));
        },
      });
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Редактирование" />
      <div className="py-12">
        <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
          <div className="profileEdit">
            <h1 className="text-xl font-semibold leading-tight text-gray-800">
              Редактирование профиля
            </h1>
            <div className="profile__edit">
              <div className="profile__edit__header">
                <ul className="flex">
                  <li
                    className={`flex-1 text-center py-2 px-4 cursor-pointer ${activeTab === 0 ? 'bg-green-900 text-green-500' : ''}`}
                    onClick={() => handleTabClick(0)}
                  >
                    Общие
                  </li>
                  <li
                    className={`flex-1 text-center py-2 px-4 cursor-pointer ${activeTab === 1 ? 'bg-green-900 text-green-500' : ''}`}
                    onClick={() => handleTabClick(1)}
                  >
                    Профиль
                  </li>
                </ul>
              </div>

              <div className="profile__edit__data">
                {/* Общие настройки */}
                {activeTab === 0 && (
                  <form onSubmit={handleGeneralSubmit}>
                    <div className="profile__edit__data__item">
                      <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          id="email"
                          value={generalData.email}
                          onChange={(e) => setGeneralData('email', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {generalErrors.email && <p className="text-red-600 text-sm mt-2">{generalErrors.email}</p>}
                      </div>

                      <div>
                        <label htmlFor="current_password" className="block text-sm font-medium text-gray-700">
                          Текущий пароль (только если меняете пароль)
                        </label>
                        <input
                          type="password"
                          name="current_password"
                          id="current_password"
                          value={generalData.current_password}
                          onChange={(e) => setGeneralData('current_password', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {generalErrors.current_password && <p className="text-red-600 text-sm mt-2">Не верный пароль</p>}
                      </div>

                      <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                          Новый пароль
                        </label>
                        <input
                          type="password"
                          name="password"
                          id="password"
                          value={generalData.password}
                          onChange={(e) => setGeneralData('password', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {generalErrors.password && <p className="text-red-600 text-sm mt-2">Подтверждение поля пароля не совпадает или длина менее 4 символов.</p>}
                      </div>

                      <div>
                        <label htmlFor="password_confirm" className="block text-sm font-medium text-gray-700">
                          Подтвердить пароль
                        </label>
                        <input
                          type="password"
                          name="password_confirm"
                          id="password_confirm"
                          value={generalData.password_confirmation}
                          onChange={(e) => setGeneralData('password_confirmation', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {generalErrors.password_confirmation && <p className="text-red-600 text-sm mt-2">Подтверждение поля пароля не совпадает</p>}
                      </div>

                      <div className="btn">
                        <button
                          type="button"
                          onClick={handleDeleteAccount}
                          className="text-sm text-red-600 mr-4"
                        >
                          Удалить
                        </button>
                        <button
                          type="submit"
                          className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-white hover:bg-green-700"
                        >
                          Сохранить
                        </button>
                      </div>
                    </div>
                  </form>
                )}

                {/* Профиль */}
                {activeTab === 1 && (
                  <form onSubmit={handleProfileSubmit} encType="multipart/form-data">
                    <div className="profile__edit__data__item">
                      <div className='avatar'>
                        <label className="block text-sm font-medium text-gray-700">
                          Фото профиля
                        </label>
                        <div className='profile__edit__data__itemImg'>
                          <img
                            id="image1"
                            src={preview}
                            alt="Avatar"
                          />
                          <label
                            htmlFor="file1"
                            className="buttonplus"
                          >
                            +
                          </label>
                          <input
                            type="file"
                            id="file1"
                            name="img"
                            style={{ display: 'none' }}
                            accept="image/*"
                            onChange={handleFileChange}
                          />
                        </div>
                        {profileErrors.img && <p className="text-red-600 text-sm mt-2">{profileErrors.img}</p>}
                      </div>

                      <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                          Ваше имя
                        </label>
                        <input
                          type="text"
                          name="name"
                          id="name"
                          value={profileData.name}
                          onChange={(e) => setProfileData('name', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {profileErrors.name && <p className="text-red-600 text-sm mt-2">{profileErrors.name}</p>}
                      </div>

                      <div>
                        <label htmlFor="profession" className="block text-sm font-medium text-gray-700">
                          Ваша профессия
                        </label>
                        <input
                          type="text"
                          name="profession"
                          id="profession"
                          value={profileData.profession}
                          onChange={(e) => setProfileData('profession', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {profileErrors.profession && <p className="text-red-600 text-sm mt-2">{profileErrors.profession}</p>}
                      </div>

                      <div>
                        <label htmlFor="country" className="block text-sm font-medium text-gray-700">
                          Страна проживания
                        </label>
                        <input
                          type="text"
                          name="country"
                          id="country"
                          value={profileData.country}
                          onChange={(e) => setProfileData('country', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {profileErrors.country && <p className="text-red-600 text-sm mt-2">{profileErrors.country}</p>}
                      </div>

                      <div>
                        <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                          Город (не обязательно)
                        </label>
                        <input
                          type="text"
                          name="city"
                          id="city"
                          value={profileData.city}
                          onChange={(e) => setProfileData('city', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        {profileErrors.city && <p className="text-red-600 text-sm mt-2">{profileErrors.city}</p>}
                      </div>

                      <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                          Описание
                        </label>
                        <textarea
                          name="description"
                          id="description"
                          rows="4"
                          value={profileData.description}
                          onChange={(e) => setProfileData('description', e.target.value)}
                          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        ></textarea>
                        {profileErrors.description && <p className="text-red-600 text-sm mt-2">{profileErrors.description}</p>}
                      </div>

                      <div className="btn">
                        <button
                          type="submit"
                          disabled={processing}
                          className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                        >
                          Сохранить
                        </button>
                      </div>
                    </div>
                  </form>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}