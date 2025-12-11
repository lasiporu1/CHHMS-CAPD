/**
 * Patient Search Component JavaScript
 * Provides autocomplete functionality for patient selection across all forms
 * 
 * Usage:
 * new PatientSearch('input-id', {
 *     onSelect: function(patient) { console.log('Selected:', patient); },
 *     placeholder: 'Search patients...',
 *     minChars: 1
 * });
 */

class PatientSearch {
    constructor(inputId, options = {}) {
        this.inputElement = document.getElementById(inputId);
        if (!this.inputElement) {
            console.error('Patient Search: Input element not found:', inputId);
            return;
        }
        
        this.options = {
            minChars: options.minChars || parseInt(this.inputElement.dataset.minChars) || 1,
            delay: options.delay || 200,
            maxResults: options.maxResults || 15,
            placeholder: options.placeholder || '🔍 Search by patient name, NIC, hospital number, clinic number...',
            onSelect: options.onSelect || function(patient) { console.log('Patient selected:', patient); },
            onClear: options.onClear || function() { console.log('Selection cleared'); },
            showSelectedDisplay: options.showSelectedDisplay !== false,
            ajaxUrl: options.ajaxUrl || this.inputElement.dataset.ajaxUrl || '../includes/patient_search_ajax.php'
        };
        
        this.selectedPatient = null;
        this.selectedIndex = -1;
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        this.setupElements();
        this.setupEventListeners();
        this.inputElement.setAttribute('placeholder', this.options.placeholder);
        this.inputElement.setAttribute('autocomplete', 'off');
    }
    
    setupElements() {
        // Add CSS if not already included
        if (!document.querySelector('link[href*="patient_search.css"]') && 
            !document.querySelector('style[data-patient-search]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '../includes/patient_search.css';
            document.head.appendChild(link);
        }
        
        // Wrap input in container if not already wrapped
        if (!this.inputElement.parentElement.classList.contains('patient-search-container')) {
            const container = document.createElement('div');
            container.className = 'patient-search-container';
            this.inputElement.parentNode.insertBefore(container, this.inputElement);
            container.appendChild(this.inputElement);
        }
        
        this.container = this.inputElement.parentElement;
        this.inputElement.className += ' patient-search-input';
        
        // Create results dropdown
        this.resultsElement = document.createElement('div');
        this.resultsElement.className = 'patient-search-results';
        this.container.appendChild(this.resultsElement);
        
        // Create selected patient display if enabled
        if (this.options.showSelectedDisplay) {
            this.selectedDisplay = document.createElement('div');
            this.selectedDisplay.className = 'selected-patient-display';
            this.selectedDisplay.innerHTML = `
                <div class="selected-patient-info">
                    <div class="selected-patient-details">
                        <h4></h4>
                        <p></p>
                    </div>
                    <button type="button" class="clear-selection-btn">✖ Clear</button>
                </div>
            `;
            this.container.appendChild(this.selectedDisplay);
            
            // Clear selection button
            this.selectedDisplay.querySelector('.clear-selection-btn').addEventListener('click', () => {
                this.clearSelection();
            });
        }
    }
    
    setupEventListeners() {
        // Input events
        this.inputElement.addEventListener('input', (e) => this.handleInput(e));
        this.inputElement.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.inputElement.addEventListener('focus', (e) => this.handleFocus(e));
        
        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.hideResults();
            }
        });
    }
    
    handleInput(e) {
        const searchTerm = e.target.value.trim();
        this.selectedIndex = -1;
        
        clearTimeout(this.searchTimeout);
        
        if (searchTerm.length < this.options.minChars) {
            this.hideResults();
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.performSearch(searchTerm);
        }, this.options.delay);
    }
    
    handleKeydown(e) {
        const items = this.resultsElement.querySelectorAll('.patient-search-result-item:not(.patient-search-no-results):not(.patient-search-error)');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelection(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelection(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    this.selectPatient(JSON.parse(items[this.selectedIndex].dataset.patient));
                }
                break;
            case 'Escape':
                this.hideResults();
                this.selectedIndex = -1;
                break;
        }
    }
    
    handleFocus(e) {
        // If there are results and input has value, show them
        if (this.inputElement.value.trim().length >= this.options.minChars && 
            this.resultsElement.children.length > 0) {
            this.showResults();
        }
    }
    
    async performSearch(searchTerm) {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.options.ajaxUrl}?search_patients=1&search_term=${encodeURIComponent(searchTerm)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.displayResults(data);
            
        } catch (error) {
            console.error('Patient search error:', error);
            this.showError('Search failed. Please try again.');
        }
    }
    
    displayResults(patients) {
        if (!patients || patients.length === 0) {
            this.showNoResults();
            return;
        }
        
        const html = patients.map(patient => {
            return `
                <div class="patient-search-result-item" data-patient='${JSON.stringify(patient)}'>
                    <div class="patient-result-name">${this.escapeHtml(patient.full_name)} (${this.escapeHtml(patient.calling_name)})</div>
                    <div class="patient-result-details">
                        <span class="patient-result-badge patient-result-nic">NIC: ${this.escapeHtml(patient.nic)}</span>
                        ${patient.hospital_number ? `<span class="patient-result-badge patient-result-hospital">PHN: ${this.escapeHtml(patient.hospital_number)}</span>` : ''}
                        <span class="patient-result-badge patient-result-clinic">Clinic: ${this.escapeHtml(patient.clinic_number)}</span>
                    </div>
                </div>
            `;
        }).join('');
        
        // Add result count info if at max results
        const resultInfo = patients.length >= this.options.maxResults ? 
            '<div class="patient-search-no-results">Showing first ' + this.options.maxResults + ' results. Type more characters for specific search.</div>' : '';
        
        this.resultsElement.innerHTML = html + resultInfo;
        
        // Add click handlers
        this.resultsElement.querySelectorAll('.patient-search-result-item[data-patient]').forEach(item => {
            item.addEventListener('click', () => {
                const patient = JSON.parse(item.dataset.patient);
                this.selectPatient(patient);
            });
        });
        
        this.showResults();
    }
    
    selectPatient(patient) {
        this.selectedPatient = patient;
        this.inputElement.value = patient.calling_name;
        this.hideResults();
        
        // Show selected display
        if (this.options.showSelectedDisplay && this.selectedDisplay) {
            const details = this.selectedDisplay.querySelector('.selected-patient-details');
            details.querySelector('h4').textContent = `${patient.calling_name} (${patient.full_name})`;
            details.querySelector('p').textContent = `NIC: ${patient.nic} | Clinic: ${patient.clinic_number} ${patient.hospital_number ? '| Hospital: ' + patient.hospital_number : ''}`;
            this.selectedDisplay.classList.add('show');
        }
        
        // Trigger callback
        this.options.onSelect(patient);
    }
    
    clearSelection() {
        this.selectedPatient = null;
        this.inputElement.value = '';
        if (this.selectedDisplay) {
            this.selectedDisplay.classList.remove('show');
        }
        this.hideResults();
        this.inputElement.focus();
        this.options.onClear();
    }
    
    getSelectedPatient() {
        return this.selectedPatient;
    }
    
    setSelectedPatient(patient) {
        if (patient) {
            this.selectPatient(patient);
        } else {
            this.clearSelection();
        }
    }
    
    showResults() {
        this.resultsElement.style.display = 'block';
        this.inputElement.classList.add('has-results');
    }
    
    hideResults() {
        this.resultsElement.style.display = 'none';
        this.inputElement.classList.remove('has-results');
        this.selectedIndex = -1;
    }
    
    showLoading() {
        this.resultsElement.innerHTML = '<div class="patient-search-loading">🔍 Searching...</div>';
        this.showResults();
    }
    
    showError(message) {
        this.resultsElement.innerHTML = `<div class="patient-search-error">${message}</div>`;
        this.showResults();
    }
    
    showNoResults() {
        this.resultsElement.innerHTML = '<div class="patient-search-no-results">No patients found matching your search</div>';
        this.showResults();
    }
    
    updateSelection(items) {
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
    }
    
    destroy() {
        clearTimeout(this.searchTimeout);
        // Remove event listeners and clean up
        this.inputElement.removeEventListener('input', this.handleInput);
        this.inputElement.removeEventListener('keydown', this.handleKeydown);
        this.inputElement.removeEventListener('focus', this.handleFocus);
    }
}

// Global function for easy integration
window.PatientSearch = PatientSearch;

// Auto-initialize if data attributes are present
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-patient-search]').forEach(input => {
        const options = {};
        
        // Parse data attributes
        if (input.dataset.minChars) options.minChars = parseInt(input.dataset.minChars);
        if (input.dataset.placeholder) options.placeholder = input.dataset.placeholder;
        if (input.dataset.onSelect) options.onSelect = window[input.dataset.onSelect];
        if (input.dataset.showSelectedDisplay) options.showSelectedDisplay = input.dataset.showSelectedDisplay === 'true';
        
        new PatientSearch(input.id, options);
    });
});