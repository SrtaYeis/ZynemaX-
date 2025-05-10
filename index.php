<?php
ob_start(); // Iniciar el búfer de salida
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Procesar registro
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

// Seleccionar sede y función
if (isset($_POST['select_function']) && isset($_SESSION['dni'])) {
    $id_sede = isset($_POST['id_sede']) ? (int)$_POST['id_sede'] : null;
    $id_funcion = isset($_POST['id_funcion']) ? (int)$_POST['id_funcion'] : null;
    if ($id_sede && $id_funcion) {
        $_SESSION['selected_sede'] = $id_sede;
        $_SESSION['selected_funcion'] = $id_funcion;
        header("Location: index.php#select-seat");
        exit();
    } else {
        header("Location: index.php?error=8");
        exit();
    }
}

// Procesar reserva con butaca
if (isset($_POST['make_reservation']) && isset($_SESSION['dni']) && isset($_SESSION['selected_funcion'])) {
    $id_funcion = $_SESSION['selected_funcion'];
    $id_butaca = isset($_POST['id_butaca']) ? (int)$_POST['id_butaca'] : null;
    $dni_usuario = $_SESSION['dni'];

    if ($id_funcion && $id_butaca) {
        $sql = "INSERT INTO Reserva (id_reserva, dni_usuario, fecha_reserva, estado_reserva) VALUES (NEXT VALUE FOR Reserva_seq, ?, GETDATE(), 'pendiente')";
        $params = [$dni_usuario];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            header("Location: index.php?error=6");
            exit();
        } else {
            $sql_get_id = "SELECT SCOPE_IDENTITY() AS id_reserva";
            $stmt_id = sqlsrv_query($conn, $sql_get_id);
            $row = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
            $id_reserva = (int)$row['id_reserva'];

            $sql = "INSERT INTO Reserva_funcion (id_reserva_funcion, id_reserva, id_funcion) VALUES (NEXT VALUE FOR Reserva_funcion_seq, ?, ?)";
            $params = [$id_reserva, $id_funcion];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                header("Location: index.php?error=7");
                exit();
            } else {
                $_SESSION['id_reserva'] = $id_reserva;
                $_SESSION['id_butaca'] = $id_butaca;
                header("Location: index.php#payment");
                exit();
            }
        }
        sqlsrv_free_stmt($stmt);
    } else {
        header("Location: index.php?error=8");
        exit();
    }
}

// Procesar pago y generar boleta
if (isset($_POST['make_payment']) && isset($_SESSION['dni']) && isset($_SESSION['id_reserva'])) {
    $id_reserva = $_SESSION['id_reserva'];
    $metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : null;
    $monto_pago = 10.00; // Monto fijo para simplicidad

    if ($metodo_pago) {
        $sql = "INSERT INTO Pago (id_pago, id_reserva, metodo_pago, monto_pago, fecha_pago, estado_pago) VALUES (NEXT VALUE FOR Pago_seq, ?, ?, ?, GETDATE(), 'completado')";
        $params = [$id_reserva, $metodo_pago, $monto_pago];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            header("Location: index.php?error=9");
            exit();
        } else {
            $sql = "UPDATE Reserva SET estado_reserva = 'confirmada' WHERE id_reserva = ?";
            $params = [$id_reserva];
            $stmt = sqlsrv_query($conn, $sql, $params);

            // Obtener datos para la boleta
            $sql_boleta = "SELECT u.nombre, p.titulo, f.fecha_hora, s.nombre_sala, b.fila, b.numero_butaca, se.ciudad_sede, pa.monto_pago 
                           FROM Reserva r 
                           JOIN Usuario u ON r.dni_usuario = u.dni 
                           JOIN Reserva_funcion rf ON r.id_reserva = rf.id_reserva 
                           JOIN Funcion f ON rf.id_funcion = f.id_funcion 
                           JOIN Pelicula p ON f.id_pelicula = p.id_pelicula 
                           JOIN Sala s ON f.id_sala = s.id_sala 
                           JOIN Sede se ON s.id_sede = se.id_sede 
                           JOIN Butaca b ON b.id_butaca = ? 
                           JOIN Pago pa ON r.id_reserva = pa.id_reserva 
                           WHERE r.id_reserva = ?";
            $params = [$_SESSION['id_butaca'], $id_reserva];
            $stmt_boleta = sqlsrv_query($conn, $sql_boleta, $params);
            $boleta = sqlsrv_fetch_array($stmt_boleta, SQLSRV_FETCH_ASSOC);

            unset($_SESSION['selected_sede']);
            unset($_SESSION['selected_funcion']);
            unset($_SESSION['id_reserva']);
            unset($_SESSION['id_butaca']);
            header("Location: index.php?ticket=1&boleta=" . urlencode(serialize($boleta)));
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        header("Location: index.php?error=10");
        exit();
    }
}

// Obtener cartelera
$cartelera = [];
if ($conn) {
    $sql = "SELECT f.id_funcion, p.titulo, s.nombre_sala, f.fecha_hora, se.ciudad_sede, s.id_sala, se.id_sede 
            FROM Funcion f 
            JOIN Pelicula p ON f.id_pelicula = p.id_pelicula 
            JOIN Sala s ON f.id_sala = s.id_sala 
            JOIN Sede se ON s.id_sede = se.id_sede";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $cartelera[] = $row;
        }
    }
    sqlsrv_free_stmt($stmt);
}

// Obtener butacas disponibles
$butacas = [];
if (isset($_SESSION['selected_funcion'])) {
    $id_funcion = $_SESSION['selected_funcion'];
    $sql = "SELECT b.id_butaca, b.fila, b.numero_butaca 
            FROM Butaca b 
            JOIN Sala s ON b.id_sala = s.id_sala 
            JOIN Funcion f ON f.id_sala = s.id_sala 
            WHERE f.id_funcion = ? 
            AND b.id_butaca NOT IN (
                SELECT rf.id_funcion 
                FROM Reserva_funcion rf 
                JOIN Reserva r ON rf.id_reserva = r.id_reserva 
                WHERE rf.id_funcion = ? AND r.estado_reserva = 'confirmada'
            )";
    $params = [$id_funcion, $id_funcion];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $butacas[] = $row;
        }
    }
    sqlsrv_free_stmt($stmt);
}

// Obtener sedes
$sedes = [];
if ($conn) {
    $sql = "SELECT id_sede, ciudad_sede, direccion_sede FROM Sede";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sedes[] = $row;
        }
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
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <?php if (!isset($_SESSION['dni'])): ?>
        <div class="auth-section">
            <h2>Iniciar Sesión</h2>
            <?php
            $error = isset($_GET['error']) ? $_GET['error'] : 0;
            $register_success = isset($_GET['register_success']) ? true : false;
            if ($error == 1) echo "<p style='color:red;'>Error al registrarse. Verifica los datos o intenta con otro DNI.</p>";
            if ($error == 2) echo "<p style='color:red;'>Faltan datos en el formulario. Completa todos los campos.</p>";
            if ($error == 3) echo "<p style='color:red;'>Contraseña incorrecta.</p>";
            if ($error == 4) echo "<p style='color:red;'>Usuario no encontrado.</p>";
            if ($error == 5) echo "<p style='color:red;'>Faltan datos para iniciar sesión.</p>";
            if ($error == 11) echo "<p style='color:red;'>El DNI ya está registrado. Usa otro DNI.</p>";
            if ($register_success) echo "<p style='color:green;'>Registro exitoso. Por favor inicia sesión.</p>";
            ?>
            <div id="login-form" class="form-container">
                <form method="POST">
                    <input type="number" name="dni" placeholder="DNI" required>
                    <input type="password" name="contrasena" placeholder="Contraseña" required>
                    <button type="submit" name="login">Iniciar Sesión</button>
                </form>
                <button onclick="showForm('register')">Registrarse</button>
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
        <header>
            <h1>Zynemax+ | Tu Cine Favorito</h1>
        </header>
        <nav>
            <a href="#cartelera">Cartelera</a>
            <a href="#sedes">Sedes</a>
            <a href="/logout.php">Logout (<?php echo $_SESSION['nombre']; ?>)</a>
        </nav>
        <div class="container">
            <div class="welcome-message">
                <?php if (isset($_GET['login_success'])): ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?></h2>
                <?php elseif (isset($_GET['ticket']) && isset($_GET['boleta'])): ?>
                    <div class="ticket-section">
                        <h2>Boleta de Reserva</h2>
                        <?php
                        $boleta = unserialize(urldecode($_GET['boleta']));
                        if ($boleta) {
                            echo "<p>Nombre: " . htmlspecialchars($boleta['nombre']) . "</p>";
                            echo "<p>Película: " . htmlspecialchars($boleta['titulo']) . "</p>";
                            echo "<p>Fecha y Hora: " . htmlspecialchars($boleta['fecha_hora']->format('Y-m-d H:i')) . "</p>";
                            echo "<p>Sala: " . htmlspecialchars($boleta['nombre_sala']) . "</p>";
                            echo "<p>Asiento: Fila " . htmlspecialchars($boleta['fila']) . " - " . htmlspecialchars($boleta['numero_butaca']) . "</p>";
                            echo "<p>Sede: " . htmlspecialchars($boleta['ciudad_sede']) . "</p>";
                            echo "<p>Monto Pagado: $" . htmlspecialchars($boleta['monto_pago']) . "</p>";
                            echo "<p>Fecha de Pago: " . date('Y-m-d H:i:s') . "</p>";
                        } else {
                            echo "<p>Error al generar la boleta.</p>";
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?></h2>
                    <p>Explora la cartelera y reserva tu entrada.</p>
                <?php endif; ?>
            </div>

            <!-- Sección Cartelera -->
            <div class="section" id="cartelera">
                <h2>Cartelera</h2>
                <?php if (!empty($cartelera)): ?>
                    <?php foreach ($cartelera as $funcion): ?>
                        <div class="movie-item">
                            <h3><?php echo htmlspecialchars($funcion['titulo']); ?></h3>
                            <p>Sala: <?php echo htmlspecialchars($funcion['nombre_sala']); ?></p>
                            <p>Fecha y Hora: <?php echo $funcion['fecha_hora']->format('Y-m-d H:i'); ?></p>
                            <p>Sede: <?php echo htmlspecialchars($funcion['ciudad_sede']); ?></p>
                            <?php if (isset($_SESSION['dni'])): ?>
                                <form method="POST">
                                    <input type="hidden" name="id_sede" value="<?php echo $funcion['id_sede']; ?>">
                                    <input type="hidden" name="id_funcion" value="<?php echo $funcion['id_funcion']; ?>">
                                    <button type="submit" name="select_function">Seleccionar</button>
                                </form>
                            <?php else: ?>
                                <p>Debes iniciar sesión para seleccionar.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay funciones disponibles en este momento.</p>
                <?php endif; ?>
            </div>

            <!-- Sección Selección de Butaca -->
            <?php if (isset($_SESSION['selected_funcion'])): ?>
                <div class="section" id="select-seat">
                    <h2>Seleccionar Asiento</h2>
                    <?php
                    $sql_sala = "SELECT nombre_sala FROM Sala WHERE id_sala = (SELECT id_sala FROM Funcion WHERE id_funcion = ?)";
                    $params = [$_SESSION['selected_funcion']];
                    $stmt_sala = sqlsrv_query($conn, $sql_sala, $params);
                    $sala = sqlsrv_fetch_array($stmt_sala, SQLSRV_FETCH_ASSOC);
                    ?>
                    <p>Número de Sala: <?php echo htmlspecialchars($sala['nombre_sala']); ?></p>
                    <?php if (!empty($butacas)): ?>
                        <form method="POST">
                            <label for="id_butaca">Elige tu asiento:</label>
                            <select name="id_butaca" required>
                                <?php foreach ($butacas as $butaca): ?>
                                    <option value="<?php echo $butaca['id_butaca']; ?>">
                                        Fila <?php echo htmlspecialchars($butaca['fila']); ?> - Asiento <?php echo htmlspecialchars($butaca['numero_butaca']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="make_reservation">Reservar</button>
                        </form>
                    <?php else: ?>
                        <p>No hay butacas disponibles para esta función.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Sección Pago -->
            <?php if (isset($_SESSION['id_reserva'])): ?>
                <div class="section" id="payment">
                    <h2>Procesar Pago</h2>
                    <p>Monto a pagar: $10.00</p>
                    <form method="POST">
                        <label for="metodo_pago">Método de Pago:</label>
                        <select name="metodo_pago" required>
                            <option value="tarjeta">Tarjeta de Crédito</option>
                            <option value="efectivo">Efectivo</option>
                        </select>
                        <button type="submit" name="make_payment">Pagar</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Sección Sedes -->
            <div class="section" id="sedes">
                <h2>Sedes</h2>
                <?php if (!empty($sedes)): ?>
                    <?php foreach ($sedes as $sede): ?>
                        <div class="sede-item">
                            <h3><?php echo htmlspecialchars($sede['ciudad_sede']); ?></h3>
                            <p>Dirección: <?php echo htmlspecialchars($sede['direccion_sede']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay sedes disponibles en este momento.</p>
                <?php endif; ?>
            </div>
        </div>
        <footer>
            <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
        </footer>
    <?php endif; ?>
    <script src="/script.js" defer></script>
</body>
</html>
<?php
ob_end_flush(); // Finalizar el búfer de salida
?>
