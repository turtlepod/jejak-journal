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
	var saveTimer     = null;

	var features = data.features || ['highlights', 'todos', 'journal'];
	var realCurrentYear  = parseInt(data.current_year, 10) || new Date().getFullYear();
	var realCurrentMonth = parseInt(data.current_month, 10) || (new Date().getMonth() + 1);

	var monthNames = [
		'January', 'February', 'March', 'April', 'May', 'June',
		'July', 'August', 'September', 'October', 'November', 'December'
	];

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
			if (!res.ok) throw new Error('API error ' + res.status);
			return res.json();
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

	// ── URL Navigation ───────────────────────────────
	function getMonthSlug(year, month) {
		return 'jj-' + monthNames[month - 1].toLowerCase() + '-' + year;
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
		if (param && /^jj-[a-z]+-\d{4}$/.test(param)) {
			var parts = param.split('-');
			var monthName = parts[1]; // e.g. "june"
			var year = parseInt(parts[2], 10);
			for (var i = 0; i < monthNames.length; i++) {
				if (monthNames[i].toLowerCase() === monthName) {
					return { year: year, month: i + 1 };
				}
			}
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
			alert(data.i18n.error || 'Could not create entry.');
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
		var icons = data.icons || {};
		return icons[key] || '\u2B50';
	}

	function renderHighlights(items) {
		if (!highlightsList) return;
		highlightsList.innerHTML = '';
		items.forEach(function (item, index) {
			highlightsList.appendChild(createHighlightItem(item, index));
		});
	}

	function createHighlightItem(item, index) {
		var iconKey = item.icon_key || defaultIconKey;
		var emoji = getEmoji(iconKey);
		var div = document.createElement('div');
		div.className = 'jejak-highlight-item';
		div.dataset.index = index;
		div.innerHTML =
			'<button type="button" class="jejak-icon-picker-btn" data-action="pick-icon" data-icon-key="' + iconKey + '" title="' + (data.i18n.select_icon || 'Select icon') + '">' +
				emoji +
			'</button>' +
			'<input type="text" class="jejak-highlight-text" value="' + escapeHtml(item.text || '') + '" placeholder="What happened?">' +
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
				item.remove();
				saveHighlights();
			} else if (action === 'pick-icon') {
				openIconPicker(btn);
			}
		});

		highlightsList.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-highlight-text')) {
				saveHighlightsDebounced();
			}
		});
	}

	var highlightsSaveTimer;
	function saveHighlightsDebounced() {
		clearTimeout(highlightsSaveTimer);
		highlightsSaveTimer = setTimeout(saveHighlights, 800);
	}

	function saveHighlights() {
		if (!currentEntry) return;
		var highlights = getHighlightsFromDOM();
		apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/highlights', {
			method: 'PUT',
			body: JSON.stringify(highlights),
		}).then(function () {
			showSaved();
		}).catch(function () { /* silent */ });
	}

	if (addHighlight) {
		addHighlight.addEventListener('click', function () {
			var newItem = { icon_key: defaultIconKey, text: '' };
			var item = createHighlightItem(newItem, highlightsList.children.length);
			highlightsList.appendChild(item);
			item.querySelector('.jejak-highlight-text').focus();
			saveHighlights();
		});
	}

	// ── Icon Picker Modal ────────────────────────────
	function openIconPicker(triggerBtn) {
		var existing = document.getElementById('jejak-icon-modal');
		if (existing) existing.remove();

		var icons = data.icons || {};
		var iconHtml = '';
		Object.keys(icons).forEach(function (key) {
			iconHtml += '<button type="button" class="jejak-icon-option" data-icon-key="' + key + '">' + icons[key] + '</button>';
		});

		var modal = document.createElement('div');
		modal.id = 'jejak-icon-modal';
		modal.className = 'jejak-icon-modal';
		modal.innerHTML =
			'<div class="jejak-icon-modal-backdrop" data-action="close-modal"></div>' +
			'<div class="jejak-icon-modal-content">' +
				'<h3>' + (data.i18n.select_icon || 'Select icon') + '</h3>' +
				'<input type="text" class="jejak-icon-search" id="jejak-icon-search" placeholder="' + (data.i18n.search_icon || 'Search icons...') + '">' +
				'<div class="jejak-icon-grid">' + iconHtml + '</div>' +
				'<button type="button" class="jejak-btn jejak-modal-close" data-action="close-modal">\u00D7</button>' +
			'</div>';

		document.body.appendChild(modal);

		setTimeout(function () {
			var searchEl = document.getElementById('jejak-icon-search');
			if (searchEl) searchEl.focus();
		}, 50);

		modal.querySelector('.jejak-icon-grid').addEventListener('click', function (e) {
			var iconBtn = e.target.closest('.jejak-icon-option');
			if (!iconBtn) return;
			var key = iconBtn.dataset.iconKey;
			var emoji = getEmoji(key);
			triggerBtn.textContent = emoji;
			triggerBtn.dataset.iconKey = key;
			modal.remove();
			saveHighlights();
		});

		modal.addEventListener('click', function (e) {
			if (e.target.dataset.action === 'close-modal' || e.target.classList.contains('jejak-icon-modal-backdrop')) {
				modal.remove();
			}
		});

		var searchEl = modal.querySelector('#jejak-icon-search');
		if (searchEl) {
			searchEl.addEventListener('input', function () {
				var query = this.value.toLowerCase();
				modal.querySelectorAll('.jejak-icon-option').forEach(function (btn) {
					var key = btn.dataset.iconKey || '';
					btn.style.display = key.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
				});
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
	}

	function createTodoItem(item, index) {
		var div = document.createElement('div');
		div.className = 'jejak-todo-item' + (item.imported ? ' is-imported' : '');
		div.dataset.index = index;
		var checked = item.done ? ' checked' : '';
		var disabled = item.imported ? ' disabled' : '';
		div.innerHTML =
			'<div class="jejak-todo-row">' +
				'<span class="jejak-todo-custom-check" data-action="toggle-todo"></span>' +
				'<input type="checkbox" class="jejak-todo-checkbox"' + checked + disabled + '>' +
				'<span class="jejak-todo-text" contenteditable="' + (item.imported ? 'false' : 'true') + '">' + escapeHtml(item.text || '') + '</span>' +
			'</div>' +
			'<button type="button" class="jejak-remove-btn" data-action="remove" title="Remove">\u00D7</button>';
		return div;
	}

	function getTodosFromDOM() {
		if (!todosList) return [];
		var items = todosList.querySelectorAll('.jejak-todo-item');
		return Array.prototype.map.call(items, function (el) {
			return {
				text: el.querySelector('.jejak-todo-text').textContent.trim(),
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
					saveTodos();
				}
				return;
			}

			// Remove button
			var btn = e.target.closest('button');
			if (btn && btn.dataset.action === 'remove') {
				btn.closest('.jejak-todo-item').remove();
				saveTodos();
			}
		});

		todosList.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-todo-text')) {
				saveTodosDebounced();
			}
		});
	}

	var todosSaveTimer;
	function saveTodosDebounced() {
		clearTimeout(todosSaveTimer);
		todosSaveTimer = setTimeout(saveTodos, 800);
	}

	function saveTodos() {
		if (!currentEntry) return;
		var todos = getTodosFromDOM();
		apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/todos', {
			method: 'PUT',
			body: JSON.stringify(todos),
		}).then(function () {
			showSaved();
		}).catch(function () { /* silent */ });
	}

	// ── Import Todos ─────────────────────────────────
	function updateImportButton() {
		if (!importTodosBtn) return;
		if (isCurrentMonth()) {
			importTodosBtn.style.display = '';
		} else {
			importTodosBtn.style.display = 'none';
		}
	}

	importTodosBtn.addEventListener('click', function () {
		var prevMonth = currentMonth - 1;
		var prevYear = currentYear;
		if (prevMonth < 1) {
			prevMonth = 12;
			prevYear--;
		}

		apiFetch(getEntryUrl(prevYear, prevMonth)).then(function (prevEntry) {
			var prevTodos = prevEntry.todos || [];
			var toImport = prevTodos.filter(function (t) {
				return !t.done && !t.imported;
			});

			if (toImport.length === 0) {
				alert('No unchecked items to import from ' + monthNames[prevMonth - 1] + ' ' + prevYear + '.');
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
					imported: true,
				});
			});

			var p1 = apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/todos', {
				method: 'PUT',
				body: JSON.stringify(currentTodos),
			});
			var p2 = apiFetch(data.rest_url + '/entry/' + prevEntry.id + '/todos', {
				method: 'PUT',
				body: JSON.stringify(prevTodos),
			});

			Promise.all([p1, p2]).then(function () {
				apiFetch(getEntryUrl(currentYear, currentMonth)).then(function (entry) {
					currentEntry = entry;
					renderAll(entry);
					showSaved();
				});
			}).catch(function () {
				alert('Failed to import todos.');
			});
		}).catch(function () {
			alert('No previous month entry found.');
		});
	});

	if (addTodo) {
		addTodo.addEventListener('click', function () {
			var newItem = { text: '', done: false, imported: false };
			var item = createTodoItem(newItem, todosList.children.length);
			todosList.appendChild(item);
			item.querySelector('.jejak-todo-text').focus();
			saveTodos();
		});
	}

	// ── Journal Notes ────────────────────────────────
	function renderJournal(notes) {
		if (!journalTbody) return;
		journalTbody.innerHTML = '';
		if (!notes || !notes.length) return;
		notes.forEach(function (note, index) {
			var tr = document.createElement('tr');
			tr.className = 'jejak-journal-row';
			tr.dataset.index = index;
			var dayName = note.day_name || '';
			var dayNameShort = dayName.substring(0, 3);
			if (dayNameShort === 'Sat' || dayNameShort === 'Sun') {
				tr.classList.add('is-weekend');
			}
			tr.innerHTML =
				'<td class="jejak-col-day">' + note.day + ' <span class="jejak-day-name">' + dayNameShort + '</span></td>' +
				'<td class="jejak-col-notes">' +
					'<textarea class="jejak-note-textarea" rows="3" data-index="' + index + '">' + escapeHtml(note.notes || '') + '</textarea>' +
				'</td>';
			journalTbody.appendChild(tr);
		});

		// Auto-resize after render — defer to next frame for accurate scrollHeight
		journalTbody.querySelectorAll('.jejak-note-textarea').forEach(function (ta) {
			requestAnimationFrame(function () {
				autoResize(ta);
			});
		});
	}

	function getNotesFromDOM() {
		if (!journalTbody) return [];
		var rows = journalTbody.querySelectorAll('.jejak-journal-row');
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

	function autoResize(textarea) {
		// Reset to get true scrollHeight
		textarea.style.height = 'auto';
		var h = Math.max(textarea.scrollHeight, 60);
		textarea.style.height = h + 'px';
	}

	if (journalTbody) {
		journalTbody.addEventListener('input', function (e) {
			if (e.target.classList.contains('jejak-note-textarea')) {
				autoResize(e.target);
				saveNotesDebounced();
			}
		});
	}

	var notesSaveTimer;
	function saveNotesDebounced() {
		clearTimeout(notesSaveTimer);
		notesSaveTimer = setTimeout(saveNotes, 1000);
	}

	function saveNotes() {
		if (!currentEntry) return;
		var notes = getNotesFromDOM();
		apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/notes', {
			method: 'PUT',
			body: JSON.stringify(notes),
		}).then(function () {
			showSaved();
		}).catch(function () { /* silent */ });
	}

	// ── Utility ──────────────────────────────────────
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// ── Init ─────────────────────────────────────────
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
