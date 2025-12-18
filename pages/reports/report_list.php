<?php
include '../../config/db.php';
include '../../includes/header.php';

// Get counts for dashboard
$patient_count = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$doctor_count = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$nursing_count = $conn->query("SELECT COUNT(*) as count FROM nursing_officers")->fetch_assoc()['count'];
// Clinic patients (distinct patients with clinic admissions)
$clinic_patient_count = $conn->query("SELECT COUNT(DISTINCT patient_id) as count FROM clinic_admissions")->fetch_assoc()['count'];
?>

<div class="container">
    <div class="header">
        <div>
            <h1>📊 Reports Module</h1>
            <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Generate detailed reports with advanced filtering options</p>
        </div>
        <div>
            <a href="../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
        </div>
    </div>

    <!-- Report Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem;">
        <!-- Counselling Report -->
        <div class="card" style="background: linear-gradient(135deg, #f3e5f5 0%, #ce93d8 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🗣️</span>
                </div>
                <h3 style="color: #8e24aa; margin-bottom: 1rem;">Counselling Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">View and filter all patient counselling sessions and assigned nursing officers.</p>
                <a href="counselling_report.php" class="btn btn-primary" style="width: 100%; background: #8e24aa; border-color: #8e24aa;">🗣️ View Counselling Report</a>
            </div>
        </div>

        <!-- Detail Counselling Report -->
        <div class="card" style="background: linear-gradient(135deg, #ede7f6 0%, #b39ddb 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">📋</span>
                </div>
                <h3 style="color: #5e35b1; margin-bottom: 1rem;">Detail Counselling Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Detailed counselling sessions with date range and Nursing Officer filters.</p>
                <a href="detail_counselling_report.php" class="btn btn-primary" style="width: 100%; background: #5e35b1; border-color: #5e35b1;">📋 View Detail Counselling Report</a>
            </div>
        </div>
        
        <!-- Patient Reports -->
        <div class="card" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🤒</span>
                </div>
                <h3 style="color: #1976d2; margin-bottom: 1rem;">Patient Details Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Comprehensive patient information with advanced filtering by age, gender, blood group, and more.</p>
                
                <div style="background: rgba(25,118,210,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #1976d2;"><?php echo $patient_count; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Total Patients</div>
                </div>
                
                <a href="patient_report.php" class="btn btn-primary" style="width: 100%;">📋 Generate Patient Report</a>
            </div>
        </div>

        <!-- Doctor Reports -->
        <div class="card" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">👨‍⚕️</span>
                </div>
                <h3 style="color: #c2185b; margin-bottom: 1rem;">Doctor Details Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Detailed doctor profiles with filtering by specialization, qualification, and experience.</p>
                
                <div style="background: rgba(194,24,91,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #c2185b;"><?php echo $doctor_count; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Total Doctors</div>
                </div>
                
                <a href="doctor_report.php" class="btn btn-primary" style="width: 100%; background: #c2185b; border-color: #c2185b;">👨‍⚕️ Generate Doctor Report</a>
            </div>
        </div>

        <!-- Nursing Officer Reports -->
        <div class="card" style="background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🏥</span>
                </div>
                <h3 style="color: #00796b; margin-bottom: 1rem;">Nursing Officer Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Complete nursing staff details with filtering by ward assignment and qualifications.</p>
                
                <div style="background: rgba(0,121,107,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #00796b;"><?php echo $nursing_count; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Total Nursing Officers</div>
                </div>
                
                <a href="nursing_report.php" class="btn btn-primary" style="width: 100%; background: #00796b; border-color: #00796b;">🏥 Generate Nursing Report</a>
            </div>
        </div>

        <!-- Ward Admissions Reports -->
        <div class="card" style="background: linear-gradient(135deg, #fff9c4 0%, #f4e04d 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🏨</span>
                </div>
                <h3 style="color: #827717; margin-bottom: 1rem;">Ward Admissions Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Comprehensive admission records with filtering by date, ward, doctor, and discharge status.</p>
                
                <div style="background: rgba(130,119,23,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #827717;"><?php echo $conn->query("SELECT COUNT(*) as count FROM ward_admissions")->fetch_assoc()['count']; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Total Admissions</div>
                </div>
                
                <a href="admission_report.php" class="btn btn-primary" style="width: 100%; background: #827717; border-color: #827717;">🏨 Generate Admission Report</a>
            </div>
        </div>

        <!-- CAPD Status Reports -->
        <div class="card" style="background: linear-gradient(135deg, #e1f5fe 0%, #81d4fa 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🩺</span>
                </div>
                <h3 style="color: #0277bd; margin-bottom: 1rem;">CAPD Status Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Patient CAPD treatment status including catheter insertion dates and process start dates.</p>
                
                <div style="background: rgba(2,119,189,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #0277bd;"><?php echo $conn->query("SELECT COUNT(DISTINCT patient_id) as count FROM ward_admissions")->fetch_assoc()['count']; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">CAPD Patients</div>
                </div>
                
                <a href="capd_status_report.php" class="btn btn-primary" style="width: 100%; background: #0277bd; border-color: #0277bd;">🩺 Generate CAPD Status Report</a>
            </div>
        </div>

        <!-- Patient History Detail Report -->
        <div class="card" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">📄</span>
                </div>
                <h3 style="color: #2e7d32; margin-bottom: 1rem;">Patient History Detail Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Comprehensive detailed patient information with investigations, medicines, and complete medical history.</p>
                
                <div style="background: rgba(46,125,50,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #2e7d32;"><?php echo $patient_count; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Detailed Records Available</div>
                </div>
                
                <a href="patient_detail_search.php" class="btn btn-primary" style="width: 100%; background: #2e7d32; border-color: #2e7d32;">📄 Search Detail History</a>
            </div>
        </div>

        <!-- Clinic Patient History Detail Report -->
        <div class="card" style="background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🏥</span>
                </div>
                <h3 style="color: #00796b; margin-bottom: 1rem;">Clinic Patient History Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Clinic-only patient history reports using clinic admissions and admission numbers.</p>
                <div style="background: rgba(0,121,107,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #00796b;"><?php echo $clinic_patient_count; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Patients with Clinic Admissions</div>
                </div>
                <a href="clinic_patient_detail_search.php" class="btn btn-primary" style="width: 100%; background: #00796b; border-color: #00796b;">🏥 Clinic History Search</a>
            </div>
        </div>

        <!-- PET Summary Report -->
        <div class="card" style="background: linear-gradient(135deg, #e8eaf6 0%, #c5cae9 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">🧪</span>
                </div>
                <h3 style="color: #3f51b5; margin-bottom: 1rem;">PET Summary Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">View latest Peritoneal Equilibration Test (PET) results for each patient.</p>
                
                <div style="background: rgba(63,81,181,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #3f51b5;"><?php echo $conn->query("SELECT COUNT(DISTINCT patient_id) as count FROM peritoneal_equilibration_test")->fetch_assoc()['count']; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Patients with PET Tests</div>
                </div>
                
                <a href="pet_summary_report.php" class="btn btn-primary" style="width: 100%; background: #3f51b5; border-color: #3f51b5;">🧪 View PET Summary</a>
            </div>
        </div>

        <!-- Death Report -->
        <div class="card" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border: none;">
            <div style="padding: 2rem; text-align: center;">
                <div style="background: white; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 2rem;">⚰️</span>
                </div>
                <h3 style="color: #c62828; margin-bottom: 1rem;">Death Report</h3>
                <p style="color: #424242; margin-bottom: 1.5rem;">Comprehensive deceased patient records with death dates, causes, and advanced filtering options.</p>
                
                <div style="background: rgba(198,40,40,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-size: 2rem; font-weight: bold; color: #c62828;"><?php echo $conn->query("SELECT COUNT(*) as count FROM patients WHERE patient_status = 'Deceased'")->fetch_assoc()['count']; ?></div>
                    <div style="color: #666; font-size: 0.9rem;">Deceased Patients</div>
                </div>
                
                <a href="death_report.php" class="btn btn-primary" style="width: 100%; background: #c62828; border-color: #c62828;">⚰️ View Death Report</a>
            </div>
        </div>
    </div>

    <!-- Report Features -->
    <div class="card" style="margin-top: 2rem;">
        <h3>📋 Report Features</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
            
            <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                <h4 style="color: #28a745; margin-bottom: 1rem;">🔍 Advanced Filtering</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Filter by multiple criteria simultaneously</li>
                    <li>Date range selections</li>
                    <li>Custom field combinations</li>
                    <li>Real-time filter results</li>
                </ul>
            </div>
            
            <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                <h4 style="color: #007bff; margin-bottom: 1rem;">📊 Export Options</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Export to Excel/CSV format</li>
                    <li>Print-friendly layouts</li>
                    <li>PDF generation</li>
                    <li>Email report delivery</li>
                </ul>
            </div>
            
            <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ffc107;">
                <h4 style="color: #e68900; margin-bottom: 1rem;">📈 Data Insights</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Statistical summaries</li>
                    <li>Trend analysis</li>
                    <li>Data visualization charts</li>
                    <li>Comparative reports</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="card" style="margin-top: 2rem;">
        <h3>📊 System Overview</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $patient_count + $doctor_count + $nursing_count; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Total Personnel</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏥</div>
                <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $doctor_count + $nursing_count; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Medical Staff</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                <div style="font-size: 1.5rem; font-weight: bold;">3</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Report Types</div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    display: inline-block;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,123,255,0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}

.btn-secondary:hover {
    background: #545b62;
    transform: translateY(-2px);
}
</style>

<?php include '../../includes/footer.php'; ?>