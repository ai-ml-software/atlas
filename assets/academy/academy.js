/*
 * Hospitality Academy public site behaviour.
 *
 * Deliberately small: every page works with JavaScript off. The navigation is
 * a real list, the search is a real GET form, and the header is a real header.
 * This adds the disclosure behaviour and the one authored motion moment.
 */
(function () {
    'use strict';

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ------------------------------------------------------------ masthead
    // The chrome condenses once the page has scrolled past the rail. The
    // threshold is the rail's own height, so the transition happens exactly
    // when the rail would have left the viewport anyway; hysteresis keeps it
    // from flickering for anyone parked on the boundary.
    var chrome = document.querySelector('[data-ha-chrome]');
    if (chrome) {
        var ENTER = 56, LEAVE = 24, stuck = false, ticking = false;
        var sync = function () {
            var y = window.pageYOffset || document.documentElement.scrollTop;
            if (!stuck && y > ENTER) {
                stuck = true;
                chrome.classList.add('ha-chrome--stuck');
            } else if (stuck && y < LEAVE) {
                stuck = false;
                chrome.classList.remove('ha-chrome--stuck');
            }
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(sync);
            }
        }, { passive: true });
        sync();
    }

    // ------------------------------------------------------------ mobile nav
    var navToggle = document.querySelector('[data-ha-nav-toggle]');
    var nav = document.getElementById('ha-nav');
    var scrim = document.querySelector('[data-ha-scrim]');

    function setNav(open) {
        if (!nav || !navToggle) { return; }
        nav.classList.toggle('is-open', open);
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (scrim) {
            scrim.hidden = false;
            scrim.classList.toggle('is-open', open);
        }
        // The page behind a full-height panel must not scroll under it.
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            var first = nav.querySelector('a');
            if (first && !reduced) { first.focus({ preventScroll: true }); }
        }
    }

    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            setNav(!nav.classList.contains('is-open'));
        });
        if (scrim) {
            scrim.addEventListener('click', function () { setNav(false); navToggle.focus(); });
        }
        // The panel carries its own close control. It is a separate hook from
        // the burger: binding both to one selector and reading only the first
        // match is how the close button ended up doing nothing.
        document.querySelectorAll('[data-ha-nav-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { setNav(false); navToggle.focus(); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                setNav(false);
                navToggle.focus();
            }
        });
        // A panel that stays open when the layout stops being a panel traps
        // the visitor behind an invisible scrim.
        window.matchMedia('(min-width: 1081px)').addEventListener('change', function (e) {
            if (e.matches) { setNav(false); }
        });
    }

    // ------------------------------------------------------- priority nav
    // The menu is administrator-managed, so its length is not knowable at
    // build time: eleven items overflow a 1160px row, eight would not, and
    // fifteen would overflow badly. Rather than tune the type size to today's
    // menu, measure the row and move whatever does not fit into a disclosure.
    // Without this script the list simply wraps and every link stays visible.
    var moreHost = document.querySelector('[data-ha-more]');
    var navList = moreHost && moreHost.parentElement;

    if (moreHost && navList && nav) {
        var moreBtn = moreHost.querySelector('.ha-more__btn');
        var morePanel = moreHost.querySelector('.ha-more__panel');
        var items = [].slice.call(navList.children).filter(function (li) {
            return li !== moreHost;
        });

        // Synchronously, before fonts resolve: hold the row to one line from
        // the first paint so the later measurement cannot cause a reflow.
        if (window.innerWidth > 1080) {
            nav.classList.add('is-measured', 'is-measuring');
        }

        var layout = function () {
            if (window.innerWidth <= 1080) {
                nav.classList.remove('is-measured', 'is-measuring');
                moreHost.classList.remove('is-shown');
                items.forEach(function (li) { navList.insertBefore(li, moreHost); });
                morePanel.innerHTML = '';
                return;
            }
            // Start from everything inline, then measure honestly.
            items.forEach(function (li) { navList.insertBefore(li, moreHost); });
            morePanel.innerHTML = '';
            moreHost.classList.remove('is-shown');
            nav.classList.add('is-measured');

            var avail = navList.getBoundingClientRect().width;
            var gap = parseFloat(getComputedStyle(navList).columnGap) || 0;
            var used = 0, overflow = [];

            items.forEach(function (li) {
                used += li.getBoundingClientRect().width + gap;
                if (used > avail - 90) { overflow.push(li); }   // 90px reserves the More control
            });

            if (!overflow.length) {
                nav.classList.remove('is-measuring');
                return;
            }
            overflow.forEach(function (li) {
                var clone = li.cloneNode(true);
                morePanel.appendChild(clone);
                li.remove();
            });
            moreHost.classList.add('is-shown');
            nav.classList.remove('is-measuring');
        };

        var closeMore = function () {
            morePanel.classList.remove('is-open');
            moreBtn.setAttribute('aria-expanded', 'false');
        };
        moreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !morePanel.classList.contains('is-open');
            morePanel.classList.toggle('is-open', open);
            moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!moreHost.contains(e.target)) { closeMore(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && morePanel.classList.contains('is-open')) {
                closeMore();
                moreBtn.focus();
            }
        });

        // Webfonts change the measurement, so wait for them before deciding.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(layout);
        } else {
            layout();
        }
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(layout, 150);
        });
    }

    // ------------------------------------------------------------ search
    // Closed, the field is zero-width and out of the tab order; open, it is a
    // normal input. Submitting while closed still works, because it is the
    // same form either way.
    var searchForm = document.querySelector('[data-ha-search]');
    if (searchForm) {
        var toggle = searchForm.querySelector('[data-ha-search-toggle]');
        var field = searchForm.querySelector('input[type="search"]');

        var setSearch = function (open) {
            searchForm.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            field.setAttribute('tabindex', open ? '0' : '-1');
            if (open) { field.focus(); }
        };

        // A field arriving with a term already in it is the result of a search,
        // so it opens showing what was asked.
        if (field.value.trim() !== '') { setSearch(true); }

        toggle.addEventListener('click', function () {
            var open = !searchForm.classList.contains('is-open');
            if (!open && field.value.trim() !== '') {
                searchForm.submit();
                return;
            }
            setSearch(open);
        });
        field.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { setSearch(false); toggle.focus(); }
        });
        document.addEventListener('click', function (event) {
            if (!searchForm.contains(event.target) && field.value.trim() === '') {
                setSearch(false);
            }
        });
    }

    // Submit a filter form as soon as a select changes, which is what a
    // visitor expects, while the explicit submit button still works without JS.
    document.querySelectorAll('[data-ha-autosubmit]').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.submit();
            });
        });
    });
})();
