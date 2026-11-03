document.addEventListener('DOMContentLoaded', function () {
    const navLinks = document.querySelectorAll('.j3-nav a');
    if (!navLinks.length) return;
    function normalizePath(path) {
        return path.replace(/\/+$/, '') || '/';
    }
    const currentPath = normalizePath(window.location.pathname);
    navLinks.forEach(function (link) {
        try {
            const linkUrl = new URL(link.href, window.location.origin);
            const linkPath = normalizePath(linkUrl.pathname);

            if (linkUrl.origin === window.location.origin && linkPath === currentPath) {
                link.classList.add('current-menu-item');
            }
        } catch (e) {
        }
        link.addEventListener('click', function () {
            navLinks.forEach(function (el) {
                el.classList.remove('current-menu-item');
            });
            this.classList.add('current-menu-item');
        });
    });
});
