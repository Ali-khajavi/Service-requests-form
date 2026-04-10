function SRF_onReady(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    fn();
  }
}

/* =========================================================
   Slider + zoom popup
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

  function getLightbox() {
    var box = document.getElementById('srf-image-lightbox');
    if (box) return box;

    box = document.createElement('div');
    box.id = 'srf-image-lightbox';
    box.className = 'srf-image-lightbox';
    box.innerHTML =
      '<div class="srf-image-lightbox__inner">' +
        '<button type="button" class="srf-image-lightbox__close" aria-label="Close">&times;</button>' +
        '<img class="srf-image-lightbox__img" src="" alt="">' +
      '</div>';

    document.body.appendChild(box);

    box.addEventListener('click', function (e) {
      if (
        e.target === box ||
        e.target.classList.contains('srf-image-lightbox__close')
      ) {
        box.classList.remove('is-open');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        box.classList.remove('is-open');
      }
    });

    return box;
  }

  function openLightbox(src, alt) {
    if (!src) return;
    var box = getLightbox();
    var img = box.querySelector('.srf-image-lightbox__img');
    img.src = src;
    img.alt = alt || '';
    box.classList.add('is-open');
  }

  function ensureZoomButton(viewport) {
    if (!viewport) return null;

    var btn = viewport.querySelector('.srf-service-slider__zoom');
    if (btn) return btn;

    btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'srf-service-slider__zoom';
    btn.setAttribute('aria-label', 'View larger image');
    btn.setAttribute('title', 'View larger image');
    btn.innerHTML = '&#128269;';
    viewport.appendChild(btn);

    return btn;
  }

  function initSlider(slider) {
    if (!slider || slider.getAttribute('data-srf-slider-ready') === '1') {
      return;
    }

    var imgEl     = slider.querySelector('.srf-service-slider__image');
    var viewport  = slider.querySelector('.srf-service-slider__viewport');
    var prev      = slider.querySelector('.srf-service-slider__prev');
    var next      = slider.querySelector('.srf-service-slider__next');
    var nav       = slider.querySelector('.srf-service-slider__nav');
    var images    = parseImages(slider);

    if (!imgEl || !viewport || !images.length) return;

    var zoomBtn = ensureZoomButton(viewport);
    var index = 0;

    function show(i) {
      index = i;
      var item = images[index];
      if (!item || !item.url) return;

      imgEl.src = item.url;
      imgEl.alt = item.alt || '';
      imgEl.style.display = 'block';
      viewport.setAttribute('data-current-src', item.url);
      viewport.setAttribute('data-current-alt', item.alt || '');
    }

    if (images.length <= 1) {
      if (nav) nav.style.display = 'none';
      if (prev) prev.style.display = 'none';
      if (next) next.style.display = 'none';
    }

    if (prev) {
      prev.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        show((index - 1 + images.length) % images.length);
      };
    }

    if (next) {
      next.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        show((index + 1) % images.length);
      };
    }

    if (zoomBtn) {
      zoomBtn.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        openLightbox(
          viewport.getAttribute('data-current-src'),
          viewport.getAttribute('data-current-alt')
        );
      };
    }

    viewport.addEventListener('click', function () {
      openLightbox(
        viewport.getAttribute('data-current-src'),
        viewport.getAttribute('data-current-alt')
      );
    });

    show(0);
    slider.setAttribute('data-srf-slider-ready', '1');
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
    var video   = service.video && typeof service.video === 'object' ? service.video : {};

    // Variant groups (optional)
    var variantsRaw  = service.variants || service.variations || [];
    var variants     = Array.isArray(variantsRaw) ? variantsRaw : [];
    var variantsHtml = '';

    if (variants.length) {
      var items = [];
      for (var i = 0; i < variants.length; i++) {
        var g = variants[i] || {};

        // New format: { key: 'Height', values: ['2m','3m'] }
        if (g.key && Array.isArray(g.values) && g.values.length) {
          var vals = [];
          for (var j = 0; j < g.values.length; j++) {
            if (g.values[j] == null) continue;
            vals.push(String(g.values[j]));
          }
          if (vals.length) {
            items.push(
              '<li><strong>' +
                escapeHtml(String(g.key)) +
              ':</strong> ' +
                escapeHtml(vals.join(', ')) +
              '</li>'
            );
          }
          continue;
        }

        // Legacy format: { label: 'Upper jaw', value: 'upper_jaw' }
        if (g.label) {
          items.push('<li>' + escapeHtml(String(g.label)) + '</li>');
        }
      }

      if (items.length) {
        variantsHtml =
          '<div class="srf-service-info__variants">' +
            '<h3 class="srf-service-info__subtitle">Variants</h3>' +
            '<ul class="srf-service-info__variants-list">' + items.join('') + '</ul>' +
          '</div>';
      }
    }

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

    var videoHtml = '';
    if (video.embed) {
      videoHtml =
        '<div class="srf-service-video">' +
          '<div class="srf-service-video__frame">' + String(video.embed) + '</div>' +
          (video.title ? '<h3 class="srf-service-video__title">' + escapeHtml(String(video.title)) + '</h3>' : '') +
          (video.description ? '<p class="srf-service-video__desc">' + escapeHtml(String(video.description)) + '</p>' : '') +
        '</div>';
    }

    return (
      '<div class="srf-service-info" data-service-id="' + escapeAttr(service.id) + '">' +
        videoHtml +
        sliderHtml +
        '<h2 class="srf-service-info__title">' + escapeHtml(title) + '</h2>' +
        '<div class="srf-service-info__text is-collapsed" data-srf-collapsible="text">' + content + '</div>' +
        '<button type="button" class="srf-service-info__toggle" data-srf-toggle="text">Show more</button>' +
        variantsHtml +
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

    // Init
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


/* =========================================================
   Custom service dropdown with images
========================================================= */
(function () {
  'use strict';

  function setTriggerMedia(container, thumb, title) {
    var media = container.querySelector('[data-srf-service-trigger-media]');
    if (!media) return;
    if (thumb) {
      media.innerHTML = '<img src="' + encodeURI(thumb) + '" alt="" loading="lazy">';
    } else {
      media.innerHTML = '<span class="srf-service-dropdown__trigger-placeholder"></span>';
    }
  }

  function closeDropdown(container) {
    var trigger = container.querySelector('[data-srf-service-trigger]');
    var menu = container.querySelector('[data-srf-service-menu]');
    if (!trigger || !menu) return;
    trigger.setAttribute('aria-expanded', 'false');
    menu.hidden = true;
    container.classList.remove('is-open');
  }

  function openDropdown(container) {
    var trigger = container.querySelector('[data-srf-service-trigger]');
    var menu = container.querySelector('[data-srf-service-menu]');
    if (!trigger || !menu) return;
    trigger.setAttribute('aria-expanded', 'true');
    menu.hidden = false;
    container.classList.add('is-open');
  }

  function syncDropdownFromSelect(container, select) {
    var value = String(select.value || '');
    var textNode = container.querySelector('[data-srf-service-trigger-text]');
    var selected = select.options[select.selectedIndex];
    var activeTitle = selected ? selected.text : 'Please choose a service';
    var activeThumb = '';
    var options = container.querySelectorAll('[data-srf-service-option]');

    for (var i = 0; i < options.length; i++) {
      var option = options[i];
      var isActive = option.getAttribute('data-service-id') === value;
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-selected', isActive ? 'true' : 'false');
      if (isActive) {
        activeTitle = option.getAttribute('data-service-title') || activeTitle;
        activeThumb = option.getAttribute('data-service-thumb') || '';
      }
    }

    if (textNode) textNode.textContent = activeTitle;
    setTriggerMedia(container, activeThumb, activeTitle);
  }

  function initServiceDropdown(container) {
    var select = document.getElementById('srf-service');
    var trigger = container.querySelector('[data-srf-service-trigger]');
    var options = container.querySelectorAll('[data-srf-service-option]');

    if (!select || !trigger || !options.length) return;

    syncDropdownFromSelect(container, select);

    trigger.addEventListener('click', function () {
      if (container.classList.contains('is-open')) {
        closeDropdown(container);
      } else {
        openDropdown(container);
      }
    });

    for (var i = 0; i < options.length; i++) {
      options[i].addEventListener('click', function () {
        var value = this.getAttribute('data-service-id') || '';
        select.value = value;
        syncDropdownFromSelect(container, select);
        closeDropdown(container);
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }

    select.addEventListener('change', function () {
      syncDropdownFromSelect(container, select);
    });

    document.addEventListener('click', function (event) {
      if (!container.contains(event.target)) {
        closeDropdown(container);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeDropdown(container);
      }
    });
  }

  SRF_onReady(function () {
    var dropdowns = document.querySelectorAll('[data-srf-service-dropdown]');
    for (var i = 0; i < dropdowns.length; i++) {
      initServiceDropdown(dropdowns[i]);
    }
  });

})();

(function () {
  'use strict';

  function projectFormInit() {
    var form = document.querySelector('[data-srf-project-form]');
    if (!form) return;

    var step1Panel = form.querySelector('[data-srf-step-panel="1"]');
    var step2Panel = form.querySelector('[data-srf-step-panel="2"]');
    var step1Dot   = form.querySelector('.srf-project-step[data-step="1"]');
    var step2Dot   = form.querySelector('.srf-project-step[data-step="2"]');
    var step3Dot   = form.querySelector('.srf-project-step[data-step="3"]');

    var nextBtn = form.querySelector('[data-srf-next-step="1"]');
    var prevBtn = form.querySelector('[data-srf-prev-step="2"]');

    var titleInput = form.querySelector('#srf-project-title');

    function isLoggedIn() {
      return document.body.classList.contains('logged-in');
    }

    function activateStep(step) {
      if (step1Panel) step1Panel.classList.remove('is-active');
      if (step2Panel) step2Panel.classList.remove('is-active');
      if (step1Dot) step1Dot.classList.remove('is-active', 'is-done');
      if (step2Dot) step2Dot.classList.remove('is-active', 'is-done');
      if (step3Dot) step3Dot.classList.remove('is-active', 'is-done');

      if (step === 1) {
        if (step1Panel) step1Panel.classList.add('is-active');
        if (step1Dot) step1Dot.classList.add('is-active');
      }

      if (step === 2) {
        if (step1Dot) step1Dot.classList.add('is-done');
        if (step2Panel) step2Panel.classList.add('is-active');
        if (step2Dot) step2Dot.classList.add('is-active');
      }
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        var title = titleInput ? titleInput.value.trim() : '';

        if (!title) {
          alert('Please enter the project title first.');
          if (titleInput) titleInput.focus();
          return;
        }

        if (!isLoggedIn()) {
          alert('Please log in or register first to continue to the upload step.');
          return;
        }

        activateStep(2);
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        activateStep(1);
      });
    }
  }

  function projectSuccessInit() {
    var successBox = document.querySelector('[data-srf-project-success]');
    if (!successBox) return;

    var dashboardUrl = successBox.getAttribute('data-dashboard-url') || '';
    var step3 = document.querySelector('.srf-project-step[data-step="3"]');
    if (step3) {
      step3.classList.add('is-active');
    }

    setTimeout(function () {
      if (dashboardUrl) {
        window.location.href = dashboardUrl;
      }
    }, 3000);
  }

  SRF_onReady(function () {
    projectFormInit();
    projectSuccessInit();
  });
})();
