<?php
include 'includes/header.php';
?>

<div class="container">
    <div class="card" style="text-align:center;padding:4rem;position:relative;overflow:hidden;">
        <!-- Watermark SVG behind content -->
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:0;">
            <svg viewBox="0 0 1200 300" preserveAspectRatio="xMidYMid meet" style="width:90%;height:auto;opacity:0.06;transform:rotate(-18deg);">
                <defs>
                    <style type="text/css">@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;700&display=swap');</style>
                </defs>
                <text x="50%" y="45%" text-anchor="middle" font-family="Roboto, Arial, sans-serif" font-weight="700" font-size="72" fill="#000">CAPD Unit</text>
                <text x="50%" y="65%" text-anchor="middle" font-family="Roboto, Arial, sans-serif" font-weight="400" font-size="36" fill="#000">National Hospital Kandy — Sri Lanka</text>
            </svg>
        </div>

        <div style="position:relative;z-index:1;">
            <h1 style="font-size:2rem;margin-bottom:0.5rem;">Woard &amp; Clinic Management System</h1>
            <p style="font-weight:600;color:#666;margin-bottom:1rem;">CAPD Unit — National Hospital Kandy</p>
            <p style="color:#444;max-width:760px;margin:0 auto 1.5rem;">Welcome to the hospital management front page. Use the top navigation to access system sections.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
