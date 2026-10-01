/*
 * Mega menu under "Altus Knowledge and Performance".
 *
 * Desktop (> 1080px): opens on hover (with intent delay), on keyboard focus of
 * the toggle and on click; closes on Escape, outside click, or leaving the item.
 * GSAP reveals the panel and staggers its columns (transform/opacity only).
 * Drawer (<= 1080px): the toggle expands the panel in place as an accordion.
 * Works without GSAP (plain show/hide) and respects reduced motion.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var item = document.querySelector('[data-ha-mega]');
    if (!item) { return; }
    var btn = item.querySelector('[data-ha-mega-toggle]');
    var panel = item.querySelector('[data-ha-mega-panel]');
    var cols = [].slice.call(panel.querySelectorAll('[data-mega-col]'));
    var gsap = window.gsap;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var desktop = function () { return window.innerWidth > 1080; };
    var open = false, timer = null, tl = null;

    function show() {
        if (open) { return; }
        open = true;
        item.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        if (gsap && !reduce) {
            if (tl) { tl.kill(); }
            tl = gsap.timeline();
            if (desktop()) {
                tl.fromTo(panel, { autoAlpha: 0, y: -10 }, { autoAlpha: 1, y: 0, duration: 0.32, ease: 'power3.out' })
                  .fromTo(cols, { autoAlpha: 0, y: 14 }, { autoAlpha: 1, y: 0, duration: 0.42, stagger: 0.05, ease: 'power3.out' }, 0.06);
            } else {
                tl.fromTo(cols, { autoAlpha: 0, y: 8 }, { autoAlpha: 1, y: 0, duration: 0.3, stagger: 0.04, ease: 'power2.out' });
            }
        }
    }
    function hide(now) {
        if (!open) { return; }
        open = false;
        btn.setAttribute('aria-expanded', 'false');
        if (gsap && !reduce && !now && desktop()) {
            if (tl) { tl.kill(); }
            tl = gsap.to(panel, { autoAlpha: 0, y: -6, duration: 0.2, ease: 'power2.in', onComplete: function () { item.classList.remove('is-open'); gsap.set(panel, { clearProps: 'all' }); gsap.set(cols, { clearProps: 'all' }); } });
        } else {
            item.classList.remove('is-open');
            if (gsap) { gsap.set(panel, { clearProps: 'all' }); gsap.set(cols, { clearProps: 'all' }); }
        }
    }

    btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); open ? hide() : show(); });
    // Hover intent on desktop only: a brief delay stops the panel flashing as the pointer crosses the bar.
    item.addEventListener('pointerenter', function (e) {
        if (e.pointerType !== 'mouse' || !desktop()) { return; }
        clearTimeout(timer); timer = setTimeout(show, 120);
    });
    item.addEventListener('pointerleave', function (e) {
        if (e.pointerType !== 'mouse' || !desktop()) { return; }
        clearTimeout(timer); timer = setTimeout(function () { hide(); }, 180);
    });
    document.addEventListener('click', function (e) { if (open && desktop() && !item.contains(e.target)) { hide(); } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && open) { hide(true); btn.focus(); }
    });
    // Tabbing out of the panel closes it, so focus never sits behind a closed menu.
    item.addEventListener('focusout', function (e) {
        if (desktop() && open && e.relatedTarget && !item.contains(e.relatedTarget)) { hide(); }
    });
    window.addEventListener('resize', function () { hide(true); });
});
