<?php
header('Content-Type: application/json; charset=utf-8');

try {
  // TODO: ajusta esta conexión a tu entorno
  $pdo = new PDO("mysql:host=localhost;dbname=tu_db;charset=utf8mb4", "tu_usuario", "tu_password", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  $input = json_decode(file_get_contents('php://input'), true) ?: [];

  $product_id = isset($input['product_id']) ? intval($input['product_id']) : null;
  if (!$product_id) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "product_id es requerido"]);
    exit;
  }

  // Campos opcionales
  $fields = [];
  $params = [":id" => $product_id];

  if (isset($input['name']))        { $fields[] = "name = :name";               $params[":name"] = trim($input['name']); }
  if (isset($input['price']))       { $fields[] = "price = :price";             $params[":price"] = floatval($input['price']); }
  if (isset($input['category_id'])) { $fields[] = "category_id = :category_id"; $params[":category_id"] = intval($input['category_id']); }
  if (isset($input['stock']))       { $fields[] = "stock = :stock";             $params[":stock"] = intval($input['stock']); }
  if (array_key_exists('image_url', $input)) { // permite forzar null
    $fields[] = "image_url = :image_url";
    $params[":image_url"] = $input['image_url']; // puede ser string o null
  }
  if (isset($input['active']))      { $fields[] = "active = :active";           $params[":active"] = intval($input['active']); }

  if (empty($fields)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "No hay campos para actualizar"]);
    exit;
  }

  $sql = "UPDATE products SET ".implode(", ", $fields).", updated_at = NOW() WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // Devuelve el registro actualizado
  $q = $pdo->prepare("SELECT id, name, price, category_id, stock, image_url FROM products WHERE id = :id");
  $q->execute([":id" => $product_id]);
  $item = $q->fetch();

  echo json_encode([
    "ok" => true,
    "product_id" => $product_id,
    "item" => $item
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
