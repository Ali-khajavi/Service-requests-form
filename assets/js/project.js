/* global window, document, Worker, FileReader, ResizeObserver, requestAnimationFrame, cancelAnimationFrame */
(function () {
  'use strict';

  var config = window.srfProject || {};
  var messages = config.messages || {};
  var profileMap = config.profiles || {};
  var MAX_BROWSER_PREVIEW_BYTES = 128 * 1024 * 1024;

  function query(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function queryAll(root, selector) {
    return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
  }

  function toNumber(value, fallback) {
    var number = Number(value);
    return isFinite(number) ? number : (typeof fallback === 'number' ? fallback : 0);
  }

  function toInteger(value, fallback) {
    var number = parseInt(value, 10);
    return isFinite(number) ? number : (typeof fallback === 'number' ? fallback : 0);
  }

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  }

  function extensionOf(name) {
    var parts = String(name || '').toLowerCase().split('.');
    return parts.length > 1 ? parts.pop() : '';
  }

  function formatBytes(bytes) {
    var value = Math.max(0, toNumber(bytes, 0));
    if (value < 1024) { return Math.round(value) + ' B'; }
    if (value < 1024 * 1024) { return (value / 1024).toFixed(1) + ' KB'; }
    if (value < 1024 * 1024 * 1024) { return (value / (1024 * 1024)).toFixed(1) + ' MB'; }
    return (value / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
  }

  function formatNumber(value, digits) {
    var number = toNumber(value, 0);
    try {
      return number.toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    } catch (error) {
      return number.toFixed(digits);
    }
  }

  function money(value, symbol) {
    return String(symbol || '') + formatNumber(Math.max(0, toNumber(value, 0)), 2);
  }

  function formatDuration(minutes) {
    var total = Math.max(0, Math.ceil(toNumber(minutes, 0)));
    var hours = Math.floor(total / 60);
    var remaining = total % 60;
    if (hours > 0) {
      return hours + ' h ' + remaining + ' min';
    }
    return remaining + ' min';
  }

  function parseJson(value, fallback) {
    try {
      var parsed = JSON.parse(String(value || ''));
      return parsed === null ? fallback : parsed;
    } catch (error) {
      return fallback;
    }
  }

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
  }

  function message(key, fallback) {
    return messages && messages[key] ? String(messages[key]) : String(fallback || '');
  }

  function interpolate(template, values) {
    return String(template || '').replace(/%(\d+)\$s/g, function (match, index) {
      var value = values[toInteger(index, 1) - 1];
      return value === undefined || value === null ? '' : String(value);
    }).replace(/%s/g, function () {
      return values.length ? String(values.shift()) : '';
    }).replace(/%%/g, '%');
  }

  function readFileBuffer(file) {
    if (file && typeof file.arrayBuffer === 'function') {
      return file.arrayBuffer();
    }
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(reader.error || new Error(message('couldNotReadFile', 'Could not read the file.'))); };
      reader.readAsArrayBuffer(file);
    });
  }

  function optionData(option, key, fallback) {
    if (!option) { return fallback; }
    var value = option.getAttribute('data-' + key);
    return value === null || value === '' ? fallback : value;
  }

  function selectedOption(select) {
    if (!select || select.selectedIndex < 0) { return null; }
    return select.options[select.selectedIndex] || null;
  }

  function WorkerBroker(url, maxTriangles) {
    this.url = url;
    this.maxTriangles = maxTriangles;
    this.worker = null;
    this.sequence = 0;
    this.pending = {};
  }

  WorkerBroker.prototype.available = function () {
    return typeof Worker !== 'undefined' && !!this.url;
  };

  WorkerBroker.prototype.start = function () {
    if (!this.available()) { return false; }
    this.stop();
    try {
      this.worker = new Worker(this.url);
    } catch (error) {
      this.worker = null;
      return false;
    }

    var self = this;
    this.worker.onmessage = function (event) {
      var payload = event && event.data ? event.data : {};
      var pending = self.pending[payload.id];
      if (!pending) { return; }
      delete self.pending[payload.id];
      if (payload.ok) {
        pending.resolve(payload);
      } else {
        pending.reject(new Error((messages.workerErrors && messages.workerErrors[payload.message]) || payload.message || message('previewError', 'The browser preview could not be created.')));
      }
    };
    this.worker.onerror = function () {
      Object.keys(self.pending).forEach(function (key) {
        self.pending[key].reject(new Error(message('previewStopped', 'The background model analyser stopped unexpectedly.')));
      });
      self.pending = {};
    };
    return true;
  };

  WorkerBroker.prototype.stop = function () {
    if (this.worker) {
      this.worker.terminate();
      this.worker = null;
    }
    Object.keys(this.pending).forEach(function (key) {
      this.pending[key].reject(new Error(message('analysisCancelled', 'Model analysis was cancelled.')));
    }, this);
    this.pending = {};
  };

  WorkerBroker.prototype.analyse = function (file, extension, buffer) {
    var self = this;
    return new Promise(function (resolve, reject) {
      if (!self.worker && !self.start()) {
        reject(new Error(message('analysisUnsupported', 'Background model analysis is not supported in this browser.')));
        return;
      }
      self.sequence += 1;
      var id = self.sequence;
      self.pending[id] = { resolve: resolve, reject: reject };
      try {
        self.worker.postMessage({
          id: id,
          name: file.name,
          extension: extension,
          maxPreviewTriangles: self.maxTriangles,
          buffer: buffer
        }, [buffer]);
      } catch (error) {
        delete self.pending[id];
        reject(error);
      }
    });
  };

  function CanvasModelRenderer(container) {
    this.container = container;
    this.canvas = query(container, 'canvas');
    this.context = this.canvas ? this.canvas.getContext('2d') : null;
    this.positions = null;
    this.center = { x: 0, y: 0, z: 0 };
    this.radius = 1;
    this.yaw = -0.65;
    this.pitch = 0.55;
    this.zoom = 1;
    this.dragging = false;
    this.lastPoint = null;
    this.frame = null;
    this.resizeObserver = null;
    this.bind();
  }

  CanvasModelRenderer.prototype.bind = function () {
    if (!this.canvas || !this.context) { return; }
    var self = this;

    this.canvas.addEventListener('pointerdown', function (event) {
      self.dragging = true;
      self.lastPoint = { x: event.clientX, y: event.clientY };
      if (self.canvas.setPointerCapture) {
        self.canvas.setPointerCapture(event.pointerId);
      }
    });
    this.canvas.addEventListener('pointermove', function (event) {
      if (!self.dragging || !self.lastPoint) { return; }
      var deltaX = event.clientX - self.lastPoint.x;
      var deltaY = event.clientY - self.lastPoint.y;
      self.yaw += deltaX * 0.01;
      self.pitch = clamp(self.pitch + deltaY * 0.01, -1.45, 1.45);
      self.lastPoint = { x: event.clientX, y: event.clientY };
      self.scheduleDraw();
    });
    function endDrag(event) {
      self.dragging = false;
      self.lastPoint = null;
      if (self.canvas.releasePointerCapture && event && event.pointerId !== undefined) {
        try { self.canvas.releasePointerCapture(event.pointerId); } catch (error) { /* no-op */ }
      }
    }
    this.canvas.addEventListener('pointerup', endDrag);
    this.canvas.addEventListener('pointercancel', endDrag);
    this.canvas.addEventListener('wheel', function (event) {
      event.preventDefault();
      self.zoom = clamp(self.zoom * (event.deltaY > 0 ? 0.9 : 1.1), 0.25, 8);
      self.scheduleDraw();
    }, { passive: false });

    queryAll(this.container, '[data-srf-view]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.setView(button.getAttribute('data-srf-view'));
      });
    });

    if (typeof ResizeObserver !== 'undefined') {
      this.resizeObserver = new ResizeObserver(function () { self.scheduleDraw(); });
      this.resizeObserver.observe(this.container);
    } else {
      window.addEventListener('resize', function () { self.scheduleDraw(); });
    }
  };

  CanvasModelRenderer.prototype.setModel = function (positions, center, radius) {
    this.positions = positions instanceof Float32Array ? positions : null;
    this.center = center || { x: 0, y: 0, z: 0 };
    this.radius = Math.max(0.0001, toNumber(radius, 1));
    this.zoom = 1;
    this.yaw = -0.65;
    this.pitch = 0.55;
    this.scheduleDraw();
  };

  CanvasModelRenderer.prototype.clear = function () {
    this.positions = null;
    this.scheduleDraw();
  };

  CanvasModelRenderer.prototype.setView = function (view) {
    if (view === 'front') {
      this.yaw = 0;
      this.pitch = 0;
    } else if (view === 'back') {
      this.yaw = Math.PI;
      this.pitch = 0;
    } else if (view === 'left') {
      this.yaw = -Math.PI / 2;
      this.pitch = 0;
    } else if (view === 'right') {
      this.yaw = Math.PI / 2;
      this.pitch = 0;
    } else if (view === 'top') {
      this.yaw = 0;
      this.pitch = -Math.PI / 2;
    } else if (view === 'bottom') {
      this.yaw = 0;
      this.pitch = Math.PI / 2;
    } else if (view === 'fit') {
      this.zoom = 1;
    } else {
      this.yaw = -0.65;
      this.pitch = 0.55;
      this.zoom = 1;
    }
    this.scheduleDraw();
  };

  CanvasModelRenderer.prototype.resize = function () {
    if (!this.canvas) { return { width: 0, height: 0 }; }
    var rect = this.canvas.getBoundingClientRect();
    var width = Math.max(260, Math.floor(rect.width || this.container.clientWidth || 640));
    var height = Math.max(260, Math.floor(rect.height || 420));
    var ratio = Math.min(2, window.devicePixelRatio || 1);
    var pixelWidth = Math.floor(width * ratio);
    var pixelHeight = Math.floor(height * ratio);
    if (this.canvas.width !== pixelWidth || this.canvas.height !== pixelHeight) {
      this.canvas.width = pixelWidth;
      this.canvas.height = pixelHeight;
    }
    this.context.setTransform(ratio, 0, 0, ratio, 0, 0);
    return { width: width, height: height };
  };

  CanvasModelRenderer.prototype.scheduleDraw = function () {
    var self = this;
    if (this.frame) {
      cancelAnimationFrame(this.frame);
    }
    this.frame = requestAnimationFrame(function () {
      self.frame = null;
      self.draw();
    });
  };

  CanvasModelRenderer.prototype.draw = function () {
    if (!this.context || !this.canvas) { return; }
    var size = this.resize();
    var context = this.context;
    context.clearRect(0, 0, size.width, size.height);

    var gradient = context.createLinearGradient(0, 0, 0, size.height);
    gradient.addColorStop(0, '#f7fafc');
    gradient.addColorStop(1, '#e8eef5');
    context.fillStyle = gradient;
    context.fillRect(0, 0, size.width, size.height);

    context.strokeStyle = 'rgba(70, 92, 118, 0.12)';
    context.lineWidth = 1;
    var grid = 28;
    for (var x = 0; x < size.width; x += grid) {
      context.beginPath();
      context.moveTo(x, 0);
      context.lineTo(x, size.height);
      context.stroke();
    }
    for (var y = 0; y < size.height; y += grid) {
      context.beginPath();
      context.moveTo(0, y);
      context.lineTo(size.width, y);
      context.stroke();
    }

    if (!this.positions || this.positions.length < 9) {
      context.fillStyle = '#59697a';
      context.font = '600 15px system-ui, sans-serif';
      context.textAlign = 'center';
      context.fillText('3D preview', size.width / 2, size.height / 2 - 8);
      context.font = '13px system-ui, sans-serif';
      context.fillStyle = '#718096';
      context.fillText(message('selectPreviewModel', 'Select an STL or OBJ model'), size.width / 2, size.height / 2 + 18);
      return;
    }

    var cosY = Math.cos(this.yaw);
    var sinY = Math.sin(this.yaw);
    var cosX = Math.cos(this.pitch);
    var sinX = Math.sin(this.pitch);
    var scale = Math.min(size.width, size.height) * 0.39 * this.zoom / this.radius;
    var centerX = size.width / 2;
    var centerY = size.height / 2;
    var triangles = [];

    function transform(px, py, pz, modelCenter) {
      var x0 = px - modelCenter.x;
      var y0 = py - modelCenter.y;
      var z0 = pz - modelCenter.z;
      var x1 = x0 * cosY + z0 * sinY;
      var z1 = -x0 * sinY + z0 * cosY;
      var y2 = y0 * cosX - z1 * sinX;
      var z2 = y0 * sinX + z1 * cosX;
      return { x: centerX + x1 * scale, y: centerY - y2 * scale, z: z2 };
    }

    for (var i = 0; i < this.positions.length; i += 9) {
      var a = transform(this.positions[i], this.positions[i + 1], this.positions[i + 2], this.center);
      var b = transform(this.positions[i + 3], this.positions[i + 4], this.positions[i + 5], this.center);
      var c = transform(this.positions[i + 6], this.positions[i + 7], this.positions[i + 8], this.center);
      var normalZ = (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
      triangles.push({ a: a, b: b, c: c, z: (a.z + b.z + c.z) / 3, light: normalZ });
    }

    triangles.sort(function (left, right) { return left.z - right.z; });
    context.lineWidth = 0.65;
    triangles.forEach(function (triangle) {
      var brightness = clamp(0.56 + Math.sign(triangle.light || 1) * 0.10 + triangle.z / (Math.max(1, scale) * 8), 0.35, 0.78);
      var blue = Math.round(155 + brightness * 90);
      var green = Math.round(115 + brightness * 100);
      context.beginPath();
      context.moveTo(triangle.a.x, triangle.a.y);
      context.lineTo(triangle.b.x, triangle.b.y);
      context.lineTo(triangle.c.x, triangle.c.y);
      context.closePath();
      context.fillStyle = 'rgba(28,' + green + ',' + blue + ',0.86)';
      context.fill();
      context.strokeStyle = 'rgba(20,70,92,0.22)';
      context.stroke();
    });
  };

  function ProjectOrderForm(form) {
    this.form = form;
    this.currentStep = clamp(toInteger(form.getAttribute('data-initial-step'), 1), 1, 3);
    this.furthestStep = this.currentStep;
    this.allowedExtensions = String(form.getAttribute('data-allowed-extensions') || 'stl,obj,3mf')
      .toLowerCase().split(',').map(function (item) { return item.trim(); }).filter(Boolean);
    this.maxUploadBytes = toNumber(form.getAttribute('data-max-upload-bytes'), 500 * 1024 * 1024);
    this.checkoutEnabled = form.getAttribute('data-checkout-enabled') === '1';
    this.files = [];
    this.fileValidationError = '';
    this.modelResults = [];
    this.metrics = null;
    this.metricsComplete = false;
    this.analysisPending = false;
    this.analysisBatch = 0;
    this.worker = new WorkerBroker(config.workerUrl || '', toInteger(config.previewTriangles, 160000));
    this.renderer = new CanvasModelRenderer(query(form, '[data-srf-model-viewer]'));
    this.bind();
    this.showStep(this.currentStep, false);
    this.syncPrinterOptions(true);
    this.updateEstimate();
  }

  ProjectOrderForm.prototype.bind = function () {
    var self = this;

    queryAll(this.form, '[data-srf-next-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        var next = toInteger(button.getAttribute('data-srf-next-step'), self.currentStep + 1);
        if (self.validateStep(self.currentStep)) {
          self.furthestStep = Math.max(self.furthestStep, next);
          self.showStep(next, true);
        }
      });
    });
    queryAll(this.form, '[data-srf-prev-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.showStep(toInteger(button.getAttribute('data-srf-prev-step'), self.currentStep - 1), true);
      });
    });
    queryAll(this.form, '[data-srf-step-go]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = toInteger(button.getAttribute('data-srf-step-go'), 1);
        if (target <= self.furthestStep) {
          self.showStep(target, true);
        }
      });
    });

    this.fileInput = query(this.form, '[data-srf-model-files]');
    this.dropzone = query(this.form, '[data-srf-dropzone]');
    if (this.fileInput) {
      this.fileInput.addEventListener('change', function () {
        self.handleFileSelection(Array.prototype.slice.call(self.fileInput.files || []));
      });
    }
    if (this.dropzone && this.fileInput) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        self.dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          self.dropzone.classList.add('is-dragging');
        });
      });
      ['dragleave', 'drop'].forEach(function (eventName) {
        self.dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          self.dropzone.classList.remove('is-dragging');
        });
      });
      this.dropzone.addEventListener('drop', function (event) {
        var dropped = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
        if (!dropped || !dropped.length) { return; }
        try {
          self.fileInput.files = dropped;
          self.handleFileSelection(Array.prototype.slice.call(dropped));
        } catch (error) {
          self.setFileNotice(message('useSelectButton', 'Use the Select models button to add these files in this browser.'), 'warning');
        }
      });
    }

    this.printerSelect = query(this.form, '[name="srf_printer_id"]');
    this.materialSelect = query(this.form, '[name="srf_material_id"]');
    this.profileSelect = query(this.form, '[name="srf_print_profile"]');
    if (this.printerSelect) {
      this.printerSelect.addEventListener('change', function () { self.syncPrinterOptions(false); });
    }
    if (this.materialSelect) {
      this.materialSelect.addEventListener('change', function () { self.updateEstimate(); });
    }
    if (this.profileSelect) {
      this.profileSelect.addEventListener('change', function () {
        self.applySelectedProfile();
        self.updateEstimate();
      });
    }

    queryAll(this.form, '[data-srf-quote-input]').forEach(function (field) {
      field.addEventListener('input', function () { self.updateEstimate(); });
      field.addEventListener('change', function () { self.updateEstimate(); });
    });

    this.form.addEventListener('submit', function (event) {
      if (!self.validateStep(1)) {
        event.preventDefault();
        self.showStep(1, true);
        return;
      }
      if (!self.validateStep(2)) {
        event.preventDefault();
        self.showStep(2, true);
        return;
      }
      if (!self.validateStep(3)) {
        event.preventDefault();
        self.showStep(3, true);
        return;
      }
      self.setSubmitting(true);
    });
  };

  ProjectOrderForm.prototype.showStep = function (step, focusHeading) {
    step = clamp(toInteger(step, 1), 1, 3);
    this.currentStep = step;
    queryAll(this.form, '[data-srf-step-panel]').forEach(function (panel) {
      var active = toInteger(panel.getAttribute('data-srf-step-panel'), 0) === step;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    queryAll(this.form, '[data-srf-step-go]').forEach(function (button) {
      var number = toInteger(button.getAttribute('data-srf-step-go'), 0);
      button.classList.toggle('is-active', number === step);
      button.classList.toggle('is-complete', number < step);
      button.setAttribute('aria-current', number === step ? 'step' : 'false');
    });
    this.form.setAttribute('data-current-step', String(step));

    if (focusHeading) {
      var panel = query(this.form, '[data-srf-step-panel="' + step + '"]');
      var heading = query(panel, 'h2');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        try {
          heading.focus({ preventScroll: true });
        } catch (error) {
          heading.focus();
        }
      }
      try {
        this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        this.form.scrollIntoView(true);
      }
    }
    if (step === 3) {
      this.updateEstimate();
    }
  };

  ProjectOrderForm.prototype.setStepError = function (step, text) {
    var element = query(this.form, '[data-srf-step-error="' + step + '"]');
    if (!element) { return; }
    element.textContent = text || '';
    element.hidden = !text;
  };

  ProjectOrderForm.prototype.validatePanelFields = function (step) {
    var panel = query(this.form, '[data-srf-step-panel="' + step + '"]');
    if (!panel) { return true; }
    var invalid = queryAll(panel, 'input, select, textarea').filter(function (field) {
      return !field.disabled && typeof field.checkValidity === 'function' && !field.checkValidity();
    });
    if (invalid.length) {
      if (typeof invalid[0].reportValidity === 'function') {
        invalid[0].reportValidity();
      } else {
        invalid[0].focus();
      }
      return false;
    }
    return true;
  };

  ProjectOrderForm.prototype.validateStep = function (step) {
    this.setStepError(step, '');
    if (!this.validatePanelFields(step)) {
      this.setStepError(step, message('completeRequired', 'Complete the required fields before continuing.'));
      return false;
    }

    if (step === 2) {
      if (!this.files.length) {
        this.setStepError(step, messages.fileRequired || 'Select at least one STL, OBJ, or 3MF model.');
        return false;
      }
      if (this.fileValidationError) {
        this.setStepError(step, this.fileValidationError);
        return false;
      }
      if (this.analysisPending) {
        this.setStepError(step, message('waitAnalysis', 'Please wait while the model is analysed.'));
        return false;
      }
    }

    if (step === 3) {
      var quote = this.calculateQuote();
      if (quote && quote.fits === false) {
        this.setStepError(step, messages.doesNotFit || 'The selected model does not fit this printer at the current scale.');
        return false;
      }
    }
    return true;
  };

  ProjectOrderForm.prototype.setFileNotice = function (text, type) {
    var notice = query(this.form, '[data-srf-file-notice]');
    if (!notice) { return; }
    notice.textContent = text || '';
    notice.hidden = !text;
    notice.setAttribute('data-type', type || 'info');
  };

  ProjectOrderForm.prototype.handleFileSelection = function (files) {
    var self = this;
    var errors = [];
    var total = 0;
    files.forEach(function (file) {
      total += Math.max(0, toNumber(file.size, 0));
      var extension = extensionOf(file.name);
      if (self.allowedExtensions.indexOf(extension) === -1) {
        errors.push(file.name + ': unsupported format.');
      }
      if (!file.size) {
        errors.push(file.name + ': empty file.');
      }
      if (file.size > self.maxUploadBytes) {
        errors.push(file.name + ': exceeds ' + formatBytes(self.maxUploadBytes) + '.');
      }
    });
    if (total > this.maxUploadBytes) {
      errors.push(interpolate(message('fileTotalExceeds', 'The selected files total %1$s, above the %2$s upload limit.'), [formatBytes(total), formatBytes(this.maxUploadBytes)]));
    }

    this.files = files;
    this.fileValidationError = errors.join(' ');
    this.renderFileList();
    this.metrics = null;
    this.metricsComplete = false;
    this.modelResults = [];
    this.renderer.clear();
    this.updateModelMeta(null);

    if (errors.length) {
      this.setFileNotice(this.fileValidationError, 'error');
      this.analysisPending = false;
      this.updateEstimate();
      return;
    }
    this.fileValidationError = '';
    this.setFileNotice('', 'info');
    this.analyseFiles(files);
  };

  ProjectOrderForm.prototype.renderFileList = function () {
    var list = query(this.form, '[data-srf-file-list]');
    if (!list) { return; }
    list.innerHTML = '';
    this.files.forEach(function (file, index) {
      var item = document.createElement('li');
      item.setAttribute('data-file-index', String(index));
      item.innerHTML = '<span class="srf-project-file__name"></span><span class="srf-project-file__size"></span><span class="srf-project-file__status" data-file-status></span>';
      setText(query(item, '.srf-project-file__name'), file.name);
      setText(query(item, '.srf-project-file__size'), formatBytes(file.size));
      setText(query(item, '[data-file-status]'), message('waiting', 'Waiting'));
      list.appendChild(item);
    });
    list.hidden = !this.files.length;
  };

  ProjectOrderForm.prototype.setFileStatus = function (index, text, status) {
    var item = query(this.form, '[data-file-index="' + index + '"]');
    if (!item) { return; }
    item.setAttribute('data-status', status || 'pending');
    setText(query(item, '[data-file-status]'), text);
  };

  ProjectOrderForm.prototype.setAnalysisProgress = function (completed, total) {
    var progress = query(this.form, '[data-srf-analysis-progress]');
    var bar = query(this.form, '[data-srf-analysis-progress-bar]');
    var text = query(this.form, '[data-srf-analysis-progress-text]');
    if (progress) { progress.hidden = total <= 0 || completed >= total; }
    if (bar) {
      bar.max = Math.max(1, total);
      bar.value = Math.min(total, completed);
    }
    if (text) {
      text.textContent = total > 0 ? (completed + ' / ' + total) : '';
    }
  };

  ProjectOrderForm.prototype.analyseFiles = function (files) {
    var self = this;
    var batch = this.analysisBatch + 1;
    this.analysisBatch = batch;
    this.analysisPending = true;
    this.modelResults = [];
    this.metrics = null;
    this.metricsComplete = false;
    this.worker.stop();
    this.worker.start();
    this.setAnalysisProgress(0, files.length);
    this.setFileNotice(messages.parsing || 'Analysing the model in the background…', 'info');

    var chain = Promise.resolve();
    files.forEach(function (file, index) {
      chain = chain.then(function () {
        if (batch !== self.analysisBatch) { return; }
        var extension = extensionOf(file.name);
        if (extension === '3mf') {
          self.modelResults.push({ file: file, extension: extension, serverOnly: true });
          self.setFileStatus(index, message('serverAnalysis', 'Server analysis'), 'server');
          self.setAnalysisProgress(index + 1, files.length);
          return;
        }
        if (file.size > MAX_BROWSER_PREVIEW_BYTES) {
          self.modelResults.push({ file: file, extension: extension, serverOnly: true });
          self.setFileStatus(index, message('largeServerAnalysis', 'Large file: server analysis'), 'server');
          self.setAnalysisProgress(index + 1, files.length);
          return;
        }
        if (!self.worker.available()) {
          self.modelResults.push({ file: file, extension: extension, serverOnly: true });
          self.setFileStatus(index, message('serverAnalysis', 'Server analysis'), 'server');
          self.setAnalysisProgress(index + 1, files.length);
          return;
        }

        self.setFileStatus(index, message('analysing', 'Analysing…'), 'working');
        return readFileBuffer(file)
          .then(function (buffer) {
            if (batch !== self.analysisBatch) { throw new Error('Cancelled'); }
            return self.worker.analyse(file, extension, buffer);
          })
          .then(function (result) {
            result.file = file;
            result.extension = extension;
            self.modelResults.push(result);
            self.setFileStatus(index, message('ready', 'Ready'), 'ready');
            if (!self.renderer.positions && result.previewPositions && result.previewPositions.length) {
              self.renderer.setModel(result.previewPositions, result.center, result.radius);
              self.updateModelMeta(result);
            }
          })
          .catch(function (error) {
            if (batch !== self.analysisBatch || (error && error.message === 'Cancelled')) { return; }
            self.modelResults.push({ file: file, extension: extension, serverOnly: true, browserError: error && error.message ? error.message : '' });
            self.setFileStatus(index, message('serverAnalysis', 'Server analysis'), 'server');
          })
          .then(function () {
            self.setAnalysisProgress(index + 1, files.length);
          });
      });
    });

    chain.then(function () {
      if (batch !== self.analysisBatch) { return; }
      self.analysisPending = false;
      self.worker.stop();
      self.buildAggregateMetrics();
      self.setAnalysisProgress(files.length, files.length);
      if (self.metricsComplete) {
        self.setFileNotice(messages.previewReady || 'Preview ready. The server will verify the final amount before payment.', 'success');
      } else if (self.modelResults.some(function (item) { return item.extension === '3mf'; })) {
        self.setFileNotice(messages.threeMf || '3MF is securely analysed after submission. Instant browser preview is available for STL and OBJ.', 'info');
      } else {
        self.setFileNotice(messages.previewError || 'The browser preview could not be created. The server will analyse the model before checkout.', 'warning');
      }
      self.updateEstimate();
    });
  };

  ProjectOrderForm.prototype.buildAggregateMetrics = function () {
    var complete = this.modelResults.length === this.files.length && this.modelResults.every(function (item) {
      return item.ok && !item.serverOnly;
    });
    this.metricsComplete = complete;
    if (!complete) {
      this.metrics = null;
      return;
    }

    var aggregate = {
      volumeMm3: 0,
      surfaceAreaMm2: 0,
      triangleCount: 0,
      bounds: { x: 0, y: 0, z: 0 },
      models: []
    };
    this.modelResults.forEach(function (result) {
      aggregate.volumeMm3 += toNumber(result.volumeMm3, 0);
      aggregate.surfaceAreaMm2 += toNumber(result.surfaceAreaMm2, 0);
      aggregate.triangleCount += toInteger(result.triangleCount, 0);
      aggregate.bounds.x = Math.max(aggregate.bounds.x, toNumber(result.bounds && result.bounds.x, 0));
      aggregate.bounds.y = Math.max(aggregate.bounds.y, toNumber(result.bounds && result.bounds.y, 0));
      aggregate.bounds.z = Math.max(aggregate.bounds.z, toNumber(result.bounds && result.bounds.z, 0));
      aggregate.models.push({
        name: result.file ? result.file.name : 'Model',
        bounds: result.bounds || { x: 0, y: 0, z: 0 }
      });
    });
    this.metrics = aggregate;
    this.updateModelMeta({
      file: this.files.length === 1 ? this.files[0] : { name: this.files.length + ' models' },
      format: this.files.length === 1 ? extensionOf(this.files[0].name) : 'multiple',
      triangleCount: aggregate.triangleCount,
      bounds: aggregate.bounds,
      volumeCm3: aggregate.volumeMm3 / 1000
    });
  };

  ProjectOrderForm.prototype.updateModelMeta = function (result) {
    var meta = query(this.form, '[data-srf-model-meta]');
    if (!meta) { return; }
    setText(query(meta, '[data-field="filename"]'), result && result.file ? result.file.name : '—');
    setText(query(meta, '[data-field="format"]'), result && result.format ? String(result.format).toUpperCase() : '—');
    setText(query(meta, '[data-field="triangles"]'), result ? formatNumber(result.triangleCount || 0, 0) : '—');
    if (result && result.bounds) {
      setText(query(meta, '[data-field="bounds"]'), formatNumber(result.bounds.x, 1) + ' × ' + formatNumber(result.bounds.y, 1) + ' × ' + formatNumber(result.bounds.z, 1) + ' mm');
    } else {
      setText(query(meta, '[data-field="bounds"]'), '—');
    }
    setText(query(meta, '[data-field="volume"]'), result && result.volumeCm3 !== undefined ? formatNumber(result.volumeCm3, 2) + ' cm³' : '—');
  };

  ProjectOrderForm.prototype.getPrinter = function () {
    var option = selectedOption(this.printerSelect);
    if (!option || !option.value) { return null; }
    return {
      id: toInteger(option.value, 0),
      name: option.textContent.trim(),
      brand: optionData(option, 'brand', ''),
      model: optionData(option, 'model', ''),
      technology: String(optionData(option, 'technology', 'fdm')).toLowerCase(),
      suffix: optionData(option, 'printer-suffix', 'BBL'),
      isBambu: optionData(option, 'is-bambu', '0') === '1',
      supportedMaterials: parseJson(optionData(option, 'supported-materials', '[]'), []).map(function (id) { return toInteger(id, 0); }),
      defaultMaterialId: toInteger(optionData(option, 'default-material-id', 0), 0),
      buildX: toNumber(optionData(option, 'build-x', 0), 0),
      buildY: toNumber(optionData(option, 'build-y', 0), 0),
      buildZ: toNumber(optionData(option, 'build-z', 0), 0),
      nozzleSize: toNumber(optionData(option, 'nozzle-size', 0.4), 0.4),
      lineWidth: toNumber(optionData(option, 'line-width', 0), 0),
      minLayer: toNumber(optionData(option, 'min-layer-height', 0), 0),
      maxLayer: toNumber(optionData(option, 'max-layer-height', 0), 0),
      speed: toNumber(optionData(option, 'default-speed', 0), 0),
      speedUnit: optionData(option, 'speed-unit', ''),
      hourlyCost: toNumber(optionData(option, 'hourly-cost', 0), 0),
      efficiency: Math.max(0.05, toNumber(optionData(option, 'efficiency', 1), 1)),
      setupMinutes: Math.max(0, toNumber(optionData(option, 'setup-minutes', 0), 0)),
      warmupMinutes: Math.max(0, toNumber(optionData(option, 'warmup-minutes', 0), 0)),
      postprocessMinutes: Math.max(0, toNumber(optionData(option, 'postprocess-minutes', 0), 0)),
      minimumJobPrice: Math.max(0, toNumber(optionData(option, 'minimum-job-price', 0), 0)),
      minimumMaterialCharge: Math.max(0, toNumber(optionData(option, 'minimum-material-charge', 0), 0)),
      marginOverride: optionData(option, 'margin-override', '') === '' ? null : Math.max(0, toNumber(optionData(option, 'margin-override', 0), 0)),
      pricingModel: optionData(option, 'pricing-model', 'hybrid'),
      supportFactor: Math.max(1, toNumber(optionData(option, 'support-factor', 1.12), 1.12))
    };
  };

  ProjectOrderForm.prototype.getMaterial = function () {
    var option = selectedOption(this.materialSelect);
    if (!option || !option.value) { return null; }
    return {
      id: toInteger(option.value, 0),
      name: option.textContent.trim(),
      pricePerGram: Math.max(0, toNumber(optionData(option, 'price-per-gram', 0), 0)),
      pricePerCm3: Math.max(0, toNumber(optionData(option, 'price-per-cm3', 0), 0)),
      density: Math.max(0, toNumber(optionData(option, 'density', 0), 0)),
      machineFactor: Math.max(0.01, toNumber(optionData(option, 'machine-factor', 1), 1)),
      surfaceFactor: Math.max(0.01, toNumber(optionData(option, 'surface-factor', 1), 1)),
      wastageFactor: Math.max(0.01, toNumber(optionData(option, 'wastage-factor', 1), 1))
    };
  };

  ProjectOrderForm.prototype.syncPrinterOptions = function (initial) {
    var printer = this.getPrinter();
    var selectedMaterial = this.materialSelect ? toInteger(this.materialSelect.value, 0) : 0;
    var supported = printer ? printer.supportedMaterials : [];
    var firstAllowed = 0;

    if (this.materialSelect) {
      Array.prototype.forEach.call(this.materialSelect.options, function (option, index) {
        if (index === 0) {
          option.hidden = false;
          option.disabled = false;
          return;
        }
        var id = toInteger(option.value, 0);
        var allowed = !printer || !supported.length || supported.indexOf(id) !== -1;
        option.hidden = !allowed;
        option.disabled = !allowed;
        if (allowed && !firstAllowed) { firstAllowed = id; }
      });

      if (printer && selectedMaterial && supported.length && supported.indexOf(selectedMaterial) === -1) {
        this.materialSelect.value = '';
      }
      if (printer && !this.materialSelect.value) {
        var preferred = printer.defaultMaterialId && (!supported.length || supported.indexOf(printer.defaultMaterialId) !== -1)
          ? printer.defaultMaterialId : firstAllowed;
        if (preferred) { this.materialSelect.value = String(preferred); }
      }
    }

    this.syncProfileOptions(printer, initial);
    this.updateEstimate();
  };

  ProjectOrderForm.prototype.syncProfileOptions = function (printer, initial) {
    if (!this.profileSelect) { return; }
    var isBambu = !!(printer && printer.isBambu && config.profilesEnabled !== false);
    var current = this.profileSelect.value;
    Array.prototype.forEach.call(this.profileSelect.options, function (option) {
      if (option.value === 'custom' || !option.value) {
        option.hidden = false;
        option.disabled = false;
        return;
      }
      option.hidden = !isBambu;
      option.disabled = !isBambu;
      var profile = profileMap[option.value];
      if (profile) {
        option.textContent = isBambu ? (profile.name + ' @' + (printer.suffix || 'BBL')) : profile.name;
      }
    });

    if (!isBambu) {
      this.profileSelect.value = 'custom';
    } else if (!profileMap[current]) {
      this.profileSelect.value = config.defaultProfile || 'bambu-020-standard';
    } else if (initial && current) {
      this.profileSelect.value = current;
    }
    this.applySelectedProfile();
  };

  ProjectOrderForm.prototype.applySelectedProfile = function () {
    var key = this.profileSelect ? this.profileSelect.value : 'custom';
    var profile = profileMap[key] || null;
    var locked = !!profile;
    var fields = {
      layer_height: query(this.form, '[name="srf_layer_height"]'),
      infill: query(this.form, '[name="srf_infill"]'),
      wall_loops: query(this.form, '[name="srf_wall_loops"]'),
      top_layers: query(this.form, '[name="srf_top_layers"]'),
      bottom_layers: query(this.form, '[name="srf_bottom_layers"]'),
      infill_pattern: query(this.form, '[name="srf_infill_pattern"]')
    };

    if (profile) {
      if (fields.layer_height) { fields.layer_height.value = profile.layer_height; }
      if (fields.infill) { fields.infill.value = profile.infill; }
      if (fields.wall_loops) { fields.wall_loops.value = profile.wall_loops || profile.wall_count || 2; }
      if (fields.top_layers) { fields.top_layers.value = profile.top_layers; }
      if (fields.bottom_layers) { fields.bottom_layers.value = profile.bottom_layers; }
      if (fields.infill_pattern) { fields.infill_pattern.value = profile.infill_pattern || 'grid'; }
    }

    Object.keys(fields).forEach(function (name) {
      var field = fields[name];
      if (!field) { return; }
      if (field.tagName === 'SELECT') {
        field.disabled = locked;
      } else {
        field.readOnly = locked;
      }
      field.setAttribute('aria-readonly', locked ? 'true' : 'false');
    });
    var advanced = query(this.form, '[data-srf-advanced-settings]');
    if (advanced) { advanced.classList.toggle('is-profile-locked', locked); }
    var notice = query(this.form, '[data-srf-profile-notice]');
    if (notice) {
      notice.hidden = !locked;
      notice.textContent = locked ? message('profileLocked', 'This named Bambu process controls layer height, infill, walls, and top/bottom layers. Choose Custom settings to edit them.') : '';
    }
  };

  ProjectOrderForm.prototype.getQuoteOptions = function () {
    var key = this.profileSelect ? this.profileSelect.value : 'custom';
    var profile = profileMap[key] || null;
    return {
      profileKey: profile ? key : 'custom',
      profileName: profile ? profile.name : message('customSettings', 'Custom settings'),
      layerHeight: clamp(profile ? toNumber(profile.layer_height, 0.20) : toNumber(query(this.form, '[name="srf_layer_height"]') && query(this.form, '[name="srf_layer_height"]').value, 0.20), 0.01, 1),
      infill: clamp(profile ? toInteger(profile.infill, 20) : toInteger(query(this.form, '[name="srf_infill"]') && query(this.form, '[name="srf_infill"]').value, 20), 0, 100),
      wallLoops: clamp(profile ? toInteger(profile.wall_loops || profile.wall_count, 2) : toInteger(query(this.form, '[name="srf_wall_loops"]') && query(this.form, '[name="srf_wall_loops"]').value, 2), 1, 12),
      topLayers: clamp(profile ? toInteger(profile.top_layers, 4) : toInteger(query(this.form, '[name="srf_top_layers"]') && query(this.form, '[name="srf_top_layers"]').value, 4), 0, 30),
      bottomLayers: clamp(profile ? toInteger(profile.bottom_layers, 3) : toInteger(query(this.form, '[name="srf_bottom_layers"]') && query(this.form, '[name="srf_bottom_layers"]').value, 3), 0, 30),
      infillPattern: profile ? (profile.infill_pattern || 'grid') : String(query(this.form, '[name="srf_infill_pattern"]') && query(this.form, '[name="srf_infill_pattern"]').value || 'grid'),
      timeFactor: profile ? clamp(toNumber(profile.time_factor, 1), 0.1, 5) : 1,
      materialFactor: profile ? clamp(toNumber(profile.material_factor, 1), 0.1, 5) : 1,
      supports: !!(query(this.form, '[name="srf_supports"]') && query(this.form, '[name="srf_supports"]').checked),
      shellMode: String(query(this.form, '[name="srf_shell_mode"]') && query(this.form, '[name="srf_shell_mode"]').value || 'solid') === 'hollow' ? 'hollow' : 'solid',
      scale: clamp(toInteger(query(this.form, '[name="srf_scale"]') && query(this.form, '[name="srf_scale"]').value, 100), 10, 500),
      quantity: clamp(toInteger(query(this.form, '[name="srf_quantity"]') && query(this.form, '[name="srf_quantity"]').value, 1), 1, 999)
    };
  };

  ProjectOrderForm.prototype.resolveThroughput = function (printer) {
    var speed = Math.max(0, printer.speed);
    var unit = String(printer.speedUnit || '').toLowerCase().replace(/\s+/g, '');
    if (unit.indexOf('cm3') !== -1 || unit.indexOf('cm³') !== -1) {
      return Math.max(0.25, speed);
    }
    if (speed > 0 && speed <= 30) {
      return Math.max(0.25, speed);
    }
    if (speed > 30) {
      return Math.max(2, Math.min(30, speed * 0.05));
    }
    return printer.technology === 'resin' ? 5 : 8;
  };

  ProjectOrderForm.prototype.dimensionsFit = function (dimensions, build) {
    var permutations = [
      [0, 1, 2], [0, 2, 1], [1, 0, 2],
      [1, 2, 0], [2, 0, 1], [2, 1, 0]
    ];
    for (var index = 0; index < permutations.length; index += 1) {
      var order = permutations[index];
      if (
        dimensions[order[0]] <= build[0] + 0.001 &&
        dimensions[order[1]] <= build[1] + 0.001 &&
        dimensions[order[2]] <= build[2] + 0.001
      ) {
        return true;
      }
    }
    return false;
  };

  ProjectOrderForm.prototype.modelsFit = function (printer, scaleLinear) {
    if (!this.metrics || !this.metrics.models.length || Math.min(printer.buildX, printer.buildY, printer.buildZ) <= 0) {
      return null;
    }
    var build = [printer.buildX, printer.buildY, printer.buildZ];
    return this.metrics.models.every(function (model) {
      return this.dimensionsFit([
        toNumber(model.bounds.x, 0) * scaleLinear,
        toNumber(model.bounds.y, 0) * scaleLinear,
        toNumber(model.bounds.z, 0) * scaleLinear
      ], build);
    }, this);
  };

  ProjectOrderForm.prototype.calculateQuote = function () {
    var printer = this.getPrinter();
    var material = this.getMaterial();
    var options = this.getQuoteOptions();
    if (!printer || !material || !this.metrics || !this.metricsComplete || this.metrics.volumeMm3 <= 0) {
      return null;
    }

    if (printer.minLayer > 0 && options.layerHeight < printer.minLayer) { return { invalidLayer: true }; }
    if (printer.maxLayer > 0 && options.layerHeight > printer.maxLayer) { return { invalidLayer: true }; }

    var summary = query(this.form, '[data-srf-quote-summary]');
    var taxRate = Math.max(0, toNumber(summary && summary.getAttribute('data-tax-rate'), 0));
    var serviceFee = Math.max(0, toNumber(summary && summary.getAttribute('data-service-fee'), 0));
    var setupFee = Math.max(0, toNumber(summary && summary.getAttribute('data-setup-fee'), 0));
    var margin = Math.max(0, toNumber(summary && summary.getAttribute('data-profit-margin'), 0));
    if (printer.marginOverride !== null) { margin = printer.marginOverride; }

    var scaleLinear = options.scale / 100;
    var scaleArea = scaleLinear * scaleLinear;
    var scaleVolume = scaleArea * scaleLinear;
    var fits = this.modelsFit(printer, scaleLinear);
    var solidVolumeCm3 = (this.metrics.volumeMm3 / 1000) * scaleVolume;
    var surfaceAreaMm2 = this.metrics.surfaceAreaMm2 * scaleArea;
    var lineWidth = printer.lineWidth > 0 ? printer.lineWidth : Math.max(0.1, printer.nozzleSize * 1.05);
    var wallThickness = lineWidth * options.wallLoops;
    var capEquivalent = options.layerHeight * (options.topLayers + options.bottomLayers) * 0.10;
    var shellEquivalent = Math.max(lineWidth, wallThickness + capEquivalent);
    var shellVolumeCm3 = Math.min(solidVolumeCm3, Math.max(0, surfaceAreaMm2 * shellEquivalent / 1000));
    var interiorVolumeCm3 = Math.max(0, solidVolumeCm3 - shellVolumeCm3);
    var printedVolumeCm3 = options.shellMode === 'hollow'
      ? shellVolumeCm3
      : shellVolumeCm3 + interiorVolumeCm3 * (options.infill / 100);
    var withSupportCm3 = printedVolumeCm3 * (options.supports ? printer.supportFactor : 1);
    var adjustedCm3 = withSupportCm3 * material.wastageFactor;
    var estimatedGrams = material.density > 0 ? adjustedCm3 * material.density : 0;
    var materialByVolume = adjustedCm3 * material.pricePerCm3;
    var materialByWeight = estimatedGrams * material.pricePerGram;
    var unitMaterialCost = Math.max(materialByVolume, materialByWeight) * material.surfaceFactor * options.materialFactor;
    var materialTotal = unitMaterialCost * options.quantity;
    var materialMinimumAdjustment = Math.max(0, printer.minimumMaterialCharge * options.quantity - materialTotal);
    materialTotal += materialMinimumAdjustment;

    var throughput = this.resolveThroughput(printer);
    var layerFactor = Math.pow(0.20 / Math.max(0.04, options.layerHeight), 0.65);
    var unitPrintHours = Math.max(0.01,
      (withSupportCm3 / throughput) * material.machineFactor * printer.efficiency * layerFactor * options.timeFactor
    );
    var fixedHours = (printer.setupMinutes + printer.warmupMinutes + printer.postprocessMinutes) / 60;
    var totalHours = unitPrintHours * options.quantity + fixedHours;
    var printerTotal = totalHours * printer.hourlyCost;

    var pricingModel = String(printer.pricingModel || 'hybrid').toLowerCase();
    if (pricingModel === 'material' || pricingModel === 'material_only') {
      printerTotal = 0;
    } else if (pricingModel === 'machine_time' || pricingModel === 'time_only') {
      materialTotal = 0;
      materialMinimumAdjustment = 0;
    }

    var beforeMargin = materialTotal + printerTotal + serviceFee + setupFee;
    var marginAmount = beforeMargin * margin / 100;
    var withMargin = beforeMargin + marginAmount;
    var minimumJobAdjustment = Math.max(0, printer.minimumJobPrice * options.quantity - withMargin);
    var taxable = withMargin + minimumJobAdjustment;
    var tax = taxable * taxRate / 100;
    var total = taxable + tax;

    return {
      fits: fits,
      solidVolumeCm3: solidVolumeCm3,
      printedVolumeCm3: printedVolumeCm3,
      adjustedVolumeCm3: adjustedCm3,
      estimatedGrams: estimatedGrams,
      materialCost: materialTotal,
      printerCost: printerTotal,
      serviceFee: serviceFee,
      setupFee: setupFee,
      marginAmount: marginAmount,
      minimumJobAdjustment: minimumJobAdjustment,
      tax: tax,
      total: Math.max(0, total),
      totalMinutes: Math.ceil(totalHours * 60),
      options: options,
      printer: printer,
      material: material
    };
  };

  ProjectOrderForm.prototype.updateEstimate = function () {
    var summary = query(this.form, '[data-srf-quote-summary]');
    if (!summary) { return; }
    var symbol = summary.getAttribute('data-currency-symbol') || '€';
    var printer = this.getPrinter();
    var material = this.getMaterial();
    var options = this.getQuoteOptions();
    var quote = this.calculateQuote();

    setText(query(summary, '[data-srf-summary-material]'), material ? material.name : '—');
    setText(query(summary, '[data-srf-summary-printer]'), printer ? printer.name : '—');
    setText(query(summary, '[data-srf-summary-profile]'), this.profileSelect && selectedOption(this.profileSelect) ? selectedOption(this.profileSelect).textContent.trim() : '—');
    setText(query(summary, '[data-srf-summary-layer]'), formatNumber(options.layerHeight, 2) + ' mm');
    setText(query(summary, '[data-srf-summary-quantity]'), String(options.quantity));

    var status = query(summary, '[data-srf-estimate-status]');
    var fit = query(summary, '[data-srf-fit-status]');
    if (!quote || quote.invalidLayer) {
      setText(query(summary, '[data-srf-price-volume]'), this.metrics ? formatNumber(this.metrics.volumeMm3 / 1000, 2) + ' cm³' : '—');
      setText(query(summary, '[data-srf-price-weight]'), '—');
      setText(query(summary, '[data-srf-price-material]'), '—');
      setText(query(summary, '[data-srf-price-printer]'), '—');
      setText(query(summary, '[data-srf-price-fees]'), '—');
      setText(query(summary, '[data-srf-price-tax]'), '—');
      setText(query(summary, '[data-srf-price-time]'), '—');
      setText(query(summary, '[data-srf-price-total]'), '—');
      if (status) {
        status.textContent = quote && quote.invalidLayer
          ? message('invalidLayer', 'The selected layer height is outside this printer’s supported range.')
          : (messages.unknownEstimate || 'Select a model, printer, and material to see an estimate.');
        status.setAttribute('data-type', quote && quote.invalidLayer ? 'error' : 'info');
      }
      if (fit) {
        fit.textContent = this.metricsComplete ? message('selectPrinterBuild', 'Select a printer to check build volume.') : message('buildCheckDuringAnalysis', 'Build-volume check occurs during secure server analysis.');
        fit.setAttribute('data-fit', 'unknown');
      }
      return;
    }

    setText(query(summary, '[data-srf-price-volume]'), formatNumber(quote.printedVolumeCm3, 2) + ' cm³');
    setText(query(summary, '[data-srf-price-weight]'), formatNumber(quote.estimatedGrams, 1) + ' g');
    setText(query(summary, '[data-srf-price-material]'), money(quote.materialCost, symbol));
    setText(query(summary, '[data-srf-price-printer]'), money(quote.printerCost, symbol));
    setText(query(summary, '[data-srf-price-fees]'), money(quote.serviceFee + quote.setupFee + quote.marginAmount + quote.minimumJobAdjustment, symbol));
    setText(query(summary, '[data-srf-price-tax]'), money(quote.tax, symbol));
    setText(query(summary, '[data-srf-price-time]'), formatDuration(quote.totalMinutes));
    setText(query(summary, '[data-srf-price-total]'), quote.fits === false ? '—' : money(quote.total, symbol));
    if (status) {
      status.textContent = this.checkoutEnabled
        ? message('instantEstimateCheckout', 'Instant geometry estimate. The uploaded files are recalculated securely on the server before this amount is placed in checkout.')
        : message('instantEstimateSaved', 'Instant geometry estimate. The server recalculates and stores the final quote when you submit.');
      status.setAttribute('data-type', 'success');
    }
    if (fit) {
      if (quote.fits === true) {
        fit.textContent = interpolate(message('fitsScale', 'Fits the selected build volume at %s%% scale.'), [options.scale]);
        fit.setAttribute('data-fit', 'yes');
      } else if (quote.fits === false) {
        fit.textContent = messages.doesNotFit || 'The model does not fit this printer at the current scale.';
        fit.setAttribute('data-fit', 'no');
      } else {
        fit.textContent = message('buildCheckServer', 'Build-volume check will be completed securely on the server.');
        fit.setAttribute('data-fit', 'unknown');
      }
    }
  };

  ProjectOrderForm.prototype.setSubmitting = function (submitting) {
    var button = query(this.form, '[data-srf-project-submit]');
    var overlay = query(this.form, '[data-srf-submit-overlay]');
    if (button) {
      button.disabled = !!submitting;
      button.setAttribute('aria-busy', submitting ? 'true' : 'false');
      var label = query(button, '[data-srf-submit-label]');
      if (label && submitting) {
        label.setAttribute('data-original-text', label.textContent);
        label.textContent = messages.calculating || 'Preparing the secure quote and checkout…';
      }
    }
    if (overlay) { overlay.hidden = !submitting; }
    this.form.classList.toggle('is-submitting', !!submitting);
  };

  function init() {
    queryAll(document, '[data-srf-project-form]').forEach(function (form) {
      if (form.getAttribute('data-srf-project-initialized') === '1') { return; }
      form.setAttribute('data-srf-project-initialized', '1');
      new ProjectOrderForm(form);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
