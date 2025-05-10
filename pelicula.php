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

// Validación 1: Verificar que el usuario esté logueado
if (!isset($_SESSION['dni'])) {
    header("Location: index.php?error=6");
    exit();
}

// Procesar selección de película
if (isset($_POST['select_movie'])) {
    $movie_id = $_POST['movie_id'];
    
    // Validación 2: Verificar que la película tenga una función asociada
    $sql = "SELECT f.id_funcion, f.id_sala, s.nombre_sala 
            FROM Funcion f 
            JOIN Sala s ON f.id_sala = s.id_sala 
            WHERE f.id_pelicula = ? AND f.fecha_hora > ?";
    $params = [$movie_id, date('Y-m-d H:i:s')];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['selected_movie'] = $movie_id;
        $_SESSION['selected_sala'] = $row['id_sala'];
        $_SESSION['sala_name'] = $row['nombre_sala'];
        $_SESSION['function_id'] = $row['id_funcion'];
        header("Location: pelicula.php?step=butaca");
        exit();
    } else {
        echo "<p style='color:red;'>No hay funciones disponibles para esta película en el futuro. Por favor, seleccione otra película o contacte al administrador.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);
}

// Procesar selección de butaca
if (isset($_POST['select_butaca'])) {
    $butaca_id = $_POST['butaca_id'];
    
    // Validación 3: Verificar que el asiento exista
    $sql = "SELECT id_butaca FROM Butaca WHERE id_butaca = ?";
    $params = [$butaca_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['selected_butaca'] = $butaca_id;
        header("Location: pelicula.php?step=reserve");
        exit();
    } else {
        echo "<p style='color:red;'>El asiento seleccionado no existe. Por favor, elija otro.</p>";
        echo "<a href='pelicula.php?step=butaca'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);
}

// Procesar reserva
if (isset($_POST['confirm_reservation'])) {
    // Validación 4: Verificar que todos los datos necesarios estén presentes
    if (!isset($_SESSION['selected_movie']) || !isset($_SESSION['selected_sala']) || !isset($_SESSION['selected_butaca']) || !isset($_SESSION['function_id'])) {
        echo "<p style='color:red;'>Error: Faltan datos para completar la reserva. Por favor, reinicie el proceso.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $dni_usuario = $_SESSION['dni'];
    $fecha_reserva = date('Y-m-d H:i:s');

    // Insertar en Reserva
    $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva) VALUES (?, ?)";
    $params = [$dni_usuario, $fecha_reserva];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al crear reserva: " . print_r(sqlsrv_errors(), true));
    }

    // Obtener el ID de la reserva
    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva = $row['id_reserva'];

    // Insertar en Reserva_funcion
    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $_SESSION['function_id']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
    }

    // Obtener el ID de Reserva_funcion
    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva_funcion";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva_funcion = $row['id_reserva_funcion'];

    // Insertar en Reserva_butaca
    $sql = "INSERT INTO Reserva_butaca (id_reserva_funcion, id_butaca) VALUES (?, ?)";
    $params = [$id_reserva_funcion, $_SESSION['selected_butaca']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true));
    }

    $_SESSION['reservation_id'] = $id_reserva;
    $_SESSION['reserva_funcion_id'] = $id_reserva_funcion;
    header("Location: pelicula.php?step=comprobante");
    exit();
    sqlsrv_free_stmt($stmt);
}

// Procesar pago
if (isset($_POST['process_payment'])) {
    if (!isset($_SESSION['reserva_funcion_id'])) {
        echo "<p style='color:red;'>Error: No se encontró una reserva para procesar el pago.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $id_reserva_funcion = $_SESSION['reserva_funcion_id'];
    $metodo_pago = $_POST['payment_method'];
    $fecha_pago = date('Y-m-d H:i:s');
    $estado_pago = 'completado';

    // Obtener el precio de la película
    $sql = "SELECT p.precio FROM Pelicula p JOIN Funcion f ON p.id_pelicula = f.id_pelicula WHERE f.id_funcion = ?";
    $params = [$_SESSION['function_id']];
    $stmt = sqlsrv_query($conn, $sql, $params);
    $monto_pago = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row['precio'] : 10.00; // Usar precio de la película o 10.00 por defecto
    sqlsrv_free_stmt($stmt);

    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?)";
    $params = [$id_reserva_funcion, $metodo_pago, $fecha_pago, $estado_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error al registrar pago: " . print_r(sqlsrv_errors(), true));
    }

    unset($_SESSION['selected_movie']);
    unset($_SESSION['selected_sala']);
    unset($_SESSION['sala_name']);
    unset($_SESSION['selected_butaca']);
    unset($_SESSION['function_id']);
    unset($_SESSION['reservation_id']);
    unset($_SESSION['reserva_funcion_id']);
    header("Location: pelicula.php?payment_success=1");
    exit();
    sqlsrv_free_stmt($stmt);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Películas</title>
    <link rel="stylesheet" href="/style.css">
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
                $sql = "SELECT DISTINCT p.id_pelicula, p.titulo, p.duracion, p.clasificacion, p.fecha_estreno, p.precio
                        FROM Pelicula p 
                        JOIN Funcion f ON p.id_pelicula = f.id_pelicula
                        WHERE f.fecha_hora > ?";
                $params = [date('Y-m-d H:i:s')];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='movie_id' value='" . $row['id_pelicula'] . "'>";
                            echo "<p><strong>Título:</strong> " . $row['titulo'] . "</p>";
                            echo "<p><strong>Duración:</strong> " . $row['duracion'] . " min</p>";
                            echo "<p><strong>Clasificación:</strong> " . $row['clasificacion'] . "</p>";
                            echo "<p><strong>Fecha Estreno:</strong> " . $row['fecha_estreno']->format('Y-m-d') . "</p>";
                            echo "<p><strong>Precio:</strong> S/. " . number_format($row['precio'], 2) . "</p>";
                            echo "<button type='submit' name='select_movie'>Seleccionar</button>";
                            echo "</form>";
                        }
                    } else {
                        echo "<p>No hay películas con funciones disponibles en el futuro.</p>";
                    }
                    sqlsrv_free_stmt($stmt);
                } else {
                    echo "<p>Error al cargar las películas: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Sección de Butacas -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'butaca' && isset($_SESSION['selected_sala'])): ?>
            <div class="form-container">
                <h2>Selecciona una Butaca</h2>
                <p>Esta película se transmite en la sala <strong><?php echo $_SESSION['sala_name']; ?></strong>.</p>
                <h3>Asientos Disponibles:</h3>
                <?php
                echo "Debug: id_sala seleccionado = " . $_SESSION['selected_sala'] . "<br>";

                $sql = "SELECT id_butaca, fila, numero_butaca 
                        FROM Butaca 
                        WHERE id_sala = ?";
                $params = [$_SESSION['selected_sala']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    echo "<p>Error al cargar las butacas: " . print_r(sqlsrv_errors(), true) . "</p>";
                } else {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='butaca_id' value='" . $row['id_butaca'] . "'>";
                            echo "<p><strong>Fila:</strong> " . $row['fila'] . " <strong>Número:</strong> " . $row['numero_butaca'] . "</p>";
                            echo "<button type='submit' name='select_butaca'>Seleccionar</button>";
                            echo "</form>";
                        }
                    } else {
                        echo "<p style='color:red;'>No hay asientos registrados en esta sala.</p>";
                        echo "<a href='pelicula.php'>Volver a seleccionar película</a>";
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
                    <button type='submit' name='confirm_reservation'>Reservar</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Sección de Comprobante -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'comprobante' && isset($_SESSION['reservation_id']) && isset($_SESSION['reserva_funcion_id'])): ?>
            <div class="form-container">
                <h2>Resumen de la Compra</h2>
                <?php
                $sql = "SELECT r.id_reserva, r.fecha_reserva, rf.id_reserva_funcion, f.id_funcion, p.titulo, s.nombre_sala, b.fila, b.numero_butaca, f.fecha_hora AS fecha_hora_funcion
                        FROM Reserva r
                        JOIN Reserva_funcion rf ON r.id_reserva = rf.id_reserva
                        JOIN Funcion f ON rf.id_funcion = f.id_funcion
                        JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
                        JOIN Sala s ON f.id_sala = s.id_sala
                        JOIN Reserva_butaca rb ON rf.id_reserva_funcion = rb.id_reserva_funcion
                        JOIN Butaca b ON rb.id_butaca = b.id_butaca
                        WHERE r.id_reserva = ? AND rf.id_reserva_funcion = ?";
                $params = [$_SESSION['reservation_id'], $_SESSION['reserva_funcion_id']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    echo "<p><strong>Usuario:</strong> " . $_SESSION['nombre'] . "</p>";
                    echo "<p><strong>Reserva ID:</strong> " . $row['id_reserva'] . "</p>";
                    echo "<p><strong>Fecha de Reserva:</strong> " . $row['fecha_reserva']->format('Y-m-d H:i:s') . "</p>";
                    echo "<p><strong>Función ID:</strong> " . $row['id_funcion'] . "</p>";
                    echo "<p><strong>Película:</strong> " . $row['titulo'] . "</p>";
                    echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . $row['fila'] . ", Número " . $row['numero_butaca'] . "</p>";
                    echo "<p><strong>Fecha y Hora de la Función:</strong> " . $row['fecha_hora_funcion']->format('Y-m-d H:i:s') . "</p>";
                } else {
                    echo "<p>Error al cargar el resumen: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
                <h3>Proceder al Pago</h3>
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
    <script src="/scrip.js" defer></script>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
