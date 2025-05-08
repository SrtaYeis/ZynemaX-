<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Entradas de Cine</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #1a1a1a;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        nav {
            background-color: #333;
            padding: 1rem;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin: 0 1rem;
            font-weight: bold;
        }
        nav a:hover {
            color: #ffd700;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        h2 {
            color: #333;
        }
        .section {
            background-color: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        footer {
            background-color: #1a1a1a;
            color: white;
            text-align: center;
            padding: 1rem;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <header>
        <h1>Zynemax+ | Entradas de Cine</h1>
    </header>
    <nav>
        <a href="#cartelera">Cartelera</a>
        <a href="#sede">Sede</a>
        <a href="#login">Login</a>
        <a href="#register">Register</a>
    </nav>
    <div class="container">
        <div class="section" id="cartelera">
            <h2>Cartelera</h2>
            <p>Aquí podrás ver las películas disponibles y sus horarios.</p>
        </div>
        <div class="section" id="sede">
            <h2>Sede</h2>
            <p>Selecciona la sede de tu cine favorito para comprar entradas.</p>
        </div>
        <div class="section" id="login">
            <h2>Login</h2>
            <p>Inicia sesión para acceder a tu cuenta y comprar entradas.</p>
        </div>
        <div class="section" id="register">
            <h2>Register</h2>
            <p>Regístrate para crear una cuenta y disfrutar de Zynemax+.</p>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
</body>
</html>
