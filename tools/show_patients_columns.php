<?php
include '../config/db.php';
$result = $conn->query("SHOW COLUMNS FROM patients");
if ($result) {
    echo "<h2>patients table columns:</h2><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['Field']) . " (" . htmlspecialchars($row['Type']) . ")</li>";
    }
    echo "</ul>";
} else {
    echo "Error: " . $conn->error;
}
?>
