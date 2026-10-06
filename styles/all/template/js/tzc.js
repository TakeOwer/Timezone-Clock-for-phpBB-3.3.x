/**
 * Timezone Clock - world clock bar for phpBB
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * Times are computed from the transitions sent by the server (IANA tz
 * database kept up to date by the extension), so they are correct even
 * when the visitor's browser or operating system has an old tz database.
 */
(function (window, document) {
	'use strict';

	var TZC = window.TZC || {};
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var ICONS = {
		sun: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></g></svg>',
		moon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1z"/><circle cx="17" cy="6" r="1" fill="currentColor"/><circle cx="20" cy="9.5" r=".7" fill="currentColor"/></svg>',
		dawn: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.5 17a5.5 5.5 0 0 1 11 0z"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 20h20M12 6v2.5M4.6 10.6l1.7 1.7M19.4 10.6l-1.7 1.7"/></g></svg>',
		prev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>',
		next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>',
		pause: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>',
		play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>',
		gear: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19.4 13a7.5 7.5 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 0 0-1.7-1L15 3.3h-4l-.4 2.6a7.4 7.4 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11a7.5 7.5 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1c.5.4 1.1.7 1.7 1l.4 2.6h4l.4-2.6c.6-.3 1.2-.6 1.7-1l2.5 1 2-3.5zM13 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z" transform="translate(-1 0)"/></svg>',
		up: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>',
		down: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 9l7 7 7-7"/></svg>',
		search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/><path stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M15.5 15.5L20.5 20.5"/></svg>',
		close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>',
		chev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>',
		globe: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" d="M3 12h18M12 3c2.6 2.6 3.9 5.6 3.9 9s-1.3 6.4-3.9 9c-2.6-2.6-3.9-5.6-3.9-9S9.4 5.6 12 3z"/></svg>'
	};

	/* ------------------------------------------------------------------
	 * Time helpers
	 * ------------------------------------------------------------------ */

	function entryAt(t, ms) {
		for (var i = 0; i < t.length; i++) {
			if (t[i][0] === null || ms < t[i][0]) {
				return t[i];
			}
		}
		return t.length ? t[t.length - 1] : [null, 0, 'UTC', false];
	}

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function dayNumber(shiftedMs) {
		return Math.floor(shiftedMs / 86400000);
	}

	function fmtOffset(min) {
		var sign = min < 0 ? '\u2212' : '+';
		var a = Math.abs(min);
		return 'UTC' + sign + Math.floor(a / 60) + (a % 60 ? ':' + pad(a % 60) : '');
	}

	function fmtDiff(min, i18n) {
		if (min === 0) {
			return i18n.same;
		}
		var a = Math.abs(min);
		return (min > 0 ? '+' : '\u2212') + Math.floor(a / 60) + (a % 60 ? ':' + pad(a % 60) : '') + ' ' + i18n.hours;
	}

	var dateFormatters = {};
	function formatDate(locale, shiftedMs) {
		try {
			if (!dateFormatters[locale]) {
				dateFormatters[locale] = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' });
			}
			return dateFormatters[locale].format(new Date(shiftedMs));
		} catch (e) {
			var d = new Date(shiftedMs);
			return d.getUTCDate() + '/' + (d.getUTCMonth() + 1);
		}
	}

	// Solar elevation in degrees (good to a few tenths of a degree)
	function sunElevation(lat, lon, ms) {
		var rad = Math.PI / 180;
		var d = ms / 86400000 - 10957.5;
		var g = (357.529 + 0.98560028 * d) * rad;
		var q = 280.459 + 0.98564736 * d;
		var L = (q + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * rad;
		var e = (23.439 - 0.00000036 * d) * rad;
		var ra = Math.atan2(Math.cos(e) * Math.sin(L), Math.cos(L));
		var dec = Math.asin(Math.sin(e) * Math.sin(L));
		var gmst = (18.697374558 + 24.06570982441908 * d) % 24;
		var ha = (gmst * 15 + lon) * rad - ra;
		var la = lat * rad;
		return Math.asin(Math.sin(la) * Math.sin(dec) + Math.cos(la) * Math.cos(dec) * Math.cos(ha)) / rad;
	}

	function phaseOf(city, ms, hour) {
		if (typeof city.lat === 'number' && typeof city.lon === 'number') {
			var e = sunElevation(city.lat, city.lon, ms);
			if (e > 6) {
				return 'day';
			}
			if (e > -6) {
				return hour < 12 ? 'dawn' : 'dusk';
			}
			return 'night';
		}
		if (hour >= 8 && hour < 18) {
			return 'day';
		}
		if (hour === 6 || hour === 7) {
			return 'dawn';
		}
		if (hour === 18 || hour === 19) {
			return 'dusk';
		}
		return 'night';
	}

	/* ------------------------------------------------------------------
	 * DOM helpers
	 * ------------------------------------------------------------------ */

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text !== undefined && text !== null) {
			n.textContent = text;
		}
		return n;
	}

	function button(cls, icon, label) {
		var b = el('button', 'tzc-btn ' + cls);
		b.type = 'button';
		b.innerHTML = ICONS[icon];
		b.setAttribute('aria-label', label);
		b.title = label;
		return b;
	}

	function setText(node, text) {
		if (node && node.textContent !== text) {
			node.textContent = text;
		}
	}

	function storage(key, value) {
		try {
			if (value === undefined) {
				return window.localStorage.getItem(key);
			}
			window.localStorage.setItem(key, value);
		} catch (e) {
			return null;
		}
		return null;
	}

	function flagEmoji(cc) {
		try {
			return String.fromCodePoint(127397 + cc.charCodeAt(0), 127397 + cc.charCodeAt(1));
		} catch (e) {
			return cc;
		}
	}

	function analogSvg() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 40 40');
		svg.setAttribute('class', 'tzc-analog');
		svg.setAttribute('aria-hidden', 'true');
		var html = '<circle cx="20" cy="20" r="18.5" class="tzc-a-face"/>';
		for (var i = 0; i < 12; i++) {
			html += '<line x1="20" y1="3.5" x2="20" y2="' + (i % 3 ? 5.5 : 7) + '" class="tzc-a-tick" transform="rotate(' + (i * 30) + ' 20 20)"/>';
		}
		html += '<line x1="20" y1="20" x2="20" y2="10.5" class="tzc-a-h"/>' +
			'<line x1="20" y1="20" x2="20" y2="6" class="tzc-a-m"/>' +
			'<line x1="20" y1="23" x2="20" y2="5" class="tzc-a-s"/>' +
			'<circle cx="20" cy="20" r="1.6" class="tzc-a-c"/>';
		svg.innerHTML = html;
		return svg;
	}

	/* ------------------------------------------------------------------
	 * Bar
	 * ------------------------------------------------------------------ */

	function Bar(root, data) {
		this.root = root;
		this.data = data;
		this.o = data.opt;
		this.i18n = data.i18n || {};
		this.locale = data.locale || document.documentElement.lang || 'en';
		this.refs = [];
		this.timers = [];
		this.listeners = [];
		this.paused = reduceMotion;
		this.hover = false;
		this.focusIn = false;
		this.drag = null;
		this.offset = 0;
		this.speed = 0;
		this.collapsed = this.o.collapsible && storage('tzc-collapsed') === '1';
		this.lastMinute = -1;

		this.build();
		this.tick(true);
		this.schedule();
		this.motion();
		root.classList.add('tzc-ready');
	}

	Bar.prototype.on = function (target, type, fn, opts) {
		target.addEventListener(type, fn, opts || false);
		this.listeners.push([target, type, fn, opts || false]);
	};

	Bar.prototype.destroy = function () {
		var i;
		clearTimeout(this.tickTimer);
		for (i = 0; i < this.timers.length; i++) {
			clearTimeout(this.timers[i]);
			clearInterval(this.timers[i]);
		}
		for (i = 0; i < this.listeners.length; i++) {
			this.listeners[i][0].removeEventListener(this.listeners[i][1], this.listeners[i][2], this.listeners[i][3]);
		}
		if (this.raf) {
			window.cancelAnimationFrame(this.raf);
		}
		this.destroyed = true;
		this.root.innerHTML = '';
		this.root.classList.remove('tzc-ready');
	};

	Bar.prototype.build = function () {
		var o = this.o, i18n = this.i18n, self = this;
		var bar = el('div', 'tzc-bar tzc-mode-' + o.mode + ' tzc-theme-' + o.theme + ' tzc-density-' + o.density +
			(o.daynight_bg ? ' tzc-dnbg' : '') + (this.collapsed ? ' tzc-collapsed' : '') + (o.mobile ? '' : ' tzc-hide-mobile'));
		bar.setAttribute('role', 'region');
		bar.setAttribute('aria-label', i18n.title);
		bar.style.setProperty('--tzc-accent', o.accent);
		bar.style.setProperty('--tzc-cw', (o.card_width + (o.analog && o.density !== 'compact' ? 56 : 0)) + 'px');
		bar.style.setProperty('--tzc-radius', o.radius + 'px');

		// Tools
		var tools = el('div', 'tzc-tools');
		var count = (this.data.cities || []).length;
		if (o.search === 'always' || (o.search !== 'never' && count >= (o.search_min || 8))) {
			this.btnSearch = button('tzc-search', 'search', i18n.find);
			this.btnSearch.setAttribute('aria-haspopup', 'dialog');
			this.btnSearch.setAttribute('aria-expanded', 'false');
			this.on(this.btnSearch, 'click', function (e) {
				e.stopPropagation();
				self.toggleFinder();
			});
			tools.appendChild(this.btnSearch);
		}
		if (o.mode !== 'grid' && o.autoplay) {
			this.btnPause = button('tzc-pause', this.paused ? 'play' : 'pause', this.paused ? i18n.play : i18n.pause);
			this.btnPause.setAttribute('aria-pressed', this.paused ? 'true' : 'false');
			this.on(this.btnPause, 'click', function () {
				self.setPaused(!self.paused);
			});
			tools.appendChild(this.btnPause);
		}
		if (this.data.ucp) {
			var link = el('a', 'tzc-btn tzc-settings');
			link.href = this.data.ucp;
			link.innerHTML = ICONS.gear;
			link.title = i18n.settings;
			link.setAttribute('aria-label', i18n.settings);
			tools.appendChild(link);
		}
		if (o.collapsible) {
			this.btnCollapse = button('tzc-collapse', this.collapsed ? 'down' : 'up', this.collapsed ? i18n.expand : i18n.collapse);
			this.btnCollapse.setAttribute('aria-expanded', this.collapsed ? 'false' : 'true');
			this.on(this.btnCollapse, 'click', function () {
				self.setCollapsed(!self.collapsed);
			});
			tools.appendChild(this.btnCollapse);
		}

		// Collapsed summary line
		this.mini = el('div', 'tzc-mini');
		var miniIcon = el('span', 'tzc-mini-icon');
		miniIcon.innerHTML = ICONS.globe;
		this.miniText = el('span', 'tzc-mini-text');
		this.mini.appendChild(miniIcon);
		this.mini.appendChild(this.miniText);
		this.on(this.mini, 'click', function () {
			self.setCollapsed(false);
		});

		// Cards
		this.viewport = el('div', 'tzc-viewport');
		this.viewport.setAttribute('tabindex', '0');
		this.viewport.setAttribute('aria-label', i18n.cities);
		this.track = el('div', 'tzc-track');
		this.viewport.appendChild(this.track);

		var cities = this.data.cities || [];
		var copies = (o.mode === 'ticker') ? 2 : 1;
		for (var c = 0; c < copies; c++) {
			for (var i = 0; i < cities.length; i++) {
				var card = this.card(cities[i], c > 0);
				this.track.appendChild(card);
			}
		}

		bar.appendChild(this.mini);
		if (o.mode === 'carousel' && o.arrows) {
			this.btnPrev = button('tzc-nav tzc-prev', 'prev', i18n.prev);
			this.btnNext = button('tzc-nav tzc-next', 'next', i18n.next);
			bar.appendChild(this.btnPrev);
		}
		bar.appendChild(this.viewport);
		if (this.btnNext) {
			bar.appendChild(this.btnNext);
		}
		if (o.mode === 'carousel' && o.dots) {
			this.dots = el('div', 'tzc-dots');
			bar.appendChild(this.dots);
		}
		bar.appendChild(tools);
		if (this.btnSearch) {
			bar.appendChild(this.buildFinder());
			bar.classList.add('tzc-has-search');
		}

		this.bar = bar;
		this.root.innerHTML = '';
		this.root.appendChild(bar);
	};

	Bar.prototype.card = function (city, clone) {
		var o = this.o, i18n = this.i18n;
		var card = el('div', 'tzc-card' + (city.home && o.highlight_home ? ' tzc-home' : '') + (city.m === 0 ? ' tzc-nomobile' : ''));
		var ref = { city: city, card: card, clone: clone, lastDay: null, lastPhase: null };

		if (clone) {
			card.setAttribute('aria-hidden', 'true');
		} else {
			card.setAttribute('role', 'group');
			card.setAttribute('aria-label', city.n + (city.cn ? ', ' + city.cn : ''));
		}

		if (o.analog) {
			var svg = analogSvg();
			ref.hHand = svg.querySelector('.tzc-a-h');
			ref.mHand = svg.querySelector('.tzc-a-m');
			ref.sHand = svg.querySelector('.tzc-a-s');
			if (!o.seconds) {
				ref.sHand.style.display = 'none';
			}
			card.appendChild(svg);
		}

		var body = el('div', 'tzc-body');
		var head = el('div', 'tzc-head');

		if (o.show_flag && city.cc) {
			if (o.flag_style === 'emoji') {
				head.appendChild(el('span', 'tzc-flag tzc-flag-emoji', flagEmoji(city.cc)));
			} else {
				var img = el('img', 'tzc-flag');
				img.src = this.data.flags + city.cc + '.svg';
				img.alt = '';
				img.loading = 'lazy';
				img.width = 18;
				img.height = 12;
				img.onerror = function () {
					this.style.display = 'none';
				};
				head.appendChild(img);
			}
		}
		head.appendChild(el('span', 'tzc-city', city.n));
		body.appendChild(head);

		if (city.home && o.highlight_home) {
			body.appendChild(el('span', 'tzc-home-badge', i18n.home));
		} else if (o.show_country && city.cn) {
			body.appendChild(el('div', 'tzc-country', city.cn));
		}

		var time = el('div', 'tzc-time');
		ref.hm = el('span', 'tzc-hm');
		time.appendChild(ref.hm);
		if (o.seconds) {
			ref.s = el('span', 'tzc-s');
			time.appendChild(ref.s);
		}
		if (o.format === '12') {
			ref.ampm = el('span', 'tzc-ampm');
			time.appendChild(ref.ampm);
		}
		body.appendChild(time);

		if (o.show_date) {
			var meta = el('div', 'tzc-meta');
			ref.date = el('span', 'tzc-date');
			ref.rel = el('span', 'tzc-rel');
			meta.appendChild(ref.date);
			meta.appendChild(ref.rel);
			body.appendChild(meta);
		}

		var badges = el('div', 'tzc-badges');
		if (o.show_diff && !city.home) {
			ref.diff = el('span', 'tzc-badge tzc-b-diff');
			badges.appendChild(ref.diff);
		}
		if (o.show_dst) {
			ref.dst = el('span', 'tzc-badge tzc-b-dst', i18n.dst);
			badges.appendChild(ref.dst);
		}
		if (o.show_abbr) {
			ref.abbr = el('span', 'tzc-badge tzc-b-abbr');
			badges.appendChild(ref.abbr);
		}
		if (o.show_utc) {
			ref.utc = el('span', 'tzc-badge tzc-b-utc');
			badges.appendChild(ref.utc);
		}
		if (badges.childNodes.length) {
			body.appendChild(badges);
		}
		card.appendChild(body);

		if (o.show_daynight) {
			ref.dn = el('span', 'tzc-dn');
			card.appendChild(ref.dn);
		}

		this.refs.push(ref);
		return card;
	};

	/* ------------------------------------------------------------------
	 * Clock update
	 * ------------------------------------------------------------------ */

	Bar.prototype.tick = function (force) {
		var o = this.o, i18n = this.i18n;
		var now = Date.now();
		var minute = Math.floor(now / 60000);
		var fullUpdate = force || minute !== this.lastMinute;
		this.lastMinute = minute;

		var homeOff = entryAt(this.data.home, now)[1];
		var homeDay = dayNumber(now + homeOff * 60000);
		var mini = [];

		for (var i = 0; i < this.refs.length; i++) {
			var r = this.refs[i];
			var e = entryAt(r.city.t, now);
			var off = e[1];
			var shifted = now + off * 60000;
			var d = new Date(shifted);
			var h = d.getUTCHours(), m = d.getUTCMinutes(), s = d.getUTCSeconds();

			var hm;
			if (o.format === '12') {
				hm = (h % 12 || 12) + ':' + pad(m);
				setText(r.ampm, h < 12 ? i18n.am : i18n.pm);
			} else {
				hm = pad(h) + ':' + pad(m);
			}
			setText(r.hm, hm);
			if (r.s) {
				setText(r.s, ':' + pad(s));
			}

			if (r.hHand) {
				r.hHand.setAttribute('transform', 'rotate(' + ((h % 12) * 30 + m * 0.5) + ' 20 20)');
				r.mHand.setAttribute('transform', 'rotate(' + (m * 6 + s * 0.1) + ' 20 20)');
				if (o.seconds) {
					r.sHand.setAttribute('transform', 'rotate(' + (s * 6) + ' 20 20)');
				}
			}

			if (!r.clone && mini.length < 5) {
				mini.push(r.city.n + ' ' + hm + (o.format === '12' ? (h < 12 ? i18n.am : i18n.pm) : ''));
			}

			if (!fullUpdate) {
				continue;
			}

			var day = dayNumber(shifted);
			if (r.date && r.lastDay !== day) {
				r.lastDay = day;
				setText(r.date, formatDate(this.locale, shifted));
			}
			if (r.rel) {
				var delta = day - homeDay;
				var rel = delta === 1 ? i18n.tomorrow : (delta === -1 ? i18n.yesterday : (delta === 0 ? '' : (delta > 0 ? '+' : '') + delta));
				setText(r.rel, rel);
				r.rel.style.display = rel ? '' : 'none';
			}
			if (r.diff) {
				setText(r.diff, fmtDiff(off - homeOff, i18n));
			}
			if (r.dst) {
				r.dst.style.display = e[3] ? '' : 'none';
			}
			if (r.abbr) {
				var abbr = /^[+\-]?\d/.test(e[2]) ? '' : e[2];
				setText(r.abbr, abbr);
				r.abbr.style.display = abbr ? '' : 'none';
			}
			if (r.utc) {
				setText(r.utc, fmtOffset(off));
			}

			var phase = phaseOf(r.city, now, h);
			if (phase !== r.lastPhase) {
				if (r.lastPhase) {
					r.card.classList.remove('tzc-ph-' + r.lastPhase);
				}
				r.card.classList.add('tzc-ph-' + phase);
				r.lastPhase = phase;
				if (r.dn) {
					r.dn.innerHTML = phase === 'day' ? ICONS.sun : (phase === 'night' ? ICONS.moon : ICONS.dawn);
					r.dn.title = i18n[phase] || '';
				}
			}
		}

		setText(this.miniText, mini.join('  \u00b7  '));
		if (this.finderOpen && fullUpdate) {
			this.finderTimes();
		}
	};

	Bar.prototype.schedule = function () {
		var self = this;
		var everySecond = this.o.seconds;
		var now = Date.now();
		var wait = everySecond ? 1000 - (now % 1000) + 5 : 60000 - (now % 60000) + 50;

		clearTimeout(this.tickTimer);
		this.tickTimer = setTimeout(function () {
			if (self.destroyed) {
				return;
			}
			self.tick(false);
			self.schedule();
		}, wait);
	};

	/* ------------------------------------------------------------------
	 * Motion: carousel / ticker
	 * ------------------------------------------------------------------ */

	Bar.prototype.canPlay = function () {
		return this.o.autoplay && !this.paused && !this.collapsed && !this.drag && !document.hidden && !this.finderOpen &&
			!(this.holdUntil && Date.now() < this.holdUntil) &&
			!(this.o.pause_hover && this.hover) && !this.focusIn && this.overflowing;
	};

	Bar.prototype.setPaused = function (paused) {
		this.paused = paused;
		if (this.btnPause) {
			this.btnPause.innerHTML = paused ? ICONS.play : ICONS.pause;
			this.btnPause.setAttribute('aria-label', paused ? this.i18n.play : this.i18n.pause);
			this.btnPause.title = paused ? this.i18n.play : this.i18n.pause;
			this.btnPause.setAttribute('aria-pressed', paused ? 'true' : 'false');
		}
	};

	Bar.prototype.setCollapsed = function (collapsed) {
		this.collapsed = collapsed;
		this.bar.classList.toggle('tzc-collapsed', collapsed);
		storage('tzc-collapsed', collapsed ? '1' : '0');
		if (this.btnCollapse) {
			this.btnCollapse.innerHTML = collapsed ? ICONS.down : ICONS.up;
			this.btnCollapse.setAttribute('aria-label', collapsed ? this.i18n.expand : this.i18n.collapse);
			this.btnCollapse.title = collapsed ? this.i18n.expand : this.i18n.collapse;
			this.btnCollapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		}
		if (!collapsed) {
			this.layout();
		}
	};

	Bar.prototype.motion = function () {
		var self = this, vp = this.viewport, o = this.o;

		this.on(this.bar, 'mouseenter', function () { self.hover = true; });
		this.on(this.bar, 'mouseleave', function () { self.hover = false; });
		this.on(this.bar, 'focusin', function () { self.focusIn = true; });
		this.on(this.bar, 'focusout', function () { self.focusIn = false; });
		this.on(document, 'visibilitychange', function () {
			if (!document.hidden) {
				self.tick(true);
			}
		});

		var resizeTimer;
		this.on(window, 'resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () { self.layout(); }, 150);
		});

		if (o.mode === 'carousel') {
			this.on(vp, 'scroll', function () { self.updateNav(); }, { passive: true });
			if (this.btnPrev) {
				this.on(this.btnPrev, 'click', function () { self.go(-1); });
				this.on(this.btnNext, 'click', function () { self.go(1); });
			}
			this.on(vp, 'keydown', function (e) {
				if (e.key === 'ArrowLeft') { self.go(-1); e.preventDefault(); }
				if (e.key === 'ArrowRight') { self.go(1); e.preventDefault(); }
			});
			this.mouseDrag();
			var id = setInterval(function () {
				if (self.canPlay()) {
					self.go(1);
				}
			}, Math.max(2, o.autoplay_delay) * 1000);
			this.timers.push(id);
		} else if (o.mode === 'ticker') {
			this.tickerDrag();
			var last = 0;
			var loop = function (ts) {
				if (self.destroyed) {
					return;
				}
				var dt = last ? Math.min(0.1, (ts - last) / 1000) : 0;
				last = ts;
				var target = self.canPlay() ? o.ticker_speed : 0;
				self.speed += (target - self.speed) * Math.min(1, dt * 4);
				if (self.overflowing && !self.drag) {
					self.offset -= self.speed * dt;
					self.applyTicker();
				}
				self.raf = window.requestAnimationFrame(loop);
			};
			this.raf = window.requestAnimationFrame(loop);
		}

		// fonts and flags change the widths: measure again once loaded
		this.layout();
		var t = setTimeout(function () { self.layout(); }, 600);
		this.timers.push(t);
	};

	Bar.prototype.layout = function () {
		if (this.destroyed) {
			return;
		}
		var vp = this.viewport, o = this.o;

		if (o.mode === 'ticker') {
			this.half = this.track.scrollWidth / 2;
			this.overflowing = this.half > vp.clientWidth + 2;
			this.bar.classList.toggle('tzc-overflow', this.overflowing);
			if (!this.overflowing) {
				this.offset = 0;
				this.track.style.transform = '';
			}
		} else if (o.mode === 'carousel') {
			this.overflowing = vp.scrollWidth > vp.clientWidth + 2;
			this.bar.classList.toggle('tzc-overflow', this.overflowing);
			this.buildDots();
			this.updateNav();
		} else {
			this.overflowing = false;
		}
	};

	Bar.prototype.step = function () {
		var card = this.track.querySelector('.tzc-card');
		if (!card) {
			return 200;
		}
		var gap = parseFloat(window.getComputedStyle(this.track).columnGap || window.getComputedStyle(this.track).gap) || 0;
		return card.getBoundingClientRect().width + gap;
	};

	Bar.prototype.go = function (dir) {
		var vp = this.viewport;
		var max = vp.scrollWidth - vp.clientWidth;
		var smooth = reduceMotion ? 'auto' : 'smooth';
		if (dir > 0 && vp.scrollLeft >= max - 4) {
			vp.scrollTo({ left: 0, behavior: smooth });
		} else if (dir < 0 && vp.scrollLeft <= 4) {
			vp.scrollTo({ left: max, behavior: smooth });
		} else {
			vp.scrollBy({ left: dir * this.step(), behavior: smooth });
		}
	};

	Bar.prototype.pages = function () {
		var vp = this.viewport;
		if (!this.overflowing || !vp.clientWidth) {
			return 1;
		}
		return Math.max(1, Math.ceil((vp.scrollWidth - 2) / vp.clientWidth));
	};

	Bar.prototype.buildDots = function () {
		if (!this.dots) {
			return;
		}
		var self = this, n = this.pages();
		if (this.dots.childNodes.length === n) {
			return;
		}
		this.dots.innerHTML = '';
		for (var i = 0; i < n; i++) {
			(function (index) {
				var b = el('button', 'tzc-dot');
				b.type = 'button';
				b.setAttribute('aria-label', (self.i18n.page || '%d').replace('%d', index + 1));
				b.addEventListener('click', function () {
					var vp = self.viewport;
					var max = vp.scrollWidth - vp.clientWidth;
					vp.scrollTo({ left: n > 1 ? Math.round(max * index / (n - 1)) : 0, behavior: reduceMotion ? 'auto' : 'smooth' });
				});
				self.dots.appendChild(b);
			})(i);
		}
		this.dots.style.display = n > 1 ? '' : 'none';
	};

	Bar.prototype.updateNav = function () {
		var vp = this.viewport;
		var max = vp.scrollWidth - vp.clientWidth;
		if (this.dots && this.dots.childNodes.length > 1) {
			var n = this.dots.childNodes.length;
			var active = max > 0 ? Math.round(vp.scrollLeft / max * (n - 1)) : 0;
			for (var i = 0; i < n; i++) {
				this.dots.childNodes[i].classList.toggle('tzc-active', i === active);
			}
		}
		this.bar.classList.toggle('tzc-at-start', vp.scrollLeft <= 4);
		this.bar.classList.toggle('tzc-at-end', vp.scrollLeft >= max - 4);
	};

	Bar.prototype.mouseDrag = function () {
		var self = this, vp = this.viewport, moved = false;

		this.on(vp, 'pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) {
				return;
			}
			self.drag = { x: e.clientX, left: vp.scrollLeft };
			moved = false;
		});
		this.on(window, 'pointermove', function (e) {
			if (!self.drag) {
				return;
			}
			var dx = e.clientX - self.drag.x;
			if (!moved && Math.abs(dx) > 4) {
				moved = true;
				self.bar.classList.add('tzc-dragging');
			}
			if (moved) {
				vp.scrollLeft = self.drag.left - dx;
			}
		});
		this.on(window, 'pointerup', function () {
			if (!self.drag) {
				return;
			}
			self.drag = null;
			self.bar.classList.remove('tzc-dragging');
		});
		this.on(vp, 'click', function (e) {
			if (moved) {
				e.preventDefault();
				e.stopPropagation();
				moved = false;
			}
		}, true);
	};

	Bar.prototype.applyTicker = function () {
		if (!this.half) {
			return;
		}
		while (this.offset <= -this.half) {
			this.offset += this.half;
		}
		while (this.offset > 0) {
			this.offset -= this.half;
		}
		this.track.style.transform = 'translate3d(' + this.offset.toFixed(2) + 'px,0,0)';
	};

	Bar.prototype.tickerDrag = function () {
		var self = this, vp = this.viewport;

		this.on(vp, 'pointerdown', function (e) {
			if (!self.overflowing || (e.pointerType === 'mouse' && e.button !== 0)) {
				return;
			}
			self.drag = { x: e.clientX, start: self.offset };
			self.bar.classList.add('tzc-dragging');
		});
		this.on(window, 'pointermove', function (e) {
			if (!self.drag) {
				return;
			}
			self.offset = self.drag.start + (e.clientX - self.drag.x);
			self.applyTicker();
		});
		var end = function () {
			if (self.drag) {
				self.drag = null;
				self.bar.classList.remove('tzc-dragging');
			}
		};
		this.on(window, 'pointerup', end);
		this.on(window, 'pointercancel', end);
	};


	/* ------------------------------------------------------------------
	 * City finder (search box in the bar, like the "New topic" picker)
	 * ------------------------------------------------------------------ */

	var REGIONS = ['europe', 'america', 'asia', 'africa', 'oceania', 'atlantic', 'indian', 'antarctica', 'other'];

	function regionOf(zone) {
		var first = (zone || '').split('/')[0];
		var map = { Europe: 'europe', America: 'america', Asia: 'asia', Africa: 'africa', Australia: 'oceania', Pacific: 'oceania', Atlantic: 'atlantic', Indian: 'indian', Antarctica: 'antarctica' };
		return map[first] || 'other';
	}

	function fold(text) {
		text = String(text || '').toLowerCase();
		return text.normalize ? text.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : text;
	}

	Bar.prototype.buildFinder = function () {
		var self = this, i18n = this.i18n;
		var pop = el('div', 'tzc-pop');
		pop.hidden = true;
		pop.setAttribute('role', 'dialog');
		pop.setAttribute('aria-label', i18n.find_title);

		var head = el('div', 'tzc-pop-head');
		head.appendChild(el('span', 'tzc-pop-title', i18n.find_title));
		var close = button('tzc-pop-close', 'close', i18n.close);
		this.on(close, 'click', function () { self.closeFinder(true); });
		head.appendChild(close);
		pop.appendChild(head);

		var box = el('div', 'tzc-pop-search');
		var icon = el('span', 'tzc-pop-icon');
		icon.innerHTML = ICONS.search;
		box.appendChild(icon);
		this.finderInput = el('input', 'tzc-pop-input');
		this.finderInput.type = 'search';
		this.finderInput.placeholder = i18n.find_ph;
		this.finderInput.setAttribute('aria-label', i18n.find_ph);
		this.finderInput.setAttribute('autocomplete', 'off');
		box.appendChild(this.finderInput);
		pop.appendChild(box);

		this.finderList = el('div', 'tzc-pop-list');
		this.finderList.setAttribute('role', 'listbox');
		pop.appendChild(this.finderList);
		pop.appendChild(el('div', 'tzc-pop-foot', i18n.find_keys));

		this.collapsedRegions = {};
		this.on(this.finderInput, 'input', function () { self.renderFinder(); });
		this.on(this.finderInput, 'keydown', function (e) { self.finderKey(e); });
		this.on(pop, 'click', function (e) { e.stopPropagation(); });
		this.on(document, 'click', function () { self.closeFinder(false); });
		this.on(document, 'keydown', function (e) {
			if (e.key === 'Escape' && self.finderOpen) {
				self.closeFinder(true);
			}
		});

		this.finder = pop;
		return pop;
	};

	Bar.prototype.toggleFinder = function () {
		if (this.finderOpen) {
			this.closeFinder(true);
			return;
		}
		if (this.collapsed) {
			this.setCollapsed(false);
		}
		this.finderOpen = true;
		this.finder.hidden = false;
		this.btnSearch.setAttribute('aria-expanded', 'true');
		this.bar.classList.add('tzc-finder-open');
		this.finderInput.value = '';
		this.renderFinder();
		this.finderInput.focus();
	};

	Bar.prototype.closeFinder = function (focusButton) {
		if (!this.finderOpen) {
			return;
		}
		this.finderOpen = false;
		this.finder.hidden = true;
		this.btnSearch.setAttribute('aria-expanded', 'false');
		this.bar.classList.remove('tzc-finder-open');
		if (focusButton) {
			this.btnSearch.focus();
		}
	};

	Bar.prototype.recent = function (add) {
		var list = [];
		try {
			list = JSON.parse(storage('tzc-recent') || '[]') || [];
		} catch (e) {
			list = [];
		}
		if (add) {
			list = [add].concat(list.filter(function (k) { return k !== add; })).slice(0, 4);
			storage('tzc-recent', JSON.stringify(list));
		}
		return list;
	};

	Bar.prototype.finderItem = function (index) {
		var self = this, city = this.data.cities[index];
		var item = el('div', 'tzc-pop-item');
		item.setAttribute('role', 'option');
		item.setAttribute('data-index', index);
		if (city.cc && this.o.show_flag) {
			if (this.o.flag_style === 'emoji') {
				item.appendChild(el('span', 'tzc-flag tzc-flag-emoji', flagEmoji(city.cc)));
			} else {
				var img = el('img', 'tzc-flag');
				img.src = this.data.flags + city.cc + '.svg';
				img.alt = '';
				item.appendChild(img);
			}
		}
		var text = el('span', 'tzc-pop-text');
		text.appendChild(el('span', 'tzc-pop-name', city.n));
		if (city.cn) {
			text.appendChild(el('span', 'tzc-pop-country', city.cn));
		}
		item.appendChild(text);
		item.appendChild(el('span', 'tzc-pop-time'));
		this.on(item, 'mousedown', function (e) {
			e.preventDefault();
			self.selectCity(index);
		});
		this.on(item, 'mousemove', function () { self.finderActive(item); });
		return item;
	};

	Bar.prototype.renderFinder = function () {
		var self = this, i18n = this.i18n, cities = this.data.cities;
		var q = fold(this.finderInput.value.trim());
		var list = this.finderList;
		list.innerHTML = '';

		if (q) {
			var found = 0;
			cities.forEach(function (c, i) {
				if (fold(c.n).indexOf(q) !== -1 || fold(c.cn).indexOf(q) !== -1 || fold(c.z).indexOf(q) !== -1) {
					list.appendChild(self.finderItem(i));
					found++;
				}
			});
			if (!found) {
				list.appendChild(el('div', 'tzc-pop-empty', i18n.find_none));
			}
		} else {
			// recently used
			var keys = cities.map(function (c) { return c.z + '|' + c.n; });
			var recent = this.recent().map(function (k) { return keys.indexOf(k); }).filter(function (i) { return i !== -1; });
			if (recent.length) {
				list.appendChild(el('div', 'tzc-pop-section', i18n.find_recent));
				recent.forEach(function (i) { list.appendChild(self.finderItem(i)); });
			}

			list.appendChild(el('div', 'tzc-pop-section', i18n.find_all));
			REGIONS.forEach(function (region) {
				var members = [];
				cities.forEach(function (c, i) {
					if (regionOf(c.z) === region) {
						members.push(i);
					}
				});
				if (!members.length) {
					return;
				}
				var closed = !!self.collapsedRegions[region];
				var group = el('div', 'tzc-pop-group' + (closed ? ' tzc-closed' : ''));
				var title = el('button', 'tzc-pop-gtitle');
				title.type = 'button';
				title.setAttribute('aria-expanded', closed ? 'false' : 'true');
				var chev = el('span', 'tzc-pop-chev');
				chev.innerHTML = ICONS.chev;
				title.appendChild(chev);
				title.appendChild(el('span', 'tzc-pop-gname', i18n['region_' + region] || region));
				title.appendChild(el('span', 'tzc-pop-gcount', String(members.length)));
				self.on(title, 'mousedown', function (e) {
					e.preventDefault();
					self.collapsedRegions[region] = !self.collapsedRegions[region];
					self.renderFinder();
				});
				group.appendChild(title);
				var body = el('div', 'tzc-pop-gbody');
				members.forEach(function (i) { body.appendChild(self.finderItem(i)); });
				group.appendChild(body);
				list.appendChild(group);
			});
		}

		this.finderTimes();
		var first = list.querySelector('.tzc-pop-item');
		this.finderActive(q ? first : null);
	};

	Bar.prototype.finderTimes = function () {
		var now = Date.now(), o = this.o, i18n = this.i18n;
		var homeOff = entryAt(this.data.home, now)[1];
		var items = this.finderList.querySelectorAll('.tzc-pop-item');
		for (var i = 0; i < items.length; i++) {
			var city = this.data.cities[+items[i].getAttribute('data-index')];
			var off = entryAt(city.t, now)[1];
			var d = new Date(now + off * 60000);
			var h = d.getUTCHours(), m = d.getUTCMinutes();
			var hm = o.format === '12' ? (h % 12 || 12) + ':' + pad(m) + (h < 12 ? i18n.am : i18n.pm) : pad(h) + ':' + pad(m);
			var diff = city.home ? i18n.home : fmtDiff(off - homeOff, i18n);
			setText(items[i].querySelector('.tzc-pop-time'), hm + '  \u00b7  ' + diff);
		}
	};

	Bar.prototype.finderActive = function (item) {
		var items = this.finderList.querySelectorAll('.tzc-pop-item');
		for (var i = 0; i < items.length; i++) {
			items[i].classList.toggle('tzc-active', items[i] === item);
			items[i].setAttribute('aria-selected', items[i] === item ? 'true' : 'false');
		}
		this.finderCurrent = item || null;
		if (item && item.scrollIntoView) {
			item.scrollIntoView({ block: 'nearest' });
		}
	};

	Bar.prototype.finderKey = function (e) {
		var items = Array.prototype.slice.call(this.finderList.querySelectorAll('.tzc-pop-item'));
		var pos = items.indexOf(this.finderCurrent);
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			if (!items.length) {
				return;
			}
			pos = e.key === 'ArrowDown' ? (pos + 1) % items.length : (pos <= 0 ? items.length - 1 : pos - 1);
			this.finderActive(items[pos]);
		} else if (e.key === 'Enter') {
			e.preventDefault();
			var item = this.finderCurrent || items[0];
			if (item) {
				this.selectCity(+item.getAttribute('data-index'));
			}
		}
	};

	/**
	 * Bring the card of a city into view and highlight it
	 */
	Bar.prototype.selectCity = function (index) {
		var city = this.data.cities[index];
		this.recent(city.z + '|' + city.n);
		this.closeFinder(false);

		var ref = null;
		for (var i = 0; i < this.refs.length; i++) {
			if (!this.refs[i].clone && this.refs[i].city === city) {
				ref = this.refs[i];
				break;
			}
		}
		if (!ref) {
			return;
		}

		var card = ref.card, vp = this.viewport;
		this.holdUntil = Date.now() + 8000;
		var centre = card.offsetLeft - (vp.clientWidth - card.offsetWidth) / 2;

		if (this.o.mode === 'carousel') {
			vp.scrollTo({ left: Math.max(0, centre), behavior: reduceMotion ? 'auto' : 'smooth' });
		} else if (this.o.mode === 'ticker' && this.overflowing) {
			this.speed = 0;
			this.offset = -centre;
			this.applyTicker();
		} else if (card.scrollIntoView) {
			card.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
		}

		card.classList.remove('tzc-flash');
		void card.offsetWidth;
		card.classList.add('tzc-flash');
		var t = setTimeout(function () { card.classList.remove('tzc-flash'); }, 2600);
		this.timers.push(t);
	};

	/* ------------------------------------------------------------------
	 * Public API
	 * ------------------------------------------------------------------ */

	TZC.render = function (root, data) {
		if (root.tzcBar) {
			root.tzcBar.destroy();
		}
		if (!data || !data.cities || !data.cities.length) {
			root.tzcBar = null;
			return null;
		}
		root.tzcBar = new Bar(root, data);
		return root.tzcBar;
	};

	TZC.mount = function (scope) {
		var roots = (scope || document).querySelectorAll('.tzc-root');
		for (var i = 0; i < roots.length; i++) {
			var script = roots[i].querySelector('script.tzc-data');
			if (!script || roots[i].tzcBar) {
				continue;
			}
			try {
				var data = JSON.parse(script.textContent);
				roots[i].removeChild(script);
				TZC.render(roots[i], data);
			} catch (e) {
				if (window.console) {
					window.console.error('Timezone Clock:', e);
				}
			}
		}
	};

	TZC.entryAt = entryAt;
	TZC.fmtOffset = fmtOffset;
	window.TZC = TZC;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { TZC.mount(); });
	} else {
		TZC.mount();
	}
})(window, document);
