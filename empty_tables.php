<?php
include 'config/db.php';

// Tables to preserve (DO NOT DELETE DATA)
$preserve_tables = array(
    'users',
    'doctors', 
    'nursing_officers',
    'patients'
);

echo "<h2>CHHMS Database Data Cleanup</h2>\n";
echo "<p><strong>Preserving data in:</strong> " . implode(', ', $preserve_tables) . "</p>\n";

// Get confirmation
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>⚠️ WARNING: Database Cleanup Operation</h3>";
    echo "<p>This operation will <strong>permanently delete all data</strong> from the following tables:</p>";
    
    // Get list of all tables
    $result = $conn->query("SHOW TABLES");
    $tables_to_clear = array();
    
    if ($result) {
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            $table_name = $row[0];
            if (!in_array($table_name, $preserve_tables)) {
                $tables_to_clear[] = $table_name;
                
                // Get row count
                $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table_name`");
                $count = 0;
                if ($count_result) {
                    $count_row = $count_result->fetch_assoc();
                    $count = $count_row['count'];
                }
                
                echo "<li><strong>$table_name</strong> ($count records)</li>";
            }
        }
        echo "</ul>";
        
        echo "<p><strong>Data will be PRESERVED in:</strong></p>";
        echo "<ul>";
        foreach ($preserve_tables as $table) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = 0;
            if ($count_result) {
                $count_row = $count_result->fetch_assoc();
                $count = $count_row['count'];
            }
            echo "<li><strong>$table</strong> ($count records) - ✅ WILL BE PRESERVED</li>";
        }
        echo "</ul>";
    }
    
    echo "<p style='color: red;'><strong>This action cannot be undone!</strong></p>";
    echo "<a href='empty_tables.php?confirm=yes' style='background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🗑️ CONFIRM: Empty Tables</a>";
    echo "<a href='index.php' style='background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>❌ Cancel</a>";
    echo "</div>";
    
} else {
    // Perform the cleanup
    echo "<h3>🔄 Processing Database Cleanup...</h3>\n";
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Get list of all tables
    $result = $conn->query("SHOW TABLES");
    $cleared_count = 0;
    $total_records_deleted = 0;
    
    if ($result) {
        echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        
        while ($row = $result->fetch_array()) {
            $table_name = $row[0];
            
            if (!in_array($table_name, $preserve_tables)) {
                // Get count before deletion
                $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table_name`");
                $count_before = 0;
                if ($count_result) {
                    $count_row = $count_result->fetch_assoc();
                    $count_before = $count_row['count'];
                }
                
                // Clear the table
                if ($count_before > 0) {
                    $delete_result = $conn->query("DELETE FROM `$table_name`");
                    if ($delete_result) {
                        echo "✅ Cleared <strong>$table_name</strong> - Deleted $count_before records<br>\n";
                        $cleared_count++;
                        $total_records_deleted += $count_before;
                    } else {
                        echo "❌ Error clearing <strong>$table_name</strong>: " . $conn->error . "<br>\n";
                    }
                } else {
                    echo "ℹ️ Table <strong>$table_name</strong> was already empty<br>\n";
                    $cleared_count++;
                }
            }
        }
        
        echo "</div>";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>✅ Database Cleanup Completed Successfully!</h3>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Tables processed: $cleared_count</li>";
    echo "<li>Total records deleted: $total_records_deleted</li>";
    echo "<li>Preserved tables: " . count($preserve_tables) . "</li>";
    echo "</ul>";
    
    echo "<h4>📊 Final Status:</h4>";
    foreach ($preserve_tables as $table) {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count = 0;
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $count = $count_row['count'];
        }
        echo "✅ <strong>$table:</strong> $count records preserved<br>\n";
    }
    echo "</div>";
    
    echo "<p><a href='index.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Return to Dashboard</a></p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Cleanup - CHHMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Content above is echoed by PHP -->
    </div>
</body>
</html>