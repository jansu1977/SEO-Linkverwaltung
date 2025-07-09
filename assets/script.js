// Basis-JavaScript für das System
document.addEventListener("DOMContentLoaded", function() {
    // Auto-hide Flash Messages
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
    
    // Bestätigungsdialoge für Lösch-Aktionen
    const deleteLinks = document.querySelectorAll('a[onclick*="confirm"]');
    deleteLinks.forEach(link => {
        link.addEventListener("click", function(e) {
            const confirmed = confirm("Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?");
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Form-Validierung
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ff6b6b';
                    isValid = false;
                } else {
                    field.style.borderColor = '#3a3d52';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Bitte füllen Sie alle Pflichtfelder aus.');
            }
        });
    });
    
    // URL-Validierung
    const urlInputs = document.querySelectorAll('input[type="url"]');
    urlInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url && !isValidUrl(url)) {
                this.style.borderColor = '#ff6b6b';
                showTooltip(this, 'Bitte geben Sie eine gültige URL ein');
            } else {
                this.style.borderColor = '#3a3d52';
                hideTooltip(this);
            }
        });
    });
    
    // E-Mail-Validierung
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.style.borderColor = '#ff6b6b';
                showTooltip(this, 'Bitte geben Sie eine gültige E-Mail-Adresse ein');
            } else {
                this.style.borderColor = '#3a3d52';
                hideTooltip(this);
            }
        });
    });
});

// Hilfsfunktionen
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showTooltip(element, message) {
    hideTooltip(element); // Entferne existierende Tooltips
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.innerHTML = message;
    tooltip.style.cssText = `
        position: absolute;
        background: #ff6b6b;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 1000;
        margin-top: 5px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    `;
    
    element.parentNode.style.position = 'relative';
    element.parentNode.appendChild(tooltip);
    element.tooltipElement = tooltip;
}

function hideTooltip(element) {
    if (element.tooltipElement) {
        element.tooltipElement.remove();
        element.tooltipElement = null;
    }
}

// Blog-Filter-Funktionen (falls benötigt)
function filterBlogs() {
    const search = document.getElementById('blogSearch');
    const topicFilter = document.getElementById('topicFilter');
    const cards = document.querySelectorAll('.blog-card');
    
    if (!search || !cards.length) return;
    
    const searchTerm = search.value.toLowerCase();
    const selectedTopic = topicFilter ? topicFilter.value.toLowerCase() : '';
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const url = card.dataset.url || '';
        const topics = card.dataset.topics || '';
        
        const searchMatch = !searchTerm || name.includes(searchTerm) || url.includes(searchTerm);
        const topicMatch = !selectedTopic || topics.includes(selectedTopic);
        
        const matches = searchMatch && topicMatch;
        card.style.display = matches ? 'block' : 'none';
    });
}

// Tab-Funktionalität
function showTab(tabName) {
    // Alle Tabs verstecken
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Alle Tab-Buttons deaktivieren
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Gewählten Tab anzeigen
    const targetTab = document.getElementById(tabName + 'Tab');
    if (targetTab) {
        targetTab.style.display = 'block';
    }
    
    // Button aktivieren
    if (event && event.target) {
        event.target.classList.add('active');
    }
}