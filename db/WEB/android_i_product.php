<?php
header('Content-Type: application/json; charset=utf-8');

try {
  // TODO: ajusta esta conexión a tu entorno
  $pdo = new PDO("mysql:host=localhost;dbname=tu_db;charset=utf8mb4", "tu_usuario", "tu_password", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  $input = json_decode(file_get_contents('php://input'), true) ?: [];

  $name        = isset($input['name']) ? trim($input['name']) : null;
  $price       = isset($input['price']) ? floatval($input['price']) : null;
  $category_id = isset($input['category_id']) ? intval($input['category_id']) : null;

  $stock       = isset($input['stock']) ? intval($input['stock']) : 0;
  $image_url   = array_key_exists('image_url', $input) ? $input['image_url'] : null; // permite null
  $active      = isset($input['active']) ? intval($input['active']) : 1;

  if (!$name || $price === null || $category_id === null) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "name, price y category_id son requeridos"]);
    exit;
  }

  $stmt = $pdo->prepare("
    INSERT INTO products (name, price, category_id, stock, image_url, active, created_at, updated_at)
    VALUES (:name, :price, :category_id, :stock, :image_url, :active, NOW(), NOW())
  ");
  $stmt->execute([
    ":name" => $name,
    ":price" => $price,
    ":category_id" => $category_id,
    ":stock" => $stock,
    ":image_url" => $image_url,
    ":active" => $active
  ]);

  $id = intval($pdo->lastInsertId());

  // Devuelve el registro creado
  $q = $pdo->prepare("SELECT id, name, price, category_id, stock, image_url FROM products WHERE id = :id");
  $q->execute([":id" => $id]);
  $item = $q->fetch();

  echo json_encode([
    "ok" => true,
    "product_id" => $id,
    "item" => $item
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
