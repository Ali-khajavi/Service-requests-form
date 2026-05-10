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

  var DEG = Math.PI / 180;

  function SRFProjectViewer(root, input) {
    this.root = root;
    this.input = input;
    this.canvas = root ? root.querySelector('.srf-3d-viewer__canvas') : null;
    this.placeholder = root ? root.querySelector('[data-srf-3d-placeholder]') : null;
    this.status = root ? root.querySelector('[data-srf-3d-status]') : null;
    this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
    this.pixelRatio = Math.max(1, window.devicePixelRatio || 1);
    this.mesh = null;
    this.objectUrl = null;
    this.resizeObserver = null;

    this.stats = {
      filename: root ? root.querySelector('[data-field="filename"]') : null,
      format: root ? root.querySelector('[data-field="format"]') : null,
      triangles: root ? root.querySelector('[data-field="triangles"]') : null,
      bounds: root ? root.querySelector('[data-field="bounds"]') : null
    };

    this.state = {
      yaw: -25 * DEG,
      pitch: -18 * DEG,
      zoom: 1.85,
      panX: 0,
      panY: 0,
      isDragging: false,
      dragMode: 'rotate',
      lastX: 0,
      lastY: 0
    };

    if (!this.root || !this.canvas || !this.ctx || !this.input) {
      return;
    }

    this.bindEvents();
    this.resize(true);
    this.drawEmpty();
    this.updatePlaceholder('No model loaded yet.', true);
    this.updateStatus('Viewer ready. Upload an STL or OBJ file to preview it.', 'info');
    this.updateStats({});
  }

  SRFProjectViewer.prototype.bindEvents = function () {
    var self = this;

    this.handleResize = function () {
      if (self.resize()) {
        self.render();
      }
    };

    window.addEventListener('resize', this.handleResize);

    var resizeTarget = this.canvas.parentElement || this.root;
    if ('ResizeObserver' in window && resizeTarget) {
      this.resizeObserver = new ResizeObserver(function () {
        if (self.resize()) {
          self.render();
        }
      });
      this.resizeObserver.observe(resizeTarget);
    }

    this.input.addEventListener('change', function () {
      self.handleFileSelection();
    });

    this.canvas.addEventListener('pointerdown', function (event) {
      self.state.isDragging = true;
      self.state.dragMode = event.shiftKey ? 'pan' : 'rotate';
      self.state.lastX = event.clientX;
      self.state.lastY = event.clientY;
      if (self.canvas.setPointerCapture) {
        self.canvas.setPointerCapture(event.pointerId);
      }
    });

    this.canvas.addEventListener('pointermove', function (event) {
      if (!self.state.isDragging) return;

      var deltaX = event.clientX - self.state.lastX;
      var deltaY = event.clientY - self.state.lastY;

      self.state.lastX = event.clientX;
      self.state.lastY = event.clientY;

      if (self.state.dragMode === 'pan') {
        self.state.panX += deltaX * 0.004;
        self.state.panY -= deltaY * 0.004;
      } else {
        self.state.yaw += deltaX * 0.012;
        self.state.pitch += deltaY * 0.012;
        self.state.pitch = Math.max(-Math.PI / 2.2, Math.min(Math.PI / 2.2, self.state.pitch));
      }

      self.render();
    });

    function releasePointer(event) {
      self.state.isDragging = false;
      if (
        event &&
        self.canvas.releasePointerCapture &&
        self.canvas.hasPointerCapture &&
        self.canvas.hasPointerCapture(event.pointerId)
      ) {
        self.canvas.releasePointerCapture(event.pointerId);
      }
    }

    this.canvas.addEventListener('pointerup', releasePointer);
    this.canvas.addEventListener('pointercancel', releasePointer);
    this.canvas.addEventListener('pointerleave', function () {
      self.state.isDragging = false;
    });

    this.canvas.addEventListener('wheel', function (event) {
      event.preventDefault();
      self.applyZoom(event.deltaY > 0 ? -0.12 : 0.12);
    }, { passive: false });

    this.root.querySelectorAll('[data-action]').forEach(function (button) {
      button.addEventListener('click', function () {
        var action = button.getAttribute('data-action');
        if (action === 'reset-view') {
          self.resetCamera();
        } else if (action === 'fit-view') {
          self.fitView();
        } else if (action === 'zoom-in') {
          self.applyZoom(-0.16);
        } else if (action === 'zoom-out') {
          self.applyZoom(0.16);
        } else if (action && action.indexOf('view-') === 0) {
          self.setPresetView(action.replace('view-', ''));
        }
      });
    });
  };

  SRFProjectViewer.prototype.handleFileSelection = function () {
    var file = this.findPreviewableFile();

    if (!file) {
      this.clearModel();
      this.updateStatus('Preview is available for STL and OBJ files. Other files can still be uploaded.', 'warning');
      return;
    }

    this.loadLocalFile(file);
  };

  SRFProjectViewer.prototype.findPreviewableFile = function () {
    if (!this.input || !this.input.files || !this.input.files.length) {
      return null;
    }

    for (var i = 0; i < this.input.files.length; i++) {
      var file = this.input.files[i];
      var ext = this.detectExtension(file.name || '');
      if (ext === 'stl' || ext === 'obj') {
        return file;
      }
    }

    return null;
  };

  SRFProjectViewer.prototype.clearModel = function () {
    if (this.objectUrl) {
      URL.revokeObjectURL(this.objectUrl);
      this.objectUrl = null;
    }

    this.mesh = null;
    this.drawEmpty();
    this.updatePlaceholder('No model loaded yet.', true);
    this.updateStats({});
  };

  SRFProjectViewer.prototype.resize = function (force) {
    if (!this.canvas || !this.ctx) {
      return false;
    }

    var host = this.canvas.parentElement || this.root;
    var hostRect = host ? host.getBoundingClientRect() : null;
    var canvasRect = this.canvas.getBoundingClientRect();

    var width = Math.max(
      320,
      Math.round(
        (hostRect && hostRect.width) ||
        canvasRect.width ||
        this.canvas.clientWidth ||
        640
      )
    );

    var height = Math.max(
      280,
      Math.round(
        canvasRect.height ||
        this.canvas.clientHeight ||
        (hostRect && hostRect.height) ||
        420
      )
    );

    var pixelWidth = Math.round(width * this.pixelRatio);
    var pixelHeight = Math.round(height * this.pixelRatio);

    if (!force && this.canvas.width === pixelWidth && this.canvas.height === pixelHeight) {
      return false;
    }

    this.canvas.width = pixelWidth;
    this.canvas.height = pixelHeight;
    this.canvas.style.width = width + 'px';
    this.canvas.style.height = height + 'px';
    this.ctx.setTransform(this.pixelRatio, 0, 0, this.pixelRatio, 0, 0);

    return true;
  };

  SRFProjectViewer.prototype.resetCamera = function () {
    this.state.yaw = -25 * DEG;
    this.state.pitch = -18 * DEG;
    this.state.zoom = 1.85;
    this.state.panX = 0;
    this.state.panY = 0;
    this.render();
  };

  SRFProjectViewer.prototype.fitView = function () {
    this.state.panX = 0;
    this.state.panY = 0;
    this.state.zoom = this.mesh && this.mesh.radius > 0 ? 1.95 : 1.85;
    this.render();
  };

  SRFProjectViewer.prototype.applyZoom = function (delta) {
    var factor = delta > 0 ? 1.1 : 0.9;
    var intensity = 1 + Math.abs(delta);

    this.state.zoom = this.state.zoom * Math.pow(factor, intensity);

    if (this.state.zoom < 0.05) this.state.zoom = 0.05;
    if (this.state.zoom > 100) this.state.zoom = 100;

    this.render();
  };

  SRFProjectViewer.prototype.setPresetView = function (view) {
    this.state.panX = 0;
    this.state.panY = 0;

    if (view === 'front') {
      this.state.yaw = 0;
      this.state.pitch = 0;
    } else if (view === 'left') {
      this.state.yaw = -90 * DEG;
      this.state.pitch = 0;
    } else if (view === 'right') {
      this.state.yaw = 90 * DEG;
      this.state.pitch = 0;
    } else if (view === 'top') {
      this.state.yaw = 0;
      this.state.pitch = 90 * DEG;
    } else if (view === 'iso') {
      this.state.yaw = -25 * DEG;
      this.state.pitch = -18 * DEG;
    }

    this.fitView();
  };

  SRFProjectViewer.prototype.updatePlaceholder = function (message, isVisible) {
    if (!this.placeholder) return;
    this.placeholder.textContent = message || '';
    this.placeholder.hidden = !isVisible;
  };

  SRFProjectViewer.prototype.updateStatus = function (message, type) {
    if (!this.status) return;
    this.status.textContent = message || '';
    this.status.setAttribute('data-state', type || 'info');
  };

  SRFProjectViewer.prototype.updateStats = function (data) {
    if (!this.stats.filename) return;
    this.stats.filename.textContent = data.filename || '—';
    this.stats.format.textContent = data.format || '—';
    this.stats.triangles.textContent = data.triangles ? this.formatNumber(data.triangles) : '—';
    this.stats.bounds.textContent = data.bounds || '—';
  };

  SRFProjectViewer.prototype.formatNumber = function (value) {
    return new Intl.NumberFormat().format(value);
  };

  SRFProjectViewer.prototype.detectExtension = function (name) {
    var raw = String(name || '');
    var clean = raw.split('?')[0].split('#')[0];
    var parts = clean.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
  };

  SRFProjectViewer.prototype.loadLocalFile = function (file) {
    var self = this;
    var extension = this.detectExtension(file.name || '');

    if (this.objectUrl) {
      URL.revokeObjectURL(this.objectUrl);
      this.objectUrl = null;
    }

    this.updatePlaceholder('', false);
    this.updateStatus('Loading 3D preview…', 'loading');

    if (extension !== 'stl' && extension !== 'obj') {
      this.mesh = null;
      this.drawEmpty();
      this.updateStats({
        filename: file.name || '—',
        format: extension ? extension.toUpperCase() : '—',
        triangles: 0,
        bounds: '—'
      });
      this.updateStatus('Preview is currently available for STL and OBJ files.', 'warning');
      return;
    }

    var reader = new FileReader();

    reader.onload = function (event) {
      try {
        var mesh;

        if (extension === 'stl') {
          mesh = parseSTL(event.target.result);
          mesh.format = 'STL';
        } else {
          mesh = parseOBJ(event.target.result);
          mesh.format = 'OBJ';
        }

        mesh.filename = file.name || 'Model';
        self.mesh = mesh;
        self.resetCamera();
        self.fitView();
        self.updateStats({
          filename: mesh.filename,
          format: mesh.format,
          triangles: mesh.triangleCount,
          bounds: formatBounds(mesh.bounds)
        });
        self.updateStatus('3D preview ready. Drag to rotate, use Shift+drag to pan, and use the wheel or zoom buttons.', 'success');
        self.render();
      } catch (error) {
        self.mesh = null;
        self.drawEmpty();
        self.updateStats({
          filename: file.name || '—',
          format: extension.toUpperCase(),
          triangles: 0,
          bounds: '—'
        });
        self.updateStatus('The viewer could not load this model.', 'error');
        self.updatePlaceholder((error && error.message) ? error.message : 'Preview failed.', true);
      }
    };

    reader.onerror = function () {
      self.mesh = null;
      self.drawEmpty();
      self.updateStats({
        filename: file.name || '—',
        format: extension.toUpperCase(),
        triangles: 0,
        bounds: '—'
      });
      self.updateStatus('The viewer could not load this model.', 'error');
      self.updatePlaceholder('Preview failed.', true);
    };

    if (extension === 'stl') {
      reader.readAsArrayBuffer(file);
    } else {
      reader.readAsText(file);
    }
  };

  SRFProjectViewer.prototype.drawEmpty = function () {
    var width = this.canvas.clientWidth || 640;
    var height = this.canvas.clientHeight || 420;
    this.ctx.clearRect(0, 0, width, height);
    this.drawBackground(width, height);
    this.drawGrid(width, height);
    this.drawAxes(width, height);
  };

  SRFProjectViewer.prototype.render = function () {
    var width = this.canvas.clientWidth || 640;
    var height = this.canvas.clientHeight || 420;

    this.ctx.clearRect(0, 0, width, height);
    this.drawBackground(width, height);
    this.drawGrid(width, height);
    this.drawAxes(width, height);

    if (!this.mesh || !this.mesh.triangles.length) {
      return;
    }

    var projected = [];
    var cosY = Math.cos(this.state.yaw);
    var sinY = Math.sin(this.state.yaw);
    var cosX = Math.cos(this.state.pitch);
    var sinX = Math.sin(this.state.pitch);
    var cx = width / 2 + this.state.panX * width * 0.6;
    var cy = height / 2 - this.state.panY * height * 0.6;
    var baseScale = Math.min(width, height) * 0.31 * this.state.zoom;
    var distance = 4.2;
    var light = normalizeVector({ x: -0.35, y: -0.45, z: 1 });

    for (var i = 0; i < this.mesh.triangles.length; i++) {
      var tri = this.mesh.triangles[i];
      var vertices = tri.vertices.map(function (vertex) {
        var centered = {
          x: (vertex.x - this.mesh.center.x) / this.mesh.radius,
          y: (vertex.y - this.mesh.center.y) / this.mesh.radius,
          z: (vertex.z - this.mesh.center.z) / this.mesh.radius
        };

        var yawed = {
          x: centered.x * cosY + centered.z * sinY,
          y: centered.y,
          z: -centered.x * sinY + centered.z * cosY
        };

        var pitched = {
          x: yawed.x,
          y: yawed.y * cosX - yawed.z * sinX,
          z: yawed.y * sinX + yawed.z * cosX
        };

        var perspective = baseScale / (distance - pitched.z);
        return {
          x: cx + pitched.x * perspective,
          y: cy - pitched.y * perspective,
          z: pitched.z
        };
      }, this);

      var v1 = subtract(vertices[1], vertices[0]);
      var v2 = subtract(vertices[2], vertices[0]);
      var normal = normalizeVector(cross(v1, v2));

      if (normal.z <= 0) {
        continue;
      }

      var shade = Math.max(0.18, dot(normal, light));
      var depth = (vertices[0].z + vertices[1].z + vertices[2].z) / 3;

      projected.push({
        vertices: vertices,
        shade: shade,
        depth: depth
      });
    }

    projected.sort(function (a, b) {
      return a.depth - b.depth;
    });

    for (var j = 0; j < projected.length; j++) {
      var p = projected[j];
      var fill = Math.round(80 + p.shade * 135);
      var edge = Math.round(55 + p.shade * 110);

      this.ctx.beginPath();
      this.ctx.moveTo(p.vertices[0].x, p.vertices[0].y);
      this.ctx.lineTo(p.vertices[1].x, p.vertices[1].y);
      this.ctx.lineTo(p.vertices[2].x, p.vertices[2].y);
      this.ctx.closePath();

      this.ctx.fillStyle = 'rgb(' + fill + ',' + (fill + 12) + ',' + Math.min(255, fill + 30) + ')';
      this.ctx.strokeStyle = 'rgba(' + edge + ',' + edge + ',' + Math.min(255, edge + 20) + ',0.38)';
      this.ctx.lineWidth = 0.9;
      this.ctx.fill();
      this.ctx.stroke();
    }
  };

  SRFProjectViewer.prototype.drawBackground = function (width, height) {
    var gradient = this.ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, '#101b34');
    gradient.addColorStop(1, '#050915');
    this.ctx.fillStyle = gradient;
    this.ctx.fillRect(0, 0, width, height);
  };

  SRFProjectViewer.prototype.drawGrid = function (width, height) {
    this.ctx.save();
    this.ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    this.ctx.lineWidth = 1;
    var step = Math.max(26, Math.round(width / 16));

    for (var x = 0; x <= width; x += step) {
      this.ctx.beginPath();
      this.ctx.moveTo(x, 0);
      this.ctx.lineTo(x, height);
      this.ctx.stroke();
    }

    for (var y = 0; y <= height; y += step) {
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      this.ctx.lineTo(width, y);
      this.ctx.stroke();
    }

    this.ctx.restore();
  };

  SRFProjectViewer.prototype.drawAxes = function (width, height) {
    var originX = 56;
    var originY = height - 46;

    this.ctx.save();
    this.ctx.lineWidth = 2;

    this.ctx.strokeStyle = 'rgba(255,96,96,0.85)';
    this.ctx.beginPath();
    this.ctx.moveTo(originX, originY);
    this.ctx.lineTo(originX + 28, originY);
    this.ctx.stroke();

    this.ctx.strokeStyle = 'rgba(96,255,160,0.85)';
    this.ctx.beginPath();
    this.ctx.moveTo(originX, originY);
    this.ctx.lineTo(originX, originY - 28);
    this.ctx.stroke();

    this.ctx.strokeStyle = 'rgba(120,180,255,0.85)';
    this.ctx.beginPath();
    this.ctx.moveTo(originX, originY);
    this.ctx.lineTo(originX - 18, originY + 18);
    this.ctx.stroke();

    this.ctx.restore();
  };

  function subtract(a, b) {
    return { x: a.x - b.x, y: a.y - b.y, z: a.z - b.z };
  }

  function cross(a, b) {
    return {
      x: a.y * b.z - a.z * b.y,
      y: a.z * b.x - a.x * b.z,
      z: a.x * b.y - a.y * b.x
    };
  }

  function dot(a, b) {
    return a.x * b.x + a.y * b.y + a.z * b.z;
  }

  function normalizeVector(vector) {
    var length = Math.sqrt(
      vector.x * vector.x +
      vector.y * vector.y +
      vector.z * vector.z
    ) || 1;

    return {
      x: vector.x / length,
      y: vector.y / length,
      z: vector.z / length
    };
  }

  function formatBounds(bounds) {
    if (!bounds) {
      return '—';
    }

    return [bounds.x, bounds.y, bounds.z].map(function (value) {
      return value.toFixed(2);
    }).join(' × ');
  }

  function parseSTL(buffer) {
    if (looksLikeBinarySTL(buffer)) {
      return parseBinarySTL(buffer);
    }

    return parseASCIISTL(new TextDecoder().decode(buffer));
  }

  function looksLikeBinarySTL(buffer) {
    if (buffer.byteLength < 84) {
      return false;
    }

    var view = new DataView(buffer);
    var faces = view.getUint32(80, true);
    var expectedLength = 84 + (faces * 50);

    if (expectedLength === buffer.byteLength) {
      return true;
    }

    var header = new TextDecoder().decode(buffer.slice(0, 80)).trim().toLowerCase();
    return header.indexOf('solid') !== 0;
  }

  function parseBinarySTL(buffer) {
    var view = new DataView(buffer);
    var faces = view.getUint32(80, true);
    var offset = 84;
    var triangles = [];
    var bounds = createBounds();

    for (var i = 0; i < faces; i++) {
      offset += 12;
      var vertices = [];

      for (var j = 0; j < 3; j++) {
        var vertex = {
          x: view.getFloat32(offset, true),
          y: view.getFloat32(offset + 4, true),
          z: view.getFloat32(offset + 8, true)
        };
        vertices.push(vertex);
        expandBounds(bounds, vertex);
        offset += 12;
      }

      triangles.push({ vertices: vertices });
      offset += 2;
    }

    return buildMesh(triangles, bounds);
  }

  function parseASCIISTL(text) {
    var pattern = /vertex\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)/ig;
    var vertices = [];
    var match;

    while ((match = pattern.exec(text)) !== null) {
      vertices.push({
        x: parseFloat(match[1]),
        y: parseFloat(match[2]),
        z: parseFloat(match[3])
      });
    }

    if (!vertices.length || vertices.length % 3 !== 0) {
      throw new Error('This STL file could not be parsed.');
    }

    var bounds = createBounds();
    var triangles = [];

    for (var i = 0; i < vertices.length; i += 3) {
      var triVertices = [vertices[i], vertices[i + 1], vertices[i + 2]];
      for (var j = 0; j < triVertices.length; j++) {
        expandBounds(bounds, triVertices[j]);
      }
      triangles.push({ vertices: triVertices });
    }

    return buildMesh(triangles, bounds);
  }

  function parseOBJ(text) {
    var lines = text.split(/\r?\n/);
    var sourceVertices = [];
    var bounds = createBounds();
    var triangles = [];

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i].trim();
      if (!line || line.charAt(0) === '#') {
        continue;
      }

      var parts = line.split(/\s+/);

      if (parts[0] === 'v' && parts.length >= 4) {
        var vertex = {
          x: parseFloat(parts[1]),
          y: parseFloat(parts[2]),
          z: parseFloat(parts[3])
        };

        if ([vertex.x, vertex.y, vertex.z].some(function (value) { return Number.isNaN(value); })) {
          continue;
        }

        sourceVertices.push(vertex);
        expandBounds(bounds, vertex);
      } else if (parts[0] === 'f' && parts.length >= 4) {
        var faceIndexes = parts
          .slice(1)
          .map(function (token) {
            return parseOBJVertexIndex(token, sourceVertices.length);
          })
          .filter(function (index) {
            return index >= 0;
          });

        if (faceIndexes.length < 3) {
          continue;
        }

        for (var j = 1; j < faceIndexes.length - 1; j++) {
          var a = sourceVertices[faceIndexes[0]];
          var b = sourceVertices[faceIndexes[j]];
          var c = sourceVertices[faceIndexes[j + 1]];

          if (!a || !b || !c) {
            continue;
          }

          triangles.push({
            vertices: [
              { x: a.x, y: a.y, z: a.z },
              { x: b.x, y: b.y, z: b.z },
              { x: c.x, y: c.y, z: c.z }
            ]
          });
        }
      }
    }

    if (!sourceVertices.length || !triangles.length) {
      throw new Error('This OBJ file could not be parsed.');
    }

    return buildMesh(triangles, bounds);
  }

  function parseOBJVertexIndex(token, total) {
    var raw = String(token).split('/')[0];
    var index = parseInt(raw, 10);

    if (Number.isNaN(index) || !index) {
      return -1;
    }

    return index > 0 ? index - 1 : total + index;
  }

  function createBounds() {
    return {
      minX: Infinity,
      minY: Infinity,
      minZ: Infinity,
      maxX: -Infinity,
      maxY: -Infinity,
      maxZ: -Infinity
    };
  }

  function expandBounds(bounds, vertex) {
    bounds.minX = Math.min(bounds.minX, vertex.x);
    bounds.minY = Math.min(bounds.minY, vertex.y);
    bounds.minZ = Math.min(bounds.minZ, vertex.z);
    bounds.maxX = Math.max(bounds.maxX, vertex.x);
    bounds.maxY = Math.max(bounds.maxY, vertex.y);
    bounds.maxZ = Math.max(bounds.maxZ, vertex.z);
  }

  function signedTriangleVolume(a, b, c) {
    return (
      (a.x * b.y * c.z +
       b.x * c.y * a.z +
       c.x * a.y * b.z -
       a.x * c.y * b.z -
       b.x * a.y * c.z -
       c.x * b.y * a.z) / 6
    );
  }

  function calculateMeshVolumeMm3(triangles) {
    if (!triangles || !triangles.length) return 0;

    var total = 0;

    for (var i = 0; i < triangles.length; i++) {
      var tri = triangles[i];
      if (!tri || !tri.vertices || tri.vertices.length !== 3) continue;

      total += signedTriangleVolume(
        tri.vertices[0],
        tri.vertices[1],
        tri.vertices[2]
      );
    }

    return Math.abs(total);
  }

  function buildMesh(triangles, rawBounds) {
    var bounds = {
      x: rawBounds.maxX - rawBounds.minX,
      y: rawBounds.maxY - rawBounds.minY,
      z: rawBounds.maxZ - rawBounds.minZ
    };

    var center = {
      x: (rawBounds.minX + rawBounds.maxX) / 2,
      y: (rawBounds.minY + rawBounds.maxY) / 2,
      z: (rawBounds.minZ + rawBounds.maxZ) / 2
    };

    var radius = Math.max(bounds.x, bounds.y, bounds.z) || 1;
    var volumeMm3 = calculateMeshVolumeMm3(triangles);
    var volumeCm3 = volumeMm3 > 0 ? (volumeMm3 / 1000) : 0;

    return {
      triangles: triangles,
      triangleCount: triangles.length,
      center: center,
      radius: radius,
      bounds: bounds,
      volumeMm3: volumeMm3,
      volumeCm3: volumeCm3
    };
  }

  function initProjectViewer(form) {
    if (!form) return null;

    var viewerRoot = form.querySelector('[data-srf-3d-viewer]');
    var fileInput = form.querySelector('#srf-files');

    if (!viewerRoot || !fileInput) {
      return null;
    }

    return new SRFProjectViewer(viewerRoot, fileInput);
  }

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
    var descriptionInput = form.querySelector('#srf-project-description');
    var step2Actions = form.querySelector('.srf-form__actions--project');

    form._srfProjectViewer = initProjectViewer(form);

    function isLoggedIn() {
      return document.body.classList.contains('logged-in');
    }

    function activateStep(step) {
      if (step1Panel) step1Panel.classList.remove('is-active');
      if (step2Panel) step2Panel.classList.remove('is-active');
      if (step1Dot) step1Dot.classList.remove('is-active', 'is-done');
      if (step2Dot) step2Dot.classList.remove('is-active', 'is-done');
      if (step3Dot) step3Dot.classList.remove('is-active', 'is-done');
      if (step2Actions) step2Actions.style.display = 'none';

      if (step === 1) {
        if (step1Panel) step1Panel.classList.add('is-active');
        if (step1Dot) step1Dot.classList.add('is-active');
      }

      if (step === 2) {
        if (step1Dot) step1Dot.classList.add('is-done');
        if (step2Panel) step2Panel.classList.add('is-active');
        if (step2Dot) step2Dot.classList.add('is-active');
        if (step2Actions) step2Actions.style.display = '';
      }
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        var title = titleInput ? titleInput.value.trim() : '';
        var description = descriptionInput ? descriptionInput.value.trim() : '';

        if (!title) {
          alert('Please enter the project title first.');
          if (titleInput) titleInput.focus();
          return;
        }

        if (!description) {
          alert('Please enter the project description first.');
          if (descriptionInput) descriptionInput.focus();
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
      prevBtn.addEventListener('click', function (e) {
        activateStep(1);
      });
    }

    if (step2Actions) {
      step2Actions.style.display = 'none';
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


function init3DQuotePage() {
  var form = document.querySelector('.tpq-shortcode-form');
  if (!form) return;

  var state = {
    model: null
  };

  function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    return (bytes / 1024).toFixed(1) + ' KB';
  }

  function collectInputs() {
    var data = {};
    var formData = new FormData(form);

    formData.forEach(function(value, key) {
      data[key] = value;
    });

    if (state.model) {
      data.model_url = state.model.url || '';
      data.model_name = state.model.name || '';
      data.model_size = state.model.size || 0;
    }

    return data;
  }

  function refreshQuote() {
    var breakdown = document.querySelector('.tpq-quote-breakdown');
    if (!breakdown) return;

    var data = collectInputs();

    if (window.tpqCalculator && typeof window.tpqCalculator.calculate === 'function') {
      var result = window.tpqCalculator.calculate(data);

      var total = breakdown.querySelector('[data-quote="total"]');
      if (total) {
        total.textContent = result.total_formatted || '€0.00';
      }
    }
  }

  function debounce(fn, delay) {
    var timeout;
    return function() {
      var args = arguments;
      var ctx = this;
      clearTimeout(timeout);
      timeout = setTimeout(function() {
        fn.apply(ctx, args);
      }, delay);
    };
  }

  var debouncedRefresh = debounce(refreshQuote, 200);

  form.addEventListener('input', debouncedRefresh);
  form.addEventListener('change', debouncedRefresh);

  form.addEventListener('submit', function(e) {
    if (!state.model || !state.model.url) {
      e.preventDefault();
      alert('Please upload a 3D model first.');
      return;
    }
  });

  document.addEventListener('tpqModelUploaded', function(event) {
    state.model = event.detail || null;

    var status = document.querySelector('.tpq-upload-status');
    if (status && state.model) {
      status.textContent = (state.model.name || 'Model uploaded') +
        (state.model.size ? ' (' + formatSize(state.model.size) + ')' : '');
    }

    refreshQuote();
  });

  refreshQuote();
}

/* =========================================================
   Project form: material/printer filtering + summary
========================================================= */
(function () {
  'use strict';

  function parseSupportedMaterials(option) {
    if (!option) return [];
    var raw = option.getAttribute('data-supported-materials') || '[]';

    try {
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.map(function (id) {
        return parseInt(id, 10);
      }).filter(function (id) {
        return !Number.isNaN(id) && id > 0;
      }) : [];
    } catch (e) {
      return [];
    }
  }

  function getSelectedOption(select) {
    if (!select) return null;
    return select.options[select.selectedIndex] || null;
  }

  function updateProjectSummary(form) {
    if (!form) return;

    var materialSelect = form.querySelector('#srf-material-id');
    var printerSelect  = form.querySelector('#srf-printer-id');
    var layerInput     = form.querySelector('#srf-layer-height');
    var quantityInput  = form.querySelector('#srf-quantity');

    var materialOut = form.querySelector('[data-srf-summary-material]');
    var printerOut  = form.querySelector('[data-srf-summary-printer]');
    var layerOut    = form.querySelector('[data-srf-summary-layer]');
    var quantityOut = form.querySelector('[data-srf-summary-quantity]');

    var materialOpt = getSelectedOption(materialSelect);
    var printerOpt  = getSelectedOption(printerSelect);

    if (materialOut) {
      materialOut.textContent = materialOpt && materialOpt.value ? materialOpt.textContent.trim() : '—';
    }

    if (printerOut) {
      printerOut.textContent = printerOpt && printerOpt.value ? printerOpt.textContent.trim() : '—';
    }

    if (layerOut) {
      layerOut.textContent = layerInput && layerInput.value ? (layerInput.value + ' mm') : '—';
    }

    if (quantityOut) {
      quantityOut.textContent = quantityInput && quantityInput.value ? quantityInput.value : '—';
    }
  }

  function filterPrintersByMaterial(form) {
    if (!form) return;

    var materialSelect = form.querySelector('#srf-material-id');
    var printerSelect  = form.querySelector('#srf-printer-id');

    if (!materialSelect || !printerSelect) return;

    var selectedMaterial = parseInt(materialSelect.value || '0', 10);
    var hasSelectedPrinterStillValid = false;

    Array.prototype.forEach.call(printerSelect.options, function (option, index) {
      if (index === 0) {
        option.hidden = false;
        option.disabled = false;
        return;
      }

      var supported = parseSupportedMaterials(option);
      var isAllowed = !selectedMaterial || !supported.length || supported.indexOf(selectedMaterial) !== -1;

      option.hidden = !isAllowed;
      option.disabled = !isAllowed;

      if (isAllowed && option.value === printerSelect.value) {
        hasSelectedPrinterStillValid = true;
      }
    });

    if (printerSelect.value && !hasSelectedPrinterStillValid) {
      printerSelect.value = '';
    }

    updateProjectSummary(form);
  }

  
  function parseIdListOption(option, attr) {
    if (!option) return [];
    var raw = option.getAttribute(attr) || '[]';
    try {
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.map(function (id) {
        return parseInt(id, 10);
      }).filter(function (id) {
        return !Number.isNaN(id) && id > 0;
      }) : [];
    } catch (e) {
      return [];
    }
  }

  function renderProfileVariations(form, serviceId) {
    var wrap = form.querySelector('#srf-profile-variations-wrap');
    var host = form.querySelector('#srf-profile-variations');
    if (!wrap || !host) return;

    host.innerHTML = '';
    wrap.style.display = 'none';

    if (!serviceId || !window.srfServiceData || !window.srfServiceData[String(serviceId)]) {
      return;
    }

    var service = window.srfServiceData[String(serviceId)] || {};
    var variants = Array.isArray(service.variants) ? service.variants : [];
    if (!variants.length) return;

    for (var i = 0; i < variants.length; i++) {
      var group = variants[i] || {};
      var key = group.key || ('Variation ' + (i + 1));
      var values = Array.isArray(group.values) ? group.values : [];
      if (!values.length) continue;

      var slug = key.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
      var field = document.createElement('div');
      field.className = 'srf-form__field';

      var label = document.createElement('label');
      label.setAttribute('for', 'srf-profile-var-' + slug);
      label.textContent = key;
      field.appendChild(label);

      var select = document.createElement('select');
      select.id = 'srf-profile-var-' + slug;
      select.name = 'srf_profile_variations[' + key + ']';

      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Select ' + key;
      select.appendChild(placeholder);

      for (var j = 0; j < values.length; j++) {
        var option = document.createElement('option');
        option.value = String(values[j]);
        option.textContent = String(values[j]);
        select.appendChild(option);
      }

      field.appendChild(select);
      host.appendChild(field);
    }

    if (host.children.length) {
      wrap.style.display = '';
    }
  }

  function updateProjectServiceProfiles(form) {
    if (!form) return;

    var printerSelect = form.querySelector('#srf-printer-id');
    var profileWrap = form.querySelector('#srf-service-profile-wrap');
    var profileSelect = form.querySelector('#srf-service-profile-id');
    if (!printerSelect || !profileWrap || !profileSelect) return;

    var printerOption = getSelectedOption(printerSelect);
    var supportedIds = parseIdListOption(printerOption, 'data-supported-service-profiles');
    var defaultId = parseInt(printerOption && printerOption.getAttribute('data-default-service-profile-id') || '0', 10) || 0;

    profileSelect.innerHTML = '';
    var baseOption = document.createElement('option');
    baseOption.value = '';
    baseOption.textContent = 'Select profile service';
    profileSelect.appendChild(baseOption);

    if (!supportedIds.length || !window.srfServiceData) {
      profileWrap.style.display = 'none';
      renderProfileVariations(form, 0);
      return;
    }

    for (var i = 0; i < supportedIds.length; i++) {
      var id = supportedIds[i];
      var service = window.srfServiceData[String(id)];
      if (!service) continue;
      var opt = document.createElement('option');
      opt.value = String(id);
      opt.textContent = service.title || ('Service #' + id);
      profileSelect.appendChild(opt);
    }

    profileWrap.style.display = '';

    if (defaultId > 0 && supportedIds.indexOf(defaultId) !== -1) {
      profileSelect.value = String(defaultId);
    } else {
      profileSelect.value = '';
    }

    renderProfileVariations(form, profileSelect.value);
  }

  function initProjectQuoteOptions() {
    var form = document.querySelector('[data-srf-project-form]');
    if (!form) return;

    var materialSelect = form.querySelector('#srf-material-id');
    var printerSelect  = form.querySelector('#srf-printer-id');
    var layerInput     = form.querySelector('#srf-layer-height');
    var quantityInput  = form.querySelector('#srf-quantity');

    if (!materialSelect || !printerSelect) return;

    materialSelect.addEventListener('change', function () {
      filterPrintersByMaterial(form);
      updateProjectServiceProfiles(form);
    });

    printerSelect.addEventListener('change', function () {
      updateProjectSummary(form);
      updateProjectServiceProfiles(form);
    });

    if (layerInput) {
      layerInput.addEventListener('input', function () {
        updateProjectSummary(form);
      });
    }

    if (quantityInput) {
      quantityInput.addEventListener('input', function () {
        updateProjectSummary(form);
      });
    }

    var profileSelect = form.querySelector('#srf-service-profile-id');
    if (profileSelect) {
      profileSelect.addEventListener('change', function () {
        renderProfileVariations(form, this.value);
      });
    }

    filterPrintersByMaterial(form);
    updateProjectSummary(form);
    updateProjectServiceProfiles(form);
  }

  SRF_onReady(function () {
    initProjectQuoteOptions();
  });
})();


/* =========================================================
   Project form: live estimate preview
========================================================= */
(function () {
  'use strict';

  function toNumber(value, fallback) {
    var n = parseFloat(value);
    return Number.isFinite(n) ? n : (fallback || 0);
  }

  function getSelectedOption(select) {
    if (!select) return null;
    return select.options[select.selectedIndex] || null;
  }

  function formatMoney(amount, symbol) {
    var n = Number.isFinite(amount) ? amount : 0;
    return (symbol || '€') + n.toFixed(2);
  }

  function formatVolume(value) {
    var n = Number.isFinite(value) ? value : 0;
    return n.toFixed(2) + ' cm3';
  }

  function formatWeight(value) {
    var n = Number.isFinite(value) ? value : 0;
    return n.toFixed(2) + ' g';
  }

  function getProjectViewer(form) {
    return form && form._srfProjectViewer ? form._srfProjectViewer : null;
  }

  function getModelVolumeFromViewer(form) {
    var viewer = getProjectViewer(form);
    if (!viewer || !viewer.mesh) return 0;

    var volumeCm3 = toNumber(viewer.mesh.volumeCm3, 0);
    return volumeCm3 > 0 ? volumeCm3 : 0;
  }

  function getPrinterLayerLimits(printerOption) {
    return {
      min: toNumber(printerOption && printerOption.getAttribute('data-min-layer-height'), 0),
      max: toNumber(printerOption && printerOption.getAttribute('data-max-layer-height'), 0)
    };
  }

  function calculateLiveEstimate(form) {
    if (!form) return null;

    var summary = form.querySelector('[data-srf-quote-summary]');
    var materialSelect = form.querySelector('#srf-material-id');
    var printerSelect  = form.querySelector('#srf-printer-id');
    var layerInput     = form.querySelector('#srf-layer-height');
    var infillInput    = form.querySelector('#srf-infill');
    var shellSelect    = form.querySelector('#srf-shell-mode');
    var scaleInput     = form.querySelector('#srf-scale');
    var quantityInput  = form.querySelector('#srf-quantity');

    if (!summary || !materialSelect || !printerSelect) return null;

    var materialOption = getSelectedOption(materialSelect);
    var printerOption  = getSelectedOption(printerSelect);

    if (!materialOption || !materialOption.value || !printerOption || !printerOption.value) {
      return null;
    }

    var currencySymbol = summary.getAttribute('data-currency-symbol') || '€';
    var taxRate        = toNumber(summary.getAttribute('data-tax-rate'), 0);
    var serviceFee     = toNumber(summary.getAttribute('data-service-fee'), 0);
    var setupFee       = toNumber(summary.getAttribute('data-setup-fee'), 0);
    var profitMargin   = toNumber(summary.getAttribute('data-profit-margin'), 0);

    var pricePerGram   = toNumber(materialOption.getAttribute('data-price-per-gram'), 0);
    var pricePerCm3    = toNumber(materialOption.getAttribute('data-price-per-cm3'), 0);
    var density        = toNumber(materialOption.getAttribute('data-density'), 0);
    var machineFactor  = toNumber(materialOption.getAttribute('data-machine-factor'), 1) || 1;
    var surfaceFactor  = toNumber(materialOption.getAttribute('data-surface-factor'), 1) || 1;
    var wastageFactor  = toNumber(materialOption.getAttribute('data-wastage-factor'), 1) || 1;

    var hourlyCost     = toNumber(printerOption.getAttribute('data-hourly-cost'), 0);
    var defaultSpeed   = toNumber(printerOption.getAttribute('data-default-speed'), 1);

    var layerHeight    = Math.max(0.01, toNumber(layerInput && layerInput.value, 0.2));
    var infill         = Math.max(0, Math.min(100, toNumber(infillInput && infillInput.value, 20)));
    var scale          = Math.max(10, toNumber(scaleInput && scaleInput.value, 100));
    var quantity       = Math.max(1, parseInt((quantityInput && quantityInput.value) || '1', 10) || 1);
    var shellMode      = shellSelect ? shellSelect.value : 'solid';

    var layerLimits = getPrinterLayerLimits(printerOption);
    if (layerLimits.min > 0 && layerHeight < layerLimits.min) {
      layerHeight = layerLimits.min;
    }
    if (layerLimits.max > 0 && layerHeight > layerLimits.max) {
      layerHeight = layerLimits.max;
    }

    var baseVolumeCm3 = getModelVolumeFromViewer(form);
    if (!baseVolumeCm3 || baseVolumeCm3 <= 0) {
      return null;
    }

    var scaleFactor   = Math.pow(scale / 100, 3);
    var infillFactor  = Math.max(0.05, Math.min(1, infill / 100));
    var shellFactor   = shellMode === 'hollow' ? 0.35 : 1;

    var effectiveVolumeCm3 = baseVolumeCm3 * scaleFactor * shellFactor;
    var printVolumeCm3     = effectiveVolumeCm3 * infillFactor;
    var adjustedVolumeCm3  = printVolumeCm3 * wastageFactor;

    var estimatedGrams = 0;
    if (density > 0) {
      estimatedGrams = adjustedVolumeCm3 * density;
    }

    var materialCostFromVolume = adjustedVolumeCm3 * pricePerCm3;
    var materialCostFromWeight = estimatedGrams * pricePerGram;

    var unitMaterialCost = Math.max(materialCostFromVolume, materialCostFromWeight);
    unitMaterialCost *= surfaceFactor;

    var estimatedHours = (adjustedVolumeCm3 / Math.max(defaultSpeed, 1)) * machineFactor * (0.2 / layerHeight);
    if (!Number.isFinite(estimatedHours) || estimatedHours < 0) {
      estimatedHours = 0;
    }

    var unitPrinterCost = estimatedHours * hourlyCost;

    var itemsSubtotal = (unitMaterialCost + unitPrinterCost) * quantity;
    var orderSubtotal = itemsSubtotal + serviceFee + setupFee;
    var marginAmount  = orderSubtotal * (profitMargin / 100);
    var subtotal      = orderSubtotal + marginAmount;
    var taxAmount     = subtotal * (taxRate / 100);
    var total         = subtotal + taxAmount;

    return {
      currencySymbol: currencySymbol,
      modelVolume: baseVolumeCm3,
      estimatedVolume: adjustedVolumeCm3,
      estimatedWeight: estimatedGrams * quantity,
      materialCost: unitMaterialCost * quantity,
      printerCost: unitPrinterCost * quantity,
      serviceFee: serviceFee,
      setupFee: setupFee,
      marginAmount: marginAmount,
      taxAmount: taxAmount,
      total: total
    };
  }

  function setText(form, selector, value) {
    var node = form ? form.querySelector(selector) : null;
    if (node) {
      node.textContent = value;
    }
  }

  function updateLiveEstimate(form) {
    if (!form) return;

    var estimate = calculateLiveEstimate(form);

    if (!estimate) {
      setText(form, '[data-srf-price-volume]', 'Upload STL/OBJ to calculate');
      setText(form, '[data-srf-price-material]', '—');
      setText(form, '[data-srf-price-printer]', '—');
      setText(form, '[data-srf-price-service]', '—');
      setText(form, '[data-srf-price-setup]', '—');
      setText(form, '[data-srf-price-tax]', '—');
      setText(form, '[data-srf-price-total]', '—');
      return;
    }

    setText(form, '[data-srf-price-volume]', formatVolume(estimate.estimatedVolume));
    setText(form, '[data-srf-price-material]', formatMoney(estimate.materialCost, estimate.currencySymbol));
    setText(form, '[data-srf-price-printer]', formatMoney(estimate.printerCost, estimate.currencySymbol));
    setText(form, '[data-srf-price-service]', formatMoney(estimate.serviceFee, estimate.currencySymbol));
    setText(form, '[data-srf-price-setup]', formatMoney(estimate.setupFee, estimate.currencySymbol));
    setText(form, '[data-srf-price-tax]', formatMoney(estimate.taxAmount, estimate.currencySymbol));
    setText(form, '[data-srf-price-total]', formatMoney(estimate.total, estimate.currencySymbol));

    setText(form, '[data-srf-price-weight]', formatWeight(estimate.estimatedWeight));
  }

  function initProjectLiveEstimate() {
    var form = document.querySelector('[data-srf-project-form]');
    if (!form) return;

    [
      '#srf-material-id',
      '#srf-printer-id',
      '#srf-layer-height',
      '#srf-infill',
      '#srf-shell-mode',
      '#srf-scale',
      '#srf-quantity',
      '#srf-files'
    ].forEach(function (selector) {
      var field = form.querySelector(selector);
      if (!field) return;

      field.addEventListener('change', function () {
        updateLiveEstimate(form);
      });

      field.addEventListener('input', function () {
        updateLiveEstimate(form);
      });
    });

    updateLiveEstimate(form);

    setTimeout(function () {
      updateLiveEstimate(form);
    }, 300);
    
    form.addEventListener('submit', function (e) {
      var estimate = calculateLiveEstimate(form);

      if (!estimate) {
        e.preventDefault();
        alert('Please upload a valid STL or OBJ model and select material and printer before submitting.');
        return;
      }
    });
  }

  SRF_onReady(function () {
    initProjectLiveEstimate();
  });
})();


SRF_onReady(function () {
  init3DQuotePage();
});

