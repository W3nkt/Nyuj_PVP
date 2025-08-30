<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    echo "<h2>Adding competitor image columns to events table...</h2>";
    
    // Check if columns already exist
    $stmt = $conn->query("DESCRIBE events");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $needsCompetitorAImage = !in_array('competitor_a_image', $columns);
    $needsCompetitorBImage = !in_array('competitor_b_image', $columns);
    
    if ($needsCompetitorAImage) {
        $sql = "ALTER TABLE events ADD COLUMN competitor_a_image VARCHAR(255) NULL AFTER competitor_a";
        if ($conn->exec($sql) !== false) {
            echo "✅ Added competitor_a_image column<br>";
        } else {
            echo "❌ Failed to add competitor_a_image column<br>";
        }
    } else {
        echo "✅ competitor_a_image column already exists<br>";
    }
    
    if ($needsCompetitorBImage) {
        $sql = "ALTER TABLE events ADD COLUMN competitor_b_image VARCHAR(255) NULL AFTER competitor_b";
        if ($conn->exec($sql) !== false) {
            echo "✅ Added competitor_b_image column<br>";
        } else {
            echo "❌ Failed to add competitor_b_image column<br>";
        }
    } else {
        echo "✅ competitor_b_image column already exists<br>";
    }
    
    echo "<br><h3>Database migration complete!</h3>";
    echo "<p><a href='admin/create_event.php'>Create Event</a> | <a href='admin/index.php'>Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>