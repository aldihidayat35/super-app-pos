/**
 * GudangToko — Sidebar Pin & Hover State Manager
 * Mengatur interaksi pin/unpin desktop, hover expand, dan sinkronisasi preferensi di localStorage.
 */

export const initializeSidebarPin = () => {
    const pinBtn = document.getElementById('kt_app_sidebar_pin_btn');
    const sidebar = document.getElementById('kt_app_sidebar');

    if (!pinBtn || !sidebar) return;

    const updatePinButtonState = (isPinned) => {
        if (isPinned) {
            pinBtn.classList.add('active', 'is-pinned');
            pinBtn.setAttribute('title', 'Lepaskan Pin Sidebar (Sembunyikan Otomatis)');
            pinBtn.setAttribute('aria-label', 'Lepaskan Pin Sidebar');
            pinBtn.setAttribute('aria-pressed', 'true');
        } else {
            pinBtn.classList.remove('active', 'is-pinned');
            pinBtn.setAttribute('title', 'Sematkan Sidebar (Selalu Terbuka)');
            pinBtn.setAttribute('aria-label', 'Sematkan Sidebar');
            pinBtn.setAttribute('aria-pressed', 'false');
        }
    };

    const setPinState = (isPinned, persist = true) => {
        if (isPinned) {
            document.body.removeAttribute('data-kt-app-sidebar-minimize');
        } else {
            document.body.setAttribute('data-kt-app-sidebar-minimize', 'on');
        }

        updatePinButtonState(isPinned);

        if (persist) {
            localStorage.setItem('gudangtoko-sidebar-pinned', isPinned ? 'true' : 'false');
        }
    };

    // Load initial state
    const savedState = localStorage.getItem('gudangtoko-sidebar-pinned');
    const isPinned = savedState !== 'false';

    if (window.innerWidth >= 992) {
        setPinState(isPinned, false);
    } else {
        document.body.removeAttribute('data-kt-app-sidebar-minimize');
    }

    // Toggle event
    pinBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const currentlyPinned = !document.body.hasAttribute('data-kt-app-sidebar-minimize')
            || document.body.getAttribute('data-kt-app-sidebar-minimize') !== 'on';
        setPinState(!currentlyPinned, true);
    });

    const isUnpinned = () => document.body.getAttribute('data-kt-app-sidebar-minimize') === 'on' && window.innerWidth >= 992;

    const collapseOpenAccordions = () => {
        if (!isUnpinned()) return;
        const openAccordions = sidebar.querySelectorAll('.menu-item.menu-accordion.show:not(.here)');
        openAccordions.forEach((item) => {
            item.classList.remove('show', 'hover', 'showing');
            const sub = item.querySelector('.menu-sub');
            if (sub) {
                sub.classList.remove('show');
                sub.style.display = 'none';
                sub.style.height = '';
            }
        });
    };

    // When cursor leaves the sidebar in unpinned mode, collapse accordions & blur focused element
    sidebar.addEventListener('mouseleave', () => {
        if (isUnpinned()) {
            if (document.activeElement && sidebar.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            collapseOpenAccordions();
        }
    });

    // When clicking outside the sidebar, also collapse open accordions in unpinned mode
    document.addEventListener('click', (event) => {
        if (isUnpinned() && !sidebar.contains(event.target)) {
            collapseOpenAccordions();
        }
    });

    // Handle responsive window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            const currentSaved = localStorage.getItem('gudangtoko-sidebar-pinned') !== 'false';
            setPinState(currentSaved, false);
        } else {
            document.body.removeAttribute('data-kt-app-sidebar-minimize');
        }
    });
};

