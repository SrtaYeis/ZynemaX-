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

// Validación de sesión con manejo de redirección
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['dni']) && $current_page !== 'index.php') {
    // Depuración: Verificar si estamos en un bucle
    error_log("Sesión no iniciada en $current_page, redirigiendo a index.php?error=6");
    header("Location: index.php?error=6");
    exit();
}

// Procesar selección de película
if (isset($_POST['select_movie'])) {
    $movie_id = $_POST['movie_id'];
    $_SESSION['selected_movie'] = $movie_id;
    header("Location: pelicula.php?step=sede");
    exit();
}

// Procesar selección de sede
if (isset($_POST['select_sede'])) {
    $sede_id = $_POST['sede_id'];
    $_SESSION['selected_sede'] = $sede_id;
    header("Location: pelicula.php?step=sala");
    exit();
}

// Procesar selección de sala
if (isset($_POST['select_sala'])) {
    $sala_id = $_POST['sala_id'];
    $funcion_id = $_POST['funcion_id'];
    $sala_name = $_POST['sala_name'];
    $_SESSION['selected_sala'] = $sala_id;
    $_SESSION['function_id'] = $funcion_id;
    $_SESSION['sala_name'] = $sala_name;
    header("Location: pelicula.php?step=butaca");
    exit();
}

// Procesar selección de butaca
if (isset($_POST['select_butaca'])) {
    $butaca_id = $_POST['butaca_id'];
    $sql = "SELECT id_butaca FROM Butaca WHERE id_butaca = ? AND id_sala = ?";
    $params = [$butaca_id, $_SESSION['selected_sala']];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['selected_butaca'] = $butaca_id;
        header("Location: pelicula.php?step=summary");
        exit();
    } else {
        echo "<p style='color:red;'>El asiento seleccionado no existe o no pertenece a la sala. Por favor, elija otro.</p>";
        echo "<a href='pelicula.php?step=butaca'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);
}

// Procesar confirmación de compra
if (isset($_POST['confirm_purchase'])) {
    if (!isset($_SESSION['selected_movie']) || !isset($_SESSION['selected_sede']) || !isset($_SESSION['selected_sala']) || !isset($_SESSION['selected_butaca']) || !isset($_SESSION['function_id'])) {
        echo "<p style='color:red;'>Error: Faltan datos para completar la compra. Por favor, reinicie el proceso.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $dni_usuario = $_SESSION['dni'];
    $fecha_reserva = date('Y-m-d H:i:s');
    $movie_id = $_SESSION['selected_movie'];
    $sala_id = $_SESSION['selected_sala'];
    $funcion_id = $_SESSION['function_id'];

    // Verificar si la función seleccionada existe o crearla
    $sql = "SELECT id_funcion FROM Funcion WHERE id_pelicula = ? AND id_sala = ? AND fecha_hora = (
        SELECT fecha_hora FROM Funcion WHERE id_funcion = ?
    )";
    $params = [$movie_id, $sala_id, $funcion_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && !sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Si no existe, insertar nueva función
        $sql = "INSERT INTO Funcion (id_pelicula, id_sala, fecha_hora) VALUES (?, ?, (
            SELECT fecha_hora FROM Funcion WHERE id_funcion = ?
        ))";
        $params = [$movie_id, $sala_id, $funcion_id];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            echo "<p style='color:red;'>Error al crear función: " . print_r(sqlsrv_errors(), true) . "</p>";
            echo "<a href='pelicula.php'>Volver</a>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }
        $funcion_id = sqlsrv_next_result($conn) ? sqlsrv_get_field(1, 0) : sqlsrv_errors();
    }
    sqlsrv_free_stmt($stmt);

    // Insertar en Reserva
    $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva) VALUES (?, ?)";
    $params = [$dni_usuario, $fecha_reserva];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<p style='color:red;'>Error al crear reserva: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    $id_reserva = sqlsrv_next_result($conn) ? sqlsrv_get_field(1, 0) : sqlsrv_errors();

    // Vincular la reserva con la función en Reserva_funcion
    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $funcion_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<p style='color:red;'>Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    $id_reserva_funcion = sqlsrv_next_result($conn) ? sqlsrv_get_field(1, 0) : sqlsrv_errors();

    // Vincular la reserva con la butaca
    $sql = "INSERT INTO Reserva_butaca (id_reserva_funcion, id_butaca) VALUES (?, ?)";
    $params = [$id_reserva_funcion, $_SESSION['selected_butaca']];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<p style='color:red;'>Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Obtener el precio de la película
    $sql = "SELECT precio FROM Pelicula WHERE id_pelicula = ?";
    $params = [$_SESSION['selected_movie']];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<p style='color:red;'>Error al obtener precio: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $monto_pago = $row['precio'];

    // Insertar en Pago
    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?)";
    $params = [$id_reserva_funcion, 'tarjeta', date('Y-m-d H:i:s'), 'pendiente'];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo "<p style='color:red;'>Error al crear pago: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Obtener el ID del pago
    $sql = "SELECT SCOPE_IDENTITY() AS id_pago";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_pago = $row['id_pago'];

    $_SESSION['id_pago'] = $id_pago;
    $_SESSION['monto_pago'] = $monto_pago;
    header("Location: pelicula.php?step=payment");
    exit();
    sqlsrv_free_stmt($stmt);
}

// Procesar simulación de pago
if (isset($_POST['simulate_payment'])) {
    if (!isset($_SESSION['id_pago']) || !isset($_SESSION['monto_pago'])) {
        echo "<p style='color:red;'>Error: No se encontró un pago para procesar la simulación.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $id_pago = $_SESSION['id_pago'];
    $monto_pago = $_SESSION['monto_pago'];
    $metodo_pago = $_POST['payment_method'];

    // Actualizar estado del pago a 'completado'
    $sql = "UPDATE Pago SET metodo_pago = ?, estado_pago = ?, fecha_pago = ? WHERE id_pago = ?";
    $params = [$metodo_pago, 'completado', date('Y-m-d H:i:s'), $id_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<p style='color:red;'>Error al actualizar el pago: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Limpiar sesiones
    unset($_SESSION['selected_movie']);
    unset($_SESSION['selected_sede']);
    unset($_SESSION['selected_sala']);
    unset($_SESSION['sala_name']);
    unset($_SESSION['selected_butaca']);
    unset($_SESSION['function_id']);
    unset($_SESSION['id_pago']);
    unset($_SESSION['monto_pago']);

    echo "<div class='form-container'>";
    echo "<h2>¡Compra realizada con éxito!</h2>";
    echo "<p><strong>ID Pago:</strong> " . $id_pago . "</p>";
    echo "<p><strong>Monto Pagado:</strong> $" . number_format($monto_pago, 2) . "</p>";
    echo "<a href='pelicula.php'>Volver</a>";
    echo "</div>";
    sqlsrv_close($conn);
    ob_end_flush();
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
                $sql = "SELECT DISTINCT p.id_pelicula, p.titulo, p.duracion, p.clasificacion, p.fecha_estreno, p.precio 
                        FROM Pelicula p 
                        JOIN Funcion f ON p.id_pelicula = f.id_pelicula";
                $stmt = sqlsrv_query($conn, $sql);
                if ($stmt) {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='movie_id' value='" . $row['id_pelicula'] . "'>";
                            echo "<p><strong>Título:</strong> " . $row['titulo'] . "</p>";
                            echo "<p><strong>Duración:</strong> " . $row['duracion'] . " min</p>";
                            echo "<p><strong>Clasificación:</strong> " . $row['clasificacion'] . "</p>";
                            echo "<p><strong>Fecha Estreno:</strong> " . $row['fecha_estreno']->format('Y-m-d') . "</p>";
                            echo "<p><strong>Precio:</strong> $" . number_format($row['precio'], 2) . "</p>";
                            echo "<button type='submit' name='select_movie'>Seleccionar</button>";
                            echo "</form>";
                        }
                    } else {
                        echo "<p>No hay películas con funciones disponibles.</p>";
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
                $sql = "SELECT s.id_sede, s.ciudad_sede, s.direccion_sede 
                        FROM Sede s 
                        JOIN Sala sa ON s.id_sede = sa.id_sede 
                        JOIN Funcion f ON sa.id_sala = f.id_sala 
                        WHERE f.id_pelicula = ?";
                $params = [$_SESSION['selected_movie']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='sede_id' value='" . $row['id_sede'] . "'>";
                            echo "<p><strong>Ciudad:</strong> " . $row['ciudad_sede'] . "</p>";
                            echo "<p><strong>Dirección:</strong> " . $row['direccion_sede'] . "</p>";
                            echo "<button type='submit' name='select_sede'>Seleccionar</button>";
                            echo "</form>";
                        }
                    } else {
                        echo "<p>No hay sedes disponibles para esta película.</p>";
                        echo "<a href='pelicula.php'>Volver</a>";
                    }
                    sqlsrv_free_stmt($stmt);
                } else {
                    echo "<p>Error al cargar las sedes: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Sección de Salas -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'sala' && isset($_SESSION['selected_sede']) && isset($_SESSION['selected_movie'])): ?>
            <div class="form-container">
                <h2>Selecciona una Sala</h2>
                <?php
                $sql = "SELECT s.id_sala, s.nombre_sala, f.id_funcion, f.fecha_hora 
                        FROM Sala s 
                        JOIN Funcion f ON s.id_sala = f.id_sala 
                        WHERE s.id_sede = ? AND f.id_pelicula = ?";
                $params = [$_SESSION['selected_sede'], $_SESSION['selected_movie']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<form method='POST' style='margin: 10px 0;'>";
                            echo "<input type='hidden' name='sala_id' value='" . $row['id_sala'] . "'>";
                            echo "<input type='hidden' name='funcion_id' value='" . $row['id_funcion'] . "'>";
                            echo "<input type='hidden' name='sala_name' value='" . $row['nombre_sala'] . "'>";
                            echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                            echo "<p><strong>Fecha y Hora:</strong> " . $row['fecha_hora']->format('Y-m-d H:i:s') . "</p>";
                            echo "<button type='submit' name='select_sala'>Seleccionar</button>";
                            echo "</form>";
                        }
                    } else {
                        echo "<p>No hay salas disponibles para esta sede y película.</p>";
                        echo "<a href='pelicula.php?step=sede'>Volver</a>";
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
                <p>Esa película se transmite en la sala <strong><?php echo $_SESSION['sala_name']; ?></strong>.</p>
                <h3>Asientos Disponibles:</h3>
                <?php
                $sql = "SELECT id_butaca, fila, numero_butaca 
                        FROM Butaca 
                        WHERE id_sala = ? AND id_butaca NOT IN (SELECT id_butaca FROM Reserva_butaca)";
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
                        echo "<p style='color:red;'>No hay asientos disponibles en esta sala.</p>";
                        echo "<a href='pelicula.php?step=sala'>Volver</a>";
                    }
                    sqlsrv_free_stmt($stmt);
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Resumen de la compra -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'summary' && isset($_SESSION['selected_butaca'])): ?>
            <div class="form-container">
                <h2>Resumen de tu Compra</h2>
                <?php
                $sql = "SELECT 
                    u.nombre AS usuario,
                    p.titulo,
                    s.ciudad_sede,
                    sa.nombre_sala,
                    b.fila,
                    b.numero_butaca,
                    f.fecha_hora,
                    p.precio
                FROM Usuario u
                JOIN Reserva r ON u.dni = r.dni_usuario
                JOIN Reserva_funcion rf ON r.id_reserva = rf.id_reserva
                JOIN Funcion f ON rf.id_funcion = f.id_funcion
                JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
                JOIN Sala sa ON f.id_sala = sa.id_sala
                JOIN Sede s ON sa.id_sede = s.id_sede
                JOIN Reserva_butaca rb ON rf.id_reserva_funcion = rb.id_reserva_funcion
                JOIN Butaca b ON rb.id_butaca = b.id_butaca
                WHERE u.dni = ? AND p.id_pelicula = ? AND s.id_sede = ? AND sa.id_sala = ? AND b.id_butaca = ? AND f.id_funcion = (
                    SELECT id_funcion FROM Reserva_funcion WHERE id_reserva = r.id_reserva
                )";
                $params = [
                    $_SESSION['dni'],
                    $_SESSION['selected_movie'],
                    $_SESSION['selected_sede'],
                    $_SESSION['selected_sala'],
                    $_SESSION['selected_butaca']
                ];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    echo "<p><strong>Usuario:</strong> " . $row['usuario'] . "</p>";
                    echo "<p><strong>Película:</strong> " . $row['titulo'] . "</p>";
                    echo "<p><strong>Sede:</strong> " . $row['ciudad_sede'] . "</p>";
                    echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . $row['fila'] . ", Número " . $row['numero_butaca'] . "</p>";
                    echo "<p><strong>Fecha y Hora:</strong> " . $row['fecha_hora']->format('Y-m-d H:i:s') . "</p>";
                    echo "<p><strong>Precio:</strong> $" . number_format($row['precio'], 2) . "</p>";
                } else {
                    echo "<p style='color:red;'>Error al cargar el resumen o no se encontraron datos: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }
                sqlsrv_free_stmt($stmt);
                ?>
                <form method="POST">
                    <button type="submit" name="confirm_purchase">Confirmar Compra</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Simulación de pago -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'payment' && isset($_SESSION['id_pago'])): ?>
            <div class="form-container">
                <h2>Simular Pago</h2>
                <p>Por favor, selecciona un método de pago para simular la transacción.</p>
                <form method="POST">
                    <select name="payment_method" required>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                    <button type="submit" name="simulate_payment">Pagar</button>
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
