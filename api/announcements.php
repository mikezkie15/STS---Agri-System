<?php
require_once '../config/database.php';

// Handle announcement requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetAnnouncements();
} elseif ($method === 'POST') {
  handleCreateAnnouncement($input);
} elseif ($method === 'PUT') {
  handleUpdateAnnouncement($input);
} elseif ($method === 'DELETE') {
  handleDeleteAnnouncement();
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetAnnouncements()
{
  $db = getDB();

  // Get query parameters
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $important_only = $_GET['important'] ?? false;

  // Build query
  $query = "
        SELECT a.*, u.name as admin_name
        FROM announcements a
        LEFT JOIN users u ON a.admin_id = u.id
        WHERE 1=1
    ";

  $params = [];

  if ($important_only) {
    $query .= " AND a.is_important = 1";
  }

  $query .= " ORDER BY a.is_important DESC, a.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $announcements = $stmt->fetchAll();

  // Get total count for pagination
  $count_query = "SELECT COUNT(*) as total FROM announcements a WHERE 1=1";
  $count_params = [];

  if ($important_only) {
    $count_query .= " AND a.is_important = 1";
  }

  $stmt = $db->prepare($count_query);
  $stmt->execute($count_params);
  $total = $stmt->fetch()['total'];

  sendResponse(true, 'Announcements retrieved successfully', [
    'announcements' => $announcements,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleCreateAnnouncement($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can create announcements
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Only admins can create announcements', null, 403);
  }

  $required_fields = ['title', 'content'];
  $missing_fields = validateRequiredFields($data, $required_fields);

  if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
  }

  $db = getDB();

  try {
    $stmt = $db->prepare("
            INSERT INTO announcements (admin_id, title, content, is_important) 
            VALUES (?, ?, ?, ?)
        ");

    $stmt->execute([
      $user['id'],
      sanitizeInput($data['title']),
      sanitizeInput($data['content']),
      $data['is_important'] ? 1 : 0
    ]);

    $announcement_id = $db->lastInsertId();

    // Get the created announcement with admin info
    $stmt = $db->prepare("
            SELECT a.*, u.name as admin_name
            FROM announcements a
            LEFT JOIN users u ON a.admin_id = u.id
            WHERE a.id = ?
        ");
    $stmt->execute([$announcement_id]);
    $announcement = $stmt->fetch();

    sendResponse(true, 'Announcement created successfully', ['announcement' => $announcement]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to create announcement: ' . $e->getMessage(), null, 500);
  }
}

function handleUpdateAnnouncement($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can update announcements
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Only admins can update announcements', null, 403);
  }

  $announcement_id = $data['id'] ?? '';
  if (empty($announcement_id)) {
    sendResponse(false, 'Announcement ID required', null, 400);
  }

  $db = getDB();

  // Check if announcement exists and user owns it
  $stmt = $db->prepare("SELECT * FROM announcements WHERE id = ? AND admin_id = ?");
  $stmt->execute([$announcement_id, $user['id']]);
  $announcement = $stmt->fetch();

  if (!$announcement) {
    sendResponse(false, 'Announcement not found or access denied', null, 404);
  }

  // Build update query dynamically
  $update_fields = [];
  $params = [];

  $allowed_fields = ['title', 'content', 'is_important'];

  foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
      $update_fields[] = "$field = ?";
      $params[] = $field === 'is_important' ? (int)$data[$field] : $data[$field];
    }
  }

  if (empty($update_fields)) {
    sendResponse(false, 'No fields to update', null, 400);
  }

  $params[] = $announcement_id;

  try {
    $query = "UPDATE announcements SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // Get updated announcement
    $stmt = $db->prepare("
            SELECT a.*, u.name as admin_name
            FROM announcements a
            LEFT JOIN users u ON a.admin_id = u.id
            WHERE a.id = ?
        ");
    $stmt->execute([$announcement_id]);
    $updated_announcement = $stmt->fetch();

    sendResponse(true, 'Announcement updated successfully', ['announcement' => $updated_announcement]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to update announcement: ' . $e->getMessage(), null, 500);
  }
}

function handleDeleteAnnouncement()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  // Only admins can delete announcements
  if ($user['user_type'] !== 'admin') {
    sendResponse(false, 'Only admins can delete announcements', null, 403);
  }

  $announcement_id = $_GET['id'] ?? '';
  if (empty($announcement_id)) {
    sendResponse(false, 'Announcement ID required', null, 400);
  }

  $db = getDB();

  // Check if announcement exists and user owns it
  $stmt = $db->prepare("SELECT * FROM announcements WHERE id = ? AND admin_id = ?");
  $stmt->execute([$announcement_id, $user['id']]);
  $announcement = $stmt->fetch();

  if (!$announcement) {
    sendResponse(false, 'Announcement not found or access denied', null, 404);
  }

  try {
    $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$announcement_id]);

    sendResponse(true, 'Announcement deleted successfully');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to delete announcement: ' . $e->getMessage(), null, 500);
  }
}
