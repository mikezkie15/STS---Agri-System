<?php
require_once '../config/database.php';

// Handle authentication requests
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'register':
            handleRegister($input);
            break;
        case 'login':
            handleLogin($input);
            break;
        case 'validate':
            handleValidate($input);
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} else {
    sendResponse(false, 'Method not allowed', null, 405);
}

function handleRegister($data) {
    $required_fields = ['name', 'email', 'password', 'user_type', 'phone'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    
    if (!empty($missing_fields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
    }
    
    // Validate user type
    if (!in_array($data['user_type'], ['farmer', 'buyer', 'admin'])) {
        sendResponse(false, 'Invalid user type', null, 400);
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        sendResponse(false, 'Password must be at least 6 characters long', null, 400);
    }
    
    $db = getDB();
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        sendResponse(false, 'Email already registered', null, 400);
    }
    
    // Hash password
    $hashed_password = hashPassword($data['password']);
    
    // Insert new user
    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, phone, address, user_type, is_verified, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $is_verified = $data['user_type'] === 'admin' ? 1 : 0;
    $is_active = 1;
    
    try {
        $stmt->execute([
            sanitizeInput($data['name']),
            sanitizeInput($data['email']),
            $hashed_password,
            sanitizeInput($data['phone']),
            sanitizeInput($data['address'] ?? ''),
            $data['user_type'],
            $is_verified,
            $is_active
        ]);
        
        $user_id = $db->lastInsertId();
        
        // Get the created user
        $stmt = $db->prepare("SELECT id, name, email, phone, address, user_type, is_verified FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Generate token (in a real app, use JWT or similar)
        $token = generateToken();
        
        sendResponse(true, 'Registration successful', [
            'user' => $user,
            'token' => $token
        ]);
        
    } catch (PDOException $e) {
        sendResponse(false, 'Registration failed: ' . $e->getMessage(), null, 500);
    }
}

function handleLogin($data) {
    $required_fields = ['email', 'password'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    
    if (!empty($missing_fields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
    }
    
    $db = getDB();
    
    // Get user by email
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    // Verify password
    if (!verifyPassword($data['password'], $user['password'])) {
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    // Generate token and store it in database
    $token = generateToken();
    
    // Store token in database with user ID
    $stmt = $db->prepare("INSERT INTO user_tokens (user_id, token, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    $stmt->execute([$user['id'], $token]);
    
    // Remove password from response
    unset($user['password']);
    
    sendResponse(true, 'Login successful', [
        'user' => $user,
        'token' => $token
    ]);
}

function handleValidate($data) {
    $token = $data['token'] ?? '';
    
    if (empty($token)) {
        sendResponse(false, 'Token required', null, 400);
    }
    
    $db = getDB();
    
    // Validate token by checking user_tokens table
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.address, u.user_type, u.is_verified 
        FROM users u 
        INNER JOIN user_tokens ut ON u.id = ut.user_id 
        WHERE ut.token = ? AND ut.expires_at > NOW() AND u.is_active = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'Invalid or expired token', null, 401);
    }
    
    sendResponse(true, 'Token valid', ['user' => $user]);
}

function handleLogout() {
    // In a real application, you would invalidate the token
    sendResponse(true, 'Logout successful');
}
?>
