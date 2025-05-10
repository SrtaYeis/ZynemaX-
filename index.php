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

// Procesar login
if (isset($_POST['login'])) {
    echo "Procesando login..."; // Depuración
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
                echo "Login exitoso, redirigiendo..."; // Depuración
                header("Location: index.php?login_success=1");
                exit();
            } else {
                echo "Contraseña incorrecta"; // Depuración
                header("Location: index.php?error=3");
                exit();
            }
        } else {
            echo "Usuario no encontrado"; // Depuración
            header("Location: index.php?error=4");
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        echo "Faltan datos"; // Depuración
        header("Location: index.php?error=5");
        exit();
    }
}

// Procesar selección de película
if (isset($_POST['select_movie'])) {
    $_SESSION['selected_movie'] = $_POST['movie_id'];
    header("Location: index.php?step=sede");
    exit();
}

// Procesar selección de sede
if (isset($_POST['select_sede'])) {
    $_SESSION['selected_sede'] = $_POST['sede_id'];
    header("Location: index.php?step=sala");
    exit();
}

// Procesar selección de sala
if (isset($_POST['select_sala'])) {
    $_SESSION['selected_sala'] = $_POST['sala_id'];
    header("Location: index.php?step=butaca");
    exit();
}

// Procesar selección de butaca
if (isset($_POST['select_butaca'])) {
    $_SESSION['selected_butaca'] = $_POST['butaca_id'];
    header("Location: index.php?step=reserve");
    exit();
}

// Procesar reserva
if (isset($_POST['confirm_reservation'])) {
    $dni_usuario = $_SESSION['dni'];
    $fecha_reserva = date('Y-m-d H:i:s');
    $estado_reserva = 'activa';

    $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva, estado_reserva) VALUES (?, ?, ?)";
    $params = [$dni_usuario, $fecha_reserva, $estado_reserva];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al crear reserva: " . print_r(sqlsrv_errors(), true));
    }

    $id_reserva = sqlsrv_get_id($conn); // Simulación de obtención del ID autoincremental
    $id_funcion = 13; // Usar la primera función como ejemplo (ajustar según lógica real)

    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $id_funcion];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
    }

    $_SESSION['reservation_id'] = $id_reserva;
    header("Location: index.php?step=payment");
    exit();
}

// Procesar pago (simulación)
if (isset($_POST['process_payment'])) {
    $id_reserva = $_SESSION['reservation_id'];
    $metodo_pago = $_POST['payment_method'];
    $monto_pago = 10.00; // Monto fijo para simulación
    $fecha_pago = date('Y-m-d H:i:s');
    $estado_pago = 'completado';

    $sql = "INSERT INTO Pago (id_reserva, metodo_pago, monto_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?, ?)";
    $params = [$id_reserva, $metodo_pago, $monto_pago, $fecha_pago, $estado_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al registrar pago: " . print_r(sqlsrv_errors(), true));
    }

    unset($_SESSION['selected_movie']);
    unset($_SESSION['selected_sede']);
    unset($_SESSION['selected_sala']);
    unset($_SESSION['selected_butaca']);
    unset($_SESSION['reservation_id']);
    header("Location: index.php?payment_success=1");
    exit();
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
            <a href="#" onclick="showForm('movies')">Películas</a>
            <a href="/logout.php">Logout</a>
        <?php endif; ?>
    </nav>
    <div class="container">
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
            <div class="welcome-message">
                <?php if (isset($_GET['login_success'])): ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                <?php elseif (isset($_GET['payment_success'])): ?>
                    <h2 style='color:green;'>¡Pago realizado con éxito!</h2>
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

            <!-- Sección de Películas -->
            <div id="movies-form" class="form-container" style="display: none;">
                <h2>Selecciona una Película</h2>
                <?php
                $sql = "SELECT * FROM Pelicula";
                $stmt = sqlsrv_query($conn, $sql);
                if ($stmt) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<form method='POST' style='margin: 10px 0;'>";
                        echo "<input type='hidden' name='movie_id' value='" . $row['id_pelicula'] . "'>";
                        echo "<p><strong>Título:</strong> " . $row['titulo'] . "</p>";
                        echo "<p><strong>Sinopsis:</strong> " . $row['sinopsis'] . "</p>";
                        echo "<p><strong>Duración:</strong> " . $row['duracion'] . " min</p>";
                        echo "<p><strong>Clasificación:</strong> " . $row['clasificacion'] . "</p>";
                        echo "<p><strong>Fecha Estreno:</strong> " . $row['fecha_estreno']->format('Y-m-d') . "</p>";
                        echo "<button type='submit' name='select_movie'>Seleccionar</button>";
                        echo "</form>";
                    }
                    sqlsrv_free_stmt($stmt);
                }
                ?>
            </div>

            <!-- Sección de Sedes -->
            <?php if (isset($_GET['step']) && $_GET['step'] === 'sede' && isset($_SESSION['selected_movie'])): ?>
                <div class="form-container">
                    <h2>Selecciona una Sede</h2>
                    <?php
                    $sql = "SELECT * FROM Sede";
                    $stmt = sqlsrv_query($conn, $sql);
                    if ($stmt) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='sede_id' value='" . $row['id_sede'] . "'>";
                            echo "<p><strong>Ciudad:</strong> " . $row['ciudad_sede'] . "</p>";
                            echo "<p><strong>Dirección:</strong> " . $row['direccion_sede'] . "</p>";
                            echo "<button type='submit' name='select_sede'>Seleccionar</button>";
                            echo "</form>";
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Sección de Salas -->
            <?php if (isset($_GET['step']) && $_GET['step'] === 'sala' && isset($_SESSION['selected_sede'])): ?>
                <div class="form-container">
                    <h2>Selecciona una Sala</h2>
                    <?php
                    $sql = "SELECT * FROM Sala WHERE id_sede = ?";
                    $params = [$_SESSION['selected_sede']];
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    if ($stmt) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='sala_id' value='" . $row['id_sala'] . "'>";
                            echo "<p><strong>Nombre:</strong> " . $row['nombre_sala'] . "</p>";
                            echo "<button type='submit' name='select_sala'>Seleccionar</button>";
                            echo "</form>";
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Sección de Butacas -->
            <?php if (isset($_GET['step']) && $_GET['step'] === 'butaca' && isset($_SESSION['selected_sala'])): ?>
                <div class="form-container">
                    <h2>Selecciona una Butaca</h2>
                    <?php
                    $sql = "SELECT * FROM Butaca WHERE id_sala = ?";
                    $params = [$_SESSION['selected_sala']];
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    if ($stmt) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='butaca_id' value='" . $row['id_butaca'] . "'>";
                            echo "<p><strong>Fila:</strong> " . $row['fila'] . " <strong>Número:</strong> " . $row['numero_butaca'] . "</p>";
                            echo "<button type='submit' name='select_butaca'>Seleccionar</button>";
                            echo "</form>";
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Sección de Reserva -->
            <?php if (isset($_GET['step']) && $_GET['step'] === 'reserve' && isset($_SESSION['selected_butaca'])): ?>
                <div class="form-container">
                    <h2>Confirmar Reserva</h2>
                    <p>¿Deseas reservar la butaca seleccionada?</p>
                    <form method="POST">
                        <button type="submit" name="confirm_reservation">Reservar</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Sección de Pago -->
            <?php if (isset($_GET['step']) && $_GET['step'] === 'payment' && isset($_SESSION['reservation_id'])): ?>
                <div class="form-container">
                    <h2>Realizar Pago</h2>
                    <p>Resumen de tu reserva:</p>
                    <?php
                    $sql = "SELECT p.titulo, s.ciudad_sede, sa.nombre_sala, b.fila, b.numero_butaca 
                            FROM Pelicula p 
                            JOIN Funcion f ON p.id_pelicula = f.id_pelicula 
                            JOIN Sala sa ON f.id_sala = sa.id_sala 
                            JOIN Sede s ON sa.id_sede = s.id_sede 
                            JOIN Butaca b ON b.id_sala = sa.id_sala 
                            WHERE f.id_funcion = 13"; // Ajustar con ID de función real
                    $stmt = sqlsrv_query($conn, $sql);
                    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<p><strong>Película:</strong> " . $row['titulo'] . "</p>";
                        echo "<p><strong>Sede:</strong> " . $row['ciudad_sede'] . "</p>";
                        echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                        echo "<p><strong>Butaca:</strong> Fila " . $row['fila'] . ", Número " . $row['numero_butaca'] . "</p>";
                    }
                    sqlsrv_free_stmt($stmt);
                    ?>
                    <form method="POST">
                        <select name="payment_method" required>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                        <button type="submit" name="process_payment">Pagar</button>
                    </form>
                </div>
            <?php endif; ?>
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
