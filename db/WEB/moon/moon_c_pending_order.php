<?php
// db/WEB/moon/moon_c_pending_order.php
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

if (!isset($in["organization_id"])) {
  echo json_encode(["success"=>false,"error"=>"Falta parámetro obligatorio: organization_id"]); exit;
}

$organization_id = (int)$in["organization_id"];
$status          = $in["status"] ?? 0;        // 0=pending por defecto, "all" para todos
$since           = isset($in["since"]) ? trim((string)$in["since"]) : null; // 'YYYY-MM-DD HH:MM:SS'
$include_items   = !empty($in["include_items"]);
$page            = max(1, (int)($in["page"] ?? 1));
$page_size       = max(1, min(500, (int)($in["page_size"] ?? 200)));
$offset          = ($page - 1) * $page_size;

$con = conectar();
if (!$con) { echo json_encode(["success"=>false,"error"=>"No se pudo conectar a la base de datos"]); exit; }
$con->set_charset("utf8mb4");

/* ====== WHERE dinámico ====== */
$where  = " WHERE organization_id = ?";
$params = [$organization_id];
$types  = "i";

if ($status !== "all") {
  $where .= " AND status = ?";
  $params[] = (int)$status;
  $types   .= "i";
}
if (!empty($since)) {
  $where .= " AND updated_at > ?";
  $params[] = $since;
  $types   .= "s";
}

/* ====== Total ====== */
$sqlCount = "SELECT COUNT(*) AS c FROM moon_pending_order {$where}";
$stmt = $con->prepare($sqlCount);
if (!$stmt) { echo json_encode(["success"=>false,"error"=>"Error al preparar consulta (count)"]); $con->close(); exit; }

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$total = (int)($res->fetch_assoc()["c"] ?? 0);
$stmt->close();

/* ====== Datos paginados ====== */
$sql = "SELECT id, organization_id, source, channel, label, customer_name,
               status, total, external_ref, attributes, created_by_user_id,
               created_at, updated_at
        FROM moon_pending_order
        {$where}
        ORDER BY updated_at DESC
        LIMIT ? OFFSET ?";
$stmt = $con->prepare($sql);
if (!$stmt) { echo json_encode(["success"=>false,"error"=>"Error al preparar consulta (lista)"]); $con->close(); exit; }

$types2  = $types . "ii";
$params2 = $params;
$params2[] = $page_size;
$params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$ids  = [];
while ($r = $res->fetch_assoc()) {
  $r["total"] = (float)$r["total"];
  $r["status"] = (int)$r["status"];
  $r["source"] = (int)$r["source"];
  $r["created_by_user_id"] = is_null($r["created_by_user_id"]) ? null : (int)$r["created_by_user_id"];
  $r["items"] = []; // si include_items luego se llena
  $rows[] = $r;
  $ids[]  = (int)$r["id"];
}
$stmt->close();

/* ====== Items ====== */
if ($include_items && !empty($ids)) {
  $place = implode(',', array_fill(0, count($ids), '?'));
  $sqlI = "SELECT pending_order_id, id, product_id, name, image_name, qty, unit_price, note, created_at, updated_at
           FROM moon_pending_order_item
           WHERE pending_order_id IN ($place)
           ORDER BY id ASC";
  $stmtI = $con->prepare($sqlI);
  if ($stmtI) {
    $typesI = str_repeat('i', count($ids));
    $stmtI->bind_param($typesI, ...$ids);
    $stmtI->execute();
    $resI = $stmtI->get_result();

    // indexar por id
    $byId = [];
    foreach ($rows as $idx => $row) { $byId[$row["id"]] = $idx; }

    while ($it = $resI->fetch_assoc()) {
      $pid = (int)$it["pending_order_id"];
      if (isset($byId[$pid])) {
        $it["id"]         = (int)$it["id"];
        $it["product_id"] = (int)$it["product_id"];
        $it["qty"]        = (int)$it["qty"];
        $it["unit_price"] = (float)$it["unit_price"];
        $rows[$byId[$pid]]["items"][] = $it;
      }
    }
    $stmtI->close();
  }
}

echo json_encode([
  "success"   => true,
  "total"     => $total,
  "page"      => $page,
  "page_size" => $page_size,
  "data"      => $rows
], JSON_UNESCAPED_UNICODE);
$con->close();
