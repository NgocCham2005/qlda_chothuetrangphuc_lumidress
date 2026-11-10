<?php
/* client/ajax_product.php – returns JSON for a single costume (quick-view) */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error'=>true]); exit; }

$stmt = $conn->prepare(
  "SELECT c.*, cat.name AS cat_name
   FROM costumes c
   LEFT JOIN categories cat ON cat.id = c.category_id
   WHERE c.id = ? AND c.status = 'available'
   LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) { echo json_encode(['error'=>true]); exit; }

// Resolve image URL
$imageUrl = null;
if ($row['image']) {
  $abs = '../uploads/'.$row['image'];
  if (file_exists($abs)) {
    $imageUrl = BASE_URL.'uploads/'.rawurlencode($row['image']);
  }
}

// Strip tags from description for safety
$desc = $row['description'] ? strip_tags($row['description'], '<p><br><strong><em><ul><li>') : '';

echo json_encode([
  'id'          => $row['id'],
  'name'        => htmlspecialchars($row['name']),
  'cat_name'    => htmlspecialchars($row['cat_name'] ?? ''),
  'size'        => htmlspecialchars($row['size']),
  'color'       => htmlspecialchars($row['color'] ?? ''),
  'price_fmt'   => format_price($row['rental_price']).'₫',
  'deposit_fmt' => format_price($row['deposit']),
  'image'       => $imageUrl,
  'description' => $desc,
], JSON_UNESCAPED_UNICODE);

