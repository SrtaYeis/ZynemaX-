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

// Obtener las sedes para el selector
$sql_sedes = "SELECT id_sede, ciudad_sede, direccion_sede FROM Sede ORDER BY ciudad_sede ASC";
$stmt_sedes = sqlsrv_query($conn, $sql_sedes);
$sedes = [];
while ($row = sqlsrv_fetch_array($stmt_sedes, SQLSRV_FETCH_ASSOC)) {
    $sedes[] = $row;
}
sqlsrv_free_stmt($stmt_sedes);

// Determinar la sede seleccionada (por defecto, la primera sede)
$selected_sede = isset($_GET['sede']) ? (int)$_GET['sede'] : (!empty($sedes) ? $sedes[0]['id_sede'] : null);

// Obtener las fechas disponibles para el carrusel
$sql_fechas = "
    SELECT DISTINCT CAST(f.fecha_hora AS DATE) AS fecha
    FROM Funcion f
    WHERE f.fecha_hora >= GETDATE()
    ORDER BY fecha ASC";
$stmt_fechas = sqlsrv_query($conn, $sql_fechas);
$fechas = [];
while ($row = sqlsrv_fetch_array($stmt_fechas, SQLSRV_FETCH_ASSOC)) {
    $fechas[] = $row['fecha'];
}
sqlsrv_free_stmt($stmt_fechas);

// Determinar la fecha seleccionada (por defecto, la primera fecha)
$selected_fecha = isset($_GET['fecha']) ? $_GET['fecha'] : (!empty($fechas) ? $fechas[0]->format('Y-m-d') : date('Y-m-d'));

// Consulta para la Cartelera (Películas, Funciones y Salas)
$sql_cartelera = "
    SELECT p.id_pelicula, p.titulo, p.sinopsis, p.duracion, p.clasificacion,
           f.fecha_hora, f.formato, s.nombre_sala, se.ciudad_sede
    FROM Funcion f
    INNER JOIN Pelicula p ON f.id_pelicula = p.id_pelicula
    INNER JOIN Sala s ON f.id_sala = s.id_sala
    INNER JOIN Sede se ON s.id_sede = se.id_sede
    WHERE f.fecha_hora >= GETDATE()
      AND se.id_sede = ?
      AND CAST(f.fecha_hora AS DATE) = ?
    ORDER BY p.titulo, f.fecha_hora ASC";
$params = [$selected_sede, $selected_fecha];
$stmt_cartelera = sqlsrv_query($conn, $sql_cartelera, $params);

$cartelera = [];
while ($row = sqlsrv_fetch_array($stmt_cartelera, SQLSRV_FETCH_ASSOC)) {
    $cartelera[$row['id_pelicula']][] = $row;
}
sqlsrv_free_stmt($stmt_cartelera);

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
        <a href="#cartelera">Cartelera</a>
        <a href="#sedes">Sedes</a>
        <?php if (!isset($_SESSION['dni'])): ?>
            <a href="#" onclick="showForm('login')">Login</a>
            <a href="#" onclick="showForm('register')">Register</a>
        <?php else: ?>
            <a href="/logout.php">Logout (<?php echo $_SESSION['nombre']; ?>)</a>
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
            <div class="welcome-message">
                <?php if (isset($_GET['login_success'])): ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                    <p>Explora la cartelera y reserva tus entradas.</p>
                <?php else: ?>
                    <h2>Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['tipo_usuario']; ?>)</h2>
                    <p>Explora la cartelera y reserva tus entradas.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Sección Cartelera -->
        <div class="section" id="cartelera">
            <h2>Cartelera</h2>
            <!-- Selector de Sede -->
            <div class="sede-selector">
                <label for="sede">Seleccionar Cine:</label>
                <select id="sede" name="sede" onchange="updateSede(this.value)">
                    <?php foreach ($sedes as $sede): ?>
                        <option value="<?php echo $sede['id_sede']; ?>" <?php echo $sede['id_sede'] == $selected_sede ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sede['ciudad_sede']); ?> - <?php echo htmlspecialchars($sede['direccion_sede']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Carrusel de Fechas -->
            <div class="date-carousel">
                <?php foreach ($fechas as $fecha): ?>
                    <a href="?sede=<?php echo $selected_sede; ?>&fecha=<?php echo $fecha->format('Y-m-d'); ?>#cartelera"
                       class="date-tab <?php echo $fecha->format('Y-m-d') == $selected_fecha ? 'active' : ''; ?>">
                        <?php echo strtoupper($fecha->format('D d M')); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Lista de Películas -->
            <?php if (empty($cartelera)): ?>
                <p>No hay funciones disponibles para esta fecha y sede.</p>
            <?php else: ?>
                <div class="movie-list">
                    <?php foreach ($cartelera as $pelicula_id => $funciones): ?>
                        <?php $pelicula = $funciones[0]; ?>
                        <div class="movie-card">
                            <!-- Póster (Placeholder, ya que no tenemos imágenes en la base de datos) -->
                            <div class="movie-poster">
                                <img src="https://via.placeholder.com/150x220?text=<?php echo urlencode($pelicula['titulo']); ?>" alt="<?php echo htmlspecialchars($pelicula['titulo']); ?>">
                            </div>
                            <div class="movie-details">
                                <h3><?php echo htmlspecialchars($pelicula['titulo']); ?></h3>
                                <p class="movie-info">
                                    <span class="clasificacion"><?php echo htmlspecialchars($pelicula['clasificacion']); ?></span>
                                    <span class="duracion"><?php echo htmlspecialchars($pelicula['duracion']); ?> min</span>
                                </p>
                                <!-- Formatos -->
                                <p class="movie-formats">
                                    <?php
                                    $formatos = array_unique(array_column($funciones, 'formato'));
                                    foreach ($formatos as $formato) {
                                        echo "<span class='formato'>" . htmlspecialchars($formato) . "</span>";
                                    }
                                    ?>
                                </p>
                                <!-- Horarios -->
                                <p class="movie-schedule">
                                    <?php
                                    $horarios = array_unique(array_map(function($f) { return $f['fecha_hora']->format('H:i'); }, $funciones));
                                    echo implode(' | ', $horarios);
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección Sedes -->
        <div class="section" id="sedes">
            <h2>Sedes</h2>
            <?php if (empty($sedes)): ?>
                <p>No hay sedes disponibles en este momento.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($sedes as $sede): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($sede['ciudad_sede']); ?></strong> - 
                            Dirección: <?php echo htmlspecialchars($sede['direccion_sede']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
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
