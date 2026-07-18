var BookViewer = (function () {
	'use strict';

	var config = {};
	var tocData = [];
	var activeId = null;
	var contentCache = {};

	var lastLoadedItem = null;

	function init() {
		config = window.BookViewerConfig || {};
		tocData = config.toc || [];

		// Hide the site sidebar on every page load
		try { localStorage.setItem('stw-sidebar-hidden', 'true'); } catch (e) {}

		if (tocData.length > 0) {
			renderToc(tocData);
			loadFinishedTopics();
			restorePoint();
		}
	}

	// --- TOC Rendering ---

	function renderToc(data) {
		var container = document.getElementById('bv-toc-tree');
		if (!container) return;
		container.innerHTML = '';
		var ul = buildTocList(data, 0);
		container.appendChild(ul);
	}

	function buildTocList(items, depth) {
		var ul = document.createElement('ul');
		ul.className = 'bv-toc-level bv-toc-level-' + depth;

		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			var li = document.createElement('li');
			li.className = 'bv-toc-item bv-toc-type-' + item.type;
			li.setAttribute('data-id', item.id);
			li.setAttribute('data-type', item.type);
			li.setAttribute('data-number', item.number);

			var row = document.createElement('div');
			row.className = 'bv-toc-row';

			// Toggle arrow for items with children
			if (item.children && item.children.length > 0) {
				var toggle = document.createElement('span');
				toggle.className = 'bv-toggle';
				toggle.textContent = '\u25B6'; // right arrow
				toggle.onclick = (function (liEl, toggleEl) {
					return function (e) {
						e.stopPropagation();
						toggleNode(liEl, toggleEl);
					};
				})(li, toggle);
				row.appendChild(toggle);
			} else {
				var spacer = document.createElement('span');
				spacer.className = 'bv-toggle-spacer';
				row.appendChild(spacer);
			}

			// Finished checkbox for topics
			if (item.type === 'topic') {
				var cb = document.createElement('input');
				cb.type = 'checkbox';
				cb.className = 'bv-finished-cb';
				cb.title = 'Mark as finished';
				cb.onchange = (function (it, liEl) {
					return function (e) {
						e.stopPropagation();
						var checked = this.checked;
						this.title = checked ? 'Mark as unfinished' : 'Mark as finished';
						if (checked) {
							liEl.classList.add('bv-finished');
						} else {
							liEl.classList.remove('bv-finished');
						}
						updateParentFinished(liEl);
						applyFinishedFilter();
						setTopicFinishedOnServer(it.id, checked);
					};
				})(item, li);
				row.appendChild(cb);
			}

			var label = document.createElement('a');
			label.className = 'bv-toc-label';
			label.href = 'javascript:void(0)';
			label.innerHTML = '<span class="bv-num">' + escapeHtml(item.number) + '</span> ' + escapeHtml(item.title);
			if (item.count) {
				label.innerHTML += ' <span class="bv-count">(' + escapeHtml(item.count) + ')</span>';
			}
			label.onclick = (function (it) {
				return function (e) {
					e.preventDefault();
					loadSection(it);
				};
			})(item);
			row.appendChild(label);

			li.appendChild(row);

			// Build children (collapsed by default)
			if (item.children && item.children.length > 0) {
				var childUl = buildTocList(item.children, depth + 1);
				childUl.style.display = 'none';
				li.appendChild(childUl);
			}

			ul.appendChild(li);
		}

		return ul;
	}

	function toggleNode(li, toggleEl) {
		var childUl = li.querySelector(':scope > ul');
		if (!childUl) return;

		if (childUl.style.display === 'none') {
			childUl.style.display = 'block';
			toggleEl.textContent = '\u25BC'; // down arrow
			li.classList.add('bv-expanded');
		} else {
			childUl.style.display = 'none';
			toggleEl.textContent = '\u25B6'; // right arrow
			li.classList.remove('bv-expanded');
		}
	}

	// --- Section Loading ---

	function loadSection(item) {
		var cacheKey = config.selectedBook + ':' + item.id;

		// Highlight active item
		setActive(item.id);

		// Auto-expand parent nodes when clicking a child 
		expandParentsOf(item.id);

		// Check cache
		if (contentCache[cacheKey]) {
			displayContent(contentCache[cacheKey], item);
			return;
		}

		var contentArea = document.getElementById('bv-content-area');
		contentArea.innerHTML = '<div class="bv-loading">Loading...</div>';

		var url = config.ajaxUrl
			+ '?book=' + encodeURIComponent(config.selectedBook)
			+ '&section=' + encodeURIComponent(item.id)
			+ '&type=' + encodeURIComponent(item.type);

		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					try {
						var data = JSON.parse(xhr.responseText);
						if (data.error) {
							contentArea.innerHTML = '<p class="bv-error">' + escapeHtml(data.error) + '</p>';
						} else {
							contentCache[cacheKey] = data.html;
							displayContent(data.html, item);
						}
					} catch (e) {
						contentArea.innerHTML = '<p class="bv-error">Error parsing response.</p>';
					}
				} else {
					contentArea.innerHTML = '<p class="bv-error">Failed to load section.</p>';
				}
			}
		};
		xhr.send();
	}

	function getFlatTopics() {
		var topics = [];
		for (var i = 0; i < tocData.length; i++) {
			var cat = tocData[i];
			if (cat.children) {
				for (var j = 0; j < cat.children.length; j++) {
					if (cat.children[j].type === 'topic') {
						topics.push(cat.children[j]);
					}
				}
			}
		}
		return topics;
	}

	function getFlatQuestions() {
		var questions = [];
		for (var i = 0; i < tocData.length; i++) {
			var cat = tocData[i];
			if (!cat.children) continue;
			for (var j = 0; j < cat.children.length; j++) {
				var child = cat.children[j];
				if (child.type === 'topic' && child.children) {
					for (var k = 0; k < child.children.length; k++) {
						if (child.children[k].type === 'question') {
							questions.push(child.children[k]);
						}
					}
				} else if (child.type === 'question') {
					questions.push(child);
				}
			}
		}
		return questions;
	}

	function buildNavBar(item) {
		var html = '<div class="bv-nav-bar">';

		if (item.type === 'topic') {
			var topics = getFlatTopics();
			var idx = -1;
			for (var i = 0; i < topics.length; i++) {
				if (topics[i].id === item.id) { idx = i; break; }
			}
			if (idx === -1) return '';
			var prev = idx > 0 ? topics[idx - 1] : null;
			var next = idx < topics.length - 1 ? topics[idx + 1] : null;
			if (prev) {
				html += '<button class="bv-nav-btn bv-nav-prev" data-idx="' + (idx - 1) + '" data-mode="topic">&larr; ' + escapeHtml(prev.number + ' ' + prev.title) + '</button>';
			} else {
				html += '<span></span>';
			}
			if (next) {
				html += '<button class="bv-nav-btn bv-nav-next" data-idx="' + (idx + 1) + '" data-mode="topic">' + escapeHtml(next.number + ' ' + next.title) + ' &rarr;</button>';
			} else {
				html += '<span></span>';
			}

		} else if (item.type === 'question') {
			var questions = getFlatQuestions();
			var idx = -1;
			for (var i = 0; i < questions.length; i++) {
				if (questions[i].id === item.id) { idx = i; break; }
			}
			if (idx === -1) return '';
			var prev = idx > 0 ? questions[idx - 1] : null;
			var next = idx < questions.length - 1 ? questions[idx + 1] : null;
			if (prev) {
				html += '<button class="bv-nav-btn bv-nav-prev" data-idx="' + (idx - 1) + '" data-mode="question">&larr; ' + escapeHtml(prev.number + ' ' + prev.title) + '</button>';
			} else {
				html += '<span></span>';
			}
			if (next) {
				html += '<button class="bv-nav-btn bv-nav-next" data-idx="' + (idx + 1) + '" data-mode="question">' + escapeHtml(next.number + ' ' + next.title) + ' &rarr;</button>';
			} else {
				html += '<span></span>';
			}

		} else {
			return '';
		}

		html += '</div>';
		return html;
	}

	function attachNavListeners() {
		var btns = document.querySelectorAll('.bv-nav-btn');
		var topics = getFlatTopics();
		var questions = getFlatQuestions();
		for (var i = 0; i < btns.length; i++) {
			btns[i].addEventListener('click', (function(btn) {
				return function() {
					var idx = parseInt(btn.getAttribute('data-idx'), 10);
					var mode = btn.getAttribute('data-mode');
					if (mode === 'question') {
						if (questions[idx]) loadSection(questions[idx]);
					} else {
						if (topics[idx]) loadSection(topics[idx]);
					}
				};
			})(btns[i]));
		}
	}

	function displayContent(html, item) {
		lastLoadedItem = item;
		var contentArea = document.getElementById('bv-content-area');
		var navBar = buildNavBar(item);
		contentArea.innerHTML = navBar + html + navBar;

		// Re-render MathJax if available
		if (window.MathJax) {
			if (MathJax.Hub && MathJax.Hub.Queue) {
				MathJax.Hub.Queue(['Typeset', MathJax.Hub, contentArea]);
			} else if (MathJax.typesetPromise) {
				MathJax.typesetPromise([contentArea]).catch(function() {});
			}
		}

		// Re-run code prettify if available.
		// lang-c_cpp is already normalised to lang-cpp by the server, so
		// PR.prettyPrint can find the registered C/C++ handler directly.
		if (window.PR && PR.prettyPrint) {
			var blocks = contentArea.querySelectorAll('pre.prettyprint');
			for (var pi = 0; pi < blocks.length; pi++) {
				blocks[pi].classList.remove('prettyprinted');
			}
			PR.prettyPrint(null, contentArea);
		}

		// Make all links open in a new tab
		var links = contentArea.querySelectorAll('a[href^="http"]');
		for (var i = 0; i < links.length; i++) {
			links[i].setAttribute('target', '_blank');
			links[i].setAttribute('rel', 'noopener');
		}

		// Attach prev/next button listeners
		attachNavListeners();

		// Inject per-question save point pins
		injectQuestionPins();

		// Re-apply active tag filter
		var tagInput = document.getElementById('bv-tag-filter');
		if (tagInput && tagInput.value.trim()) {
			filterByTag(tagInput.value);
		}

		// Fetch and inject lists buttons (only if lists plugin is available)
		if (config.listsEnabled) injectListButtons();

		// Fetch and inject notes buttons (only if notes plugin is available)
		if (config.notesEnabled) injectNoteButtons();

		// Inject question status buttons and load statuses
		injectStatusButtons();
		loadQuestionStatuses();
		initStatusFilter();
		activeStatusFilters = [];
		applyStatusFilter();

		// Apply practice mode if active
		if (practiceMode) applyPracticeMode();

		// Scroll content to top
		document.getElementById('bv-content').scrollTop = 0;
	}

	function setActive(id) {
		// Remove previous active
		var prev = document.querySelector('.bv-toc-row.bv-active');
		if (prev) prev.classList.remove('bv-active');

		// Set new active
		var li = document.querySelector('.bv-toc-item[data-id="' + id + '"]');
		if (li) {
			var row = li.querySelector(':scope > .bv-toc-row');
			if (row) row.classList.add('bv-active');
		}
		activeId = id;
	}

	function expandParentsOf(id) {
		var li = document.querySelector('.bv-toc-item[data-id="' + id + '"]');
		if (!li) return;

		var parent = li.parentElement;
		while (parent) {
			if (parent.tagName === 'UL' && parent.style.display === 'none') {
				parent.style.display = 'block';
				var parentLi = parent.parentElement;
				if (parentLi && parentLi.classList.contains('bv-toc-item')) {
					parentLi.classList.add('bv-expanded');
					var toggle = parentLi.querySelector(':scope > .bv-toc-row > .bv-toggle');
					if (toggle) toggle.textContent = '\u25BC';
				}
			}
			parent = parent.parentElement;
		}
	}

	// --- Toolbar Actions ---

	function selectBook(slug) {
		if (slug) {
			window.location.href = config.rootUrl + '/' + encodeURIComponent(slug);
		}
	}

	function filterToc(query) {
		query = query.toLowerCase().trim();
		var items = document.querySelectorAll('.bv-toc-item');

		if (!query) {
			// Show all
			for (var i = 0; i < items.length; i++) {
				items[i].style.display = '';
			}
			return;
		}

		// First pass: mark matches
		for (var i = 0; i < items.length; i++) {
			var row = items[i].querySelector(':scope > .bv-toc-row .bv-toc-label');
			var text = row ? row.textContent.toLowerCase() : '';
			if (text.indexOf(query) >= 0) {
				items[i].setAttribute('data-match', '1');
			} else {
				items[i].setAttribute('data-match', '0');
			}
		}

		// Second pass: show matched items and their ancestors and descendants
		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			var isMatch = item.getAttribute('data-match') === '1';
			var hasMatchedChild = item.querySelector('.bv-toc-item[data-match="1"]') !== null;
			var hasMatchedParent = false;
			var p = item.parentElement;
			while (p) {
				if (p.classList && p.classList.contains('bv-toc-item') && p.getAttribute('data-match') === '1') {
					hasMatchedParent = true;
					break;
				}
				p = p.parentElement;
			}

			if (isMatch || hasMatchedChild || hasMatchedParent) {
				item.style.display = '';
				// Auto-expand to show matches
				if (hasMatchedChild) {
					var childUl = item.querySelector(':scope > ul');
					if (childUl) childUl.style.display = 'block';
					var toggle = item.querySelector(':scope > .bv-toc-row > .bv-toggle');
					if (toggle) toggle.textContent = '\u25BC';
					item.classList.add('bv-expanded');
				}
			} else {
				item.style.display = 'none';
			}
		}
	}

	function expandAll() {
		var items = document.querySelectorAll('.bv-toc-item');
		for (var i = 0; i < items.length; i++) {
			var childUl = items[i].querySelector(':scope > ul');
			if (childUl) {
				childUl.style.display = 'block';
				items[i].classList.add('bv-expanded');
				var toggle = items[i].querySelector(':scope > .bv-toc-row > .bv-toggle');
				if (toggle) toggle.textContent = '\u25BC';
			}
		}
	}

	function collapseAll() {
		var items = document.querySelectorAll('.bv-toc-item');
		for (var i = 0; i < items.length; i++) {
			var childUl = items[i].querySelector(':scope > ul');
			if (childUl) {
				childUl.style.display = 'none';
				items[i].classList.remove('bv-expanded');
				var toggle = items[i].querySelector(':scope > .bv-toc-row > .bv-toggle');
				if (toggle) toggle.textContent = '\u25B6';
			}
		}
	}

	function toggleSidebar() {
		var app = document.getElementById('book-viewer-app');
		app.classList.toggle('bv-sidebar-hidden');
	}

	function toggleFullscreen() {
		var app = document.getElementById('book-viewer-app');
		var isFullscreen = app.classList.contains('bv-fullscreen');

		if (!isFullscreen) {
			// Enter fullscreen
			app.classList.add('bv-fullscreen');
			var el = app;
			if (el.requestFullscreen) el.requestFullscreen();
			else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
			else if (el.msRequestFullscreen) el.msRequestFullscreen();
		} else {
			// Exit fullscreen
			exitFullscreen();
		}
	}

	function exitFullscreen() {
		var app = document.getElementById('book-viewer-app');
		app.classList.remove('bv-fullscreen');
		if (document.exitFullscreen) document.exitFullscreen();
		else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
		else if (document.msExitFullscreen) document.msExitFullscreen();
	}

	// Sync class when browser exits fullscreen (e.g. user presses Escape natively)
	document.addEventListener('fullscreenchange', function () {
		if (!document.fullscreenElement) {
			var app = document.getElementById('book-viewer-app');
			if (app) app.classList.remove('bv-fullscreen');
		}
	});
	document.addEventListener('webkitfullscreenchange', function () {
		if (!document.webkitFullscreenElement) {
			var app = document.getElementById('book-viewer-app');
			if (app) app.classList.remove('bv-fullscreen');
		}
	});

	// --- Save Point ---

	function getSavedPoint() {
		if (!config.selectedBook) return null;
		try {
			var raw = localStorage.getItem('bv_savepoint_' + config.selectedBook);
			if (!raw) return null;
			return JSON.parse(raw);
		} catch (e) { return null; }
	}

	function restorePoint() {
		if (!config.selectedBook) return;
		var data = getSavedPoint();
		if (data && data.id && data.type) {
			loadSection(data);
			showToast('Restored: ' + data.number + ' ' + data.title);
		}
	}

	function injectQuestionPins() {
		var titles = document.querySelectorAll('#bv-content-area .question-title');
		for (var i = 0; i < titles.length; i++) {
			var titleEl = titles[i];
			// The id is on the <a> inside the title div, not on the div itself
			var anchor = titleEl.querySelector('a[id^="question"]');
			if (!anchor) continue;
			var qId = anchor.getAttribute('id');

			// Extract number and title text from the anchor
			var numEl = titleEl.querySelector('.number');
			var qNumber = numEl ? numEl.textContent.trim() : '';
			var qTitle = '';
			if (anchor) {
				// Title text is the anchor text minus the number span
				var clone = anchor.cloneNode(true);
				var numSpan = clone.querySelector('.number');
				if (numSpan) numSpan.remove();
				qTitle = clone.textContent.trim();
			}

			var pin = document.createElement('span');
			pin.className = 'bv-q-pin';
			pin.setAttribute('data-qid', qId);
			pin.setAttribute('data-qnum', qNumber);
			pin.setAttribute('data-qtitle', qTitle);
			pin.title = 'Save point here';
			pin.innerHTML = '&#x1F4CC;';
			pin.onclick = (function (el) {
				return function (e) {
					e.stopPropagation();
					pinQuestion(el);
				};
			})(pin);
			titleEl.appendChild(pin);
		}
		updateQuestionPins();
	}

	function pinQuestion(pinEl) {
		var qId = pinEl.getAttribute('data-qid');
		var qNumber = pinEl.getAttribute('data-qnum');
		var qTitle = pinEl.getAttribute('data-qtitle');
		var saved = getSavedPoint();

		if (saved && saved.id === qId) {
			// Remove
			try { localStorage.removeItem('bv_savepoint_' + config.selectedBook); } catch (e) {}
			showToast('Save point removed');
		} else {
			// Save
			var data = {
				book: config.selectedBook,
				id: qId,
				type: 'question',
				number: qNumber,
				title: qTitle
			};
			try {
				localStorage.setItem('bv_savepoint_' + config.selectedBook, JSON.stringify(data));
				var verb = saved ? 'Updated' : 'Saved';
				showToast(verb + ': ' + qNumber + ' ' + qTitle);
			} catch (e) {
				showToast('Could not save position');
			}
		}
		updateQuestionPins();
	}

	function updateQuestionPins() {
		var saved = getSavedPoint();
		var pins = document.querySelectorAll('.bv-q-pin');
		for (var i = 0; i < pins.length; i++) {
			var qId = pins[i].getAttribute('data-qid');
			if (saved && saved.id === qId) {
				pins[i].classList.add('bv-q-pin-active');
				pins[i].title = 'Remove save point';
			} else {
				pins[i].classList.remove('bv-q-pin-active');
				pins[i].title = 'Save point here';
			}
		}
	}

	function showToast(msg) {
		var existing = document.getElementById('bv-toast');
		if (existing) existing.remove();
		var el = document.createElement('div');
		el.id = 'bv-toast';
		el.textContent = msg;
		document.getElementById('book-viewer-app').appendChild(el);
		setTimeout(function () { el.classList.add('bv-toast-show'); }, 10);
		setTimeout(function () {
			el.classList.remove('bv-toast-show');
			setTimeout(function () { el.remove(); }, 300);
		}, 2500);
	}

	// --- Tag Filter ---

	function filterByTag(query) {
		query = query.toLowerCase().trim();
		var questions = document.querySelectorAll('#bv-content-area .question');
		var countEl = document.getElementById('bv-tag-count');

		if (!questions.length) {
			if (countEl) countEl.textContent = '';
			return;
		}

		if (!query) {
			// Show all
			for (var i = 0; i < questions.length; i++) {
				questions[i].style.display = '';
			}
			if (countEl) countEl.textContent = '';
			return;
		}

		var terms = query.split(/[,\s]+/).filter(function (t) { return t.length > 0; });
		var shown = 0;
		for (var i = 0; i < questions.length; i++) {
			var tags = questions[i].querySelectorAll('.qa-tag-link');
			var tagTexts = [];
			for (var j = 0; j < tags.length; j++) {
				tagTexts.push(tags[j].textContent.trim().toLowerCase());
			}
			var match = terms.every(function (term) {
				return tagTexts.some(function (t) { return t === term; });
			});
			questions[i].style.display = match ? '' : 'none';
			if (match) shown++;
		}
		if (countEl) {
			countEl.textContent = shown + '/' + questions.length;
		}
	}

	// --- Lists Integration ---

	var activePopupQid = null;

	function getPostIdFromTitle(titleEl) {
		var anchor = titleEl.querySelector('a[id^="question"]');
		if (!anchor) return null;
		var m = anchor.getAttribute('id').match(/^question(\d+)$/);
		return m ? parseInt(m[1], 10) : null;
	}

	function getSiteUrlFromTitle(titleEl) {
		var anchor = titleEl.querySelector('a[id^="question"]');
		if (!anchor || !anchor.href) return '';
		return anchor.href;
		try {
			var u = new URL(anchor.href);
			return u.origin;
		} catch (e) { return ''; }
	}

	function injectListButtons() {
		var titles = document.querySelectorAll('#bv-content-area .question-title');
		for (var i = 0; i < titles.length; i++) {
			if (titles[i].querySelector('.bv-lists-btn')) continue;
			var postId = getPostIdFromTitle(titles[i]);
			if (!postId) continue;
			var siteUrl = getSiteUrlFromTitle(titles[i]);

			var btn = document.createElement('button');
			btn.className = 'bv-lists-btn';
			btn.setAttribute('data-postid', postId);
			btn.setAttribute('data-siteurl', siteUrl);
			btn.title = 'Manage it in lists';
			btn.innerHTML = '&#9734;';
			btn.onclick = (function (pid, su, btnEl) {
				return function (e) {
					e.stopPropagation();
					e.preventDefault();
					openListsPopup(pid, su, btnEl);
				};
			})(postId, siteUrl, btn);
			titles[i].appendChild(btn);
		}
	}

	function openListsPopup(postId, siteUrl, anchorEl) {
		closeListsPopup();
		activePopupQid = postId;

		// Show loading popup
		var popup = document.createElement('div');
		popup.id = 'bv-lists-popup';
		popup.innerHTML = '<div class="bv-lists-popup-body" style="padding:12px;text-align:center;">Loading...</div>';
		var rect = anchorEl.getBoundingClientRect();
		popup.style.position = 'fixed';
		popup.style.top = (rect.bottom + 4) + 'px';
		popup.style.left = Math.min(rect.left, window.innerWidth - 240) + 'px';
		popup.style.zIndex = '10000';
		// Append inside the app so it is visible in native fullscreen mode
		(document.getElementById('book-viewer-app') || document.body).appendChild(popup);

		// Fetch list data for this question
		var url = config.ajaxUrl + '?type=lists&postid=' + postId
			+ (siteUrl ? '&siteurl=' + encodeURIComponent(siteUrl) : '');
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				var p = document.getElementById('bv-lists-popup');
				if (!p) return;
				if (xhr.status === 200) {
					try {
						var data = JSON.parse(xhr.responseText);
						if (!data.loggedIn) {
							p.innerHTML = '<div class="bv-lists-popup-body" style="padding:12px;">Please log in to use lists.</div>';
							return;
						}
						renderListsPopup(p, postId, siteUrl, data);
					} catch (e) {
						p.innerHTML = '<div class="bv-lists-popup-body" style="padding:12px;">Error loading lists.</div>';
					}
				} else {
					p.innerHTML = '<div class="bv-lists-popup-body" style="padding:12px;">Error loading lists.</div>';
				}
			}
		};
		xhr.send();

		setTimeout(function () {
			document.addEventListener('mousedown', outsideClickHandler);
		}, 0);
	}

	function renderListsPopup(popup, postId, siteUrl, data) {
		var checkedLists = data.checkedLists || [];
		var suParam = siteUrl ? ',\'' + siteUrl.replace(/'/g, '') + '\'' : ',\'\''; 
		var html = '<div class="bv-lists-popup-header"><span>My Lists</span>'
			+ '<button class="bv-lists-popup-close" onclick="document.getElementById(\'bv-lists-popup\').remove()">&times;</button></div>'
			+ '<div class="bv-lists-popup-body">';

		for (var i = 0; i <= data.listCount; i++) {
			var checked = checkedLists.indexOf(i) >= 0 ? ' checked' : '';
			var name = data.listNames[i] || ('List ' + i);
			html += '<label class="bv-lists-check-label">'
				+ '<input type="checkbox" class="bv-lists-check" value="' + i + '"' + checked
				+ ' onchange="BookViewer.toggleList(' + postId + ',' + i + ',this.checked' + suParam + ')">'
				+ ' ' + escapeHtml(name) + '</label>';
		}

		html += '</div>';
		popup.innerHTML = html;
	}

	function toggleList(postId, listId, checked, siteUrl) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.error) {
						showToast('Error: ' + (resp.message || resp.error));
					}
				} catch (e) {}
			}
		};
		var body = 'type=listtoggle&postid=' + postId + '&listid=' + listId + '&checked=' + (checked ? '1' : '0');
		if (siteUrl) body += '&siteurl=' + encodeURIComponent(siteUrl);
		xhr.send(body);
	}

	function outsideClickHandler(e) {
		var popup = document.getElementById('bv-lists-popup');
		if (popup && !popup.contains(e.target) && !e.target.classList.contains('bv-lists-btn')) {
			closeListsPopup();
		}
	}

	function closeListsPopup() {
		var popup = document.getElementById('bv-lists-popup');
		if (popup) popup.remove();
		activePopupQid = null;
		document.removeEventListener('mousedown', outsideClickHandler);
	}

	// --- Notes Integration ---

	function injectNoteButtons() {
		var titles = document.querySelectorAll('#bv-content-area .question-title');
		for (var i = 0; i < titles.length; i++) {
			if (titles[i].querySelector('.bv-note-btn')) continue;
			var postId = getPostIdFromTitle(titles[i]);
			if (!postId) continue;
			var siteUrl = getSiteUrlFromTitle(titles[i]);

			var btn = document.createElement('button');
			btn.className = 'bv-note-btn';
			btn.setAttribute('data-postid', postId);
			btn.setAttribute('data-siteurl', siteUrl);
			btn.title = 'Add / Edit Note';
			btn.innerHTML = '&#9998;';
			btn.onclick = (function (pid, su, btnEl) {
				return function (e) {
					e.stopPropagation();
					e.preventDefault();
					openNotePopup(pid, su, btnEl);
				};
			})(postId, siteUrl, btn);
			titles[i].appendChild(btn);
		}
	}

	function openNotePopup(postId, siteUrl, anchorEl) {
		closeNotePopup();

		var popup = document.createElement('div');
		popup.id = 'bv-note-popup';
		popup.innerHTML = '<div class="bv-note-popup-header"><span>My Note</span>'
			+ '<button class="bv-note-popup-close" onclick="document.getElementById(\'bv-note-popup\').remove()">&times;</button></div>'
			+ '<div class="bv-note-popup-body" style="padding:12px;text-align:center;">Loading...</div>';
		var rect = anchorEl.getBoundingClientRect();
		popup.style.position = 'fixed';
		popup.style.top = (rect.bottom + 4) + 'px';
		popup.style.left = Math.min(rect.left, window.innerWidth - 320) + 'px';
		popup.style.zIndex = '10000';
		// Append inside the app so it is visible in native fullscreen mode
		(document.getElementById('book-viewer-app') || document.body).appendChild(popup);

		// Load existing note
		var url = config.ajaxUrl + '?type=notes&postid=' + postId
			+ (siteUrl ? '&siteurl=' + encodeURIComponent(siteUrl) : '');
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				var p = document.getElementById('bv-note-popup');
				if (!p) return;
				if (xhr.status === 200) {
					try {
						var data = JSON.parse(xhr.responseText);
						if (data.loggedIn === false) {
							p.querySelector('.bv-note-popup-body').innerHTML = 'Please log in to use notes.';
							return;
						}
						renderNotePopup(p, postId, siteUrl, data.note || '');
					} catch (e) {
						p.querySelector('.bv-note-popup-body').innerHTML = 'Error loading note.';
					}
				} else {
					p.querySelector('.bv-note-popup-body').innerHTML = 'Error loading note.';
				}
			}
		};
		xhr.send();

		setTimeout(function () {
			document.addEventListener('mousedown', noteOutsideClickHandler);
		}, 0);
	}

	function renderNotePopup(popup, postId, siteUrl, noteText) {
		var body = popup.querySelector('.bv-note-popup-body');
		var hasNote = noteText.length > 0;
		body.style.textAlign = '';
		body.innerHTML = '<textarea id="bv-note-textarea" rows="6" placeholder="Type your note here...">' + escapeHtml(noteText) + '</textarea>'
			+ '<div class="bv-note-actions">'
			+ '<button class="bv-note-save-btn" id="bv-note-save">' + (hasNote ? 'Update' : 'Save') + '</button>'
			+ (hasNote ? '<button class="bv-note-delete-btn" id="bv-note-delete">Delete</button>' : '')
			+ '</div>';

		document.getElementById('bv-note-save').onclick = function () {
			var text = document.getElementById('bv-note-textarea').value.trim();
			saveNote(postId, siteUrl, text);
		};

		var delBtn = document.getElementById('bv-note-delete');
		if (delBtn) {
			delBtn.onclick = function () {
				deleteNote(postId, siteUrl);
			};
		}
	}

	function saveNote(postId, siteUrl, text) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.success) {
						closeNotePopup();
						updateNoteBtnState(postId, !resp.deleted);
						showToast(resp.deleted ? 'Note deleted' : 'Note saved');
					} else {
						showToast('Error: ' + (resp.error || 'Unknown'));
					}
				} catch (e) {
					showToast('Error saving note');
				}
			}
		};
		var body = 'type=notesave&postid=' + postId + '&text=' + encodeURIComponent(text);
		if (siteUrl) body += '&siteurl=' + encodeURIComponent(siteUrl);
		xhr.send(body);
	}

	function deleteNote(postId, siteUrl) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.success) {
						closeNotePopup();
						updateNoteBtnState(postId, false);
						showToast('Note deleted');
					} else {
						showToast('Error: ' + (resp.error || 'Unknown'));
					}
				} catch (e) {
					showToast('Error deleting note');
				}
			}
		};
		var body = 'type=notedelete&postid=' + postId;
		if (siteUrl) body += '&siteurl=' + encodeURIComponent(siteUrl);
		xhr.send(body);
	}

	function updateNoteBtnState(postId, hasNote) {
		var btns = document.querySelectorAll('.bv-note-btn[data-postid="' + postId + '"]');
		for (var i = 0; i < btns.length; i++) {
			if (hasNote) {
				btns[i].classList.add('bv-note-active');
				btns[i].title = 'Edit Note';
			} else {
				btns[i].classList.remove('bv-note-active');
				btns[i].title = 'Add / Edit Note';
			}
		}
	}

	function noteOutsideClickHandler(e) {
		var popup = document.getElementById('bv-note-popup');
		if (popup && !popup.contains(e.target) && !e.target.classList.contains('bv-note-btn')) {
			closeNotePopup();
		}
	}

	function closeNotePopup() {
		var popup = document.getElementById('bv-note-popup');
		if (popup) popup.remove();
		document.removeEventListener('mousedown', noteOutsideClickHandler);
	}

	// --- Question Status Integration ---

	var STATUS_OPTIONS = [
		{ value: '',          label: '—',       icon: '—',  title: 'Clear status' },
		{ value: 'completed', label: 'Done',     icon: '✔', title: 'Mark as completed' },
		{ value: 'skipped',   label: 'Skipped',  icon: '⏭', title: 'Mark as skipped' },
		{ value: 'wrong',     label: 'Wrong',    icon: '✘', title: 'Mark as wrong attempt' }
	];

	function injectStatusButtons() {
		var titles = document.querySelectorAll('#bv-content-area .question-title');
		for (var i = 0; i < titles.length; i++) {
			if (titles[i].querySelector('.bv-status-btn')) continue;
			var postId = getPostIdFromTitle(titles[i]);
			if (!postId) continue;
			var siteUrl = getSiteUrlFromTitle(titles[i]);

			var btn = document.createElement('button');
			btn.className = 'bv-status-btn';
			btn.setAttribute('data-postid', postId);
			btn.setAttribute('data-siteurl', siteUrl);
			btn.setAttribute('data-status', '');
			btn.title = 'Set status';
			btn.innerHTML = '—';
			btn.onclick = (function (pid, su, btnEl) {
				return function (e) {
					e.stopPropagation();
					e.preventDefault();
					openStatusPopup(pid, su, btnEl);
				};
			})(postId, siteUrl, btn);
			titles[i].appendChild(btn);
		}
	}

	function loadQuestionStatuses() {
		var btns = document.querySelectorAll('#bv-content-area .bv-status-btn');
		if (!btns.length) return;

		// Group by siteUrl
		var groups = {};
		for (var i = 0; i < btns.length; i++) {
			var su = btns[i].getAttribute('data-siteurl') || '';
			if (!groups[su]) groups[su] = [];
			groups[su].push(btns[i].getAttribute('data-postid'));
		}

		Object.keys(groups).forEach(function (su) {
			var postids = groups[su].join(',');
			var url = config.ajaxUrl + '?type=qstatus&postids=' + encodeURIComponent(postids)
				+ (su ? '&siteurl=' + encodeURIComponent(su) : '');
			var xhr = new XMLHttpRequest();
			xhr.open('GET', url, true);
			xhr.onreadystatechange = function () {
				if (xhr.readyState === 4 && xhr.status === 200) {
					try {
						var data = JSON.parse(xhr.responseText);
						if (data.statuses) {
							Object.keys(data.statuses).forEach(function (pid) {
								updateStatusBtn(parseInt(pid), data.statuses[pid]);
								setQuestionStatusClass(parseInt(pid), data.statuses[pid]);
							});
						}
						updateStatusFilterCounts();
					} catch (e) {}
				}
			};
			xhr.send();
		});
	}

	function openStatusPopup(postId, siteUrl, anchorEl) {
		closeStatusPopup();

		var popup = document.createElement('div');
		popup.id = 'bv-status-popup';
		var currentStatus = anchorEl.getAttribute('data-status') || '';

		var html = '<div class="bv-status-popup-header"><span>Question Status</span>'
			+ '<button class="bv-status-popup-close" onclick="document.getElementById(\'bv-status-popup\').remove()">&times;</button></div>'
			+ '<div class="bv-status-popup-body">';

		for (var i = 0; i < STATUS_OPTIONS.length; i++) {
			var opt = STATUS_OPTIONS[i];
			var active = (opt.value === currentStatus) ? ' bv-status-opt-active' : '';
			html += '<button class="bv-status-opt' + active + '" data-value="' + opt.value + '"'
				+ ' title="' + opt.title + '">'
				+ '<span class="bv-status-opt-icon bv-si-' + (opt.value || 'clear') + '">' + opt.icon + '</span> '
				+ opt.label + '</button>';
		}

		html += '</div>';
		popup.innerHTML = html;

		var rect = anchorEl.getBoundingClientRect();
		popup.style.position = 'fixed';
		popup.style.top = (rect.bottom + 4) + 'px';
		popup.style.left = Math.min(rect.left, window.innerWidth - 200) + 'px';
		popup.style.zIndex = '10000';
		// Append inside the app so it is visible in native fullscreen mode
		(document.getElementById('book-viewer-app') || document.body).appendChild(popup);

		// Bind click handlers
		var opts = popup.querySelectorAll('.bv-status-opt');
		for (var j = 0; j < opts.length; j++) {
			opts[j].onclick = (function (val) {
				return function (e) {
					e.stopPropagation();
					setQuestionStatus(postId, siteUrl, val || 'clear');
					closeStatusPopup();
				};
			})(opts[j].getAttribute('data-value'));
		}

		setTimeout(function () {
			document.addEventListener('mousedown', statusOutsideClickHandler);
		}, 0);
	}

	function setQuestionStatus(postId, siteUrl, status) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.success) {
						var newStatus = resp.status || '';
						updateStatusBtn(postId, newStatus);
						setQuestionStatusClass(postId, newStatus);
						updateStatusFilterCounts();
						applyStatusFilter();
					} else {
						showToast('Error: ' + (resp.error || 'Unknown'));
					}
				} catch (e) {
					showToast('Error setting status');
				}
			}
		};
		var body = 'type=qstatusset&postid=' + postId + '&status=' + encodeURIComponent(status);
		if (siteUrl) body += '&siteurl=' + encodeURIComponent(siteUrl);
		xhr.send(body);
	}

	function updateStatusBtn(postId, status) {
		var btns = document.querySelectorAll('.bv-status-btn[data-postid="' + postId + '"]');
		for (var i = 0; i < btns.length; i++) {
			btns[i].setAttribute('data-status', status);
			btns[i].className = 'bv-status-btn' + (status ? ' bv-qs-' + status : '');
			var opt = STATUS_OPTIONS.find(function (o) { return o.value === status; }) || STATUS_OPTIONS[0];
			btns[i].innerHTML = opt.icon;
			btns[i].title = status ? (opt.label) : 'Set status';
		}
	}

	function setQuestionStatusClass(postId, status) {
		// Find the .question div containing this postid
		var anchor = document.querySelector('#bv-content-area a#question' + postId);
		if (!anchor) return;
		var questionDiv = anchor.closest('.question');
		if (!questionDiv) return;
		questionDiv.setAttribute('data-qstatus', status || '');
	}

	// --- Status Filter ---

	var activeStatusFilters = [];

	function initStatusFilter() {
		var toolbar = document.querySelector('.bv-toolbar');
		if (!toolbar || document.getElementById('bv-status-filter-bar')) return;

		var bar = document.createElement('div');
		bar.id = 'bv-status-filter-bar';
		bar.className = 'bv-status-filter-bar';
		bar.innerHTML = '<span class="bv-sf-label">Filter:</span>'
			+ '<button class="bv-sf-btn bv-sf-all bv-sf-active" data-filter="all" onclick="BookViewer.filterByStatus(\'all\')">All <span class="bv-sf-count" id="bv-sf-count-all"></span></button>'
			+ '<button class="bv-sf-btn bv-sf-unmarked" data-filter="unmarked" onclick="BookViewer.filterByStatus(\'unmarked\')">Unmarked <span class="bv-sf-count" id="bv-sf-count-unmarked"></span></button>'
			+ '<button class="bv-sf-btn bv-sf-completed" data-filter="completed" onclick="BookViewer.filterByStatus(\'completed\')">✔ Done <span class="bv-sf-count" id="bv-sf-count-completed"></span></button>'
			+ '<button class="bv-sf-btn bv-sf-skipped" data-filter="skipped" onclick="BookViewer.filterByStatus(\'skipped\')">⏭ Skipped <span class="bv-sf-count" id="bv-sf-count-skipped"></span></button>'
			+ '<button class="bv-sf-btn bv-sf-wrong" data-filter="wrong" onclick="BookViewer.filterByStatus(\'wrong\')">✘ Wrong <span class="bv-sf-count" id="bv-sf-count-wrong"></span></button>';
		toolbar.parentNode.insertBefore(bar, toolbar.nextSibling);
	}

	function filterByStatus(filter) {
		var btns = document.querySelectorAll('.bv-sf-btn');

		if (filter === 'all') {
			activeStatusFilters = [];
			for (var i = 0; i < btns.length; i++) {
				btns[i].classList.toggle('bv-sf-active', btns[i].getAttribute('data-filter') === 'all');
			}
		} else {
			// Toggle this filter
			var idx = activeStatusFilters.indexOf(filter);
			if (idx >= 0) {
				activeStatusFilters.splice(idx, 1);
			} else {
				activeStatusFilters.push(filter);
			}

			// Update button states
			for (var i = 0; i < btns.length; i++) {
				var f = btns[i].getAttribute('data-filter');
				if (f === 'all') {
					btns[i].classList.toggle('bv-sf-active', activeStatusFilters.length === 0);
				} else {
					btns[i].classList.toggle('bv-sf-active', activeStatusFilters.indexOf(f) >= 0);
				}
			}

			if (activeStatusFilters.length === 0) {
				// nothing selected = show all
			}
		}

		applyStatusFilter();
	}

	function applyStatusFilter() {
		var questions = document.querySelectorAll('#bv-content-area .question');
		if (!questions.length) return;

		if (activeStatusFilters.length === 0) {
			// Show all
			for (var i = 0; i < questions.length; i++) {
				questions[i].style.display = '';
			}
			return;
		}

		for (var i = 0; i < questions.length; i++) {
			var qs = questions[i].getAttribute('data-qstatus') || '';
			var matchKey = qs || 'unmarked';
			var show = activeStatusFilters.indexOf(matchKey) >= 0;
			questions[i].style.display = show ? '' : 'none';
		}
	}

	function updateStatusFilterCounts() {
		var questions = document.querySelectorAll('#bv-content-area .question');
		var counts = { all: questions.length, unmarked: 0, completed: 0, skipped: 0, wrong: 0 };
		for (var i = 0; i < questions.length; i++) {
			var qs = questions[i].getAttribute('data-qstatus') || '';
			if (qs && counts.hasOwnProperty(qs)) {
				counts[qs]++;
			} else {
				counts.unmarked++;
			}
		}
		['all', 'unmarked', 'completed', 'skipped', 'wrong'].forEach(function (key) {
			var el = document.getElementById('bv-sf-count-' + key);
			if (el) el.textContent = '(' + counts[key] + ')';
		});
	}

	function statusOutsideClickHandler(e) {
		var popup = document.getElementById('bv-status-popup');
		if (popup && !popup.contains(e.target) && !e.target.classList.contains('bv-status-btn')) {
			closeStatusPopup();
		}
	}

	function closeStatusPopup() {
		var popup = document.getElementById('bv-status-popup');
		if (popup) popup.remove();
		document.removeEventListener('mousedown', statusOutsideClickHandler);
	}

	// --- Section Finished (server-stored) ---

	var hideFinished = false;
	var finishedSet = {};

	function loadFinishedTopics() {
		if (!config.selectedBook || !config.userId) return;

		var url = config.ajaxUrl + '?type=topic_finished&book=' + encodeURIComponent(config.selectedBook);
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4 && xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.finished && resp.finished.length) {
						for (var i = 0; i < resp.finished.length; i++) {
							finishedSet[resp.finished[i]] = true;
						}
						applyFinishedState();
					}
				} catch (e) {}
			}
		};
		xhr.send();
	}

	function applyFinishedState() {
		var items = document.querySelectorAll('.bv-toc-type-topic');
		for (var i = 0; i < items.length; i++) {
			var id = items[i].getAttribute('data-id');
			var cb = items[i].querySelector(':scope > .bv-toc-row .bv-finished-cb');
			if (finishedSet[id]) {
				items[i].classList.add('bv-finished');
				if (cb) {
					cb.checked = true;
					cb.title = 'Mark as unfinished';
				}
			}
		}
		initParentFinishedState();
		applyFinishedFilter();
	}

	function setTopicFinishedOnServer(topicId, finished) {
		if (finished) {
			finishedSet[topicId] = true;
		} else {
			delete finishedSet[topicId];
		}

		if (!config.userId) return;

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		var body = 'type=topic_finished_set&book=' + encodeURIComponent(config.selectedBook)
			+ '&topic_id=' + encodeURIComponent(topicId)
			+ '&finished=' + (finished ? '1' : '0');
		xhr.send(body);
	}

	function toggleFinishedFilter() {
		hideFinished = !hideFinished;
		var btn = document.getElementById('bv-hide-finished-btn');
		if (btn) {
			btn.classList.toggle('bv-sf-active', hideFinished);
		}
		applyFinishedFilter();
	}

	function initParentFinishedState() {
		var categories = document.querySelectorAll('.bv-toc-type-category');
		for (var i = 0; i < categories.length; i++) {
			var childUl = categories[i].querySelector(':scope > ul');
			if (!childUl) continue;
			var childTopics = childUl.querySelectorAll(':scope > .bv-toc-type-topic');
			if (!childTopics.length) continue;
			var allFinished = true;
			for (var j = 0; j < childTopics.length; j++) {
				if (!childTopics[j].classList.contains('bv-finished')) {
					allFinished = false;
					break;
				}
			}
			if (allFinished) {
				categories[i].classList.add('bv-finished');
			}
		}
	}

	function updateParentFinished(topicLi) {
		var parentUl = topicLi.parentElement;
		if (!parentUl) return;
		var parentLi = parentUl.parentElement;
		if (!parentLi || !parentLi.classList.contains('bv-toc-type-category')) return;

		var childTopics = parentUl.querySelectorAll(':scope > .bv-toc-type-topic');
		if (!childTopics.length) return;

		var allFinished = true;
		for (var i = 0; i < childTopics.length; i++) {
			if (!childTopics[i].classList.contains('bv-finished')) {
				allFinished = false;
				break;
			}
		}

		if (allFinished) {
			parentLi.classList.add('bv-finished');
		} else {
			parentLi.classList.remove('bv-finished');
		}
	}

	function applyFinishedFilter() {
		var topics = document.querySelectorAll('.bv-toc-type-topic');
		for (var i = 0; i < topics.length; i++) {
			if (hideFinished && topics[i].classList.contains('bv-finished')) {
				topics[i].style.display = 'none';
			} else {
				topics[i].style.display = '';
			}
		}
		// Also hide categories where all topics are finished
		var categories = document.querySelectorAll('.bv-toc-type-category');
		for (var i = 0; i < categories.length; i++) {
			if (hideFinished && categories[i].classList.contains('bv-finished')) {
				categories[i].style.display = 'none';
			} else {
				categories[i].style.display = '';
			}
		}
	}

	// --- Utility ---

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	// --- Practice Mode ---

	var practiceMode = false;

	function togglePracticeMode() {
		practiceMode = !practiceMode;
		var btn = document.getElementById('bv-practice-mode-btn');
		if (btn) btn.classList.toggle('bv-sf-active', practiceMode);
		var app = document.getElementById('book-viewer-app');
		if (app) app.classList.toggle('bv-practice-on', practiceMode);
		if (practiceMode) {
			applyPracticeMode();
		} else {
			removePracticeMode();
		}
	}

	function getQuestionType(questionEl) {
		var tags = questionEl.querySelectorAll('.qa-tag-link');
		for (var i = 0; i < tags.length; i++) {
			var t = tags[i].textContent.trim().toLowerCase();
			if (t === 'numerical-answers') return 'NAT';
			if (t === 'multiple-selects') return 'MSQ';
		}
		return 'MCQ';
	}

	function getCorrectAnswer(questionEl) {
		var ansEl = questionEl.querySelector('.bv-answer-value');
		if (!ansEl) return null;
		// Get text content excluding the link icon
		var clone = ansEl.cloneNode(true);
		var link = clone.querySelector('.bv-answer-link');
		if (link) link.remove();
		return clone.textContent.trim();
	}

	function applyPracticeMode() {
		var questions = document.querySelectorAll('#bv-content-area .question');
		for (var i = 0; i < questions.length; i++) {
			var q = questions[i];
			if (q.querySelector('.bv-practice-panel')) continue;

			var correctAns = getCorrectAnswer(q);
			if (!correctAns) continue;

			var qType = getQuestionType(q);
			var panel = document.createElement('div');
			panel.className = 'bv-practice-panel';
			panel.setAttribute('data-qtype', qType);
			panel.setAttribute('data-answer', correctAns);

			var postId = null;
			var titleEl = q.querySelector('.question-title');
			if (titleEl) postId = getPostIdFromTitle(titleEl);

			if (postId) panel.setAttribute('data-postid', postId);

			var html = '<div class="bv-practice-header"><span class="bv-practice-badge">' + qType + '</span><span class="bv-practice-title">Your Answer</span></div>';

			if (qType === 'MCQ') {
				var opts = ['A', 'B', 'C', 'D'];
				html += '<div class="bv-practice-options bv-practice-grid">';
				for (var j = 0; j < opts.length; j++) {
					html += '<label class="bv-practice-opt"><input type="radio" name="bv-pq-' + i + '" value="' + opts[j] + '"><span class="bv-opt-letter">' + opts[j] + '</span></label>';
				}
				html += '</div>';
			} else if (qType === 'MSQ') {
				var opts = ['A', 'B', 'C', 'D'];
				html += '<div class="bv-practice-options bv-practice-grid">';
				for (var j = 0; j < opts.length; j++) {
					html += '<label class="bv-practice-opt bv-opt-multi"><input type="checkbox" name="bv-pq-' + i + '" value="' + opts[j] + '"><span class="bv-opt-letter">' + opts[j] + '</span></label>';
				}
				html += '</div>';
			} else {
				html += '<div class="bv-practice-options"><input type="text" class="bv-practice-nat-input" placeholder="Type your numerical answer..."></div>';
			}

			html += '<div class="bv-practice-footer">';
			html += '<div class="bv-practice-actions">';
			html += '<button class="bv-practice-submit">&#x2713; Submit</button>';
			html += '<button class="bv-practice-skip">&#x23ED; Skip</button>';
			html += '</div>';
			html += '<div class="bv-practice-result"></div>';
			html += '</div>';

			panel.innerHTML = html;

			// Insert after question content, before answer key
			var answerKeyEl = q.querySelector('.bv-answer-key');
			if (answerKeyEl) {
				answerKeyEl.parentNode.insertBefore(panel, answerKeyEl);
			} else {
				q.appendChild(panel);
			}

			// Bind submit
			(function(panelEl, idx) {
				panelEl.querySelector('.bv-practice-submit').onclick = function() {
					submitPracticeAnswer(panelEl);
				};
				panelEl.querySelector('.bv-practice-skip').onclick = function() {
					skipPracticeQuestion(panelEl);
				};
			})(panel, i);
		}
	}

	function submitPracticeAnswer(panel) {
		var qType = panel.getAttribute('data-qtype');
		var correctAns = panel.getAttribute('data-answer').toUpperCase().trim();
		var userAns = '';
		var resultEl = panel.querySelector('.bv-practice-result');

		if (qType === 'MCQ') {
			var checked = panel.querySelector('input[type="radio"]:checked');
			userAns = checked ? checked.value : '';
		} else if (qType === 'MSQ') {
			var checks = panel.querySelectorAll('input[type="checkbox"]:checked');
			var vals = [];
			for (var i = 0; i < checks.length; i++) vals.push(checks[i].value);
			vals.sort();
			userAns = vals.join(';');
		} else {
			var input = panel.querySelector('.bv-practice-nat-input');
			userAns = input ? input.value.trim() : '';
		}

		if (!userAns) {
			resultEl.textContent = 'Please select/enter an answer.';
			resultEl.className = 'bv-practice-result bv-pr-warn';
			return;
		}

		var isCorrect = checkAnswer(qType, userAns, correctAns);
		var status = isCorrect ? 'completed' : 'wrong';

		if (isCorrect) {
			resultEl.innerHTML = '&#x2714; Correct!';
			resultEl.className = 'bv-practice-result bv-pr-correct';
		} else {
			resultEl.innerHTML = '&#x2718; Wrong. Correct answer: ' + escapeHtml(correctAns);
			resultEl.className = 'bv-practice-result bv-pr-wrong';
		}

		disablePanel(panel);
		autoMarkStatus(panel, status);
		revealAnswerKey(panel);
	}

	function skipPracticeQuestion(panel) {
		var correctAns = panel.getAttribute('data-answer');
		var resultEl = panel.querySelector('.bv-practice-result');
		resultEl.innerHTML = '&#x23ED; Skipped. Answer: ' + escapeHtml(correctAns);
		resultEl.className = 'bv-practice-result bv-pr-skip';
		disablePanel(panel);
		autoMarkStatus(panel, 'skipped');
		revealAnswerKey(panel);
	}

	function checkAnswer(qType, userAns, correctAns) {
		if (correctAns === 'X') return true; // all options correct

		if (qType === 'MCQ') {
			return userAns.toUpperCase() === correctAns.toUpperCase();
		} else if (qType === 'MSQ') {
			// correctAns could be like "A;B" or "A;B;C" (sorted letters separated by ;)
			// or could be like "AB" or "A,B"
			var correctLetters = correctAns.replace(/[^A-D]/gi, '').toUpperCase().split('').sort().join(';');
			return userAns.toUpperCase() === correctLetters;
		} else {
			// NAT: compare numerically with tolerance
			var userNum = parseFloat(userAns);
			// correctAns might be a range like "2.5:2.7" or a single value
			if (correctAns.indexOf(':') >= 0) {
				var parts = correctAns.split(':');
				var lo = parseFloat(parts[0]);
				var hi = parseFloat(parts[1]);
				return !isNaN(userNum) && userNum >= lo && userNum <= hi;
			}
			var correctNum = parseFloat(correctAns);
			if (isNaN(userNum) || isNaN(correctNum)) {
				return userAns.toLowerCase() === correctAns.toLowerCase();
			}
			// Allow 1% tolerance for NAT
			var tol = Math.abs(correctNum) * 0.01;
			if (tol < 0.01) tol = 0.01;
			return Math.abs(userNum - correctNum) <= tol;
		}
	}

	function disablePanel(panel) {
		var inputs = panel.querySelectorAll('input');
		for (var i = 0; i < inputs.length; i++) inputs[i].disabled = true;
		var btns = panel.querySelectorAll('button');
		for (var i = 0; i < btns.length; i++) btns[i].disabled = true;
		panel.classList.add('bv-practice-done');
	}

	function revealAnswerKey(panel) {
		var question = panel.closest('.question');
		if (!question) return;
		var ansVal = question.querySelector('.bv-answer-value');
		if (ansVal) ansVal.classList.remove('bv-answer-hidden');
	}

	function autoMarkStatus(panel, status) {
		var postId = panel.getAttribute('data-postid');
		if (!postId || !config.userId) return;
		var question = panel.closest('.question');
		var siteUrl = '';
		if (question) {
			var titleEl = question.querySelector('.question-title');
			if (titleEl) siteUrl = getSiteUrlFromTitle(titleEl);
		}
		setQuestionStatus(parseInt(postId, 10), siteUrl, status);
	}

	function removePracticeMode() {
		var panels = document.querySelectorAll('.bv-practice-panel');
		for (var i = 0; i < panels.length; i++) {
			panels[i].remove();
		}
	}

	// --- PDF / Hardcopy Request Modal ---

	var _pendingRequestType = null;

	function requestPdf() {
		openRequestModal('pdf');
	}

	function requestHardcopy() {
		openRequestModal('hardcopy');
	}

	function openRequestModal(type) {
		if (!config.selectedBook) {
			showToast('Please select a book first.');
			return;
		}
		_pendingRequestType = type;

		var modal    = document.getElementById('bv-request-modal');
		var title    = document.getElementById('bv-modal-title');
		var desc     = document.getElementById('bv-modal-desc');
		var result   = document.getElementById('bv-modal-result');
		var emailRow = document.getElementById('bv-modal-email-row');

		title.textContent = type === 'pdf' ? 'Request PDF' : 'Request Hardcopy';
		desc.textContent  = 'Book: ' + (config.bookTitle || config.selectedBook);
		if (result) result.textContent = '';

		// Show email row only for anonymous users; pre-fill if we have their email
		if (!config.userId) {
			if (emailRow) emailRow.style.display = '';
			var emailInput = document.getElementById('bv-modal-email');
			if (emailInput) emailInput.value = config.userEmail || '';
		} else {
			if (emailRow) emailRow.style.display = 'none';
		}

		if (modal) modal.style.display = 'flex';
	}

	function closeModal() {
		var modal = document.getElementById('bv-request-modal');
		if (modal) modal.style.display = 'none';
		_pendingRequestType = null;
	}

	function submitRequest() {
		var type = _pendingRequestType;
		if (!type || !config.selectedBook) return;

		var email = '';
		if (!config.userId) {
			var emailInput = document.getElementById('bv-modal-email');
			email = emailInput ? emailInput.value.trim() : '';
		}

		var resultEl = document.getElementById('bv-modal-result');
		if (resultEl) { resultEl.textContent = 'Submitting\u2026'; resultEl.style.color = ''; }

		var submitBtn = document.querySelector('.bv-modal-submit');
		if (submitBtn) submitBtn.disabled = true;

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl + '?action=book_request', true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) return;
			if (submitBtn) submitBtn.disabled = false;
			try {
				var data = JSON.parse(xhr.responseText);
				if (data.ok) {
					if (resultEl) { resultEl.textContent = data.msg || 'Request submitted!'; resultEl.style.color = '#2da44e'; }
					showToast(data.msg || 'Request submitted!');
					setTimeout(closeModal, 1800);
				} else {
					if (resultEl) { resultEl.textContent = data.error || 'Error. Please try again.'; resultEl.style.color = '#d73a49'; }
				}
			} catch (e) {
				if (resultEl) { resultEl.textContent = 'Error submitting request.'; resultEl.style.color = '#d73a49'; }
			}
		};
		var params = 'type='  + encodeURIComponent(type)
			+ '&book='  + encodeURIComponent(config.selectedBook)
			+ '&email=' + encodeURIComponent(email)
			+ '&csrf='  + encodeURIComponent(config.csrfToken || '');
		xhr.send(params);
	}

	// Close modal on Escape key
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && _pendingRequestType) closeModal();
	});

	// Auto-init on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	return {
		selectBook: selectBook,
		filterToc: filterToc,
		expandAll: expandAll,
		collapseAll: collapseAll,
		loadSection: loadSection,
		toggleSidebar: toggleSidebar,
		toggleFullscreen: toggleFullscreen,
		filterByTag: filterByTag,
		toggleList: toggleList,
		openNotePopup: openNotePopup,
		filterByStatus: filterByStatus,
		toggleFinishedFilter: toggleFinishedFilter,
		togglePracticeMode: togglePracticeMode,
		requestPdf: requestPdf,
		requestHardcopy: requestHardcopy,
		closeModal: closeModal,
		submitRequest: submitRequest
	};
})();
