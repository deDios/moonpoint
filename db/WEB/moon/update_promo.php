<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "error" => "Método no permitido. Usa POST."]);
  exit;
}

$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if (!$path || !file_exists($path)) {
  echo json_encode(["success" => false, "error" => "No se encontró Conexion.php"]);
  exit;
}
include $path;

$in = json_decode(file_get_contents("php://input"), true);
if (!is_array($in)) { $in = []; }

if (!isset($in['id']) || $in['id'] === "") {
  echo json_encode(["success" => false, "error" => "Falta parámetro obligatorio: id"]);
  exit;
}

$id         = (int)$in['id'];
$nombre     = array_key_exists('nombre', $in)     ? trim((string)$in['nombre']) : null;
$porcentaje = array_key_exists('porcentaje', $in) && $in['porcentaje'] !== '' ? (int)$in['porcentaje'] : null;
$cantidad   = array_key_exists('cantidad', $in)   && $in['cantidad'] !== '' ? (int)$in['cantidad'] : null;
$promo_type = array_key_exists('promo_type', $in) && $in['promo_type'] !== '' ? (int)$in['promo_type'] : null;
$status     = array_key_exists('status', $in)     && $in['status'] !== '' ? (int)$in['status'] : null;

$con = conectar();
if (!$con) {
  echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos"]);
  exit;
}
$con->set_charset('utf8mb4');

// Build dinámico
$sets  = [];
$types = "";
$args  = [];

if ($nombre !== null) {
  $sets[]  = "nombre = ?";
  $types  .= "s";
  $args[]  = $nombre;
}
if ($porcentaje !== null) {
  $sets[]  = "porcentaje = ?";
  $types  .= "i";
  $args[]  = $porcentaje;
}
if ($cantidad !== null) {
  $sets[]  = "cantidad = ?";
  $types  .= "i";
  $args[]  = $cantidad;
}
if ($promo_type !== null) {
  $sets[]  = "promo_type = ?";
  $types  .= "i";
  $args[]  = $promo_type;
}
if ($status !== null) {
  $sets[]  = "status = ?";
  $types  .= "i";
  $args[]  = $status;
}

if (count($sets) === 0) {
    echo json_encode(["success" => false, "error" => "No hay campos para actualizar"]);
    exit;
}

// siempre tocamos updated_at (dependiendo de tu MySQL, esto puede ser automático, pero lo dejamos por consistencia)
$sets[] = "updated_at = NOW()";

$sql = "UPDATE `moon_promo` SET " . implode(", ", $sets) . " WHERE id = ?";
$types .= "i";
$args[]  = $id;

$stmt = $con->prepare($sql);
if (!$stmt) {
  echo json_encode(["success" => false, "error" => "Error al preparar consulta: " . $con->error]);
  $con->close();
  exit;
}

$stmt->bind_param($types, ...$args);

if ($stmt->execute()) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "error" => "Error al actualizar: " . $stmt->error]);
}

$stmt->close();
$con->close();