(function () {
    'use strict';

    const VIEWER_SELECTOR = '.tpq-viewer-panel';
    const DEG = Math.PI / 180;

    class TPQViewer {
        constructor(root) {
            this.root = root;
            this.canvas = root.querySelector('.tpq-viewer-render');
            this.placeholder = root.querySelector('.tpq-viewer-placeholder');
            this.status = root.querySelector('.tpq-viewer-status');
            this.stats = {
                filename: root.querySelector('[data-field="filename"]'),
                format: root.querySelector('[data-field="format"]'),
                triangles: root.querySelector('[data-field="triangles"]'),
                bounds: root.querySelector('[data-field="bounds"]'),
            };

            this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
            this.pixelRatio = Math.max(1, window.devicePixelRatio || 1);
            this.mesh = null;
            this.resizeObserver = null;
            this.state = {
                yaw: -25 * DEG,
                pitch: -18 * DEG,
                zoom: 1.85,
                panX: 0,
                panY: 0,
                isDragging: false,
                dragMode: 'rotate',
                lastX: 0,
                lastY: 0,
            };

            if (!this.root || !this.canvas || !this.ctx) {
                return;
            }

            this.bindEvents();
            this.resize(true);
            this.drawEmpty();
            this.updateStatus((window.tpq && tpq.messages && tpq.messages.viewerReady) || 'Viewer ready. Upload an STL or OBJ file to preview it.');
        }

        bindEvents() {
            this.handleResize = () => {
                if (this.resize()) {
                    this.render();
                }
            };

            window.addEventListener('resize', this.handleResize);

            const resizeTarget = this.canvas.parentElement || this.root;
            if ('ResizeObserver' in window && resizeTarget) {
                this.resizeObserver = new ResizeObserver(() => {
                    if (this.resize()) {
                        this.render();
                    }
                });
                this.resizeObserver.observe(resizeTarget);
            }

            this.canvas.addEventListener('pointerdown', (event) => {
                this.state.isDragging = true;
                this.state.dragMode = event.shiftKey ? 'pan' : 'rotate';
                this.state.lastX = event.clientX;
                this.state.lastY = event.clientY;
                this.canvas.setPointerCapture(event.pointerId);
            });

            this.canvas.addEventListener('pointermove', (event) => {
                if (!this.state.isDragging) {
                    return;
                }

                const deltaX = event.clientX - this.state.lastX;
                const deltaY = event.clientY - this.state.lastY;
                this.state.lastX = event.clientX;
                this.state.lastY = event.clientY;

                if (this.state.dragMode === 'pan') {
                    this.state.panX += deltaX * 0.004;
                    this.state.panY -= deltaY * 0.004;
                } else {
                    this.state.yaw += deltaX * 0.012;
                    this.state.pitch += deltaY * 0.012;
                    this.state.pitch = Math.max(-Math.PI / 2.2, Math.min(Math.PI / 2.2, this.state.pitch));
                }

                this.render();
            });

            const releasePointer = (event) => {
                this.state.isDragging = false;
                if (event && this.canvas.hasPointerCapture && this.canvas.hasPointerCapture(event.pointerId)) {
                    this.canvas.releasePointerCapture(event.pointerId);
                }
            };

            this.canvas.addEventListener('pointerup', releasePointer);
            this.canvas.addEventListener('pointercancel', releasePointer);
            this.canvas.addEventListener('pointerleave', () => {
                this.state.isDragging = false;
            });

            this.canvas.addEventListener('wheel', (event) => {
                event.preventDefault();
                this.applyZoom(event.deltaY > 0 ? -0.12 : 0.12);
            }, { passive: false });

            this.root.querySelectorAll('[data-action]').forEach((button) => {
                button.addEventListener('click', () => {
                    const action = button.getAttribute('data-action');
                    if (action === 'reset-view') {
                        this.resetCamera();
                    } else if (action === 'fit-view') {
                        this.fitView();
                    } else if (action === 'zoom-in') {
                        this.applyZoom(-0.16);
                    } else if (action === 'zoom-out') {
                        this.applyZoom(0.16);
                    } else if (action && action.indexOf('view-') === 0) {
                        this.setPresetView(action.replace('view-', ''));
                    }
                });
            });
        }

        resize(force) {
            if (!this.canvas || !this.ctx) {
                return false;
            }

            const host = this.canvas.parentElement || this.root;
            const hostRect = host ? host.getBoundingClientRect() : null;
            const canvasRect = this.canvas.getBoundingClientRect();

            const width = Math.max(
                320,
                Math.round(
                    (hostRect && hostRect.width) ||
                    canvasRect.width ||
                    this.canvas.clientWidth ||
                    640
                )
            );

            const height = Math.max(
                280,
                Math.round(
                    canvasRect.height ||
                    this.canvas.clientHeight ||
                    (hostRect && hostRect.height) ||
                    420
                )
            );

            const pixelWidth = Math.round(width * this.pixelRatio);
            const pixelHeight = Math.round(height * this.pixelRatio);

            if (!force &&
                this.canvas.width === pixelWidth &&
                this.canvas.height === pixelHeight) {
                return false;
            }

            this.canvas.width = pixelWidth;
            this.canvas.height = pixelHeight;
            this.canvas.style.width = width + 'px';
            this.canvas.style.height = height + 'px';
            this.ctx.setTransform(this.pixelRatio, 0, 0, this.pixelRatio, 0, 0);

            return true;
        }

        resetCamera() {
            this.state.yaw = -25 * DEG;
            this.state.pitch = -18 * DEG;
            this.state.zoom = 1.85;
            this.state.panX = 0;
            this.state.panY = 0;
            this.render();
        }

        fitView() {
            this.state.panX = 0;
            this.state.panY = 0;
            this.state.zoom = this.mesh && this.mesh.radius > 0 ? 1.95 : 1.85;
            this.render();
        }

        applyZoom(delta) {
            const factor = delta > 0 ? 1.1 : 0.9;
            const intensity = 1 + Math.abs(delta);

            this.state.zoom = this.state.zoom * Math.pow(factor, intensity);

            if (this.state.zoom < 0.05) this.state.zoom = 0.05;
            if (this.state.zoom > 100) this.state.zoom = 100;

            this.render();
        }

        setPresetView(view) {
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
        }

        drawEmpty() {
            const width = this.canvas.clientWidth || 640;
            const height = this.canvas.clientHeight || 420;
            this.ctx.clearRect(0, 0, width, height);
            this.drawBackground(width, height);
            this.drawGrid(width, height);
            this.drawAxes(width, height);
        }

        updatePlaceholder(message, isVisible) {
            if (!this.placeholder) {
                return;
            }
            this.placeholder.textContent = message || '';
            this.placeholder.hidden = !isVisible;
        }

        updateStatus(message, type) {
            if (!this.status) {
                return;
            }
            this.status.textContent = message || '';
            this.status.dataset.state = type || 'info';
        }

        updateStats(data) {
            if (!this.stats.filename) {
                return;
            }
            this.stats.filename.textContent = data.filename || '—';
            this.stats.format.textContent = data.format || '—';
            this.stats.triangles.textContent = data.triangles ? this.formatNumber(data.triangles) : '—';
            this.stats.bounds.textContent = data.bounds || '—';
        }

        formatNumber(value) {
            return new Intl.NumberFormat().format(value);
        }

        async loadModel(model) {
            if (!model || !model.url) {
                this.mesh = null;
                this.updateStats({});
                this.drawEmpty();
                this.updatePlaceholder((window.tpq && tpq.messages && tpq.messages.viewerNoModel) || 'No model loaded yet.', true);
                return;
            }

            const extension = this.detectExtension(model);
            if (!['stl', 'obj'].includes(extension)) {
                this.mesh = null;
                this.drawEmpty();
                this.updateStats({
                    filename: model.name || '—',
                    format: extension.toUpperCase(),
                    triangles: 0,
                    bounds: '—',
                });
                this.updatePlaceholder('', false);
                this.updateStatus(
                    (window.tpq && tpq.messages && tpq.messages.viewerUnsupportedFormat) || 'Preview is currently available for STL and OBJ files. 3MF is next.',
                    'warning'
                );
                return;
            }

            this.updatePlaceholder('', false);
            this.updateStatus((window.tpq && tpq.messages && tpq.messages.viewerLoading) || 'Loading 3D preview…', 'loading');

            try {
                const response = await fetch(model.url, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                let mesh;
                if (extension === 'stl') {
                    const buffer = await response.arrayBuffer();
                    mesh = parseSTL(buffer);
                    mesh.format = 'STL';
                } else {
                    const text = await response.text();
                    mesh = parseOBJ(text);
                    mesh.format = 'OBJ';
                }

                mesh.filename = model.name || 'Model';
                this.mesh = mesh;
                this.resetCamera();
                this.fitView();
                this.updateStats({
                    filename: mesh.filename,
                    format: mesh.format,
                    triangles: mesh.triangleCount,
                    bounds: formatBounds(mesh.bounds),
                });
                this.updateStatus((window.tpq && tpq.messages && tpq.messages.viewerLoaded) || '3D preview ready. Drag to rotate, use Shift+drag to pan, and use the wheel or zoom buttons.', 'success');
                this.render();
            } catch (error) {
                this.mesh = null;
                this.drawEmpty();
                this.updateStats({
                    filename: model.name || '—',
                    format: extension.toUpperCase(),
                    triangles: 0,
                    bounds: '—',
                });
                this.updateStatus(
                    (window.tpq && tpq.messages && tpq.messages.viewerLoadFailed) || 'The viewer could not load this model.',
                    'error'
                );
                this.updatePlaceholder((error && error.message) ? error.message : 'Preview failed.', true);
            }
        }

        detectExtension(model) {
            const raw = model.name || model.url || '';
            const clean = raw.split('?')[0].split('#')[0];
            const parts = clean.split('.');
            return parts.length > 1 ? parts.pop().toLowerCase() : '';
        }

        render() {
            const width = this.canvas.clientWidth || 640;
            const height = this.canvas.clientHeight || 420;
            this.ctx.clearRect(0, 0, width, height);
            this.drawBackground(width, height);
            this.drawGrid(width, height);
            this.drawAxes(width, height);

            if (!this.mesh || !this.mesh.triangles.length) {
                return;
            }

            const projected = [];
            const cosY = Math.cos(this.state.yaw);
            const sinY = Math.sin(this.state.yaw);
            const cosX = Math.cos(this.state.pitch);
            const sinX = Math.sin(this.state.pitch);
            const cx = width / 2 + this.state.panX * width * 0.6;
            const cy = height / 2 - this.state.panY * height * 0.6;
            const baseScale = Math.min(width, height) * 0.31 * this.state.zoom;
            const distance = 4.2;
            const light = normalizeVector({ x: -0.35, y: -0.45, z: 1 });

            for (let i = 0; i < this.mesh.triangles.length; i += 1) {
                const tri = this.mesh.triangles[i];
                const vertices = tri.vertices.map((vertex) => {
                    const centered = {
                        x: (vertex.x - this.mesh.center.x) / this.mesh.radius,
                        y: (vertex.y - this.mesh.center.y) / this.mesh.radius,
                        z: (vertex.z - this.mesh.center.z) / this.mesh.radius,
                    };

                    const yawed = {
                        x: centered.x * cosY + centered.z * sinY,
                        y: centered.y,
                        z: -centered.x * sinY + centered.z * cosY,
                    };

                    const pitched = {
                        x: yawed.x,
                        y: yawed.y * cosX - yawed.z * sinX,
                        z: yawed.y * sinX + yawed.z * cosX,
                    };

                    const perspective = baseScale / (distance - pitched.z);
                    return {
                        x: cx + pitched.x * perspective,
                        y: cy - pitched.y * perspective,
                        z: pitched.z,
                    };
                });

                const v1 = subtract(vertices[1], vertices[0]);
                const v2 = subtract(vertices[2], vertices[0]);
                const normal = normalizeVector(cross(v1, v2));
                if (normal.z <= 0) {
                    continue;
                }

                const shade = Math.max(0.18, dot(normal, light));
                const depth = (vertices[0].z + vertices[1].z + vertices[2].z) / 3;
                projected.push({ vertices, shade, depth });
            }

            projected.sort((a, b) => a.depth - b.depth);

            projected.forEach((tri) => {
                this.ctx.beginPath();
                this.ctx.moveTo(tri.vertices[0].x, tri.vertices[0].y);
                this.ctx.lineTo(tri.vertices[1].x, tri.vertices[1].y);
                this.ctx.lineTo(tri.vertices[2].x, tri.vertices[2].y);
                this.ctx.closePath();

                const fill = Math.round(80 + tri.shade * 135);
                const edge = Math.round(55 + tri.shade * 110);
                this.ctx.fillStyle = 'rgb(' + fill + ',' + (fill + 12) + ',' + Math.min(255, fill + 30) + ')';
                this.ctx.strokeStyle = 'rgba(' + edge + ',' + edge + ',' + Math.min(255, edge + 20) + ',0.38)';
                this.ctx.lineWidth = 0.9;
                this.ctx.fill();
                this.ctx.stroke();
            });
        }

        drawBackground(width, height) {
            const gradient = this.ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, '#101b34');
            gradient.addColorStop(1, '#050915');
            this.ctx.fillStyle = gradient;
            this.ctx.fillRect(0, 0, width, height);
        }

        drawGrid(width, height) {
            this.ctx.save();
            this.ctx.strokeStyle = 'rgba(255,255,255,0.06)';
            this.ctx.lineWidth = 1;
            const step = Math.max(26, Math.round(width / 16));
            for (let x = 0; x <= width; x += step) {
                this.ctx.beginPath();
                this.ctx.moveTo(x, 0);
                this.ctx.lineTo(x, height);
                this.ctx.stroke();
            }
            for (let y = 0; y <= height; y += step) {
                this.ctx.beginPath();
                this.ctx.moveTo(0, y);
                this.ctx.lineTo(width, y);
                this.ctx.stroke();
            }
            this.ctx.restore();
        }

        drawAxes(width, height) {
            const originX = 56;
            const originY = height - 46;
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
        }
    }

    function subtract(a, b) {
        return { x: a.x - b.x, y: a.y - b.y, z: a.z - b.z };
    }

    function cross(a, b) {
        return {
            x: a.y * b.z - a.z * b.y,
            y: a.z * b.x - a.x * b.z,
            z: a.x * b.y - a.y * b.x,
        };
    }

    function dot(a, b) {
        return a.x * b.x + a.y * b.y + a.z * b.z;
    }

    function normalizeVector(vector) {
        const length = Math.sqrt(vector.x * vector.x + vector.y * vector.y + vector.z * vector.z) || 1;
        return {
            x: vector.x / length,
            y: vector.y / length,
            z: vector.z / length,
        };
    }

    function formatBounds(bounds) {
        if (!bounds) {
            return '—';
        }
        return [bounds.x, bounds.y, bounds.z].map((value) => value.toFixed(2)).join(' × ');
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

        const view = new DataView(buffer);
        const faces = view.getUint32(80, true);
        const expectedLength = 84 + (faces * 50);
        if (expectedLength === buffer.byteLength) {
            return true;
        }

        const header = new TextDecoder().decode(buffer.slice(0, 80)).trim().toLowerCase();
        return header.indexOf('solid') !== 0;
    }

    function parseBinarySTL(buffer) {
        const view = new DataView(buffer);
        const faces = view.getUint32(80, true);
        let offset = 84;
        const triangles = [];
        const bounds = createBounds();

        for (let i = 0; i < faces; i += 1) {
            offset += 12;
            const vertices = [];
            for (let j = 0; j < 3; j += 1) {
                const vertex = {
                    x: view.getFloat32(offset, true),
                    y: view.getFloat32(offset + 4, true),
                    z: view.getFloat32(offset + 8, true),
                };
                vertices.push(vertex);
                expandBounds(bounds, vertex);
                offset += 12;
            }
            triangles.push({ vertices });
            offset += 2;
        }

        return buildMesh(triangles, bounds);
    }

    function parseASCIISTL(text) {
        const pattern = /vertex\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)/ig;
        const vertices = [];
        let match;
        while ((match = pattern.exec(text)) !== null) {
            vertices.push({
                x: parseFloat(match[1]),
                y: parseFloat(match[2]),
                z: parseFloat(match[3]),
            });
        }

        if (!vertices.length || vertices.length % 3 !== 0) {
            throw new Error('This STL file could not be parsed.');
        }

        const bounds = createBounds();
        const triangles = [];
        for (let i = 0; i < vertices.length; i += 3) {
            const triVertices = [vertices[i], vertices[i + 1], vertices[i + 2]];
            triVertices.forEach((vertex) => expandBounds(bounds, vertex));
            triangles.push({ vertices: triVertices });
        }

        return buildMesh(triangles, bounds);
    }

    function parseOBJ(text) {
        const lines = text.split(/\r?\n/);
        const sourceVertices = [];
        const bounds = createBounds();
        const triangles = [];

        for (let i = 0; i < lines.length; i += 1) {
            const line = lines[i].trim();
            if (!line || line.charAt(0) === '#') {
                continue;
            }

            const parts = line.split(/\s+/);
            if (parts[0] === 'v' && parts.length >= 4) {
                const vertex = {
                    x: parseFloat(parts[1]),
                    y: parseFloat(parts[2]),
                    z: parseFloat(parts[3]),
                };
                if ([vertex.x, vertex.y, vertex.z].some((value) => Number.isNaN(value))) {
                    continue;
                }
                sourceVertices.push(vertex);
                expandBounds(bounds, vertex);
            } else if (parts[0] === 'f' && parts.length >= 4) {
                const faceIndexes = parts
                    .slice(1)
                    .map((token) => parseOBJVertexIndex(token, sourceVertices.length))
                    .filter((index) => index >= 0);

                if (faceIndexes.length < 3) {
                    continue;
                }

                for (let j = 1; j < faceIndexes.length - 1; j += 1) {
                    const a = sourceVertices[faceIndexes[0]];
                    const b = sourceVertices[faceIndexes[j]];
                    const c = sourceVertices[faceIndexes[j + 1]];
                    if (!a || !b || !c) {
                        continue;
                    }
                    triangles.push({
                        vertices: [
                            { x: a.x, y: a.y, z: a.z },
                            { x: b.x, y: b.y, z: b.z },
                            { x: c.x, y: c.y, z: c.z },
                        ],
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
        const raw = String(token).split('/')[0];
        const index = parseInt(raw, 10);
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
            maxZ: -Infinity,
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

    function buildMesh(triangles, rawBounds) {
        const bounds = {
            x: rawBounds.maxX - rawBounds.minX,
            y: rawBounds.maxY - rawBounds.minY,
            z: rawBounds.maxZ - rawBounds.minZ,
        };
        const center = {
            x: (rawBounds.minX + rawBounds.maxX) / 2,
            y: (rawBounds.minY + rawBounds.maxY) / 2,
            z: (rawBounds.minZ + rawBounds.maxZ) / 2,
        };
        const radius = Math.max(bounds.x, bounds.y, bounds.z) || 1;

        return {
            triangles,
            triangleCount: triangles.length,
            center,
            radius,
            bounds,
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector(VIEWER_SELECTOR);
        if (!root) {
            return;
        }

        const viewer = new TPQViewer(root);
        window.tpqViewer = viewer;

        document.addEventListener('tpqModelUploaded', function (event) {
            viewer.loadModel(event.detail || {});
        });
    });
})();