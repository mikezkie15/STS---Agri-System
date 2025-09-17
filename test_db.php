<?php
// Simple database test
$host = 'localhost';
$dbname = 'barangay_agri_market';
$username = 'root';
$password = 'root';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  echo "Database connection successful!\n";

  // Test announcements query
  $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
  $stmt->execute();
  $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($announcements) . " announcements:\n";
  foreach ($announcements as $announcement) {
    echo "- " . $announcement['title'] . " (ID: " . $announcement['id'] . ")\n";
  }
} catch (PDOException $e) {
  echo "Database connection failed: " . $e->getMessage() . "\n";
}
