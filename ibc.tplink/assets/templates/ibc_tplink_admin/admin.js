(function () {
  'use strict';

  function initReveal() {
    var nodes = document.querySelectorAll('.tplink-reveal');
    if (!nodes.length || !('IntersectionObserver' in window)) {
      nodes.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });
    nodes.forEach(function (el) { io.observe(el); });
  }

  function initNav() {
    var toggle = document.querySelector('.tplink-nav-toggle');
    var menu = document.getElementById('tplink-mobile-menu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (open) {
        menu.classList.remove('is-open');
        menu.hidden = true;
      } else {
        menu.hidden = false;
        requestAnimationFrame(function () { menu.classList.add('is-open'); });
      }
    });
  }

  function initCopy() {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var sel = btn.getAttribute('data-copy');
        var el = sel ? document.querySelector(sel) : null;
        if (!el) return;
        var text = el.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text);
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        var span = btn.querySelector('span');
        if (span) {
          var orig = span.textContent;
          span.textContent = 'Скопировано';
          setTimeout(function () { span.textContent = orig; }, 1600);
        }
      });
    });
  }

  function initCleanupForm() {
    var form = document.querySelector('[data-clear-form]');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('[name="clear_confirm"]');
      if (input && input.value.trim() !== 'CLEAR') {
        e.preventDefault();
        window.alert('Введите CLEAR для подтверждения.');
        return;
      }
      if (!window.confirm('Удалить все элементы каталога? Это действие необратимо.')) {
        e.preventDefault();
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initReveal();
    initNav();
    initCopy();
    initCleanupForm();
  });
})();
