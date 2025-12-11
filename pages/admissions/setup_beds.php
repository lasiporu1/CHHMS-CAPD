<?php
include '../../config/db.php';

// Create beds table if it doesn't exist
$create_beds_table = "CREATE TABLE IF NOT EXISTS ward_beds (
    bed_id INT AUTO_INCREMENT PRIMARY KEY,
    ward_name VARCHAR(100) NOT NULL,
    bed_number VARCHAR(50) NOT NULL,
    bed_type ENUM('General', 'ICU', 'CCU', 'Private', 'Semi-Private', 'Emergency') DEFAULT 'General',
    bed_status ENUM('Available', 'Occupied', 'Maintenance', 'Reserved') DEFAULT 'Available',
    room_number VARCHAR(50),
    floor_number VARCHAR(10),
    equipment TEXT,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ward_bed (ward_name, bed_number)
)";
$conn->query($create_beds_table);

// Check if beds already exist
$count_query = "SELECT COUNT(*) as bed_count FROM ward_beds";
$count_result = $conn->query($count_query);
$count = $count_result->fetch_assoc()['bed_count'];

if ($count == 0) {
    // Insert sample beds
    $sample_beds = [
        // General Ward
        ['General Ward', 'A-01', 'General', 'Available', '101', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'A-02', 'General', 'Available', '101', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'A-03', 'General', 'Available', '102', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'A-04', 'General', 'Available', '102', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'B-01', 'General', 'Available', '103', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'B-02', 'General', 'Available', '103', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'B-03', 'General', 'Available', '104', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        ['General Ward', 'B-04', 'General', 'Available', '104', 'Ground', 'Basic monitoring, Oxygen outlet', 'Standard bed'],
        
        // ICU
        ['ICU', 'ICU-01', 'ICU', 'Available', '201', '1st', 'Ventilator, Advanced monitoring, Infusion pumps', 'Intensive care bed'],
        ['ICU', 'ICU-02', 'ICU', 'Available', '201', '1st', 'Ventilator, Advanced monitoring, Infusion pumps', 'Intensive care bed'],
        ['ICU', 'ICU-03', 'ICU', 'Available', '202', '1st', 'Ventilator, Advanced monitoring, Infusion pumps', 'Intensive care bed'],
        ['ICU', 'ICU-04', 'ICU', 'Available', '202', '1st', 'Ventilator, Advanced monitoring, Infusion pumps', 'Intensive care bed'],
        
        // CCU
        ['CCU', 'CCU-01', 'CCU', 'Available', '203', '1st', 'Cardiac monitors, Defibrillator, Oxygen', 'Cardiac care bed'],
        ['CCU', 'CCU-02', 'CCU', 'Available', '203', '1st', 'Cardiac monitors, Defibrillator, Oxygen', 'Cardiac care bed'],
        ['CCU', 'CCU-03', 'CCU', 'Available', '204', '1st', 'Cardiac monitors, Defibrillator, Oxygen', 'Cardiac care bed'],
        
        // Emergency Ward
        ['Emergency', 'ER-01', 'Emergency', 'Available', '001', 'Ground', 'Crash cart, Monitors, Suction', 'Emergency bed'],
        ['Emergency', 'ER-02', 'Emergency', 'Available', '001', 'Ground', 'Crash cart, Monitors, Suction', 'Emergency bed'],
        ['Emergency', 'ER-03', 'Emergency', 'Available', '002', 'Ground', 'Crash cart, Monitors, Suction', 'Emergency bed'],
        
        // Private Rooms
        ['Private Ward', 'P-01', 'Private', 'Available', '301', '2nd', 'TV, Refrigerator, Oxygen, Private bathroom', 'Private room'],
        ['Private Ward', 'P-02', 'Private', 'Available', '302', '2nd', 'TV, Refrigerator, Oxygen, Private bathroom', 'Private room'],
        ['Private Ward', 'P-03', 'Private', 'Available', '303', '2nd', 'TV, Refrigerator, Oxygen, Private bathroom', 'Private room'],
        
        // Maternity Ward
        ['Maternity', 'M-01', 'Semi-Private', 'Available', '401', '3rd', 'Baby warmer, Oxygen, Monitors', 'Maternity bed'],
        ['Maternity', 'M-02', 'Semi-Private', 'Available', '401', '3rd', 'Baby warmer, Oxygen, Monitors', 'Maternity bed'],
        ['Maternity', 'M-03', 'Semi-Private', 'Available', '402', '3rd', 'Baby warmer, Oxygen, Monitors', 'Maternity bed'],
        ['Maternity', 'M-04', 'Semi-Private', 'Available', '402', '3rd', 'Baby warmer, Oxygen, Monitors', 'Maternity bed'],
    ];

    foreach ($sample_beds as $bed) {
        $stmt = $conn->prepare("INSERT INTO ward_beds (ward_name, bed_number, bed_type, bed_status, room_number, floor_number, equipment, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $bed[0], $bed[1], $bed[2], $bed[3], $bed[4], $bed[5], $bed[6], $bed[7]);
        $stmt->execute();
    }

    echo "<h2>✅ Sample beds created successfully!</h2>";
    echo "<p>Created " . count($sample_beds) . " sample beds across different wards:</p>";
    echo "<ul>";
    echo "<li>General Ward: 8 beds</li>";
    echo "<li>ICU: 4 beds</li>";
    echo "<li>CCU: 3 beds</li>";
    echo "<li>Emergency: 3 beds</li>";
    echo "<li>Private Ward: 3 beds</li>";
    echo "<li>Maternity: 4 beds</li>";
    echo "</ul>";
    echo "<p><a href='bed_management.php' style='background: #4a90e2; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; display: inline-block; margin-top: 1rem;'>🛏️ Go to Bed Management</a></p>";
} else {
    echo "<h2>ℹ️ Beds already exist</h2>";
    echo "<p>Found $count beds in the system.</p>";
    echo "<p><a href='bed_management.php' style='background: #4a90e2; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; display: inline-block; margin-top: 1rem;'>🛏️ Go to Bed Management</a></p>";
}

$conn->close();
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    padding: 2rem; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    min-height: 100vh;
}
h2 { color: white; }
p { font-size: 1.1rem; }
ul { margin-left: 2rem; }
li { margin: 0.5rem 0; }
</style>