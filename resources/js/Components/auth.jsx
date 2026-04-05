export default auth(){
    return (
        { isModalOpen && (
            <div className="modal__overlay">
                <div className="modal__content">
                    <button
                        className="modal__close"
                        onClick={() => setIsModalOpen(false)}
                    >
                        ×
                    </button>
                    <div className="modal__tabs">
                        <button
                            className={`tab__button ${isLogin ? 'active' : ''}`}
                            onClick={() => setIsLogin(true)}
                        >
                            Вход
                        </button>
                        <button
                            className={`tab__button ${!isLogin ? 'active' : ''}`}
                            onClick={() => setIsLogin(false)}
                        >
                            Регистрация
                        </button>
                    </div>

                    {isLogin ? (
                        <form onSubmit={handleLoginSubmit}>
                            <div>
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={loginData.email}
                                    className="mt-1 block w-full"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setLoginData('email', e.target.value)}
                                />
                                <InputError message={loginErrors.email} className="mt-2" />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="password" value="Пароль" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={loginData.password}
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setLoginData('password', e.target.value)}
                                />
                                <InputError message={loginErrors.password} className="mt-2" />
                            </div>

                            <div className="mt-4 block">
                                <label className="flex items-center">
                                    <Checkbox
                                        name="remember"
                                        checked={loginData.remember}
                                        onChange={(e) => setLoginData('remember', e.target.checked)}
                                    />
                                    <span className="ms-2 text-sm text-gray-600">
                                        Запомнить меня
                                    </span>
                                </label>
                            </div>

                            <div className="mt-4 flex items-center justify-between">
                            
                                <PrimaryButton disabled={loginProcessing}>
                                    Войти
                                </PrimaryButton>
                            </div>
                        </form>
                    ) : (
                        <form onSubmit={handleRegisterSubmit}>
                            <div>
                                <InputLabel htmlFor="name" value="Имя" />
                                <TextInput
                                    id="name"
                                    name="name"
                                    value={registerData.name}
                                    className="mt-1 block w-full"
                                    autoComplete="name"
                                    isFocused={true}
                                    onChange={(e) => setRegisterData('name', e.target.value)}
                                    required
                                />
                                <InputError message={registerErrors.name} className="mt-2" />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={registerData.email}
                                    className="mt-1 block w-full"
                                    autoComplete="username"
                                    onChange={(e) => setRegisterData('email', e.target.value)}
                                    required
                                />
                                <InputError message={registerErrors.email} className="mt-2" />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="password" value="Пароль" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={registerData.password}
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    onChange={(e) => setRegisterData('password', e.target.value)}
                                    required
                                />
                                <InputError message={registerErrors.password} className="mt-2" />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="password_confirmation" value="Подтверждение пароля" />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={registerData.password_confirmation}
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    onChange={(e) => setRegisterData('password_confirmation', e.target.value)}
                                    required
                                />
                                <InputError message={registerErrors.password_confirmation} className="mt-2" />
                            </div>

                            <div className="mt-4 flex items-center justify-between">
                                <Link
                                    href={route('login')}
                                    className="text-sm text-gray-600 underline hover:text-gray-900"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        setIsLogin(true);
                                    }}
                                >
                                    Уже зарегистрированы?
                                </Link>
                                <PrimaryButton disabled={registerProcessing}>
                                    Зарегистрироваться
                                </PrimaryButton>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        )}
    );
            }