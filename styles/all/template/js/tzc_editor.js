/**
 * Timezone Clock - city picker and live preview (ACP and UCP)
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 */
(function (window, document) {
	'use strict';

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

	function json(node, attr, fallback) {
		try {
			var v = node.getAttribute(attr);
			return v ? JSON.parse(v) : fallback;
		} catch (e) {
			return fallback;
		}
	}

	function iconButton(cls, text, title) {
		var b = el('button', 'tzc-ed-btn ' + cls, text);
		b.type = 'button';
		b.title = title;
		b.setAttribute('aria-label', title);
		return b;
	}


	/* ------------------------------------------------------------------
	 * phpBB confirm / alert boxes (the same used by phpBB for AJAX actions)
	 * ------------------------------------------------------------------ */

	function i18n(key) {
		var holder = document.getElementById('tzc-i18n');
		return holder ? (holder.getAttribute('data-' + key) || '') : '';
	}

	function escapeHtml(text) {
		var d = document.createElement('div');
		d.textContent = text == null ? '' : String(text);
		return d.innerHTML;
	}

	/**
	 * Ask a confirmation with the phpBB box (#phpbb_confirm); callback(true|false)
	 */
	function tzcConfirm(message, callback) {
		var $ = window.jQuery;
		if ($ && window.phpbb && typeof window.phpbb.confirm === 'function' && $('#phpbb_confirm').length) {
			var html = '<h3>' + escapeHtml(i18n('title')) + '</h3><p>' + escapeHtml(message) + '</p>' +
				'<fieldset class="submit-buttons">' +
				'<input type="button" name="confirm" value="' + escapeHtml(i18n('yes')) + '" class="button1">&nbsp;' +
				'<input type="button" name="cancel" value="' + escapeHtml(i18n('no')) + '" class="button2">' +
				'</fieldset>';
			window.phpbb.confirm(html, function (ok) { callback(!!ok); }, true);
			return;
		}
		tzcModal(message, true, callback);
	}

	/**
	 * Show a message with the phpBB alert box
	 */
	function tzcAlert(message) {
		var $ = window.jQuery;
		if ($ && window.phpbb && typeof window.phpbb.alert === 'function' && $('#phpbb_alert').length) {
			window.phpbb.alert(i18n('title'), escapeHtml(message));
			return;
		}
		tzcModal(message, false, function () {});
	}

	// Fallback with the same look, for styles without the phpBB boxes
	function tzcModal(message, ask, callback) {
		var wrap = el('div', 'tzc-modal-wrap');
		var box = el('div', 'tzc-modal');
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.appendChild(el('h3', '', i18n('title')));
		box.appendChild(el('p', '', message));
		var buttons = el('div', 'tzc-modal-buttons');
		var yes = el('button', 'button1', ask ? i18n('yes') : i18n('ok'));
		yes.type = 'button';
		buttons.appendChild(yes);
		var no = null;
		if (ask) {
			no = el('button', 'button2', i18n('no'));
			no.type = 'button';
			buttons.appendChild(no);
		}
		box.appendChild(buttons);
		wrap.appendChild(box);
		document.body.appendChild(wrap);
		var close = function (result) {
			document.removeEventListener('keydown', onKey);
			wrap.parentNode.removeChild(wrap);
			callback(result);
		};
		var onKey = function (e) {
			if (e.key === 'Escape') { close(false); }
			if (e.key === 'Enter') { e.preventDefault(); close(true); }
		};
		document.addEventListener('keydown', onKey);
		yes.addEventListener('click', function () { close(true); });
		if (no) {
			no.addEventListener('click', function () { close(false); });
		}
		wrap.addEventListener('click', function (e) {
			if (e.target === wrap) { close(false); }
		});
		yes.focus();
	}

	/**
	 * Links and submit buttons with data-tzc-confirm ask first
	 */
	function bindConfirms() {
		var nodes = document.querySelectorAll('[data-tzc-confirm]');
		for (var i = 0; i < nodes.length; i++) {
			(function (node) {
				node.addEventListener('click', function (e) {
					if (node.tzcConfirmed) {
						node.tzcConfirmed = false;
						return;
					}
					e.preventDefault();
					tzcConfirm(node.getAttribute('data-tzc-confirm'), function (ok) {
						if (!ok) {
							return;
						}
						if (node.tagName === 'A') {
							window.location.href = node.href;
							return;
						}
						// submit button: keep its name/value in the request
						var form = node.form;
						if (form) {
							var hidden = document.createElement('input');
							hidden.type = 'hidden';
							hidden.name = node.name;
							hidden.value = node.value;
							form.appendChild(hidden);
							form.submit();
						}
					});
				});
			})(nodes[i]);
		}
	}

	window.TZC = window.TZC || {};
	window.TZC.confirm = tzcConfirm;
	window.TZC.alert = tzcAlert;

	/* ------------------------------------------------------------------
	 * City picker
	 * ------------------------------------------------------------------ */

	function Picker(root) {
		this.root = root;
		this.url = root.getAttribute('data-search-url');
		this.max = parseInt(root.getAttribute('data-max'), 10) || 100;
		this.flags = root.getAttribute('data-flags');
		this.mobileToggle = root.getAttribute('data-mobile-toggle') === '1';
		this.i18n = json(root, 'data-i18n', {});
		this.cities = json(root, 'data-initial', []);
		this.field = root.querySelector('.tzc-ed-field');
		this.input = root.querySelector('.tzc-ed-input');
		this.results = root.querySelector('.tzc-ed-results');
		this.list = root.querySelector('.tzc-ed-list');
		this.counter = root.querySelector('.tzc-ed-count');
		this.items = [];
		this.active = -1;
		this.dragIndex = null;
		this.timer = null;
		this.request = 0;

		this.bind();
		this.renderList();
	}

	Picker.prototype.bind = function () {
		var self = this;

		this.input.addEventListener('input', function () {
			clearTimeout(self.timer);
			var q = self.input.value.trim();
			if (q.length < 2) {
				self.hideResults();
				return;
			}
			self.timer = setTimeout(function () { self.search(q); }, 220);
		});

		this.input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				self.move(e.key === 'ArrowDown' ? 1 : -1);
			} else if (e.key === 'Enter') {
				// never submit the form from the search box
				e.preventDefault();
				var item = self.items[self.active >= 0 ? self.active : 0];
				if (item) {
					self.add(item);
				}
			} else if (e.key === 'Escape') {
				self.hideResults();
			}
		});

		this.addBtn = this.root.querySelector('.tzc-ed-addbtn');
		if (this.addBtn) {
			this.addBtn.addEventListener('click', function () {
				var item = self.items[self.active >= 0 ? self.active : 0];
				if (item) {
					self.add(item);
				} else if (self.input.value.trim().length >= 2) {
					self.search(self.input.value.trim());
				} else {
					self.input.focus();
				}
			});
		}

		document.addEventListener('click', function (e) {
			if (!self.root.contains(e.target)) {
				self.hideResults();
			}
		});

		var form = this.root.closest('form');
		if (form) {
			form.addEventListener('submit', function () { self.sync(); });
		}
	};

	Picker.prototype.search = function (q) {
		var self = this;
		var id = ++this.request;
		this.results.innerHTML = '';
		this.results.appendChild(el('li', 'tzc-ed-msg', this.i18n.searching));
		this.results.hidden = false;

		var url = this.url + (this.url.indexOf('?') === -1 ? '?' : '&') + 'action=search&q=' + encodeURIComponent(q);
		fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (id === self.request) {
					self.showResults(data.results || []);
				}
			})
			.catch(function () {
				if (id === self.request) {
					self.results.innerHTML = '';
					self.results.appendChild(el('li', 'tzc-ed-msg tzc-ed-error', self.i18n.error));
				}
			});
	};

	Picker.prototype.showResults = function (items) {
		var self = this;
		this.items = items;
		this.active = -1;
		this.results.innerHTML = '';

		if (!items.length) {
			this.results.appendChild(el('li', 'tzc-ed-msg', this.i18n.none));
			return;
		}

		items.forEach(function (item, index) {
			var li = el('li', 'tzc-ed-result');
			li.setAttribute('role', 'option');
			if (item.cc) {
				var img = el('img', 'tzc-ed-flag');
				img.src = self.flags + item.cc + '.svg';
				img.alt = '';
				li.appendChild(img);
			}
			var text = el('span', 'tzc-ed-rtext');
			text.appendChild(el('strong', '', item.n));
			text.appendChild(el('span', 'tzc-ed-rcountry', item.cn ? ' \u2014 ' + item.cn : ''));
			var meta = el('span', 'tzc-ed-rmeta', item.z + ' \u00b7 ' + item.off + (item.pop ? ' \u00b7 ' + Number(item.pop).toLocaleString(self.root.getAttribute('data-locale') || undefined) + ' ' + self.i18n.inhabitants : ''));
			text.appendChild(meta);
			li.appendChild(text);
			li.appendChild(el('span', 'tzc-ed-rtype tzc-ed-rtype-' + item.type, item.type === 'city' ? self.i18n.type_city : self.i18n.type_zone));
			li.addEventListener('mousedown', function (e) {
				e.preventDefault();
				self.add(item);
			});
			li.addEventListener('mouseenter', function () {
				self.highlight(index);
			});
			self.results.appendChild(li);
		});
		this.highlight(0);
	};

	Picker.prototype.move = function (dir) {
		if (!this.items.length) {
			return;
		}
		var i = this.active + dir;
		if (i < 0) {
			i = this.items.length - 1;
		}
		if (i >= this.items.length) {
			i = 0;
		}
		this.highlight(i);
	};

	Picker.prototype.highlight = function (index) {
		var nodes = this.results.querySelectorAll('.tzc-ed-result');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].classList.toggle('tzc-ed-active', i === index);
		}
		this.active = index;
		if (nodes[index]) {
			nodes[index].scrollIntoView({ block: 'nearest' });
		}
	};

	Picker.prototype.hideResults = function () {
		this.results.hidden = true;
		this.items = [];
		this.active = -1;
	};

	Picker.prototype.add = function (item) {
		for (var i = 0; i < this.cities.length; i++) {
			if (this.cities[i].z === item.z && this.cities[i].n === item.n) {
				this.flash(i);
				this.hideResults();
				return;
			}
		}
		if (this.cities.length >= this.max) {
			tzcAlert(this.i18n.max.replace('%d', this.max));
			return;
		}
		this.cities.push({ n: item.n, cc: item.cc, cn: item.cn, z: item.z, lat: item.lat, lon: item.lon, m: 1, t: item.t, off: item.off });
		this.input.value = '';
		this.hideResults();
		this.renderList();
		this.flash(this.cities.length - 1);
		this.input.focus();
	};

	Picker.prototype.flash = function (index) {
		var row = this.list.children[index];
		if (row) {
			row.classList.remove('tzc-ed-flash');
			void row.offsetWidth;
			row.classList.add('tzc-ed-flash');
			row.scrollIntoView({ block: 'nearest' });
		}
	};

	Picker.prototype.moveCity = function (from, to) {
		if (to < 0 || to >= this.cities.length || from === to) {
			return;
		}
		var c = this.cities.splice(from, 1)[0];
		this.cities.splice(to, 0, c);
		this.renderList();
	};

	Picker.prototype.renderList = function () {
		var self = this;
		this.list.innerHTML = '';

		this.cities.forEach(function (city, index) {
			var li = el('li', 'tzc-ed-row');
			li.draggable = true;

			var handle = el('span', 'tzc-ed-handle', '\u2807');
			handle.title = self.i18n.drag;
			li.appendChild(handle);

			if (city.cc) {
				var img = el('img', 'tzc-ed-flag');
				img.src = self.flags + city.cc + '.svg';
				img.alt = city.cc;
				li.appendChild(img);
			}

			var name = el('input', 'inputbox tzc-ed-name');
			name.type = 'text';
			name.maxLength = 100;
			name.value = city.n;
			name.title = self.i18n.rename;
			name.addEventListener('input', function () {
				city.n = name.value;
				self.changed(false);
			});
			name.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
				}
			});
			li.appendChild(name);

			li.appendChild(el('span', 'tzc-ed-zone', (city.cn ? city.cn + ' \u00b7 ' : '') + city.z + (city.off ? ' \u00b7 ' + city.off : '')));

			if (self.mobileToggle) {
				var label = el('label', 'tzc-ed-mobile');
				var cb = el('input');
				cb.type = 'checkbox';
				cb.checked = city.m !== 0;
				cb.addEventListener('change', function () {
					city.m = cb.checked ? 1 : 0;
					self.changed(false);
				});
				label.appendChild(cb);
				label.appendChild(document.createTextNode(' ' + self.i18n.mobile));
				li.appendChild(label);
			}

			var up = iconButton('tzc-ed-up', '\u25B2', self.i18n.up);
			var down = iconButton('tzc-ed-down', '\u25BC', self.i18n.down);
			var del = iconButton('tzc-ed-del', '\u2715', self.i18n.remove);
			up.disabled = index === 0;
			down.disabled = index === self.cities.length - 1;
			up.addEventListener('click', function () { self.moveCity(index, index - 1); });
			down.addEventListener('click', function () { self.moveCity(index, index + 1); });
			del.addEventListener('click', function () {
				self.cities.splice(index, 1);
				self.renderList();
			});
			li.appendChild(up);
			li.appendChild(down);
			li.appendChild(del);

			// drag & drop
			li.addEventListener('dragstart', function (e) {
				if (document.activeElement === name) {
					e.preventDefault();
					return;
				}
				self.dragIndex = index;
				li.classList.add('tzc-ed-dragging');
				e.dataTransfer.effectAllowed = 'move';
				try { e.dataTransfer.setData('text/plain', String(index)); } catch (err) { /* IE */ }
			});
			li.addEventListener('dragend', function () {
				li.classList.remove('tzc-ed-dragging');
				self.dragIndex = null;
			});
			li.addEventListener('dragover', function (e) {
				if (self.dragIndex === null) {
					return;
				}
				e.preventDefault();
				li.classList.add('tzc-ed-over');
			});
			li.addEventListener('dragleave', function () {
				li.classList.remove('tzc-ed-over');
			});
			li.addEventListener('drop', function (e) {
				e.preventDefault();
				li.classList.remove('tzc-ed-over');
				if (self.dragIndex !== null) {
					self.moveCity(self.dragIndex, index);
				}
			});

			self.list.appendChild(li);
		});

		if (this.counter) {
			this.counter.textContent = this.cities.length + ' / ' + this.max;
		}
		this.root.classList.toggle('tzc-ed-empty', !this.cities.length);
		this.changed(true);
	};

	Picker.prototype.sync = function () {
		var out = this.cities.map(function (c) {
			return { n: c.n, cc: c.cc || '', z: c.z, lat: c.lat === null || c.lat === undefined ? '' : String(c.lat), lon: c.lon === null || c.lon === undefined ? '' : String(c.lon), m: c.m === 0 ? 0 : 1 };
		});
		this.field.value = JSON.stringify(out);
	};

	Picker.prototype.changed = function () {
		this.sync();
		var ev;
		try {
			ev = new CustomEvent('tzc:change', { detail: this.cities });
		} catch (e) {
			ev = document.createEvent('CustomEvent');
			ev.initCustomEvent('tzc:change', false, false, this.cities);
		}
		this.root.dispatchEvent(ev);
	};

	/* ------------------------------------------------------------------
	 * Live preview
	 * ------------------------------------------------------------------ */

	function Preview(root) {
		var self = this;
		this.root = root;
		this.target = root.querySelector('.tzc-root');
		this.base = json(root, 'data-payload', null);
		this.form = document.getElementById(root.getAttribute('data-form'));
		this.picker = document.querySelector(root.getAttribute('data-picker') || '.tzc-ed-picker');
		this.adminCities = this.base ? this.base.cities.slice() : [];
		this.mine = null;
		this.known = {};
		this.timer = null;

		if (!this.base || !window.TZC) {
			return;
		}

		this.remember(this.base.cities);
		this.remember(json(root, 'data-extra', []));

		if (this.form) {
			// typing inside the bar itself (its search box) must not rebuild the preview
			var onForm = function (e) {
				if (!self.target.contains(e.target)) {
					self.queue();
				}
			};
			this.form.addEventListener('change', onForm);
			this.form.addEventListener('input', onForm);
		}
		if (this.picker) {
			this.picker.addEventListener('tzc:change', function (e) {
				self.mine = e.detail;
				self.remember(e.detail);
				self.queue();
			});
			if (this.picker.tzcPicker) {
				this.mine = this.picker.tzcPicker.cities;
				this.remember(this.mine);
			}
		}
		this.render();
	}

	Preview.prototype.remember = function (cities) {
		for (var i = 0; i < (cities || []).length; i++) {
			if (cities[i].t) {
				this.known[cities[i].z] = { t: cities[i].t, cn: cities[i].cn, lat: cities[i].lat, lon: cities[i].lon, home: cities[i].home };
			}
		}
	};

	Preview.prototype.queue = function () {
		var self = this;
		clearTimeout(this.timer);
		this.timer = setTimeout(function () { self.render(); }, 120);
	};

	Preview.prototype.options = function () {
		var opt = JSON.parse(JSON.stringify(this.base.opt));
		var meta = { source: this.root.getAttribute('data-source') || 'admin', show: 1 };
		if (!this.form) {
			return { opt: opt, meta: meta };
		}

		var fields = this.form.querySelectorAll('[data-tzc-opt]');
		for (var i = 0; i < fields.length; i++) {
			var f = fields[i];
			var key = f.getAttribute('data-tzc-opt');
			var value;

			if (f.type === 'radio') {
				if (!f.checked) {
					continue;
				}
				value = f.value;
			} else if (f.type === 'checkbox') {
				value = f.checked ? '1' : '0';
			} else {
				value = f.value;
			}

			if (value === '') {
				continue; // "board default" in the UCP
			}

			if (key === 'source' || key === 'show') {
				meta[key] = key === 'show' ? parseInt(value, 10) : value;
				continue;
			}

			if (!(key in opt)) {
				continue;
			}
			opt[key] = (f.getAttribute('data-tzc-type') === 'int') ? parseInt(value, 10) || 0 : value;
		}

		return { opt: opt, meta: meta };
	};

	Preview.prototype.cities = function (meta) {
		var self = this;
		var mine = (this.mine || []).filter(function (c) { return self.known[c.z]; }).map(function (c) {
			var k = self.known[c.z];
			return { n: c.n, cc: c.cc, cn: c.cn || k.cn, z: c.z, lat: c.lat !== '' && c.lat !== undefined ? parseFloat(c.lat) : k.lat, lon: c.lon !== '' && c.lon !== undefined ? parseFloat(c.lon) : k.lon, m: c.m === 0 ? 0 : 1, home: k.home || 0, t: k.t };
		});

		var mode = this.root.getAttribute('data-mode');
		if (mode === 'admin-editor') {
			return mine;
		}
		if (meta.source === 'mine') {
			return mine.length ? mine : this.adminCities;
		}
		if (meta.source === 'both') {
			var seen = {};
			mine.forEach(function (c) { seen[c.z + '|' + c.n.toLowerCase()] = 1; });
			return mine.concat(this.adminCities.filter(function (c) { return !seen[c.z + '|' + c.n.toLowerCase()]; }));
		}
		return this.adminCities;
	};

	Preview.prototype.sort = function (cities, mode) {
		if (mode === 'manual') {
			return cities;
		}
		var now = Date.now();
		return cities.slice().sort(function (a, b) {
			if (mode === 'name') {
				return a.n.localeCompare(b.n);
			}
			var oa = window.TZC.entryAt(a.t, now)[1], ob = window.TZC.entryAt(b.t, now)[1];
			if (oa === ob) {
				return a.n.localeCompare(b.n);
			}
			return mode === 'west' ? oa - ob : ob - oa;
		});
	};

	Preview.prototype.render = function () {
		var o = this.options();
		var data = JSON.parse(JSON.stringify(this.base));
		data.opt = o.opt;
		data.cities = this.sort(this.cities(o.meta), o.opt.sort || 'manual');
		this.root.classList.toggle('tzc-ed-hidden', o.meta.show === 0);
		var empty = this.root.querySelector('.tzc-ed-preview-empty');
		if (empty) {
			empty.style.display = data.cities.length ? 'none' : '';
		}
		window.TZC.render(this.target, data);
	};

	/* ------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */

	function boot() {
		bindConfirms();

		var pickers = document.querySelectorAll('.tzc-ed-picker');
		for (var i = 0; i < pickers.length; i++) {
			pickers[i].tzcPicker = new Picker(pickers[i]);
		}
		var previews = document.querySelectorAll('.tzc-ed-preview');
		for (var j = 0; j < previews.length; j++) {
			previews[j].tzcPreview = new Preview(previews[j]);
		}

		// "board default" selects show the value they inherit
		var dependants = document.querySelectorAll('[data-tzc-toggle]');
		for (var k = 0; k < dependants.length; k++) {
			(function (node) {
				var sel = document.querySelectorAll('[name="' + node.getAttribute('data-tzc-toggle') + '"]');
				var want = node.getAttribute('data-tzc-when').split(',');
				var update = function () {
					var value = '';
					for (var s = 0; s < sel.length; s++) {
						if ((sel[s].type !== 'radio' && sel[s].type !== 'checkbox') || sel[s].checked) {
							value = sel[s].value;
						}
					}
					node.style.display = want.indexOf(value) !== -1 ? '' : 'none';
				};
				for (var s = 0; s < sel.length; s++) {
					sel[s].addEventListener('change', update);
				}
				update();
			})(dependants[k]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
