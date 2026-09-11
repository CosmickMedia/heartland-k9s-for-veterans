/**
 * Heartland Canines for Veterans — frontend behaviour (vanilla, no jQuery).
 *
 * 1. Mobile menu: toggle + aria-expanded, Escape closes and returns focus,
 *    click outside closes, link click closes, resize past the desktop
 *    breakpoint closes. No scroll lock (matches the reference).
 * 2. Admin-bar-aware sticky offset (--hk9-sticky-top).
 * 3. Hero entrance animation is CSS-only and already gated by
 *    prefers-reduced-motion; JS only marks the document as "js" so that
 *    scripted enhancements can be styled.
 * 4. <details> accordion enhancement (single-open groups).
 * 5. External link rel safety (target=_blank → rel="noopener noreferrer").
 */

const config = Object.assign(
	{ navBreakpoint: 1024, adminBar: false, i18n: { openMenu: 'Open menu', closeMenu: 'Close menu' } },
	(window.HK9 && window.HK9.config) || {}
);

const prefersReducedMotion = () =>
	window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------------------------------------------------------------- */
/* Mobile menu                                                             */
/* ---------------------------------------------------------------------- */

function initMobileMenu() {
	const toggle = document.querySelector('[data-hk9-toggle]');
	const panel = document.querySelector('[data-hk9-mobile]');
	if (!toggle || !panel) {
		return;
	}

	const label = toggle.querySelector('[data-hk9-toggle-label]');
	const header = toggle.closest('.hk9-header') || document.body;
	let open = false;

	const setOpen = (next, { returnFocus = false } = {}) => {
		if (next === open) {
			return;
		}
		open = next;
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (label) {
			label.textContent = open ? config.i18n.closeMenu : config.i18n.openMenu;
		}
		if (open) {
			panel.hidden = false;
			panel.classList.add('is-open');
		} else {
			panel.classList.remove('is-open');
			panel.hidden = true;
			if (returnFocus) {
				toggle.focus();
			}
		}
	};

	toggle.addEventListener('click', (event) => {
		event.preventDefault();
		setOpen(!open);
	});

	// Close on link click (same-page anchors included).
	panel.addEventListener('click', (event) => {
		const link = event.target.closest('a[href]');
		if (link) {
			setOpen(false);
		}
	});

	// Escape closes and returns focus to the toggle.
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && open) {
			event.preventDefault();
			setOpen(false, { returnFocus: true });
		}
	});

	// Click outside the header closes.
	document.addEventListener('click', (event) => {
		if (open && !header.contains(event.target)) {
			setOpen(false);
		}
	});

	// Leaving the panel by keyboard (Tab past the last item) closes it.
	panel.addEventListener('focusout', (event) => {
		if (open && event.relatedTarget && !header.contains(event.relatedTarget)) {
			setOpen(false);
		}
	});

	// Desktop breakpoint: reset state.
	const mq = window.matchMedia(`(min-width: ${config.navBreakpoint}px)`);
	const onChange = () => {
		if (mq.matches) {
			setOpen(false);
		}
	};
	if (typeof mq.addEventListener === 'function') {
		mq.addEventListener('change', onChange);
	} else if (typeof mq.addListener === 'function') {
		mq.addListener(onChange);
	}
}

/* ---------------------------------------------------------------------- */
/* Sticky header offset for the WP admin bar                               */
/* ---------------------------------------------------------------------- */

function initStickyOffset() {
	const root = document.documentElement;
	const bar = document.getElementById('wpadminbar');

	const update = () => {
		let offset = 0;
		if (bar) {
			const style = window.getComputedStyle(bar);
			// The admin bar is position:fixed above 600px and scrolls away below it.
			if (style.position === 'fixed') {
				offset = bar.offsetHeight;
			}
		}
		root.style.setProperty('--hk9-sticky-top', `${offset}px`);
	};

	update();
	if (bar) {
		window.addEventListener('resize', update, { passive: true });
	}
}

/* ---------------------------------------------------------------------- */
/* Accordion enhancement                                                   */
/* ---------------------------------------------------------------------- */

function initAccordions() {
	document.querySelectorAll('[data-hk9-accordion="single"]').forEach((group) => {
		group.addEventListener('toggle', (event) => {
			const details = event.target;
			if (!(details instanceof HTMLDetailsElement) || !details.open) {
				return;
			}
			group.querySelectorAll('details[open]').forEach((other) => {
				if (other !== details) {
					other.open = false;
				}
			});
		}, true);
	});
}

/* ---------------------------------------------------------------------- */
/* External link safety                                                    */
/* ---------------------------------------------------------------------- */

function initExternalLinks() {
	document.querySelectorAll('a[target="_blank"]').forEach((link) => {
		const rel = (link.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
		let changed = false;
		['noopener', 'noreferrer'].forEach((token) => {
			if (!rel.includes(token)) {
				rel.push(token);
				changed = true;
			}
		});
		if (changed) {
			link.setAttribute('rel', rel.join(' '));
		}
	});
}

/* ---------------------------------------------------------------------- */
/* Boot                                                                    */
/* ---------------------------------------------------------------------- */

function boot() {
	document.documentElement.classList.add('hk9-js');
	if (prefersReducedMotion()) {
		document.documentElement.classList.add('hk9-reduced-motion');
	}
	initStickyOffset();
	initMobileMenu();
	initAccordions();
	initExternalLinks();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot);
} else {
	boot();
}

window.HK9 = window.HK9 || {};
window.HK9.version = '1.0.0';
