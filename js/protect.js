// Simple authentication check for protected pages
(function() {
    const session = localStorage.getItem('brightMindsSession');
    if (!session) {
        // window.location.replace('/../index.html');
        window.location.href = 'http://169.239.251.102:341/~logan.anabi/BrightMinds/BrightMinds/index.html';
    }
})();
