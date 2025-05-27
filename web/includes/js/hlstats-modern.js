/**
 * HLstatsX Community Edition - Modern JavaScript
 * Replaces legacy MooTools with modern vanilla JavaScript
 */

// Theme Management
class ThemeManager {
    constructor() {
        this.currentTheme = this.getStoredTheme();
        this.init();
    }
    
    init() {
        this.applyTheme(this.currentTheme);
        this.updateThemeIcon();
        
        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (this.currentTheme === 'auto') {
                    this.applyTheme('auto');
                }
            });
        }
    }
    
    getStoredTheme() {
        const stored = localStorage.getItem('hlx-theme') || this.getCookie('theme_mode');
        return stored || 'light';
    }
    
    applyTheme(theme) {
        let actualTheme = theme;
        
        if (theme === 'auto') {
            actualTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        
        document.documentElement.setAttribute('data-theme', actualTheme);
        this.currentTheme = theme;
        
        // Store preference
        localStorage.setItem('hlx-theme', theme);
        this.setCookie('theme_mode', actualTheme, 30);
        
        this.updateThemeIcon();
    }
    
    toggle() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }
    
    updateThemeIcon() {
        const icon = document.getElementById('theme-icon');
        if (icon) {
            const actualTheme = document.documentElement.getAttribute('data-theme');
            icon.className = actualTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
    }
    
    // Cookie utilities
    setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
    }
    
    getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
}

// Enhanced Search functionality
class SearchManager {
    constructor() {
        this.searchInput = document.querySelector('#player-search');
        this.searchResults = document.querySelector('#search-results');
        this.searchTimeout = null;
        this.cache = new Map();
        
        if (this.searchInput) {
            this.init();
        }
    }
    
    init() {
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length >= 2) {
                this.searchTimeout = setTimeout(() => this.performSearch(query), 300);
            } else {
                this.hideResults();
            }
        });
        
        // Hide results when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.searchInput.contains(e.target) && !this.searchResults.contains(e.target)) {
                this.hideResults();
            }
        });
    }
    
    async performSearch(query) {
        // Check cache first
        if (this.cache.has(query)) {
            this.displayResults(this.cache.get(query));
            return;
        }
        
        try {
            this.showLoading();
            
            // Use the existing autocomplete.php endpoint
            const response = await fetch(`autocomplete.php?q=${encodeURIComponent(query)}`);
            const results = await response.json();
            
            this.cache.set(query, results);
            this.displayResults(results);
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search failed. Please try again.');
        }
    }
    
    displayResults(results) {
        if (!this.searchResults) {
            this.createResultsContainer();
        }
        
        if (results.length === 0) {
            this.searchResults.innerHTML = '<div class="dropdown-item text-muted">No players found</div>';
        } else {
            this.searchResults.innerHTML = results.map(player => `
                <a href="?mode=playerinfo&player=${player.id}" class="dropdown-item">
                    <div class="d-flex align-items-center">
                        <img src="${player.avatar || 'hlstatsimg/noimage.gif'}" 
                             alt="" class="rounded me-2" width="24" height="24">
                        <div>
                            <div class="fw-semibold">${this.escapeHtml(player.name)}</div>
                            <small class="text-muted">Rank: ${player.rank}</small>
                        </div>
                    </div>
                </a>
            `).join('');
        }
        
        this.showResults();
    }
    
    createResultsContainer() {
        this.searchResults = document.createElement('div');
        this.searchResults.id = 'search-results';
        this.searchResults.className = 'dropdown-menu show position-absolute';
        this.searchResults.style.cssText = 'top: 100%; left: 0; right: 0; max-height: 300px; overflow-y: auto; z-index: 1050;';
        
        this.searchInput.parentNode.style.position = 'relative';
        this.searchInput.parentNode.appendChild(this.searchResults);
    }
    
    showLoading() {
        if (!this.searchResults) {
            this.createResultsContainer();
        }
        this.searchResults.innerHTML = '<div class="dropdown-item"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>';
        this.showResults();
    }
    
    showError(message) {
        this.searchResults.innerHTML = `<div class="dropdown-item text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${message}</div>`;
        this.showResults();
    }
    
    showResults() {
        this.searchResults.classList.add('show');
    }
    
    hideResults() {
        if (this.searchResults) {
            this.searchResults.classList.remove('show');
        }
    }
    
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Table enhancements
class TableManager {
    constructor() {
        this.initSortableTables();
        this.initResponsiveTables();
    }
    
    initSortableTables() {
        document.querySelectorAll('.hlx-table[data-sortable="true"]').forEach(table => {
            const headers = table.querySelectorAll('th[data-sort]');
            
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.innerHTML += ' <i class="bi bi-arrow-down-up ms-1"></i>';
                
                header.addEventListener('click', () => {
                    this.sortTable(table, header.getAttribute('data-sort'));
                });
            });
        });
    }
    
    sortTable(table, column) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(table.querySelectorAll('th')).findIndex(th => 
            th.getAttribute('data-sort') === column
        );
        
        if (columnIndex === -1) return;
        
        const isNumeric = rows.every(row => {
            const cell = row.cells[columnIndex];
            const text = cell.textContent.trim().replace(/[,]/g, '');
            return !isNaN(text) && text !== '';
        });
        
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            if (isNumeric) {
                return parseFloat(aVal.replace(/[,]/g, '')) - parseFloat(bVal.replace(/[,]/g, ''));
            } else {
                return aVal.localeCompare(bVal);
            }
        });
        
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }
    
    initResponsiveTables() {
        document.querySelectorAll('.hlx-table').forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
}

// Animation utilities
class AnimationManager {
    static fadeIn(element, duration = 300) {
        element.style.opacity = '0';
        element.style.display = 'block';
        
        const start = performance.now();
        
        function animate(currentTime) {
            const elapsed = currentTime - start;
            const progress = Math.min(elapsed / duration, 1);
            
            element.style.opacity = progress;
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }
        
        requestAnimationFrame(animate);
    }
    
    static slideDown(element, duration = 300) {
        element.style.height = '0';
        element.style.overflow = 'hidden';
        element.style.display = 'block';
        
        const targetHeight = element.scrollHeight;
        const start = performance.now();
        
        function animate(currentTime) {
            const elapsed = currentTime - start;
            const progress = Math.min(elapsed / duration, 1);
            
            element.style.height = (targetHeight * progress) + 'px';
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                element.style.height = '';
                element.style.overflow = '';
            }
        }
        
        requestAnimationFrame(animate);
    }
    
    static countUp(element, target, duration = 1000) {
        const start = performance.now();
        const startValue = 0;
        
        function animate(currentTime) {
            const elapsed = currentTime - start;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(startValue + (target - startValue) * progress);
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }
        
        requestAnimationFrame(animate);
    }
}

// AJAX utilities for dynamic content loading
class AjaxManager {
    static async loadContent(url, container) {
        try {
            container.classList.add('hlx-loading');
            
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const html = await response.text();
            container.innerHTML = html;
            
            // Reinitialize components for new content
            new TableManager();
            
        } catch (error) {
            console.error('Content loading error:', error);
            container.innerHTML = '<div class="alert alert-danger">Failed to load content. Please refresh the page.</div>';
        } finally {
            container.classList.remove('hlx-loading');
        }
    }
    
    static async refreshStats() {
        const statElements = document.querySelectorAll('[data-stat-refresh]');
        
        for (const element of statElements) {
            const url = element.getAttribute('data-stat-refresh');
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                // Animate number changes
                const currentValue = parseInt(element.textContent.replace(/[,]/g, ''));
                const newValue = parseInt(data.value);
                
                if (currentValue !== newValue) {
                    AnimationManager.countUp(element, newValue);
                }
            } catch (error) {
                console.error('Stat refresh error:', error);
            }
        }
    }
}

// Global functions for backward compatibility and theme toggle
function toggleTheme() {
    window.hlxTheme.toggle();
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize managers
    window.hlxTheme = new ThemeManager();
    window.hlxSearch = new SearchManager();
    window.hlxTables = new TableManager();
    
    // Add fade-in animation to content blocks
    document.querySelectorAll('.hlx-block').forEach((block, index) => {
        setTimeout(() => {
            block.classList.add('hlx-fade-in');
        }, index * 100);
    });
    
    // Initialize count-up animations for stats
    document.querySelectorAll('.hlx-stat-number').forEach(element => {
        const target = parseInt(element.textContent.replace(/[,]/g, ''));
        if (target > 0) {
            element.textContent = '0';
            setTimeout(() => {
                AnimationManager.countUp(element, target);
            }, 500);
        }
    });
    
    // Auto-refresh stats every 30 seconds
    if (document.querySelectorAll('[data-stat-refresh]').length > 0) {
        setInterval(() => {
            AjaxManager.refreshStats();
        }, 30000);
    }
    
    console.log('HLstatsX Modern Theme initialized');
});

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ThemeManager,
        SearchManager,
        TableManager,
        AnimationManager,
        AjaxManager
    };
}