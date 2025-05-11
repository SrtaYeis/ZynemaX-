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
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding: 50px; }
            .error { color: #b22222; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error">
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

// Procesar selección de sala - Aquí registramos en Reserva y Reserva_funcion
if (isset($_POST['select_sala'])) {
    $sala_id = isset($_POST['sala_id']) ? (int)$_POST['sala_id'] : null;
    $funcion_id = isset($_POST['funcion_id']) ? (int)$_POST['funcion_id'] : null;
    $sala_name = isset($_POST['sala_name']) ? $_POST['sala_name'] : '';
    
    if ($sala_id && $funcion_id && $sala_name) {
        // Paso 1: Crear un registro en Reserva
        $dni_usuario = $_SESSION['dni'];
        $fecha_reserva = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva) VALUES (?, ?)";
        $params = [$dni_usuario, $fecha_reserva];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error al crear reserva: " . print_r(sqlsrv_errors(), true));
            echo "<div class='form-container'><p style='color:red;'>Error al crear reserva: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Verificar si la inserción fue exitosa
        $rows_affected = sqlsrv_rows_affected($stmt);
        error_log("Filas afectadas al insertar en Reserva: " . $rows_affected);
        if ($rows_affected <= 0) {
            error_log("No se insertó ninguna fila en Reserva");
            echo "<div class='form-container'><p style='color:red;'>Error: No se pudo insertar la reserva.</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Paso 2: Obtener el ID de la reserva recién creada
        $sql = "SELECT SCOPE_IDENTITY() AS id_reserva";
        $stmt = sqlsrv_query($conn, $sql);

        if ($stmt === false) {
            error_log("Error al obtener SCOPE_IDENTITY para id_reserva: " . print_r(sqlsrv_errors(), true));
            echo "<div class='form-container'><p style='color:red;'>Error al obtener el ID de reserva: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id_reserva = isset($row['id_reserva']) ? (int)$row['id_reserva'] : null;
        sqlsrv_free_stmt($stmt);
        error_log("ID de reserva obtenido con SCOPE_IDENTITY: " . ($id_reserva ?? 'NULL'));

        // Si SCOPE_IDENTITY() falla, intentar obtener el ID con una consulta alternativa
        if (!$id_reserva) {
            error_log("SCOPE_IDENTITY devolvió NULL para id_reserva, intentando consulta alternativa");
            $sql = "SELECT id_reserva FROM Reserva WHERE dni_usuario = ? AND fecha_reserva = ?";
            $params = [$dni_usuario, $fecha_reserva];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                error_log("Error en consulta alternativa para id_reserva: " . print_r(sqlsrv_errors(), true));
                echo "<div class='form-container'><p style='color:red;'>Error al obtener el ID de reserva (consulta alternativa): " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
                sqlsrv_close($conn);
                ob_end_flush();
                exit();
            }

            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $id_reserva = isset($row['id_reserva']) ? (int)$row['id_reserva'] : null;
            sqlsrv_free_stmt($stmt);
            error_log("ID de reserva obtenido con consulta alternativa: " . ($id_reserva ?? 'NULL'));
        }

        // Verificar si se obtuvo un id_reserva válido
        if (!$id_reserva) {
            error_log("No se pudo obtener un id_reserva válido");
            echo "<div class='form-container'><p style='color:red;'>Error: No se pudo generar el ID de reserva.</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Paso 3: Crear un registro en Reserva_funcion
        $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?)";
        $params = [$id_reserva, $funcion_id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true));
            echo "<div class='form-container'><p style='color:red;'>Error al vincular reserva con función: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Verificar si la inserción fue exitosa
        $rows_affected = sqlsrv_rows_affected($stmt);
        error_log("Filas afectadas al insertar en Reserva_funcion: " . $rows_affected);
        if ($rows_affected <= 0) {
            error_log("No se insertó ninguna fila en Reserva_funcion");
            echo "<div class='form-container'><p style='color:red;'>Error: No se pudo insertar en Reserva_funcion.</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Paso 4: Obtener el ID de la reserva_función recién creada
        $sql = "SELECT SCOPE_IDENTITY() AS id_reserva_funcion";
        $stmt = sqlsrv_query($conn, $sql);

        if ($stmt === false) {
            error_log("Error al obtener SCOPE_IDENTITY para id_reserva_funcion: " . print_r(sqlsrv_errors(), true));
            echo "<div class='form-container'><p style='color:red;'>Error al obtener el ID de reserva_función: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id_reserva_funcion = isset($row['id_reserva_funcion']) ? (int)$row['id_reserva_funcion'] : null;
        sqlsrv_free_stmt($stmt);
        error_log("ID de reserva_funcion obtenido con SCOPE_IDENTITY: " . ($id_reserva_funcion ?? 'NULL'));

        // Si SCOPE_IDENTITY() falla, intentar obtener el ID con una consulta alternativa
        if (!$id_reserva_funcion) {
            error_log("SCOPE_IDENTITY devolvió NULL para id_reserva_funcion, intentando consulta alternativa");
            $sql = "SELECT id_reserva_funcion FROM Reserva_funcion WHERE id_reserva = ? AND id_funcion = ?";
            $params = [$id_reserva, $funcion_id];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                error_log("Error en consulta alternativa para id_reserva_funcion: " . print_r(sqlsrv_errors(), true));
                echo "<div class='form-container'><p style='color:red;'>Error al obtener el ID de reserva_función (consulta alternativa): " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=sede'>Volver</a></div>";
                sqlsrv_close($conn);
                ob_end_flush();
                exit();
            }

            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $id_reserva_funcion = isset($row['id_reserva_funcion']) ? (int)$row['id_reserva_funcion'] : null;
            sqlsrv_free_stmt($stmt);
            error_log("ID de reserva_funcion obtenido con consulta alternativa: " . ($id_reserva_funcion ?? 'NULL'));
        }

        // Verificar si se obtuvo un id_reserva_funcion válido
        if (!$id_reserva_funcion) {
            error_log("No se pudo obtener un id_reserva_funcion válido");
            echo "<div class='form-container'><p style='color:red;'>Error: No se pudo generar el ID de reserva_función.</p><a href='pelicula.php?step=sede'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        // Guardar datos en la sesión para los siguientes pasos
        $_SESSION['selected_sala'] = $sala_id;
        $_SESSION['function_id'] = $funcion_id;
        $_SESSION['sala_name'] = $sala_name;
        $_SESSION['id_reserva'] = $id_reserva;
        $_SESSION['id_reserva_funcion'] = $id_reserva_funcion;

        header("Location: pelicula.php?step=butaca");
        exit();
    } else {
        error_log("Datos de sala no válidos: sala_id=$sala_id, funcion_id=$funcion_id, sala_name=$sala_name");
        header("Location: pelicula.php?step=sede");
        exit();
    }
}

// Procesar selección de butaca - Vincular a Reserva_funcion mediante Reserva_butaca
if (isset($_POST['select_butaca'])) {
    $butaca_id = isset($_POST['butaca_id']) ? (int)$_POST['butaca_id'] : null;
    if ($butaca_id && isset($_SESSION['selected_sala']) && isset($_SESSION['id_reserva_funcion'])) {
        $sql = "SELECT id_butaca FROM Butaca WHERE id_butaca = ? AND id_sala = ?";
        $params = [$butaca_id, $_SESSION['selected_sala']];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error al verificar butaca: " . print_r(sqlsrv_errors(), true));
            echo "<div class='form-container'><p style='color:red;'>Error al verificar el asiento: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=butaca'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }

        if (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Vincular la butaca al id_reserva_funcion
            $sql = "INSERT INTO Reserva_butaca (id_reserva_funcion, id_butaca) VALUES (?, ?)";
            $params = [$_SESSION['id_reserva_funcion'], $butaca_id];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                error_log("Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true));
                echo "<div class='form-container'><p style='color:red;'>Error al vincular reserva con butaca: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php?step=butaca'>Volver</a></div>";
                sqlsrv_close($conn);
                ob_end_flush();
                exit();
            }

            $_SESSION['selected_butaca'] = $butaca_id;
            header("Location: pelicula.php?step=summary");
            exit();
        } else {
            echo "<div class='form-container'><p style='color:red;'>El asiento seleccionado no existe o no pertenece a la sala.</p><a href='pelicula.php?step=butaca'>Volver</a></div>";
            sqlsrv_close($conn);
            ob_end_flush();
            exit();
        }
        sqlsrv_free_stmt($stmt);
    } else {
        error_log("ID de butaca no válido o datos previos no definidos: butaca_id=$butaca_id");
        header("Location: pelicula.php?step=sala");
        exit();
    }
}

// Procesar confirmación de compra - Crear el registro en Pago y mostrar comprobante
if (isset($_POST['confirm_purchase'])) {
    error_log("Iniciando confirm_purchase");

    if (!isset($_SESSION['selected_movie']) || !isset($_SESSION['selected_sede']) || !isset($_SESSION['selected_sala']) || !isset($_SESSION['function_id']) || !isset($_SESSION['selected_butaca']) || !isset($_SESSION['id_reserva_funcion'])) {
        error_log("Faltan datos de sesión en confirm_purchase");
        echo "<div class='form-container'><p style='color:red;'>Error: Faltan datos para completar la compra.</p><a href='pelicula.php'>Volver</a></div>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Crear un registro en Pago con valores predeterminados
    $id_reserva_funcion = $_SESSION['id_reserva_funcion'];
    $metodo_pago = 'efectivo'; // Valor predeterminado
    $fecha_pago = date('Y-m-d H:i:s'); // Fecha actual
    $estado_pago = 'completado'; // Estado predeterminado

    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, ?, ?, ?)";
    $params = [$id_reserva_funcion, $metodo_pago, $fecha_pago, $estado_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al crear pago: " . print_r(sqlsrv_errors(), true));
        echo "<div class='form-container'><p style='color:red;'>Error al crear pago: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php'>Volver</a></div>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Obtener el ID del pago recién creado
    $sql = "SELECT SCOPE_IDENTITY() AS id_pago";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_pago = isset($row['id_pago']) ? (int)$row['id_pago'] : null;
    sqlsrv_free_stmt($stmt);
    error_log("Pago creado - id_pago: " . ($id_pago ?? 'NULL'));

    if (!$id_pago) {
        error_log("No se pudo obtener un id_pago válido");
        echo "<div class='form-container'><p style='color:red;'>Error: No se pudo generar el ID de pago.</p><a href='pelicula.php'>Volver</a></div>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    // Cargar todos los datos para el comprobante
    $sql = "SELECT 
                u.nombre AS usuario_nombre,
                p.titulo AS pelicula_titulo,
                p.precio AS monto_pagado,
                s.ciudad_sede AS sede_ciudad,
                sa.nombre_sala AS sala_nombre,
                b.fila AS butaca_fila,
                b.numero_butaca AS butaca_numero,
                f.fecha_hora AS funcion_fecha_hora,
                pa.fecha_pago AS fecha_pago,
                pa.metodo_pago AS metodo_pago
            FROM Pago pa
            JOIN Reserva_funcion rf ON pa.id_reserva_funcion = rf.id_reserva_funcion
            JOIN Reserva r ON rf.id_reserva = r.id_reserva
            JOIN Usuario u ON r.dni_usuario = u.dni
            JOIN Funcion f ON rf.id_funcion = f.id_funcion
            JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
            JOIN Sala sa ON f.id_sala = sa.id_sala
            JOIN Sede s ON sa.id_sede = s.id_sede
            JOIN Reserva_butaca rb ON rb.id_reserva_funcion = rf.id_reserva_funcion
            JOIN Butaca b ON rb.id_butaca = b.id_butaca
            WHERE pa.id_pago = ?";
    $params = [$id_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        error_log("Error al cargar datos del comprobante: " . print_r(sqlsrv_errors(), true));
        echo "<div class='form-container'><p style='color:red;'>Error al cargar datos del comprobante: " . print_r(sqlsrv_errors(), true) . "</p><a href='pelicula.php'>Volver</a></div>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $comprobante = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    // Mostrar el comprobante
    echo "<div class='form-container comprobante'>";
    echo "<h2>Comprobante de Pago</h2>";
    echo "<h3>Zynemax+ | Tu Cine Favorito</h3>";
    echo "<hr>";
    echo "<p><strong>Usuario:</strong> " . htmlspecialchars($comprobante['usuario_nombre']) . "</p>";
    echo "<p><strong>Película:</strong> " . htmlspecialchars($comprobante['pelicula_titulo']) . "</p>";
    echo "<p><strong>Sede:</strong> " . htmlspecialchars($comprobante['sede_ciudad']) . "</p>";
    echo "<p><strong>Sala:</strong> " . htmlspecialchars($comprobante['sala_nombre']) . "</p>";
    echo "<p><strong>Butaca:</strong> Fila " . htmlspecialchars($comprobante['butaca_fila']) . ", Número " . $comprobante['butaca_numero'] . "</p>";
    echo "<p><strong>Fecha y Hora de la Función:</strong> " . $comprobante['funcion_fecha_hora']->format('Y-m-d H:i:s') . "</p>";
    echo "<p><strong>Monto Pagado:</strong> $" . number_format($comprobante['monto_pagado'], 2) . "</p>";
    echo "<p><strong>Método de Pago:</strong> " . htmlspecialchars(ucfirst($comprobante['metodo_pago'])) . "</p>";
    echo "<p><strong>Fecha de Pago:</strong> " . htmlspecialchars($comprobante['fecha_pago']->format('Y-m-d H:i:s')) . "</p>";
    echo "<hr>";
    echo "<p>¡Gracias por tu compra en Zynemax+! Disfruta tu película.</p>";
    echo "<a href='pelicula.php' class='button'>Volver</a>";
    echo "</div>";

    // Limpiar las variables de sesión
    unset($_SESSION['selected_movie']);
    unset($_SESSION['selected_sede']);
    unset($_SESSION['selected_sala']);
    unset($_SESSION['sala_name']);
    unset($_SESSION['selected_butaca']);
    unset($_SESSION['function_id']);
    unset($_SESSION['id_reserva']);
    unset($_SESSION['id_reserva_funcion']);

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
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        header {
            background-color: #b22222;
            color: white;
            text-align: center;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        nav {
            background-color: #333;
            padding: 10px 0;
            text-align: center;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin: 0 20px;
            font-size: 1.1em;
        }
        nav a:hover {
            color: #b22222;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .welcome-message {
            text-align: center;
            margin-bottom: 30px;
        }
        .welcome-message h2 {
            color: #b22222;
            font-size: 1.8em;
        }
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .form-container h2 {
            color: #b22222;
            margin-top: 0;
            font-size: 1.6em;
        }
        .form-container p {
            margin: 10px 0;
            font-size: 1.1em;
        }
        .form-container form {
            margin: 15px 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .form-container form:last-child {
            border-bottom: none;
        }
        .form-container button {
            background-color: #b22222;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .form-container button:hover {
            background-color: #8b1a1a;
        }
        .comprobante {
            background-color: #fff8e1;
            border: 2px solid #b22222;
            text-align: center;
        }
        .comprobante h2 {
            color: #b22222;
            font-size: 2em;
        }
        .comprobante h3 {
            color: #333;
            font-size: 1.5em;
        }
        .comprobante hr {
            border: 1px dashed #b22222;
            margin: 20px 0;
        }
        .comprobante p {
            font-size: 1.2em;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            background-color: #b22222;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1em;
        }
        .button:hover {
            background-color: #8b1a1a;
        }
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 15px 0;
            position: fixed;
            width: 100%;
            bottom: 0;
        }
    </style>
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
            <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['tipo_usuario']); ?>)</h2>
        </div>

        <?php if (!isset($_GET['step']) || $_GET['step'] === 'movies'): ?>
            <div class="form-container">
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
                        echo "<form method='POST'>";
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
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='sede_id' value='" . $row['id_sede'] . "'>";
                        echo "<p><strong>Ciudad:</strong> " . htmlspecialchars($row['ciudad_sede']) . "</p>";
                        echo "<p><strong>Dirección:</strong> " . htmlspecialchars($row['direccion_sede']) . "</p>";
                        echo "<button type='submit' name='select_sede'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p>No hay sedes disponibles para esta película.</p>";
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                        echo "<form method='POST'>";
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
                    echo "<a href='pelicula.php?step=sede' class='button'>Volver</a>";
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
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='butaca_id' value='" . $row['id_butaca'] . "'>";
                        echo "<p><strong>Fila:</strong> " . htmlspecialchars($row['fila']) . " <strong>Número:</strong> " . $row['numero_butaca'] . "</p>";
                        echo "<button type='submit' name='select_butaca'>Seleccionar</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<p style='color:red;'>No hay asientos disponibles en esta sala.</p>";
                    echo "<a href='pelicula.php?step=sala' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
                    echo "<a href='pelicula.php' class='button'>Volver</a>";
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
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
