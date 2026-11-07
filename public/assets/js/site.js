const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
const header = document.querySelector('[data-site-header]');
const sentinel = document.querySelector('[data-header-sentinel]');
const toggle = document.querySelector('[data-nav-toggle]');
const nav = document.querySelector('[data-site-nav]');
const revealItems = document.querySelectorAll('.reveal');
const processLane = document.querySelector('[data-process-steps]');

if (toggle && nav) {
    const close = () => {
        nav.classList.remove('is-open');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded','false');
        toggle.setAttribute('aria-label','Abrir menú');
    };
    toggle.addEventListener('click', () => {
        const open = !nav.classList.contains('is-open');
        nav.classList.toggle('is-open',open);
        document.body.classList.toggle('nav-open',open);
        toggle.setAttribute('aria-expanded',String(open));
        toggle.setAttribute('aria-label',open ? 'Cerrar menú' : 'Abrir menú');
    });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click',close));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) { close(); toggle.focus(); }
    });
    matchMedia('(min-width: 1101px)').addEventListener('change', event => { if(event.matches) close(); });
}

if ('IntersectionObserver' in window) {
    if (header && sentinel) {
        new IntersectionObserver(entries => header.classList.toggle('is-scrolled',!entries[0].isIntersecting)).observe(sentinel);
    }
    if (!reducedMotion.matches) {
        const reveal = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                reveal.unobserve(entry.target);
            });
        },{threshold:0.06});
        revealItems.forEach(item => reveal.observe(item));
        document.documentElement.classList.add('motion-ready');
        new IntersectionObserver(entries => {
            entries.forEach(entry => entry.target.classList.toggle('is-in-view',entry.isIntersecting));
        },{threshold:0.05}).observe(document.querySelector('.hero'));
    }
    if (processLane) {
        const process = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                processLane.classList.add('is-sequenced');
                process.disconnect();
            }
        },{threshold:0.1});
        process.observe(processLane);
    }
} else {
    processLane?.classList.add('is-sequenced');
}
reducedMotion.addEventListener('change', event => {
    if (event.matches) {
        document.documentElement.classList.remove('motion-ready');
        processLane?.classList.add('is-sequenced');
    }
});
const requestedScroll = new URLSearchParams(location.search).get('scroll');
if (requestedScroll) {
    addEventListener('load', () => document.getElementById(requestedScroll)?.scrollIntoView({block:'start'}), {once:true});
}
