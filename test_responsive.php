<?php
/*
 * Mobile Responsive Test Page
 * Use this page to test responsive features on different devices
 */
include 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <h2>📱 Mobile & Laptop Responsive Test Page</h2>
        
        <div class="alert alert-info">
            <strong>Testing Instructions:</strong>
            <ul style="margin: 0.5rem 0 0 1.5rem;">
                <li>On <strong>mobile</strong>: Look for the hamburger menu (☰) in the top right</li>
                <li>On <strong>laptop</strong>: All navigation items should be visible in the navbar</li>
                <li>Try resizing your browser window to see responsive behavior</li>
            </ul>
        </div>
    </div>

    <!-- Navigation Test -->
    <div class="card">
        <h2>1. Navigation Test</h2>
        <p><strong>Mobile:</strong> Tap the hamburger menu icon to open/close navigation</p>
        <p><strong>Laptop:</strong> Hover over "Master Files" to see the dropdown menu</p>
        <div class="alert alert-success">
            ✓ If you can see this page, the header loaded successfully!
        </div>
    </div>

    <!-- Grid System Test -->
    <div class="card">
        <h2>2. Grid System Test</h2>
        <p>These columns should stack on mobile and display side-by-side on laptop:</p>
        <div class="row">
            <div class="col-4">
                <div style="background: #3498db; color: white; padding: 1rem; border-radius: 4px; text-align: center;">
                    Column 1
                </div>
            </div>
            <div class="col-4">
                <div style="background: #27ae60; color: white; padding: 1rem; border-radius: 4px; text-align: center;">
                    Column 2
                </div>
            </div>
            <div class="col-4">
                <div style="background: #e74c3c; color: white; padding: 1rem; border-radius: 4px; text-align: center;">
                    Column 3
                </div>
            </div>
        </div>
    </div>

    <!-- Table Test -->
    <div class="card">
        <h2>3. Responsive Table Test</h2>
        <p><strong>Mobile:</strong> Scroll horizontally or see card view</p>
        <p><strong>Laptop:</strong> See full table width</p>
        
        <table>
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Patient ID">P001</td>
                    <td data-label="Name">John Doe</td>
                    <td data-label="Age">45</td>
                    <td data-label="Gender">Male</td>
                    <td data-label="Phone">077-1234567</td>
                    <td data-label="Address">123 Main St, Kandy</td>
                    <td data-label="Actions">
                        <a href="#" class="btn btn-primary">View</a>
                    </td>
                </tr>
                <tr>
                    <td data-label="Patient ID">P002</td>
                    <td data-label="Name">Jane Smith</td>
                    <td data-label="Age">32</td>
                    <td data-label="Gender">Female</td>
                    <td data-label="Phone">077-7654321</td>
                    <td data-label="Address">456 Lake Rd, Kandy</td>
                    <td data-label="Actions">
                        <a href="#" class="btn btn-primary">View</a>
                    </td>
                </tr>
                <tr>
                    <td data-label="Patient ID">P003</td>
                    <td data-label="Name">Bob Johnson</td>
                    <td data-label="Age">58</td>
                    <td data-label="Gender">Male</td>
                    <td data-label="Phone">077-9876543</td>
                    <td data-label="Address">789 Hill St, Kandy</td>
                    <td data-label="Actions">
                        <a href="#" class="btn btn-primary">View</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Form Test -->
    <div class="card">
        <h2>4. Form Elements Test</h2>
        <p><strong>Mobile:</strong> Input fields should be 16px to prevent auto-zoom on iOS</p>
        <p><strong>Laptop:</strong> Standard form layout</p>
        
        <form style="max-width: 600px;">
            <div class="form-group">
                <label for="test-name">Full Name</label>
                <input type="text" id="test-name" placeholder="Enter your name">
            </div>
            
            <div class="form-group">
                <label for="test-email">Email</label>
                <input type="email" id="test-email" placeholder="your@email.com">
            </div>
            
            <div class="form-group">
                <label for="test-phone">Phone Number</label>
                <input type="tel" id="test-phone" placeholder="077-1234567">
            </div>
            
            <div class="form-group">
                <label for="test-select">Select Option</label>
                <select id="test-select">
                    <option>Option 1</option>
                    <option>Option 2</option>
                    <option>Option 3</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="test-textarea">Comments</label>
                <textarea id="test-textarea" rows="4" placeholder="Enter your comments"></textarea>
            </div>
            
            <button type="button" class="btn btn-primary">Submit Test</button>
            <button type="button" class="btn btn-success">Success Button</button>
            <button type="button" class="btn btn-danger">Danger Button</button>
        </form>
    </div>

    <!-- Button Group Test -->
    <div class="card">
        <h2>5. Button Group Test</h2>
        <p><strong>Mobile:</strong> Buttons stack vertically</p>
        <p><strong>Laptop:</strong> Buttons display horizontally</p>
        
        <div class="btn-group">
            <button class="btn btn-primary">Button 1</button>
            <button class="btn btn-success">Button 2</button>
            <button class="btn btn-warning">Button 3</button>
            <button class="btn btn-danger">Button 4</button>
        </div>
    </div>

    <!-- Touch Target Test -->
    <div class="card">
        <h2>6. Touch Target Size Test</h2>
        <p>All interactive elements should be at least 44x44px on mobile (Apple's recommendation)</p>
        
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <button class="btn btn-primary">Touch Me</button>
            <a href="#" class="btn btn-success">Link Button</a>
            <input type="submit" class="btn btn-warning" value="Submit">
        </div>
        
        <p style="margin-top: 1rem;">
            <small style="color: #666;">On mobile, try tapping these buttons - they should be easy to hit accurately</small>
        </p>
    </div>

    <!-- Device Detection Test -->
    <div class="card">
        <h2>7. Device Detection</h2>
        <div id="device-info">
            <p><strong>Screen Width:</strong> <span id="screen-width"></span>px</p>
            <p><strong>Screen Height:</strong> <span id="screen-height"></span>px</p>
            <p><strong>Device Type:</strong> <span id="device-type"></span></p>
            <p><strong>Orientation:</strong> <span id="orientation"></span></p>
            <p><strong>Touch Supported:</strong> <span id="touch-support"></span></p>
        </div>
    </div>

    <!-- Viewport Test -->
    <div class="card">
        <h2>8. Viewport & Breakpoint Test</h2>
        <div class="hide-mobile">
            <div class="alert alert-info">
                ✓ This message is <strong>visible on laptop</strong> (hidden on mobile)
            </div>
        </div>
        <div class="show-mobile">
            <div class="alert alert-success">
                ✓ This message is <strong>visible on mobile</strong> (hidden on laptop)
            </div>
        </div>
    </div>

    <!-- Performance Test -->
    <div class="card">
        <h2>9. JavaScript Test</h2>
        <p>Click the button to verify mobile helpers are loaded:</p>
        <button onclick="testMobileHelpers()" class="btn btn-primary">Test Mobile Helpers</button>
        <div id="js-test-result" style="margin-top: 1rem;"></div>
    </div>

    <!-- Summary -->
    <div class="card">
        <h2>✅ Test Summary</h2>
        <ul style="line-height: 1.8;">
            <li>✓ Header and navigation loaded</li>
            <li>✓ Responsive grid system working</li>
            <li>✓ Tables are responsive</li>
            <li>✓ Forms are mobile-optimized</li>
            <li>✓ Buttons are touch-friendly</li>
            <li>✓ Viewport is properly configured</li>
        </ul>
        
        <div class="alert alert-success" style="margin-top: 1rem;">
            <strong>Next Steps:</strong>
            <ol style="margin: 0.5rem 0 0 1.5rem;">
                <li>Test this page on your mobile phone</li>
                <li>Try rotating your device (portrait/landscape)</li>
                <li>Navigate to actual system pages (Patient List, Admission List, etc.)</li>
                <li>Test data entry on a form</li>
                <li>Add a home screen shortcut on mobile (see MOBILE_ACCESS_GUIDE.md)</li>
            </ol>
        </div>
    </div>
</div>

<script>
// Device detection script
function updateDeviceInfo() {
    document.getElementById('screen-width').textContent = window.innerWidth;
    document.getElementById('screen-height').textContent = window.innerHeight;
    
    let deviceType = 'Desktop';
    if (window.innerWidth <= 768) {
        deviceType = 'Mobile';
    } else if (window.innerWidth <= 1024) {
        deviceType = 'Tablet';
    }
    document.getElementById('device-type').textContent = deviceType;
    
    const orientation = window.innerHeight > window.innerWidth ? 'Portrait' : 'Landscape';
    document.getElementById('orientation').textContent = orientation;
    
    const touchSupport = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) ? 'Yes' : 'No';
    document.getElementById('touch-support').textContent = touchSupport;
}

function testMobileHelpers() {
    const resultDiv = document.getElementById('js-test-result');
    
    if (typeof MobileHelpers !== 'undefined') {
        resultDiv.innerHTML = '<div class="alert alert-success">✓ Mobile Helpers loaded successfully!<br>' +
            'Mobile Device: ' + (MobileHelpers.isMobileDevice() ? 'Yes' : 'No') + '</div>';
        
        // Test table enhancement
        MobileHelpers.makeTablesResponsive();
        MobileHelpers.addTableLabels();
    } else {
        resultDiv.innerHTML = '<div class="alert alert-danger">✗ Mobile Helpers not loaded. Check console for errors.</div>';
    }
}

// Update device info on load and resize
updateDeviceInfo();
window.addEventListener('resize', updateDeviceInfo);
window.addEventListener('orientationchange', function() {
    setTimeout(updateDeviceInfo, 200);
});
</script>

<?php include 'includes/footer.php'; ?>
