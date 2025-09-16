<?php
require_once '../../config/database.php';

// Handle admin user management requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetUsers();
} elseif ($method === 'PUT') {
  handleUpdateUser($input);
} elseif ($method === 'DELETE') {
  handleDeleteUser();
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetUsers()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can access this
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Admin access required', null, 403);
  }

  $db = getDB();

  // Get query parameters
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $user_type = $_GET['user_type'] ?? '';
  $count_only = $_GET['count_only'] ?? false;

  if ($count_only) {
    $query = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
    $params = [];

    if (!empty($user_type)) {
      $query .= " AND user_type = ?";
      $params[] = $user_type;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();

    sendResponse(true, 'User count retrieved', ['count' => $result['count']]);
    return;
  }

  // Build query
  $query = "
        SELECT id, name, email, phone, address, user_type, is_verified, is_active, created_at
        FROM users
        WHERE 1=1
    ";

  $params = [];

  if (!empty($user_type)) {
    $query .= " AND user_type = ?";
    $params[] = $user_type;
  }

  $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $users = $stmt->fetchAll();

  // Get total count for pagination
  $count_query = "SELECT COUNT(*) as total FROM users WHERE 1=1";
  $count_params = [];

  if (!empty($user_type)) {
    $count_query .= " AND user_type = ?";
    $count_params[] = $user_type;
  }

  $stmt = $db->prepare($count_query);
  $stmt->execute($count_params);
  $total = $stmt->fetch()['total'];

  sendResponse(true, 'Users retrieved successfully', [
    'users' => $users,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleUpdateUser($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can access this
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Admin access required', null, 403);
  }

  $user_id = $data['id'] ?? '';
  if (empty($user_id)) {
    sendResponse(false, 'User ID required', null, 400);
  }

  $db = getDB();

  // Check if user exists
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $target_user = $stmt->fetch();

  if (!$target_user) {
    sendResponse(false, 'User not found', null, 404);
  }

  // Build update query dynamically
  $update_fields = [];
  $params = [];

  $allowed_fields = ['is_verified', 'is_active', 'name', 'email', 'phone', 'address'];

  foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
      $update_fields[] = "$field = ?";
      $params[] = $field === 'is_verified' || $field === 'is_active' ? (int)$data[$field] : sanitizeInput($data[$field]);
    }
  }

  if (empty($update_fields)) {
    sendResponse(false, 'No fields to update', null, 400);
  }

  $params[] = $user_id;

  try {
    $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // Get updated user
    $stmt = $db->prepare("
            SELECT id, name, email, phone, address, user_type, is_verified, is_active, created_at
            FROM users WHERE id = ?
        ");
    $stmt->execute([$user_id]);
    $updated_user = $stmt->fetch();

    sendResponse(true, 'User updated successfully', ['user' => $updated_user]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to update user: ' . $e->getMessage(), null, 500);
  }
}

function handleDeleteUser()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can access this
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Admin access required', null, 403);
  }

  $user_id = $_GET['id'] ?? '';
  if (empty($user_id)) {
    sendResponse(false, 'User ID required', null, 400);
  }

  // Don't allow deleting self
  if ($user['id'] == $user_id) {
    sendResponse(false, 'Cannot delete your own account', null, 400);
  }

  $db = getDB();

  // Check if user exists
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $target_user = $stmt->fetch();

  if (!$target_user) {
    sendResponse(false, 'User not found', null, 404);
  }

  try {
    // Soft delete by setting is_active to false
    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    $stmt->execute([$user_id]);

    sendResponse(true, 'User deleted successfully');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to delete user: ' . $e->getMessage(), null, 500);
  }
}
