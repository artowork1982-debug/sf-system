// assets/js/mobile.js
(function () {
    'use strict';

    function checkOrientation() {
        const warning = document.getElementById('sfRotateWarning');
        if (!warning) return;

        const isLandscape = window.innerWidth > window.innerHeight;
        const isMobile = window.innerWidth <= 900 || window.innerHeight <= 500;

        warning.style.display = (isLandscape && isMobile) ? 'flex' : 'none';
    }

    function setVH() {
        // Aseta CSS-muuttuja viewportin korkeudelle (iOS-tuki)
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', vh + 'px');
        // Ei aseteta kiinteitä height-arvoja elementeille
    }

    window.addEventListener('resize', function () {
        checkOrientation();
        setVH();
    });

    window.addEventListener('orientationchange', function () {
        setTimeout(function () {
            checkOrientation();
            setVH();
        }, 100);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            checkOrientation();
            setVH();
        });
    } else {
        checkOrientation();
        setVH();
    }
})();