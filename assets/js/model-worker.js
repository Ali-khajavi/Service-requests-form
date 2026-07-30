/* global self, TextDecoder */
(function () {
  'use strict';

  var DEFAULT_PREVIEW_TRIANGLES = 160000;
  var MAX_PREVIEW_TRIANGLES = 160000;
  var MAX_OBJ_VERTICES = 2000000;
  var MAX_OBJ_TRIANGLES = 12000000;
  var EXACT_SMOOTH_TRIANGLE_LIMIT = 90000;

  function finiteNumber(value) {
    return typeof value === 'number' && isFinite(value);
  }

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
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

  function expandBounds(bounds, x, y, z) {
    if (x < bounds.minX) { bounds.minX = x; }
    if (y < bounds.minY) { bounds.minY = y; }
    if (z < bounds.minZ) { bounds.minZ = z; }
    if (x > bounds.maxX) { bounds.maxX = x; }
    if (y > bounds.maxY) { bounds.maxY = y; }
    if (z > bounds.maxZ) { bounds.maxZ = z; }
  }

  function signedVolume(ax, ay, az, bx, by, bz, cx, cy, cz) {
    return (
      ax * (by * cz - bz * cy) +
      ay * (bz * cx - bx * cz) +
      az * (bx * cy - by * cx)
    ) / 6;
  }

  function triangleArea(ax, ay, az, bx, by, bz, cx, cy, cz) {
    var ux = bx - ax;
    var uy = by - ay;
    var uz = bz - az;
    var vx = cx - ax;
    var vy = cy - ay;
    var vz = cz - az;
    var crossX = uy * vz - uz * vy;
    var crossY = uz * vx - ux * vz;
    var crossZ = ux * vy - uy * vx;
    return 0.5 * Math.sqrt(crossX * crossX + crossY * crossY + crossZ * crossZ);
  }

  function normalizeColorComponent(value) {
    var number = Number(value);
    if (!finiteNumber(number)) { return null; }
    if (number > 1) { number /= 255; }
    return clamp(number, 0, 1);
  }

  function normalizedColor(red, green, blue) {
    red = normalizeColorComponent(red);
    green = normalizeColorComponent(green);
    blue = normalizeColorComponent(blue);
    if (red === null || green === null || blue === null) { return null; }
    return [red, green, blue];
  }

  function faceNormal(positions, offset) {
    var ax = positions[offset];
    var ay = positions[offset + 1];
    var az = positions[offset + 2];
    var bx = positions[offset + 3];
    var by = positions[offset + 4];
    var bz = positions[offset + 5];
    var cx = positions[offset + 6];
    var cy = positions[offset + 7];
    var cz = positions[offset + 8];
    var ux = bx - ax;
    var uy = by - ay;
    var uz = bz - az;
    var vx = cx - ax;
    var vy = cy - ay;
    var vz = cz - az;
    var nx = uy * vz - uz * vy;
    var ny = uz * vx - ux * vz;
    var nz = ux * vy - uy * vx;
    var length = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
    return [nx / length, ny / length, nz / length];
  }

  function vertexKey(x, y, z) {
    // The preview is visual only. Five decimal places preserve normal sharing for
    // millimetre-scale meshes while avoiding tiny exporter rounding differences.
    return Math.round(x * 100000) + '|' + Math.round(y * 100000) + '|' + Math.round(z * 100000);
  }

  function computeNormals(positions, center, triangleCount) {
    var flat = new Float32Array(positions.length);
    var smooth = new Float32Array(positions.length);
    var offset;
    var vertex;

    for (offset = 0; offset < positions.length; offset += 9) {
      var normal = faceNormal(positions, offset);
      for (vertex = 0; vertex < 3; vertex += 1) {
        var normalOffset = offset + vertex * 3;
        flat[normalOffset] = normal[0];
        flat[normalOffset + 1] = normal[1];
        flat[normalOffset + 2] = normal[2];
      }
    }

    if (triangleCount <= EXACT_SMOOTH_TRIANGLE_LIMIT) {
      var sums = new Map();
      for (offset = 0; offset < positions.length; offset += 9) {
        var face = [flat[offset], flat[offset + 1], flat[offset + 2]];
        for (vertex = 0; vertex < 3; vertex += 1) {
          var positionOffset = offset + vertex * 3;
          var key = vertexKey(positions[positionOffset], positions[positionOffset + 1], positions[positionOffset + 2]);
          var sum = sums.get(key);
          if (sum) {
            sum[0] += face[0];
            sum[1] += face[1];
            sum[2] += face[2];
          } else {
            sums.set(key, [face[0], face[1], face[2]]);
          }
        }
      }
      for (offset = 0; offset < positions.length; offset += 3) {
        var smoothKey = vertexKey(positions[offset], positions[offset + 1], positions[offset + 2]);
        var accumulated = sums.get(smoothKey) || [flat[offset], flat[offset + 1], flat[offset + 2]];
        var accumulatedLength = Math.sqrt(
          accumulated[0] * accumulated[0] +
          accumulated[1] * accumulated[1] +
          accumulated[2] * accumulated[2]
        ) || 1;
        smooth[offset] = accumulated[0] / accumulatedLength;
        smooth[offset + 1] = accumulated[1] / accumulatedLength;
        smooth[offset + 2] = accumulated[2] / accumulatedLength;
      }
      sums.clear();
    } else {
      // For exceptionally complex previews use a bounded-memory blend of the
      // exact face normal and a radial normal. It keeps organic models smooth
      // without building a very large JavaScript adjacency map in the worker.
      for (offset = 0; offset < positions.length; offset += 3) {
        var rx = positions[offset] - center.x;
        var ry = positions[offset + 1] - center.y;
        var rz = positions[offset + 2] - center.z;
        var radialLength = Math.sqrt(rx * rx + ry * ry + rz * rz) || 1;
        rx /= radialLength;
        ry /= radialLength;
        rz /= radialLength;
        var nx = flat[offset] * 0.72 + rx * 0.28;
        var ny = flat[offset + 1] * 0.72 + ry * 0.28;
        var nz = flat[offset + 2] * 0.72 + rz * 0.28;
        var blendedLength = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
        smooth[offset] = nx / blendedLength;
        smooth[offset + 1] = ny / blendedLength;
        smooth[offset + 2] = nz / blendedLength;
      }
    }

    return { flat: flat, smooth: smooth };
  }

  function createCollector(maxTriangles) {
    var seen = 0;
    var count = 0;
    var hasColors = false;
    var max = Math.max(100, Math.min(MAX_PREVIEW_TRIANGLES, Number(maxTriangles) || DEFAULT_PREVIEW_TRIANGLES));
    var positionStore = new Float32Array(max * 9);
    var colorStore = null;

    function writeTriangle(target, values, colors) {
      var offset = target * 9;
      for (var i = 0; i < 9; i += 1) {
        positionStore[offset + i] = values[i];
      }

      if (colors) {
        if (!colorStore) {
          colorStore = new Float32Array(max * 9);
          colorStore.fill(1);
        }
        for (i = 0; i < 9; i += 1) {
          colorStore[offset + i] = colors[i];
        }
        hasColors = true;
      } else if (colorStore) {
        for (i = 0; i < 9; i += 1) {
          colorStore[offset + i] = 1;
        }
      }
    }

    return {
      add: function (values, colors) {
        seen += 1;
        var target;
        if (count < max) {
          target = count;
          count += 1;
        } else {
          target = Math.floor(Math.random() * seen);
          if (target >= max) {
            return;
          }
        }
        writeTriangle(target, values, colors || null);
      },
      toArrays: function (center) {
        var length = count * 9;
        var positions = positionStore.slice(0, length);
        var colors = hasColors && colorStore ? colorStore.slice(0, length) : new Float32Array(0);
        var normals = computeNormals(positions, center, count);
        positionStore = new Float32Array(0);
        colorStore = null;
        return {
          positions: positions,
          flatNormals: normals.flat,
          smoothNormals: normals.smooth,
          colors: colors,
          hasColors: hasColors
        };
      },
      count: function () {
        return count;
      }
    };
  }

  function resultFromMetrics(format, bounds, volume, area, triangleCount, collector) {
    if (!triangleCount || !finiteNumber(volume) || !finiteNumber(area) || !isFinite(bounds.minX)) {
      throw new Error('The model does not contain readable triangles.');
    }

    var dimensions = {
      x: Math.max(0, bounds.maxX - bounds.minX),
      y: Math.max(0, bounds.maxY - bounds.minY),
      z: Math.max(0, bounds.maxZ - bounds.minZ)
    };
    var center = {
      x: (bounds.minX + bounds.maxX) / 2,
      y: (bounds.minY + bounds.maxY) / 2,
      z: (bounds.minZ + bounds.maxZ) / 2
    };
    var radius = Math.max(dimensions.x, dimensions.y, dimensions.z) / 2;
    var preview = collector.toArrays(center);

    return {
      format: format,
      triangleCount: triangleCount,
      previewTriangleCount: collector.count(),
      volumeMm3: Math.abs(volume),
      volumeCm3: Math.abs(volume) / 1000,
      surfaceAreaMm2: Math.max(0, area),
      bounds: dimensions,
      limits: bounds,
      center: center,
      radius: radius > 0 ? radius : 1,
      hasEmbeddedColors: preview.hasColors,
      previewPositions: preview.positions,
      previewFlatNormals: preview.flatNormals,
      previewSmoothNormals: preview.smoothNormals,
      previewColors: preview.colors
    };
  }

  function decodeStlHeaderColor(view) {
    var marker = [67, 79, 76, 79, 82, 61]; // COLOR=
    for (var i = 0; i <= 80 - marker.length - 4; i += 1) {
      var found = true;
      for (var j = 0; j < marker.length; j += 1) {
        if (view.getUint8(i + j) !== marker[j]) {
          found = false;
          break;
        }
      }
      if (found) {
        return [
          view.getUint8(i + marker.length) / 255,
          view.getUint8(i + marker.length + 1) / 255,
          view.getUint8(i + marker.length + 2) / 255
        ];
      }
    }
    return null;
  }

  function decodeStlFacetColor(attribute, headerColor) {
    if (headerColor) {
      // Materialise Magics: bit 15 means use the object colour. Otherwise the
      // low five bits are red, then green, then blue.
      if ((attribute & 0x8000) !== 0) {
        return headerColor;
      }
      if ((attribute & 0x7fff) !== 0) {
        return [
          (attribute & 0x1f) / 31,
          ((attribute >> 5) & 0x1f) / 31,
          ((attribute >> 10) & 0x1f) / 31
        ];
      }
      return null;
    }

    // VisCAM/SolidView: bit 15 marks a valid per-facet colour and stores BGR.
    if ((attribute & 0x8000) !== 0) {
      return [
        ((attribute >> 10) & 0x1f) / 31,
        ((attribute >> 5) & 0x1f) / 31,
        (attribute & 0x1f) / 31
      ];
    }
    return null;
  }

  function repeatTriangleColor(color) {
    if (!color) { return null; }
    return [
      color[0], color[1], color[2],
      color[0], color[1], color[2],
      color[0], color[1], color[2]
    ];
  }

  function parseBinaryStl(buffer, maxTriangles) {
    var view = new DataView(buffer);
    if (view.byteLength < 84) {
      throw new Error('The STL file is too small.');
    }

    var faceCount = view.getUint32(80, true);
    var requiredBytes = 84 + faceCount * 50;
    if (!faceCount || requiredBytes > view.byteLength) {
      throw new Error('The binary STL structure is invalid.');
    }

    var bounds = createBounds();
    var collector = createCollector(maxTriangles);
    var headerColor = decodeStlHeaderColor(view);
    var volume = 0;
    var area = 0;
    var offset = 84;

    for (var face = 0; face < faceCount; face += 1) {
      var ax = view.getFloat32(offset + 12, true);
      var ay = view.getFloat32(offset + 16, true);
      var az = view.getFloat32(offset + 20, true);
      var bx = view.getFloat32(offset + 24, true);
      var by = view.getFloat32(offset + 28, true);
      var bz = view.getFloat32(offset + 32, true);
      var cx = view.getFloat32(offset + 36, true);
      var cy = view.getFloat32(offset + 40, true);
      var cz = view.getFloat32(offset + 44, true);

      if (
        finiteNumber(ax) && finiteNumber(ay) && finiteNumber(az) &&
        finiteNumber(bx) && finiteNumber(by) && finiteNumber(bz) &&
        finiteNumber(cx) && finiteNumber(cy) && finiteNumber(cz)
      ) {
        expandBounds(bounds, ax, ay, az);
        expandBounds(bounds, bx, by, bz);
        expandBounds(bounds, cx, cy, cz);
        volume += signedVolume(ax, ay, az, bx, by, bz, cx, cy, cz);
        area += triangleArea(ax, ay, az, bx, by, bz, cx, cy, cz);
        var attribute = view.getUint16(offset + 48, true);
        var facetColor = decodeStlFacetColor(attribute, headerColor);
        collector.add(
          [ax, ay, az, bx, by, bz, cx, cy, cz],
          repeatTriangleColor(facetColor)
        );
      }
      offset += 50;
    }

    return resultFromMetrics('stl', bounds, volume, area, faceCount, collector);
  }

  function parseAsciiStl(buffer, maxTriangles) {
    var text = new TextDecoder('utf-8').decode(buffer);
    var vertexPattern = /^\s*vertex\s+([-+0-9.eE]+)\s+([-+0-9.eE]+)\s+([-+0-9.eE]+)/gmi;
    var match;
    var vertices = [];
    var bounds = createBounds();
    var collector = createCollector(maxTriangles);
    var volume = 0;
    var area = 0;
    var triangleCount = 0;

    while ((match = vertexPattern.exec(text)) !== null) {
      var x = Number(match[1]);
      var y = Number(match[2]);
      var z = Number(match[3]);
      if (!finiteNumber(x) || !finiteNumber(y) || !finiteNumber(z)) {
        continue;
      }
      vertices.push(x, y, z);
      if (vertices.length === 9) {
        expandBounds(bounds, vertices[0], vertices[1], vertices[2]);
        expandBounds(bounds, vertices[3], vertices[4], vertices[5]);
        expandBounds(bounds, vertices[6], vertices[7], vertices[8]);
        volume += signedVolume(
          vertices[0], vertices[1], vertices[2],
          vertices[3], vertices[4], vertices[5],
          vertices[6], vertices[7], vertices[8]
        );
        area += triangleArea(
          vertices[0], vertices[1], vertices[2],
          vertices[3], vertices[4], vertices[5],
          vertices[6], vertices[7], vertices[8]
        );
        collector.add(vertices.slice(0), null);
        triangleCount += 1;
        vertices.length = 0;
      }
    }

    return resultFromMetrics('stl', bounds, volume, area, triangleCount, collector);
  }

  function looksLikeBinaryStl(buffer) {
    if (buffer.byteLength < 84) {
      return false;
    }
    var view = new DataView(buffer);
    var count = view.getUint32(80, true);
    return count > 0 && 84 + count * 50 <= buffer.byteLength;
  }

  function parseStl(buffer, maxTriangles) {
    return looksLikeBinaryStl(buffer) ? parseBinaryStl(buffer, maxTriangles) : parseAsciiStl(buffer, maxTriangles);
  }

  function parseObjIndex(token, length) {
    var part = String(token || '').split('/')[0];
    var index = parseInt(part, 10);
    if (!index) {
      return -1;
    }
    return index < 0 ? length + index : index - 1;
  }

  function vertexAt(vertices, index) {
    var offset = index * 3;
    return [vertices[offset], vertices[offset + 1], vertices[offset + 2]];
  }

  function colorAt(colors, index) {
    var offset = index * 3;
    if (colors[offset] < 0 || colors[offset + 1] < 0 || colors[offset + 2] < 0) {
      return null;
    }
    return [colors[offset], colors[offset + 1], colors[offset + 2]];
  }

  function triangleVertexColors(a, b, c) {
    if (!a && !b && !c) { return null; }
    a = a || [1, 1, 1];
    b = b || [1, 1, 1];
    c = c || [1, 1, 1];
    return [a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]];
  }

  function forEachTextLine(text, callback) {
    var start = 0;
    while (start <= text.length) {
      var end = text.indexOf('\n', start);
      if (end === -1) { end = text.length; }
      var line = text.slice(start, end);
      if (line.charAt(line.length - 1) === '\r') {
        line = line.slice(0, -1);
      }
      callback(line);
      if (end >= text.length) { break; }
      start = end + 1;
    }
  }

  function parseObj(buffer, maxTriangles) {
    var text = new TextDecoder('utf-8').decode(buffer).replace(/^\uFEFF/, '');
    var vertices = [];
    var vertexColors = [];
    var bounds = createBounds();
    var collector = createCollector(maxTriangles);
    var volume = 0;
    var area = 0;
    var triangleCount = 0;

    forEachTextLine(text, function (sourceLine) {
      var line = sourceLine.trim();
      if (!line || line.charAt(0) === '#') {
        return;
      }
      var parts = line.split(/\s+/);
      if (parts[0] === 'v' && parts.length >= 4) {
        var vx = Number(parts[1]);
        var vy = Number(parts[2]);
        var vz = Number(parts[3]);
        if (finiteNumber(vx) && finiteNumber(vy) && finiteNumber(vz)) {
          vertices.push(vx, vy, vz);
          // Non-standard OBJ vertex colours are commonly written as
          // `v x y z r g b`. When the optional OBJ weight is also present,
          // exporters use `v x y z w r g b`; read the RGB triplet after it.
          var colorOffset = parts.length >= 8 ? 5 : 4;
          var vertexColor = parts.length >= 7
            ? normalizedColor(parts[colorOffset], parts[colorOffset + 1], parts[colorOffset + 2])
            : null;
          if (vertexColor) {
            vertexColors.push(vertexColor[0], vertexColor[1], vertexColor[2]);
          } else {
            vertexColors.push(-1, -1, -1);
          }
          if (vertices.length / 3 > MAX_OBJ_VERTICES) {
            throw new Error('This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.');
          }
        }
        return;
      }

      if (parts[0] !== 'f' || parts.length < 4) {
        return;
      }

      var vertexCount = vertices.length / 3;
      var face = parts.slice(1);
      var firstIndex = parseObjIndex(face[0], vertexCount);
      if (firstIndex < 0 || firstIndex >= vertexCount) {
        return;
      }
      for (var j = 1; j < face.length - 1; j += 1) {
        var secondIndex = parseObjIndex(face[j], vertexCount);
        var thirdIndex = parseObjIndex(face[j + 1], vertexCount);
        if (
          secondIndex < 0 || thirdIndex < 0 ||
          secondIndex >= vertexCount || thirdIndex >= vertexCount
        ) {
          continue;
        }

        var a = vertexAt(vertices, firstIndex);
        var b = vertexAt(vertices, secondIndex);
        var c = vertexAt(vertices, thirdIndex);
        expandBounds(bounds, a[0], a[1], a[2]);
        expandBounds(bounds, b[0], b[1], b[2]);
        expandBounds(bounds, c[0], c[1], c[2]);
        volume += signedVolume(a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]);
        area += triangleArea(a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]);
        collector.add(
          [a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]],
          triangleVertexColors(
            colorAt(vertexColors, firstIndex),
            colorAt(vertexColors, secondIndex),
            colorAt(vertexColors, thirdIndex)
          )
        );
        triangleCount += 1;
        if (triangleCount > MAX_OBJ_TRIANGLES) {
          throw new Error('This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.');
        }
      }
    });

    if (vertices.length / 3 < 3 || triangleCount < 1) {
      throw new Error('The OBJ file does not contain readable vertices and faces.');
    }

    return resultFromMetrics('obj', bounds, volume, area, triangleCount, collector);
  }

  self.onmessage = function (event) {
    var data = event && event.data ? event.data : {};
    var id = data.id;
    var extension = String(data.extension || '').toLowerCase();

    try {
      if (!(data.buffer instanceof ArrayBuffer)) {
        throw new Error('No model data was supplied.');
      }

      var result;
      if (extension === 'stl') {
        result = parseStl(data.buffer, data.maxPreviewTriangles);
      } else if (extension === 'obj') {
        result = parseObj(data.buffer, data.maxPreviewTriangles);
      } else {
        throw new Error('Instant preview supports STL and OBJ. This model will be analysed securely on the server.');
      }

      result.id = id;
      result.ok = true;
      var transfers = [
        result.previewPositions.buffer,
        result.previewFlatNormals.buffer,
        result.previewSmoothNormals.buffer
      ];
      if (result.previewColors && result.previewColors.length) {
        transfers.push(result.previewColors.buffer);
      }
      self.postMessage(result, transfers);
    } catch (error) {
      self.postMessage({
        id: id,
        ok: false,
        message: error && error.message ? String(error.message) : 'The model could not be analysed in the browser.'
      });
    }
  };
}());
