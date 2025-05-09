<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Conexión a la base de datos
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
    die("<pre>Conexión fallida: " . print_r(sqlsrv_errors(), true) . "</pre>");
} else {
    echo "<!-- Conexión a la base de datos exitosa -->";
}

// Procesar registro (solo cliente)
if (isset($_POST['register'])) {
    echo "<!-- Datos recibidos del formulario: " . print_r($_POST, true) . " -->";

    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $nombre = isset($_POST['nombre']) ? substr($_POST['nombre'], 0, 50) : null;
    $email = isset($_POST['email']) ? substr($_POST['email'], 0, 50) : null;
    $contrasena = isset($_POST['contrasena']) ? password_hash($_POST['contrasena'], PASSWORD_DEFAULT) : null;
    $tipo_usuario = 'cliente'; // Fijo como cliente

    if ($dni && $nombre && $email && $contrasena) {
        $sql = "INSERT INTO Usuario (dni, nombre, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
        $params = [$dni, $nombre, $email, $contrasena, $tipo_usuario];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            echo "<script>alert('Error al registrarse: " . print_r(sqlsrv_errors(), true) . "');</script>";
        } else {
            header("Location: index.php");
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        echo "<script>alert('Faltan datos en el formulario.');</script>";
    }
}

// Procesar login (para cualquier tipo_usuario)
if (isset($_POST['login'])) {
    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : null;

    if ($dni && $contrasena) {
        $sql = "SELECT dni, nombre, contrasena, tipo_usuario FROM Usuario WHERE dni = ?";
        $params = [$dni];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if (password_verify($contrasena, $row['contrasena'])) {
                $_SESSION['dni'] = $row['dni'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['tipo_usuario'] = $row['tipo_usuario'];
                header("Location: index.php");
                exit();
            } else {
                echo "<script>alert('Contraseña incorrecta.');</script>";
            }
        } else {
            echo "<script>alert('Usuario no encontrado.');</script>";
        }
        sqlsrv_free_stmt($stmt);
    } else {
        echo "<script>alert('Faltan datos para iniciar sesión.');</script>";
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Plataforma de Cine</title>
    <link rel="stylesheet" href="/style.css">
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
            <a href="/logout.php">Logout (<?php echo $_SESSION['nombre']; ?>)</a>
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
                        <input type="text" name="nombre" placeholder="Nombre" required maxlength="50">
                        <input type="email" name="email" placeholder="Correo" required maxlength="50">
                        <input type="password" name="contrasena" placeholder="Contraseña" required>
                        <button type="submit" name="register">Registrarse</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="welcome-message">
                <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
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
    <script src="/scrip.js" defer></script>
</body>
</html>
