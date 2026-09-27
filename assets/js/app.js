(function () {
  'use strict';

  var meta = function (n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; };
  var BASE = meta('base');
  var CSRF = meta('csrf');

  function api(path, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    opts.headers = Object.assign({ 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }, opts.headers || {});
    return fetch(BASE + '/api/' + path, opts).then(function (r) { return r.json(); });
  }
  function form(data) {
    var b = new URLSearchParams();
    Object.keys(data).forEach(function (k) { b.append(k, data[k]); });
    return { method: 'POST', body: b };
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  // ---------- Sidebar (mobile drawer) ----------
  var sidebar = document.getElementById('sidebar');
  var scrim = document.getElementById('scrim');
  var menuBtn = document.getElementById('menuBtn');
  function setMenu(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    scrim.classList.toggle('show', open);
    menuBtn.setAttribute('aria-expanded', String(open));
  }
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', function () { setMenu(!sidebar.classList.contains('open')); });
    scrim.addEventListener('click', function () { setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setMenu(false); closeNotif(); } });
  }

  // ---------- Confirm before destructive actions ----------
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // ---------- Auto-growing textareas ----------
  document.querySelectorAll('textarea[data-autosize]').forEach(function (t) {
    var fit = function () { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 120) + 'px'; };
    t.addEventListener('input', fit);
  });

  // ---------- Notifications ----------
  var notifBtn = document.getElementById('notifBtn');
  var notifPanel = document.getElementById('notifPanel');
  var notifList = document.getElementById('notifList');
  var notifCount = document.getElementById('notifCount');

  function setCount(n) {
    if (!notifCount) return;
    notifCount.hidden = !n;
    notifCount.textContent = n > 99 ? '99+' : n;
  }
  function closeNotif() {
    if (notifPanel && !notifPanel.hidden) { notifPanel.hidden = true; notifBtn.setAttribute('aria-expanded', 'false'); }
  }
  function loadNotifications() {
    api('notifications.php').then(function (d) {
      if (!d.ok) return;
      setCount(d.unread);
      notifList.textContent = '';
      if (!d.items.length) { notifList.appendChild(el('p', 'muted small pad', 'You’re all caught up.')); return; }
      d.items.forEach(function (n) {
        var a = el('a', 'notif-item' + (n.is_read ? '' : ' unread'));
        a.href = n.link || '#';
        a.appendChild(el('strong', '', n.title));
        if (n.body) a.appendChild(el('span', '', n.body));
        a.appendChild(el('small', '', n.time));
        a.addEventListener('click', function () {
          if (!n.is_read) api('notifications.php', Object.assign(form({ action: 'read', id: n.id }), { keepalive: true }));
        });
        notifList.appendChild(a);
      });
    }).catch(function () {});
  }
  if (notifBtn && notifPanel) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = notifPanel.hidden;
      notifPanel.hidden = !open;
      notifBtn.setAttribute('aria-expanded', String(open));
      if (open) loadNotifications();
    });
    document.addEventListener('click', function (e) { if (!notifPanel.contains(e.target)) closeNotif(); });
    document.getElementById('notifReadAll').addEventListener('click', function () {
      api('notifications.php', form({ action: 'read_all' })).then(loadNotifications);
    });
    setInterval(function () {
      if (document.hidden) return;
      api('notifications.php?count=1').then(function (d) { if (d.ok) setCount(d.unread); }).catch(function () {});
    }, 30000);
  }

  // ---------- Skill suggestions (datalist fed by the API) ----------
  document.querySelectorAll('input[data-suggest]').forEach(function (input) {
    var list = document.getElementById(input.getAttribute('data-suggest'));
    var timer;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var v = input.value.trim();
      if (v.length < 2) return;
      timer = setTimeout(function () {
        api('search-skills.php?q=' + encodeURIComponent(v)).then(function (d) {
          if (!d.ok) return;
          list.textContent = '';
          d.skills.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.name; o.label = s.category;
            list.appendChild(o);
          });
        }).catch(function () {});
      }, 200);
    });
  });

  // ---------- Live chat ----------
  var chat = document.getElementById('chatBody');
  if (chat) {
    var partner = chat.getAttribute('data-partner');
    var last = parseInt(chat.getAttribute('data-last') || '0', 10);
    var chatForm = document.getElementById('chatForm');
    var box = chatForm ? chatForm.querySelector('textarea') : null;

    var toBottom = function () { chat.scrollTop = chat.scrollHeight; };
    var addBubble = function (m) {
      var empty = chat.querySelector('.chat-empty');
      if (empty) empty.remove();
      var b = el('div', 'bubble ' + (m.mine ? 'mine' : 'theirs'));
      b.appendChild(document.createTextNode(m.body));
      b.appendChild(el('small', '', m.time));
      chat.appendChild(b);
      if (!m.mine) last = Math.max(last, m.id);   // only incoming messages move the polling cursor
    };
    toBottom();

    var poll = function () {
      if (document.hidden) return;
      api('messages.php?with=' + partner + '&after=' + last).then(function (d) {
        if (d.ok && d.messages.length) {
          var nearBottom = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 120;
          d.messages.forEach(addBubble);
          if (nearBottom) toBottom();
        }
      }).catch(function () {});
    };
    setInterval(poll, 4000);

    var send = function () {
      if (!box) return;
      var body = box.value.trim();
      if (!body) return;
      box.disabled = true;
      api('messages.php', form({ to: partner, body: body })).then(function (d) {
        box.disabled = false;
        if (d.ok) { box.value = ''; box.style.height = 'auto'; addBubble(d.message); toBottom(); }
        else { window.alert(d.error || 'Message could not be sent.'); }
        box.focus();
      }).catch(function () { box.disabled = false; window.alert('Network error. Please try again.'); });
    };
    if (chatForm) {
      chatForm.addEventListener('submit', function (e) { e.preventDefault(); send(); });
      box.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
      });
    }
  }

  // ---------- Toggle blocks (data-toggle="#id") ----------
  document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var t = document.querySelector(btn.getAttribute('data-toggle'));
      if (t) { t.classList.toggle('hidden'); if (!t.classList.contains('hidden')) { var f = t.querySelector('input,select,textarea'); if (f) f.focus(); } }
    });
  });
})();
