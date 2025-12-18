/**
 * Mobile Helper Functions for CHHMS
 * Enhances mobile experience with table wrapping and touch optimization
 */

(function() {
    'use strict';
    
    /**
     * Wraps all tables in a responsive wrapper for horizontal scrolling
     */
    function makeTablesResponsive() {
        const tables = document.querySelectorAll('table:not(.table-responsive table)');
        
        tables.forEach(function(table) {
            // Skip if already wrapped
            if (table.parentElement.classList.contains('table-responsive')) {
                return;
            }
            
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }
    
    /**
     * Adds data-label attributes to table cells for mobile card view
     * This allows CSS to display column headers for each cell on mobile
     */
    function addTableLabels() {
        const tables = document.querySelectorAll('table');
        
        tables.forEach(function(table) {
            const headers = table.querySelectorAll('thead th');
            const headerTexts = Array.from(headers).map(th => th.textContent.trim());
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const cells = row.querySelectorAll('td');
                cells.forEach(function(cell, index) {
                    if (headerTexts[index]) {
                        cell.setAttribute('data-label', headerTexts[index]);
                    }
                });
            });
        });
    }
    
    /**
     * Detects if user is on a mobile device
     */
    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               window.innerWidth <= 768;
    }
    
    /**
     * Optimizes viewport for better mobile experience
     */
    function optimizeViewport() {
        const viewport = document.querySelector('meta[name="viewport"]');
        if (!viewport && isMobileDevice()) {
            const meta = document.createElement('meta');
            meta.name = 'viewport';
            meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes';
            document.head.appendChild(meta);
        }
    }
    
    /**
     * Adds touch-friendly classes to interactive elements
     */
    function enhanceTouchTargets() {
        if (!isMobileDevice()) return;
        
        // Add minimum touch target size
        const interactiveElements = document.querySelectorAll('a, button, input[type="submit"], input[type="button"], .btn');
        interactiveElements.forEach(function(element) {
            if (!element.style.minHeight) {
                element.style.minHeight = '44px';
            }
        });
    }
    
    /**
     * Smooth scroll behavior for anchor links
     */
    function enableSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    /**
     * Handles orientation change events
     */
    function handleOrientationChange() {
        window.addEventListener('orientationchange', function() {
            // Reload tables on orientation change for better layout
            setTimeout(function() {
                makeTablesResponsive();
            }, 200);
        });
    }
    
    /**
     * Adds pull-to-refresh functionality (optional enhancement)
     */
    function addPullToRefresh() {
        if (!isMobileDevice()) return;
        
        let touchStartY = 0;
        let pullDistance = 0;
        const threshold = 80;
        
        document.addEventListener('touchstart', function(e) {
            if (window.pageYOffset === 0) {
                touchStartY = e.touches[0].pageY;
            }
        }, { passive: true });
        
        document.addEventListener('touchmove', function(e) {
            if (touchStartY === 0) return;
            pullDistance = e.touches[0].pageY - touchStartY;
        }, { passive: true });
        
        document.addEventListener('touchend', function() {
            if (pullDistance > threshold && window.pageYOffset === 0) {
                // Optionally reload the page or show a refresh indicator
                // location.reload();
            }
            touchStartY = 0;
            pullDistance = 0;
        }, { passive: true });
    }
    
    /**
     * Initialize all mobile enhancements
     */
    function init() {
        // Run on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                optimizeViewport();
                makeTablesResponsive();
                addTableLabels();
                enhanceTouchTargets();
                enableSmoothScroll();
                handleOrientationChange();
                // addPullToRefresh(); // Uncomment if you want pull-to-refresh
            });
        } else {
            optimizeViewport();
            makeTablesResponsive();
            addTableLabels();
            enhanceTouchTargets();
            enableSmoothScroll();
            handleOrientationChange();
            // addPullToRefresh(); // Uncomment if you want pull-to-refresh
        }
    }
    
    // Auto-initialize
    init();
    
    // Expose public API
    window.MobileHelpers = {
        makeTablesResponsive: makeTablesResponsive,
        addTableLabels: addTableLabels,
        isMobileDevice: isMobileDevice,
        enhanceTouchTargets: enhanceTouchTargets
    };
})();
