<?php
require_once '../config/database.php';

// Handle buyer request requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetRequests();
} elseif ($method === 'POST') {
  handleCreateRequest($input);
} elseif ($method === 'PUT') {
  handleUpdateRequest($input);
} elseif ($method === 'DELETE') {
  handleDeleteRequest();
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetRequests()
{
  $db = getDB();

  // Get query parameters
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $buyer_id = $_GET['buyer_id'] ?? '';

  // Build query
  $query = "
        SELECT r.*, u.name as buyer_name
        FROM buyer_requests r
        LEFT JOIN users u ON r.buyer_id = u.id
        WHERE r.is_active = 1
    ";

  $params = [];

  if (!empty($buyer_id)) {
    $query .= " AND r.buyer_id = ?";
    $params[] = $buyer_id;
  }

  $query .= " ORDER BY r.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $requests = $stmt->fetchAll();

  // Get total count for pagination
  $count_query = "SELECT COUNT(*) as total FROM buyer_requests r WHERE r.is_active = 1";
  $count_params = [];

  if (!empty($buyer_id)) {
    $count_query .= " AND r.buyer_id = ?";
    $count_params[] = $buyer_id;
  }

  $stmt = $db->prepare($count_query);
  $stmt->execute($count_params);
  $total = $stmt->fetch()['total'];

  sendResponse(true, 'Requests retrieved successfully', [
    'requests' => $requests,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleCreateRequest($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only buyers can create requests
  if ($user['user_type'] !== 'buyer') {
    sendResponse(false, 'Only buyers can create requests', null, 403);
  }

  $required_fields = ['title', 'description'];
  $missing_fields = validateRequiredFields($data, $required_fields);

  if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
  }

  $db = getDB();

  try {
    $stmt = $db->prepare("
            INSERT INTO buyer_requests (buyer_id, title, description, quantity, unit, max_price, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

    $stmt->execute([
      $user['id'],
      sanitizeInput($data['title']),
      sanitizeInput($data['description']),
      $data['quantity'] ?? null,
      sanitizeInput($data['unit'] ?? ''),
      $data['max_price'] ?? null,
      1
    ]);

    $request_id = $db->lastInsertId();

    // Get the created request with buyer info
    $stmt = $db->prepare("
            SELECT r.*, u.name as buyer_name
            FROM buyer_requests r
            LEFT JOIN users u ON r.buyer_id = u.id
            WHERE r.id = ?
        ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    sendResponse(true, 'Request created successfully', ['request' => $request]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to create request: ' . $e->getMessage(), null, 500);
  }
}

function handleUpdateRequest($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $request_id = $data['id'] ?? '';
  if (empty($request_id)) {
    sendResponse(false, 'Request ID required', null, 400);
  }

  $db = getDB();

  // Check if request exists and user owns it
  $stmt = $db->prepare("SELECT * FROM buyer_requests WHERE id = ? AND buyer_id = ?");
  $stmt->execute([$request_id, $user['id']]);
  $request = $stmt->fetch();

  if (!$request) {
    sendResponse(false, 'Request not found or access denied', null, 404);
  }

  // Build update query dynamically
  $update_fields = [];
  $params = [];

  $allowed_fields = ['title', 'description', 'quantity', 'unit', 'max_price', 'is_active'];

  foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
      $update_fields[] = "$field = ?";
      $params[] = $field === 'is_active' ? (int)$data[$field] : $data[$field];
    }
  }

  if (empty($update_fields)) {
    sendResponse(false, 'No fields to update', null, 400);
  }

  $params[] = $request_id;

  try {
    $query = "UPDATE buyer_requests SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // Get updated request
    $stmt = $db->prepare("
            SELECT r.*, u.name as buyer_name
            FROM buyer_requests r
            LEFT JOIN users u ON r.buyer_id = u.id
            WHERE r.id = ?
        ");
    $stmt->execute([$request_id]);
    $updated_request = $stmt->fetch();

    sendResponse(true, 'Request updated successfully', ['request' => $updated_request]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to update request: ' . $e->getMessage(), null, 500);
  }
}

function handleDeleteRequest()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $request_id = $_GET['id'] ?? '';
  if (empty($request_id)) {
    sendResponse(false, 'Request ID required', null, 400);
  }

  $db = getDB();

  // Check if request exists and user owns it
  $stmt = $db->prepare("SELECT * FROM buyer_requests WHERE id = ? AND buyer_id = ?");
  $stmt->execute([$request_id, $user['id']]);
  $request = $stmt->fetch();

  if (!$request) {
    sendResponse(false, 'Request not found or access denied', null, 404);
  }

  try {
    // Soft delete by setting is_active to false
    $stmt = $db->prepare("UPDATE buyer_requests SET is_active = 0 WHERE id = ?");
    $stmt->execute([$request_id]);

    sendResponse(true, 'Request deleted successfully');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to delete request: ' . $e->getMessage(), null, 500);
  }
}
