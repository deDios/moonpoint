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

if (empty($in['Id_company'])) {
  echo json_encode(["success" => false, "error" => "Falta parámetro obligatorio: Id_company"]);
  exit;
}

$Id_company = (int)$in['Id_company'];
$status     = isset($in['status']) && $in['status'] !== '' ? (int)$in['status'] : null;

$con = conectar();
if (!$con) {
  echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos"]);
  exit;
}
$con->set_charset('utf8mb4');

$sql = "SELECT p.*, pt.nombre as nombre_tipo_promo 
        FROM `moon_promo` p
        LEFT JOIN `moon_promo_type` pt ON p.promo_type = pt.id
        WHERE p.Id_company = ?";
        
$types = "i";
$args = [$Id_company];

if ($status !== null) {
    $sql .= " AND p.status = ?";
    $types .= "i";
    $args[] = $status;
}

$stmt = $con->prepare($sql);
if (!$stmt) {
  echo json_encode(["success" => false, "error" => "Error al preparar consulta: " . $con->error]);
  $con->close();
  exit;
}

$stmt->bind_param($types, ...$args);
$stmt->execute();
$result = $stmt->get_result();

$promos = [];
while ($row = $result->fetch_assoc()) {
    $promos[] = $row;
}

echo json_encode(["success" => true, "data" => $promos]);

$stmt->close();
$con->close();