<?php
require_once '../config/database.php';

// Handle message requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
  handleGetMessages();
} elseif ($method === 'POST') {
  handleSendMessage($input);
} elseif ($method === 'PUT') {
  handleMarkAsRead($input);
} else {
  sendResponse(false, 'Method not allowed', null, 405);
}

function handleGetMessages()
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $db = getDB();

  // Get query parameters
  $limit = $_GET['limit'] ?? 20;
  $offset = $_GET['offset'] ?? 0;
  $conversation_with = $_GET['conversation_with'] ?? '';

  // Build query to get messages for the current user
  $query = "
        SELECT m.*, 
               sender.name as sender_name, 
               receiver.name as receiver_name,
               p.name as product_name,
               r.title as request_title
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN users receiver ON m.receiver_id = receiver.id
        LEFT JOIN products p ON m.product_id = p.id
        LEFT JOIN buyer_requests r ON m.request_id = r.id
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
    ";

  $params = [$user['id'], $user['id']];

  if (!empty($conversation_with)) {
    $query .= " AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))";
    $params[] = $user['id'];
    $params[] = $conversation_with;
    $params[] = $conversation_with;
    $params[] = $user['id'];
  }

  $query .= " ORDER BY m.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

  $stmt = $db->prepare($query);
  $stmt->execute($params);
  $messages = $stmt->fetchAll();

  sendResponse(true, 'Messages retrieved successfully', [
    'messages' => $messages,
    'limit' => $limit,
    'offset' => $offset
  ]);
}

function handleSendMessage($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $required_fields = ['receiver_id', 'message'];
  $missing_fields = validateRequiredFields($data, $required_fields);

  if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
  }

  // Check if receiver exists
  $db = getDB();
  $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
  $stmt->execute([$data['receiver_id']]);
  $receiver = $stmt->fetch();

  if (!$receiver) {
    sendResponse(false, 'Receiver not found', null, 404);
  }

  // Don't allow sending message to self
  if ($user['id'] == $data['receiver_id']) {
    sendResponse(false, 'Cannot send message to yourself', null, 400);
  }

  try {
    $stmt = $db->prepare("
            INSERT INTO messages (sender_id, receiver_id, product_id, request_id, message) 
            VALUES (?, ?, ?, ?, ?)
        ");

    $stmt->execute([
      $user['id'],
      $data['receiver_id'],
      $data['product_id'] ?? null,
      $data['request_id'] ?? null,
      sanitizeInput($data['message'])
    ]);

    $message_id = $db->lastInsertId();

    // Get the created message with sender/receiver info
    $stmt = $db->prepare("
            SELECT m.*, 
                   sender.name as sender_name, 
                   receiver.name as receiver_name,
                   p.name as product_name,
                   r.title as request_title
            FROM messages m
            LEFT JOIN users sender ON m.sender_id = sender.id
            LEFT JOIN users receiver ON m.receiver_id = receiver.id
            LEFT JOIN products p ON m.product_id = p.id
            LEFT JOIN buyer_requests r ON m.request_id = r.id
            WHERE m.id = ?
        ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();

    sendResponse(true, 'Message sent successfully', ['message' => $message]);
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to send message: ' . $e->getMessage(), null, 500);
  }
}

function handleMarkAsRead($data)
{
  // Check authentication
  $user = getCurrentUser();
  if (!$user) {
    sendResponse(false, 'Authentication required', null, 401);
  }

  $message_id = $data['message_id'] ?? '';
  if (empty($message_id)) {
    sendResponse(false, 'Message ID required', null, 400);
  }

  $db = getDB();

  // Check if message exists and user is the receiver
  $stmt = $db->prepare("SELECT * FROM messages WHERE id = ? AND receiver_id = ?");
  $stmt->execute([$message_id, $user['id']]);
  $message = $stmt->fetch();

  if (!$message) {
    sendResponse(false, 'Message not found or access denied', null, 404);
  }

  try {
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([$message_id]);

    sendResponse(true, 'Message marked as read');
  } catch (PDOException $e) {
    sendResponse(false, 'Failed to mark message as read: ' . $e->getMessage(), null, 500);
  }
}
