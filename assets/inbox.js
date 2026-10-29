(function () {
  'use strict';
  var config = window.BfCamelCRMInbox;
  if (!config) return;
  var strings = config.strings || {};
  var center, toast, panel, inbox, lastLink, dirty = false, currentCard = 0, requestToken = 0;
  var feed = config.feed || {items: [], unread: 0, urgent: 0, latest: 0};
  var cursor = Number(feed.latest || 0), timer, audio;
  var baseTitle = document.title;
  var key = config.storageKey;
  function stored(name, fallback) { try { var value = localStorage.getItem(key + ':' + name); return value === null ? fallback : value; } catch (e) { return fallback; } }
  function save(name, value) { try { localStorage.setItem(key + ':' + name, String(value)); } catch (e) {} }
  function api(action, values, method) {
    var params = new URLSearchParams(Object.assign({action: action, nonce: config.nonce}, values || {}));
    var controller = new AbortController(), timeout = setTimeout(function () { controller.abort(); }, 12000);
    var url = config.ajaxUrl, options = {method: method || 'POST', credentials: 'same-origin', signal: controller.signal};
    if (options.method === 'GET') url += '?' + params.toString();
    else { options.body = params; options.headers = {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}; }
    return fetch(url, options).then(function (response) {
      return response.json().then(function (result) {
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message || strings.error);
        return result.data;
      });
    }).finally(function () { clearTimeout(timeout); });
  }
  function updateFeed(next) {
    feed = next;
    if (next.changed && inbox) {
      var row = inbox.querySelector('[data-bfcamel-row-id="' + next.changed.id + '"]');
      if (row) {
        var owner = row.querySelector('td.bfcamel-crm-owner-column'); if (owner) owner.textContent = next.changed.assignee || '';
        var status = row.querySelector('.bfcamel-crm-request-meta span'); if (status) status.textContent = next.changed.status;
        var view = inbox.querySelector('input[name="view"]');
        if (view && ((view.value === 'attention' && !next.changed.attention) || (view.value === 'mine' && next.changed.closed))) row.remove();
      }
    }
    document.querySelectorAll('.bfcamel-notification-count').forEach(function (node) {
      node.textContent = String(feed.unread); node.classList.toggle('has-unread', feed.unread > 0); node.classList.toggle('is-urgent', feed.urgent > 0);
    });
    var menu = document.querySelector('#toplevel_page_bfcamel-crm .wp-menu-name');
    if (menu) {
      menu.closest('#toplevel_page_bfcamel-crm').classList.toggle('bfcamel-has-urgent', feed.urgent > 0);
      var badge = menu.querySelector('.awaiting-mod');
      if (!badge && feed.unread) { badge = document.createElement('span'); badge.className = 'awaiting-mod'; var count = document.createElement('span'); count.className = 'pending-count'; badge.appendChild(count); menu.appendChild(badge); }
      if (badge) { badge.hidden = !feed.unread; badge.querySelector('.pending-count').textContent = String(feed.unread); }
    }
    document.title = feed.unread ? '(' + feed.unread + ') ' + baseTitle : baseTitle;
    document.querySelectorAll('.bfcamel-urgent-notice').forEach(function (notice) { notice.hidden = !feed.urgent; notice.querySelector('[data-bfcamel-urgent-count]').textContent = String(feed.urgent); });
    if (!center) return;
    var list = center.querySelector('[data-bfcamel-notification-items]'); list.replaceChildren();
    (feed.items || []).forEach(function (item) {
      var link = document.createElement('a'); link.href = item.url;
      link.className = 'bfcamel-notification-item bfcamel-notification-item--' + item.level + (item.read ? ' is-read' : '');
      link.dataset.bfcamelNotificationSubmission = item.submissionId;
      var title = document.createElement('strong'); title.textContent = item.title;
      var label = document.createElement('span'); label.textContent = item.level === 'urgent' ? strings.urgent : item.level === 'high' ? strings.high : strings.normal;
      link.append(title, label); list.appendChild(link);
    });
    if (!(feed.items || []).length) { var empty = document.createElement('p'); empty.textContent = strings.empty; list.appendChild(empty); }
  }
  function read(id) { return api('bfcamel_crm_notification_read', {submission_id: id}).then(updateFeed).catch(function () {}); }
  function sound() {
    if (stored('sound', '0') !== '1') return;
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      if (audio.state !== 'running') return;
      var oscillator = audio.createOscillator(), gain = audio.createGain();
      oscillator.connect(gain); gain.connect(audio.destination); oscillator.frequency.value = 740;
      gain.gain.setValueAtTime(0.035, audio.currentTime); gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.3);
      oscillator.start(); oscillator.stop(audio.currentTime + 0.3);
    } catch (e) {}
  }
  function showToast(items) {
    if (!toast || !items.length) return;
    var urgent = items.some(function (x) { return x.level === 'urgent'; });
    var high = items.some(function (x) { return x.level === 'high'; });
    var label = urgent ? strings.urgent : high ? strings.high : strings.normal;
    var first = items.find(function (x) { return x.level === 'urgent'; }) || items[0];
    toast.replaceChildren(); toast.classList.toggle('is-urgent', urgent); toast.setAttribute('role', urgent ? 'alert' : 'status');
    var close = document.createElement('button'); close.type = 'button'; close.className = 'button'; close.textContent = '×'; close.setAttribute('aria-label', strings.close); close.onclick = function () { toast.hidden = true; };
    var text = document.createElement('strong'); text.textContent = label + (items.length > 1 ? ' · ' + items.length : '');
    var link = document.createElement('a'); link.textContent = strings.open + ' →'; link.href = first.url; link.dataset.bfcamelNotificationSubmission = first.submissionId;
    toast.append(close, text, link); toast.hidden = false;
    if (urgent) sound();
    if (document.hidden && stored('browser', '0') === '1' && 'Notification' in window && Notification.permission === 'granted') {
      var notification = new Notification(label, {body: strings.open, tag: key + ':incoming'});
      notification.onclick = function () { window.focus(); window.location.href = first.url; notification.close(); };
    }
    if (!urgent) setTimeout(function () { toast.hidden = true; }, high ? 15000 : 8000);
  }
  function announce(next) {
    function run() {
      var seen = Math.max(cursor, Number(stored('cursor', '0')));
      var incoming = (next.items || []).filter(function (item) { return item.id > seen && !item.read; });
      cursor = Math.max(cursor, Number(next.latest || 0)); save('cursor', cursor);
      showToast(incoming);
    }
    if (navigator.locks && navigator.locks.request) navigator.locks.request(key + ':alerts', {ifAvailable: true}, function (lock) { if (lock) run(); }).catch(function () {});
    else run();
  }
  function poll() {
    clearTimeout(timer);
    if (!config.enabled) return;
    api('bfcamel_crm_notification_feed').then(function (next) { announce(next); updateFeed(next); }).catch(function () {}).finally(function () { timer = setTimeout(poll, document.hidden ? 60000 : 20000); });
  }
  function allowLeave() { return !dirty || window.confirm(strings.unsaved); }
  function listUrl() { var url = new URL(window.location.href); url.hash = ''; return url.href; }
  function closePanel(focus) {
    if (!panel) return;
    panel.hidden = true; inbox.querySelector('.bfcamel-crm-inbox-layout').classList.remove('has-preview');
    requestToken++; currentCard = 0; dirty = false;
    if (focus && lastLink && document.contains(lastLink)) lastLink.focus();
  }
  function openPanel(id, url, historyMode) {
    if (!panel || !window.matchMedia('(min-width:1100px)').matches) { window.location.href = url; return; }
    if (!allowLeave()) return;
    dirty = false; currentCard = Number(id); var token = ++requestToken;
    panel.hidden = false; inbox.querySelector('.bfcamel-crm-inbox-layout').classList.add('has-preview');
    panel.querySelector('[data-bfcamel-full-card]').href = url;
    var content = panel.querySelector('[data-bfcamel-preview-content]'); content.textContent = strings.loading;
    if (historyMode) history.pushState({bfcamelCard: Number(id)}, '', listUrl() + '#submission-' + id);
    var returnUrl = listUrl() + '#submission-' + id;
    api('bfcamel_crm_submission_detail', {id: id, bfcamel_return: returnUrl}, 'GET').then(function (result) {
      if (token !== requestToken) return;
      content.innerHTML = result.html;
      if (window.BfCamelCRMEnhance) window.BfCamelCRMEnhance(content);
      read(id);
      var heading = content.querySelector('h1'); if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({preventScroll: true}); }
    }).catch(function () { if (token === requestToken) content.textContent = strings.error; });
  }
  function updateBulk(form) {
    var bar = form.querySelector('.bfcamel-crm-bulk-bar'); if (!bar) return;
    var checked = form.querySelectorAll('tbody .check-column input:checked').length; bar.hidden = !checked;
    var counter = bar.querySelector('[data-bfcamel-selected-count]'); if (counter) counter.textContent = String(checked);
    var action = form.querySelector('[name="bulk_action"]'); if (!action) return;
    var name = {status: 'bulk_status', priority: 'bulk_priority', assigned_to: 'bulk_assigned_to', add_tags: 'bulk_tags', remove_tags: 'bulk_tags'}[action.value];
    ['bulk_status', 'bulk_priority', 'bulk_assigned_to', 'bulk_tags'].forEach(function (fieldName) {
      var field = form.querySelector('[name="' + fieldName + '"]'); if (!field) return;
      field.hidden = fieldName !== name;
      if (field.type === 'hidden' && field.nextElementSibling && field.nextElementSibling.classList.contains('bfcamel-crm-tag-picker')) field.nextElementSibling.hidden = fieldName !== name;
    });
  }
  function ready() {
    center = document.getElementById('bfcamel-notification-center'); toast = document.getElementById('bfcamel-notification-toast');
    inbox = document.querySelector('[data-bfcamel-inbox]'); panel = inbox && inbox.querySelector('.bfcamel-crm-preview');
    updateFeed(feed);
    if (inbox) { try { var oldScroll = Number(sessionStorage.getItem(key + ':scroll:' + listUrl()) || 0); if (oldScroll) window.scrollTo(0, oldScroll); } catch (e) {} }
    document.querySelectorAll('.bfcamel-crm-bulk-form').forEach(updateBulk);
    if (center) {
      var checkbox = center.querySelector('[data-bfcamel-sound]'); checkbox.checked = stored('sound', '0') === '1';
      checkbox.addEventListener('change', function () { save('sound', checkbox.checked ? '1' : '0'); if (checkbox.checked) { try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); audio.resume().catch(function () {}); } catch (e) {} } });
      center.querySelector('[data-bfcamel-browser]').addEventListener('click', function () {
        if (!('Notification' in window)) { center.querySelector('[data-bfcamel-browser-status]').textContent = strings.browserDenied; return; }
        Notification.requestPermission().then(function (permission) { save('browser', permission === 'granted' ? '1' : '0'); center.querySelector('[data-bfcamel-browser-status]').textContent = permission === 'granted' ? strings.browserEnabled : strings.browserDenied; });
      });
    }
    document.addEventListener('click', function (event) {
      var target = event.target instanceof Element ? event.target : null; if (!target) return;
      var bell = target.closest('#wp-admin-bar-bfcamel-crm-notifications a');
      if (bell && center) { event.preventDefault(); center.hidden = !center.hidden; return; }
      if (target.closest('[data-bfcamel-notification-close]') && center) { center.hidden = true; return; }
      if (target.closest('[data-bfcamel-preview-close]')) { if (allowLeave()) { history.pushState({}, '', listUrl()); closePanel(true); } return; }
      if (inbox && target.closest('a[href]')) { try { sessionStorage.setItem(key + ':scroll:' + listUrl(), String(window.scrollY)); } catch (e) {} }
      var link = target.closest('[data-bfcamel-open]');
      if (link && inbox && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0 && window.matchMedia('(min-width:1100px)').matches) { event.preventDefault(); lastLink = link; openPanel(link.dataset.bfcamelOpen, link.href, true); return; }
      var notificationLink = target.closest('[data-bfcamel-notification-submission]');
      if (notificationLink) read(notificationLink.dataset.bfcamelNotificationSubmission);
      var row = target.closest('[data-bfcamel-row-url]');
      if (row && !target.closest('a,button,input,select,textarea,label,summary') && !window.getSelection().toString()) {
        var anchor = row.querySelector('[data-bfcamel-open]') || row.querySelector('a');
        if (anchor) anchor.click();
      }
    });
    document.addEventListener('change', function (event) { var form = event.target.closest('.bfcamel-crm-bulk-form'); if (form) updateBulk(form); });
    document.addEventListener('input', function (event) { if (event.target.closest('[data-bfcamel-submission-id]')) dirty = true; });
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (form.matches('[data-bfcamel-claim],[data-bfcamel-complete]') && config.enabled) {
        event.preventDefault(); var button = form.querySelector('button[type="submit"]'); if (button) button.disabled = true;
        var id = form.querySelector('[name="submission_id"]').value;
        api(form.matches('[data-bfcamel-complete]') ? 'bfcamel_crm_complete_submission' : 'bfcamel_crm_claim_submission', {submission_id: id}).then(function (next) {
          updateFeed(next); dirty = false;
          if (currentCard) openPanel(id, panel.querySelector('[data-bfcamel-full-card]').href, false);
          else window.location.reload();
        }).catch(function (error) { window.alert(error.message || strings.claimError); if (button) button.disabled = false; });
      } else dirty = false;
    });
    window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('popstate', function () {
      var match = window.location.hash.match(/^#submission-(\d+)$/);
      if (match && inbox) openPanel(match[1], new URL('admin.php?page=bfcamel-crm-submissions&id=' + match[1], config.ajaxUrl).href, false);
      else if (allowLeave()) closePanel(true);
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    window.addEventListener('pointerdown', function () { if (stored('sound', '0') === '1') { try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); audio.resume().catch(function () {}); } catch (e) {} } }, {once: true});
    var fullCard = !inbox && document.querySelector('[data-bfcamel-submission-id]'); if (fullCard && config.enabled) read(fullCard.dataset.bfcamelSubmissionId);
    var initial = window.location.hash.match(/^#submission-(\d+)$/); if (initial && panel) openPanel(initial[1], new URL('admin.php?page=bfcamel-crm-submissions&id=' + initial[1], config.ajaxUrl).href, false);
    timer = setTimeout(poll, 20000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
