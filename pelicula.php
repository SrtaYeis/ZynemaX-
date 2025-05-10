<?php
ob_start(); // Iniciar el búfer de salida
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Conexión a la base de datos
$serverName = "database-zynemaxplus-server.database.windows.net";
$connectionInfo = [
    "Database" => "ZynemaxDB",
    "UID" => "zynemaxplus",
    "PWD" => "grupo2_1al10",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    die("<pre>Conexión fallida: " . print_r(sqlsrv_errors(), true) . "</pre>");
}

// Verificar que el usuario esté logueado
if (!isset($_SESSION['dni'])) {
    header("Location: index.php?error=6");
    exit();
}

// Procesar selección de película
if (isset($_POST['select_movie'])) {
    $_SESSION['selected_movie'] = $_POST['movie_id'];
    header("Location: pelicula.php?step=sede");
    exit();
}

// Procesar selección de sede
if (isset($_POST['select_sede'])) {
    $_SESSION['selected_sede'] = $_POST['sede_id'];
    header("Location: pelicula.php?step=sala");
    exit();
}

// Procesar selección de sala
if (isset($_POST['select_sala'])) {
    $_SESSION['selected_sala'] = $_POST['sala_id'];
    header("Location: pelicula.php?step=butaca");
    exit();
}

// Procesar selección de butaca
if (isset($_POST['select_butaca'])) {
    $_SESSION['selected_butaca'] = $_POST['butaca_id'];
    header("Location: pelicula.php?step=reserve");
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

    // Obtener el ID de la reserva
    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva = $row['id_reserva'];

    // Obtener el id_funcion basado en la película y sala seleccionadas
    $sql = "SELECT id_funcion FROM Funcion WHERE id_pelicula = ? AND id_sala = ?";
    $params = [$_SESSION['selected_movie'], $_SESSION['selected_sala']];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $id_funcion = $row['id_funcion'];
    } else {
        die("Error: No se encontró una función para la película y sala seleccionadas.");
    }
    sqlsrv_free_stmt($stmt);

    // Vincular la reserva con la función
    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $id_funcion];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
    }

    $_SESSION['reservation_id'] = $id_reserva;
    $_SESSION['function_id'] = $id_funcion; // Guardar para usar en el resumen
    header("Location: pelicula.php?step=payment");
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
    unset($_SESSION['function_id']);
    header("Location: pelicula.php?payment_success=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Películas</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <header>
        <h1>Zynemax+ | Tu Cine Favorito</h1>
    </header>
    <nav>
        <a href="/index.php">Inicio</a>
        <a href="/pelicula.php">Películas</a>
        <a href="/logout.php">Logout</a>
    </nav>
    <div class="container">
        <div class="welcome-message">
            <?php if (isset($_GET['payment_success'])): ?>
                <h2 style='color:green;'>¡Pago realizado con éxito!</h2>
            <?php else: ?>
                <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
            <?php endif; ?>
        </div>

        <!-- Sección de Películas -->
        <?php if (!isset($_GET['step']) || $_GET['step'] === 'movies'): ?>
            <div id="movies-form" class="form-container">
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
                } else {
                    echo "<p>Error al cargar las películas: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                ?>
            </div>
        <?php endif; ?>

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
                } else {
                    echo "<p>Error al cargar las sedes: " . print_r(sqlsrv_errors(), true) . "</p>";
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
                } else {
                    echo "<p>Error al cargar las salas: " . print_r(sqlsrv_errors(), true) . "</p>";
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
                } else {
                    echo "<p>Error al cargar las butacas: " . print_r(sqlsrv_errors(), true) . "</p>";
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
                        JOIN Butaca b ON b.id_butaca = ? 
                        WHERE f.id_funcion = ?";
                $params = [$_SESSION['selected_butaca'], $_SESSION['function_id']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    echo "<p><strong>Película:</strong> " . $row['titulo'] . "</p>";
                    echo "<p><strong>Sede:</strong> " . $row['ciudad_sede'] . "</p>";
                    echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . $row['fila'] . ", Número " . $row['numero_butaca'] . "</p>";
                } else {
                    echo "<p>Error al cargar el resumen: " . print_r(sqlsrv_errors(), true) . "</p>";
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
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <script src="/script.js" defer></script>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
