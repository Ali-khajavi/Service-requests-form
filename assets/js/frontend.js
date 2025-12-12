function SRF_onReady(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    fn();
  }
}

// Slider (simple image switcher)
(function () {
  'use strict';

  function parseImages(slider) {
    var raw = slider.getAttribute('data-images');
    if (!raw) return [];
    try {
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function initSlider(slider) {
    var imgEl = slider.querySelector('.srf-service-slider__image');
    var prev  = slider.querySelector('.srf-service-slider__prev');
    var next  = slider.querySelector('.srf-service-slider__next');
    var images = parseImages(slider);

    if (!imgEl || !images.length) return;

    var index = 0;

    function show(i) {
      index = i;
      var item = images[index];
      if (!item || !item.url) return;
      imgEl.src = item.url;
      imgEl.alt = item.alt || '';
    }

    if (images.length <= 1) {
      if (prev) prev.style.display = 'none';
      if (next) next.style.display = 'none';
    } else {
      if (prev) prev.style.display = '';
      if (next) next.style.display = '';
    }

    if (prev) prev.onclick = null;
    if (next) next.onclick = null;

    if (prev) prev.onclick = function (e) {
      e.preventDefault();
      show((index - 1 + images.length) % images.length);
    };

    if (next) next.onclick = function (e) {
      e.preventDefault();
      show((index + 1) % images.length);
    };

    show(0);
  }

  function initAll(scope) {
    var sliders = (scope || document).querySelectorAll('.srf-service-slider[data-srf-slider="switcher"]');
    for (var i = 0; i < sliders.length; i++) initSlider(sliders[i]);
  }

  window.SRF_initSliders = initAll;

  SRF_onReady(function () {
    initAll(document);
  });
})();


// Phase 4: Dynamic service info switching (title + text + images)
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
    var title   = service && service.title ? service.title : '';
    var content = service && service.content ? service.content : '';
    var images  = (service && Array.isArray(service.images)) ? service.images : [];

    var sliderHtml = '';
    if (images.length) {
      var first = images[0];
      sliderHtml =
        '<div class="srf-service-slider" data-srf-slider="switcher" data-images="' + escapeAttr(JSON.stringify(images)) + '">' +
          '<div class="srf-service-slider__viewport">' +
            '<img class="srf-service-slider__image" src="' + escapeAttr(first.url) + '" alt="' + escapeAttr(first.alt || "") + '" loading="lazy" />' +
          '</div>' +
          '<div class="srf-service-slider__nav">' +
            '<button type="button" class="srf-service-slider__prev" aria-label="Previous image">&#10094;</button>' +
            '<button type="button" class="srf-service-slider__next" aria-label="Next image">&#10095;</button>' +
          '</div>' +
        '</div>';
    }

    return (
      '<div class="srf-service-info" data-service-id="' + escapeAttr(String(service.id || '')) + '">' +
        '<h2 class="srf-service-info__title">' + escapeHtml(title) + '</h2>' +
        '<div class="srf-service-info__text">' + content + '</div>' +
        sliderHtml +
      '</div>'
    );
  }

  function findService(services, id) {
    if (!services) return null;
    if (services[id]) return services[id];

    var n = parseInt(id, 10);
    if (!isNaN(n) && services[n]) return services[n];

    for (var k in services) {
      if (!Object.prototype.hasOwnProperty.call(services, k)) continue;
      var s = services[k];
      if (s && String(s.id) === String(id)) return s;
    }
    return null;
  }

  SRF_onReady(function () {
    var select = document.getElementById('srf-service');

    // wp_localize_script outputs: var srfServices = {...}
    var services = (typeof window.srfServices !== 'undefined' && window.srfServices) ||
                   (typeof srfServices !== 'undefined' ? srfServices : null);

    if (!select || !services) return;

    var host = document.querySelector('.srf-layout__service-info');
    if (!host) return;

    select.addEventListener('change', function () {
      var id = String(select.value || '');
      var service = findService(services, id);
      if (!service) return;

      host.innerHTML = buildServiceInfoHTML(service);

      if (window.SRF_initSliders) {
        window.SRF_initSliders(host);
      }
    });
  });
})();


// Gate form submission to business_user only
(function () {
  'use strict';

  function createPopup() {
    var existing = document.querySelector('.srf-popup-backdrop');
    if (existing) {
      existing.style.display = 'flex';
      return existing;
    }

    var backdrop = document.createElement('div');
    backdrop.className = 'srf-popup-backdrop';

    var box = document.createElement('div');
    box.className = 'srf-popup';

    var title = document.createElement('h3');
    title.className = 'srf-popup__title';
    title.textContent = (window.srfFrontend && window.srfFrontend.popup_title) || 'Business account required';

    var msg = document.createElement('p');
    msg.className = 'srf-popup__message';
    msg.textContent = (window.srfFrontend && window.srfFrontend.popup_message) ||
      'To submit a service request you must have a Business account. Please contact our IT team.';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'srf-popup__button';
    btn.textContent = (window.srfFrontend && window.srfFrontend.popup_button) || 'OK';

    btn.addEventListener('click', function () {
      backdrop.style.display = 'none';
    });

    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) backdrop.style.display = 'none';
    });

    box.appendChild(title);
    box.appendChild(msg);
    box.appendChild(btn);
    backdrop.appendChild(box);

    document.body.appendChild(backdrop);
    return backdrop;
  }

  SRF_onReady(function () {
    if (!window.srfFrontend || window.srfFrontend.can_submit) return;

    var forms = document.querySelectorAll('.srf-form');
    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        createPopup();
      });
    }
  });
})();
