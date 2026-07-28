/* global self, TextDecoder */
(function () {
  'use strict';

  var DEFAULT_PREVIEW_TRIANGLES = 160000;
  var MAX_OBJ_VERTICES = 2000000;
  var MAX_OBJ_TRIANGLES = 12000000;

  function finiteNumber(value) {
    return typeof value === 'number' && isFinite(value);
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

  function createCollector(maxTriangles) {
    var sample = [];
    var seen = 0;
    var max = Math.max(100, Math.min(20000, Number(maxTriangles) || DEFAULT_PREVIEW_TRIANGLES));

    return {
      add: function (values) {
        seen += 1;
        if (sample.length < max) {
          sample.push(values);
          return;
        }
        var replacement = Math.floor(Math.random() * seen);
        if (replacement < max) {
          sample[replacement] = values;
        }
      },
      toFloat32Array: function () {
        var output = new Float32Array(sample.length * 9);
        var offset = 0;
        for (var i = 0; i < sample.length; i += 1) {
          var triangle = sample[i];
          for (var j = 0; j < 9; j += 1) {
            output[offset] = triangle[j];
            offset += 1;
          }
        }
        return output;
      },
      count: function () {
        return sample.length;
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
    var previewPositions = collector.toFloat32Array();

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
      previewPositions: previewPositions
    };
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
        collector.add([ax, ay, az, bx, by, bz, cx, cy, cz]);
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
        collector.add(vertices.slice(0));
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

  function parseObj(buffer, maxTriangles) {
    var text = new TextDecoder('utf-8').decode(buffer);
    var lines = text.split(/\r?\n/);
    var vertices = [];
    var faces = [];
    var i;

    for (i = 0; i < lines.length; i += 1) {
      var line = lines[i].trim();
      if (!line || line.charAt(0) === '#') {
        continue;
      }
      var parts = line.split(/\s+/);
      if (parts[0] === 'v' && parts.length >= 4) {
        var vx = Number(parts[1]);
        var vy = Number(parts[2]);
        var vz = Number(parts[3]);
        if (finiteNumber(vx) && finiteNumber(vy) && finiteNumber(vz)) {
          vertices.push([vx, vy, vz]);
          if (vertices.length > MAX_OBJ_VERTICES) {
            throw new Error('This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.');
          }
        }
      } else if (parts[0] === 'f' && parts.length >= 4) {
        faces.push(parts.slice(1));
      }
    }

    if (vertices.length < 3 || faces.length < 1) {
      throw new Error('The OBJ file does not contain readable vertices and faces.');
    }

    var bounds = createBounds();
    var collector = createCollector(maxTriangles);
    var volume = 0;
    var area = 0;
    var triangleCount = 0;

    for (i = 0; i < faces.length; i += 1) {
      var face = faces[i];
      var firstIndex = parseObjIndex(face[0], vertices.length);
      if (firstIndex < 0 || !vertices[firstIndex]) {
        continue;
      }
      for (var j = 1; j < face.length - 1; j += 1) {
        var secondIndex = parseObjIndex(face[j], vertices.length);
        var thirdIndex = parseObjIndex(face[j + 1], vertices.length);
        if (secondIndex < 0 || thirdIndex < 0 || !vertices[secondIndex] || !vertices[thirdIndex]) {
          continue;
        }

        var a = vertices[firstIndex];
        var b = vertices[secondIndex];
        var c = vertices[thirdIndex];
        expandBounds(bounds, a[0], a[1], a[2]);
        expandBounds(bounds, b[0], b[1], b[2]);
        expandBounds(bounds, c[0], c[1], c[2]);
        volume += signedVolume(a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]);
        area += triangleArea(a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]);
        collector.add([a[0], a[1], a[2], b[0], b[1], b[2], c[0], c[1], c[2]]);
        triangleCount += 1;
        if (triangleCount > MAX_OBJ_TRIANGLES) {
          throw new Error('This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.');
        }
      }
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
      self.postMessage(result, [result.previewPositions.buffer]);
    } catch (error) {
      self.postMessage({
        id: id,
        ok: false,
        message: error && error.message ? String(error.message) : 'The model could not be analysed in the browser.'
      });
    }
  };
}());
