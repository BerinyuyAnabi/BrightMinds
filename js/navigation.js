/**
 * Smart Dashboard Navigation
 * Redirects to the appropriate dashboard based on user role
 */
function goToDashboard() {
    // Determine correct path based on current location
    const path = window.location.pathname;

    // If we are in the /games/ directory, we need to go up one level
    if (path.includes('/games/')) {
        window.location.href = '../dashboard.php';
    } else {
        // Otherwise, we are likely in root, so just go to dashboard.php
        window.location.href = 'dashboard.php';
    }
}
