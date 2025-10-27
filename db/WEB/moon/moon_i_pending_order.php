<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "error" => "Método no permitido. Usa POST."]);
  exit;
}

$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if (!$path || !file_exists($path)) {
  echo json_encode(["success" => false, "error" => "No se encontró Conexion.php en $path"]);
  exit;
}
include $path;

// Lee body
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) { $input = []; }

// Param obligatorios
if (!isset($input['organization_id']) || $input['organization_id'] === "") {
  echo json_encode(["success" => false, "error" => "Falta parámetro obligatorio: organization_id"]);
  exit;
}
// Acepta label o name para compat
$label = null;
if (isset($input['label']) && $input['label'] !== '') {
  $label = trim((string)$input['label']);
} elseif (isset($input['name']) && $input['name'] !== '') {
  $label = trim((string)$input['name']);
} else {
  echo json_encode(["success" => false, "error" => "Falta parámetro obligatorio: label/name"]);
  exit;
}

$organization_id = (int)$input["organization_id"];
$customer_name   = isset($input["customer_name"]) ? trim((string)$input["customer_name"]) : null;
$source          = isset($input["source"]) ? trim((string)$input["source"]) : "pos";
$channel         = isset($input["channel"]) ? trim((string)$input["channel"]) : "local";
$status          = isset($input["status"]) ? trim((string)$input["status"]) : "pending";
$total           = isset($input["total"]) ? (float)$input["total"] : 0.0;
$external_ref    = isset($input["external_ref"]) ? trim((string)$input["external_ref"]) : null;
$created_by_user = isset($input["created_by_user"]) ? (int)$input["created_by_user"] : null;
$attributes      = isset($input["attributes"]) ? json_encode($input["attributes"]) : null;

$con = conectar();
if (!$con) {
  echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos"]);
  exit;
}
$con->set_charset('utf8mb4');
$con->autocommit(true);

// Usa base/tabla totalmente calificada
$sql = "INSERT INTO `moon_point`.`moon_pending_order`
  (organization_id, source, channel, label, customer_name, status, total, external_ref, created_by_user, attributes)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
if (!$stmt) {
  echo json_encode(["success" => false, "error" => "Error al preparar consulta: " . $con->error]);
  $con->close(); exit;
}

$stmt->bind_param(
  "isssssdsis",
  $organization_id,
  $source,
  $channel,
  $label,
  $customer_name,
  $status,
  $total,
  $external_ref,
  $created_by_user,
  $attributes
);

if ($stmt->execute()) {
  echo json_encode([
    "success" => true,
    "message" => "Orden pendiente creada",
    "id"      => (int)$stmt->insert_id
  ]);
} else {
  echo json_encode(["success" => false, "error" => "Error al insertar: " . $stmt->error]);
}

$stmt->close();
$con->close();
