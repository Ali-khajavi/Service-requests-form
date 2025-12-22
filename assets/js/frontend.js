function SRF_onReady(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    fn();
  }
}

/* =========================================================
   Slider (simple image switcher)
========================================================= */
(function () {
  'use strict';

  function parseImages(slider) {
    var raw = slider.getAttribute('data-images');
    if (!raw) return [];
    try {
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      console.error('Error parsing slider images:', e);
      return [];
    }
  }

  function initSlider(slider) {
    var imgEl  = slider.querySelector('.srf-service-slider__image');
    var prev   = slider.querySelector('.srf-service-slider__prev');
    var next   = slider.querySelector('.srf-service-slider__next');
    var images = parseImages(slider);

    if (!imgEl || !images.length) return;

    var index = 0;

    function show(i) {
      index = i;
      var item = images[index];
      if (!item || !item.url) return;

      imgEl.src = item.url;
      imgEl.alt = item.alt || '';
      imgEl.style.display = 'block';
    }

    var nav = slider.querySelector('.srf-service-slider__nav');
    if (images.length <= 1) {
      if (nav) nav.style.display = 'none';
      if (prev) prev.style.display = 'none';
      if (next) next.style.display = 'none';
    }

    if (prev) {
      prev.onclick = function (e) {
        e.preventDefault();
        show((index - 1 + images.length) % images.length);
      };
    }

    if (next) {
      next.onclick = function (e) {
        e.preventDefault();
        show((index + 1) % images.length);
      };
    }

    show(0);
  }

  function initAll(scope) {
    var sliders = (scope || document).querySelectorAll(
      '.srf-service-slider[data-srf-slider="switcher"]'
    );
    for (var i = 0; i < sliders.length; i++) {
      initSlider(sliders[i]);
    }
  }

  window.SRF_initSliders = initAll;
})();

/* =========================================================
   Dynamic service info switching (dropdown only)
========================================================= */
(function () {
  'use strict';

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttr(str) {
    return String(str).replace(/"/g, '&quot;');
  }

  function buildServiceInfoHTML(service) {
    var title   = service.title || '';
    var content = service.content || '';
    var images  = Array.isArray(service.images) ? service.images : [];

    var sliderHtml = '';
    if (images.length) {
      var first = images[0];
      sliderHtml =
        '<div class="srf-service-slider" data-srf-slider="switcher" data-images="' +
        escapeAttr(JSON.stringify(images)) +
        '">' +
          '<div class="srf-service-slider__viewport">' +
            '<img class="srf-service-slider__image" src="' +
            escapeAttr(first.url) +
            '" alt="' +
            escapeAttr(first.alt || '') +
            '" loading="lazy" />' +
          '</div>' +
          '<div class="srf-service-slider__nav">' +
            '<button type="button" class="srf-service-slider__prev">&#10094;</button>' +
            '<button type="button" class="srf-service-slider__next">&#10095;</button>' +
          '</div>' +
        '</div>';
    }

    return (
      '<div class="srf-service-info" data-service-id="' + escapeAttr(service.id) + '">' +
        '<h2 class="srf-service-info__title">' + escapeHtml(title) + '</h2>' +
        '<div class="srf-service-info__text is-collapsed" data-srf-collapsible="text">' + content + '</div>' +
        '<button type="button" class="srf-service-info__toggle" data-srf-toggle="text">Show more</button>' +
        sliderHtml +
      '</div>'
    );
  }

  function initializeServiceInfo() {
    var select =
      document.getElementById('srf-service') ||
      document.querySelector('select[name="srf_service"]');

    if (!select) return;

    var rawServices =
      window.srfServiceData ||
      window.srfServices ||
      (typeof srfServices !== 'undefined' ? srfServices : null);

    if (!rawServices) {
      console.warn('SRF: services data not found (window.srfServiceData missing).');
      return;
    }

    // Stable lookup by string ID (supports object or array)
    var servicesById = {};
    if (Array.isArray(rawServices)) {
      for (var i = 0; i < rawServices.length; i++) {
        var s = rawServices[i];
        if (s && s.id != null) servicesById[String(s.id)] = s;
      }
    } else {
      for (var k in rawServices) {
        if (!Object.prototype.hasOwnProperty.call(rawServices, k)) continue;
        var sv = rawServices[k];
        var id = (sv && sv.id != null) ? sv.id : k;
        servicesById[String(id)] = sv;
      }
    }

    var host = document.querySelector('.srf-layout__service-info');
    if (!host) {
      host = document.createElement('div');
      host.className = 'srf-layout__service-info';
      select.parentNode.appendChild(host);
    }

    function updateServiceInfo(serviceId) {
      if (!serviceId) {
        host.innerHTML =
          '<div class="srf-service-info"><h2 class="srf-service-info__title">Please select a service</h2></div>';
        // Collapsible long text
          var txt = host.querySelector('[data-srf-collapsible="text"]');
          var btn = host.querySelector('[data-srf-toggle="text"]');

          if (txt && btn) {
            btn.addEventListener('click', function () {
              var collapsed = txt.classList.toggle('is-collapsed');
              btn.textContent = collapsed ? 'Show more' : 'Show less';
            });
          }
          return;
      }

      var sid = String(serviceId);
      var service = servicesById[sid];
      if (!service) return;

      host.innerHTML = buildServiceInfoHTML(service);

      if (window.SRF_initSliders) {
        setTimeout(function () {
          window.SRF_initSliders(host);
        }, 30);
      }
    }

    // Init: if no selection, keep placeholder option selected
    updateServiceInfo(select.value);

    select.addEventListener('change', function () {
      updateServiceInfo(this.value);
    });
  }

  SRF_onReady(function () {
    initializeServiceInfo();
    if (window.SRF_initSliders) window.SRF_initSliders(document);
  });
})();

document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-srf-toggle="text"]');
  if (!btn) return;

  var box = btn.closest('.srf-service-info');
  if (!box) return;

  var txt = box.querySelector('[data-srf-collapsible="text"]');
  if (!txt) return;

  var collapsed = txt.classList.toggle('is-collapsed');
  btn.textContent = collapsed ? 'Show more' : 'Show less';
});



/* =========================================================
   Gate form submission to business_user only
========================================================= */
(function () {
  'use strict';

  function createPopup() {
    var backdrop = document.querySelector('.srf-popup-backdrop');
    if (backdrop) {
      backdrop.style.display = 'flex';
      return;
    }

    backdrop = document.createElement('div');
    backdrop.className = 'srf-popup-backdrop';

    var box = document.createElement('div');
    box.className = 'srf-popup';

    box.innerHTML =
      '<h3 class="srf-popup__title">' +
      ((window.srfFrontend && srfFrontend.popup_title) || 'Business account required') +
      '</h3>' +
      '<p class="srf-popup__message">' +
      ((window.srfFrontend && srfFrontend.popup_message) ||
        'To submit a service request you must have a Business account.') +
      '</p>' +
      '<button type="button" class="srf-popup__button">' +
      ((window.srfFrontend && srfFrontend.popup_button) || 'OK') +
      '</button>';

    box.querySelector('button').onclick = function () {
      backdrop.style.display = 'none';
    };

    backdrop.onclick = function (e) {
      if (e.target === backdrop) backdrop.style.display = 'none';
    };

    backdrop.appendChild(box);
    document.body.appendChild(backdrop);
  }

  SRF_onReady(function () {
    if (!window.srfFrontend || window.srfFrontend.can_submit) return;

    var forms = document.querySelectorAll('.srf-form');
    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', function (e) {
        e.preventDefault();
        createPopup();
      });
    }
  });
})();
