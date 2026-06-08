/**
 * Jejak Journal — Frontend JavaScript
 * Vanilla JS, no jQuery.
 */
(function () {
	'use strict';

	const data = window.jejakJournalData || {};
	const app = document.getElementById('jejak-journal-app');
	if (!app) return;

	const yearSelect   = document.getElementById('jejak-year-select');
	const monthSelect  = document.getElementById('jejak-month-select');
	const contentEl    = document.getElementById('jejak-content');
	const noEntryEl    = document.getElementById('jejak-no-entry');
	const sectionsEl   = document.getElementById('jejak-sections');
	const createBtn    = document.getElementById('jejak-create-entry');
	const savedToast   = document.getElementById('jejak-saved-toast');
	const titleEl      = app.querySelector('.jejak-title');
	const loadingEl    = contentEl ? contentEl.querySelector('.jejak-loading') : null;

	const highlightsList = document.getElementById('jejak-highlights-list');
	const todosList      = document.getElementById('jejak-todos-list');
	const journalTbody   = document.getElementById('jejak-journal-tbody');

	let currentYear   = parseInt(app.dataset.year, 10);
	let currentMonth  = parseInt(app.dataset.month, 10);
	let currentEntry  = null;
	let saveTimer     = null;

	const monthNames = [
		'January', 'February', 'March', 'April', 'May', 'June',
		'July', 'August', 'September', 'October', 'November', 'December'
	];

	// ── API Helpers ──────────────────────────────────
	async function apiFetch(url, options) {
		const res = await fetch(url, {
			headers: {
				'X-WP-Nonce': data.rest_nonce,
				'Content-Type': 'application/json',
			},
			...options,
		});
		if (!res.ok) throw new Error('API error ' + res.status);
		return res.json();
	}

	function getEntryUrl(year, month) {
		return data.rest_url + '/entry/' + year + '/' + month;
	}

	// ── Save Notification ────────────────────────────
	function showSaved() {
		savedToast.classList.add('is-visible');
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			savedToast.classList.remove('is-visible');
		}, 2000);
	}

	// ── Load Entry ───────────────────────────────────
	async function loadEntry(year, month) {
		showLoading();
		try {
			const entry = await apiFetch(getEntryUrl(year, month));
			currentEntry = entry;
			renderAll(entry);
			showSections();
		} catch (err) {
			showNoEntry();
		}
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

	// ── Switch Month/Year ────────────────────────────
	async function switchTo(year, month) {
		currentYear  = year;
		currentMonth = month;
		updateTitle(year, month);
		try {
			const entry = await apiFetch(getEntryUrl(year, month));
			currentEntry = entry;
			renderAll(entry);
			showSections();
		} catch (err) {
			showNoEntry();
		}
	}

	yearSelect.addEventListener('change', function () {
		switchTo(parseInt(this.value, 10), currentMonth);
	});
	monthSelect.addEventListener('change', function () {
		switchTo(currentYear, parseInt(this.value, 10));
	});

	// ── Create Entry ─────────────────────────────────
	createBtn.addEventListener('click', async function () {
		try {
			const entry = await apiFetch(getEntryUrl(currentYear, currentMonth), { method: 'POST' });
			currentEntry = entry;
			renderAll(entry);
			showSections();
		} catch (err) {
			alert(data.i18n.error || 'Could not create entry.');
		}
	});

	// ── Render All Sections ──────────────────────────
	function renderAll(entry) {
		renderHighlights(entry.highlights || []);
		renderTodos(entry.todos || []);
		renderJournal(entry.journal_notes || []);
	}

	// ── Highlights ───────────────────────────────────
	function renderHighlights(items) {
		highlightsList.innerHTML = '';
		items.forEach(function (item, index) {
			highlightsList.appendChild(createHighlightItem(item, index));
		});
	}

	function createHighlightItem(item, index) {
		const div = document.createElement('div');
		div.className = 'jejak-highlight-item';
		div.dataset.index = index;
		div.innerHTML =
			'<button type="button" class="jejak-icon-picker-btn" data-action="pick-icon" title="' + (data.i18n.select_icon || 'Select icon') + '">' +
				(item.icon || '⭐') +
			'</button>' +
			'<input type="text" class="jejak-highlight-text" value="' + escapeHtml(item.text || '') + '" placeholder="What happened?">' +
			'<button type="button" class="jejak-remove-btn" data-action="remove" title="Remove">×</button>';
		return div;
	}

	function getHighlightsFromDOM() {
		const items = highlightsList.querySelectorAll('.jejak-highlight-item');
		return Array.from(items).map(function (el) {
			return {
				icon: el.querySelector('.jejak-icon-picker-btn').textContent.trim(),
				text: el.querySelector('.jejak-highlight-text').value,
			};
		});
	}

	highlightsList.addEventListener('click', function (e) {
		const btn = e.target.closest('button');
		if (!btn) return;
		const action = btn.dataset.action;
		const item = btn.closest('.jejak-highlight-item');

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

	let highlightsSaveTimer;
	function saveHighlightsDebounced() {
		clearTimeout(highlightsSaveTimer);
		highlightsSaveTimer = setTimeout(saveHighlights, 800);
	}

	async function saveHighlights() {
		if (!currentEntry) return;
		const highlights = getHighlightsFromDOM();
		try {
			await apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/highlights', {
				method: 'PUT',
				body: JSON.stringify(highlights),
			});
			showSaved();
		} catch (err) {
			/* silent */
		}
	}

	document.getElementById('jejak-add-highlight').addEventListener('click', function () {
		const newItem = { icon: '⭐', text: '' };
		const item = createHighlightItem(newItem, highlightsList.children.length);
		highlightsList.appendChild(item);
		item.querySelector('.jejak-highlight-text').focus();
		saveHighlights();
	});

	// ── Icon Picker ──────────────────────────────────
	function openIconPicker(triggerBtn) {
		const existing = document.getElementById('jejak-icon-modal');
		if (existing) existing.remove();

		const icons = data.icons || {};
		let iconHtml = '';
		Object.keys(icons).forEach(function (key) {
			iconHtml += '<button type="button" class="jejak-icon-option" data-icon="' + key + '">' + icons[key] + '</button>';
		});

		const modal = document.createElement('div');
		modal.id = 'jejak-icon-modal';
		modal.className = 'jejak-icon-modal';
		modal.innerHTML =
			'<div class="jejak-icon-modal-backdrop" data-action="close-modal"></div>' +
			'<div class="jejak-icon-modal-content">' +
				'<h3>' + (data.i18n.select_icon || 'Select icon') + '</h3>' +
				'<input type="text" class="jejak-icon-search" id="jejak-icon-search" placeholder="' + (data.i18n.search_icon || 'Search icons...') + '">' +
				'<div class="jejak-icon-grid">' + iconHtml + '</div>' +
				'<button type="button" class="jejak-btn jejak-modal-close" data-action="close-modal">×</button>' +
			'</div>';

		document.body.appendChild(modal);

		// Focus search
		setTimeout(function () {
			document.getElementById('jejak-icon-search').focus();
		}, 50);

		// Icon click
		modal.querySelector('.jejak-icon-grid').addEventListener('click', function (e) {
			const iconBtn = e.target.closest('.jejak-icon-option');
			if (!iconBtn) return;
			const emoji = iconBtn.textContent.trim();
			triggerBtn.textContent = emoji;
			modal.remove();
			saveHighlights();
		});

		// Close
		modal.addEventListener('click', function (e) {
			if (e.target.dataset.action === 'close-modal' || e.target.classList.contains('jejak-icon-modal-backdrop')) {
				modal.remove();
			}
		});

		// Search filter
		modal.querySelector('#jejak-icon-search').addEventListener('input', function () {
			const query = this.value.toLowerCase();
			modal.querySelectorAll('.jejak-icon-option').forEach(function (btn) {
				const key = btn.dataset.icon || '';
				btn.style.display = key.toLowerCase().includes(query) ? '' : 'none';
			});
		});
	}

	// ── ToDos ────────────────────────────────────────
	function renderTodos(items) {
		todosList.innerHTML = '';
		items.forEach(function (item, index) {
			todosList.appendChild(createTodoItem(item, index));
		});
	}

	function createTodoItem(item, index) {
		const div = document.createElement('div');
		div.className = 'jejak-todo-item';
		div.dataset.index = index;
		const checked = item.done ? ' checked' : '';
		div.innerHTML =
			'<label class="jejak-todo-label">' +
				'<input type="checkbox" class="jejak-todo-checkbox"' + checked + '>' +
				'<span class="jejak-todo-custom-check"></span>' +
				'<span class="jejak-todo-text" contenteditable="true">' + escapeHtml(item.text || '') + '</span>' +
			'</label>' +
			'<button type="button" class="jejak-remove-btn" data-action="remove" title="Remove">×</button>';
		return div;
	}

	function getTodosFromDOM() {
		const items = todosList.querySelectorAll('.jejak-todo-item');
		return Array.from(items).map(function (el) {
			return {
				text: el.querySelector('.jejak-todo-text').textContent.trim(),
				done: el.querySelector('.jejak-todo-checkbox').checked,
			};
		});
	}

	todosList.addEventListener('click', function (e) {
		const btn = e.target.closest('button');
		if (btn && btn.dataset.action === 'remove') {
			btn.closest('.jejak-todo-item').remove();
			saveTodos();
		}
	});

	todosList.addEventListener('change', function (e) {
		if (e.target.classList.contains('jejak-todo-checkbox')) {
			saveTodos();
		}
	});

	todosList.addEventListener('input', function (e) {
		if (e.target.classList.contains('jejak-todo-text')) {
			saveTodosDebounced();
		}
	});

	let todosSaveTimer;
	function saveTodosDebounced() {
		clearTimeout(todosSaveTimer);
		todosSaveTimer = setTimeout(saveTodos, 800);
	}

	async function saveTodos() {
		if (!currentEntry) return;
		const todos = getTodosFromDOM();
		try {
			await apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/todos', {
				method: 'PUT',
				body: JSON.stringify(todos),
			});
			showSaved();
		} catch (err) {
			/* silent */
		}
	}

	document.getElementById('jejak-add-todo').addEventListener('click', function () {
		const newItem = { text: '', done: false };
		const item = createTodoItem(newItem, todosList.children.length);
		todosList.appendChild(item);
		item.querySelector('.jejak-todo-text').focus();
		saveTodos();
	});

	// ── Journal Notes ────────────────────────────────
	function renderJournal(notes) {
		journalTbody.innerHTML = '';
		if (!notes || !notes.length) return;
		notes.forEach(function (note, index) {
			const tr = document.createElement('tr');
			tr.className = 'jejak-journal-row';
			tr.dataset.index = index;
			const dayNameShort = (note.day_name || '').substring(0, 3);
			tr.innerHTML =
				'<td class="jejak-col-day">' + note.day + ' <span class="jejak-day-name">' + dayNameShort + '</span></td>' +
				'<td class="jejak-col-date">' + note.date + '</td>' +
				'<td class="jejak-col-notes">' +
					'<textarea class="jejak-note-textarea" rows="3" data-index="' + index + '">' + escapeHtml(note.notes || '') + '</textarea>' +
				'</td>';
			journalTbody.appendChild(tr);
		});

		// Auto-resize textareas
		journalTbody.querySelectorAll('.jejak-note-textarea').forEach(function (ta) {
			autoResize(ta);
		});
	}

	function getNotesFromDOM() {
		const rows = journalTbody.querySelectorAll('.jejak-journal-row');
		return Array.from(rows).map(function (row, idx) {
			const ta = row.querySelector('.jejak-note-textarea');
			return {
				day: idx + 1,
				date: row.querySelector('.jejak-col-date').textContent,
				day_name: currentEntry && currentEntry.journal_notes && currentEntry.journal_notes[idx]
					? currentEntry.journal_notes[idx].day_name : '',
				notes: ta ? ta.value : '',
			};
		});
	}

	function autoResize(textarea) {
		textarea.style.height = 'auto';
		textarea.style.height = Math.max(textarea.scrollHeight, 60) + 'px';
	}

	journalTbody.addEventListener('input', function (e) {
		if (e.target.classList.contains('jejak-note-textarea')) {
			autoResize(e.target);
			saveNotesDebounced();
		}
	});

	let notesSaveTimer;
	function saveNotesDebounced() {
		clearTimeout(notesSaveTimer);
		notesSaveTimer = setTimeout(saveNotes, 1000);
	}

	async function saveNotes() {
		if (!currentEntry) return;
		const notes = getNotesFromDOM();
		try {
			await apiFetch(data.rest_url + '/entry/' + currentEntry.id + '/notes', {
				method: 'PUT',
				body: JSON.stringify(notes),
			});
			showSaved();
		} catch (err) {
			/* silent */
		}
	}

	// ── Utility ──────────────────────────────────────
	function escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// ── Init ─────────────────────────────────────────
	updateTitle(currentYear, currentMonth);
	loadEntry(currentYear, currentMonth);
})();
