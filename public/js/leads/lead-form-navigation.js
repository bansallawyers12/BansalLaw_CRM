/**
 * Lead Form Navigation JavaScript
 * 
 * Handles sidebar navigation, scrolling, and section management for lead forms
 */

/**
 * Scroll to a specific section
 */
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        // Smooth scroll to section
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Update active nav item
        updateActiveNavItem(sectionId);
    }
}

/**
 * Update active navigation item
 */
function updateActiveNavItem(sectionId) {
    // Remove active class from all nav items
    document.querySelectorAll('.nav-menu .nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to corresponding nav item
    const navItems = document.querySelectorAll('.nav-menu .nav-item');
    const sectionMap = {
        'personalSection': 0,
        'visaPassportSection': 1,
        'addressTravelSection': 2,
        'skillsEducationSection': 3,
        'otherInformationSection': 4,
        'familySection': 5,
        'eoiReferenceSection': 6
    };
    
    const index = sectionMap[sectionId];
    if (index !== undefined && navItems[index]) {
        navItems[index].classList.add('active');
    }
}

/**
 * Toggle sidebar visibility (for mobile)
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarNav');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

/**
 * Scroll to top of page
 */
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Handle scroll event to update active nav item
 */
function handleScroll() {
    const sections = [
        'personalSection',
        'visaPassportSection',
        'addressTravelSection',
        'skillsEducationSection',
        'otherInformationSection',
        'familySection',
        'eoiReferenceSection'
    ];
    
    let currentSection = null;
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    // Find which section is currently in view
    sections.forEach(sectionId => {
        const section = document.getElementById(sectionId);
        if (section) {
            const sectionTop = section.offsetTop - 100; // 100px offset for header
            const sectionBottom = sectionTop + section.offsetHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                currentSection = sectionId;
            }
        }
    });
    
    if (currentSection) {
        updateActiveNavItem(currentSection);
    }
}

/**
 * Initialize navigation
 */
function initNavigation() {
    // Add scroll listener with throttle
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        scrollTimeout = setTimeout(handleScroll, 100);
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebarNav');
        const toggle = document.querySelector('.sidebar-toggle');
        
        if (sidebar && toggle) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation);
} else {
    initNavigation();
}

// Make functions globally available
window.scrollToSection = scrollToSection;
window.toggleSidebar = toggleSidebar;
window.scrollToTop = scrollToTop;

