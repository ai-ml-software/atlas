/*
 * Altus Gulf corporate profile: page-turning book (GSAP).
 *
 * Desktop: a two-page spread. Leaf k holds pages 2k+1 (front) and 2k+2 (back)
 * and turns about the spine with a 3D rotateY; `pos` is the number of leaves
 * already turned, so the open spread is page 2*pos (left) and 2*pos+1 (right).
 * The closed book is centred on its cover and the finished book on its back.
 * Arabic (data-dir="rtl") binds on the right and turns the other way.
 *
 * Phone (<= 760px): one page at a time on a swipe track.
 *
 * Only transform and opacity are animated. Reduced motion jumps instead of turning.
 */
// Starts on DOMContentLoaded, which fires after every deferred script, so the layout's GSAP is loaded by then.
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var root = document.querySelector('[data-pb]');
    if (!root || !window.gsap) { return; }
    var gsap = window.gsap;
    var rtl = root.getAttribute('data-dir') === 'rtl';
    var total = parseInt(root.getAttribute('data-total'), 10);
    var book = root.querySelector('[data-pb-book]');
    var stage = root.querySelector('[data-pb-stage]');
    var leaves = [].slice.call(root.querySelectorAll('[data-pb-leaf]'));
    var now = root.querySelector('[data-pb-now]');
    var thumbs = [].slice.call(root.querySelectorAll('[data-pb-go]'));
    var prevBtn = root.querySelector('[data-pb-prev]');
    var nextBtn = root.querySelector('[data-pb-next]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var DUR = reduce ? 0 : 0.95;
    var sign = rtl ? 1 : -1;                    // LTR turns to -180deg, RTL to +180deg
    var pos = 0, page = 1, busy = false, mobile = null, track = null;
    // With an odd page count the last leaf's back is blank: the book ends on the last spread, not one turn later.
    var maxPos = total % 2 ? leaves.length - 1 : leaves.length;

    root.classList.add('pb--js');

    // ------------------------------------------------------------ desktop book
    function z(k, turned) { return turned ? k + 1 : leaves.length - k; }
    function shift(p) {
        // Centre the closed cover / finished back cover; an open spread sits centred as is.
        var x = p === 0 ? -25 : (p === leaves.length ? 25 : 0);
        return rtl ? -x : x;
    }
    function layoutBook() {
        leaves.forEach(function (leaf, k) {
            var turned = k < pos;
            gsap.set(leaf, { rotationY: turned ? sign * 180 : 0, zIndex: z(k, turned) });
        });
        gsap.set(book, { xPercent: shift(pos) });
    }
    function turn(to) {
        to = Math.max(0, Math.min(maxPos, to));
        if (to === pos || busy) { return; }
        busy = true;
        var forward = to > pos;
        var order = [];
        for (var k = forward ? pos : pos - 1; forward ? k < to : k >= to; k += forward ? 1 : -1) { order.push(k); }
        var tl = gsap.timeline({ onComplete: function () { busy = false; layoutBook(); } });
        tl.to(book, { xPercent: shift(to), duration: DUR, ease: 'power3.inOut' }, 0);
        order.forEach(function (k, i) {
            var leaf = leaves[k];
            var at = i * (reduce ? 0 : 0.12);
            var shades = leaf.querySelectorAll('.pb-shade');
            tl.set(leaf, { zIndex: 200 + i }, at)
              .to(leaf, { rotationY: forward ? sign * 180 : 0, duration: DUR, ease: 'power2.inOut' }, at)
              .fromTo(shades, { opacity: 0 }, { opacity: 0.45, duration: DUR / 2, ease: 'sine.in', yoyo: true, repeat: 1 }, at);
        });
        pos = to;
        page = Math.min(total, pos === 0 ? 1 : 2 * pos + (pos === leaves.length ? 0 : 1));
        sync();
    }

    // ------------------------------------------------------------ phone track
    function buildTrack() {
        if (track) { return; }
        track = document.createElement('div');
        track.className = 'pb__track';
        leaves.forEach(function (leaf) {
            [].forEach.call(leaf.querySelectorAll('.pb-page:not(.pb-page--blank)'), function (pg) {
                var slot = document.createElement('div');
                slot.className = 'pb__slot';
                slot.appendChild(pg.cloneNode(true));
                track.appendChild(slot);
            });
        });
        stage.appendChild(track);
    }
    function slideTo(n) {
        n = Math.max(1, Math.min(total, n));
        page = n;
        gsap.to(track, { xPercent: (rtl ? 1 : -1) * (n - 1) * 100 / total, duration: reduce ? 0 : 0.55, ease: 'power3.out' });
        sync();
    }

    // ------------------------------------------------------------ shared
    function sync() {
        if (now) { now.textContent = page; }
        thumbs.forEach(function (b) {
            var n = parseInt(b.getAttribute('data-pb-go'), 10);
            var on = mobile ? n === page : (n === page || n === page - 1 && pos > 0 && pos < leaves.length);
            b.classList.toggle('is-on', on);
            if (n === page) { b.setAttribute('aria-current', 'page'); } else { b.removeAttribute('aria-current'); }
        });
        prevBtn.disabled = page <= 1;
        nextBtn.disabled = mobile ? page >= total : pos >= maxPos;
    }
    function next() { mobile ? slideTo(page + 1) : turn(pos + 1); }
    function prev() { mobile ? slideTo(page - 1) : turn(pos - 1); }
    function go(n) { mobile ? slideTo(n) : turn(n === 1 ? 0 : Math.min(maxPos, Math.floor(n / 2))); }

    function mode() {
        var m = window.matchMedia('(max-width: 760px)').matches;
        if (m === mobile) { return; }
        mobile = m;
        root.classList.toggle('pb--single', m);
        if (m) { buildTrack(); gsap.set(track, { width: (total * 100) + '%' }); slideTo(page); }
        else { pos = page <= 1 ? 0 : Math.min(maxPos, Math.floor(page / 2)); layoutBook(); sync(); }
    }

    nextBtn.addEventListener('click', next);
    prevBtn.addEventListener('click', prev);
    thumbs.forEach(function (b) { b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-pb-go'), 10)); }); });
    // Click the right half to go forward, the left half to go back (mirrored in Arabic).
    stage.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) { return; }
        var r = stage.getBoundingClientRect();
        var right = e.clientX > r.left + r.width / 2;
        (right !== rtl) ? next() : prev();
    });
    root.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); rtl ? prev() : next(); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); rtl ? next() : prev(); }
        if (e.key === 'Home') { e.preventDefault(); go(1); }
        if (e.key === 'End') { e.preventDefault(); go(total); }
    });
    // Swipe (touch and pen): a horizontal drag of 40px turns a page.
    var sx = null, sy = null;
    stage.addEventListener('pointerdown', function (e) { sx = e.clientX; sy = e.clientY; });
    stage.addEventListener('pointerup', function (e) {
        if (sx === null) { return; }
        var dx = e.clientX - sx, dy = e.clientY - sy; sx = null;
        if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) { return; }
        ((dx < 0) !== rtl) ? next() : prev();
    });
    stage.setAttribute('tabindex', '0');
    stage.setAttribute('role', 'region');
    stage.setAttribute('aria-roledescription', 'book');
    // ------------------------------------------------------------ full screen
    // The Fullscreen API where the browser has it; iPhone Safari has none for pages,
    // so the book then fills the viewport in place (pb--full) instead.
    var fullBtn = root.querySelector('[data-pb-full]');
    var fullLabel = root.querySelector('[data-pb-full-label]');
    var native = !!(root.requestFullscreen || root.webkitRequestFullscreen);
    function isFull() { return !!(document.fullscreenElement || document.webkitFullscreenElement) || root.classList.contains('pb--full'); }
    function paintFull() {
        var on = isFull();
        root.classList.toggle('pb--fullscreen', on);
        fullBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        fullLabel.textContent = fullBtn.getAttribute(on ? 'data-label-off' : 'data-label-on');
        document.documentElement.classList.toggle('pb-locked', root.classList.contains('pb--full'));
        mobile = null; mode();                    // re-measure the book for the new box
    }
    if (fullBtn) {
        fullBtn.addEventListener('click', function () {
            if (isFull()) {
                if (document.fullscreenElement) { document.exitFullscreen(); }
                else if (document.webkitFullscreenElement) { document.webkitExitFullscreen(); }
                else { root.classList.remove('pb--full'); paintFull(); }
            } else if (native) {
                var req = (root.requestFullscreen || root.webkitRequestFullscreen).call(root);
                // A refused request (iframe, policy) falls back to the in-place view.
                if (req && req.catch) { req.catch(function () { root.classList.add('pb--full'); paintFull(); }); }
            } else {
                root.classList.add('pb--full'); paintFull();
            }
            stage.focus();
        });
        document.addEventListener('fullscreenchange', paintFull);
        document.addEventListener('webkitfullscreenchange', paintFull);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.classList.contains('pb--full')) { root.classList.remove('pb--full'); paintFull(); }
        });
    }

    window.addEventListener('resize', mode);
    mode();

    // Entrance: the closed book settles onto the table.
    if (!reduce) { gsap.from(book, { y: 40, rotationX: 8, opacity: 0, duration: 1.1, ease: 'power3.out' }); }
});
