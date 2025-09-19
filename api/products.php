<?php
require_once '../config/database.php';

// Handle product requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetProducts();
} elseif ($method === 'POST') {
  handleCreateProduct($input);
} elseif ($method === 'PUT') {
  handleUpdateProduct($input);
} elseif ($method === 'DELETE') {
  handleDeleteProduct();
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetProducts()
{
  $db = getDB();

  // Get query parameters
  $product_id = $_GET['id'] ?? '';
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $category = $_GET['category'] ?? '';
  $search = $_GET['search'] ?? '';
  $seller_id = $_GET['seller_id'] ?? '';
  $count_only = $_GET['count_only'] ?? false;

  if (!empty($product_id)) {
    $query = "
            SELECT p.*, u.name as seller_name, u.is_verified, c.name as category_name
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.is_available = 1
        ";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
      sendResponse(true, 'Product retrieved successfully', ['product' => $product]);
    } else {
      sendResponse(false, 'Product not found', null, 404);
    }
    return;
  }

  if ($count_only) {
    $query = "SELECT COUNT(*) as count FROM products WHERE is_available = 1";
    $params = [];

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();

    sendResponse(true, 'Product count retrieved', ['data' => ['count' => $result['count']]]);
    return;
  }

  // Build query
  $query = "
        SELECT p.*, u.name as seller_name, u.is_verified, c.name as category_name
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1
    ";

  $params = [];

  if (!empty($category)) {
    $query .= " AND c.name = ?";
    $params[] = $category;
  }

  if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
  }

  if (!empty($seller_id)) {
    $query .= " AND p.seller_id = ?";
    $params[] = $seller_id;
  }

  $query .= " ORDER BY p.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $products = $stmt->fetchAll();

  // Get total count for pagination
  $count_query = "
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1
    ";

  $count_params = [];

  if (!empty($category)) {
    $count_query .= " AND c.name = ?";
    $count_params[] = $category;
  }

  if (!empty($search)) {
    $count_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
  }

  if (!empty($seller_id)) {
    $count_query .= " AND p.seller_id = ?";
    $count_params[] = $seller_id;
  }

  $stmt = $db->prepare($count_query);
  $stmt->execute($count_params);
  $total = $stmt->fetch()['total'];

  sendResponse(true, 'Products retrieved successfully', [
    'products' => $products,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleCreateProduct($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only farmers can create products
  if ($user['user_type'] !== 'farmer') {
    sendResponse(false, 'Only farmers can create products', null, 403);
  }

  $required_fields = ['name', 'price', 'quantity', 'unit'];
  $missing_fields = validateRequiredFields($data, $required_fields);

  if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
  }

  // Validate numeric fields
  if (!is_numeric($data['price']) || $data['price'] <= 0) {
    sendResponse(false, 'Price must be a positive number', null, 400);
  }

  if (!is_numeric($data['quantity']) || $data['quantity'] <= 0) {
    sendResponse(false, 'Quantity must be a positive number', null, 400);
  }

  $db = getDB();

  try {
    $stmt = $db->prepare("
            INSERT INTO products (seller_id, category_id, name, description, price, quantity, unit, image, is_available) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $stmt->execute([
      $user['id'],
      $data['category_id'] ?? null,
      sanitizeInput($data['name']),
      sanitizeInput($data['description'] ?? ''),
      $data['price'],
      $data['quantity'],
      sanitizeInput($data['unit']),
      $data['image'] ?? null,
      1
    ]);

    $product_id = $db->lastInsertId();

    // Get the created product with seller info
    $stmt = $db->prepare("
            SELECT p.*, u.name as seller_name, u.is_verified, c.name as category_name
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    sendResponse(true, 'Product created successfully', ['product' => $product]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to create product: ' . $e->getMessage(), null, 500);
  }
}

function handleUpdateProduct($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $product_id = $data['id'] ?? '';
  if (empty($product_id)) {
    sendResponse(false, 'Product ID required', null, 400);
  }

  $db = getDB();

  // Check if product exists and user owns it
  $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
  $stmt->execute([$product_id, $user['id']]);
  $product = $stmt->fetch();

  if (!$product) {
    sendResponse(false, 'Product not found or access denied', null, 404);
  }

  // Build update query dynamically
  $update_fields = [];
  $params = [];

  $allowed_fields = ['name', 'description', 'price', 'quantity', 'unit', 'category_id', 'image', 'is_available'];

  foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
      $update_fields[] = "$field = ?";
      $params[] = $field === 'is_available' ? (int)$data[$field] : $data[$field];
    }
  }

  if (empty($update_fields)) {
    sendResponse(false, 'No fields to update', null, 400);
  }

  $params[] = $product_id;

  try {
    $query = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // Get updated product
    $stmt = $db->prepare("
            SELECT p.*, u.name as seller_name, u.is_verified, c.name as category_name
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
    $stmt->execute([$product_id]);
    $updated_product = $stmt->fetch();

    sendResponse(true, 'Product updated successfully', ['product' => $updated_product]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to update product: ' . $e->getMessage(), null, 500);
  }
}

function handleDeleteProduct()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $product_id = $_GET['id'] ?? '';
  if (empty($product_id)) {
    sendResponse(false, 'Product ID required', null, 400);
  }

  $db = getDB();

  // Check if product exists and user owns it
  $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
  $stmt->execute([$product_id, $user['id']]);
  $product = $stmt->fetch();

  if (!$product) {
    sendResponse(false, 'Product not found or access denied', null, 404);
  }

  try {
    // Soft delete by setting is_available to false
    $stmt = $db->prepare("UPDATE products SET is_available = 0 WHERE id = ?");
    $stmt->execute([$product_id]);

    sendResponse(true, 'Product deleted successfully');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to delete product: ' . $e->getMessage(), null, 500);
  }
}
