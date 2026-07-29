/**
 * Jejak Journal — Frontend JavaScript
 * Vanilla JS, no jQuery.
 */
(function () {
	'use strict';

	var data = window.jejakJournalData || {};
	var app = document.getElementById('jejak-journal-app');
	if (!app) return;

	var prevBtn       = document.getElementById('jejak-prev-month');
	var nextBtn       = document.getElementById('jejak-next-month');
	var selectBtn     = document.getElementById('jejak-select-month');
	var monthModal    = document.getElementById('jejak-month-modal');
	var modalYear     = document.getElementById('jejak-modal-year');
	var modalMonth    = document.getElementById('jejak-modal-month');
	var modalGo       = document.getElementById('jejak-modal-go');
	var modalToday    = document.getElementById('jejak-modal-today');
	var contentEl     = document.getElementById('jejak-content');
	var noEntryEl     = document.getElementById('jejak-no-entry');
	var sectionsEl    = document.getElementById('jejak-sections');
	var createBtn     = document.getElementById('jejak-create-entry');
	var savedToast    = document.getElementById('jejak-saved-toast');
	var titleEl       = app.querySelector('.jejak-title');
	var loadingEl     = contentEl ? contentEl.querySelector('.jejak-loading') : null;

	var highlightsList = document.getElementById('jejak-highlights-list');
	var todosList      = document.getElementById('jejak-todos-list');
	var journalTbody   = document.getElementById('jejak-journal-tbody');
	var addHighlight   = document.getElementById('jejak-add-highlight');
	var addTodo        = document.getElementById('jejak-add-todo');
	var importTodosBtn = document.getElementById('jejak-import-todos');

	var currentYear   = parseInt(app.dataset.year, 10);
	var currentMonth  = parseInt(app.dataset.month, 10);
	var currentEntry  = null;
	var currentUpdatedAt = null;
	var lastLoadTime  = 0;
	var saveTimer     = null;
	var saveQueue     = Promise.resolve();

	var features = data.features || ['highlights', 'todos', 'journal'];
	var realCurrentYear  = parseInt(data.current_year, 10) || new Date().getFullYear();
	var realCurrentMonth = parseInt(data.current_month, 10) || (new Date().getMonth() + 1);

	var monthNames = data.i18n.months || [];

// ── Feature Helpers ─────────────────────────────
	function hasFeature(name) {
		return features.indexOf(name) !== -1;
	}

	function isCurrentMonth() {
		return currentYear === realCurrentYear && currentMonth === realCurrentMonth;
	}

	// ── API Helpers ──────────────────────────────────
	function apiFetch(url, options) {
		var opts = options || {};
		opts.headers = opts.headers || {};
		opts.headers['X-WP-Nonce'] = data.rest_nonce;
		opts.headers['Content-Type'] = 'application/json';
		return fetch(url, opts).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok) {
					var err = new Error('API error ' + res.status);
					err.status = res.status;
					err.body = body;
					throw err;
				}
				return body;
			});
		});
	}

	function getEntryUrl(year, month) {
		return data.rest_url + '/entry/' + year + '/' + month;
	}

	// ── Save Notification ────────────────────────────
	function showSaved() {
		if (!savedToast) return;
		savedToast.classList.add('is-visible');
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			savedToast.classList.remove('is-visible');
		}, 2000);
	}

	// ── Conflict Handler ─────────────────────────────
	function handleConflict(err) {
		if (err && err.status === 409) {
			showNotice(data.i18n.conflict || 'This entry was updated on another device. Reloading...');
			loadEntry(currentYear, currentMonth);
			return true;
		}
		return false;
	}

	// ── Overlay / Dialog Helper ──────────────────────
	function createOverlay(innerHtml, focusSelector) {
		var exist = document.getElementById('jejak-overlay');
		if (exist) exist.remove();

		var overlay = document.createElement('div');
		overlay.id = 'jejak-overlay';
		overlay.className = 'jejak-overlay';
		overlay.innerHTML = innerHtml;
		document.body.appendChild(overlay);

		if (focusSelector) {
			setTimeout(function () {
				var el = overlay.querySelector(focusSelector);
				if (el) el.focus();
			}, 50);
		}

		return overlay;
	}

	function closeOverlay() {
		var overlay = document.getElementById('jejak-overlay');
		if (overlay) overlay.remove();
	}

	// ── Confirm Delete Modal ─────────────────────────
	function confirmDelete(itemEl, onConfirm) {
		var html =
			'<div class="jejak-overlay-backdrop" data-action="dismiss-overlay"></div>' +
			'<div class="jejak-dialog">' +
				'<p class="jejak-dialog-text">' + (data.i18n.delete_confirm || 'Delete this item?') + '</p>' +
				'<div class="jejak-dialog-actions">' +
					'<button type="button" class="jejak-btn jejak-btn-outline" data-action="dismiss-overlay">' + (data.i18n.cancel || 'Cancel') + '</button>' +
					'<button type="button" class="jejak-btn jejak-btn-danger" data-action="confirm-delete">' + (data.i18n.delete || 'Delete') + '</button>' +
				'</div>' +
			'</div>';
		var overlay = createOverlay(html, '[data-action="dismiss-overlay"]');

		overlay.addEventListener('click', function (e) {
			var btn = e.target.closest('button');
			var action = btn ? btn.dataset.action : null;
			if (action === 'confirm-delete') {
				closeOverlay();
				onConfirm();
			} else if (action === 'dismiss-overlay' || e.target.classList.contains('jejak-overlay-backdrop')) {
				closeOverlay();
			}
		});
	}

	// ── Notice Modal ─────────────────────────────────
	function showNotice(message) {
		var html =
			'<div class="jejak-overlay-backdrop" data-action="dismiss-overlay"></div>' +
			'<div class="jejak-dialog">' +
				'<p class="jejak-dialog-text">' + message + '</p>' +
				'<div class="jejak-dialog-actions">' +
					'<button type="button" class="jejak-btn jejak-btn-primary" data-action="dismiss-overlay">' + (data.i18n.ok || 'OK') + '</button>' +
				'</div>' +
			'</div>';
		var overlay = createOverlay(html, '[data-action="dismiss-overlay"]');

		overlay.addEventListener('click', function (e) {
			var btn = e.target.closest('button');
			var action = btn ? btn.dataset.action : null;
			if (action === 'dismiss-overlay' || e.target.classList.contains('jejak-overlay-backdrop')) {
				closeOverlay();
			}
		});
	}

	// ── URL Navigation ───────────────────────────────
	function getMonthSlug(year, month) {
		var m = month < 10 ? '0' + month : '' + month;
		return year + '.' + m;
	}

	function updateURL(year, month) {
		var slug = getMonthSlug(year, month);
		var url = new URL(window.location.href);
		url.searchParams.set('jejak', slug);
		window.history.replaceState({}, '', url.toString());
	}

	function getURLMonth() {
		var url = new URL(window.location.href);
		var param = url.searchParams.get('jejak');
		if (param && /^\d{4}\.\d{2}$/.test(param)) {
			var parts = param.split('.');
			return { year: parseInt(parts[0], 10), month: parseInt(parts[1], 10) };
		}
		return null;
	}

	// ── Navigation ───────────────────────────────────
	function switchTo(year, month) {
		currentYear  = year;
		currentMonth = month;
		updateURL(year, month);
		updateTitle(year, month);
		updateModalSelections(year, month);
		loadEntry(year, month);
	}

	prevBtn.addEventListener('click', function () {
		var m = currentMonth - 1;
		var y = currentYear;
		if (m < 1) { m = 12; y--; }
		switchTo(y, m);
	});

	nextBtn.addEventListener('click', function () {
		var m = currentMonth + 1;
		var y = currentYear;
		if (m > 12) { m = 1; y++; }
		switchTo(y, m);
	});

	// ── Month Picker Modal ───────────────────────────
	function populateModalYears() {
		modalYear.innerHTML = '';
		var start = realCurrentYear - 5;
		var end   = realCurrentYear + 5;
		for (var y = start; y <= end; y++) {
			var opt = document.createElement('option');
			opt.value = y;
			opt.textContent = y;
			if (y === currentYear) opt.selected = true;
			modalYear.appendChild(opt);
		}
	}

	function updateModalSelections(year, month) {
		if (modalYear) modalYear.value = year;
		if (modalMonth) modalMonth.value = month;
	}

	function openMonthModal() {
		populateModalYears();
		updateModalSelections(currentYear, currentMonth);
		monthModal.style.display = 'flex';
		setTimeout(function () {
			if (modalYear) modalYear.focus();
		}, 50);
	}

	function closeMonthModal() {
		monthModal.style.display = 'none';
	}

	selectBtn.addEventListener('click', openMonthModal);

	monthModal.addEventListener('click', function (e) {
		if (e.target.dataset.action === 'close-month-modal' ||
			e.target.classList.contains('jejak-month-modal-backdrop')) {
			closeMonthModal();
		}
	});

	modalGo.addEventListener('click', function () {
		var y = parseInt(modalYear.value, 10);
		var m = parseInt(modalMonth.value, 10);
		closeMonthModal();
		switchTo(y, m);
	});

	modalToday.addEventListener('click', function () {
		closeMonthModal();
		switchTo(realCurrentYear, realCurrentMonth);
	});

	// ── Load Entry ───────────────────────────────────
	function loadEntry(year, month) {
		showLoading();
		apiFetch(getEntryUrl(year, month)).then(function (entry) {
			currentEntry = entry;
			currentUpdatedAt = entry.updated_at || null;
			lastLoadTime = Date.now();
			renderAll(entry);
			showSections();
			updateImportButton();
		}).catch(function () {
			showNoEntry();
		});
	}

	function showLoading() {
		contentEl.style.display = 'block';
		noEntryEl.style.display = 'none';
		sectionsEl.style.display = 'none';
		if (loadingEl) loadingEl.style.display = 'block';
	}

	function showNoEntry() {
		contentEl.style.display = 'none';
		noEntryEl.style.display = 'block';
		sectionsEl.style.display = 'none';
	}

	function showSections() {
		contentEl.style.display = 'block';
		noEntryEl.style.display = 'none';
		sectionsEl.style.display = 'block';
		if (loadingEl) loadingEl.style.display = 'none';
	}

	// ── Update Title ─────────────────────────────────
	function updateTitle(year, month) {
		titleEl.textContent = monthNames[month - 1] + ' ' + year;
	}

	// ── Create Entry ─────────────────────────────────
	createBtn.addEventListener('click', function () {
		apiFetch(getEntryUrl(currentYear, currentMonth), { method: 'POST' }).then(function (entry) {
			currentEntry = entry;
			renderAll(entry);
			showSections();
			updateImportButton();
		}).catch(function () {
			showNotice(data.i18n.error || 'Could not create entry.');
		});
	});

	// ── Render All Sections ──────────────────────────
	function renderAll(entry) {
		if (hasFeature('highlights')) {
			renderHighlights(entry.highlights || []);
		}
		if (hasFeature('todos')) {
			renderTodos(entry.todos || []);
		}
		if (hasFeature('journal')) {
			renderJournal(entry.journal_notes || []);
		}
	}

	// ── Highlights + Icon Picker ─────────────────────
	// Each highlight item stores the icon key (e.g. "star") in data-icon-key.
	// The emoji is rendered from data.icons[key] on display.
	var defaultIconKey = 'star';

	function getEmoji(key) {
		var allIcons = data.allIcons || {};
		var icons = data.icons || {};
		return allIcons[key] || icons[key] || '⭐';
	}

	function renderHighlights(items) {
		if (!highlightsList) return;
		highlightsList.innerHTML = '';
		items.forEach(function (item, index) {
			highlightsList.appendChild(createHighlightItem(item, index));
		});
		// Auto-resize after render
		resizeAll(highlightsList, '.jejak-highlight-text');
	}

	function createHighlightItem(item, index) {
		var iconKey = item.icon_key || defaultIconKey;
		var emoji = getEmoji(iconKey);
		var div = document.createElement('div');
		div.className = 'jejak-field-row jejak-highlight-item';
		div.dataset.index = index;
		div.innerHTML =
			'<button type="button" class="jejak-drag-handle" title="' + (data.i18n.drag_to_reorder || 'Drag to reorder') + '" draggable="true">\u2261</button>' +
			'<button type="button" class="jejak-field-control jejak-icon-picker-btn" data-action="pick-icon" data-icon-key="' + iconKey + '" title="' + (data.i18n.select_icon || 'Select icon') + '">' +
				emoji +
			'</button>' +
			'<textarea class="jejak-field-textarea jejak-highlight-text" rows="1">' + escapeHtml(item.text || '') + '</textarea>' +
			'<button type="button" class="jejak-remove-btn" data-action="remove" title="Remove">\u00D7</button>';
		return div;
	}

	function getHighlightsFromDOM() {
		if (!highlightsList) return [];
		var items = highlightsList.querySelectorAll('.jejak-highlight-item');
		return Array.prototype.map.call(items, function (el) {
			return {
				icon_key: el.querySelector('.jejak-icon-picker-btn').dataset.iconKey || defaultIconKey,
				text: el.querySelector('.jejak-highlight-text').value || '',
			};
		});
	}

	if (highlightsList) {
		highlightsList.addEventListener('click', function (e) {
			var btn = e.target.closest('button');
			if (!btn) return;
			var action = btn.dataset.action;
			var item = btn.closest('.jejak-highlight-item');

			if (action === 'remove') {
				confirmDelete(item, function () {
					item.remove();
					enqueueSave(saveHighlights);
				});
			} else if (action === 'pick-icon') {
				openIconPicker(btn);
			}
		});

		highlightsList.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-highlight-text')) {
				autoResizeTextarea(e.target);
				saveHighlightsDebounced();
			}
		});
	}

	var saveHighlightsDebounced = debounce(function () {
		enqueueSave(saveHighlights);
	}, 800);

	function saveHighlights() {
		if (!currentEntry) return Promise.resolve();
		var highlights = getHighlightsFromDOM();
		return apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/highlights', {
			method: 'PUT',
			body: JSON.stringify({ data: highlights, updated_at: currentUpdatedAt }),
		}).then(function (res) {
			currentUpdatedAt = res.updated_at;
			showSaved();
		}).catch(function (err) {
			if (!handleConflict(err)) { /* silent */ }
		});
	}

	if (addHighlight) {
		addHighlight.addEventListener('click', function () {
			var newItem = { icon_key: defaultIconKey, text: '' };
			var item = createHighlightItem(newItem, highlightsList.children.length);
			highlightsList.appendChild(item);
			var ta = item.querySelector('.jejak-highlight-text');
			ta.focus();
			requestAnimationFrame(function () { autoResizeTextarea(ta); });
			enqueueSave(saveHighlights);
		});
	}

	// ── Icon Picker Modal ────────────────────────────
	function openIconPicker(triggerBtn) {
		var allIcons = data.allIcons || {};
		var defaultIcons = data.icons || {};
		var selectedKey = triggerBtn.dataset.iconKey || defaultIconKey;

		function buildGrid(filterKey) {
			var iconKeys = [];
			var query = filterKey ? filterKey.toLowerCase().replace(/\s+/g, '_') : '';

			if (query) {
				// Search full library by word match
				var words = query.split('_').filter(function (w) { return w.length > 0; });
				Object.keys(allIcons).forEach(function (key) {
					if (words.every(function (w) { return key.indexOf(w) !== -1; })) {
						iconKeys.push(key);
					}
				});
			} else {
				// Default: the 20 predefined icons
				iconKeys = Object.keys(defaultIcons);
			}

			// Include selected icon, dropping last to stay at 20
			var hasSelected = iconKeys.indexOf(selectedKey) !== -1;
			if (!hasSelected) {
				iconKeys = iconKeys.slice(0, 19);
				iconKeys.unshift(selectedKey);
			} else {
				iconKeys = iconKeys.slice(0, 20);
			}

			var html = '';
			iconKeys.forEach(function (key) {
				var isSelected = key === selectedKey;
				html += '<button type="button" class="jejak-icon-option' + (isSelected ? ' jejak-icon-option--selected' : '') + '" data-icon-key="' + key + '">' +
					(allIcons[key] || defaultIcons[key] || '⭐') + '</button>';
			});
			return html;
		}

		var iconHtml = buildGrid('');

		var html =
			'<div class="jejak-overlay-backdrop" data-action="dismiss-overlay"></div>' +
			'<div class="jejak-dialog jejak-icon-picker-dialog">' +
				'<h3>' + (data.i18n.select_icon || 'Select icon') + '</h3>' +
				'<input type="text" class="jejak-icon-search" id="jejak-icon-search" placeholder="' + (data.i18n.search_icon || 'Search icons…') + '">' +
				'<div class="jejak-icon-grid">' + iconHtml + '</div>' +
				'<button type="button" class="jejak-btn jejak-modal-close" data-action="dismiss-overlay">\u00D7</button>' +
			'</div>';
		var overlay = createOverlay(html);

		overlay.querySelector('.jejak-icon-grid').addEventListener('click', function (e) {
			var iconBtn = e.target.closest('.jejak-icon-option');
			if (!iconBtn) return;
			var key = iconBtn.dataset.iconKey;
			var emoji = getEmoji(key);
			triggerBtn.textContent = emoji;
			triggerBtn.dataset.iconKey = key;
			closeOverlay();
			enqueueSave(saveHighlights);
		});

		overlay.addEventListener('click', function (e) {
			if (e.target.dataset.action === 'dismiss-overlay' || e.target.classList.contains('jejak-overlay-backdrop')) {
				closeOverlay();
			}
		});

		var searchEl = overlay.querySelector('#jejak-icon-search');
		var iconGrid = overlay.querySelector('.jejak-icon-grid');
		if (searchEl) {
			searchEl.addEventListener('input', function () {
				iconGrid.innerHTML = buildGrid(this.value);
			});
		}
	}

	// ── ToDos ────────────────────────────────────────
	function renderTodos(items) {
		if (!todosList) return;
		todosList.innerHTML = '';
		items.forEach(function (item, index) {
			todosList.appendChild(createTodoItem(item, index));
		});
		// Auto-resize after render
		resizeAll(todosList, '.jejak-todo-text');
	}

	function createTodoItem(item, index) {
		var div = document.createElement('div');
		div.className = 'jejak-field-row jejak-todo-item' + (item.imported ? ' is-imported' : '');
		div.dataset.index = index;
		var checked = item.done ? ' checked' : '';
		var disabled = item.imported ? ' disabled' : '';
		var readonly = item.imported ? ' readonly' : '';
		div.innerHTML =
			'<button type="button" class="jejak-drag-handle" title="' + (data.i18n.drag_to_reorder || 'Drag to reorder') + '" draggable="true">\u2261</button>' +
			'<div class="jejak-todo-row">' +
				'<input type="checkbox" class="jejak-todo-checkbox"' + checked + disabled + '>' +
				'<span class="jejak-field-control jejak-todo-custom-check" data-action="toggle-todo"></span>' +
				'<textarea class="jejak-field-textarea jejak-todo-text" rows="1"' + readonly + '>' + escapeHtml(item.text || '') + '</textarea>' +
			'</div>' +
			'<button type="button" class="jejak-remove-btn" data-action="remove" title="Remove">\u00D7</button>';
		return div;
	}

	function getTodosFromDOM() {
		if (!todosList) return [];
		var items = todosList.querySelectorAll('.jejak-todo-item');
		return Array.prototype.map.call(items, function (el) {
			return {
				text: el.querySelector('.jejak-todo-text').value.trim(),
				done: el.querySelector('.jejak-todo-checkbox').checked,
				imported: el.classList.contains('is-imported'),
			};
		});
	}

	if (todosList) {
		todosList.addEventListener('click', function (e) {
			// Custom check toggle
			var check = e.target.closest('.jejak-todo-custom-check');
			if (check) {
				var row = check.closest('.jejak-todo-row');
				var cb = row.querySelector('.jejak-todo-checkbox');
				if (cb && !cb.disabled) {
					cb.checked = !cb.checked;
					enqueueSave(saveTodos);
				}
				return;
			}

			// Remove button
			var btn = e.target.closest('button');
			if (btn && btn.dataset.action === 'remove') {
				var todoItem = btn.closest('.jejak-todo-item');
				confirmDelete(todoItem, function () {
					todoItem.remove();
					enqueueSave(saveTodos);
				});
			}
		});

		todosList.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-todo-text')) {
				autoResizeTextarea(e.target);
				saveTodosDebounced();
			}
		});
	}

	var saveTodosDebounced = debounce(function () {
		enqueueSave(saveTodos);
	}, 800);

	function saveTodos() {
		if (!currentEntry) return Promise.resolve();
		var todos = getTodosFromDOM();
		return apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/todos', {
			method: 'PUT',
			body: JSON.stringify({ data: todos, updated_at: currentUpdatedAt }),
		}).then(function (res) {
			currentUpdatedAt = res.updated_at;
			showSaved();
		}).catch(function (err) {
			if (!handleConflict(err)) { /* silent */ }
		});
	}

	// ── Import Todos ─────────────────────────────────
	function updateImportButton() {
		if (!importTodosBtn) return;
		if (!isCurrentMonth()) {
			importTodosBtn.style.display = 'none';
			return;
		}

		var prevMonth = currentMonth - 1;
		var prevYear = currentYear;
		if (prevMonth < 1) {
			prevMonth = 12;
			prevYear--;
		}

		apiFetch(getEntryUrl(prevYear, prevMonth)).then(function (prevEntry) {
			var prevTodos = prevEntry.todos || [];
			var hasImportable = prevTodos.some(function (t) {
				return !t.done && !t.imported;
			});
			importTodosBtn.style.display = hasImportable ? '' : 'none';
		}).catch(function () {
			importTodosBtn.style.display = 'none';
		});
	}

	importTodosBtn.addEventListener('click', function () {
		enqueueSave(doImport);
	});

	function doImport() {
		var prevMonth = currentMonth - 1;
		var prevYear = currentYear;
		if (prevMonth < 1) {
			prevMonth = 12;
			prevYear--;
		}

		return apiFetch(getEntryUrl(prevYear, prevMonth)).then(function (prevEntry) {
			var prevTodos = prevEntry.todos || [];
			var toImport = prevTodos.filter(function (t) {
				return !t.done && !t.imported;
			});

			if (toImport.length === 0) {
				showNotice(
					data.i18n.import_none
						.replace('%1$s', monthNames[prevMonth - 1])
						.replace('%2$d', prevYear)
				);
				return;
			}

			prevTodos.forEach(function (t) {
				if (!t.done && !t.imported) {
					t.imported = true;
				}
			});

			var currentTodos = getTodosFromDOM();
			toImport.forEach(function (t) {
				currentTodos.push({
					text: t.text,
					done: false,
					imported: false,
				});
			});

			var p1 = apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/todos', {
				method: 'PUT',
				body: JSON.stringify({ data: currentTodos, updated_at: currentUpdatedAt }),
			});
			var p2 = apiFetch(data.rest_url + '/entry/' + prevEntry.id + '/todos', {
				method: 'PUT',
				body: JSON.stringify({ data: prevTodos, updated_at: prevEntry.updated_at }),
			});

			return Promise.all([p1, p2]).then(function (results) {
				currentUpdatedAt = results[0].updated_at;
				apiFetch(getEntryUrl(currentYear, currentMonth)).then(function (entry) {
					currentEntry = entry;
					currentUpdatedAt = entry.updated_at;
					renderAll(entry);
					showSaved();
				});
			}).catch(function (err) {
				if (err && err.status === 409) {
					showNotice(data.i18n.conflict || 'This entry was updated on another device. Reloading...');
					loadEntry(currentYear, currentMonth);
					return;
				}
				showNotice(data.i18n.import_failed || 'Failed to import todos.');
			});
		}).catch(function () {
			showNotice(data.i18n.no_prev_entry || 'No previous month entry found.');
		});
	}

	if (addTodo) {
		addTodo.addEventListener('click', function () {
			var newItem = { text: '', done: false, imported: false };
			var item = createTodoItem(newItem, todosList.children.length);
			todosList.appendChild(item);
			var ta = item.querySelector('.jejak-todo-text');
			ta.focus();
			requestAnimationFrame(function () { autoResizeTextarea(ta); });
			enqueueSave(saveTodos);
		});
	}

	// ── Journal Notes ────────────────────────────────
	function renderJournal(notes) {
		if (!journalTbody) return;
		journalTbody.innerHTML = '';
		if (!notes || !notes.length) return;
		notes.forEach(function (note, index) {
			var row = document.createElement('div');
			row.className = 'jejak-field-row jejak-journal-item';
			row.dataset.index = index;
			var dayName = note.day_name || '';
			var dayNameShort = dayName.substring(0, 3);
			if (dayNameShort === 'Sat' || dayNameShort === 'Sun') {
				row.classList.add('is-weekend');
			}
			row.innerHTML =
				'<div class="jejak-field-control jejak-day-icon" title="' + note.day + '">' +
					'<span class="jejak-day-header">' + dayNameShort + '</span>' +
					'<span class="jejak-day-number">' + note.day + '</span>' +
				'</div>' +
				'<textarea class="jejak-field-textarea jejak-note-textarea" rows="1" data-index="' + index + '">' + escapeHtml(note.notes || '') + '</textarea>';
			journalTbody.appendChild(row);
		});

		// Auto-resize after render
		resizeAll(journalTbody, '.jejak-note-textarea');
	}

	function getNotesFromDOM() {
		if (!journalTbody) return [];
		var rows = journalTbody.querySelectorAll('.jejak-journal-item');
		return Array.prototype.map.call(rows, function (row, idx) {
			var ta = row.querySelector('.jejak-note-textarea');
			return {
				day: idx + 1,
				date: currentEntry && currentEntry.journal_notes && currentEntry.journal_notes[idx]
					? currentEntry.journal_notes[idx].date : '',
				day_name: currentEntry && currentEntry.journal_notes && currentEntry.journal_notes[idx]
					? currentEntry.journal_notes[idx].day_name : '',
				notes: ta ? ta.value : '',
			};
		});
	}

	function autoResizeTextarea(textarea) {
		// Reset to get true scrollHeight
		textarea.style.height = 'auto';
		var h = Math.max(textarea.scrollHeight, 30);
		textarea.style.height = h + 'px';
	}

	if (journalTbody) {
		journalTbody.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-note-textarea')) {
				autoResizeTextarea(e.target);
				saveNotesDebounced();
			}
		});
	}

	var saveNotesDebounced = debounce(function () {
		enqueueSave(saveNotes);
	}, 1000);

	function saveNotes() {
		if (!currentEntry) return Promise.resolve();
		var notes = getNotesFromDOM();
		return apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/notes', {
			method: 'PUT',
			body: JSON.stringify({ data: notes, updated_at: currentUpdatedAt }),
		}).then(function (res) {
			currentUpdatedAt = res.updated_at;
			showSaved();
		}).catch(function (err) {
			if (!handleConflict(err)) { /* silent */ }
		});
	}

	// ── Drag and Drop Reordering ─────────────────────
	function initDragAndDrop(container, saveFn, itemSelector) {
		var draggedItem = null;

		// ── Desktop (HTML5 DnD) ──────────────────────
		container.addEventListener('dragstart', function (e) {
			var handle = e.target.closest('.jejak-drag-handle');
			if (!handle) return;
			var item = handle.closest(itemSelector);
			if (!item) return;
			draggedItem = item;
			item.classList.add('jejak-dragging');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', '');
		});

		container.addEventListener('dragover', function (e) {
			e.preventDefault();
			var item = e.target.closest(itemSelector);
			if (!item || item === draggedItem) return;
			updateDragOver(item);
		});

		container.addEventListener('drop', function (e) {
			e.preventDefault();
			var target = e.target.closest(itemSelector);
			if (!target || target === draggedItem || !draggedItem) return;
			container.insertBefore(draggedItem, target);
			cleanup();
			saveFn();
		});

		container.addEventListener('dragend', cleanup);

		// ── Touch (mobile) ────────────────────────────
		container.addEventListener('touchstart', function (e) {
			var handle = e.target.closest('.jejak-drag-handle');
			if (!handle) return;
			var item = handle.closest(itemSelector);
			if (!item) return;
			e.preventDefault();
			draggedItem = item;
			item.classList.add('jejak-dragging');
		}, { passive: false });

		container.addEventListener('touchmove', function (e) {
			if (!draggedItem) return;
			e.preventDefault();
			var touch = e.touches[0];
			var el = document.elementFromPoint(touch.clientX, touch.clientY);
			if (!el) return;
			var item = el.closest(itemSelector);
			if (!item || item === draggedItem) return;
			updateDragOver(item);
		}, { passive: false });

		container.addEventListener('touchend', function () {
			if (!draggedItem) return;
			var target = container.querySelector('.jejak-drag-over');
			if (target && target !== draggedItem) {
				container.insertBefore(draggedItem, target);
				cleanup();
				saveFn();
			} else {
				cleanup();
			}
		});

		container.addEventListener('touchcancel', cleanup);

		// ── Helpers ────────────────────────────────────
		function updateDragOver(item) {
			container.querySelectorAll('.jejak-drag-over').forEach(function (el) {
				el.classList.remove('jejak-drag-over');
			});
			item.classList.add('jejak-drag-over');
		}

		function cleanup() {
			container.querySelectorAll('.jejak-dragging, .jejak-drag-over').forEach(function (el) {
				el.classList.remove('jejak-dragging', 'jejak-drag-over');
			});
			draggedItem = null;
		}
	}

	// ── Save Queue (prevents concurrent API writes) ──
	function enqueueSave(fn) {
		saveQueue = saveQueue.then(function () {
			return fn();
		}).catch(function () {
			// Queue continues even if one save fails (e.g., 409 handled by conflict handler).
		});
	}

	// ── Utility ──────────────────────────────────────
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function debounce(fn, delay) {
		var timer;
		return function () {
			clearTimeout(timer);
			timer = setTimeout(fn, delay);
		};
	}

	function resizeAll(container, selector) {
		container.querySelectorAll(selector).forEach(function (ta) {
			requestAnimationFrame(function () { autoResizeTextarea(ta); });
		});
	}

	// ── Init ─────────────────────────────────────────
	// Wire up drag-and-drop reordering
	if (highlightsList && hasFeature('highlights')) {
		initDragAndDrop(highlightsList, function () { enqueueSave(saveHighlights); }, '.jejak-highlight-item');
	}
	if (todosList && hasFeature('todos')) {
		initDragAndDrop(todosList, function () { enqueueSave(saveTodos); }, '.jejak-todo-item');
	}

	// Refresh entry when tab becomes visible again (only if stale > 10s).
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden && currentEntry && Date.now() - lastLoadTime > 10000) {
			loadEntry(currentYear, currentMonth);
		}
	});

	// Check URL param for persisted month
	var urlMonth = getURLMonth();
	if (urlMonth) {
		currentYear = urlMonth.year;
		currentMonth = urlMonth.month;
	}

	updateTitle(currentYear, currentMonth);
	updateURL(currentYear, currentMonth);
	populateModalYears();
	loadEntry(currentYear, currentMonth);
})();
