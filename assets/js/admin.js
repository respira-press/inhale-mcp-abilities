/*!
 * Inhale: MCP Abilities admin behavior.
 * Vanilla JS, no jQuery, no build step.
 */
(function () {
	'use strict';

	var wrap = document.querySelector('.inhale-wrap');
	if (!wrap) {
		return;
	}

	/* ─── Theme toggle ────────────────────────────────────────────
	 * Precedence: localStorage > wp-admin midnight/ectoplasm/modern
	 * color scheme > prefers-color-scheme > light.
	 */
	(function initTheme() {
		var btn = document.getElementById('inhaleThemeToggle');
		var STORE = 'inhale_mcp_abilities_theme';
		var saved = null;
		try { saved = localStorage.getItem(STORE); } catch (e) { saved = null; }

		var initial = 'light';
		if (saved === 'dark' || saved === 'light') {
			initial = saved;
		} else if (document.body && /admin-color-(midnight|ectoplasm|coffee|ocean)/.test(document.body.className)) {
			initial = 'dark';
		} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
			initial = 'dark';
		}
		wrap.setAttribute('data-theme', initial);
		syncTooltip(initial);

		if (btn) {
			btn.addEventListener('click', function () {
				var next = wrap.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
				wrap.setAttribute('data-theme', next);
				try { localStorage.setItem(STORE, next); } catch (e) { /* swallow */ }
				syncTooltip(next);
			});
		}

		function syncTooltip(cur) {
			if (!btn) { return; }
			btn.setAttribute('data-tooltip', cur === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
			btn.setAttribute('aria-pressed', cur === 'dark' ? 'true' : 'false');
		}
	})();

	/* ─── Abilities table state + interaction ─────────────────── */
	(function initTable() {
		var tbody = document.getElementById('inhaleAbilitiesBody');
		if (!tbody) { return; }
		var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
		var dataRows = allRows.filter(function (tr) { return !tr.classList.contains('empty-state'); });
		var originalOrder = dataRows.slice();

		var searchInput = document.getElementById('inhale-ability-search');
		var subsubsubLinks = document.querySelectorAll('.inhale-wrap .subsubsub a[data-view]');
		var sortHeaders = document.querySelectorAll('.inhale-wrap th.sortable');
		var filterBtn = document.getElementById('inhaleSourceFilterBtn');
		var filterPop = document.getElementById('inhaleSourceFilterPop');
		var filterCountBadge = document.getElementById('inhaleSourceFilterCount');
		var filterClearBtn = document.getElementById('inhaleSourceFilterClear');
		var filterCheckboxes = filterPop ? filterPop.querySelectorAll('input[type="checkbox"]') : [];
		var resetLink = document.getElementById('inhaleResetFilters');
		var visibleCounts = document.querySelectorAll('.inhale-wrap .inhale-visible-count');
		var selectAllBoxes = document.querySelectorAll('.inhale-wrap .inhale-select-all');
		var summaries = document.querySelectorAll('.inhale-wrap .inhale-inhaled-count');
		var form = document.getElementById('inhaleAbilitiesForm');

		var state = {
			search: '',
			view: 'all',
			sources: {},
			sort: { col: null, dir: null }
		};

		var meta = new WeakMap();
		dataRows.forEach(function (tr) {
			var nameEl = tr.querySelector('.ability-name');
			var descEl = tr.querySelector('.ability-desc');
			var input = tr.querySelector('input[type="checkbox"]');
			meta.set(tr, {
				ability: nameEl ? nameEl.textContent.trim() : '',
				source: tr.getAttribute('data-source') || '',
				desc: descEl ? descEl.textContent.trim() : '',
				annot: tr.getAttribute('data-annot') || '',
				managed: tr.getAttribute('data-managed') === 'true',
				input: input
			});
		});

		function rowMatches(tr) {
			var m = meta.get(tr);
			if (!m) { return false; }

			if (state.search) {
				var hay = (m.ability + ' ' + m.source + ' ' + m.desc).toLowerCase();
				if (hay.indexOf(state.search) === -1) { return false; }
			}

			var inhaled = m.managed || (m.input && m.input.checked);
			switch (state.view) {
				case 'inhaled':     if (!inhaled) { return false; } break;
				case 'read-only':   if (m.annot.indexOf('read-only') === -1) { return false; } break;
				case 'destructive': if (m.annot.indexOf('destructive') === -1) { return false; } break;
				case 'unannotated': if (m.annot.trim() !== '') { return false; } break;
				default: break;
			}

			var sourceKeys = Object.keys(state.sources);
			if (sourceKeys.length > 0 && !state.sources[m.source]) { return false; }

			return true;
		}

		function rowSortValue(tr, col) {
			var m = meta.get(tr);
			if (!m) { return ''; }
			if (col === 'status') {
				if (m.managed) { return '1-managed'; }
				return (m.input && m.input.checked) ? '0-inhaled' : '2-not';
			}
			return (m[col] || '').toLowerCase();
		}

		function apply() {
			var ordered;
			if (state.sort.col) {
				ordered = originalOrder.slice().sort(function (a, b) {
					var av = rowSortValue(a, state.sort.col);
					var bv = rowSortValue(b, state.sort.col);
					if (av < bv) { return state.sort.dir === 'asc' ? -1 : 1; }
					if (av > bv) { return state.sort.dir === 'asc' ?  1 : -1; }
					return 0;
				});
			} else {
				ordered = originalOrder.slice();
			}
			ordered.forEach(function (tr) { tbody.appendChild(tr); });

			var visible = 0;
			ordered.forEach(function (tr) {
				var show = rowMatches(tr);
				tr.classList.toggle('is-hidden', !show);
				if (show) { visible++; }
			});

			var empty = tbody.querySelector('tr.empty-state.injected');
			if (visible === 0 && dataRows.length > 0) {
				if (!empty) {
					empty = document.createElement('tr');
					empty.className = 'empty-state injected';
					var td = document.createElement('td');
					td.colSpan = 6;
					td.textContent = 'No abilities match the current filters.';
					empty.appendChild(td);
					tbody.appendChild(empty);
				}
			} else if (empty) {
				empty.parentNode.removeChild(empty);
			}

			visibleCounts.forEach(function (el) { el.textContent = String(visible); });

			sortHeaders.forEach(function (th) {
				var col = th.getAttribute('data-sort');
				if (state.sort.col === col) {
					th.setAttribute('aria-sort', state.sort.dir === 'asc' ? 'ascending' : 'descending');
				} else {
					th.setAttribute('aria-sort', 'none');
				}
			});

			var sourceCount = Object.keys(state.sources).length;
			if (filterBtn && filterCountBadge) {
				if (sourceCount > 0) {
					filterBtn.classList.add('active');
					filterCountBadge.style.display = 'block';
					filterCountBadge.textContent = String(sourceCount);
				} else {
					filterBtn.classList.remove('active');
					filterCountBadge.style.display = 'none';
				}
			}

			subsubsubLinks.forEach(function (a) {
				var isCurrent = a.getAttribute('data-view') === state.view;
				a.classList.toggle('current', isCurrent);
				if (isCurrent) { a.setAttribute('aria-current', 'page'); }
				else { a.removeAttribute('aria-current'); }
			});

			var inhaledCount = dataRows.filter(function (tr) {
				var m = meta.get(tr);
				return m && (m.managed || (m.input && m.input.checked));
			}).length;
			var inhaledLink = document.querySelector('.inhale-wrap .subsubsub a[data-view="inhaled"] .count');
			if (inhaledLink) { inhaledLink.textContent = '(' + inhaledCount + ')'; }

			var anyFilter = state.search || state.view !== 'all' || sourceCount > 0 || state.sort.col !== null;
			if (resetLink) { resetLink.classList.toggle('show', !!anyFilter); }

			var inhaledNow = String(dataRows.filter(function (tr) {
				var m = meta.get(tr);
				return m && !m.managed && m.input && m.input.checked;
			}).length);
			summaries.forEach(function (el) { el.textContent = inhaledNow; });
		}

		function resetAll() {
			state.search = '';
			state.view = 'all';
			state.sources = {};
			state.sort = { col: null, dir: null };
			if (searchInput) { searchInput.value = ''; }
			filterCheckboxes.forEach(function (c) { c.checked = false; });
			apply();
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				state.search = (searchInput.value || '').trim().toLowerCase();
				apply();
			});
		}

		subsubsubLinks.forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				state.view = a.getAttribute('data-view') || 'all';
				apply();
			});
		});

		sortHeaders.forEach(function (th) {
			th.addEventListener('click', function () {
				var col = th.getAttribute('data-sort');
				if (state.sort.col !== col) {
					state.sort = { col: col, dir: 'asc' };
				} else if (state.sort.dir === 'asc') {
					state.sort = { col: col, dir: 'desc' };
				} else {
					state.sort = { col: null, dir: null };
				}
				apply();
			});
			th.tabIndex = 0;
			th.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					th.click();
				}
			});
		});

		if (filterBtn && filterPop) {
			filterBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = filterPop.classList.toggle('open');
				filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
			filterPop.addEventListener('click', function (e) { e.stopPropagation(); });
			document.addEventListener('click', function () {
				if (filterPop.classList.contains('open')) {
					filterPop.classList.remove('open');
					filterBtn.setAttribute('aria-expanded', 'false');
				}
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && filterPop.classList.contains('open')) {
					filterPop.classList.remove('open');
					filterBtn.setAttribute('aria-expanded', 'false');
					filterBtn.focus();
				}
			});
		}
		filterCheckboxes.forEach(function (cb) {
			cb.addEventListener('change', function () {
				var v = cb.value;
				if (cb.checked) { state.sources[v] = true; }
				else { delete state.sources[v]; }
				apply();
			});
		});
		if (filterClearBtn) {
			filterClearBtn.addEventListener('click', function () {
				state.sources = {};
				filterCheckboxes.forEach(function (c) { c.checked = false; });
				apply();
			});
		}
		if (resetLink) { resetLink.addEventListener('click', resetAll); }

		// On checkbox change: Status pill, counts and filters all reflect
		// the staged state (one consistent model). The only signal that
		// something hasn't persisted is the amber row edge + the
		// "Unsaved changes" indicator next to Save.
		dataRows.forEach(function (tr) {
			var m = meta.get(tr);
			if (!m || m.managed || !m.input) { return; }
			m.input.addEventListener('change', function () {
				if (m.input.checked && m.annot.indexOf('destructive') !== -1) {
					var ok = window.confirm('This ability can modify content on your site. Inhale it?');
					if (!ok) { m.input.checked = false; }
				}
				var statusCell = tr.querySelector('.col-status');
				if (statusCell) {
					if (m.input.checked) {
						statusCell.innerHTML = '<span class="status-pill inhaled">Inhaled</span>';
					} else {
						statusCell.innerHTML = '<span class="status-empty" aria-label="Not inhaled">—</span>';
					}
				}
				updateRowPending(tr);
				apply();
			});
		});

		function updateRowPending(tr) {
			var m = meta.get(tr);
			if (!m || !m.input) { return; }
			var saved = tr.getAttribute('data-saved') === 'true';
			var staged = !!m.input.checked;
			tr.classList.toggle('inhale-pending', saved !== staged);
		}

		selectAllBoxes.forEach(function (box) {
			box.addEventListener('change', function () {
				dataRows.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || m.managed || tr.classList.contains('is-hidden')) { return; }
					if (m.input.checked !== box.checked) {
						m.input.checked = box.checked;
						m.input.dispatchEvent(new Event('change'));
					}
				});
			});
		});

		var quickActionButtons = document.querySelectorAll('.inhale-wrap .inhale-quickaction');
		quickActionButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-action');
				if (action !== 'inhale' && action !== 'exhale') { return; }

				var targetChecked = (action === 'inhale');

				// Operate on every filter-matching, non-managed row whose
				// state needs to flip. Pagination is ignored (acts across
				// all pages of the filtered set).
				var toFlip = [];
				dataRows.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || m.managed) { return; }
					if (!rowMatches(tr)) { return; }
					if (m.input.checked === targetChecked) { return; }
					toFlip.push(tr);
				});

				if (toFlip.length === 0) { return; }

				if (targetChecked) {
					var destructiveCount = 0;
					toFlip.forEach(function (tr) {
						var m = meta.get(tr);
						if (m && m.annot.indexOf('destructive') !== -1) {
							destructiveCount++;
						}
					});
					if (destructiveCount > 0) {
						var msg = destructiveCount === 1
							? 'One of the filtered abilities can modify content on your site. Inhale it?'
							: (destructiveCount + ' of the filtered abilities can modify content on your site. Inhale them?');
						if (!window.confirm(msg)) { return; }
					}
				}

				toFlip.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || !m.input) { return; }
					m.input.checked = targetChecked;
					var statusCell = tr.querySelector('.col-status');
					if (statusCell) {
						if (targetChecked) {
							statusCell.innerHTML = '<span class="status-pill inhaled">Inhaled</span>';
						} else {
							statusCell.innerHTML = '<span class="status-empty" aria-label="Not inhaled">—</span>';
						}
					}
					updateRowPending(tr);
				});
				apply();
			});
		});

		/* ─── Pagination ───────────────────────────────────────────
		 * Client-side: every row stays in the DOM (so all checkbox
		 * state submits with the form). We only hide rows outside
		 * the current page. Page changes never touch row state. */
		var perPage = 50;
		var currentPage = 1;
		var perPageSelects = document.querySelectorAll('.inhale-wrap .inhale-pg-perpage');
		var currentPageInputs = document.querySelectorAll('.inhale-wrap .inhale-pg-current');
		var totalPagesEls = document.querySelectorAll('.inhale-wrap .inhale-pg-total');
		var firstButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-first');
		var prevButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-prev');
		var nextButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-next');
		var lastButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-last');

		function getFilteredRows() {
			return dataRows.filter(rowMatches);
		}
		function getTotalPages() {
			if (perPage === 0) { return 1; }
			var filtered = getFilteredRows();
			return Math.max(1, Math.ceil(filtered.length / perPage));
		}
		function clampPage() {
			var t = getTotalPages();
			if (currentPage > t) { currentPage = t; }
			if (currentPage < 1) { currentPage = 1; }
		}
		function applyPagination() {
			clampPage();
			var filtered = getFilteredRows();
			var totalPages = getTotalPages();

			// Mark pagination visibility on filtered rows; non-filtered rows
			// keep is-hidden from rowMatches/apply() and are unaffected.
			if (perPage === 0) {
				filtered.forEach(function (tr) { tr.classList.remove('inhale-pg-hidden'); });
			} else {
				var start = (currentPage - 1) * perPage;
				var end = start + perPage;
				filtered.forEach(function (tr, idx) {
					tr.classList.toggle('inhale-pg-hidden', idx < start || idx >= end);
				});
			}

			// Sync controls
			currentPageInputs.forEach(function (input) { input.value = String(currentPage); });
			totalPagesEls.forEach(function (el) { el.textContent = String(totalPages); });
			perPageSelects.forEach(function (sel) { sel.value = String(perPage); });
			firstButtons.forEach(function (b) { b.disabled = currentPage <= 1; });
			prevButtons.forEach(function (b) { b.disabled = currentPage <= 1; });
			nextButtons.forEach(function (b) { b.disabled = currentPage >= totalPages; });
			lastButtons.forEach(function (b) { b.disabled = currentPage >= totalPages; });
		}

		// Wrap apply() so pagination always runs after filter/sort.
		var coreApply = apply;
		apply = function () {
			coreApply();
			applyPagination();
			updateDirty();
		};

		firstButtons.forEach(function (b) { b.addEventListener('click', function () { currentPage = 1; applyPagination(); }); });
		prevButtons.forEach(function (b) { b.addEventListener('click', function () { currentPage = Math.max(1, currentPage - 1); applyPagination(); }); });
		nextButtons.forEach(function (b) { b.addEventListener('click', function () { currentPage = Math.min(getTotalPages(), currentPage + 1); applyPagination(); }); });
		lastButtons.forEach(function (b) { b.addEventListener('click', function () { currentPage = getTotalPages(); applyPagination(); }); });
		currentPageInputs.forEach(function (input) {
			input.addEventListener('change', function () {
				var n = parseInt(input.value, 10);
				if (isNaN(n) || n < 1) { n = 1; }
				currentPage = n;
				applyPagination();
			});
		});
		perPageSelects.forEach(function (sel) {
			sel.addEventListener('change', function () {
				perPage = parseInt(sel.value, 10) || 0;
				currentPage = 1;
				applyPagination();
			});
		});

		// When filters change, reset to page 1.
		var resetPageOn = function () { currentPage = 1; };
		if (searchInput) { searchInput.addEventListener('input', resetPageOn); }
		subsubsubLinks.forEach(function (a) { a.addEventListener('click', resetPageOn); });
		filterCheckboxes.forEach(function (cb) { cb.addEventListener('change', resetPageOn); });
		if (filterClearBtn) { filterClearBtn.addEventListener('click', resetPageOn); }
		if (resetLink) { resetLink.addEventListener('click', resetPageOn); }

		/* ─── Unsaved-changes indicator ───────────────────────────
		 * Snapshot the saved state on first paint; flag dirty whenever
		 * the current set of checked values diverges. */
		var dirtyIndicators = document.querySelectorAll('.inhale-wrap .inhale-dirty-indicator');
		var savedSnapshot = (function () {
			var s = {};
			dataRows.forEach(function (tr) {
				var m = meta.get(tr);
				if (m && m.input && m.input.checked) { s[m.ability] = true; }
			});
			return s;
		})();

		function updateDirty() {
			if (!dirtyIndicators.length) { return; }
			var current = {};
			dataRows.forEach(function (tr) {
				var m = meta.get(tr);
				if (m && m.input && m.input.checked) { current[m.ability] = true; }
			});
			var dirty = false;
			var keysA = Object.keys(savedSnapshot);
			var keysB = Object.keys(current);
			if (keysA.length !== keysB.length) {
				dirty = true;
			} else {
				for (var i = 0; i < keysA.length; i++) {
					if (!current[keysA[i]]) { dirty = true; break; }
				}
			}
			dirtyIndicators.forEach(function (el) { el.hidden = !dirty; });
		}

		if (form) {
			form.addEventListener('submit', function (e) {
				var destructiveNewlyChecked = [];
				dataRows.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || m.managed || !m.input || !m.input.checked) { return; }
					var wasInhaled = m.input.getAttribute('data-was-inhaled') === '1';
					var isDestructive = m.input.getAttribute('data-destructive') === '1';
					if (isDestructive && !wasInhaled) {
						destructiveNewlyChecked.push(m.ability);
					}
				});
				if (destructiveNewlyChecked.length > 0) {
					var msg = destructiveNewlyChecked.length === 1
						? 'You are about to expose 1 destructive ability. This ability can modify content on your site. Continue?'
						: ('You are about to expose ' + destructiveNewlyChecked.length + ' destructive abilities. These abilities can modify content on your site. Continue?');
					if (!window.confirm(msg)) {
						e.preventDefault();
					}
				}
			});
		}

		dataRows.forEach(function (tr) {
			var m = meta.get(tr);
			if (m && m.input) {
				m.input.setAttribute('data-was-inhaled', m.input.checked ? '1' : '0');
			}
		});

		apply();
	})();

	/* ─── Copy endpoint ─── */
	(function initCopy() {
		var btn = document.getElementById('inhaleCopyEndpoint');
		var code = document.getElementById('inhaleEndpoint');
		if (!btn || !code) { return; }

		btn.addEventListener('click', function () {
			var text = code.textContent.trim();
			var label = btn.querySelector('.copy-btn-label');
			var prev = label ? label.textContent : 'Copy';

			function flash(result) {
				if (label) {
					label.textContent = result ? 'Copied' : 'Copy failed';
					setTimeout(function () { label.textContent = prev; }, 1400);
				}
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () { flash(true); }, function () { fallback(); });
			} else {
				fallback();
			}

			function fallback() {
				var range = document.createRange();
				range.selectNodeContents(code);
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range);
				var ok = false;
				try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
				sel.removeAllRanges();
				flash(ok);
			}
		});
	})();
})();
