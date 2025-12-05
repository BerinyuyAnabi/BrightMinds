// Simple authentication check for protected pages
// Only redirects if there's absolutely no session - doesn't check role
(function() {
    // Only check on pages that need protection
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    // Pages that don't need protection
    const publicPages = ['index.html', 'parent-auth.html'];
    if (publicPages.includes(currentPage)) {
        return; // Don't check on public pages
    }
    
    // Check if session exists (don't redirect based on role here)
    const session = localStorage.getItem('brightMindsSession');
    if (!session) {
        // Only redirect if we're not already on a login page
        if (!currentPath.includes('index.html') && !currentPath.includes('parent-auth.html')) {
            // Determine which login page to redirect to based on current page
            if (currentPath.includes('parent') || currentPath.includes('Parent')) {
                window.location.href = 'parent-auth.html';
            } else {
                window.location.href = 'index.html';
            }
        }
    }
    // If session exists, don't redirect - let page-specific scripts handle role checks
})();
