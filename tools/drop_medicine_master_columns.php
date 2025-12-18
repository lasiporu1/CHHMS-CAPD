<?php
// Safe migration: drop unused medicine_master columns if they exist
include __DIR__ . '/../config/db.php';

echo "Starting migration: drop obsolete medicine_master columns...\n";

$cols = ['generic_name','strength','default_dosage','default_frequency'];
$dropped = 0;
foreach ($cols as $c) {
    $res = $conn->query("SHOW COLUMNS FROM medicines LIKE '" . $conn->real_escape_string($c) . "'");
    if ($res && $res->num_rows > 0) {
        echo "Dropping column: $c\n";
        $alter = "ALTER TABLE medicines DROP COLUMN `" . $conn->real_escape_string($c) . "`";
        if ($conn->query($alter) === TRUE) {
            echo " - Dropped $c\n";
            $dropped++;
        } else {
            echo " - Failed to drop $c: " . $conn->error . "\n";
        }
    } else {
        echo "Column not present: $c\n";
    }
}

echo "Migration complete. Columns dropped: $dropped\n";

?>
