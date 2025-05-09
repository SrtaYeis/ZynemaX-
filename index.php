<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Conexión a la base de datos con los valores proporcionados
$serverName = "database-zynemaxplus-server.database.windows.net";
$connectionInfo = [
    "Database" => "database-zynemaxplus-server",
    "UID" => "zynemaxplus",
    "PWD" => "grupo2_1al10",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Procesar registro
if (isset($_POST['register'])) {
    $dni = $_POST['dni'];
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT); // Encriptar contraseña
    $tipo_usuario = $_POST['tipo_usuario'];

    $sql = "INSERT INTO Usuario (dni, nombre, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
    $params = [$dni, $nombre, $email, $contrasena, $tipo_usuario];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<script>alert('Error al registrarse: " . print_r(sqlsrv_errors(), true) . "');</script>";
    } else {
        echo "<script>alert('Registro exitoso. Por favor inicia sesión.');</script>";
    }
    sqlsrv_free_stmt($stmt);
}

// Procesar login
if (isset($_POST['login'])) {
    $dni = $_POST['dni'];
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT dni, contrasena, tipo_usuario FROM Usuario WHERE dni = ?";
    $params = [$dni];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if (password_verify($contrasena, $row['contrasena'])) {
            $_SESSION['dni'] = $row['dni'];
            $_SESSION['tipo_usuario'] = $row['tipo_usuario'];
            echo "<script>alert('Login exitoso. Bienvenido!');</script>";
        } else {
            echo "<script>alert('Contraseña incorrecta.');</script>";
        }
    } else {
        echo "<script>alert('Usuario no encontrado.');</script>";
    }
    sqlsrv_free_stmt($stmt);
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Plataforma de Cine</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Zynemax+ | Tu Cine Favorito</h1>
    </header>
    <nav>
        <a href="#cartelera">Cartelera</a>
        <a href="#sedes">Sedes</a>
        <?php if (!isset($_SESSION['dni'])): ?>
            <a href="#" onclick="showForm('login')">Login</a>
            <a href="#" onclick="showForm('register')">Register</a>
        <?php else: ?>
            <a href="logout.php">Logout (DNI: <?php echo $_SESSION['dni']; ?>)</a>
        <?php endif; ?>
    </nav>
    <div class="container">
        <!-- Formularios de Login y Registro -->
        <?php if (!isset($_SESSION['dni'])): ?>
            <div class="auth-section">
                <div id="login-form" class="form-container" style="display: none;">
                    <h2>Iniciar Sesión</h2>
                    <form method="POST">
                        <input type="number" name="dni" placeholder="DNI" required>
                        <input type="password" name="contrasena" placeholder="Contraseña" required>
                        <button type="submit" name="login">Iniciar Sesión</button>
                    </form>
                </div>
                <div id="register-form" class="form-container" style="display: none;">
                    <h2>Registrarse</h2>
                    <form method="POST">
                        <input type="number" name="dni" placeholder="DNI" required>
                        <input type="text" name="nombre" placeholder="Nombre" required>
                        <input type="email" name="email" placeholder="Correo" required>
                        <input type="password" name="contrasena" placeholder="Contraseña" required>
                        <select name="tipo_usuario" required>
                            <option value="cliente">Cliente</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="submit" name="register">Registrarse</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="welcome-message">
                <h2>Bienvenido, <?php echo $_SESSION['dni']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                <p>Explora la cartelera y reserva tus entradas.</p>
            </div>
        <?php endif; ?>

        <!-- Sección Cartelera -->
        <div class="section" id="cartelera">
            <h2>Cartelera</h2>
            <p>Próximamente: Lista de películas disponibles.</p>
        </div>

        <!-- Sección Sedes -->
        <div class="section" id="sedes">
            <h2>Sedes</h2>
            <p>Próximamente: Selecciona tu cine favorito.</p>
        </div>
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <script src="script.js"></script>
</body>
</html>
