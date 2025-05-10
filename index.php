<?php
ob_start(); // Iniciar el búfer de salida
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
}

// Procesar registro (solo cliente)
if (isset($_POST['register'])) {
    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $nombre = isset($_POST['nombre']) ? substr($_POST['nombre'], 0, 50) : null;
    $email = isset($_POST['email']) ? substr($_POST['email'], 0, 50) : null;
    $contrasena = isset($_POST['contrasena']) ? password_hash($_POST['contrasena'], PASSWORD_DEFAULT) : null;
    $tipo_usuario = 'cliente';

    if ($dni && $nombre && $email && $contrasena) {
        $sql = "INSERT INTO Usuario (dni, nombre, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
        $params = [$dni, $nombre, $email, $contrasena, $tipo_usuario];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            header("Location: index.php?error=1");
            exit();
        } else {
            header("Location: index.php?register_success=1");
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        header("Location: index.php?error=2");
        exit();
    }
}

// Procesar login (para cualquier tipo_usuario)
if (isset($_POST['login'])) {
    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : null;

    if ($dni && $contrasena) {
        $sql = "SELECT dni, nombre, email, contrasena, tipo_usuario FROM Usuario WHERE dni = ?";
        $params = [$dni];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if (password_verify($contrasena, $row['contrasena'])) {
                $_SESSION['dni'] = $row['dni'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['tipo_usuario'] = $row['tipo_usuario'];
                header("Location: index.php?login_success=1");
                exit();
            } else {
                header("Location: index.php?error=3");
                exit();
            }
        } else {
            header("Location: index.php?error=4");
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        header("Location: index.php?error=5");
        exit();
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
        <?php if (!isset($_SESSION['dni'])): ?>
            <a href="#" onclick="showForm('login')">Login</a>
            <a href="#" onclick="showForm('register')">Register</a>
        <?php else: ?>
            <a href="#" onclick="showForm('profile')">Perfil (<?php echo $_SESSION['nombre']; ?>)</a>
            <a href="/logout.php">Logout</a>
        <?php endif; ?>
    </nav>
    <div class="container">
        <!-- Formularios de Login y Registro -->
        <?php if (!isset($_SESSION['dni'])): ?>
            <div class="auth-section">
                <?php
                $error = isset($_GET['error']) ? $_GET['error'] : 0;
                $register_success = isset($_GET['register_success']) ? true : false;
                $login_success = isset($_GET['login_success']) ? true : false;
                if ($error == 1) echo "<p style='color:red;'>Error al registrarse. Verifica los datos.</p>";
                if ($error == 2) echo "<p style='color:red;'>Faltan datos en el formulario.</p>";
                if ($error == 3) echo "<p style='color:red;'>Contraseña incorrecta.</p>";
                if ($error == 4) echo "<p style='color:red;'>Usuario no encontrado.</p>";
                if ($error == 5) echo "<p style='color:red;'>Faltan datos para iniciar sesión.</p>";
                if ($register_success) echo "<p style='color:green;'>Registro exitoso. Por favor inicia sesión.</p>";
                ?>
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
            <!-- Mensaje de bienvenida -->
            <div class="welcome-message">
                <?php if (isset($_GET['login_success'])): ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                <?php else: ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                <?php endif; ?>
            </div>

            <!-- Sección de Perfil -->
            <div id="profile-form" class="form-container" style="display: none;">
                <h2>Perfil de Usuario</h2>
                <p><strong>DNI:</strong> <?php echo $_SESSION['dni']; ?></p>
                <p><strong>Nombre:</strong> <?php echo $_SESSION['nombre']; ?></p>
                <p><strong>Email:</strong> <?php echo $_SESSION['email']; ?></p>
                <p><strong>Tipo de Usuario:</strong> <?php echo $_SESSION['tipo_usuario']; ?></p>
            </div>
        <?php endif; ?>
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <script src="/scrip.js" defer></script>
</body>
</html>
<?php
ob_end_flush(); // Finalizar el búfer de salida
?>
