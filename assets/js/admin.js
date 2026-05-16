/*!
 * Inhale: MCP Abilities admin behavior.
 * Vanilla JS, no jQuery, no build step.
 *
 * Pattern: standard wp-admin list table.
 *  - Row checkbox = selection (not state).
 *  - Status column = current inhaled state (server-rendered).
 *  - Bulk actions dropdown + Apply = commits inhale/exhale immediately.
 *  - Row-hover "Inhale" / "Exhale" link = single-row commit.
 *  - No staged state, no dirty indicator, no save button.
 */
(function () {
	'use strict';

	var wrap = document.querySelector('.inhale-wrap');
	if (!wrap) {
		return;
	}

	/* ─── Theme toggle ────────────────────────────────────────── */
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

	(function initTable() {
		var tbody = document.getElementById('inhaleAbilitiesBody');
		if (!tbody) { return; }
		var form = document.getElementById('inhaleAbilitiesForm');
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
				inhaled: tr.getAttribute('data-inhaled') === 'true',
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

			var inhaled = m.inhaled || m.managed;
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
				return m.inhaled ? '0-inhaled' : '2-not';
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

			var anyFilter = state.search || state.view !== 'all' || sourceCount > 0 || state.sort.col !== null;
			if (resetLink) { resetLink.classList.toggle('show', !!anyFilter); }
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

		/* Select-all (top + bottom) toggles the row checkboxes (selection
		 * for bulk action). Only flips visible, non-managed rows. */
		selectAllBoxes.forEach(function (box) {
			box.addEventListener('change', function () {
				dataRows.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || m.managed || tr.classList.contains('is-hidden') || tr.classList.contains('inhale-pg-hidden')) { return; }
					if (!m.input) { return; }
					m.input.checked = box.checked;
				});
				// Sync the other select-all box.
				selectAllBoxes.forEach(function (other) {
					if (other !== box) { other.checked = box.checked; }
				});
			});
		});

		/* ─── Pagination (client-side) ──────────────────────────── */
		var perPage = 50;
		var currentPage = 1;
		var perPageSelects = document.querySelectorAll('.inhale-wrap .inhale-pg-perpage');
		var currentPageInputs = document.querySelectorAll('.inhale-wrap .inhale-pg-current');
		var totalPagesEls = document.querySelectorAll('.inhale-wrap .inhale-pg-total');
		var firstButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-first');
		var prevButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-prev');
		var nextButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-next');
		var lastButtons = document.querySelectorAll('.inhale-wrap .inhale-pg-last');

		function getFilteredRows() { return dataRows.filter(rowMatches); }
		function getTotalPages() {
			if (perPage === 0) { return 1; }
			return Math.max(1, Math.ceil(getFilteredRows().length / perPage));
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
			if (perPage === 0) {
				filtered.forEach(function (tr) { tr.classList.remove('inhale-pg-hidden'); });
			} else {
				var start = (currentPage - 1) * perPage;
				var end = start + perPage;
				filtered.forEach(function (tr, idx) {
					tr.classList.toggle('inhale-pg-hidden', idx < start || idx >= end);
				});
			}
			currentPageInputs.forEach(function (input) { input.value = String(currentPage); });
			totalPagesEls.forEach(function (el) { el.textContent = String(totalPages); });
			perPageSelects.forEach(function (sel) { sel.value = String(perPage); });
			firstButtons.forEach(function (b) { b.disabled = currentPage <= 1; });
			prevButtons.forEach(function (b) { b.disabled = currentPage <= 1; });
			nextButtons.forEach(function (b) { b.disabled = currentPage >= totalPages; });
			lastButtons.forEach(function (b) { b.disabled = currentPage >= totalPages; });
		}

		var coreApply = apply;
		apply = function () {
			coreApply();
			applyPagination();
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

		var resetPageOn = function () { currentPage = 1; };
		if (searchInput) { searchInput.addEventListener('input', resetPageOn); }
		subsubsubLinks.forEach(function (a) { a.addEventListener('click', resetPageOn); });
		filterCheckboxes.forEach(function (cb) { cb.addEventListener('change', resetPageOn); });
		if (filterClearBtn) { filterClearBtn.addEventListener('click', resetPageOn); }
		if (resetLink) { resetLink.addEventListener('click', resetPageOn); }

		/* ─── Bulk action Apply ─────────────────────────────────── */
		var bulkSelectsTop = document.getElementById('inhale-bulk-action-top');
		var bulkSelectsBot = document.getElementById('inhale-bulk-action-bottom');
		var applyButtons = document.querySelectorAll('.inhale-wrap .inhale-bulk-apply');

		function syncBulkSelects(source) {
			if (!bulkSelectsTop || !bulkSelectsBot) { return; }
			if (source === bulkSelectsTop) { bulkSelectsBot.value = bulkSelectsTop.value; }
			else                            { bulkSelectsTop.value = bulkSelectsBot.value; }
		}
		if (bulkSelectsTop) { bulkSelectsTop.addEventListener('change', function () { syncBulkSelects(bulkSelectsTop); }); }
		if (bulkSelectsBot) { bulkSelectsBot.addEventListener('change', function () { syncBulkSelects(bulkSelectsBot); }); }

		if (form) {
			form.addEventListener('submit', function (e) {
				// Only run guard on the bulk-Apply submit (the row-action
				// link path skips this guard because it sets up the form
				// itself before submitting).
				if (form.getAttribute('data-skip-guard') === '1') {
					form.removeAttribute('data-skip-guard');
					return;
				}

				var action = bulkSelectsTop && bulkSelectsTop.value && bulkSelectsTop.value !== '-1'
					? bulkSelectsTop.value
					: (bulkSelectsBot ? bulkSelectsBot.value : '-1');
				if (action !== 'inhale' && action !== 'exhale') {
					e.preventDefault();
					alert('Choose a bulk action before clicking Apply.');
					return;
				}

				var selected = form.querySelectorAll('input.inhale-ability-checkbox:checked');
				if (!selected.length) {
					e.preventDefault();
					alert('Select at least one ability before applying a bulk action.');
					return;
				}

				if (action === 'inhale') {
					var destructiveNames = [];
					selected.forEach(function (cb) {
						if (cb.getAttribute('data-destructive') === '1') {
							var tr = cb.closest('tr');
							var m = tr ? meta.get(tr) : null;
							destructiveNames.push(m ? m.ability : cb.value);
						}
					});
					if (destructiveNames.length) {
						var msg = destructiveNames.length === 1
							? 'Inhale "' + destructiveNames[0] + '"? This ability can modify content on your site.'
							: 'Inhale ' + destructiveNames.length + ' destructive abilities? They can modify content on your site.';
						if (!window.confirm(msg)) {
							e.preventDefault();
							return;
						}
					}
				}
			});
		}

		/* ─── Row-hover quick action ────────────────────────────── */
		var rowActions = document.querySelectorAll('.inhale-wrap .inhale-row-action');
		rowActions.forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				if (!form) { return; }

				var action = link.getAttribute('data-action');
				var ability = link.getAttribute('data-ability');
				if (!action || !ability) { return; }

				if (action === 'inhale' && link.getAttribute('data-destructive') === '1') {
					if (!window.confirm('Inhale "' + ability + '"? This ability can modify content on your site.')) {
						return;
					}
				}

				// Uncheck every row, then check just the target row.
				dataRows.forEach(function (tr) {
					var m = meta.get(tr);
					if (!m || !m.input || m.managed) { return; }
					m.input.checked = (m.ability === ability);
				});

				// Set the bulk action dropdowns to the chosen verb.
				if (bulkSelectsTop) { bulkSelectsTop.value = action; }
				if (bulkSelectsBot) { bulkSelectsBot.value = action; }

				// Skip the bulk-apply submit guard (we already validated)
				// and submit the form.
				form.setAttribute('data-skip-guard', '1');
				form.submit();
			});
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
