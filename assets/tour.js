import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('tour-banner');
    const configElement = document.getElementById('tour-config');
    if (!banner || !configElement) return;

    const config = JSON.parse(configElement.textContent);
    const root = document.documentElement;
    const mobilePopoverRevealDelayMs = 1000;
    let popoverRevealTimer = null;

    banner.classList.remove('d-none');

    const mobile = () => window.innerWidth < 992;

    function clearPopoverRevealTimer() {
        if (popoverRevealTimer !== null) {
            window.clearTimeout(popoverRevealTimer);
            popoverRevealTimer = null;
        }
    }

    function showTourPopoverImmediately() {
        clearPopoverRevealTimer();
        root.classList.remove('tour-popover-pending');
        root.classList.remove('tour-no-highlight');
        root.classList.add('tour-popover-visible');
    }

    function scheduleTourPopoverReveal(hasHighlight) {
        clearPopoverRevealTimer();
        root.classList.toggle('tour-no-highlight', !hasHighlight);

        if (!mobile()) {
            showTourPopoverImmediately();
            return;
        }

        root.classList.remove('tour-popover-visible');
        root.classList.add('tour-popover-pending');

        popoverRevealTimer = window.setTimeout(() => {
            popoverRevealTimer = null;
            root.classList.add('tour-popover-visible');
        }, mobilePopoverRevealDelayMs);
    }

    function buildSteps() {
        return config.steps.flatMap((stepConfig) => {
            if (stepConfig.desktopOnly && mobile()) {
                return [];
            }

            const selector = mobile()
                ? (stepConfig.mobileElement ?? stepConfig.element ?? stepConfig.desktopElement)
                : (stepConfig.desktopElement ?? stepConfig.element);

            if (selector && !document.querySelector(selector)) {
                return [];
            }

            return [{
                ...(selector ? { element: selector } : {}),
                popover: {
                    title: stepConfig.title,
                    description: stepConfig.description,
                    side: mobile()
                        ? (stepConfig.mobileSide ?? stepConfig.side ?? 'top')
                        : (stepConfig.desktopSide ?? stepConfig.side ?? 'bottom'),
                },
            }];
        });
    }

    async function hideTour() {
        const formData = new FormData();
        formData.set('_token', config.hideToken);

        const response = await fetch(config.hideUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            banner.remove();
        }
    }

    document.getElementById('tour-start-btn').addEventListener('click', () => {
        banner.classList.add('d-none');

        const driverObj = driver({
            smoothScroll: true,
            showProgress: true,
            progressText: config.progressText,
            nextBtnText: config.nextLabel,
            prevBtnText: config.prevLabel,
            doneBtnText: config.doneLabel,
            steps: buildSteps(),
            onHighlightStarted: (element) => scheduleTourPopoverReveal(Boolean(element)),
            onDestroyed: () => {
                showTourPopoverImmediately();
                banner.classList.remove('d-none');
            },
        });

        driverObj.drive();
    });

    document.getElementById('tour-hide-btn').addEventListener('click', () => {
        hideTour();
    });
});
