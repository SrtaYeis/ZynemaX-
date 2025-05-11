<?php
ob_start(); // Iniciar el búfer de salida
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Depuración: Verificar el estado de la sesión
echo "<pre>Estado de la sesión en pelicula.php:\n";
var_dump($_SESSION);
echo "</pre>";

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
    error_log("Conexión fallida en pelicula.php: " . print_r(sqlsrv_errors(), true));
    die("<pre>Conexión fallida: " . print_r(sqlsrv_errors(), true) . "</pre>");
}

// Validación de sesión
if (!isset($_SESSION['dni'])) {
    error_log("Sesión no iniciada en pelicula.php");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error de Sesión</title>
    </head>
    <body>
        <div style="text-align: center; margin-top: 50px;">
            <h2>Error: Sesión no iniciada</h2>
            <p>Por favor, inicia sesión en <a href='index.php'>index.php</a>.</p>
        </div>
    </body>
    </html>
    <?php
    sqlsrv_close($conn);
    ob_end_flush();
    exit();
}

// Resto del código de pelicula.php (sin cambios)
if (isset($_POST['select_movie'])) {
    $movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : null;
    if ($movie_id) {
        $_SESSION['selected_movie'] = $movie_id;
        header("Location: pelicula.php?step=sede");
        exit();
    } else {
        error_log("ID de película no válido: " . $movie_id);
        header("Location: pelicula.php");
        exit();
    }
}

if (isset($_POST['select_sede'])) {
    $sede_id = isset($_POST['sede_id']) ? (int)$_POST['sede_id'] : null;
    if ($sede_id) {
        $_SESSION['selected_sede'] = $sede_id;
        header("Location: pelicula.php?step=sala");
        exit();
    } else {
        error_log("ID de sede no válido: " . $sede_id);
        header("Location: pelicula.php?step=movies");
        exit();
    }
}

if (isset($_POST['select_sala'])) {
    $sala_id = isset($_POST['sala_id']) ? (int)$_POST['sala_id'] : null;
    $funcion_id = isset($_POST['funcion_id']) ? (int)$_POST['funcion_id'] : null;
    $sala_name = isset($_POST['sala_name']) ? $_POST['sala_name'] : '';
    if ($sala_id && $funcion_id && $sala_name) {
        $_SESSION['selected_sala'] = $sala_id;
        $_SESSION['function_id'] = $funcion_id;
        $_SESSION['sala_name'] = $sala_name;
        header("Location: pelicula.php?step=butaca");
        exit();
    } else {
        error_log("Datos de sala no válidos: sala_id=$sala_id, funcion_id=$funcion_id, sala_name=$sala_name");
        header("Location: pelicula.php?step=sede");
        exit();
    }
}

if (isset($_POST['select_butaca'])) {
    $butaca_id = isset($_POST['butaca_id']) ? (int)$_POST['butaca_id'] : null;
    if ($butaca_id && isset($_SESSION['selected_sala'])) {
        $sql = "SELECT id_butaca FROM Butaca WHERE id_butaca = ? AND id_sala = ?";
        $params = [$butaca_id, $_SESSION['selected_sala']];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error al verificar butaca: " . print_r(sqlsrv_errors(), true));
            echo "<p style='color:red;'>Error al verificar el asiento: " . print_r(sqlsrv_errors(), true) . "</p>";
            echo "<a href='pelicula.php?step=butaca'>Volver</a>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        if (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $_SESSION['selected_butaca'] = $butaca_id;
            header("Location: pelicula.php?step=summary");
            exit();
        } else {
            echo "<p style='color:red;'>El asiento seleccionado no existe o no pertenece a la sala.</p>";
            echo "<a href='pelicula.php?step=butaca'>Volver</a>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        error_log("ID de butaca no válido o sala no seleccionada: butaca_id=$butaca_id");
        header("Location: pelicula.php?step=sala");
        exit();
    }
}

if (isset($_POST['confirm_purchase'])) {
    if (!isset($_SESSION['selected_movie']) || !isset($_SESSION['selected_sede']) || !isset($_SESSION['selected_sala']) || !isset($_SESSION['selected_butaca']) || !isset($_SESSION['function_id'])) {
        echo "<p style='color:red;'>Error: Faltan datos para completar la compra.</p>";
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

    $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva) VALUES (?, ?)";
    $params = [$dni_usuario, $fecha_reserva];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al crear reserva: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al crear reserva: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva = $row['id_reserva'];
    sqlsrv_free_stmt($stmt);

    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $funcion_id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva_funcion";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva_funcion = $row['id_reserva_funcion'];
    sqlsrv_free_stmt($stmt);

    $sql = "INSERT INTO Reserva_butaca (id_reserva_funcion, id_butaca) VALUES (?, ?)";
    $params = [$id_reserva_funcion, $_SESSION['selected_butaca']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $sql = "SELECT precio FROM Pelicula WHERE id_pelicula = ?";
    $params = [$_SESSION['selected_movie']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al obtener precio: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al obtener precio: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $monto_pago = $row['precio'];
    sqlsrv_free_stmt($stmt);

    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?)";
    $params = [$id_reserva_funcion, 'tarjeta', date('Y-m-d H:i:s'), 'pendiente'];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al crear pago: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al crear pago: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $sql = "SELECT SCOPE_IDENTITY() AS id_pago";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_pago = $row['id_pago'];
    sqlsrv_free_stmt($stmt);

    $_SESSION['id_pago'] = $id_pago;
    $_SESSION['monto_pago'] = $monto_pago;
    header("Location: pelicula.php?step=payment");
    exit();
}

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
    $metodo_pago = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'tarjeta';

    $sql = "UPDATE Pago SET metodo_pago = ?, estado_pago = ?, fecha_pago = ? WHERE id_pago = ?";
    $params = [$metodo_pago, 'completado', date('Y-m-d H:i:s'), $id_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al actualizar el pago: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al actualizar el pago: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);

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
    echo "<p><strong>ID Pago:</strong> " . htmlspecialchars($id_pago) . "</p>";
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Zynemax+ | Tu Cine Favorito</h1>
    </header>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="pelicula.php">Películas</a>
        <a href="logout.php">Logout</a>
    </nav>
    <div class="container">
        <div class="welcome-message">
            <?php if (isset($_GET['payment_success'])): ?>
                <h2 style='color:green;'>¡Pago realizado con éxito!</h2>
            <?php else: ?>
                <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['tipo_usuario']); ?>)</h2>
            <?php endif; ?>
        </div>

        <?php if (!isset($_GET['step']) || $_GET['step'] === 'movies'): ?>
            <div id="movies-form" class="form-container">
                <h2>Selecciona una Película</h2>
                <?php
                $sql = "SELECT DISTINCT p.id_pelicula, p.titulo, p.duracion, p.clasificacion, p.fecha_estreno, p.precio 
                        FROM Pelicula p 
                        JOIN Funcion f ON p.id_pelicula = f.id_pelicula";
                $stmt = sqlsrv_query($conn, $sql);

                if ($stmt === false) {
                    error_log("Error al cargar películas: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar las películas: " . print_r(sqlsrv_errors(), true) . "</p>";
                } elseif (sqlsrv_has_rows($stmt)) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<form method='POST' style='margin: 10px 0;'>";
                        echo "<input type='hidden' name='movie_id' value='" . $row['id_pelicula'] . "'>";
                        echo "<p><strong>Título:</strong> " . htmlspecialchars($row['titulo']) . "</p>";
                        echo "<p><strong>Duración:</strong> " . $row['duracion'] . " min</p>";
                        echo "<p><strong>Clasificación:</strong> " . htmlspecialchars($row['clasificacion']) . "</p>";
                        echo "<p><strong>Fecha Estreno:</strong> " . $row['fecha_estreno']->format('Y-m-d') . "</p>";
                        echo "<p><strong>Precio:</strong> $" . number_format($row['precio'], 2) . "</p>";
                        echo "<button type='submit' name='select_movie'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p>No hay películas con funciones disponibles.</p>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
            </div>
        <?php endif; ?>

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

                if ($stmt === false) {
                    error_log("Error al cargar sedes: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar las sedes: " . print_r(sqlsrv_errors(), true) . "</p>";
                } elseif (sqlsrv_has_rows($stmt)) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<form method='POST' style='margin: 10px 0;'>";
                        echo "<input type='hidden' name='sede_id' value='" . $row['id_sede'] . "'>";
                        echo "<p><strong>Ciudad:</strong> " . htmlspecialchars($row['ciudad_sede']) . "</p>";
                        echo "<p><strong>Dirección:</strong> " . htmlspecialchars($row['direccion_sede']) . "</p>";
                        echo "<button type='submit' name='select_sede'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p>No hay sedes disponibles para esta película.</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
            </div>
        <?php endif; ?>

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

                if ($stmt === false) {
                    error_log("Error al cargar salas: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar las salas: " . print_r(sqlsrv_errors(), true) . "</p>";
                } elseif (sqlsrv_has_rows($stmt)) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<form method='POST' style='margin: 10px 0;'>";
                        echo "<input type='hidden' name='sala_id' value='" . $row['id_sala'] . "'>";
                        echo "<input type='hidden' name='funcion_id' value='" . $row['id_funcion'] . "'>";
                        echo "<input type='hidden' name='sala_name' value='" . htmlspecialchars($row['nombre_sala']) . "'>";
                        echo "<p><strong>Sala:</strong> " . htmlspecialchars($row['nombre_sala']) . "</p>";
                        echo "<p><strong>Fecha y Hora:</strong> " . $row['fecha_hora']->format('Y-m-d H:i:s') . "</p>";
                        echo "<button type='submit' name='select_sala'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p>No hay salas disponibles para esta sede y película.</p>";
                    echo "<a href='pelicula.php?step=sede'>Volver</a>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['step']) && $_GET['step'] === 'butaca' && isset($_SESSION['selected_sala'])): ?>
            <div class="form-container">
                <h2>Selecciona una Butaca</h2>
                <p>Esa película se transmite en la sala <strong><?php echo htmlspecialchars($_SESSION['sala_name']); ?></strong>.</p>
                <h3>Asientos Disponibles:</h3>
                <?php
                $sql = "SELECT b.id_butaca, b.fila, b.numero_butaca 
                        FROM Butaca b 
                        LEFT JOIN Reserva_butaca rb ON b.id_butaca = rb.id_butaca 
                        WHERE b.id_sala = ? AND rb.id_butaca IS NULL";
                $params = [$_SESSION['selected_sala']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar butacas: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar las butacas: " . print_r(sqlsrv_errors(), true) . "</p>";
                } elseif (sqlsrv_has_rows($stmt)) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<form method='POST' style='margin: 10px 0;'>";
                        echo "<input type='hidden' name='butaca_id' value='" . $row['id_butaca'] . "'>";
                        echo "<p><strong>Fila:</strong> " . htmlspecialchars($row['fila']) . " <strong>Número:</strong> " . $row['numero_butaca'] . "</p>";
                        echo "<button type='submit' name='select_butaca'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p style='color:red;'>No hay asientos disponibles en esta sala.</p>";
                    echo "<a href='pelicula.php?step=sala'>Volver</a>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
            </div>
        <?php endif; ?>

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
                WHERE u.dni = ? AND p.id_pelicula = ? AND s.id_sede = ? AND sa.id_sala = ? AND b.id_butaca = ? AND f.id_funcion = ?";
                $params = [
                    $_SESSION['dni'],
                    $_SESSION['selected_movie'],
                    $_SESSION['selected_sede'],
                    $_SESSION['selected_sala'],
                    $_SESSION['selected_butaca'],
                    $_SESSION['function_id']
                ];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar resumen: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar el resumen: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    echo "<p><strong>Usuario:</strong> " . htmlspecialchars($row['usuario']) . "</p>";
                    echo "<p><strong>Película:</strong> " . htmlspecialchars($row['titulo']) . "</p>";
                    echo "<p><strong>Sede:</strong> " . htmlspecialchars($row['ciudad_sede']) . "</p>";
                    echo "<p><strong>Sala:</strong> " . htmlspecialchars($row['nombre_sala']) . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . htmlspecialchars($row['fila']) . ", Número " . $row['numero_butaca'] . "</p>";
                    echo "<p><strong>Fecha y Hora:</strong> " . $row['fecha_hora']->format('Y-m-d H:i:s') . "</p>";
                    echo "<p><strong>Precio:</strong> $" . number_format($row['precio'], 2) . "</p>";
                } else {
                    echo "<p style='color:red;'>No se encontraron datos para el resumen o los datos no coinciden.</p>";
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
    <script src="scrip.js" defer></script>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
