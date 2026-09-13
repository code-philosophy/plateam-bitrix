(function () {
  function boot() {
    var root = document.getElementById('plateam-checkout-root');
    if (!root) return;

    var stashUrl = root.getAttribute('data-stash-url') || '';
    var platformOrigin =
      root.getAttribute('data-platform-origin') ||
      ((window.PLATEAM && window.PLATEAM.appOrigin) || '');
    platformOrigin = String(platformOrigin).replace(/\/$/, '');
    var orderTotalKop = parseInt(root.getAttribute('data-order-total-kop') || '0', 10) || 0;
    var mode = (root.getAttribute('data-mode') || 'cart').toLowerCase();
    var summaryOnly = mode === 'summary';
    var promoOwn = root.getAttribute('data-promo-own') === '1';
    var promoNeedsActivation = root.getAttribute('data-promo-needs-activation') === '1';
    var promoActivateRef = root.getAttribute('data-promo-activate-ref') || 'demo-ref-north';
    var promoOwnCode = (root.getAttribute('data-promo-own-code') || 'PLATEAM').toUpperCase();
    var promoStatusUrl =
      root.getAttribute('data-promo-status-url') ||
      '/local/modules/plateam.partner/tools/promo_status.php';
    var pick = { ses: false, ues: false };
    var sessionRef = null;
    var PICK_KEY = 'plateam_cert_pick';
    var PROMO_ACT_KEY = 'plateam_promo_activated';
    /** One-shot: after own-promo flash/hop, force welcome even if dismiss was set. */
    var WELCOME_AFTER_OWN_KEY = 'plateam_welcome_after_own_promo';
    var promoActivateInFlight = false;
    var applyingSync = false;
    var ownPromoUxInFlight = false;
    /** Already saw own coupon on this page boot — avoid re-flash on every poll. */
    var ownCouponSeen = false;
    var baseTotal = {
      kop: orderTotalKop,
      formatted: '',
    };

    function markWelcomeAfterOwnPromo() {
      try {
        sessionStorage.setItem(WELCOME_AFTER_OWN_KEY, '1');
      } catch (e) {}
    }

    function peekWelcomeAfterOwnPromo() {
      try {
        return sessionStorage.getItem(WELCOME_AFTER_OWN_KEY) === '1';
      } catch (e) {}
      return false;
    }

    function consumeWelcomeAfterOwnPromo() {
      try {
        if (sessionStorage.getItem(WELCOME_AFTER_OWN_KEY) === '1') {
          sessionStorage.removeItem(WELCOME_AFTER_OWN_KEY);
          return true;
        }
      } catch (e) {}
      return false;
    }

    function needsActivateHop() {
      if (!promoActivateRef) return false;
      try {
        var u = new URL(window.location.href);
        return !u.searchParams.get('pla_ref');
      } catch (e) {
        return false;
      }
    }

    function forceOpenWelcome() {
      try {
        if (window.PLATEAM && typeof window.PLATEAM.clearWelcomeDismiss === 'function') {
          window.PLATEAM.clearWelcomeDismiss();
        }
        if (window.PLATEAM && typeof window.PLATEAM.openWelcome === 'function') {
          window.PLATEAM.openWelcome({ force: true });
        }
      } catch (e) {}
    }

    function activateViaRef(force) {
      if (!promoActivateRef || promoActivateInFlight) return false;
      if (!force) {
        try {
          if (sessionStorage.getItem(PROMO_ACT_KEY) === promoActivateRef) {
            return false;
          }
        } catch (e) {}
      }
      var u = new URL(window.location.href);
      if (u.searchParams.get('pla_ref')) {
        try {
          sessionStorage.setItem(PROMO_ACT_KEY, promoActivateRef);
        } catch (e2) {}
        return false;
      }
      promoActivateInFlight = true;
      try {
        sessionStorage.setItem(PROMO_ACT_KEY, promoActivateRef);
      } catch (e3) {}
      u.searchParams.set('pla_ref', promoActivateRef);
      window.location.replace(u.toString());
      return true;
    }

    function discountPctForFlash() {
      try {
        if (window.PLATEAM && typeof window.PLATEAM.projectIssue === 'function') {
          var proj = window.PLATEAM.projectIssue(effectiveOrderTotalKop() || 10000);
          if (proj && proj.discountPct > 0) return Number(proj.discountPct);
        }
        var s = window.PLATEAM && window.PLATEAM.getSession && window.PLATEAM.getSession();
        if (s && s.discountPct > 0) return Number(s.discountPct);
      } catch (e) {}
      return 10;
    }

    function setVisibleOrderTotal(kop, noteText) {
      var priceEl = document.querySelector('[data-entity="basket-total-price"]');
      if (priceEl) {
        priceEl.textContent = rub(kop);
        priceEl.classList.add('plateam-promo-flash');
        var descEl = document.querySelector('.basket-checkout-block-total-description');
        if (descEl && noteText) {
          var note = descEl.querySelector('.plateam-promo-flash-note');
          if (!note) {
            note = document.createElement('div');
            note.className = 'plateam-promo-flash-note';
            note.style.cssText = 'color:#2f6b4f;font-size:12px;margin-top:4px';
            descEl.appendChild(note);
          }
          note.textContent = noteText;
        }
      }
      document.querySelectorAll('.bx-soa-cart-total-line-total .bx-soa-cart-d').forEach(function (el) {
        el.textContent = rub(kop);
        el.classList.add('plateam-promo-flash');
      });
    }

    function clearPromoFlashNotes() {
      document.querySelectorAll('.plateam-promo-flash-note').forEach(function (n) {
        n.remove();
      });
      document.querySelectorAll('.plateam-promo-flash').forEach(function (el) {
        el.classList.remove('plateam-promo-flash');
      });
    }

    /**
     * Свой промокод: кратко показать «скидка применилась» к сумме,
     * вернуть сумму, затем то же welcome, что при рефералке.
     */
    function flashOwnPromoThen(done) {
      captureBaseFromDomIfNeeded();
      var baseKop = effectiveOrderTotalKop();
      if (!(baseKop > 0)) {
        if (typeof done === 'function') done();
        return;
      }
      var pct = discountPctForFlash();
      var discounted = Math.max(0, Math.floor((baseKop * (100 - pct)) / 100));
      var note =
        'Скидка ' +
        pct +
        '% (промокод ' +
        promoOwnCode +
        ') — будет учтена после оплаты';
      setVisibleOrderTotal(discounted, note);
      setTimeout(function () {
        setVisibleOrderTotal(baseKop, null);
        clearPromoFlashNotes();
        refreshBitrixTotals();
        setTimeout(function () {
          if (typeof done === 'function') done();
        }, 200);
      }, 1400);
    }

    function openWelcomeOrActivate() {
      // Survive pla_ref hop / prior dismiss: next load forces the same welcome.
      markWelcomeAfterOwnPromo();
      var s =
        window.PLATEAM && typeof window.PLATEAM.getSession === 'function'
          ? window.PLATEAM.getSession()
          : null;
      var sessionActive = !!(s && s.ui && s.ui !== 'silent');

      if (!sessionActive) {
        // Silent: hop first so session becomes welcome_referral; flag keeps force.
        if (activateViaRef(true)) return;
        forceOpenWelcome();
        consumeWelcomeAfterOwnPromo();
        return;
      }

      // Already active — show welcome now; if hop still needed, keep flag for reload.
      forceOpenWelcome();
      if (needsActivateHop()) {
        markWelcomeAfterOwnPromo();
        activateViaRef(true);
        return;
      }
      consumeWelcomeAfterOwnPromo();
    }

    function runOwnPromoEntryUx() {
      if (ownPromoUxInFlight || promoActivateInFlight) return;
      ownPromoUxInFlight = true;
      flashOwnPromoThen(function () {
        openWelcomeOrActivate();
        ownPromoUxInFlight = false;
      });
    }

    /** Точное совпадение кода купона (не ищем подстроку в названии скидки). */
    function normalizeCouponCode(raw) {
      return String(raw || '')
        .toUpperCase()
        .replace(/\s+/g, '')
        .replace(/[^A-Z0-9_-]/g, '');
    }

    function collectAppliedCouponCodes() {
      var found = {};
      var nodes = document.querySelectorAll(
        '[data-entity="basket-coupon-list"] [data-coupon], ' +
          '.basket-coupon-alert [data-coupon], ' +
          '[data-entity="basket-coupon"][data-coupon], ' +
          '.bx-soa-coupon-item[data-coupon]',
      );
      for (var i = 0; i < nodes.length; i++) {
        var attr = normalizeCouponCode(nodes[i].getAttribute('data-coupon'));
        if (attr) found[attr] = true;
      }
      // Fallback: текст только у элементов, где обычно один код целиком.
      var textNodes = document.querySelectorAll(
        '[data-entity="basket-coupon-list"] strong, ' +
          '[data-entity="basket-coupon-list"] .basket-coupon-text, ' +
          '.basket-coupon-block-coupon-btn, ' +
          '.bx-soa-coupon-item-fixed',
      );
      for (var j = 0; j < textNodes.length; j++) {
        var el = textNodes[j];
        if (el.closest && el.closest('#plateam-checkout-root')) continue;
        var t = normalizeCouponCode(el.textContent);
        // Целый токен кода, без длинных описаний скидок.
        if (t && t.length <= 32 && t.indexOf('ДЕМО') === -1 && t.indexOf('СКИДК') === -1) {
          found[t] = true;
        }
      }
      return Object.keys(found);
    }

    function hasOwnCouponInDom() {
      var codes = collectAppliedCouponCodes();
      for (var i = 0; i < codes.length; i++) {
        if (codes[i] === promoOwnCode) return true;
      }
      return false;
    }

    function hasForeignCouponInDom() {
      var foreign = normalizeCouponCode(root.getAttribute('data-promo-foreign-code') || 'SHOP10');
      var codes = collectAppliedCouponCodes();
      for (var i = 0; i < codes.length; i++) {
        if (codes[i] === promoOwnCode) continue;
        if (foreign && codes[i] === foreign) return true;
        if (codes[i] && codes[i] !== promoOwnCode) return true;
      }
      return false;
    }

    function paintOwnCouponGreen() {
      var alerts = document.querySelectorAll(
        '.basket-coupon-alert-section .basket-coupon-alert, .basket-coupon-section .basket-coupon-alert',
      );
      for (var i = 0; i < alerts.length; i++) {
        var alert = alerts[i];
        var del = alert.querySelector('[data-coupon]');
        var strong = alert.querySelector('strong');
        var code = normalizeCouponCode(
          (del && del.getAttribute('data-coupon')) || (strong && strong.textContent) || '',
        );
        if (code !== promoOwnCode) continue;
        alert.classList.remove('text-danger', 'text-warning', 'text-muted');
        alert.classList.add('text-success', 'plateam-own-coupon-ok');
        // Убрать хвост «(название правила скидки)» у статуса купона.
        var textEl = alert.querySelector('.basket-coupon-text');
        if (textEl) {
          var html = textEl.innerHTML;
          var cleaned = html.replace(/\s*\([^)]*\)\s*(<\/|$)/g, '$1');
          if (cleaned !== html) {
            textEl.innerHTML = cleaned;
          }
        }
      }
    }

    function reloadBasketAfterStrip() {
      if (promoActivateInFlight) return;
      try {
        if (sessionStorage.getItem('plateam_strip_reload') === '1') {
          sessionStorage.removeItem('plateam_strip_reload');
          return;
        }
        sessionStorage.setItem('plateam_strip_reload', '1');
      } catch (e) {}
      window.location.reload();
    }

    function checkPromoAndActivate(fromServer) {
      if (fromServer && fromServer.stripped && fromServer.stripped.length) {
        reloadBasketAfterStrip();
        return;
      }
      if (fromServer && fromServer.foreignBlocked && hasForeignCouponInDom()) {
        reloadBasketAfterStrip();
        return;
      }
      if (promoActivateInFlight || ownPromoUxInFlight) return;

      // Активация ТОЛЬКО при своём купоне (сервер или точный код в DOM).
      var ownFromServer = !!(fromServer && fromServer.ownApplied);
      var ownFromDom = hasOwnCouponInDom();
      if (!ownFromServer && !ownFromDom) {
        ownCouponSeen = false;
        return;
      }

      promoOwn = true;
      root.setAttribute('data-promo-own', '1');
      if (fromServer && fromServer.activateRef) {
        promoActivateRef = String(fromServer.activateRef);
      }

      // Первый раз видим свой купон на этой загрузке → flash + welcome.
      // Повторные poll/OnBasketChange не дёргают UX снова.
      if (!ownCouponSeen) {
        ownCouponSeen = true;
        runOwnPromoEntryUx();
        return;
      }
    }

    function postPromoStatus(payload) {
      return fetch(promoStatusUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload || {}),
      }).then(function (r) {
        return r.json();
      });
    }

    function fetchPromoStatusThenActivate() {
      if (promoActivateInFlight) return;
      postPromoStatus({})
        .then(function (s) {
          if (!s || !s.ok) {
            checkPromoAndActivate(null);
            return;
          }
          checkPromoAndActivate(s);
        })
        .catch(function () {
          checkPromoAndActivate(null);
        });
    }

    function markNetworkActiveOnServer() {
      postPromoStatus({ networkActive: true })
        .then(function (s) {
          if (s && s.ok) {
            checkPromoAndActivate(s);
          }
        })
        .catch(function () {});
    }

    function schedulePromoCheck(delayMs) {
      setTimeout(function () {
        paintOwnCouponGreen();
        fetchPromoStatusThenActivate();
      }, delayMs || 0);
      setTimeout(function () {
        paintOwnCouponGreen();
        fetchPromoStatusThenActivate();
      }, (delayMs || 0) + 400);
      setTimeout(function () {
        paintOwnCouponGreen();
        fetchPromoStatusThenActivate();
      }, (delayMs || 0) + 1200);
    }

    function isCouponUiTarget(target) {
      if (!target || !target.closest) return false;
      return !!(
        target.closest('[data-entity="basket-coupon-button"]') ||
        target.closest('.basket-coupon-block-btn-t') ||
        target.closest('.basket-coupon-block-coupon-btn') ||
        target.closest('.bx-soa-coupon-input') ||
        target.closest('[data-entity="basket-coupon-field"]')
      );
    }

    // Купон уже был на странице при загрузке (не «только что ввели») —
    // не флешим сумму; при необходимости только доактивируем сеть.
    if (promoOwn || promoNeedsActivation || hasOwnCouponInDom()) {
      ownCouponSeen = true;
    }

    var forceWelcomeAfterOwn = peekWelcomeAfterOwnPromo();
    if (promoNeedsActivation || forceWelcomeAfterOwn) {
      // Hop без повторного flash. One-shot флаг переживает hop → force welcome.
      if (!forceWelcomeAfterOwn && promoNeedsActivation && needsActivateHop()) {
        markWelcomeAfterOwnPromo();
      }
      if (activateViaRef(true)) {
        return;
      }
      if (consumeWelcomeAfterOwnPromo()) {
        forceOpenWelcome();
      } else if (promoNeedsActivation) {
        // pla_ref уже есть, флага нет — soft open (уважаем dismiss).
        try {
          if (window.PLATEAM && typeof window.PLATEAM.openWelcome === 'function') {
            window.PLATEAM.openWelcome({ force: false });
          }
        } catch (eAct) {}
      }
    }

    function loadPick() {
      try {
        var raw = sessionStorage.getItem(PICK_KEY);
        if (!raw) return;
        var saved = JSON.parse(raw);
        if (saved && typeof saved === 'object') {
          pick.ses = !!saved.ses;
          pick.ues = !!saved.ues;
        }
      } catch (e) {}
    }

    function savePick() {
      try {
        sessionStorage.setItem(PICK_KEY, JSON.stringify({ ses: pick.ses, ues: pick.ues }));
      } catch (e) {}
    }

    function consumeReset() {
      var reset = false;
      if (document.cookie.indexOf('plateam_clear_cert_pick=1') !== -1) {
        reset = true;
      }
      if (orderTotalKop <= 0) {
        reset = true;
      }
      if (!reset) return false;
      pick.ses = false;
      pick.ues = false;
      try {
        sessionStorage.removeItem(PICK_KEY);
      } catch (e) {}
      document.cookie = 'plateam_clear_cert_pick=; path=/; max-age=0';
      return true;
    }

    if (!consumeReset()) {
      loadPick();
    }

    if (baseTotal.kop > 0) {
      baseTotal.formatted = rub(baseTotal.kop);
    }

    function rub(kop) {
      return (kop / 100).toLocaleString('ru-RU', { minimumFractionDigits: 0 }) + ' ₽';
    }

    function parseRub(text) {
      var cleaned = String(text || '')
        .replace(/\u00a0/g, ' ')
        .replace(/[^\d,.\s]/g, '')
        .trim()
        .replace(/\s/g, '')
        .replace(',', '.');
      var rubVal = parseFloat(cleaned);
      if (isNaN(rubVal)) return 0;
      return Math.round(rubVal * 100);
    }

    function setBaseTotal(kop) {
      if (!(kop > 0)) return;
      baseTotal.kop = kop;
      baseTotal.formatted = rub(kop);
      orderTotalKop = kop;
      root.setAttribute('data-order-total-kop', String(kop));
    }

    function effectiveOrderTotalKop() {
      return baseTotal.kop > 0 ? baseTotal.kop : orderTotalKop;
    }

    function waitSession(maxMs, cb) {
      var t0 = Date.now();
      (function tick() {
        if (window.PLATEAM && typeof window.PLATEAM.getSession === 'function') {
          var s = window.PLATEAM.getSession();
          if (s) return cb(s);
        }
        if (Date.now() - t0 > maxMs) return cb(null);
        setTimeout(tick, 200);
      })();
    }

    function allocateFromPick(totalKop, sesBal, uesBal) {
      var need = totalKop;
      var sesKop = 0;
      var uesKop = 0;
      if (pick.ses) {
        sesKop = Math.min(sesBal || 0, need);
        need -= sesKop;
      }
      if (pick.ues && need > 0) {
        uesKop = Math.min(uesBal || 0, need);
        need -= uesKop;
      }
      return {
        sesKop: sesKop,
        uesKop: uesKop,
        certKop: sesKop + uesKop,
        cashKop: need,
        sesAvail: sesBal,
        uesAvail: uesBal,
      };
    }

    function calcUse(session) {
      var bal = (session && session.balance) || {};
      return allocateFromPick(
        effectiveOrderTotalKop(),
        bal.sesForThisPartnerKop || 0,
        bal.uesKop || 0,
      );
    }

    function projectIssue(session) {
      if (window.PLATEAM && typeof window.PLATEAM.projectIssue === 'function') {
        return window.PLATEAM.projectIssue(effectiveOrderTotalKop());
      }
      var pct = (session && session.discountPct) || 10;
      var total = effectiveOrderTotalKop();
      var discountKop = Math.floor((total * pct) / 100);
      return { sesKop: 0, uesKop: 0, discountKop: discountKop, discountPct: pct };
    }

    function captureBaseFromBasketComponent(comp) {
      if (!comp || !comp.result || !comp.result.TOTAL_RENDER_DATA) return;
      var data = comp.result.TOTAL_RENDER_DATA;
      var priceRub = parseFloat(data.PRICE);
      if (isNaN(priceRub) || priceRub <= 0) return;
      setBaseTotal(Math.round(priceRub * 100));
    }

    function captureBaseFromSoaComponent(comp) {
      if (!comp || !comp.result || !comp.result.TOTAL) return;
      var total = comp.result.TOTAL;
      var priceRub = parseFloat(total.ORDER_TOTAL_PRICE);
      if (isNaN(priceRub) || priceRub <= 0) return;
      setBaseTotal(Math.round(priceRub * 100));
    }

    function captureBaseFromDomIfNeeded() {
      if (pick.ses || pick.ues) return;
      var priceEl = document.querySelector('[data-entity="basket-total-price"]');
      if (priceEl) {
        var kop = parseRub(priceEl.textContent);
        if (kop > 0) setBaseTotal(kop);
        return;
      }
      var soaTotal = document.querySelector('.bx-soa-cart-total-line-total .bx-soa-cart-d');
      if (soaTotal) {
        var soaKop = parseRub(soaTotal.textContent);
        if (soaKop > 0) setBaseTotal(soaKop);
      }
    }

    function syncCartTotals(u) {
      var priceEl = document.querySelector('[data-entity="basket-total-price"]');
      if (!priceEl) return;

      var descEl = document.querySelector('.basket-checkout-block-total-description');

      if (u.certKop > 0) {
        priceEl.textContent = rub(u.cashKop);
        priceEl.classList.add('plateam-total-adjusted');
        if (descEl) {
          var note = descEl.querySelector('.plateam-cart-cert-note');
          if (!note) {
            note = document.createElement('div');
            note.className = 'plateam-cart-cert-note';
            descEl.appendChild(note);
          }
          var parts = ['Сертификатами PLATEAM: ' + rub(u.certKop)];
          if (u.sesKop > 0) parts.push('собственный ' + rub(u.sesKop));
          if (u.uesKop > 0) parts.push('универсальный ' + rub(u.uesKop));
          note.textContent = parts.join(' · ');
        }
      } else {
        if (priceEl.classList.contains('plateam-total-adjusted')) {
          priceEl.textContent = rub(baseTotal.kop);
          priceEl.classList.remove('plateam-total-adjusted');
        }
        if (descEl) {
          var old = descEl.querySelector('.plateam-cart-cert-note');
          if (old) old.remove();
        }
      }
    }

    function syncCheckoutTotals(u) {
      document.querySelectorAll('.bx-soa-cart-total').forEach(function (container) {
        var totalValue = container.querySelector('.bx-soa-cart-total-line-total .bx-soa-cart-d');
        if (!totalValue) return;

        var certLine = container.querySelector('.plateam-cert-line');

        if (u.certKop > 0) {
          if (!certLine) {
            certLine = document.createElement('div');
            certLine.className =
              'bx-soa-cart-total-line bx-soa-cart-total-line-highlighted plateam-cert-line';
            certLine.innerHTML =
              '<span class="bx-soa-cart-t">Сертификатами PLATEAM</span>' +
              '<span class="bx-soa-cart-d plateam-cert-d"></span>';
            var totalRow = container.querySelector('.bx-soa-cart-total-line-total');
            if (totalRow) container.insertBefore(certLine, totalRow);
            else container.appendChild(certLine);
          }
          var certValue = certLine.querySelector('.plateam-cert-d');
          if (certValue) certValue.textContent = '−' + rub(u.certKop);
          totalValue.textContent = rub(u.cashKop);
          totalValue.classList.add('plateam-total-adjusted');
        } else {
          if (certLine) certLine.remove();
          if (totalValue.classList.contains('plateam-total-adjusted')) {
            totalValue.classList.remove('plateam-total-adjusted');
          }
          // Без сертификатов оставляем разметку Bitrix (innerHTML с валютой) как есть.
        }
      });
    }

    function applyBitrixTotals(u) {
      if (applyingSync) return;
      applyingSync = true;
      try {
        syncCartTotals(u);
        syncCheckoutTotals(u);
      } finally {
        applyingSync = false;
      }
    }

    function refreshBitrixTotals() {
      if (!sessionRef) return;
      applyBitrixTotals(calcUse(sessionRef));
    }

    function hookBitrixComponents() {
      if (!window.BX || !window.BX.Sale) return false;
      var hooked = false;

      if (BX.Sale.BasketComponent && BX.Sale.BasketComponent.prototype) {
        var basketProto = BX.Sale.BasketComponent.prototype;
        if (!basketProto.__plateamFillTotalHook) {
          basketProto.__plateamFillTotalHook = true;
          var origFill = basketProto.fillTotalBlocks;
          basketProto.fillTotalBlocks = function () {
            origFill.apply(this, arguments);
            captureBaseFromBasketComponent(this);
            refreshBitrixTotals();
          };
          hooked = true;
        }
      }

      if (BX.Sale.OrderAjaxComponent && !BX.Sale.OrderAjaxComponent.__plateamEditTotalHook) {
        BX.Sale.OrderAjaxComponent.__plateamEditTotalHook = true;
        var origEdit = BX.Sale.OrderAjaxComponent.editTotalBlock;
        BX.Sale.OrderAjaxComponent.editTotalBlock = function () {
          origEdit.apply(this, arguments);
          captureBaseFromSoaComponent(this);
          refreshBitrixTotals();
        };
        hooked = true;
      }

      return hooked;
    }

    function waitForBitrixHooks(maxMs) {
      var t0 = Date.now();
      (function tick() {
        if (hookBitrixComponents()) return;
        if (Date.now() - t0 > maxMs) return;
        setTimeout(tick, 150);
      })();
    }

    function brandHeadingHtml() {
      var src = platformOrigin
        ? platformOrigin + '/brand/plateam-wordmark-light.svg?v=2'
        : '';
      if (!src && window.PLATEAM && window.PLATEAM.appOrigin) {
        src =
          String(window.PLATEAM.appOrigin).replace(/\/$/, '') +
          '/brand/plateam-wordmark-light.svg?v=2';
      }
      if (src) {
        return (
          '<h4 class="plateam-checkout-brand"><img src="' +
          src +
          '" alt="PLATEAM" width="140" height="22" decoding="async" /></h4>'
        );
      }
      return '<h4 class="plateam-checkout-brand">PLATEAM</h4>';
    }

    function issueBlockHtml(session, proj) {
      var msgs = (session && session.messages) || {};
      var title =
        msgs.cart_pending_status ||
        'После оплаты заказа вам будут начислены сертификаты.';
      return (
        '<div class="plateam-issue-block">' +
        '<p class="plateam-issue-title">' +
        title +
        '</p>' +
        '<ul class="plateam-proj">' +
        '<li>Собственный — <strong>' +
        rub(proj.sesKop) +
        '</strong></li>' +
        '<li>Универсальный — <strong>' +
        rub(proj.uesKop) +
        '</strong></li>' +
        '<li><strong>Итого начисление: ' +
        rub(proj.discountKop) +
        ' (' +
        proj.discountPct +
        '% от заказа)</strong></li>' +
        '</ul></div>'
      );
    }

    function renderPending(session) {
      var msgs = (session && session.messages) || {};
      var proj = projectIssue(session);
      var intro =
        summaryOnly
          ? msgs.checkout_summary_status ||
            'Сертификаты для списания выбираются в корзине.'
          : msgs.cart_pending_status ||
            'После оплаты заказа вам будут начислены сертификаты.';

      root.innerHTML =
        '<div class="plateam-checkout">' +
        brandHeadingHtml() +
        '<p class="muted">' +
        intro +
        '</p>' +
        issueBlockHtml(session, proj) +
        '</div>';
      applyBitrixTotals(calcUse(session));
    }

    function renderSummary(session) {
      var proj = projectIssue(session);
      var msgs = (session && session.messages) || {};

      root.innerHTML =
        '<div class="plateam-checkout plateam-checkout--summary">' +
        brandHeadingHtml() +
        '<p class="muted">' +
        (msgs.checkout_summary_status ||
          'Сертификаты для списания выбираются в корзине. Итог — в блоке «Итого» справа.') +
        '</p>' +
        issueBlockHtml(session, proj) +
        '</div>';
      applyBitrixTotals(calcUse(session));
    }

    function renderTiles(session) {
      var u = calcUse(session);
      var proj = projectIssue(session);
      var msgs = (session && session.messages) || {};

      var tiles = '';
      if (u.sesAvail > 0) {
        tiles +=
          '<button type="button" class="plateam-cert-tile' +
          (pick.ses ? ' is-on' : '') +
          '" data-toggle-cert="ses" aria-pressed="' +
          (pick.ses ? 'true' : 'false') +
          '">' +
          '<span class="plateam-cert-label">Собственный</span>' +
          '<span class="plateam-cert-sum">' +
          rub(u.sesAvail) +
          '</span>' +
          '<span class="plateam-cert-use">' +
          (pick.ses ? 'к списанию ' + rub(u.sesKop) : 'нажмите, чтобы выбрать') +
          '</span></button>';
      }
      if (u.uesAvail > 0) {
        tiles +=
          '<button type="button" class="plateam-cert-tile' +
          (pick.ues ? ' is-on' : '') +
          '" data-toggle-cert="ues" aria-pressed="' +
          (pick.ues ? 'true' : 'false') +
          '">' +
          '<span class="plateam-cert-label">Универсальный</span>' +
          '<span class="plateam-cert-sum">' +
          rub(u.uesAvail) +
          '</span>' +
          '<span class="plateam-cert-use">' +
          (pick.ues ? 'к списанию ' + rub(u.uesKop) : 'нажмите, чтобы выбрать') +
          '</span></button>';
      }

      root.innerHTML =
        '<div class="plateam-checkout">' +
        brandHeadingHtml() +
        '<p class="muted">' +
        (msgs.cart_apply_status || 'Выберите, с какого сертификата погасить оплату.') +
        '</p>' +
        '<p class="plateam-hint">Выберите сертификат, с которого хотите списать средства. Если выбраны оба — сначала списывается с собственного, остаток — с универсального.</p>' +
        '<div class="plateam-cert-tiles" role="group" aria-label="Сертификаты PLATEAM">' +
        tiles +
        '</div>' +
        issueBlockHtml(session, proj) +
        '</div>';

      applyBitrixTotals(u);

      root.querySelectorAll('[data-toggle-cert]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var kind = btn.getAttribute('data-toggle-cert');
          if (kind === 'ses') pick.ses = !pick.ses;
          if (kind === 'ues') pick.ues = !pick.ues;
          savePick();
          var nextU = calcUse(session);
          applyBitrixTotals(nextU);
          renderTiles(session);
          stash(session);
        });
      });
    }

    function render(session) {
      sessionRef = session;
      if (!session) {
        root.innerHTML =
          '<div class="plateam-checkout muted-box">' +
          brandHeadingHtml() +
          '<p class="muted">Не удалось подключить виджет. Обновите страницу или зайдите по реферальной ссылке сети.</p></div>';
        return;
      }
      if (session.ui === 'silent') {
        // Чужой промокод здесь ок: сеть не активируем.
        if (promoOwn || promoNeedsActivation || hasOwnCouponInDom()) {
          if (!ownCouponSeen) {
            ownCouponSeen = true;
            runOwnPromoEntryUx();
            return;
          }
          markWelcomeAfterOwnPromo();
          if (activateViaRef(true)) {
            return;
          }
          if (peekWelcomeAfterOwnPromo()) {
            forceOpenWelcome();
            consumeWelcomeAfterOwnPromo();
          }
          root.innerHTML =
            '<div class="plateam-checkout muted-box">' +
            brandHeadingHtml() +
            '<p class="muted">Промокод ' +
            promoOwnCode +
            ' применён. Завершаем активацию сети… Обновите страницу, если окно не появилось.</p></div>';
          return;
        }
        root.innerHTML =
          '<div class="plateam-checkout muted-box">' +
          brandHeadingHtml() +
          '<p class="muted">Зайдите по реферальной ссылке PLATEAM или введите промокод ' +
          promoOwnCode +
          ' в корзине, чтобы использовать сертификаты.</p></div>';
        return;
      }

      // ui !== silent: система уже активна (реф или свой промо) — чужие блокируем.
      if (!window.__plateamNetworkLockSent) {
        window.__plateamNetworkLockSent = true;
        markNetworkActiveOnServer();
      }

      captureBaseFromDomIfNeeded();

      if (effectiveOrderTotalKop() <= 0) {
        root.innerHTML =
          '<div class="plateam-checkout">' +
          brandHeadingHtml() +
          '<p class="muted">Добавьте товары в корзину — здесь появится выбор сертификатов.</p></div>';
        return;
      }

      if (summaryOnly) {
        renderSummary(session);
        return;
      }

      var bal = session.balance || {};
      var spendable = (bal.sesForThisPartnerKop || 0) + (bal.uesKop || 0);
      if (spendable <= 0) {
        renderPending(session);
        return;
      }

      renderTiles(session);
    }

    function stashPayload(session) {
      var u = calcUse(session);
      return {
        visitorId: session.visitorId || '',
        userId: session.userId || '',
        sesKop: u.sesKop,
        uesKop: u.uesKop,
        useSes: pick.ses,
        useUes: pick.ues,
        orderTotalKop: effectiveOrderTotalKop(),
      };
    }

    function stash(session) {
      if (!stashUrl || !session) return Promise.resolve();
      return fetch(stashUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(stashPayload(session)),
      }).catch(function () {});
    }

    /** Не блокируем submit Bitrix — только успеваем записать stash до AJAX. */
    function stashBeacon(session) {
      if (!stashUrl || !session) return;
      var payload = stashPayload(session);
      var params = new URLSearchParams();
      Object.keys(payload).forEach(function (key) {
        params.append(key, String(payload[key]));
      });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(stashUrl, params);
        return;
      }
      fetch(stashUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: params.toString(),
        keepalive: true,
      }).catch(function () {});
    }

    function isCartToOrderClick(target) {
      var el = target.closest('a, button, input[type="submit"], input[type="button"]');
      if (!el) return false;
      var href = (el.getAttribute('href') || el.getAttribute('data-url') || '').toLowerCase();
      if (href.indexOf('/personal/order/make') !== -1) return true;
      var text = (el.textContent || el.value || '').toLowerCase();
      return text.indexOf('оформить') !== -1;
    }

    function isCheckoutPayClick(target) {
      var btn = target.closest('button, input[type="submit"], input[type="button"], a');
      if (!btn) return false;
      var form = btn.closest('form');
      if (!form) return false;
      if (form.id === 'bx-soa-order-form') return true;
      if (form.querySelector('.bx-soa-section')) return true;
      if (btn.name === 'confirmorder' || btn.name === 'BasketOrder') return true;
      return false;
    }

    waitForBitrixHooks(20000);

    paintOwnCouponGreen();
    setTimeout(paintOwnCouponGreen, 500);
    setTimeout(paintOwnCouponGreen, 1500);

    if (window.BX && typeof window.BX.addCustomEvent === 'function') {
      window.BX.addCustomEvent('OnBasketChange', function () {
        paintOwnCouponGreen();
        schedulePromoCheck(50);
      });
      window.BX.addCustomEvent('OnCouponApply', function () {
        paintOwnCouponGreen();
        schedulePromoCheck(50);
      });
      window.BX.addCustomEvent('onCouponApply', function () {
        paintOwnCouponGreen();
        schedulePromoCheck(50);
      });
    }

    document.addEventListener(
      'click',
      function (ev) {
        if (isCouponUiTarget(ev.target)) {
          schedulePromoCheck(300);
        }
      },
      true,
    );

    document.addEventListener(
      'keydown',
      function (ev) {
        if (ev.key !== 'Enter') return;
        var t = ev.target;
        if (!t) return;
        var isCouponInput =
          (t.getAttribute && t.getAttribute('data-entity') === 'basket-coupon-input') ||
          (t.classList && t.classList.contains('basket-coupon-block-field-input')) ||
          (t.name && String(t.name).toLowerCase().indexOf('coupon') !== -1);
        if (isCouponInput) {
          schedulePromoCheck(300);
        }
      },
      true,
    );

    if (!summaryOnly && typeof MutationObserver === 'function') {
      var couponRoots = document.querySelectorAll(
        '[data-entity="basket-coupon-list"], .basket-coupon-section, .bx-soa-coupon',
      );
      if (couponRoots.length) {
        var mo = new MutationObserver(function () {
          schedulePromoCheck(100);
        });
        for (var ci = 0; ci < couponRoots.length; ci++) {
          mo.observe(couponRoots[ci], { childList: true, subtree: true, characterData: true });
        }
      } else {
        // Корзина ещё не отрисовала блок купонов — слушаем body коротко.
        var bodyMo = new MutationObserver(function () {
          if (hasOwnCouponInDom()) {
            schedulePromoCheck(50);
          }
        });
        bodyMo.observe(document.body, { childList: true, subtree: true });
        setTimeout(function () {
          try {
            bodyMo.disconnect();
          } catch (e) {}
        }, 120000);
      }
    }

    // Периодическая страховка на случай, если Bitrix не шлёт OnBasketChange.
    if (!summaryOnly) {
      var pollN = 0;
      var pollId = setInterval(function () {
        pollN += 1;
        if (promoActivateInFlight || pollN > 90) {
          clearInterval(pollId);
          return;
        }
        if (hasOwnCouponInDom()) {
          fetchPromoStatusThenActivate();
          clearInterval(pollId);
        }
      }, 1000);
    }

    if (window.PLATEAM && typeof window.PLATEAM.onSession === 'function') {
      window.PLATEAM.onSession(function (s) {
        render(s);
      });
    }

    waitSession(15000, function (session) {
      captureBaseFromDomIfNeeded();
      render(session);
      if (session && session.visitorId && !summaryOnly) {
        stash(session);
      }
    });

    document.addEventListener(
      'click',
      function (ev) {
        if (!sessionRef) return;
        if (isCheckoutPayClick(ev.target) || isCartToOrderClick(ev.target)) {
          stashBeacon(sessionRef);
        }
      },
      true,
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
