<?php

/**
 * Barangay Agri-Market Platform Setup Script
 * Run this script to initialize the database and create default data
 */

// Database configuration
$host = 'localhost';
$db_name = 'barangay_agri_market';
$username = 'root';
$password = 'root'; // Common XAMPP MySQL password

// Create database connection
try {
  $pdo = new PDO("mysql:host=$host", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Create database if it doesn't exist
  $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
  $pdo->exec("USE $db_name");

  echo "✅ Database connection successful\n";
  echo "✅ Database '$db_name' created/verified\n";
} catch (PDOException $e) {
  die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Read and execute schema file
$schema_file = 'database/schema.sql';
if (file_exists($schema_file)) {
  $schema = file_get_contents($schema_file);

  // Split by semicolon and execute each statement
  $statements = explode(';', $schema);

  foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
      try {
        $pdo->exec($statement);
      } catch (PDOException $e) {
        // Ignore errors for existing tables
        if (strpos($e->getMessage(), 'already exists') === false) {
          echo "⚠️  Warning: " . $e->getMessage() . "\n";
        }
      }
    }
  }

  echo "✅ Database schema loaded successfully\n";
} else {
  echo "❌ Schema file not found: $schema_file\n";
}

// Verify tables were created
$tables = ['users', 'products', 'buyer_requests', 'announcements', 'messages', 'ratings', 'categories', 'meetup_points'];
$created_tables = [];

foreach ($tables as $table) {
  try {
    $result = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($result->rowCount() > 0) {
      $created_tables[] = $table;
    }
  } catch (PDOException $e) {
    // Table doesn't exist
  }
}

echo "✅ Created tables: " . implode(', ', $created_tables) . "\n";

// Check if admin user exists
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
  $stmt->execute();
  $admin_count = $stmt->fetchColumn();

  if ($admin_count > 0) {
    echo "✅ Admin user already exists\n";
  } else {
    echo "⚠️  No admin user found. Please create one manually.\n";
  }
} catch (PDOException $e) {
  echo "❌ Error checking admin user: " . $e->getMessage() . "\n";
}

// Check if categories exist
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories");
  $stmt->execute();
  $category_count = $stmt->fetchColumn();

  if ($category_count > 0) {
    echo "✅ Product categories loaded ($category_count categories)\n";
  } else {
    echo "⚠️  No product categories found\n";
  }
} catch (PDOException $e) {
  echo "❌ Error checking categories: " . $e->getMessage() . "\n";
}

// Check if meetup points exist
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM meetup_points");
  $stmt->execute();
  $meetup_count = $stmt->fetchColumn();

  if ($meetup_count > 0) {
    echo "✅ Meetup points loaded ($meetup_count locations)\n";
  } else {
    echo "⚠️  No meetup points found\n";
  }
} catch (PDOException $e) {
  echo "❌ Error checking meetup points: " . $e->getMessage() . "\n";
}

echo "\n🎉 Setup completed successfully!\n";
echo "\n📋 Next steps:\n";
echo "1. Update database credentials in config/database.php\n";
echo "2. Configure your web server to point to this directory\n";
echo "3. Access the application through your web browser\n";
echo "4. Login with admin credentials:\n";
echo "   - Email: admin@barangay.com\n";
echo "   - Password: admin123\n";
echo "\n📱 The application is mobile-friendly and ready for community use!\n";
