<?php
// Creates triggers to prevent inserting/updating a second Active medicine
include __DIR__ . '/../config/db.php';

function run_sql($conn, $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "OK: SQL executed.\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
}

echo "Dropping existing triggers if present...\n";
run_sql($conn, "DROP TRIGGER IF EXISTS prevent_duplicate_active_medicine_insert");
run_sql($conn, "DROP TRIGGER IF EXISTS prevent_duplicate_active_medicine_update");

echo "Creating BEFORE INSERT trigger...\n";
$trigger_insert = "CREATE TRIGGER prevent_duplicate_active_medicine_insert
BEFORE INSERT ON medicines
FOR EACH ROW
BEGIN
    IF NEW.status = 'Active' AND NEW.medicine_master_id IS NOT NULL AND NEW.medicine_master_id <> 0 AND NEW.patient_id IS NOT NULL THEN
        IF (SELECT COUNT(*) FROM medicines m WHERE m.patient_id = NEW.patient_id AND m.medicine_master_id = NEW.medicine_master_id AND m.status = 'Active') > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active prescription for this patient and medicine already exists';
        END IF;
    END IF;
END";
run_sql($conn, $trigger_insert);

echo "Creating BEFORE UPDATE trigger...\n";
$trigger_update = "CREATE TRIGGER prevent_duplicate_active_medicine_update
BEFORE UPDATE ON medicines
FOR EACH ROW
BEGIN
    -- If status changes to Active or medicine_master_id or patient_id changes, enforce uniqueness
    IF (NEW.status = 'Active') THEN
        IF NEW.medicine_master_id IS NOT NULL AND NEW.medicine_master_id <> 0 AND NEW.patient_id IS NOT NULL THEN
            IF (SELECT COUNT(*) FROM medicines m WHERE m.patient_id = NEW.patient_id AND m.medicine_master_id = NEW.medicine_master_id AND m.status = 'Active' AND m.medicine_id <> OLD.medicine_id) > 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active prescription for this patient and medicine already exists';
            END IF;
        END IF;
    END IF;
END";
run_sql($conn, $trigger_update);

echo "Trigger creation complete.\n";
?>
