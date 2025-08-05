<?php
header('Content-Type: application/json');

// --- Incluir la conexión
$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if ($path && file_exists($path)) {
    include $path;
} else {
    die(json_encode(["error" => "No se encontró Conexion.php en la ruta $path"]));
}

// --- Leer datos de entrada (JSON)
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['nombre_persona']) || !isset($input['numero_tarjeta'])) {
    die(json_encode(["error" => "Faltan parámetros obligatorios: 'nombre_persona' y 'numero_tarjeta'"]));
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
