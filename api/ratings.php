<?php
require_once '../config/database.php';

// Handle rating requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetRatings();
} elseif ($method === 'POST') {
  handleCreateRating($input);
} elseif ($method === 'PUT') {
  handleUpdateRating($input);
} elseif ($method === 'DELETE') {
  handleDeleteRating();
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetRatings()
{
  $db = getDB();

  // Get query parameters
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $rated_id = $_GET['rated_id'] ?? '';
  $rater_id = $_GET['rater_id'] ?? '';

  // Build query
  $query = "
        SELECT r.*, 
               rater.name as rater_name,
               rated.name as rated_name,
               p.name as product_name
        FROM ratings r
        LEFT JOIN users rater ON r.rater_id = rater.id
        LEFT JOIN users rated ON r.rated_id = rated.id
        LEFT JOIN products p ON r.product_id = p.id
        WHERE 1=1
    ";

  $params = [];

  if (!empty($rated_id)) {
    $query .= " AND r.rated_id = ?";
    $params[] = $rated_id;
  }

  if (!empty($rater_id)) {
    $query .= " AND r.rater_id = ?";
    $params[] = $rater_id;
  }

  $query .= " ORDER BY r.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $ratings = $stmt->fetchAll();

  // Get total count for pagination
  $count_query = "SELECT COUNT(*) as total FROM ratings r WHERE 1=1";
  $count_params = [];

  if (!empty($rated_id)) {
    $count_query .= " AND r.rated_id = ?";
    $count_params[] = $rated_id;
  }

  if (!empty($rater_id)) {
    $count_query .= " AND r.rater_id = ?";
    $count_params[] = $rater_id;
  }

  $stmt = $db->prepare($count_query);
  $stmt->execute($count_params);
  $total = $stmt->fetch()['total'];

  sendResponse(true, 'Ratings retrieved successfully', [
    'ratings' => $ratings,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleCreateRating($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $required_fields = ['rated_id', 'rating'];
  $missing_fields = validateRequiredFields($data, $required_fields);

  if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
  }

  // Validate rating value
  if (!in_array($data['rating'], ['satisfied', 'not_satisfied'])) {
    sendResponse(false, 'Invalid rating value', null, 400);
  }

  // Check if rated user exists
  $db = getDB();
  $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
  $stmt->execute([$data['rated_id']]);
  $rated_user = $stmt->fetch();

  if (!$rated_user) {
    sendResponse(false, 'Rated user not found', null, 404);
  }

  // Don't allow rating yourself
  if ($user['id'] == $data['rated_id']) {
    sendResponse(false, 'Cannot rate yourself', null, 400);
  }

  // Check if rating already exists for this combination
  $stmt = $db->prepare("
        SELECT id FROM ratings 
        WHERE rater_id = ? AND rated_id = ? AND product_id = ?
    ");
  $stmt->execute([
    $user['id'],
    $data['rated_id'],
    $data['product_id'] ?? null
  ]);

  if ($stmt->fetch()) {
    sendResponse(false, 'Rating already exists for this user and product', null, 400);
  }

  try {
    $stmt = $db->prepare("
            INSERT INTO ratings (rater_id, rated_id, product_id, rating, comment) 
            VALUES (?, ?, ?, ?, ?)
        ");

    $stmt->execute([
      $user['id'],
      $data['rated_id'],
      $data['product_id'] ?? null,
      $data['rating'],
      sanitizeInput($data['comment'] ?? '')
    ]);

    $rating_id = $db->lastInsertId();

    // Get the created rating with user info
    $stmt = $db->prepare("
            SELECT r.*, 
                   rater.name as rater_name,
                   rated.name as rated_name,
                   p.name as product_name
            FROM ratings r
            LEFT JOIN users rater ON r.rater_id = rater.id
            LEFT JOIN users rated ON r.rated_id = rated.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.id = ?
        ");
    $stmt->execute([$rating_id]);
    $rating = $stmt->fetch();

    sendResponse(true, 'Rating created successfully', ['rating' => $rating]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to create rating: ' . $e->getMessage(), null, 500);
  }
}

function handleUpdateRating($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $rating_id = $data['id'] ?? '';
  if (empty($rating_id)) {
    sendResponse(false, 'Rating ID required', null, 400);
  }

  $db = getDB();

  // Check if rating exists and user is the rater
  $stmt = $db->prepare("SELECT * FROM ratings WHERE id = ? AND rater_id = ?");
  $stmt->execute([$rating_id, $user['id']]);
  $rating = $stmt->fetch();

  if (!$rating) {
    sendResponse(false, 'Rating not found or access denied', null, 404);
  }

  // Build update query dynamically
  $update_fields = [];
  $params = [];

  $allowed_fields = ['rating', 'comment'];

  foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
      $update_fields[] = "$field = ?";
      $params[] = $field === 'rating' ? $data[$field] : sanitizeInput($data[$field]);
    }
  }

  if (empty($update_fields)) {
    sendResponse(false, 'No fields to update', null, 400);
  }

  $params[] = $rating_id;

  try {
    $query = "UPDATE ratings SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // Get updated rating
    $stmt = $db->prepare("
            SELECT r.*, 
                   rater.name as rater_name,
                   rated.name as rated_name,
                   p.name as product_name
            FROM ratings r
            LEFT JOIN users rater ON r.rater_id = rater.id
            LEFT JOIN users rated ON r.rated_id = rated.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.id = ?
        ");
    $stmt->execute([$rating_id]);
    $updated_rating = $stmt->fetch();

    sendResponse(true, 'Rating updated successfully', ['rating' => $updated_rating]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to update rating: ' . $e->getMessage(), null, 500);
  }
}

function handleDeleteRating()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $rating_id = $_GET['id'] ?? '';
  if (empty($rating_id)) {
    sendResponse(false, 'Rating ID required', null, 400);
  }

  $db = getDB();

  // Check if rating exists and user is the rater
  $stmt = $db->prepare("SELECT * FROM ratings WHERE id = ? AND rater_id = ?");
  $stmt->execute([$rating_id, $user['id']]);
  $rating = $stmt->fetch();

  if (!$rating) {
    sendResponse(false, 'Rating not found or access denied', null, 404);
  }

  try {
    $stmt = $db->prepare("DELETE FROM ratings WHERE id = ?");
    $stmt->execute([$rating_id]);

    sendResponse(true, 'Rating deleted successfully');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to delete rating: ' . $e->getMessage(), null, 500);
  }
}
