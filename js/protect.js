// Simple authentication check for protected pages
(function() {
    const session = localStorage.getItem('brightMindsSession');
    if (!session) {
        // window.location.replace('../index.html');
        window.location.href = '../index.html';
    }
})();
