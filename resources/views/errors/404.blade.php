<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized</title>
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}"> <!-- Ссылка на CSS -->
</head>

<body>
    <div class="container">
        <h1>404</h1>
        <h2>Уппс!</h2>
        <p>Нет такой страницы вернитесь на главную страницу</p>
        <a href="{{ route('home')}}">Главная страница</a>
    </div>
</body>

</html>

<style>
    body {
        background-color: #0e1424;
        font-family: sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        max-height: 100vh;
    }
::-webkit-scrollbar{
    display: none;
}
    .container {
        text-align: center;
        padding:40px;
        border: 1px solid #1c2533;
        backdrop-filter: blur(50px);
        border-radius: 15px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    h1 {
        font-size: 5em;
        color: red;
        margin-bottom: 0;
    }

    h2 {
        font-size: 2em;
        color: white;
        margin-top: 0;
    }

    h2>span {
        color: green;
    }

    p {
        font-size: 1.2em;
        color: grey;
    }

    a {
        color: #3498db;
        text-decoration: none;
        font-weight: var(--fw-700);
    }

    a:hover {
        text-decoration: underline;
    }
</style>