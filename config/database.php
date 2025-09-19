<?php
// Database configuration for Barangay Agri-Market Platform

class Database
{
    private $host = 'localhost';
    private $db_name = 'barangay_agri_market';
    private $username = 'root';
    private $password = 'root';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

// Helper function to get database connection
function getDB()
{
    $database = new Database();
    return $database->getConnection();
}

// Helper function to generate API response
function sendResponse($success, $message = '', $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json');

    $response = array(
        'success' => $success,
        'message' => $message
    );

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

// Helper function to validate required fields
function validateRequiredFields($data, $required_fields)
{
    $missing_fields = array();

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing_fields[] = $field;
        }
    }

    return $missing_fields;
}

// Helper function to sanitize input
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

// Helper function to generate random token
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

// Helper function to hash password
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Helper function to verify password
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// Helper function to check if user is authenticated
function checkAuth()
{
    file_put_contents('debug.log', print_r($_SERVER, true));
    $token = '';

    // Check HTTP_AUTHORIZATION first
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        $token = str_replace('Bearer ', '', $auth_header);
    }
    // Check getallheaders() as fallback (Apache sometimes doesn't set HTTP_AUTHORIZATION)
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    // Check JSON input as last resort
    else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['token'])) {
            $token = $input['token'];
        }
    }

    if (empty($token)) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*
        FROM users u
        INNER JOIN user_tokens ut ON u.id = ut.user_id
        WHERE ut.token = ? AND ut.expires_at > NOW() AND u.is_active = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    return $user ? $user : false;
}

// Helper function to get current user
function getCurrentUser()
{
    return checkAuth();
}

// CORS headers for API
function setCORSHeaders()
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Set CORS headers only when running through web server
if (php_sapi_name() !== 'cli') {
    setCORSHeaders();
}
