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

if (empty($in['id_promo']) || empty($in['Id_company'])) {
  echo json_encode(["success" => false, "error" => "Faltan parámetros obligatorios: id_promo, Id_company"]);
  exit;
}

$id_promo     = (int)$in['id_promo'];
$Id_company   = (int)$in['Id_company'];

// Opcionales (si vienen vacíos o no existen, se quedan como null)
$id_cliente   = !empty($in['id_cliente'])   ? (int)$in['id_cliente']   : null;
$id_producto  = !empty($in['id_producto'])  ? (int)$in['id_producto']  : null;
$id_compuesto = !empty($in['id_compuesto']) ? (int)$in['id_compuesto'] : null;
$cantidad     = isset($in['cantidad']) && $in['cantidad'] !== '' ? (int)$in['cantidad'] : 0;
$start_date   = !empty($in['start_date'])   ? trim((string)$in['start_date']) : null;
$end_date     = !empty($in['end_date'])     ? trim((string)$in['end_date'])   : null;

$con = conectar();
if (!$con) {
  echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos"]);
  exit;
}
$con->set_charset('utf8mb4');

$sql = "INSERT INTO `moon_lista_promo` (`id_promo`, `id_cliente`, `id_producto`, `id_compuesto`, `cantidad`, `start_date`, `end_date`, `Id_company`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
if (!$stmt) {
  echo json_encode(["success" => false, "error" => "Error al preparar consulta: " . $con->error]);
  $con->close();
  exit;
}

// Vinculamos parámetros: i=int, s=string
// PHP maneja el null automáticamente en bind_param
$stmt->bind_param("iiiiissi", $id_promo, $id_cliente, $id_producto, $id_compuesto, $cantidad, $start_date, $end_date, $Id_company);

if ($stmt->execute()) {
  echo json_encode(["success" => true, "inserted_id" => $stmt->insert_id]);
} else {
  echo json_encode(["success" => false, "error" => "Error al insertar: " . $stmt->error]);
}

$stmt->close();
$con->close();