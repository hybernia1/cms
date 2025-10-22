import {
  normalizeUrl,
  applyAdminHtml,
  readResponsePayload
} from '../../admin/assets/js/core/ajax.js';

import {
  setListingLoadingState,
  cleanListingUrl,
  buildListingJsonUrl,
  pushListingHistory
} from '../../admin/assets/js/ui/listings/listing-utils.js';

const resultsElement = document.getElementById('results');
const tests = [];

function test(name, fn) {
  tests.push({ name, fn });
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message || 'Assertion failed');
  }
}

function assertEqual(actual, expected, message) {
  if (actual !== expected) {
    const detail = message ? message + ' ' : '';
    throw new Error(detail + '(expected "' + expected + '", got "' + actual + '")');
  }
}

function appendResult(name, status, detail) {
  const line = document.createElement('div');
  line.className = 'test-result ' + status;
  line.textContent = status === 'pass' ? '✓ ' + name : '✗ ' + name;
  if (detail) {
    const small = document.createElement('small');
    small.textContent = detail;
    line.appendChild(small);
  }
  resultsElement.appendChild(line);
}

function createAdminWrapper() {
  if (!document.querySelector('.admin-wrapper')) {
    const wrapper = document.createElement('div');
    wrapper.className = 'admin-wrapper';
    wrapper.textContent = 'Original admin wrapper';
    document.body.appendChild(wrapper);
  }
}

test('normalizeUrl vrací absolutní adresu', function () {
  const url = normalizeUrl('/admin/posts');
  assert(url.indexOf('http') === 0, 'URL musí začínat protokolem');
  assert(url.indexOf('/admin/posts') !== -1, 'URL musí obsahovat cestu');
});

test('setListingLoadingState přidává a odstraňuje stavové atributy', function () {
  const container = document.createElement('div');
  setListingLoadingState(container, true);
  assert(container.classList.contains('is-loading'), 'Očekávám CSS třídu is-loading');
  assertEqual(container.getAttribute('aria-busy'), 'true', 'Očekávám atribut aria-busy');
  setListingLoadingState(container, false);
  assert(!container.classList.contains('is-loading'), 'Třída is-loading musí být odstraněna');
  assert(container.getAttribute('aria-busy') === null, 'Atribut aria-busy musí být odstraněn');
});

test('cleanListingUrl odstraní parametr format', function () {
  const cleaned = cleanListingUrl('https://example.test/admin?format=json&page=2');
  assert(cleaned.indexOf('format=') === -1, 'Očekávám odstranění parametru format');
  assert(cleaned.indexOf('page=2') !== -1, 'Ostatní parametry musí zůstat');
});

test('buildListingJsonUrl nastaví format=json a nezapisuje vynechané parametry', function () {
  const target = buildListingJsonUrl('https://example.test/admin?foo=1', { page: 3, foo: 2 }, { skipExisting: ['foo'] });
  assert(target.indexOf('format=json') !== -1, 'Výsledek musí obsahovat format=json');
  assert(target.indexOf('page=3') !== -1, 'Výsledek musí obsahovat nový parametr page');
  assert(target.indexOf('foo=1') !== -1 && target.indexOf('foo=2') === -1, 'Původní hodnota foo musí zůstat zachována');
});

test('pushListingHistory zapisuje stav bez parametru format', function () {
  const calls = [];
  const originalPushState = window.history.pushState;
  try {
    window.history.pushState = function (state, title, url) {
      calls.push({ state, url });
    };
    try {
      window.history.replaceState({ existing: true }, '', window.location.href);
    } catch (err) {
      // Některé prohlížeče mohou volání odmítnout, což nám nevadí.
    }
    pushListingHistory('https://example.test/admin?format=json&page=9', function (state) {
      const next = state && typeof state === 'object' ? Object.assign({}, state) : {};
      next.updated = true;
      return next;
    });
    assertEqual(calls.length, 1, 'pushState musí být zavoláno právě jednou');
    assert(calls[0].url.indexOf('format=') === -1, 'URL ve stavu nesmí obsahovat format');
    assert(calls[0].state && calls[0].state.updated === true, 'Transformace stavu musí být použita');
  } finally {
    window.history.pushState = originalPushState;
  }
});

test('applyAdminHtml nahradí obal a spustí inline skripty', function () {
  createAdminWrapper();
  window.__applyAdminHtmlExecuted = false;
  const html = '<html lang="cs"><head><title>Nový titul</title></head><body class="admin-body">' +
    '<div class="admin-wrapper" id="wrapper-test"><span>Obsah</span></div>' +
    '<script>window.__applyAdminHtmlExecuted = true;<\/script>' +
    '</body></html>';
  const wrapper = applyAdminHtml(html);
  assert(wrapper && wrapper.id === 'wrapper-test', 'Vrácený element musí existovat');
  assertEqual(document.title, 'Nový titul', 'Titulek dokumentu musí být aktualizován');
  assertEqual(document.body.className, 'admin-body', 'Třídy těla dokumentu musí být převzaty');
  assert(window.__applyAdminHtmlExecuted === true, 'Inline skripty musí být znovu spuštěny');
});

test('readResponsePayload rozpozná JSON odpověď', function () {
  const response = {
    text: function () { return Promise.resolve('{"message":"ok"}'); },
    headers: {
      get: function (name) {
        return name.toLowerCase() === 'content-type' ? 'application/json; charset=utf-8' : '';
      }
    }
  };
  return readResponsePayload(response).then(function (payload) {
    assert(payload.isJson === true, 'Odpověď musí být označená jako JSON');
    assert(payload.data && payload.data.message === 'ok', 'JSON musí být zparsován');
  });
});

async function run() {
  const summary = { passed: 0, failed: 0 };
  for (const entry of tests) {
    try {
      await entry.fn();
      summary.passed += 1;
      appendResult(entry.name, 'pass');
    } catch (err) {
      summary.failed += 1;
      appendResult(entry.name, 'fail', err.message);
      console.error('Test failed:', entry.name, err);
    }
  }
  const footer = document.createElement('p');
  footer.style.marginTop = '1rem';
  footer.innerHTML = '<strong>Hotovo:</strong> ' + summary.passed + ' úspěšných, ' + summary.failed + ' neúspěšných testů.';
  resultsElement.appendChild(footer);
}

createAdminWrapper();
run();
