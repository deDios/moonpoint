<?php
header('Content-Type: application/json');

$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if ($path && file_exists($path)) {
    include $path;
} else {
    die(json_encode(["error" => "No se encontró Conexion.php en la ruta $path"]));
}

// --- Capturar JSON crudo o POST tradicional
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    // Si no viene JSON, intentar leer como form-data o x-www-form-urlencoded
    $input = $_POST;
}

// --- Validar parámetros
if (empty($input['nombre_persona']) || empty($input['numero_tarjeta'])) {
    echo json_encode([
        "error" => "Faltan parámetros obligatorios: 'nombre_persona' y 'numero_tarjeta'",
        "rawInput" => $rawInput // Para depuración
    ]);
    exit;
}

$nombre_persona = trim($input['nombre_persona']);
$numero_tarjeta = trim($input['numero_tarjeta']);

// --- Conexión
$con = conectar();
if (!$con) {
    die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
}

// --- Escapar datos
$nombre_persona = mysqli_real_escape_string($con, $nombre_persona);
$numero_tarjeta = mysqli_real_escape_string($con, $numero_tarjeta);

// --- Llave secreta para AES
$secret_key = "MiClaveUltraSecreta2025";

// --- Insertar registro con AES_ENCRYPT
$sql = "INSERT INTO cliente_tarjeta (nombre_persona, tarjeta_encriptada)
        VALUES ('$nombre_persona', AES_ENCRYPT('$numero_tarjeta', '$secret_key'))";

if (mysqli_query($con, $sql)) {
    $id_cliente = mysqli_insert_id($con);
    echo json_encode([
        "status" => "success",
        "message" => "Cliente insertado correctamente",
        "id_cliente" => $id_cliente
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Error al insertar: " . mysqli_error($con)
    ]);
}

mysqli_close($con);
?>
