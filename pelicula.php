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

// Procesar reserva
if (isset($_POST['confirm_reservation'])) {
    // Validación: Verificar que todos los datos necesarios estén presentes
    if (!isset($_POST['funcion_id']) || !isset($_POST['sede_id']) || !isset($_POST['sala_id']) || !isset($_POST['butaca_id'])) {
        echo "<p style='color:red;'>Error: Faltan datos para completar la reserva. Por favor, complete todos los campos.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }

    $funcion_id = $_POST['funcion_id'];
    $butaca_id = $_POST['butaca_id'];
    $dni_usuario = $_SESSION['dni'];
    $fecha_reserva = date('Y-m-d H:i:s');

    // Validar que la función existe
    $sql = "SELECT id_funcion FROM Funcion WHERE id_funcion = ?";
    $params = [$funcion_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt || !sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<p style='color:red;'>Error: La función seleccionada no existe.</p>";
        echo "<a href='pelicula.php'>Volver</a>";
        sqlsrv_close($conn);
        ob_end_flush();
        exit();
    }
    sqlsrv_free_stmt($stmt);

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
    $params = [$id_reserva, $funcion_id];
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
    $params = [$id_reserva_funcion, $butaca_id];
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
            <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
        </div>

        <!-- Formulario de Selección -->
        <?php if (!isset($_GET['step']) || $_GET['step'] === 'movies'): ?>
            <div id="movies-form" class="form-container">
                <h2>Crear una Nueva Reserva</h2>
                <form method="POST">
                    <!-- Selección de Función -->
                    <label for="funcion_id">Función (Película, Sala, Fecha y Hora):</label>
                    <select name="funcion_id" id="funcion_id" required onchange="updateSede()">
                        <option value="">Seleccione una función</option>
                        <?php
                        $sql = "SELECT f.id_funcion, p.titulo, s.nombre_sala, f.fecha_hora
                                FROM Funcion f
                                JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
                                JOIN Sala s ON f.id_sala = s.id_sala
                                WHERE f.fecha_hora > ?";
                        $params = [date('Y-m-d H:i:s')];
                        $stmt = sqlsrv_query($conn, $sql, $params);
                        if ($stmt === false) {
                            echo "<p style='color:red;'>Error al cargar las funciones: " . print_r(sqlsrv_errors(), true) . "</p>";
                        } elseif (!sqlsrv_has_rows($stmt)) {
                            echo "<p style='color:red;'>No hay funciones disponibles en el futuro.</p>";
                        } else {
                            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                $fecha_hora = $row['fecha_hora']->format('Y-m-d H:i:s');
                                echo "<option value='" . $row['id_funcion'] . "' data-sala-id='" . $row['id_sala'] . "'>" . 
                                     $row['titulo'] . " - " . $row['nombre_sala'] . " - " . $fecha_hora . "</option>";
                            }
                        }
                        sqlsrv_free_stmt($stmt);
                        ?>
                    </select><br><br>

                    <!-- Selección de Sede -->
                    <label for="sede_id">Sede:</label>
                    <select name="sede_id" id="sede_id" required onchange="updateSalas()">
                        <option value="">Seleccione una sede</option>
                        <?php
                        $sql = "SELECT id_sede, ciudad_sede FROM Sede";
                        $stmt = sqlsrv_query($conn, $sql);
                        if ($stmt === false) {
                            echo "<p style='color:red;'>Error al cargar las sedes: " . print_r(sqlsrv_errors(), true) . "</p>";
                        } elseif (!sqlsrv_has_rows($stmt)) {
                            echo "<p style='color:red;'>No hay sedes disponibles.</p>";
                        } else {
                            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                echo "<option value='" . $row['id_sede'] . "'>" . $row['ciudad_sede'] . "</option>";
                            }
                        }
                        sqlsrv_free_stmt($stmt);
                        ?>
                    </select><br><br>

                    <!-- Selección de Sala -->
                    <label for="sala_id">Sala:</label>
                    <select name="sala_id" id="sala_id" required onchange="updateButacas()">
                        <option value="">Seleccione una sala</option>
                    </select><br><br>

                    <!-- Selección de Butaca -->
                    <label for="butaca_id">Butaca:</label>
                    <select name="butaca_id" id="butaca_id" required>
                        <option value="">Seleccione una butaca</option>
                    </select><br><br>

                    <button type="submit" name="confirm_reservation">Confirmar Reserva</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Sección de Comprobante -->
        <?php if (isset($_GET['step']) && $_GET['step'] === 'comprobante' && isset($_SESSION['reservation_id']) && isset($_SESSION['reserva_funcion_id'])): ?>
            <div class="form-container">
                <h2>Comprobante de Reserva</h2>
                <?php
                $sql = "SELECT r.id_reserva, r.fecha_reserva, rf.id_reserva_funcion, f.id_funcion, p.titulo, s.nombre_sala, se.ciudad_sede, b.fila, b.numero_butaca, f.fecha_hora AS fecha_hora_funcion
                        FROM Reserva r
                        JOIN Reserva_funcion rf ON r.id_reserva = rf.id_reserva
                        JOIN Funcion f ON rf.id_funcion = f.id_funcion
                        JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
                        JOIN Sala s ON f.id_sala = s.id_sala
                        JOIN Sede se ON s.id_sede = se.id_sede
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
                    echo "<p><strong>Sede:</strong> " . $row['ciudad_sede'] . "</p>";
                    echo "<p><strong>Sala:</strong> " . $row['nombre_sala'] . "</p>";
                    echo "<p><strong>Butaca:</strong> Fila " . $row['fila'] . ", Número " . $row['numero_butaca'] . "</p>";
                    echo "<p><strong>Fecha y Hora de la Función:</strong> " . $row['fecha_hora_funcion']->format('Y-m-d H:i:s') . "</p>";
                } else {
                    echo "<p>Error al cargar el resumen: " . print_r(sqlsrv_errors(), true) . " o no se encontró la reserva en Reserva_funcion.</p>";
                }
                sqlsrv_free_stmt($stmt);
                ?>
                <a href="pelicula.php">Volver</a>
            </div>
        <?php endif; ?>
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <script>
        function updateSede() {
            const funcionSelect = document.getElementById('funcion_id');
            const sedeSelect = document.getElementById('sede_id');
            const salaSelect = document.getElementById('sala_id');
            const butacaSelect = document.getElementById('butaca_id');

            // Limpiar los selects dependientes
            sedeSelect.innerHTML = '<option value="">Seleccione una sede</option>';
            salaSelect.innerHTML = '<option value="">Seleccione una sala</option>';
            butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

            if (funcionSelect.value) {
                const selectedOption = funcionSelect.options[funcionSelect.selectedIndex];
                const salaId = selectedOption.getAttribute('data-sala-id');

                // Obtener el id_sede de la sala seleccionada
                fetch(`/get_sede_from_sala.php?sala_id=${salaId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error al obtener la sede:', data.error);
                            return;
                        }
                        // Seleccionar automáticamente la sede correspondiente
                        Array.from(sedeSelect.options).forEach(option => {
                            if (option.value == data.id_sede) {
                                option.selected = true;
                            }
                        });
                        updateSalas(); // Actualizar las salas
                    })
                    .catch(error => console.error('Error al obtener la sede:', error));
            }
        }

        function updateSalas() {
            const sedeId = document.getElementById('sede_id').value;
            const salaSelect = document.getElementById('sala_id');
            const butacaSelect = document.getElementById('butaca_id');
            salaSelect.innerHTML = '<option value="">Seleccione una sala</option>';
            butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

            if (sedeId) {
                fetch(`/get_salas.php?sede_id=${sedeId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error al obtener las salas:', data.error);
                            return;
                        }
                        data.forEach(sala => {
                            const option = document.createElement('option');
                            option.value = sala.id_sala;
                            option.textContent = sala.nombre_sala;
                            salaSelect.appendChild(option);
                        });

                        // Seleccionar automáticamente la sala de la función
                        const funcionSelect = document.getElementById('funcion_id');
                        const selectedOption = funcionSelect.options[funcionSelect.selectedIndex];
                        const salaId = selectedOption.getAttribute('data-sala-id');
                        Array.from(salaSelect.options).forEach(option => {
                            if (option.value == salaId) {
                                option.selected = true;
                            }
                        });
                        updateButacas(); // Actualizar las butacas
                    })
                    .catch(error => console.error('Error al obtener las salas:', error));
            }
        }

        function updateButacas() {
            const salaId = document.getElementById('sala_id').value;
            const butacaSelect = document.getElementById('butaca_id');
            butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

            if (salaId) {
                fetch(`/get_butacas.php?sala_id=${salaId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error al obtener las butacas:', data.error);
                            return;
                        }
                        data.forEach(butaca => {
                            const option = document.createElement('option');
                            option.value = butaca.id_butaca;
                            option.textContent = `Fila ${butaca.fila}, Número ${butaca.numero_butaca}`;
                            butacaSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error al obtener las butacas:', error));
            }
        }
    </script>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
