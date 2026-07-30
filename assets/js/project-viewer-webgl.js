/* global window, document, ResizeObserver, requestAnimationFrame, cancelAnimationFrame */
(function () {
  'use strict';

  var DEG90 = Math.PI / 2;
  var DEFAULT_COLOR = [0.92, 0.91, 0.87];
  var COLOR_MAP = {
    white: [0.94, 0.93, 0.89],
    grey: [0.56, 0.61, 0.66],
    gray: [0.56, 0.61, 0.66],
    black: [0.075, 0.085, 0.095],
    blue: [0.12, 0.42, 0.76],
    red: [0.78, 0.14, 0.12],
    green: [0.16, 0.55, 0.31]
  };

  function query(root, selector) {
    return root ? root.querySelector(selector) : null;
  }

  function queryAll(root, selector) {
    return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
  }

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  }

  function number(value, fallback) {
    var parsed = Number(value);
    return isFinite(parsed) ? parsed : (typeof fallback === 'number' ? fallback : 0);
  }

  function normalize3(vector) {
    var length = Math.sqrt(vector[0] * vector[0] + vector[1] * vector[1] + vector[2] * vector[2]) || 1;
    return [vector[0] / length, vector[1] / length, vector[2] / length];
  }

  function cross3(a, b) {
    return [
      a[1] * b[2] - a[2] * b[1],
      a[2] * b[0] - a[0] * b[2],
      a[0] * b[1] - a[1] * b[0]
    ];
  }

  function subtract3(a, b) {
    return [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
  }

  function mat4Identity() {
    return new Float32Array([
      1, 0, 0, 0,
      0, 1, 0, 0,
      0, 0, 1, 0,
      0, 0, 0, 1
    ]);
  }

  function mat4Multiply(a, b) {
    var out = new Float32Array(16);
    for (var column = 0; column < 4; column += 1) {
      for (var row = 0; row < 4; row += 1) {
        out[column * 4 + row] =
          a[0 * 4 + row] * b[column * 4 + 0] +
          a[1 * 4 + row] * b[column * 4 + 1] +
          a[2 * 4 + row] * b[column * 4 + 2] +
          a[3 * 4 + row] * b[column * 4 + 3];
      }
    }
    return out;
  }

  function mat4Translation(x, y, z) {
    var out = mat4Identity();
    out[12] = x;
    out[13] = y;
    out[14] = z;
    return out;
  }

  function mat4Scale(scale) {
    var out = mat4Identity();
    out[0] = scale;
    out[5] = scale;
    out[10] = scale;
    return out;
  }

  function mat4RotationX(angle) {
    var cosine = Math.cos(angle);
    var sine = Math.sin(angle);
    return new Float32Array([
      1, 0, 0, 0,
      0, cosine, sine, 0,
      0, -sine, cosine, 0,
      0, 0, 0, 1
    ]);
  }

  function mat4RotationY(angle) {
    var cosine = Math.cos(angle);
    var sine = Math.sin(angle);
    return new Float32Array([
      cosine, 0, -sine, 0,
      0, 1, 0, 0,
      sine, 0, cosine, 0,
      0, 0, 0, 1
    ]);
  }

  function mat4RotationZ(angle) {
    var cosine = Math.cos(angle);
    var sine = Math.sin(angle);
    return new Float32Array([
      cosine, sine, 0, 0,
      -sine, cosine, 0, 0,
      0, 0, 1, 0,
      0, 0, 0, 1
    ]);
  }

  function mat4Perspective(fieldOfView, aspect, near, far) {
    var f = 1 / Math.tan(fieldOfView / 2);
    var inverse = 1 / (near - far);
    var out = new Float32Array(16);
    out[0] = f / aspect;
    out[5] = f;
    out[10] = (far + near) * inverse;
    out[11] = -1;
    out[14] = 2 * far * near * inverse;
    return out;
  }

  function mat4LookAt(eye, target, up) {
    var zAxis = normalize3(subtract3(eye, target));
    var xAxis = normalize3(cross3(up, zAxis));
    var yAxis = cross3(zAxis, xAxis);
    return new Float32Array([
      xAxis[0], yAxis[0], zAxis[0], 0,
      xAxis[1], yAxis[1], zAxis[1], 0,
      xAxis[2], yAxis[2], zAxis[2], 0,
      -(xAxis[0] * eye[0] + xAxis[1] * eye[1] + xAxis[2] * eye[2]),
      -(yAxis[0] * eye[0] + yAxis[1] * eye[1] + yAxis[2] * eye[2]),
      -(zAxis[0] * eye[0] + zAxis[1] * eye[1] + zAxis[2] * eye[2]),
      1
    ]);
  }

  function mat3FromMat4(matrix) {
    return new Float32Array([
      matrix[0], matrix[1], matrix[2],
      matrix[4], matrix[5], matrix[6],
      matrix[8], matrix[9], matrix[10]
    ]);
  }

  function transformPoint(matrix, point) {
    return [
      matrix[0] * point[0] + matrix[4] * point[1] + matrix[8] * point[2] + matrix[12],
      matrix[1] * point[0] + matrix[5] * point[1] + matrix[9] * point[2] + matrix[13],
      matrix[2] * point[0] + matrix[6] * point[1] + matrix[10] * point[2] + matrix[14]
    ];
  }

  function hexToColor(hex) {
    var value = String(hex || '').trim();
    var match = value.match(/^#?([0-9a-f]{6})$/i);
    if (!match) { return null; }
    var integer = parseInt(match[1], 16);
    return [
      ((integer >> 16) & 255) / 255,
      ((integer >> 8) & 255) / 255,
      (integer & 255) / 255
    ];
  }

  function colorFromText(text) {
    var value = String(text || '').toLowerCase();
    var hex = value.match(/#(?:[0-9a-f]{6})\b/i);
    if (hex) { return hexToColor(hex[0]); }
    var terms = [
      { words: ['black', 'schwarz'], color: COLOR_MAP.black },
      { words: ['white', 'weiss', 'weiß', 'ivory', 'elfenbein'], color: COLOR_MAP.white },
      { words: ['grey', 'gray', 'grau', 'silver', 'silber'], color: COLOR_MAP.grey },
      { words: ['blue', 'blau', 'navy'], color: COLOR_MAP.blue },
      { words: ['red', 'rot', 'crimson'], color: COLOR_MAP.red },
      { words: ['green', 'grün', 'gruen'], color: COLOR_MAP.green },
      { words: ['yellow', 'gelb'], color: [0.91, 0.70, 0.12] },
      { words: ['orange'], color: [0.93, 0.38, 0.08] },
      { words: ['purple', 'violet', 'lila', 'violett'], color: [0.48, 0.23, 0.71] },
      { words: ['pink', 'rosa'], color: [0.92, 0.34, 0.58] },
      { words: ['brown', 'braun'], color: [0.42, 0.23, 0.12] },
      { words: ['beige', 'sand'], color: [0.76, 0.67, 0.51] },
      { words: ['transparent', 'clear', 'transparentes', 'klar'], color: [0.70, 0.84, 0.88] }
    ];
    for (var i = 0; i < terms.length; i += 1) {
      for (var j = 0; j < terms[i].words.length; j += 1) {
        if (value.indexOf(terms[i].words[j]) !== -1) {
          return terms[i].color.slice(0);
        }
      }
    }
    return DEFAULT_COLOR.slice(0);
  }

  function createShader(gl, type, source) {
    var shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
      var message = gl.getShaderInfoLog(shader) || 'Shader compilation failed.';
      gl.deleteShader(shader);
      throw new Error(message);
    }
    return shader;
  }

  function createProgram(gl, vertexSource, fragmentSource) {
    var vertex = createShader(gl, gl.VERTEX_SHADER, vertexSource);
    var fragment = createShader(gl, gl.FRAGMENT_SHADER, fragmentSource);
    var program = gl.createProgram();
    gl.attachShader(program, vertex);
    gl.attachShader(program, fragment);
    gl.linkProgram(program);
    gl.deleteShader(vertex);
    gl.deleteShader(fragment);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
      var message = gl.getProgramInfoLog(program) || 'Shader linking failed.';
      gl.deleteProgram(program);
      throw new Error(message);
    }
    return program;
  }

  function createBuffer(gl, values, usage) {
    var buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, values, usage || gl.STATIC_DRAW);
    return buffer;
  }

  function deleteBuffer(gl, buffer) {
    if (buffer) { gl.deleteBuffer(buffer); }
  }

  function computeFlatNormals(positions) {
    var normals = new Float32Array(positions.length);
    for (var offset = 0; offset < positions.length; offset += 9) {
      var ux = positions[offset + 3] - positions[offset];
      var uy = positions[offset + 4] - positions[offset + 1];
      var uz = positions[offset + 5] - positions[offset + 2];
      var vx = positions[offset + 6] - positions[offset];
      var vy = positions[offset + 7] - positions[offset + 1];
      var vz = positions[offset + 8] - positions[offset + 2];
      var nx = uy * vz - uz * vy;
      var ny = uz * vx - ux * vz;
      var nz = ux * vy - uy * vx;
      var length = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
      nx /= length;
      ny /= length;
      nz /= length;
      for (var vertex = 0; vertex < 3; vertex += 1) {
        var normalOffset = offset + vertex * 3;
        normals[normalOffset] = nx;
        normals[normalOffset + 1] = ny;
        normals[normalOffset + 2] = nz;
      }
    }
    return normals;
  }

  function StudioModelRenderer(container, options) {
    if (!container) { throw new Error('Model viewer container is missing.'); }
    this.container = container;
    this.options = options || {};
    this.messages = this.options.messages || {};
    this.canvas = query(container, 'canvas');
    if (!this.canvas) { throw new Error('Model viewer canvas is missing.'); }

    this.gl = this.canvas.getContext('webgl', {
      antialias: true,
      alpha: false,
      depth: true,
      premultipliedAlpha: false,
      preserveDrawingBuffer: false,
      powerPreference: 'high-performance'
    }) || this.canvas.getContext('experimental-webgl');
    if (!this.gl) { throw new Error('WebGL is not available.'); }

    this.positions = null;
    this.flatNormals = null;
    this.smoothNormals = null;
    this.vertexColors = null;
    this.hasEmbeddedColors = false;
    this.center = { x: 0, y: 0, z: 0 };
    this.bounds = { x: 1, y: 1, z: 1 };
    this.limits = { minX: -0.5, minY: -0.5, minZ: -0.5, maxX: 0.5, maxY: 0.5, maxZ: 0.5 };
    this.radius = 1;
    this.scalePercent = 100;
    this.fitState = null;
    this.printer = null;
    this.materialColor = DEFAULT_COLOR.slice(0);
    this.colorMode = 'white';
    this.flatShading = false;
    this.wireframe = false;
    this.showBed = true;
    this.autoOrient = true;
    this.orientation = mat4Identity();
    this.modelMatrix = mat4Identity();
    this.orientedBounds = { minX: -0.5, minY: -0.5, minZ: 0, maxX: 0.5, maxY: 0.5, maxZ: 1 };
    this.yaw = -0.72;
    this.pitch = 0.58;
    this.zoom = 1;
    this.dragging = false;
    this.lastPoint = null;
    this.frame = null;
    this.resizeObserver = null;
    this.buffers = {};
    this.geometry = {};
    this.emptyState = query(container, '[data-srf-viewer-empty]');
    this.hudScale = query(container, '[data-srf-viewer-scale]');
    this.hudBuild = query(container, '[data-srf-viewer-build]');
    this.hudFit = query(container, '[data-srf-viewer-fit]');
    this.embeddedColorButton = query(container, '[data-srf-model-color="embedded"]');

    this.initializePrograms();
    this.initializeState();
    this.bind();
    this.rebuildSceneGeometry();
    this.updateControls();
    this.updateHud();
    this.scheduleDraw();
  }

  StudioModelRenderer.prototype.initializePrograms = function () {
    var gl = this.gl;
    var modelVertex = [
      'attribute vec3 aPosition;',
      'attribute vec3 aFlatNormal;',
      'attribute vec3 aSmoothNormal;',
      'attribute vec3 aColor;',
      'uniform mat4 uProjection;',
      'uniform mat4 uView;',
      'uniform mat4 uModel;',
      'uniform mat3 uNormalMatrix;',
      'uniform float uFlat;',
      'uniform float uUseVertexColor;',
      'uniform vec3 uBaseColor;',
      'varying vec3 vNormal;',
      'varying vec3 vViewPosition;',
      'varying vec3 vColor;',
      'void main(void) {',
      '  vec4 worldPosition = uModel * vec4(aPosition, 1.0);',
      '  vec4 viewPosition = uView * worldPosition;',
      '  vec3 selectedNormal = mix(aSmoothNormal, aFlatNormal, uFlat);',
      '  vNormal = normalize(uNormalMatrix * selectedNormal);',
      '  vViewPosition = viewPosition.xyz;',
      '  vColor = mix(uBaseColor, aColor, uUseVertexColor);',
      '  gl_Position = uProjection * viewPosition;',
      '}'
    ].join('\n');
    var modelFragment = [
      'precision highp float;',
      'uniform float uInvalidFit;',
      'varying vec3 vNormal;',
      'varying vec3 vViewPosition;',
      'varying vec3 vColor;',
      'void main(void) {',
      '  vec3 normal = normalize(vNormal);',
      '  if (!gl_FrontFacing) { normal = -normal; }',
      '  vec3 viewDirection = normalize(-vViewPosition);',
      '  vec3 keyDirection = normalize(vec3(-0.46, 0.58, 0.68));',
      '  vec3 fillDirection = normalize(vec3(0.72, -0.24, 0.42));',
      '  float key = max(dot(normal, keyDirection), 0.0);',
      '  float fill = max(dot(normal, fillDirection), 0.0);',
      '  vec3 halfVector = normalize(keyDirection + viewDirection);',
      '  float specular = pow(max(dot(normal, halfVector), 0.0), 42.0);',
      '  float rim = pow(1.0 - max(dot(normal, viewDirection), 0.0), 2.2);',
      '  vec3 base = mix(vColor, vec3(0.86, 0.12, 0.08), uInvalidFit * 0.72);',
      '  float light = 0.34 + key * 0.63 + fill * 0.20;',
      '  vec3 color = base * light;',
      '  color += vec3(1.0) * specular * 0.34;',
      '  color += mix(vec3(0.16, 0.34, 0.48), vec3(0.40, 0.12, 0.08), uInvalidFit) * rim * 0.22;',
      '  color = pow(max(color, vec3(0.0)), vec3(1.0 / 2.2));',
      '  gl_FragColor = vec4(color, 1.0);',
      '}'
    ].join('\n');
    var colorVertex = [
      'attribute vec3 aPosition;',
      'attribute vec4 aColor;',
      'uniform mat4 uMvp;',
      'varying vec4 vColor;',
      'void main(void) {',
      '  vColor = aColor;',
      '  gl_Position = uMvp * vec4(aPosition, 1.0);',
      '}'
    ].join('\n');
    var colorFragment = [
      'precision mediump float;',
      'varying vec4 vColor;',
      'void main(void) { gl_FragColor = vColor; }'
    ].join('\n');

    this.modelProgram = createProgram(gl, modelVertex, modelFragment);
    this.colorProgram = createProgram(gl, colorVertex, colorFragment);

    this.modelLocations = {
      position: gl.getAttribLocation(this.modelProgram, 'aPosition'),
      flatNormal: gl.getAttribLocation(this.modelProgram, 'aFlatNormal'),
      smoothNormal: gl.getAttribLocation(this.modelProgram, 'aSmoothNormal'),
      color: gl.getAttribLocation(this.modelProgram, 'aColor'),
      projection: gl.getUniformLocation(this.modelProgram, 'uProjection'),
      view: gl.getUniformLocation(this.modelProgram, 'uView'),
      model: gl.getUniformLocation(this.modelProgram, 'uModel'),
      normalMatrix: gl.getUniformLocation(this.modelProgram, 'uNormalMatrix'),
      flat: gl.getUniformLocation(this.modelProgram, 'uFlat'),
      useVertexColor: gl.getUniformLocation(this.modelProgram, 'uUseVertexColor'),
      baseColor: gl.getUniformLocation(this.modelProgram, 'uBaseColor'),
      invalidFit: gl.getUniformLocation(this.modelProgram, 'uInvalidFit')
    };
    this.colorLocations = {
      position: gl.getAttribLocation(this.colorProgram, 'aPosition'),
      color: gl.getAttribLocation(this.colorProgram, 'aColor'),
      mvp: gl.getUniformLocation(this.colorProgram, 'uMvp')
    };
  };

  StudioModelRenderer.prototype.initializeState = function () {
    var gl = this.gl;
    gl.enable(gl.DEPTH_TEST);
    gl.depthFunc(gl.LEQUAL);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
    gl.disable(gl.CULL_FACE);
    gl.clearColor(0.945, 0.958, 0.97, 1);
  };

  StudioModelRenderer.prototype.bind = function () {
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
      self.yaw += deltaX * 0.0085;
      self.pitch = clamp(self.pitch + deltaY * 0.0085, -1.22, 1.48);
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
      self.zoom = clamp(self.zoom * (event.deltaY > 0 ? 0.9 : 1.1), 0.25, 6.5);
      self.updateHud();
      self.scheduleDraw();
    }, { passive: false });

    queryAll(this.container, '[data-srf-view]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.setView(button.getAttribute('data-srf-view'));
      });
    });
    queryAll(this.container, '[data-srf-model-color]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (button.disabled) { return; }
        self.colorMode = button.getAttribute('data-srf-model-color') || 'white';
        self.updateControls();
        self.scheduleDraw();
      });
    });
    queryAll(this.container, '[data-srf-shading]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.flatShading = button.getAttribute('data-srf-shading') === 'flat';
        self.updateControls();
        self.scheduleDraw();
      });
    });
    queryAll(this.container, '[data-srf-viewer-toggle]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = button.getAttribute('data-srf-viewer-toggle');
        if (target === 'wireframe') {
          self.wireframe = !self.wireframe;
          if (self.wireframe) { self.ensureWireframeBuffer(); }
        } else if (target === 'bed') {
          self.showBed = !self.showBed;
        }
        self.updateControls();
        self.scheduleDraw();
      });
    });
    queryAll(this.container, '[data-srf-orient]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.applyOrientationAction(button.getAttribute('data-srf-orient'));
      });
    });

    this.canvas.addEventListener('webglcontextlost', function (event) {
      event.preventDefault();
      self.container.classList.add('is-webgl-lost');
    });
    this.canvas.addEventListener('webglcontextrestored', function () {
      self.container.classList.remove('is-webgl-lost');
      self.buffers = {};
      self.initializePrograms();
      self.initializeState();
      self.uploadModelBuffers();
      self.rebuildSceneGeometry();
      self.scheduleDraw();
    });

    if (typeof ResizeObserver !== 'undefined') {
      this.resizeObserver = new ResizeObserver(function () { self.scheduleDraw(); });
      this.resizeObserver.observe(this.container);
    } else {
      window.addEventListener('resize', function () { self.scheduleDraw(); });
    }
  };

  StudioModelRenderer.prototype.setModel = function (positions, center, radius, details) {
    details = details || {};
    this.positions = positions instanceof Float32Array ? positions : null;
    if (!this.positions || this.positions.length < 9) {
      this.clear();
      return;
    }
    this.center = center || { x: 0, y: 0, z: 0 };
    this.radius = Math.max(0.0001, number(radius, 1));
    this.bounds = details.bounds || {
      x: this.radius * 2,
      y: this.radius * 2,
      z: this.radius * 2
    };
    this.limits = details.limits || {
      minX: this.center.x - this.bounds.x / 2,
      maxX: this.center.x + this.bounds.x / 2,
      minY: this.center.y - this.bounds.y / 2,
      maxY: this.center.y + this.bounds.y / 2,
      minZ: this.center.z - this.bounds.z / 2,
      maxZ: this.center.z + this.bounds.z / 2
    };
    this.flatNormals = details.flatNormals instanceof Float32Array && details.flatNormals.length === this.positions.length
      ? details.flatNormals : computeFlatNormals(this.positions);
    this.smoothNormals = details.smoothNormals instanceof Float32Array && details.smoothNormals.length === this.positions.length
      ? details.smoothNormals : this.flatNormals;
    this.vertexColors = details.colors instanceof Float32Array && details.colors.length === this.positions.length
      ? details.colors : null;
    this.hasEmbeddedColors = !!(details.hasEmbeddedColors && this.vertexColors);
    if (!this.hasEmbeddedColors && this.colorMode === 'embedded') {
      this.colorMode = 'white';
    }
    this.yaw = -0.72;
    this.pitch = 0.58;
    this.zoom = 1;
    this.autoOrient = true;
    this.chooseAutoOrientation();
    this.uploadModelBuffers();
    this.rebuildSceneGeometry();
    this.updateEmptyState();
    this.updateControls();
    this.updateHud();
    this.scheduleDraw();
  };

  StudioModelRenderer.prototype.clear = function () {
    this.positions = null;
    this.flatNormals = null;
    this.smoothNormals = null;
    this.vertexColors = null;
    this.hasEmbeddedColors = false;
    this.releaseModelBuffers();
    this.rebuildSceneGeometry();
    this.updateEmptyState();
    this.updateControls();
    this.updateHud();
    this.scheduleDraw();
  };

  StudioModelRenderer.prototype.releaseModelBuffers = function () {
    var gl = this.gl;
    deleteBuffer(gl, this.buffers.position);
    deleteBuffer(gl, this.buffers.flatNormal);
    deleteBuffer(gl, this.buffers.smoothNormal);
    deleteBuffer(gl, this.buffers.vertexColor);
    deleteBuffer(gl, this.buffers.wireframe);
    deleteBuffer(gl, this.buffers.wireframeColor);
    this.buffers.position = null;
    this.buffers.flatNormal = null;
    this.buffers.smoothNormal = null;
    this.buffers.vertexColor = null;
    this.buffers.wireframe = null;
    this.buffers.wireframeColor = null;
    this.geometry.modelVertexCount = 0;
    this.geometry.wireframeVertexCount = 0;
  };

  StudioModelRenderer.prototype.uploadModelBuffers = function () {
    this.releaseModelBuffers();
    if (!this.positions) { return; }
    var gl = this.gl;
    this.buffers.position = createBuffer(gl, this.positions);
    this.buffers.flatNormal = createBuffer(gl, this.flatNormals);
    this.buffers.smoothNormal = createBuffer(gl, this.smoothNormals);
    if (this.vertexColors) {
      this.buffers.vertexColor = createBuffer(gl, this.vertexColors);
    }
    this.geometry.modelVertexCount = this.positions.length / 3;
    if (this.wireframe) { this.ensureWireframeBuffer(); }
  };

  StudioModelRenderer.prototype.ensureWireframeBuffer = function () {
    if (!this.positions || this.buffers.wireframe) { return; }
    var lines = new Float32Array((this.positions.length / 9) * 18);
    var output = 0;
    for (var offset = 0; offset < this.positions.length; offset += 9) {
      var indices = [0, 1, 2, 3, 4, 5, 3, 4, 5, 6, 7, 8, 6, 7, 8, 0, 1, 2];
      for (var i = 0; i < indices.length; i += 1) {
        lines[output] = this.positions[offset + indices[i]];
        output += 1;
      }
    }
    this.buffers.wireframe = createBuffer(this.gl, lines);
    this.geometry.wireframeVertexCount = lines.length / 3;
    this.buffers.wireframeColor = null;
    this.geometry.wireframeColorFitState = null;
  };

  StudioModelRenderer.prototype.ensureWireframeColorBuffer = function () {
    if (!this.geometry.wireframeVertexCount) { return; }
    var state = this.fitState === false ? 'no' : 'ok';
    if (this.buffers.wireframeColor && this.geometry.wireframeColorFitState === state) { return; }
    deleteBuffer(this.gl, this.buffers.wireframeColor);
    var count = this.geometry.wireframeVertexCount;
    var colors = new Float32Array(count * 4);
    var invalid = state === 'no';
    for (var i = 0; i < count; i += 1) {
      colors[i * 4] = invalid ? 0.56 : 0.04;
      colors[i * 4 + 1] = invalid ? 0.05 : 0.13;
      colors[i * 4 + 2] = invalid ? 0.04 : 0.18;
      colors[i * 4 + 3] = 0.34;
    }
    this.buffers.wireframeColor = createBuffer(this.gl, colors, this.gl.STATIC_DRAW);
    this.geometry.wireframeColorFitState = state;
  };

  StudioModelRenderer.prototype.setSceneOptions = function (options) {
    options = options || {};
    var previousPrinterKey = this.printer ? [this.printer.buildX, this.printer.buildY, this.printer.buildZ].join('|') : '';
    this.printer = options.printer && Math.min(
      number(options.printer.buildX, 0),
      number(options.printer.buildY, 0),
      number(options.printer.buildZ, 0)
    ) > 0 ? {
      name: String(options.printer.name || ''),
      buildX: number(options.printer.buildX, 0),
      buildY: number(options.printer.buildY, 0),
      buildZ: number(options.printer.buildZ, 0)
    } : null;
    this.scalePercent = clamp(number(options.scale, 100), 1, 1000);
    var previousFitState = this.fitState;
    this.fitState = options.fit === true ? true : (options.fit === false ? false : null);
    if (previousFitState !== this.fitState) {
      deleteBuffer(this.gl, this.buffers.wireframeColor);
      this.buffers.wireframeColor = null;
      this.geometry.wireframeColorFitState = null;
    }
    if (Object.prototype.hasOwnProperty.call(options, 'materialColor')) {
      this.materialColor = options.materialColor
        ? (Array.isArray(options.materialColor)
          ? options.materialColor.slice(0, 3)
          : colorFromText(options.materialColor))
        : DEFAULT_COLOR.slice(0);
    }
    var printerKey = this.printer ? [this.printer.buildX, this.printer.buildY, this.printer.buildZ].join('|') : '';
    if (this.autoOrient && (printerKey !== previousPrinterKey || options.forceOrientation)) {
      this.chooseAutoOrientation();
    } else {
      this.updateModelMatrix();
    }
    this.rebuildSceneGeometry();
    this.updateHud();
    this.scheduleDraw();
  };

  StudioModelRenderer.prototype.setView = function (view) {
    if (view === 'front') {
      this.yaw = 0;
      this.pitch = 0.08;
    } else if (view === 'back') {
      this.yaw = Math.PI;
      this.pitch = 0.08;
    } else if (view === 'left') {
      this.yaw = -Math.PI / 2;
      this.pitch = 0.08;
    } else if (view === 'right') {
      this.yaw = Math.PI / 2;
      this.pitch = 0.08;
    } else if (view === 'top') {
      this.yaw = 0;
      this.pitch = 1.50;
    } else if (view === 'bottom') {
      this.yaw = 0;
      this.pitch = -1.18;
    } else if (view === 'fit') {
      this.zoom = 1;
    } else {
      this.yaw = -0.72;
      this.pitch = 0.58;
      this.zoom = 1;
    }
    this.updateHud();
    this.scheduleDraw();
  };

  StudioModelRenderer.prototype.applyOrientationAction = function (action) {
    if (!this.positions) { return; }
    if (action === 'auto' || action === 'reset') {
      this.autoOrient = true;
      this.chooseAutoOrientation();
    } else {
      this.autoOrient = false;
      var rotation = action === 'x' ? mat4RotationX(DEG90)
        : (action === 'y' ? mat4RotationY(DEG90) : mat4RotationZ(DEG90));
      this.orientation = mat4Multiply(rotation, this.orientation);
      this.updateModelMatrix();
    }
    this.rebuildSceneGeometry();
    this.updateControls();
    this.updateHud();
    this.scheduleDraw();
  };

  StudioModelRenderer.prototype.orientationCandidates = function () {
    return [
      mat4Identity(),
      mat4RotationX(DEG90),
      mat4RotationY(DEG90),
      mat4RotationZ(DEG90),
      mat4Multiply(mat4RotationX(DEG90), mat4RotationZ(DEG90)),
      mat4Multiply(mat4RotationY(DEG90), mat4RotationZ(DEG90))
    ];
  };

  StudioModelRenderer.prototype.boundsForOrientation = function (orientation, scale) {
    var halfX = number(this.bounds.x, 1) / 2;
    var halfY = number(this.bounds.y, 1) / 2;
    var halfZ = number(this.bounds.z, 1) / 2;
    var minX = Infinity;
    var minY = Infinity;
    var minZ = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    var maxZ = -Infinity;
    [-1, 1].forEach(function (sx) {
      [-1, 1].forEach(function (sy) {
        [-1, 1].forEach(function (sz) {
          var point = transformPoint(orientation, [sx * halfX * scale, sy * halfY * scale, sz * halfZ * scale]);
          minX = Math.min(minX, point[0]);
          minY = Math.min(minY, point[1]);
          minZ = Math.min(minZ, point[2]);
          maxX = Math.max(maxX, point[0]);
          maxY = Math.max(maxY, point[1]);
          maxZ = Math.max(maxZ, point[2]);
        });
      });
    });
    return { minX: minX, minY: minY, minZ: minZ, maxX: maxX, maxY: maxY, maxZ: maxZ };
  };

  StudioModelRenderer.prototype.chooseAutoOrientation = function () {
    if (!this.positions) {
      this.orientation = mat4Identity();
      this.updateModelMatrix();
      return;
    }
    var scale = this.scalePercent / 100;
    var candidates = this.orientationCandidates();
    var build = this.printer ? [this.printer.buildX, this.printer.buildY, this.printer.buildZ] : null;
    var best = candidates[0];
    var bestScore = Infinity;
    for (var i = 0; i < candidates.length; i += 1) {
      var candidateBounds = this.boundsForOrientation(candidates[i], scale);
      var dimensions = [
        candidateBounds.maxX - candidateBounds.minX,
        candidateBounds.maxY - candidateBounds.minY,
        candidateBounds.maxZ - candidateBounds.minZ
      ];
      var score;
      if (build) {
        var ratios = [dimensions[0] / build[0], dimensions[1] / build[1], dimensions[2] / build[2]];
        var maxRatio = Math.max(ratios[0], ratios[1], ratios[2]);
        var fits = maxRatio <= 1.00001;
        score = (fits ? 0 : 100) + maxRatio * 4 + ratios[2] * 0.25;
      } else {
        score = dimensions[2] * 0.2 + Math.max(dimensions[0], dimensions[1]);
      }
      if (score < bestScore) {
        bestScore = score;
        best = candidates[i];
      }
    }
    this.orientation = best;
    this.updateModelMatrix();
  };

  StudioModelRenderer.prototype.updateModelMatrix = function () {
    if (!this.positions) {
      this.modelMatrix = mat4Identity();
      return;
    }
    var scale = this.scalePercent / 100;
    var oriented = this.boundsForOrientation(this.orientation, scale);
    var offsetX = -(oriented.minX + oriented.maxX) / 2;
    var offsetY = -(oriented.minY + oriented.maxY) / 2;
    var offsetZ = -oriented.minZ + 0.08;
    this.orientedBounds = {
      minX: oriented.minX + offsetX,
      maxX: oriented.maxX + offsetX,
      minY: oriented.minY + offsetY,
      maxY: oriented.maxY + offsetY,
      minZ: oriented.minZ + offsetZ,
      maxZ: oriented.maxZ + offsetZ
    };
    var translateToOrigin = mat4Translation(-this.center.x, -this.center.y, -this.center.z);
    var scaled = mat4Scale(scale);
    var model = mat4Multiply(this.orientation, mat4Multiply(scaled, translateToOrigin));
    this.modelMatrix = mat4Multiply(mat4Translation(offsetX, offsetY, offsetZ), model);
  };

  StudioModelRenderer.prototype.currentPlate = function () {
    if (this.printer) {
      return { x: this.printer.buildX, y: this.printer.buildY, z: this.printer.buildZ, named: true };
    }
    var modelX = this.positions ? this.orientedBounds.maxX - this.orientedBounds.minX : 120;
    var modelY = this.positions ? this.orientedBounds.maxY - this.orientedBounds.minY : 120;
    return {
      x: clamp(Math.max(120, modelX * 1.65), 120, 420),
      y: clamp(Math.max(120, modelY * 1.65), 120, 420),
      z: 0,
      named: false
    };
  };

  StudioModelRenderer.prototype.rebuildSceneGeometry = function () {
    var gl = this.gl;
    ['bedPosition', 'bedColor', 'linePosition', 'lineColor', 'shadowPosition', 'shadowColor'].forEach(function (name) {
      deleteBuffer(gl, this.buffers[name]);
      this.buffers[name] = null;
    }, this);

    var plate = this.currentPlate();
    var halfX = plate.x / 2;
    var halfY = plate.y / 2;
    var bedZ = -0.08;
    var bedPositions = new Float32Array([
      -halfX, -halfY, bedZ, halfX, -halfY, bedZ, halfX, halfY, bedZ,
      -halfX, -halfY, bedZ, halfX, halfY, bedZ, -halfX, halfY, bedZ
    ]);
    var bedColors = new Float32Array(6 * 4);
    for (var i = 0; i < 6; i += 1) {
      bedColors[i * 4] = 0.90;
      bedColors[i * 4 + 1] = 0.92;
      bedColors[i * 4 + 2] = 0.94;
      bedColors[i * 4 + 3] = 0.98;
    }
    this.buffers.bedPosition = createBuffer(gl, bedPositions);
    this.buffers.bedColor = createBuffer(gl, bedColors);
    this.geometry.bedVertexCount = 6;

    var linePositions = [];
    var lineColors = [];
    function pushLine(ax, ay, az, bx, by, bz, color) {
      linePositions.push(ax, ay, az, bx, by, bz);
      lineColors.push(
        color[0], color[1], color[2], color[3],
        color[0], color[1], color[2], color[3]
      );
    }
    var maximumPlate = Math.max(plate.x, plate.y);
    var step = maximumPlate <= 160 ? 10 : (maximumPlate <= 360 ? 20 : 50);
    var majorEvery = 5;
    var lineIndex = 0;
    for (var x = -Math.floor(halfX / step) * step; x <= halfX + 0.001; x += step) {
      var xMajor = lineIndex % majorEvery === 0;
      pushLine(x, -halfY, 0, x, halfY, 0, xMajor ? [0.34, 0.39, 0.45, 0.32] : [0.43, 0.48, 0.54, 0.18]);
      lineIndex += 1;
    }
    lineIndex = 0;
    for (var y = -Math.floor(halfY / step) * step; y <= halfY + 0.001; y += step) {
      var yMajor = lineIndex % majorEvery === 0;
      pushLine(-halfX, y, 0, halfX, y, 0, yMajor ? [0.34, 0.39, 0.45, 0.32] : [0.43, 0.48, 0.54, 0.18]);
      lineIndex += 1;
    }
    pushLine(-halfX, 0, 0.02, halfX, 0, 0.02, [0.78, 0.18, 0.16, 0.60]);
    pushLine(0, -halfY, 0.02, 0, halfY, 0.02, [0.12, 0.55, 0.30, 0.60]);

    if (plate.z > 0) {
      var cageColor = this.fitState === false ? [0.89, 0.18, 0.12, 0.55] : [0.22, 0.47, 0.62, 0.28];
      var corners = [
        [-halfX, -halfY], [halfX, -halfY], [halfX, halfY], [-halfX, halfY]
      ];
      for (i = 0; i < 4; i += 1) {
        var next = (i + 1) % 4;
        pushLine(corners[i][0], corners[i][1], plate.z, corners[next][0], corners[next][1], plate.z, cageColor);
        pushLine(corners[i][0], corners[i][1], 0, corners[i][0], corners[i][1], plate.z, cageColor);
      }
    }

    var linePositionArray = new Float32Array(linePositions);
    var lineColorArray = new Float32Array(lineColors);
    this.buffers.linePosition = createBuffer(gl, linePositionArray);
    this.buffers.lineColor = createBuffer(gl, lineColorArray);
    this.geometry.lineVertexCount = linePositionArray.length / 3;

    var footprintX = this.positions ? Math.max(8, this.orientedBounds.maxX - this.orientedBounds.minX) : 40;
    var footprintY = this.positions ? Math.max(8, this.orientedBounds.maxY - this.orientedBounds.minY) : 40;
    footprintX = Math.min(plate.x * 0.88, footprintX * 1.18);
    footprintY = Math.min(plate.y * 0.88, footprintY * 1.18);
    var segments = 48;
    var shadowPositions = [];
    var shadowColors = [];
    shadowPositions.push(0, 0, -0.025);
    shadowColors.push(0.05, 0.08, 0.11, this.positions ? 0.17 : 0);
    for (i = 0; i <= segments; i += 1) {
      var angle = i / segments * Math.PI * 2;
      shadowPositions.push(Math.cos(angle) * footprintX / 2, Math.sin(angle) * footprintY / 2, -0.025);
      shadowColors.push(0.05, 0.08, 0.11, 0);
    }
    var shadowPositionArray = new Float32Array(shadowPositions);
    var shadowColorArray = new Float32Array(shadowColors);
    this.buffers.shadowPosition = createBuffer(gl, shadowPositionArray);
    this.buffers.shadowColor = createBuffer(gl, shadowColorArray);
    this.geometry.shadowVertexCount = shadowPositionArray.length / 3;
  };

  StudioModelRenderer.prototype.updateEmptyState = function () {
    if (this.emptyState) {
      this.emptyState.hidden = !!this.positions;
    }
    this.container.classList.toggle('has-model', !!this.positions);
  };

  StudioModelRenderer.prototype.updateControls = function () {
    var self = this;
    queryAll(this.container, '[data-srf-model-color]').forEach(function (button) {
      var mode = button.getAttribute('data-srf-model-color');
      if (mode === 'embedded') {
        button.disabled = !self.hasEmbeddedColors;
        button.hidden = !self.hasEmbeddedColors;
      }
      var active = mode === self.colorMode;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    queryAll(this.container, '[data-srf-shading]').forEach(function (button) {
      var active = (button.getAttribute('data-srf-shading') === 'flat') === self.flatShading;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    queryAll(this.container, '[data-srf-viewer-toggle]').forEach(function (button) {
      var target = button.getAttribute('data-srf-viewer-toggle');
      var active = target === 'wireframe' ? self.wireframe : self.showBed;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    queryAll(this.container, '[data-srf-orient="auto"]').forEach(function (button) {
      button.classList.toggle('is-active', self.autoOrient);
      button.setAttribute('aria-pressed', self.autoOrient ? 'true' : 'false');
    });
  };

  StudioModelRenderer.prototype.updateHud = function () {
    var plate = this.currentPlate();
    if (this.hudScale) {
      this.hudScale.textContent = (this.messages.viewerScale || 'Scale') + ' ' + Math.round(this.scalePercent) + '%';
    }
    if (this.hudBuild) {
      this.hudBuild.textContent = plate.z > 0
        ? Math.round(plate.x) + ' × ' + Math.round(plate.y) + ' × ' + Math.round(plate.z) + ' mm'
        : (this.messages.previewBed || 'Preview bed');
    }
    if (this.hudFit) {
      if (!this.printer || !this.positions) {
        this.hudFit.textContent = this.messages.viewerFitUnknown || 'Select a printer for build-volume guidance';
        this.hudFit.setAttribute('data-fit', 'unknown');
      } else if (this.fitState === false) {
        this.hudFit.textContent = this.messages.viewerDoesNotFit || 'Does not fit the selected build volume';
        this.hudFit.setAttribute('data-fit', 'no');
      } else if (this.fitState === true) {
        this.hudFit.textContent = this.messages.viewerFits || 'Fits the selected build volume';
        this.hudFit.setAttribute('data-fit', 'yes');
      } else {
        this.hudFit.textContent = this.messages.viewerFitUnknown || 'Build-volume check pending';
        this.hudFit.setAttribute('data-fit', 'unknown');
      }
    }
    this.container.classList.toggle('is-fit-error', this.fitState === false);
  };

  StudioModelRenderer.prototype.resize = function () {
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
    this.gl.viewport(0, 0, pixelWidth, pixelHeight);
    return { width: width, height: height, ratio: ratio };
  };

  StudioModelRenderer.prototype.camera = function (size) {
    var plate = this.currentPlate();
    var modelX = this.positions ? this.orientedBounds.maxX - this.orientedBounds.minX : 80;
    var modelY = this.positions ? this.orientedBounds.maxY - this.orientedBounds.minY : 80;
    var modelZ = this.positions ? this.orientedBounds.maxZ - this.orientedBounds.minZ : 80;
    var modelRadius = Math.max(12, Math.sqrt(modelX * modelX + modelY * modelY + modelZ * modelZ) / 2);
    var bedRadius = Math.sqrt(plate.x * plate.x + plate.y * plate.y) / 2;
    var sceneRadius = Math.max(modelRadius * 1.22, Math.min(bedRadius * 0.72, modelRadius * 2.35));
    var fieldOfView = 40 * Math.PI / 180;
    var distance = sceneRadius / Math.tan(fieldOfView / 2) * 1.12 / this.zoom;
    var target = [0, 0, Math.max(4, modelZ * 0.36)];
    var horizontal = Math.cos(this.pitch) * distance;
    var eye = [
      Math.sin(this.yaw) * horizontal,
      -Math.cos(this.yaw) * horizontal,
      target[2] + Math.sin(this.pitch) * distance
    ];
    var up = Math.abs(this.pitch) > 1.42 ? [0, 1, 0] : [0, 0, 1];
    var near = Math.max(0.1, distance / 500);
    var far = Math.max(2000, distance + Math.max(plate.z, sceneRadius) * 8);
    return {
      projection: mat4Perspective(fieldOfView, size.width / Math.max(1, size.height), near, far),
      view: mat4LookAt(eye, target, up)
    };
  };

  StudioModelRenderer.prototype.scheduleDraw = function () {
    var self = this;
    if (this.frame) { cancelAnimationFrame(this.frame); }
    this.frame = requestAnimationFrame(function () {
      self.frame = null;
      self.draw();
    });
  };

  StudioModelRenderer.prototype.bindColorGeometry = function (positionBuffer, colorBuffer) {
    var gl = this.gl;
    var locations = this.colorLocations;
    gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
    gl.enableVertexAttribArray(locations.position);
    gl.vertexAttribPointer(locations.position, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
    gl.enableVertexAttribArray(locations.color);
    gl.vertexAttribPointer(locations.color, 4, gl.FLOAT, false, 0, 0);
  };

  StudioModelRenderer.prototype.drawColorGeometry = function (positionBuffer, colorBuffer, count, mode, mvp) {
    if (!positionBuffer || !colorBuffer || !count) { return; }
    var gl = this.gl;
    gl.useProgram(this.colorProgram);
    this.bindColorGeometry(positionBuffer, colorBuffer);
    gl.uniformMatrix4fv(this.colorLocations.mvp, false, mvp);
    gl.drawArrays(mode, 0, count);
  };

  StudioModelRenderer.prototype.selectedColor = function () {
    if (this.colorMode === 'filament') { return this.materialColor || DEFAULT_COLOR; }
    if (COLOR_MAP[this.colorMode]) { return COLOR_MAP[this.colorMode]; }
    return COLOR_MAP.white;
  };

  StudioModelRenderer.prototype.drawModel = function (projection, view) {
    if (!this.positions || !this.buffers.position) { return; }
    var gl = this.gl;
    var locations = this.modelLocations;
    gl.useProgram(this.modelProgram);

    gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.position);
    gl.enableVertexAttribArray(locations.position);
    gl.vertexAttribPointer(locations.position, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.flatNormal);
    gl.enableVertexAttribArray(locations.flatNormal);
    gl.vertexAttribPointer(locations.flatNormal, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.smoothNormal);
    gl.enableVertexAttribArray(locations.smoothNormal);
    gl.vertexAttribPointer(locations.smoothNormal, 3, gl.FLOAT, false, 0, 0);

    if (this.buffers.vertexColor) {
      gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.vertexColor);
      gl.enableVertexAttribArray(locations.color);
      gl.vertexAttribPointer(locations.color, 3, gl.FLOAT, false, 0, 0);
    } else {
      gl.disableVertexAttribArray(locations.color);
      gl.vertexAttrib3f(locations.color, 1, 1, 1);
    }

    var viewOrientation = mat4Multiply(view, this.orientation);
    var baseColor = this.selectedColor();
    gl.uniformMatrix4fv(locations.projection, false, projection);
    gl.uniformMatrix4fv(locations.view, false, view);
    gl.uniformMatrix4fv(locations.model, false, this.modelMatrix);
    gl.uniformMatrix3fv(locations.normalMatrix, false, mat3FromMat4(viewOrientation));
    gl.uniform1f(locations.flat, this.flatShading ? 1 : 0);
    gl.uniform1f(locations.useVertexColor, this.colorMode === 'embedded' && this.hasEmbeddedColors ? 1 : 0);
    gl.uniform3fv(locations.baseColor, new Float32Array(baseColor));
    gl.uniform1f(locations.invalidFit, this.fitState === false ? 1 : 0);

    gl.enable(gl.POLYGON_OFFSET_FILL);
    gl.polygonOffset(1, 1);
    gl.drawArrays(gl.TRIANGLES, 0, this.geometry.modelVertexCount);
    gl.disable(gl.POLYGON_OFFSET_FILL);
  };

  StudioModelRenderer.prototype.drawWireframe = function (projection, view) {
    if (!this.wireframe || !this.positions) { return; }
    this.ensureWireframeBuffer();
    if (!this.buffers.wireframe) { return; }
    var gl = this.gl;
    var count = this.geometry.wireframeVertexCount;
    this.ensureWireframeColorBuffer();
    var mvp = mat4Multiply(projection, mat4Multiply(view, this.modelMatrix));
    this.drawColorGeometry(this.buffers.wireframe, this.buffers.wireframeColor, count, gl.LINES, mvp);
  };

  StudioModelRenderer.prototype.draw = function () {
    var gl = this.gl;
    var size = this.resize();
    var camera = this.camera(size);
    var worldMvp = mat4Multiply(camera.projection, camera.view);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
    gl.enable(gl.DEPTH_TEST);
    gl.depthMask(true);

    if (this.showBed) {
      this.drawColorGeometry(this.buffers.bedPosition, this.buffers.bedColor, this.geometry.bedVertexCount, gl.TRIANGLES, worldMvp);
      gl.depthMask(false);
      this.drawColorGeometry(this.buffers.shadowPosition, this.buffers.shadowColor, this.geometry.shadowVertexCount, gl.TRIANGLE_FAN, worldMvp);
      this.drawColorGeometry(this.buffers.linePosition, this.buffers.lineColor, this.geometry.lineVertexCount, gl.LINES, worldMvp);
      gl.depthMask(true);
    }

    this.drawModel(camera.projection, camera.view);
    gl.depthMask(false);
    this.drawWireframe(camera.projection, camera.view);
    gl.depthMask(true);
  };

  window.SRFStudioModelRenderer = StudioModelRenderer;
  window.SRFStudioColorFromText = colorFromText;
}());
