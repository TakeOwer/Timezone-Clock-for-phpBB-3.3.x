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
		this.cardHelp();
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
		if (this.miniRaf) {
			window.cancelAnimationFrame(this.miniRaf);
		}
		this.hideTip();
		this.closeInfo(false);
		clearInterval(this.helpTimer);
		[this.tip, this.info, this.infoBackdrop].forEach(function (n) {
			if (n && n.parentNode) {
				n.parentNode.removeChild(n);
			}
		});
		if (this.resizeObserver) {
			this.resizeObserver.disconnect();
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
		if (o.autoplay) {
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
		this.miniTrack = el('span', 'tzc-mini-track');
		this.miniSeq = el('span', 'tzc-mini-seq');
		this.miniCopy = el('span', 'tzc-mini-seq tzc-mini-copy');
		this.miniCopy.setAttribute('aria-hidden', 'true');
		this.miniTrack.appendChild(this.miniSeq);
		this.miniTrack.appendChild(this.miniCopy);
		this.miniText.appendChild(this.miniTrack);
		this.miniOffset = 0;
		this.miniSpeed = 0;
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
			card.setAttribute('aria-label', city.n + (city.cn ? ', ' + city.cn : ''));
			if (o.info) {
				card.setAttribute('role', 'button');
				card.setAttribute('tabindex', '0');
				card.setAttribute('aria-haspopup', 'dialog');
			} else {
				card.setAttribute('role', 'group');
			}
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
				img.draggable = false;
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

			if (!r.clone && (o.autoplay || mini.length < 5)) {
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

		var miniLine = mini.join('  \u00b7  ');
		setText(this.miniSeq, miniLine);
		setText(this.miniCopy, miniLine);
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
		return this.o.autoplay && !this.paused && !this.collapsed && !this.drag && !document.hidden && !this.finderOpen && !this.infoCity &&
			!(this.holdUntil && Date.now() < this.holdUntil) &&
			!(this.o.pause_hover && this.isHover()) && !this.keyboardFocus() && this.overflowing;
	};

	/**
	 * Mouse really over the bar right now (not a stale mouseenter/mouseleave state)
	 */
	Bar.prototype.isHover = function () {
		try {
			return this.bar.matches(':hover');
		} catch (e) {
			return this.hover;
		}
	};

	/**
	 * Pause only for keyboard navigation inside the bar: a button clicked
	 * with the mouse keeps the focus but must not stop the scrolling forever
	 */
	Bar.prototype.keyboardFocus = function () {
		var a = document.activeElement;
		// only the cards area (viewport, arrows, dots): the tool buttons never block the scrolling
		var zones = [this.viewport, this.btnPrev, this.btnNext, this.dots];
		var inside = false;
		for (var i = 0; i < zones.length; i++) {
			if (zones[i] && (zones[i] === a || zones[i].contains(a))) {
				inside = true;
			}
		}
		if (!a || !inside) {
			return false;
		}
		try {
			return a.matches(':focus-visible');
		} catch (e) {
			return false;
		}
	};

	/**
	 * Collapsed line: scrolls when automatic scrolling is enabled,
	 * stops while the mouse is over it and starts again afterwards
	 */
	Bar.prototype.miniCanPlay = function () {
		var hover = false;
		try {
			hover = this.mini.matches(':hover');
		} catch (e) {
			hover = false;
		}
		return this.o.autoplay && this.collapsed && !this.paused && !document.hidden && !this.finderOpen &&
			!(this.o.pause_hover && hover) && !reduceMotion;
	};

	Bar.prototype.miniStep = function (dt) {
		if (!this.collapsed || !this.o.autoplay) {
			return;
		}
		// one round = width of the text plus its trailing space (the copy follows it)
		var period = this.miniSeq.offsetWidth;
		var overflow = period > 0 && this.miniText.clientWidth > 0 && period > this.miniText.clientWidth + 2;
		this.mini.classList.toggle('tzc-mini-scroll', overflow);
		if (!overflow) {
			this.miniOffset = 0;
			this.miniTrack.style.transform = '';
			return;
		}
		var target = this.miniCanPlay() ? Math.max(20, Math.min(80, this.o.ticker_speed || 40)) : 0;
		// quick stop (mouse over, pause), soft start
		this.miniSpeed += (target - this.miniSpeed) * Math.min(1, dt * (target ? 3 : 18));
		if (!target && this.miniSpeed < 0.5) {
			this.miniSpeed = 0;
		}
		this.miniOffset -= this.miniSpeed * dt;
		while (this.miniOffset <= -period) {
			this.miniOffset += period;
		}
		while (this.miniOffset > 0) {
			this.miniOffset -= period;
		}
		this.miniTrack.style.transform = 'translate3d(' + this.miniOffset.toFixed(2) + 'px,0,0)';
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

		if (o.autoplay) {
			var miniLast = 0;
			var miniLoop = function (ts) {
				if (self.destroyed) {
					return;
				}
				var dt = miniLast ? Math.min(0.1, (ts - miniLast) / 1000) : 0;
				miniLast = ts;
				self.miniStep(dt);
				self.miniRaf = window.requestAnimationFrame(miniLoop);
			};
			this.miniRaf = window.requestAnimationFrame(miniLoop);
		}

		// fonts and flags change the widths: measure again once loaded
		this.layout();
		if (window.ResizeObserver) {
			var pending = false;
			this.resizeObserver = new window.ResizeObserver(function () {
				if (pending) {
					return;
				}
				pending = true;
				window.requestAnimationFrame(function () {
					pending = false;
					self.layout();
				});
			});
			this.resizeObserver.observe(vp);
			this.resizeObserver.observe(this.track);
		}
		this.on(window, 'load', function () { self.layout(); });
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(function () { self.layout(); });
		}
		var t = setTimeout(function () { self.layout(); }, 600);
		this.timers.push(t);
	};

	Bar.prototype.layout = function () {
		if (this.destroyed) {
			return;
		}
		var vp = this.viewport, o = this.o;

		if (o.mode === 'ticker') {
			// Length of one full round of cards, measured on the real cards (the copies
			// used for the endless loop may still be hidden at this point)
			var cards = [];
			for (var i = 0; i < this.refs.length; i++) {
				if (!this.refs[i].clone && this.refs[i].card.offsetParent !== null) {
					cards.push(this.refs[i].card);
				}
			}
			var styles = window.getComputedStyle(this.track);
			var gap = parseFloat(styles.columnGap || styles.gap) || 0;
			var setWidth = cards.length ? cards[cards.length - 1].offsetLeft + cards[cards.length - 1].offsetWidth - cards[0].offsetLeft : 0;

			this.overflowing = cards.length > 0 && setWidth > vp.clientWidth + 2;
			this.bar.classList.toggle('tzc-overflow', this.overflowing);
			if (!this.overflowing) {
				this.offset = 0;
				this.half = 0;
				this.track.style.transform = '';
			} else {
				// copies are visible now: the period is exact
				this.half = this.measurePeriod() || (setWidth + gap);
				this.applyTicker();
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
			e.preventDefault();
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
			if (moved) {
				self.suppressClick = Date.now() + 350;
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

	/**
	 * Exact length of one round: distance between the first visible card and
	 * its copy. Measured live, so it is right even if the page changed size.
	 */
	Bar.prototype.measurePeriod = function () {
		var original = null, copy = null;
		for (var i = 0; i < this.refs.length; i++) {
			var r = this.refs[i];
			if (!r.clone && !original && r.card.offsetParent !== null) {
				original = r;
			}
		}
		if (!original) {
			return 0;
		}
		for (var j = 0; j < this.refs.length; j++) {
			if (this.refs[j].clone && this.refs[j].city === original.city) {
				copy = this.refs[j];
				break;
			}
		}
		if (!copy || copy.card.offsetParent === null) {
			return 0;
		}
		return copy.card.offsetLeft - original.card.offsetLeft;
	};

	Bar.prototype.applyTicker = function () {
		var period = this.measurePeriod();
		if (period > 0) {
			this.half = period;
		}
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
			if (e.pointerType === 'mouse') {
				// no text selection and no native drag of the flags
				e.preventDefault();
			}
			self.layout();
			self.drag = { x: e.clientX, start: self.offset };
			self.bar.classList.add('tzc-dragging');
		});
		this.on(window, 'pointermove', function (e) {
			if (!self.drag) {
				return;
			}
			if (Math.abs(e.clientX - self.drag.x) > 5) {
				self.dragMoved = true;
			}
			self.offset = self.drag.start + (e.clientX - self.drag.x);
			self.applyTicker();
		});
		var end = function () {
			if (self.drag) {
				if (self.dragMoved) {
					self.suppressClick = Date.now() + 350;
				}
				self.dragMoved = false;
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
		if (document.activeElement && this.finder.contains(document.activeElement)) {
			document.activeElement.blur();
		}
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

		var ref = null, cards = [];
		for (var i = 0; i < this.refs.length; i++) {
			if (this.refs[i].city === city) {
				// the ticker has two copies of every card: highlight both
				cards.push(this.refs[i].card);
				if (!ref && !this.refs[i].clone) {
					ref = this.refs[i];
				}
			}
		}
		if (!ref) {
			return;
		}

		this.layout();
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

		cards.forEach(function (c) {
			c.classList.remove('tzc-flash');
			void c.offsetWidth;
			c.classList.add('tzc-flash');
		});
		var t = setTimeout(function () {
			cards.forEach(function (c) { c.classList.remove('tzc-flash'); });
		}, 2600);
		this.timers.push(t);
	};


	/* ------------------------------------------------------------------
	 * City tooltip (mouse) and information popup (click / tap)
	 * ------------------------------------------------------------------ */

	var hoverCapable = !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);

	function fmtHours(min) {
		var a = Math.abs(min);
		return Math.floor(a / 60) + (a % 60 ? ':' + pad(a % 60) : '') + ' h';
	}

	var longFormatters = {};
	function formatLongDate(locale, shiftedMs) {
		try {
			if (!longFormatters[locale]) {
				longFormatters[locale] = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
			}
			return longFormatters[locale].format(new Date(shiftedMs));
		} catch (e) {
			var d = new Date(shiftedMs);
			return d.getUTCDate() + '/' + (d.getUTCMonth() + 1) + '/' + d.getUTCFullYear();
		}
	}

	var changeFormatters = {};
	function formatChangeDate(locale, shiftedMs) {
		try {
			if (!changeFormatters[locale]) {
				changeFormatters[locale] = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
			}
			return changeFormatters[locale].format(new Date(shiftedMs));
		} catch (e) {
			return formatDate(locale, shiftedMs);
		}
	}

	/**
	 * Sunrise and sunset of the local day of a city (local times, null = none)
	 */
	function sunTimes(city, now, off) {
		if (typeof city.lat !== 'number' || typeof city.lon !== 'number') {
			return null;
		}
		var start = Math.floor((now + off * 60000) / 86400000) * 86400000 - off * 60000;
		var step = 120000, h = -0.833;
		var rise = null, set = null, prev = sunElevation(city.lat, city.lon, start) - h, above = prev > 0;
		for (var t = start + step; t <= start + 86400000; t += step) {
			var cur = sunElevation(city.lat, city.lon, t) - h;
			if (prev <= 0 && cur > 0 && rise === null) {
				rise = t - step + step * (-prev / (cur - prev));
			}
			if (prev > 0 && cur <= 0 && set === null) {
				set = t - step + step * (prev / (prev - cur));
			}
			prev = cur;
		}
		return { rise: rise, set: set, polar: (rise === null && set === null) ? (above ? 'day' : 'night') : '' };
	}

	Bar.prototype.clock = function (ms, off, seconds) {
		var d = new Date(ms + off * 60000), o = this.o, i18n = this.i18n;
		var h = d.getUTCHours(), m = d.getUTCMinutes();
		var out = o.format === '12' ? (h % 12 || 12) + ':' + pad(m) : pad(h) + ':' + pad(m);
		if (seconds) {
			out += ':' + pad(d.getUTCSeconds());
		}
		return out + (o.format === '12' ? (h < 12 ? i18n.am : i18n.pm) : '');
	};

	/**
	 * Everything known about a city right now
	 */
	Bar.prototype.cityInfo = function (city) {
		var now = Date.now(), i18n = this.i18n;
		var e = entryAt(city.t, now), off = e[1];
		var homeOff = entryAt(this.data.home, now)[1];
		var homeDay = dayNumber(now + homeOff * 60000), day = dayNumber(now + off * 60000);
		var diff = off - homeOff, abbr = /^[+\-]?\d/.test(e[2]) ? '' : e[2];
		var info = {
			now: now,
			off: off,
			abbr: abbr,
			dst: !!e[3],
			utc: fmtOffset(off) + (abbr ? ' (' + abbr + ')' : ''),
			date: formatDate(this.locale, now + off * 60000),
			longDate: formatLongDate(this.locale, now + off * 60000),
			rel: day - homeDay === 1 ? i18n.tomorrow : (day - homeDay === -1 ? i18n.yesterday : ''),
			diffText: city.home ? i18n.your_zone : (diff === 0 ? i18n.diff_same : (diff > 0 ? i18n.diff_ahead : i18n.diff_behind).replace('%s', fmtHours(diff))),
			phase: phaseOf(city, now, new Date(now + off * 60000).getUTCHours()),
			usesDst: false,
			next: null
		};
		for (var i = 0; i < city.t.length; i++) {
			if (city.t[i][3]) {
				info.usesDst = true;
			}
			if (!info.next && city.t[i][0] !== null && now < city.t[i][0] && city.t[i + 1]) {
				info.next = { at: city.t[i][0], before: city.t[i][1], after: city.t[i + 1][1], toDst: !!city.t[i + 1][3], fromDst: !!city.t[i][3] };
			}
		}
		return info;
	};

	Bar.prototype.refOf = function (node) {
		var card = node && node.closest ? node.closest('.tzc-card') : null;
		if (!card || !this.viewport.contains(card)) {
			return null;
		}
		for (var i = 0; i < this.refs.length; i++) {
			if (this.refs[i].card === card) {
				return this.refs[i];
			}
		}
		return null;
	};

	Bar.prototype.cardHelp = function () {
		var self = this, vp = this.viewport, o = this.o;
		if (!o.tooltip && !o.info) {
			return;
		}
		this.bar.classList.toggle('tzc-has-info', !!o.info);

		if (o.tooltip && hoverCapable) {
			this.on(vp, 'pointerover', function (e) {
				if (e.pointerType !== 'mouse' || self.drag || self.infoCity) {
					return;
				}
				var ref = self.refOf(e.target);
				if (ref && ref !== self.tipRef) {
					self.showTip(ref);
				}
			});
			this.on(vp, 'pointerleave', function () { self.hideTip(); });
			this.on(vp, 'pointerdown', function () { self.hideTip(); });
		}

		if (o.info) {
			this.on(vp, 'click', function (e) {
				if (self.suppressClick && Date.now() < self.suppressClick) {
					return;
				}
				var ref = self.refOf(e.target);
				if (ref) {
					self.hideTip();
					self.openInfo(ref, false);
				}
			});
			this.on(vp, 'keydown', function (e) {
				if (e.key !== 'Enter' && e.key !== ' ') {
					return;
				}
				var ref = self.refOf(e.target);
				if (ref && e.target === ref.card) {
					e.preventDefault();
					self.openInfo(ref, true);
				}
			});
			this.on(document, 'pointerdown', function (e) {
				if (self.infoCity && self.info && !self.info.contains(e.target) && !self.refOf(e.target)) {
					self.closeInfo(false);
				}
			});
			this.on(document, 'keydown', function (e) {
				if (e.key === 'Escape' && self.infoCity) {
					self.closeInfo(true);
				}
			});
		}

		this.on(window, 'resize', function () {
			self.hideTip();
			self.placeInfo();
		});
		this.on(window, 'scroll', function () {
			self.hideTip();
			self.placeInfo();
		}, { passive: true });
	};

	/**
	 * Fixed box next to a card: above if there is room, otherwise below,
	 * always inside the window
	 */
	Bar.prototype.place = function (box, card, gap) {
		var r = card.getBoundingClientRect();
		var w = box.offsetWidth, h = box.offsetHeight;
		var vw = document.documentElement.clientWidth, vh = window.innerHeight;
		var below = r.top - h - gap < 8 && r.bottom + h + gap <= vh - 8;
		var top = below ? r.bottom + gap : r.top - h - gap;
		var left = Math.max(8, Math.min(vw - w - 8, r.left + r.width / 2 - w / 2));
		box.style.top = Math.max(8, top) + 'px';
		box.style.left = left + 'px';
		box.style.setProperty('--tzc-arrow', Math.max(14, Math.min(w - 14, r.left + r.width / 2 - left)) + 'px');
		box.classList.toggle('tzc-below', below);
	};

	Bar.prototype.showTip = function (ref) {
		var self = this, city = ref.city, i18n = this.i18n;
		if (!this.tip) {
			this.tip = el('div', 'tzc-tip');
			this.tip.setAttribute('role', 'tooltip');
			document.body.appendChild(this.tip);
		}
		this.tipRef = ref;
		var fill = function () {
			if (!self.tipRef) {
				return;
			}
			var info = self.cityInfo(city), tip = self.tip;
			tip.innerHTML = '';
			tip.appendChild(el('div', 'tzc-tip-title', city.n + (city.cn ? ' — ' + city.cn : '')));
			tip.appendChild(el('div', '', self.clock(info.now, info.off, false) + '  ·  ' + info.date + (info.rel ? ' (' + info.rel + ')' : '')));
			tip.appendChild(el('div', '', info.utc + (info.dst ? '  ·  ' + i18n.dst : '')));
			tip.appendChild(el('div', '', info.diffText));
			if (self.o.info) {
				tip.appendChild(el('div', 'tzc-tip-more', i18n.tip_more));
			}
			self.place(tip, ref.card, 10);
		};
		clearTimeout(this.tipDelay);
		this.tipDelay = setTimeout(function () {
			if (self.tipRef !== ref) {
				return;
			}
			fill();
			self.tip.classList.add('tzc-show');
			clearInterval(self.helpTimer);
			self.helpTimer = setInterval(function () {
				if (self.tipRef) {
					fill();
				} else if (self.infoCity) {
					self.fillInfo();
				}
			}, 1000);
		}, 250);
	};

	Bar.prototype.hideTip = function () {
		clearTimeout(this.tipDelay);
		this.tipRef = null;
		if (this.tip) {
			this.tip.classList.remove('tzc-show');
		}
		if (!this.infoCity) {
			clearInterval(this.helpTimer);
		}
	};

	Bar.prototype.openInfo = function (ref, byKeyboard) {
		this.infoByKey = !!byKeyboard;
		var self = this, i18n = this.i18n;
		if (this.infoCity === ref.city) {
			this.closeInfo(false);
			return;
		}
		if (!this.info) {
			this.infoBackdrop = el('div', 'tzc-info-backdrop');
			this.on(this.infoBackdrop, 'click', function () { self.closeInfo(false); });
			this.info = el('div', 'tzc-info tzc-info-' + (this.o.theme === 'dark' ? 'dark' : 'light'));
			this.info.setAttribute('role', 'dialog');
			this.info.style.setProperty('--tzc-accent', this.o.accent);
			document.body.appendChild(this.infoBackdrop);
			document.body.appendChild(this.info);
		}
		this.infoCity = ref.city;
		this.infoRef = ref;
		this.info.setAttribute('aria-label', ref.city.n);
		this.fillInfo();
		this.info.classList.add('tzc-show');
		this.infoBackdrop.classList.add('tzc-show');
		this.placeInfo();
		clearInterval(this.helpTimer);
		this.helpTimer = setInterval(function () { self.fillInfo(); }, 1000);
		if (byKeyboard) {
			// after the fade-in: a box that is still appearing cannot take the focus
			var box = this.info;
			clearTimeout(this.focusTimer);
			this.focusTimer = setTimeout(function () {
				var close = box.querySelector('.tzc-info-close');
				if (close && self.infoCity) {
					close.focus({ preventScroll: true });
				}
			}, 200);
		}
	};

	Bar.prototype.closeInfo = function (focusCard) {
		if (!this.infoCity) {
			return;
		}
		var ref = this.infoRef;
		this.infoCity = null;
		this.infoRef = null;
		clearInterval(this.helpTimer);
		clearTimeout(this.focusTimer);
		this.info.classList.remove('tzc-show');
		this.infoBackdrop.classList.remove('tzc-show');
		// the focus goes back to the card only for keyboard users,
		// otherwise it would keep the bar paused
		if (focusCard && this.infoByKey && ref && !ref.clone) {
			ref.card.focus({ preventScroll: true });
		} else if (this.info.contains(document.activeElement)) {
			document.activeElement.blur();
		}
	};

	Bar.prototype.placeInfo = function () {
		if (!this.infoCity || !this.info) {
			return;
		}
		if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) {
			// bottom sheet on small screens
			this.info.style.top = '';
			this.info.style.left = '';
			this.info.classList.remove('tzc-below');
			return;
		}
		this.place(this.info, this.infoRef.card, 12);
	};

	/**
	 * Rows of the information popup: [label, value]
	 */
	Bar.prototype.infoRows = function (city, info) {
		var i18n = this.i18n;
		var rows = [
			[i18n.info_diff, info.diffText],
			[i18n.info_offset, info.utc],
			[i18n.info_zone, city.z],
			[i18n.dst, info.dst ? i18n.dst_on : (info.usesDst || info.next ? i18n.dst_off : i18n.dst_none)]
		];

		var next = i18n.next_none;
		if (info.next) {
			var when = formatChangeDate(this.locale, info.next.at + info.next.before * 60000) + ', ' + this.clock(info.next.at, info.next.before, false);
			next = i18n.next_fmt.replace('%1$s', when).replace('%2$s', fmtOffset(info.next.after));
			if (info.next.toDst) {
				next += ' · ' + i18n.next_dst_start;
			} else if (info.next.fromDst) {
				next += ' · ' + i18n.next_dst_end;
			}
		}
		rows.push([i18n.info_next, next]);
		rows.push([i18n.info_phase, i18n[info.phase] || '']);

		var sun = sunTimes(city, info.now, info.off);
		if (sun) {
			var sunText;
			if (sun.polar) {
				sunText = sun.polar === 'day' ? i18n.sun_polar_day : i18n.sun_polar_night;
			} else {
				sunText = i18n.sun_fmt
					.replace('%1$s', sun.rise !== null ? this.clock(sun.rise, info.off, false) : '–')
					.replace('%2$s', sun.set !== null ? this.clock(sun.set, info.off, false) : '–');
			}
			rows.push([i18n.info_sun, sunText]);
		}
		return rows;
	};

	/**
	 * Build the popup once per city, then only refresh its texts
	 * (rebuilding every second would steal the keyboard focus)
	 */
	Bar.prototype.fillInfo = function () {
		if (!this.infoCity) {
			return;
		}
		var self = this, city = this.infoCity, i18n = this.i18n;
		var info = this.cityInfo(city), box = this.info;
		var rows = this.infoRows(city, info);
		var dateText = info.longDate + (info.rel ? ' · ' + info.rel : '');

		if (this.infoBuilt === city && this.infoNodes && this.infoNodes.values.length === rows.length) {
			setText(this.infoNodes.time, this.clock(info.now, info.off, true));
			setText(this.infoNodes.date, dateText);
			for (var i = 0; i < rows.length; i++) {
				setText(this.infoNodes.values[i], rows[i][1]);
			}
			return;
		}

		box.innerHTML = '';
		var head = el('div', 'tzc-info-head');
		if (city.cc) {
			var img = el('img', 'tzc-flag');
			img.src = this.data.flags + city.cc + '.svg';
			img.alt = '';
			img.width = 22;
			img.height = 15;
			head.appendChild(img);
		}
		var names = el('div', 'tzc-info-names');
		names.appendChild(el('div', 'tzc-info-city', city.n));
		if (city.cn) {
			names.appendChild(el('div', 'tzc-info-country', city.cn));
		}
		head.appendChild(names);
		var close = button('tzc-info-close', 'close', i18n.close);
		close.addEventListener('click', function () { self.closeInfo(true); });
		head.appendChild(close);
		box.appendChild(head);

		var nodes = { time: el('div', 'tzc-info-time', this.clock(info.now, info.off, true)), date: el('div', 'tzc-info-date', dateText), values: [] };
		box.appendChild(nodes.time);
		box.appendChild(nodes.date);
		var list = el('div', 'tzc-info-rows');
		rows.forEach(function (r) {
			var row = el('div', 'tzc-info-row');
			row.appendChild(el('span', 'tzc-info-label', r[0]));
			var value = el('span', 'tzc-info-value', r[1]);
			row.appendChild(value);
			nodes.values.push(value);
			list.appendChild(row);
		});
		box.appendChild(list);
		box.appendChild(el('div', 'tzc-info-foot', i18n.info_note));

		this.infoNodes = nodes;
		this.infoBuilt = city;
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

	TZC.version = '1.0.11';

	/**
	 * Diagnostics: type TZC.debug() in the browser console
	 */
	TZC.debug = function () {
		var out = [];
		var roots = document.querySelectorAll('.tzc-root');
		for (var i = 0; i < roots.length; i++) {
			var b = roots[i].tzcBar;
			if (!b) {
				out.push({ root: i, bar: 'not started' });
				continue;
			}
			out.push({
				version: TZC.version,
				mode: b.o.mode,
				cities: (b.data.cities || []).length,
				overflowing: b.overflowing,
				period: Math.round(b.half || 0),
				periodMeasured: b.o.mode === 'ticker' ? Math.round(b.measurePeriod()) : null,
				offset: Math.round(b.offset || 0),
				scrollLeft: b.viewport.scrollLeft,
				viewportWidth: b.viewport.clientWidth,
				canPlay: b.canPlay(),
				paused: b.paused,
				hover: b.isHover(),
				keyboardFocus: b.keyboardFocus(),
				collapsed: b.collapsed,
				collapsedLineScrolling: b.collapsed ? b.miniCanPlay() : null,
				collapsedLineOffset: Math.round(b.miniOffset || 0)
			});
		}
		if (window.console && window.console.table) {
			window.console.table(out);
		}
		return out;
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
