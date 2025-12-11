<?php
include 'config/db.php';

echo "<h2>Database Tables in intimate_hospital_management:</h2>\n";

// Get list of all tables
$result = $conn->query("SHOW TABLES");

if ($result) {
    echo "<ol>\n";
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        echo "<li><strong>$table_name</strong>";
        
        // Get row count for each table
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table_name");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            echo " - Records: " . $count_row['count'];
        }
        echo "</li>\n";
    }
    echo "</ol>\n";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>