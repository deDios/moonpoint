<?php
// db/WEB/moon/moon_i_pending_order.php
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

$required = ["organization_id"];
foreach ($required as $k) {
  if (!isset($in[$k]) || $in[$k] === "") {
    echo json_encode(["success"=>false,"error"=>"Falta parámetro obligatorio: $k"]); exit;
  }
}

$organization_id = (int)$in["organization_id"];
$source          = isset($in["source"]) ? (int)$in["source"] : 1; // 1=POS local, 2=otro canal
$channel         = isset($in["channel"]) ? trim((string)$in["channel"]) : null;
$label           = isset($in["label"]) ? trim((string)$in["label"]) : null;
$customer_name   = isset($in["customer_name"]) ? trim((string)$in["customer_name"]) : null;
$status          = isset($in["status"]) ? (int)$in["status"] : 0; // 0=pending
$external_ref    = isset($in["external_ref"]) ? trim((string)$in["external_ref"]) : null;
$created_by      = isset($in["created_by_user_id"]) ? (int)$in["created_by_user_id"] : null;
$attributes      = array_key_exists("attributes", $in) ? json_encode($in["attributes"], JSON_UNESCAPED_UNICODE) : null;

$items           = (isset($in["items"]) && is_array($in["items"])) ? $in["items"] : [];
$explicit_total  = isset($in["total"]) ? (float)$in["total"] : null;

$con = conectar();
if (!$con) { echo json_encode(["success"=>false,"error"=>"No se pudo conectar a la base de datos"]); exit; }
$con->set_charset("utf8mb4");

try {
  $con->begin_transaction();

  // Insert cabecera (total 0 temporal)
  $sql = "INSERT INTO moon_pending_order
          (organization_id, source, channel, label, customer_name, status, total, external_ref, created_by_user_id, attributes)
          VALUES (?,?,?,?,?,?,?,?,?,?)";
  $stmt = $con->prepare($sql);
  if (!$stmt) { throw new Exception("Error al preparar INSERT header"); }

  $zero = 0.0;
  $stmt->bind_param(
    "iisssi dsis",
    $organization_id, $source, $channel, $label, $customer_name,
    $status, $zero, $external_ref, $created_by, $attributes
  );
  // ^ Nota: espacios en el string de tipos son ignorados por PHP; se usa: i i s s s i d s i s

  // Mejor usar tipos sin espacios:
  $stmt->bind_param(
    "iisssidsis",
    $organization_id, $source, $channel, $label, $customer_name,
    $status, $zero, $external_ref, $created_by, $attributes
  );

  if (!$stmt->execute()) { throw new Exception("Error al insertar cabecera: ".$con->error); }
  $order_id = (int)$stmt->insert_id;
  $stmt->close();

  // Insert items
  $total = 0.0;
  if (!empty($items)) {
    $sqlI = "INSERT INTO moon_pending_order_item
             (pending_order_id, product_id, name, image_name, qty, unit_price, note)
             VALUES (?,?,?,?,?,?,?)";
    $stmtI = $con->prepare($sqlI);
    if (!$stmtI) { throw new Exception("Error al preparar INSERT items"); }

    foreach ($items as $it) {
      $pid   = (int)($it["product_id"] ?? 0);
      $name  = (string)($it["name"] ?? "");
      $img   = isset($it["image_name"]) ? (string)$it["image_name"] : null;
      $qty   = max(1, (int)($it["qty"] ?? 1));
      $price = (float)($it["unit_price"] ?? 0);
      $note  = isset($it["note"]) ? (string)$it["note"] : null;

      $stmtI->bind_param("iissids", $order_id, $pid, $name, $img, $qty, $price, $note);
      if (!$stmtI->execute()) { throw new Exception("Error al insertar item: ".$con->error); }
      $total += $qty * $price;
    }
    $stmtI->close();
  }

  if ($explicit_total !== null) { $total = $explicit_total; }

  $stmtU = $con->prepare("UPDATE moon_pending_order SET total = ? WHERE id = ? AND organization_id = ?");
  if (!$stmtU) { throw new Exception("Error al preparar UPDATE total"); }
  $stmtU->bind_param("dii", $total, $order_id, $organization_id);
  if (!$stmtU->execute()) { throw new Exception("Error al actualizar total: ".$con->error); }
  $stmtU->close();

  $con->commit();
  echo json_encode(["success"=>true,"message"=>"Orden pendiente creada","id"=>$order_id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $con->rollback();
  echo json_encode(["success"=>false,"error"=>$e->getMessage()]);
}
$con->close();
