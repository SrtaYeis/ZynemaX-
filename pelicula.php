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

// Procesar selección de película
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

// Procesar selección de sede
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

// Procesar selección de sala
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

// Procesar selección de butaca
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

// Procesar confirmación de compra (Reservar)
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

    // Insertar en la tabla Reserva
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

    // Obtener el ID de la reserva recién creada
    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva = $row['id_reserva'];
    sqlsrv_free_stmt($stmt);

    // Insertar en la tabla Reserva_funcion
    $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
    $params = [$id_reserva, $_SESSION['function_id']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Obtener el ID de la reserva_función recién creada
    $sql = "SELECT SCOPE_IDENTITY() AS id_reserva_funcion";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_reserva_funcion = $row['id_reserva_funcion'];
    sqlsrv_free_stmt($stmt);

    // Insertar en la tabla Reserva_butaca
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

    // Obtener el precio de la película
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

    // Insertar en la tabla Pago con metodo_pago como 'pendiente' inicialmente
    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?)";
    $params = [$id_reserva_funcion, 'pendiente', date('Y-m-d H:i:s'), 'pendiente'];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al crear pago: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al crear pago: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Obtener el ID del pago recién creado
    $sql = "SELECT SCOPE_IDENTITY() AS id_pago";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_pago = $row['id_pago'];
    sqlsrv_free_stmt($stmt);

    // Guardar en la sesión
    $_SESSION['id_pago'] = $id_pago;
    $_SESSION['monto_pago'] = $monto_pago;

    // Depuración: Verificar si las variables de sesión están configuradas
    error_log("Después de confirm_purchase - id_pago: " . $id_pago . ", monto_pago: " . $monto_pago);

    // Redirigir al paso de pago
    header("Location: pelicula.php?step=payment");
    exit();
}

// Procesar simulación de pago y mostrar comprobante
if (isset($_POST['simulate_payment'])) {
    // Depuración: Verificar si llegamos a este bloque
    error_log("Iniciando simulación de pago");

    if (!isset($_SESSION['id_pago']) || !isset($_SESSION['monto_pago'])) {
        error_log("Faltan datos de sesión - id_pago: " . (isset($_SESSION['id_pago']) ? $_SESSION['id_pago'] : 'No definido') . ", monto_pago: " . (isset($_SESSION['monto_pago']) ? $_SESSION['monto_pago'] : 'No definido'));
        echo "<p style='color:red;'>Error: No se encontró un pago para procesar la simulación.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $id_pago = $_SESSION['id_pago'];
    $monto_pago = $_SESSION['monto_pago'];
    $metodo_pago = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;

    // Validar el método de pago
    if (!in_array($metodo_pago, ['efectivo', 'tarjeta', 'transferencia'])) {
        error_log("Método de pago no válido: " . $metodo_pago);
        echo "<p style='color:red;'>Error: Método de pago no válido.</p>";
        echo "<a href='pelicula.php?step=payment'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Actualizar el estado del pago
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

    // Consultar los datos para el comprobante (simplificado)
    $sql = "SELECT 
        u.nombre AS usuario,
        p.titulo AS pelicula,
        s.ciudad_sede AS sede,
        sa.nombre_sala AS sala,
        b.fila AS fila,
        b.numero_butaca AS numero_butaca,
        f.fecha_hora AS fecha_funcion,
        p.precio AS monto
    FROM Pago pg
    JOIN Reserva_funcion rf ON pg.id_reserva_funcion = rf.id_reserva_funcion
    JOIN Reserva r ON rf.id_reserva = r.id_reserva
    JOIN Usuario u ON r.dni_usuario = u.dni
    JOIN Funcion f ON rf.id_funcion = f.id_funcion
    JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
    JOIN Sala sa ON f.id_sala = sa.id_sala
    JOIN Sede s ON sa.id_sede = s.id_sede
    JOIN Reserva_butaca rb ON rf.id_reserva_funcion = rb.id_reserva_funcion
    JOIN Butaca b ON rb.id_butaca = b.id_butaca
    WHERE pg.id_pago = ?";
    
    $params = [$id_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al obtener datos del comprobante: " . print_r(sqlsrv_errors(), true));
        echo "<p style='color:red;'>Error al obtener datos del comprobante: " . print_r(sqlsrv_errors(), true) . "</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Mostrar el comprobante (simplificado)
    echo "<div class='form-container'>";
    echo "<h2>Comprobante de Pago</h2>";
    echo "<h3>Zynemax+ | Tu Cine Favorito</h3>";
    echo "<hr>";

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<p><strong>Usuario:</strong> " . htmlspecialchars($row['usuario']) . "</p>";
        echo "<p><strong>Película:</strong> " . htmlspecialchars($row['pelicula']) . "</p>";
        echo "<p><strong>Sede:</strong> " . htmlspecialchars($row['sede']) . "</p>";
        echo "<p><strong>Sala:</strong> " . htmlspecialchars($row['sala']) . "</p>";
        echo "<p><strong>Butaca:</strong> Fila " . htmlspecialchars($row['fila']) . ", Número " . htmlspecialchars($row['numero_butaca']) . "</p>";
        echo "<p><strong>Fecha y Hora de la Función:</strong> " . $row['fecha_funcion']->format('Y-m-d H:i:s') . "</p>";
        echo "<p><strong>Monto Pagado:</strong> $" . number_format($row['monto'], 2) . "</p>";
        echo "<p><strong>Método de Pago:</strong> " . htmlspecialchars(ucfirst($metodo_pago)) . "</p>";
        echo "<p><strong>Fecha de Pago:</strong> " . date('Y-m-d H:i:s') . "</p>";
    } else {
        echo "<p style='color:red;'>Error: No se encontraron datos para el comprobante.</p>";
    }

    echo "<hr>";
    echo "<p>¡Gracias por tu compra en Zynemax+! Disfruta tu película.</p>";
    echo "<a href='pelicula.php'>Volver</a>";
    echo "</div>";

    sqlsrv_free_stmt($stmt);

    // Limpiar las variables de sesión
    unset($_SESSION['selected_movie']);
    unset($_SESSION['selected_sede']);
    unset($_SESSION['selected_sala']);
    unset($_SESSION['sala_name']);
    unset($_SESSION['selected_butaca']);
    unset($_SESSION['function_id']);
    unset($_SESSION['id_pago']);
    unset($_SESSION['monto_pago']);

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
                // Obtener detalles de la película
                $sql = "SELECT titulo, precio FROM Pelicula WHERE id_pelicula = ?";
                $params = [$_SESSION['selected_movie']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar detalles de la película: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar detalles de la película: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                $pelicula_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                // Obtener detalles de la sede
                $sql = "SELECT ciudad_sede FROM Sede WHERE id_sede = ?";
                $params = [$_SESSION['selected_sede']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar detalles de la sede: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar detalles de la sede: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                $sede_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                // Obtener detalles de la sala
                $sql = "SELECT nombre_sala FROM Sala WHERE id_sala = ?";
                $params = [$_SESSION['selected_sala']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar detalles de la sala: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar detalles de la sala: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                $sala_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                // Obtener detalles de la función
                $sql = "SELECT fecha_hora FROM Funcion WHERE id_funcion = ?";
                $params = [$_SESSION['function_id']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar detalles de la función: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar detalles de la función: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                $funcion_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                // Obtener detalles de la butaca
                $sql = "SELECT fila, numero_butaca FROM Butaca WHERE id_butaca = ?";
                $params = [$_SESSION['selected_butaca']];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    error_log("Error al cargar detalles de la butaca: " . print_r(sqlsrv_errors(), true));
                    echo "<p style='color:red;'>Error al cargar detalles de la butaca: " . print_r(sqlsrv_errors(), true) . "</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }

                $butaca_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                // Mostrar el resumen
                if ($pelicula_data && $sede_data && $sala_data && $funcion_data && $butaca_data) {
                    echo "<p><strong>Usuario:</strong> " . htmlspecialchars($_SESSION['nombre']) . "</p>";
                    echo "<p><strong>Película:</strong> " . htmlspecialchars($pelicula_data['titulo']) . "</p>";
                    echo "<p><strong>Sede:</strong> " . htmlspecialchars($sede_data['ciudad_sede']) . "</p>";
                    echo "<p><strong>Sala:</strong> " . htmlspecialchars($sala_data['nombre_sala']) . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . htmlspecialchars($butaca_data['fila']) . ", Número " . $butaca_data['numero_butaca'] . "</p>";
                    echo "<p><strong>Fecha y Hora:</strong> " . $funcion_data['fecha_hora']->format('Y-m-d H:i:s') . "</p>";
                    echo "<p><strong>Precio:</strong> $" . number_format($pelicula_data['precio'], 2) . "</p>";
                } else {
                    echo "<p style='color:red;'>Error: No se pudieron cargar todos los datos del resumen.</p>";
                    echo "<a href='pelicula.php'>Volver</a>";
                    sqlsrv_close($conn);
                    ob_end_flush();
                    exit();
                }
                ?>
                <form method="POST">
                    <button type="submit" name="confirm_purchase">Confirmar Compra</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['step']) && $_GET['step'] === 'payment' && isset($_SESSION['id_pago'])): ?>
            <div class="form-container">
                <h2>Simular Pago</h2>
                <p>Selecciona cómo deseas pagar tu entrada:</p>
                <form method="POST">
                    <div style="margin-bottom: 15px;">
                        <label><input type="radio" name="payment_method" value="efectivo" required> Efectivo</label><br>
                        <label><input type="radio" name="payment_method" value="tarjeta"> Tarjeta</label><br>
                        <label><input type="radio" name="payment_method" value="transferencia"> Transferencia</label>
                    </div>
                    <button type="submit" name="simulate_payment" style="background-color: #b22222; color: white; padding: 10px 20px; border: none; cursor: pointer;">Pagar</button>
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
