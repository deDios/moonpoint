<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "error" => "Método no permitido. Usa POST."]);
  exit;
}

$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if ($path && file_exists($path)) { include $path; }
else { echo json_encode(["success" => false, "error" => "No se encontró Conexion.php en la ruta $path"]); exit; }

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) { $input = []; }

$required = ["id", "organization_id"];
foreach ($required as $k) {
  if (!isset($input[$k]) || $input[$k] === "") {
    echo json_encode(["success" => false, "error" => "Falta parámetro obligatorio: $k"]);
    exit;
  }
}

$id              = (int)$input["id"];
$organization_id = (int)$input["organization_id"];

$fields = [];
$params = [];
$types  = "";

// Campos opcionales
if (array_key_exists("category_id", $input)) { $fields[] = "category_id = ?"; $params[] = (int)$input["category_id"]; $types .= "i"; }
if (array_key_exists("sku", $input))         { $fields[] = "sku = ?";         $params[] = trim((string)$input["sku"]); $types .= "s"; }
if (array_key_exists("name", $input))        { $fields[] = "name = ?";        $params[] = trim((string)$input["name"]); $types .= "s"; }
if (array_key_exists("description", $input)) { $fields[] = "description = ?"; $params[] = trim((string)$input["description"]); $types .= "s"; }
if (array_key_exists("price", $input))       { $fields[] = "price = ?";       $params[] = (float)$input["price"]; $types .= "d"; }
if (array_key_exists("cost", $input))        { $fields[] = "cost = ?";        $params[] = isset($input["cost"]) ? (float)$input["cost"] : null; $types .= "d"; }
if (array_key_exists("image_name", $input))  { $fields[] = "image_name = ?";  $params[] = ($input["image_name"] === null) ? null : trim((string)$input["image_name"]); $types .= "s"; }
if (array_key_exists("is_active", $input))   { $fields[] = "is_active = ?";   $params[] = (int)$input["is_active"]; $types .= "i"; }
if (array_key_exists("sort_order", $input))  { $fields[] = "sort_order = ?";  $params[] = (int)$input["sort_order"]; $types .= "i"; }
if (array_keyExists = array_key_exists("attributes", $input)) {
  $attr = $input["attributes"];
  $attrJson = ($attr === null) ? null : (is_string($attr) ? $attr : json_encode($attr, JSON_UNESCAPED_UNICODE));
  $fields[] = "attributes = CAST(? AS JSON)";
  $params[] = $attrJson;
  $types .= "s";
}

if (empty($fields)) {
  echo json_encode(["success" => false, "error" => "No hay campos a actualizar"]);
  exit;
}

$con = conectar();
if (!$con) { echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos"]); exit; }

$sql = "UPDATE moon_product SET " . implode(", ", $fields) . " WHERE id = ? AND organization_id = ?";
$params[] = $id;
$params[] = $organization_id;
$types   .= "ii";

$stmt = $con->prepare($sql);
if (!$stmt) { echo json_encode(["success" => false, "error" => "Error al preparar consulta"]); $con->close(); exit; }

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Producto actualizado"]);
  } else {
    echo json_encode(["success" => false, "error" => "No se encontró el producto o no hubo cambios"]);
  }
} else {
  if ($con->errno == 1062) {
    echo json_encode(["success" => false, "error" => "SKU o nombre duplicado en la organización"]);
  } else if ($con->errno == 1452) {
    echo json_encode(["success" => false, "error" => "Categoría no válida"]);
  } else {
    echo json_encode(["success" => false, "error" => "Error al actualizar: " . $con->error]);
  }
}

$stmt->close();
$con->close();
