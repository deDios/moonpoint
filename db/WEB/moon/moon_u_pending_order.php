<?php
// db/WEB/moon/moon_u_pending_order.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "error" => "Método no permitido. Usa POST."]);
  exit;
}

$path = realpath("/home/site/wwwroot/db/conn/Conexion.php");
if ($path && file_exists($path)) { include $path; }
else { echo json_encode(["success"=>false,"error"=>"No se encontró Conexion.php en la ruta $path"]); exit; }

$in = json_decode(file_get_contents("php://input"), true);
if (!is_array($in)) { $in = []; }

$required = ["organization_id","id"];
foreach ($required as $k) {
  if (!isset($in[$k]) || $in[$k] === "") { echo json_encode(["success"=>false,"error"=>"Falta parámetro obligatorio: $k"]); exit; }
}

$organization_id = (int)$in["organization_id"];
$id              = (int)$in["id"];

$fields = [];
$binds  = [];
$types  = "";

$map = [
  "label"         => "s",
  "customer_name" => "s",
  "status"        => "i",
  "source"        => "i",
  "channel"       => "s",
  "external_ref"  => "s",
  "total"         => "d"
];

foreach ($map as $key => $t) {
  if (array_key_exists($key, $in)) {
    $fields[] = "$key = ?";
    $binds[]  = $in[$key];
    $types   .= $t;
  }
}

// attributes (JSON)
if (array_key_exists("attributes", $in)) {
  $fields[] = "attributes = ?";
  $binds[]  = is_null($in["attributes"]) ? null : json_encode($in["attributes"], JSON_UNESCAPED_UNICODE);
  $types   .= "s";
}

// ¿Reemplazar items?
$replace_items = !empty($in["replace_items"]) && !empty($in["items"]) && is_array($in["items"]);

$con = conectar();
if (!$con) { echo json_encode(["success"=>false,"error"=>"No se pudo conectar a la base de datos"]); exit; }
$con->set_charset("utf8mb4");

try {
  $con->begin_transaction();

  if (!empty($fields)) {
    $sql = "UPDATE moon_pending_order SET ".implode(", ", $fields)." WHERE id = ? AND organization_id = ?";
    $stmt = $con->prepare($sql);
    if (!$stmt) { throw new Exception("Error al preparar UPDATE header"); }

    $typesFull = $types . "ii";
    $bindsFull = $binds;
    $bindsFull[] = $id;
    $bindsFull[] = $organization_id;

    $stmt->bind_param($typesFull, ...$bindsFull);
    if (!$stmt->execute()) { throw new Exception("Error al actualizar cabecera: ".$con->error); }
    $stmt->close();
  }

  if ($replace_items) {
    // borrar existentes
    $stmtD = $con->prepare("DELETE FROM moon_pending_order_item WHERE pending_order_id = ?");
    if (!$stmtD) { throw new Exception("Error al preparar DELETE items"); }
    $stmtD->bind_param("i", $id);
    if (!$stmtD->execute()) { throw new Exception("Error al borrar items: ".$con->error); }
    $stmtD->close();

    // insertar nuevos
    $sqlI = "INSERT INTO moon_pending_order_item
             (pending_order_id, product_id, name, image_name, qty, unit_price, note)
             VALUES (?,?,?,?,?,?,?)";
    $stmtI = $con->prepare($sqlI);
    if (!$stmtI) { throw new Exception("Error al preparar INSERT items"); }

    $total = 0.0;
    foreach ($in["items"] as $it) {
      $pid   = (int)($it["product_id"] ?? 0);
      $name  = (string)($it["name"] ?? "");
      $img   = isset($it["image_name"]) ? (string)$it["image_name"] : null;
      $qty   = max(1, (int)($it["qty"] ?? 1));
      $price = (float)($it["unit_price"] ?? 0);
      $note  = isset($it["note"]) ? (string)$it["note"] : null;

      $stmtI->bind_param("iissids", $id, $pid, $name, $img, $qty, $price, $note);
      if (!$stmtI->execute()) { throw new Exception("Error al insertar item: ".$con->error); }
      $total += $qty * $price;
    }
    $stmtI->close();

    // Si no mandaron total explícito, recalculamos
    if (!array_key_exists("total", $in)) {
      $stmtU = $con->prepare("UPDATE moon_pending_order SET total = ? WHERE id = ? AND organization_id = ?");
      if (!$stmtU) { throw new Exception("Error al preparar UPDATE total"); }
      $stmtU->bind_param("dii", $total, $id, $organization_id);
      if (!$stmtU->execute()) { throw new Exception("Error al actualizar total: ".$con->error); }
      $stmtU->close();
    }
  }

  $con->commit();
  echo json_encode(["success"=>true, "message"=>"Orden pendiente actualizada"]);
} catch (Throwable $e) {
  $con->rollback();
  echo json_encode(["success"=>false,"error"=>$e->getMessage()]);
}
$con->close();
