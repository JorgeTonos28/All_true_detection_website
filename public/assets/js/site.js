document.documentElement.classList.add("js");

const header = document.querySelector("[data-site-header]");
const toggle = document.querySelector("[data-nav-toggle]");
const nav = document.querySelector("[data-site-nav]");

if (header) {
    const updateHeader = () => {
        header.classList.toggle("is-scrolled", window.scrollY > 12);
    };

    updateHeader();
    window.addEventListener("scroll", updateHeader, { passive: true });
}

if (toggle && nav) {
    toggle.addEventListener("click", () => {
        const isOpen = nav.classList.toggle("is-open");
        document.body.classList.toggle("nav-open", isOpen);
        toggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
            nav.classList.remove("is-open");
            document.body.classList.remove("nav-open");
            toggle.setAttribute("aria-expanded", "false");
        });
    });
}

const revealItems = document.querySelectorAll(".reveal");
const metricNumbers = document.querySelectorAll(".metric-number");
const processLane = document.querySelector("[data-process-steps]");

const animateMetric = (metric) => {
    if (metric.dataset.counted === "true") {
        return;
    }

    metric.dataset.counted = "true";

    const from = Number(metric.dataset.countFrom || 0);
    const to = Number(metric.dataset.countTo || 0);
    const prefix = metric.dataset.prefix || "";
    const suffix = metric.dataset.suffix || "";
    const duration = 1150;
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.round(from + (to - from) * eased);

        metric.textContent = `${prefix}${value}${suffix}`;

        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    };

    requestAnimationFrame(tick);
};

if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    revealItems.forEach((item) => observer.observe(item));

    const metricObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                animateMetric(entry.target);
                metricObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.45 });

    metricNumbers.forEach((metric) => metricObserver.observe(metric));

    if (processLane) {
        const processObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    processLane.classList.add("is-sequenced");
                    processObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.35 });

        processObserver.observe(processLane);
    }
} else {
    revealItems.forEach((item) => item.classList.add("is-visible"));
    metricNumbers.forEach(animateMetric);
    processLane?.classList.add("is-sequenced");
}

document.querySelectorAll(".risk-card").forEach((card) => {
    card.addEventListener("click", () => {
        card.classList.add("is-pulsing");
        window.setTimeout(() => card.classList.remove("is-pulsing"), 1200);
    });
});

const requestedScroll = new URLSearchParams(window.location.search).get("scroll");

if (requestedScroll) {
    window.addEventListener("load", () => {
        window.setTimeout(() => {
            document.getElementById(requestedScroll)?.scrollIntoView({ block: "start" });
        }, 120);
    });
}
