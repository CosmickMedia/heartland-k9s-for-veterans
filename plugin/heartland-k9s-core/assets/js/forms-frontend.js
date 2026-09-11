/**
 * Heartland K9s forms — progressive enhancement.
 *
 * Without this script the forms POST to admin-post.php and the server renders the
 * result. With it: client-side required/email checks with inline errors + an error
 * summary (focus moved), fetch() to the REST endpoint, aria-live status, a disabled
 * "Sending..." submit state (no double submit), inline success or redirect, and a
 * native-submit fallback when the endpoint cannot be reached.
 *
 * No dependencies. Config: window.HK9.forms = { restNonce, i18n }.
 */
(function () {
	'use strict';

	var config = (window.HK9 && window.HK9.forms) || {};
	var i18n = config.i18n || {};

	function t(key, fallback, value) {
		var text = i18n[key] || fallback;
		return value === undefined ? text : text.replace('%s', value);
	}

	function fieldLabel(field) {
		var label = field.querySelector('.hk9-form__label');
		if (!label) {
			return '';
		}
		var clone = label.cloneNode(true);
		clone.querySelectorAll('.hk9-form__required').forEach(function (n) { n.remove(); });
		return clone.textContent.trim();
	}

	function controlOf(field) {
		return field.querySelector('input:not([type="hidden"]), select, textarea');
	}

	function setFieldError(field, message) {
		var control = controlOf(field);
		var error = field.querySelector('.hk9-form__error');
		if (!control || !error) {
			return;
		}
		if (message) {
			error.textContent = message;
			error.hidden = false;
			field.classList.add('is-invalid');
			control.setAttribute('aria-invalid', 'true');
			addDescribedBy(control, error.id);
		} else {
			error.textContent = '';
			error.hidden = true;
			field.classList.remove('is-invalid');
			control.removeAttribute('aria-invalid');
			removeDescribedBy(control, error.id);
		}
	}

	function addDescribedBy(control, id) {
		var ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
		if (ids.indexOf(id) === -1) {
			ids.push(id);
		}
		control.setAttribute('aria-describedby', ids.join(' '));
	}

	function removeDescribedBy(control, id) {
		var ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== id; });
		if (ids.length) {
			control.setAttribute('aria-describedby', ids.join(' '));
		} else {
			control.removeAttribute('aria-describedby');
		}
	}

	function announce(form, message) {
		var status = form.querySelector('.hk9-form__status');
		if (status) {
			status.textContent = '';
			// Re-insert on the next frame so repeated identical messages are announced.
			window.requestAnimationFrame(function () { status.textContent = message; });
		}
	}

	function clearErrors(form) {
		form.querySelectorAll('.hk9-form__field').forEach(function (field) { setFieldError(field, ''); });
		var summary = form.querySelector('.hk9-form__summary');
		if (summary) {
			summary.hidden = true;
			summary.querySelector('.hk9-form__summary-list').innerHTML = '';
		}
	}

	function showSummary(form, title, errors) {
		var summary = form.querySelector('.hk9-form__summary');
		if (!summary) {
			return;
		}
		var list = summary.querySelector('.hk9-form__summary-list');
		var titleNode = summary.querySelector('.hk9-form__summary-title');
		list.innerHTML = '';
		titleNode.textContent = title;
		Object.keys(errors).forEach(function (key) {
			var field = form.querySelector('.hk9-form__field[data-hk9-field="' + key + '"]');
			var control = field ? controlOf(field) : null;
			var li = document.createElement('li');
			if (control) {
				var a = document.createElement('a');
				a.href = '#' + control.id;
				a.textContent = errors[key];
				a.addEventListener('click', function (e) {
					e.preventDefault();
					control.focus();
				});
				li.appendChild(a);
			} else {
				li.textContent = errors[key];
			}
			list.appendChild(li);
		});
		summary.hidden = false;
		summary.focus();
	}

	function applyErrors(form, errors, title) {
		var count = 0;
		Object.keys(errors).forEach(function (key) {
			var field = form.querySelector('.hk9-form__field[data-hk9-field="' + key + '"]');
			if (field) {
				setFieldError(field, errors[key]);
				count++;
			}
		});
		showSummary(form, title || t('summaryTitle', 'Please correct the following:'), errors);
		announce(form, t('errorCount', 'The form has %s error(s). Please review the highlighted fields.', String(count || Object.keys(errors).length)));
	}

	function validate(form) {
		var errors = {};
		form.querySelectorAll('.hk9-form__field').forEach(function (field) {
			var control = controlOf(field);
			if (!control) {
				return;
			}
			var key = field.getAttribute('data-hk9-field');
			var label = fieldLabel(field);
			var required = control.hasAttribute('required');
			if (control.type === 'checkbox') {
				if (required && !control.checked) {
					errors[key] = t('checkbox', 'Please confirm: %s', label);
				}
				return;
			}
			var value = (control.value || '').trim();
			if (required && value === '') {
				errors[key] = control.tagName === 'SELECT' ? t('select', 'Please select an option for %s.', label) : t('required', '%s is required.', label);
				return;
			}
			if (value !== '' && control.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
				errors[key] = t('email', 'Please enter a valid email address.');
			}
		});
		return errors;
	}

	function setBusy(form, busy) {
		var button = form.querySelector('.hk9-form__submit');
		form.setAttribute('aria-busy', busy ? 'true' : 'false');
		form.dataset.hk9Busy = busy ? '1' : '';
		if (!button) {
			return;
		}
		if (busy) {
			button.dataset.idleLabel = button.textContent;
			button.textContent = button.getAttribute('data-sending-label') || 'Sending...';
			button.disabled = true;
			button.classList.add('is-sending');
		} else {
			button.textContent = button.dataset.idleLabel || button.textContent;
			button.disabled = false;
			button.classList.remove('is-sending');
		}
	}

	function refreshTokens(form, refresh) {
		if (!refresh) {
			return;
		}
		var token = form.querySelector('input[name="hk9_token"]');
		var ts = form.querySelector('input[name="hk9_ts"]');
		if (token && refresh.token) {
			token.value = refresh.token;
		}
		if (ts && refresh.ts) {
			ts.value = refresh.ts;
		}
	}

	function showSuccess(form) {
		var wrap = form.closest('.hk9-form-wrap');
		var template = wrap ? wrap.querySelector('.hk9-form__success-template') : null;
		if (!wrap || !template) {
			return false;
		}
		var success = template.content.firstElementChild.cloneNode(true);
		success.setAttribute('tabindex', '-1');
		wrap.querySelectorAll('.hk9-form__heading, .hk9-form__intro, .hk9-form__notice').forEach(function (n) { n.remove(); });
		form.replaceWith(success);
		success.focus();
		return true;
	}

	function nativeSubmit(form) {
		form.dataset.hk9Fallback = '1';
		setBusy(form, false);
		HTMLFormElement.prototype.submit.call(form);
	}

	function handleSubmit(event) {
		var form = event.currentTarget;
		if (form.dataset.hk9Fallback === '1') {
			return; // falling back to the native path
		}
		event.preventDefault();
		if (form.dataset.hk9Busy === '1') {
			return;
		}

		clearErrors(form);
		var errors = validate(form);
		if (Object.keys(errors).length) {
			applyErrors(form, errors);
			return;
		}

		var endpoint = form.getAttribute('data-hk9-endpoint');
		if (!endpoint || typeof window.fetch !== 'function') {
			nativeSubmit(form);
			return;
		}

		setBusy(form, true);
		announce(form, t('sending', 'Sending your message…'));

		var headers = { Accept: 'application/json' };
		if (config.restNonce) {
			headers['X-WP-Nonce'] = config.restNonce;
		}

		window.fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: new FormData(form)
		}).then(function (response) {
			return response.json().then(function (data) {
				return { status: response.status, data: data };
			}, function () {
				throw new Error('non-json');
			});
		}).then(function (result) {
			var data = result.data || {};
			if (data.ok) {
				if (form.getAttribute('data-hk9-mode') === 'redirect' && data.redirect) {
					announce(form, t('sent', 'Your message has been sent.'));
					window.location.assign(data.redirect);
					return;
				}
				announce(form, t('sent', 'Your message has been sent.'));
				if (!showSuccess(form) && data.redirect) {
					window.location.assign(data.redirect);
				}
				return;
			}
			if (data.code === 'rest_cookie_invalid_nonce') {
				nativeSubmit(form);
				return;
			}
			setBusy(form, false);
			refreshTokens(form, data.refresh);
			var fieldErrors = data.errors && typeof data.errors === 'object' ? data.errors : {};
			var message = data.message || t('generic', 'Something went wrong while sending. Please try again.');
			if (Object.keys(fieldErrors).length) {
				applyErrors(form, fieldErrors, message);
			} else {
				showSummary(form, message, {});
				announce(form, message);
			}
		}).catch(function () {
			// Network failure or unexpected response: let the server render the result.
			nativeSubmit(form);
		});
	}

	function init() {
		document.querySelectorAll('form.hk9-form[data-hk9-form]').forEach(function (form) {
			if (form.dataset.hk9Enhanced === '1') {
				return;
			}
			form.dataset.hk9Enhanced = '1';
			form.noValidate = true; // errors are rendered inline (consistent across browsers/AT)
			form.addEventListener('submit', handleSubmit);
			form.querySelectorAll('.hk9-form__field').forEach(function (field) {
				var control = controlOf(field);
				if (control) {
					control.addEventListener('input', function () { setFieldError(field, ''); });
					control.addEventListener('change', function () { setFieldError(field, ''); });
				}
			});
			// Server-rendered error summary (no-JS round trip): make its links focus the controls.
			var summary = form.querySelector('.hk9-form__summary');
			if (summary && !summary.hidden) {
				summary.querySelectorAll('a[href^="#"]').forEach(function (a) {
					a.addEventListener('click', function (e) {
						var target = document.getElementById(a.getAttribute('href').slice(1));
						if (target) {
							e.preventDefault();
							target.focus();
						}
					});
				});
				summary.focus();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
