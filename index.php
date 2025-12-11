<?php
include 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <h2>Dashboard</h2>
        <p>Welcome, <?php echo $_SESSION['username']; ?>! (<?php echo $_SESSION['user_role']; ?>)</p>
        
        <!-- CAPD Alert for overdue patients -->
        <?php if (isset($_SESSION['capd_alert']) && $_SESSION['capd_alert'] > 0): ?>
            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; color: #856404;">
                <div style="font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem;">⚠️ CAPD Status Alert</div>
                <p style="margin: 0; font-size: 1rem;">
                    <strong><?php echo $_SESSION['capd_alert']; ?> patient(s)</strong> with catheter insertion more than 14 days ago but <strong>CAPD has not yet been started</strong>. 
                    Please update their CAPD status.
                </p>
                <a href="pages/reports/capd_status_report.php" style="display: inline-block; margin-top: 1rem; padding: 0.6rem 1.2rem; background: #ff9800; color: white; border-radius: 4px; text-decoration: none; font-weight: 600;">
                    Go to CAPD Status →
                </a>
            </div>
            <?php unset($_SESSION['capd_alert']); // Clear alert after displaying ?>
        <?php endif; ?>
        
        <hr>
        
        <!-- First Row: Patient Management, Ward Admissions, CAPD Clinic -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem;">
            
            <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>🤒 Patients</h3>
                <p style="margin-top: 1rem;">Manage patient records and information</p>
                <a href="pages/patients/patient_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">Manage Patients</a>
            </div>
            
            <div style="background: linear-gradient(135deg, #a8e6cf 0%, #88d8a3 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>🏨 Ward Admissions</h3>
                <p style="margin-top: 1rem;">Manage ward admissions with investigations & medicines</p>
                <a href="pages/admissions/admission_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">Manage Admissions</a>
            </div>
            
            <div style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>🩺 CAPD Clinic</h3>
                <p style="margin-top: 1rem;">Manage CAPD clinic and patient appointments</p>
                <a href="pages/clinics/clinic_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">CAPD Clinic</a>
            </div>
            
        </div>
        
        <!-- Second Row: Doctors, Nursing Officers, Users -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem;">
            
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>👨‍⚕️ Doctors</h3>
                <p style="margin-top: 1rem;">Manage doctor profiles and specializations</p>
                <a href="pages/doctors/doctor_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">Manage Doctors</a>
            </div>
            
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>🏥 Nursing Officers</h3>
                <p style="margin-top: 1rem;">Manage nursing staff details</p>
                <a href="pages/nursing/nursing_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">Manage Nursing Officers</a>
            </div>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>👥 Users</h3>
                <p style="margin-top: 1rem;">Manage system users and their roles</p>
                <a href="pages/users/user_list.php" style="color: white; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px;">Manage Users</a>
            </div>
            
        </div>
        
        <!-- Third Row: Reports Module -->
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-top: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
            
            <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #8b4513; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <h3>📊 Reports</h3>
                <p style="margin-top: 1rem;">Generate detailed reports with advanced filters</p>
                <a href="pages/reports/report_list.php" style="color: #8b4513; text-decoration: none; display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: rgba(139,69,19,0.2); border-radius: 4px;">View Reports</a>
            </div>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
