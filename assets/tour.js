import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const STORAGE_KEY = 'carcoop_tour_v1_done';

document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('tour-banner');
    if (!banner) return;

    if (localStorage.getItem(STORAGE_KEY)) {
        banner.remove();
        return;
    }

    banner.classList.remove('d-none');

    const t = key => banner.dataset[key] ?? key;
    const mobile = () => window.innerWidth < 992;

    function navStep(desktopId, mobileId, titleKey, descKey) {
        const el = mobile()
            ? document.getElementById(mobileId)
            : document.getElementById(desktopId);
        if (!el) return null;
        return {
            element: el,
            popover: {
                title: t(titleKey),
                description: t(descKey),
                side: mobile() ? 'top' : 'bottom',
            },
        };
    }

    function buildSteps() {
        const steps = [];

        // Welcome
        steps.push({
            popover: {
                title: t('stepWelcomeTitle'),
                description: t('stepWelcomeDesc'),
            },
        });

        // Car accordion
        if (document.querySelector('.accordion-button')) {
            steps.push({
                element: '.accordion-button',
                popover: {
                    title: t('stepCarTitle'),
                    description: t('stepCarDesc'),
                    side: 'bottom',
                },
            });
        }

        // Stat cards
        if (document.querySelector('.stat-grid')) {
            steps.push({
                element: '.stat-grid',
                popover: {
                    title: t('stepStatsTitle'),
                    description: t('stepStatsDesc'),
                    side: 'top',
                },
            });
        }

        // Bookings (conditional)
        if (document.getElementById('tour-bookings')) {
            steps.push({
                element: '#tour-bookings',
                popover: {
                    title: t('stepBookingsTitle'),
                    description: t('stepBookingsDesc'),
                    side: 'top',
                },
            });
        }

        // Charts
        if (document.querySelector('.chart-grid')) {
            steps.push({
                element: '.chart-grid',
                popover: {
                    title: t('stepChartsTitle'),
                    description: t('stepChartsDesc'),
                    side: 'top',
                },
            });
        }

        // ── Navigation items ──────────────────────────────────────────
        const navItems = [
            ['nav-messages',  'mobile-nav-messages',  'stepMessagesTitle',  'stepMessagesDesc'],
            ['nav-calendar',  'mobile-nav-calendar',  'stepCalendarTitle',  'stepCalendarDesc'],
            ['nav-trips',     'mobile-nav-trips',     'stepTripsTitle',     'stepTripsDesc'],
            ['nav-expenses',  'mobile-nav-expenses',  'stepExpensesTitle',  'stepExpensesDesc'],
            ['nav-payments',  'mobile-nav-payments',  'stepPaymentsTitle',  'stepPaymentsDesc'],
            ['nav-parking',   'mobile-nav-parking',   'stepParkingTitle',   'stepParkingDesc'],
        ];

        for (const [desktopId, mobileId, titleKey, descKey] of navItems) {
            const step = navStep(desktopId, mobileId, titleKey, descKey);
            if (step) steps.push(step);
        }

        // Admin and user dropdowns — desktop only (not in mobile bottom nav)
        if (!mobile()) {
            const adminEl = document.getElementById('nav-admin');
            if (adminEl) {
                steps.push({
                    element: adminEl,
                    popover: {
                        title: t('stepAdminTitle'),
                        description: t('stepAdminDesc'),
                        side: 'bottom',
                    },
                });
            }
            const userEl = document.getElementById('nav-user');
            if (userEl) {
                steps.push({
                    element: userEl,
                    popover: {
                        title: t('stepUserTitle'),
                        description: t('stepUserDesc'),
                        side: 'bottom',
                    },
                });
            }
        }

        return steps;
    }

    function dismiss() {
        localStorage.setItem(STORAGE_KEY, '1');
        banner.remove();
    }

    document.getElementById('tour-start-btn').addEventListener('click', () => {
        banner.remove();

        const driverObj = driver({
            showProgress: true,
            progressText: t('progressText'),
            nextBtnText: t('btnNext'),
            prevBtnText: t('btnPrev'),
            doneBtnText: t('btnDone'),
            steps: buildSteps(),
            onDestroyed: () => localStorage.setItem(STORAGE_KEY, '1'),
        });

        driverObj.drive();
    });

    document.getElementById('tour-dismiss-btn').addEventListener('click', dismiss);
});
