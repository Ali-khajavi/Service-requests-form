(function () {
	'use strict';

	var config = window.srfCloudUploader || {};
	if (!config.restUrl || !config.nonce || !config.useDirectUploads) {
		return;
	}

	var activeAbortController = null;

	function json(url, options) {
		return fetch(url, options).then(function (response) {
			return response.text().then(function (text) {
				var data = null;
				if (text) {
					try { data = JSON.parse(text); } catch (e) { data = null; }
				}
				if (!response.ok) {
					var message = data && data.message ? data.message : ((config.messages && config.messages.failed) || 'Upload failed.');
					throw new Error(message);
				}
				return data;
			});
		});
	}

	function restUrl(path) {
		return String(config.restUrl).replace(/\/$/, '') + path;
	}

	function getFormType(form) {
		return form.getAttribute('data-srf-form-type') || config.formType || 'service';
	}

	function getFileInput(form) {
		return form.querySelector('input[type="file"][name="srf_files[]"]');
	}

	function getBatchIdInput(form) { return form.querySelector('[data-srf-upload-batch-id]'); }
	function getBatchTokenInput(form) { return form.querySelector('[data-srf-upload-batch-token]'); }

	function setStatus(form, message, error) {
		var node = form.querySelector('[data-srf-cloud-upload-status]');
		if (!node) {
			node = document.createElement('div');
			node.setAttribute('data-srf-cloud-upload-status', '1');
			node.style.marginTop = '8px';
			node.style.fontSize = '13px';
			var input = getFileInput(form);
			(input && input.parentNode ? input.parentNode : form).appendChild(node);
		}
		node.textContent = message || '';
		node.style.color = error ? '#b32d2e' : '#1f6f43';
	}

	function getChunkSize(session) {
		var size = (session && session.chunkSize) ? parseInt(session.chunkSize, 10) : 10485760;
		size = Math.max(327680, Math.min(size, 60 * 1024 * 1024));
		var remainder = size % 327680;
		if (remainder) size -= remainder;
		return Math.max(327680, size);
	}

	function alignChunkSize(size, remaining) {
		var max = Math.min(size, 60 * 1024 * 1024, remaining);
		if (remaining <= size) {
			return remaining;
		}
		var aligned = max - (max % 327680);
		return Math.max(327680, aligned);
	}

	function xhrPut(url, blob, headers, onProgress, signal) {
		return new Promise(function (resolve, reject) {
			var xhr = new XMLHttpRequest();
			xhr.open('PUT', url, true);
			Object.keys(headers || {}).forEach(function (key) { xhr.setRequestHeader(key, headers[key]); });
			xhr.timeout = 600000;
			xhr.upload.onprogress = function (event) {
				if (event.lengthComputable && onProgress) onProgress(event.loaded, event.total);
			};
			xhr.onload = function () {
				resolve({ status: xhr.status, text: xhr.responseText || '', headers: xhr.getAllResponseHeaders() });
			};
			xhr.onerror = function () { reject(new Error('network')); };
			xhr.onabort = function () { reject(new Error('aborted')); };
			xhr.ontimeout = function () { reject(new Error('timeout')); };
			if (signal) {
				if (signal.aborted) {
					xhr.abort();
					return;
				}
				signal.addEventListener('abort', function () { xhr.abort(); }, { once: true });
			}
			xhr.send(blob);
		});
	}

	async function uploadChunked(file, session, onProgress, signal) {
		var offset = 0;
		var attempts = 0;
		var chunkSize = getChunkSize(session);
		while (offset < file.size) {
			if (signal && signal.aborted) throw new Error('aborted');
			var remaining = file.size - offset;
			var currentSize = alignChunkSize(chunkSize, remaining);
			var end = offset + currentSize - 1;
			var blob = file.slice(offset, offset + currentSize);
			try {
				var response = await xhrPut(session.uploadUrl, blob, {
					'Content-Type': file.type || 'application/octet-stream',
					'Content-Range': 'bytes ' + offset + '-' + end + '/' + file.size
				}, function (loaded) {
					if (onProgress) onProgress(Math.min(file.size, offset + loaded), file.size);
				}, signal);
				if (response.status === 202) {
					var payload = null;
					try { payload = JSON.parse(response.text || '{}'); } catch (e) { payload = {}; }
					if (payload && Array.isArray(payload.nextExpectedRanges) && payload.nextExpectedRanges.length) {
						var range = String(payload.nextExpectedRanges[0]);
						var nextStart = parseInt(range.split('-')[0], 10);
						if (!isNaN(nextStart)) offset = nextStart;
						else offset += currentSize;
					} else {
						offset += currentSize;
					}
					attempts = 0;
					continue;
				}
				if (response.status === 200 || response.status === 201) {
					if (onProgress) onProgress(file.size, file.size);
					try { return response.text ? JSON.parse(response.text || '{}') : {}; } catch (e) { return {}; }
				}
				if (response.status === 429 || response.status >= 500) {
					throw new Error('retry');
				}
				throw new Error('upload');
			} catch (error) {
				if (error && error.message === 'aborted') throw error;
				attempts += 1;
				if (attempts > 5) throw error;
				await new Promise(function (resolve) { setTimeout(resolve, Math.min(1000 * attempts * attempts, 8000)); });
			}
		}
	}

	async function createBatch(form, files) {
		var payload = {
			form_type: getFormType(form),
			files: files.map(function (file, index) {
				return { index: index, name: file.name, size: file.size, type: file.type || '' };
			})
		};
		return json(restUrl('/upload-batches'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-SRF-Nonce': config.nonce },
			body: JSON.stringify(payload),
			credentials: 'same-origin'
		});
	}

	async function createSession(batch, file, index) {
		return json(restUrl('/upload-batches/' + encodeURIComponent(batch.batch_id) + '/files'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-SRF-Nonce': config.nonce },
			body: JSON.stringify({ batch_token: batch.batch_token, index: index, name: file.name, size: file.size, type: file.type || '' }),
			credentials: 'same-origin'
		});
	}

	async function cleanupBatch(batch) {
		if (!batch || !batch.batch_id) return;
		try {
			await fetch(restUrl('/upload-batches/' + encodeURIComponent(batch.batch_id)), {
				method: 'DELETE',
				headers: { 'X-SRF-Nonce': config.nonce },
				credentials: 'same-origin'
			});
		} catch (e) {}
	}

	async function uploadFiles(form, files) {
		var batch = await createBatch(form, files);
		getBatchIdInput(form).value = String(batch.batch_id || '');
		getBatchTokenInput(form).value = String(batch.batch_token || '');
		for (var i = 0; i < files.length; i++) {
			var session = await createSession(batch, files[i], i);
			setStatus(form, 'Uploading ' + files[i].name + '...', false);
			await uploadChunked(files[i], session, function (loaded, total) {
				setStatus(form, 'Uploading ' + files[i].name + ' ' + Math.round((loaded / total) * 100) + '%', false);
			}, activeAbortController ? activeAbortController.signal : null);
		}
		setStatus(form, (config.messages && config.messages.ready) || 'Files uploaded securely.', false);
		return batch;
	}

	function initForm(form) {
		var input = getFileInput(form);
		if (!input) return;
		input.disabled = false;

		form.addEventListener('submit', async function (event) {
			if (form.__srfDirectUploadActive) return;
			var files = Array.prototype.slice.call(input.files || []);
			if (!files.length) return;
			event.preventDefault();
			form.__srfDirectUploadActive = true;
			activeAbortController = new AbortController();
			try {
				await uploadFiles(form, files);
				input.required = false;
				input.value = '';
				form.submit();
			} catch (error) {
				await cleanupBatch({ batch_id: getBatchIdInput(form).value });
				setStatus(form, error && error.message ? error.message : ((config.messages && config.messages.failed) || 'Upload failed.'), true);
				form.__srfDirectUploadActive = false;
				activeAbortController = null;
			}
		});
	}

	window.srfCloudUploaderCancel = function () {
		if (activeAbortController) {
			activeAbortController.abort();
		}
	};

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) {
			if (forms[i].querySelector('input[type="file"][name="srf_files[]"]')) {
				initForm(forms[i]);
			}
		}
	});

	return;

	function text(key, fallback) {
		return (config.messages && config.messages[key]) ? String(config.messages[key]) : String(fallback || '');
	}

	function getFileInput(form) {
		return form.querySelector('input[type="file"][name="srf_files[]"]');
	}

	function getBatchIdInput(form) {
		return form.querySelector('[data-srf-upload-batch-id]');
	}

	function getBatchTokenInput(form) {
		return form.querySelector('[data-srf-upload-batch-token]');
	}

	function getStatusNode(form) {
		var node = form.querySelector('[data-srf-cloud-upload-status]');
		if (node) {
			return node;
		}

		node = document.createElement('div');
		node.className = 'srf-cloud-upload-status';
		node.setAttribute('data-srf-cloud-upload-status', '1');
		var input = getFileInput(form);
		if (input && input.parentNode) {
			input.parentNode.appendChild(node);
		} else {
			form.appendChild(node);
		}
		return node;
	}

	function setStatus(form, message, isError) {
		var node = getStatusNode(form);
		node.textContent = message || '';
		node.style.display = message ? 'block' : 'none';
		node.style.marginTop = '8px';
		node.style.fontSize = '13px';
		node.style.color = isError ? '#b32d2e' : '#1f6f43';
	}

	async function requestJson(url, options) {
		var response = await fetch(url, options);
		var data = null;
		try {
			data = await response.json();
		} catch (error) {
			data = null;
		}
		if (!response.ok) {
			var message = (data && data.message) ? data.message : text('failed', 'The cloud upload could not be completed.');
			throw new Error(message);
		}
		return data;
	}

	async function createBatch(form) {
		return requestJson(config.restUrl.replace(/\/$/, '') + '/upload-batches', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-SRF-Nonce': config.nonce
			},
			body: JSON.stringify({ form_type: config.formType || 'service', provider: config.provider || 'microsoft' })
		});
	}

	async function uploadFile(batch, file) {
		var formData = new FormData();
		formData.append('file', file, file.name);
		formData.append('batch_token', batch.batch_token || '');
		return requestJson(config.restUrl.replace(/\/$/, '') + '/upload-batches/' + encodeURIComponent(batch.batch_id) + '/files', {
			method: 'POST',
			headers: {
				'X-SRF-Nonce': config.nonce
			},
			body: formData
		});
	}

	async function finalizeBatch(batch) {
		return requestJson(config.restUrl.replace(/\/$/, '') + '/upload-batches/' + encodeURIComponent(batch.batch_id) + '/finalize', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-SRF-Nonce': config.nonce
			},
			body: JSON.stringify({ batch_token: batch.batch_token || '' })
		});
	}

	function setBatchInputs(form, batch) {
		var idInput = getBatchIdInput(form);
		var tokenInput = getBatchTokenInput(form);
		if (idInput) {
			idInput.value = String(batch.batch_id || '');
		}
		if (tokenInput) {
			tokenInput.value = String(batch.batch_token || '');
		}
	}

	function clearBatchInputs(form) {
		setBatchInputs(form, { batch_id: '', batch_token: '' });
	}

	async function uploadSelectedFiles(form, input) {
		var files = Array.prototype.slice.call(input.files || []);
		if (!files.length) {
			return null;
		}

		setStatus(form, text('uploading', 'Uploading files securely...'), false);
		var batch = await createBatch(form);
		setBatchInputs(form, batch);

		for (var i = 0; i < files.length; i++) {
			await uploadFile(batch, files[i]);
		}

		batch = await finalizeBatch(batch);
		setStatus(form, text('ready', 'Files uploaded securely.'), false);
		return batch;
	}

	function hasValue(input) {
		return !!(input && String(input.value || '').trim());
	}

	function initForm(form) {
		var input = getFileInput(form);
		if (!input) {
			return;
		}

		var batchIdInput = getBatchIdInput(form);
		var batchTokenInput = getBatchTokenInput(form);
		if (batchIdInput && batchTokenInput && hasValue(batchIdInput) && hasValue(batchTokenInput)) {
			input.required = false;
		}

		form.addEventListener('submit', async function (event) {
			if (form.__srfUploading) {
				return;
			}

			var files = Array.prototype.slice.call(input.files || []);
			var hasBatch = batchIdInput && batchTokenInput && hasValue(batchIdInput) && hasValue(batchTokenInput);

			if (!files.length && hasBatch) {
				input.required = false;
				return;
			}

			if (!files.length) {
				return;
			}

			event.preventDefault();
			form.__srfUploading = true;
			try {
				await uploadSelectedFiles(form, input);
				input.value = '';
				input.required = false;
				form.submit();
			} catch (error) {
				clearBatchInputs(form);
				setStatus(form, error && error.message ? error.message : text('failed', 'The cloud upload could not be completed.'), true);
				form.__srfUploading = false;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) {
			if (forms[i].querySelector('input[type="file"][name="srf_files[]"]')) {
				initForm(forms[i]);
			}
		}
	});
})();
