/**
 * Timezone Clock - ACP: update jobs with progress bar, zone browser, check-up
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 */
(function (window, document) {
	'use strict';

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function $$(sel, ctx) {
		return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
	}

	function parse(node, attr, fallback) {
		try {
			return JSON.parse(node.getAttribute(attr)) || fallback;
		} catch (e) {
			return fallback;
		}
	}

	function esc(text) {
		var d = document.createElement('div');
		d.textContent = text == null ? '' : String(text);
		return d.innerHTML;
	}

	function duration(seconds, job) {
		seconds = Math.max(0, Math.round(seconds));
		var m = Math.floor(seconds / 60), s = seconds % 60;
		var sec = job.getAttribute('data-l-sec').replace('%d', s);
		return m ? job.getAttribute('data-l-min').replace('%d', m) + ' ' + sec : sec;
	}

	// confirmations (data-tzc-confirm) are handled by tzc_editor.js with the phpBB box

	/* ------------------------------------------------------------------
	 * Update job with real progress bar
	 * ------------------------------------------------------------------ */

	var job = $('#tzc-job');
	if (job) {
		var url = job.getAttribute('data-url');
		var hash = job.getAttribute('data-hash');
		var box = $('.tzc-progress', job);
		var fill = $('.tzc-progress-fill', job);
		var track = $('.tzc-progress-track', job);
		var pct = $('.tzc-progress-pct', job);
		var text = $('.tzc-progress-text', job);
		var time = $('.tzc-progress-time', job);
		var result = $('.tzc-job-result', job);
		var buttons = $$('.tzc-job-btn', job);
		var cancelBtn = $('.tzc-job-cancel', job);
		var running = false, cancelled = false, startedAt = 0, failures = 0;

		var call = function (action, extra) {
			var body = 'action=' + action + '&hash=' + encodeURIComponent(hash) + (extra || '');
			return fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + body, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			}).then(function (r) {
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
				return r.json();
			});
		};

		var setBusy = function (busy) {
			running = busy;
			buttons.forEach(function (b) {
				if (busy) {
					b.setAttribute('data-was-disabled', b.disabled ? '1' : '0');
					b.disabled = true;
				} else {
					b.disabled = b.getAttribute('data-was-disabled') === '1';
				}
			});
			job.classList.toggle('tzc-job-running', busy);
		};

		var render = function (state) {
			var p = Math.max(0, Math.min(100, state.percent || 0));
			box.hidden = false;
			fill.style.width = p + '%';
			pct.textContent = p.toFixed(p < 10 && p > 0 ? 1 : 0) + '%';
			track.setAttribute('aria-valuenow', Math.round(p));
			if (state.text) {
				text.innerHTML = state.text;
			}
			if (startedAt && p > 1 && p < 100) {
				var elapsed = (Date.now() - startedAt) / 1000;
				time.textContent = job.getAttribute('data-l-time')
					.replace('%1$s', duration(elapsed, job))
					.replace('%2$s', duration(elapsed / p * (100 - p), job));
			}
		};

		var finish = function (state) {
			setBusy(false);
			cancelBtn.hidden = true;
			result.hidden = false;
			job.classList.remove('tzc-job-ok', 'tzc-job-ko');

			if (state.error) {
				job.classList.add('tzc-job-ko');
				fill.classList.add('tzc-progress-error');
				result.innerHTML = '<strong>' + esc(job.getAttribute('data-l-failed')) + '</strong> ' + (state.error_text || esc(state.error));
				return;
			}

			fill.classList.remove('tzc-progress-error');
			job.classList.add('tzc-job-ok');
			fill.style.width = '100%';
			pct.textContent = '100%';
			var html = '<strong>' + esc(job.getAttribute('data-l-done')) + '</strong><ul>';
			(state.results_text || []).forEach(function (line) {
				html += '<li>' + line + '</li>';
			});
			result.innerHTML = html + '</ul>';
		};

		var loop = function () {
			if (cancelled) {
				return;
			}
			call('job_step').then(function (state) {
				failures = 0;
				if (state.error === 'FORM_INVALID') {
					finish(state);
					return;
				}
				render(state);
				if (state.done || state.error || !state.active) {
					finish(state);
				} else {
					// a busy lock means another request (cron) is working: wait a little
					setTimeout(loop, state.busy ? 1500 : 60);
				}
			}).catch(function () {
				// temporary network/server problem: retry a few times
				if (++failures <= 5) {
					text.textContent = job.getAttribute('data-l-network');
					setTimeout(loop, 2000 * failures);
				} else {
					finish({ error: 'network', error_text: esc(job.getAttribute('data-l-network')) });
				}
			});
		};

		var start = function (type) {
			if (running) {
				return;
			}
			if (type !== 'tzdata') {
				window.TZC.confirm(job.getAttribute('data-l-confirm-cities'), function (ok) {
					if (ok) {
						run(type);
					}
				});
				return;
			}
			run(type);
		};

		var run = function (type) {
			cancelled = false;
			failures = 0;
			startedAt = Date.now();
			result.hidden = true;
			fill.classList.remove('tzc-progress-error');
			fill.style.width = '0%';
			cancelBtn.hidden = false;
			time.textContent = '';
			setBusy(true);
			var force = $('#tzc-force').checked ? 1 : 0;

			call('job_start', '&type=' + type + '&force=' + force).then(function (state) {
				if (state.error) {
					finish(state);
					return;
				}
				render(state);
				loop();
			}).catch(function () {
				finish({ error: 'network', error_text: esc(job.getAttribute('data-l-network')) });
			});
		};

		buttons.forEach(function (b) {
			b.addEventListener('click', function () {
				start(b.getAttribute('data-job'));
			});
		});

		cancelBtn.addEventListener('click', function () {
			cancelled = true;
			call('job_cancel').then(function (state) {
				setBusy(false);
				text.textContent = state.text || '';
				cancelBtn.hidden = true;
			});
		});

		// A job is already running (cron, or a page reloaded during an update): resume it
		var initial = parse(job, 'data-state', {});
		if (initial.active) {
			startedAt = Date.now();
			setBusy(true);
			render(initial);
			text.innerHTML = esc(job.getAttribute('data-l-resume')) + ' ' + (initial.text || '');
			loop();
		}

		window.addEventListener('beforeunload', function (e) {
			if (running) {
				e.preventDefault();
				e.returnValue = '';
			}
		});
	}

	/* ------------------------------------------------------------------
	 * Zone browser filter
	 * ------------------------------------------------------------------ */

	var filter = $('.tzc-zone-filter');
	if (filter) {
		var rows = $$('.tzc-zones tbody tr');
		var dstOnly = $('.tzc-zone-dst');
		var counter = $('.tzc-zone-count');
		var apply = function () {
			var q = filter.value.trim().toLowerCase();
			var shown = 0;
			rows.forEach(function (row) {
				var ok = (!q || row.textContent.toLowerCase().indexOf(q) !== -1) && (!dstOnly.checked || row.getAttribute('data-dst') === '1');
				row.style.display = ok ? '' : 'none';
				shown += ok ? 1 : 0;
			});
			counter.textContent = shown + ' / ' + rows.length;
		};
		filter.addEventListener('input', apply);
		dstOnly.addEventListener('change', apply);
		apply();
	}


	/* ------------------------------------------------------------------
	 * Zone browser: "Add to the bar" on every row
	 * ------------------------------------------------------------------ */

	var zoneTable = $('#tzc-zone-table');
	if (zoneTable) {
		var zl = function (key) { return zoneTable.getAttribute('data-l-' + key) || ''; };

		// cell content: "In the bar" + one "Remove" button per city, or "Add to the bar"
		var renderCell = function (box, cities) {
			var zone = box.getAttribute('data-zone');
			var html = '';
			if (cities && cities.length) {
				html = '<span class="tzc-inbar"><i class="icon fa-check fa-fw" aria-hidden="true"></i> ' + esc(zl('in-bar')) + '</span>';
				cities.forEach(function (c) {
					html += ' <button type="button" class="button2 tzc-removecity" data-city="' + c.id + '" data-name="' + esc(c.name) + '">' +
						'<i class="icon fa-times fa-fw" aria-hidden="true"></i> ' + esc(zl('remove').replace('%s', c.name)) + '</button>';
				});
			} else {
				html = '<button type="button" class="button2 tzc-addzone" data-zone="' + esc(zone) + '"><i class="icon fa-plus fa-fw" aria-hidden="true"></i> ' + esc(zl('add')) + '</button>';
			}
			box.innerHTML = html;
			var row = box.closest('tr');
			if (row) {
				row.classList.toggle('tzc-row-added', !!(cities && cities.length));
			}
		};

		var send = function (params, btn, busyText) {
			var box = btn.closest('.tzc-barcell');
			var html = btn.innerHTML;
			btn.disabled = true;
			btn.textContent = busyText;

			var url = zoneTable.getAttribute('data-url');
			var body = params + '&hash=' + encodeURIComponent(zoneTable.getAttribute('data-hash'));
			fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + body, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			}).then(function (r) { return r.json(); }).then(function (data) {
				if (data.ok || data.error === 'exists') {
					renderCell(box, data.cities || []);
					if (typeof data.count === 'number') {
						$$('.tzc-bar-count').forEach(function (n) { n.textContent = data.count; });
					}
					if (data.error) {
						window.TZC.alert(data.text);
					}
					return;
				}
				if (data.error === 'missing') {
					// already removed elsewhere: just drop this button
					btn.parentNode.removeChild(btn);
					if (!box.querySelector('.tzc-removecity')) {
						renderCell(box, []);
					}
					window.TZC.alert(data.text);
					return;
				}
				btn.disabled = false;
				btn.innerHTML = html;
				window.TZC.alert(data.text || zl('error'));
			}).catch(function () {
				btn.disabled = false;
				btn.innerHTML = html;
				window.TZC.alert(zl('error'));
			});
		};

		zoneTable.addEventListener('click', function (e) {
			var add = e.target.closest ? e.target.closest('.tzc-addzone') : null;
			var remove = e.target.closest ? e.target.closest('.tzc-removecity') : null;

			if (add && !add.disabled) {
				send('action=add_city&zone=' + encodeURIComponent(add.getAttribute('data-zone')), add, zl('adding'));
			} else if (remove && !remove.disabled) {
				// only the city of this button is removed
				window.TZC.confirm(zl('remove-confirm').replace('%s', remove.getAttribute('data-name')), function (ok) {
					if (ok) {
						send('action=remove_city&city=' + encodeURIComponent(remove.getAttribute('data-city')), remove, zl('removing'));
					}
				});
			}
		});
	}

	/* ------------------------------------------------------------------
	 * Check-up: one AJAX request per step, progress bar with percentage
	 * ------------------------------------------------------------------ */

	var chk = $('#tzc-checkup');
	if (chk) {
		var chkSteps = parse(chk, 'data-steps', {});
		var chkUrl = chk.getAttribute('data-url');
		var chkHash = chk.getAttribute('data-hash');
		var chkBox = $('.tzc-chk-progress', chk);
		var chkFill = $('.tzc-progress-fill', chkBox);
		var chkTrack = $('.tzc-progress-track', chkBox);
		var chkPct = $('.tzc-progress-pct', chkBox);
		var chkText = $('.tzc-progress-text', chkBox);
		var chkResults = $('.tzc-chk-results', chk);
		var chkSummary = $('.tzc-summary', chk);
		var chkButtons = $$('.tzc-chk-run', chk);
		var chkRunning = false;
		var icons = { ok: 'fa-check', warn: 'fa-exclamation', error: 'fa-times', info: 'fa-info' };
		var summaryIcons = { ok: 'fa-check-circle', warn: 'fa-exclamation-triangle', error: 'fa-times-circle' };

		var setPct = function (p) {
			chkFill.style.width = p + '%';
			chkPct.textContent = Math.round(p) + '%';
			chkTrack.setAttribute('aria-valuenow', Math.round(p));
		};

		var groupBox = function (group, title) {
			var box = chkResults.querySelector('[data-group="' + group + '"]');
			if (!box) {
				box = document.createElement('fieldset');
				box.className = 'tzc-chk-group';
				box.setAttribute('data-group', group);
				box.innerHTML = '<legend>' + esc(title) + '</legend><ul class="tzc-chk-list"></ul>';
				chkResults.appendChild(box);
			}
			return box.querySelector('ul');
		};

		var runCheckup = function (mode) {
			if (chkRunning) {
				return;
			}
			var steps = chkSteps[mode] || [];
			var counts = { ok: 0, warn: 0, error: 0, info: 0 };
			var index = 0, failures = 0;

			chkRunning = true;
			chkButtons.forEach(function (b) { b.disabled = true; });
			chkResults.innerHTML = '';
			chkBox.hidden = false;
			chkFill.classList.remove('tzc-progress-error');
			chk.classList.remove('tzc-job-ok', 'tzc-job-ko');
			chk.classList.add('tzc-job-running');
			chkSummary.className = 'tzc-summary tzc-summary-running';
			['ok', 'warn', 'error', 'info'].forEach(function (k) { $('.tzc-n-' + k, chk).textContent = '0'; });
			setPct(0);

			var finish = function (failed) {
				chkRunning = false;
				chkButtons.forEach(function (b) { b.disabled = false; });
				chk.classList.remove('tzc-job-running');
				var status = counts.error ? 'error' : (counts.warn ? 'warn' : 'ok');
				if (failed) {
					chkFill.classList.add('tzc-progress-error');
					chkText.textContent = chk.getAttribute('data-l-failed');
					status = 'error';
				} else {
					setPct(100);
					chkText.textContent = chk.getAttribute('data-l-done');
					chk.classList.add('tzc-job-ok');
				}
				chkSummary.className = 'tzc-summary tzc-summary-' + status;
				$('.tzc-summary-icon', chk).innerHTML = '<i class="icon ' + summaryIcons[status] + '" aria-hidden="true"></i>';
				$('.tzc-sum-text', chk).textContent = chk.getAttribute('data-l-sum-' + status);
			};

			var next = function () {
				if (index >= steps.length) {
					finish(false);
					return;
				}
				var step = steps[index];
				chkText.textContent = chk.getAttribute('data-l-running')
					.replace('%1$s', step.label).replace('%2$d', index + 1).replace('%3$d', steps.length);

				var body = 'action=checkup_step&hash=' + encodeURIComponent(chkHash) + '&step=' + encodeURIComponent(step.id);
				fetch(chkUrl + (chkUrl.indexOf('?') === -1 ? '?' : '&') + body, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body
				}).then(function (r) {
					if (!r.ok) {
						throw new Error('HTTP ' + r.status);
					}
					return r.json();
				}).then(function (data) {
					failures = 0;
					if (data.error) {
						chkText.textContent = data.text || data.error;
						finish(true);
						return;
					}
					var list = groupBox(data.group, data.group_title);
					(data.items || []).forEach(function (item) {
						counts[item.status]++;
						var li = document.createElement('li');
						li.className = 'tzc-chk tzc-chk-' + item.status;
						li.innerHTML = '<span class="tzc-chk-icon"><i class="icon ' + icons[item.status] + ' fa-fw" aria-hidden="true"></i></span>' +
							'<span class="tzc-chk-title">' + esc(item.title) + '</span><span class="tzc-chk-detail">' + esc(item.detail) + '</span>';
						list.appendChild(li);
					});
					['ok', 'warn', 'error', 'info'].forEach(function (k) { $('.tzc-n-' + k, chk).textContent = counts[k]; });
					$('.tzc-sum-time', chk).textContent = data.run_at || '';
					index++;
					setPct(index / steps.length * 100);
					next();
				}).catch(function () {
					if (++failures <= 3) {
						chkText.textContent = chk.getAttribute('data-l-network');
						setTimeout(next, 1500 * failures);
					} else {
						finish(true);
					}
				});
			};

			next();
		};

		chkButtons.forEach(function (b) {
			b.addEventListener('click', function () { runCheckup(b.getAttribute('data-mode')); });
		});

		// first run when the page opens
		runCheckup(chk.getAttribute('data-start') || 'full');
	}

	/* ------------------------------------------------------------------
	 * Check-up: browser test
	 * ------------------------------------------------------------------ */

	var test = $('#tzc-browser-test');
	if (test) {
		var cities = parse(test, 'data-cities', []);
		var add = function (status, title, detail) {
			var li = document.createElement('li');
			li.className = 'tzc-chk tzc-chk-' + status;
			var icon = { ok: 'fa-check', warn: 'fa-exclamation', error: 'fa-times', info: 'fa-info' }[status];
			li.innerHTML = '<span class="tzc-chk-icon"><i class="icon ' + icon + ' fa-fw" aria-hidden="true"></i></span>' +
				'<span class="tzc-chk-title">' + esc(title) + '</span><span class="tzc-chk-detail">' + esc(detail) + '</span>';
			test.appendChild(li);
		};

		var browserOffset = function (zone) {
			try {
				var parts = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'longOffset' }).formatToParts(new Date());
				var name = '';
				parts.forEach(function (p) {
					if (p.type === 'timeZoneName') {
						name = p.value;
					}
				});
				if (name === 'GMT') {
					return 0;
				}
				var m = /GMT([+\-\u2212])(\d{1,2}):?(\d{2})?/.exec(name);
				if (!m) {
					return null;
				}
				var v = parseInt(m[2], 10) * 60 + parseInt(m[3] || '0', 10);
				return m[1] === '+' ? v : -v;
			} catch (e) {
				return null;
			}
		};

		var serverMs = parseFloat(test.getAttribute('data-server-time'));
		var skew = Math.round((Date.now() - serverMs) / 1000);
		var skewOk = Math.abs(skew) <= 120;
		add(skewOk ? 'ok' : 'warn', test.getAttribute('data-l-clock-title'), (skewOk ? test.getAttribute('data-l-clock') : test.getAttribute('data-l-clock-ko')).replace('%d', skew));

		cities.forEach(function (c) {
			var b = browserOffset(c.z);
			if (b === null) {
				add('info', c.n, test.getAttribute('data-l-na').replace('%s', c.z));
			} else if (b === c.off) {
				add('ok', c.n, test.getAttribute('data-l-ok').replace('%s', window.TZC ? window.TZC.fmtOffset(c.off) : c.off));
			} else {
				add('warn', c.n, test.getAttribute('data-l-ko')
					.replace('%1$s', window.TZC ? window.TZC.fmtOffset(c.off) : c.off)
					.replace('%2$s', window.TZC ? window.TZC.fmtOffset(b) : b));
			}
		});
	}
})(window, document);
