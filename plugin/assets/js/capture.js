/**
 * LeadStream capture script.
 *
 * Runs client-side on every frontend request. Detects UTMs, click IDs, and
 * referrer, stores first-party cookies, and fills hidden fields on Elementor
 * forms. Other form types are handled PHP-side.
 *
 * Precedence for source/medium: explicit UTM > click ID > referrer > direct.
 */
(function () {
	'use strict';

	var cfg = Object.assign({
		cookiePrefix:      'leadstream_',
		cookieDuration:    30,
		debug:             false,
		subdomainTracking: false,
		requireConsent:    false
	}, window.leadstreamConfig || {});

	var PREFIX       = cfg.cookiePrefix;
	var DURATION_MS  = parseInt(cfg.cookieDuration, 10) * 864e5;
	var SECURE_FLAG  = location.protocol === 'https:' ? '; Secure' : '';
	var SAMESITE     = '; SameSite=Lax';
	var DOMAIN_FLAG  = cfg.subdomainTracking ? '; domain=' + registrableDomain() : '';
	var STORAGE_KEY  = 'leadstream_captured';

	function log() {
		if (!cfg.debug) return;
		try { console.log.apply(console, ['[leadstream]'].concat([].slice.call(arguments))); } catch (e) {}
	}

	// Simple eTLD+1 heuristic. Works for single-TLD hosts like example.com.
	// Multi-tier TLDs (example.co.uk) need the public suffix list; for those,
	// leave subdomainTracking off and set cookies per-host.
	function registrableDomain() {
		var parts = location.hostname.split('.');
		if (parts.length < 2) return location.hostname;
		return '.' + parts.slice(-2).join('.');
	}

	function consentGranted() {
		if (!cfg.requireConsent) return true;
		if (typeof window.wp_has_consent === 'function') {
			return !!window.wp_has_consent('marketing') || !!window.wp_has_consent('statistics');
		}
		if (window.Cookiebot && window.Cookiebot.consent) {
			return !!window.Cookiebot.consent.marketing || !!window.Cookiebot.consent.statistics;
		}
		if (typeof window.cmplz_has_consent === 'function') {
			return !!window.cmplz_has_consent('marketing') || !!window.cmplz_has_consent('statistics');
		}
		// Consent required but no API detected. Fail closed.
		return false;
	}

	function setCookie(name, value) {
		if (value == null || value === '') return;
		var expires = new Date(Date.now() + DURATION_MS).toUTCString();
		document.cookie = PREFIX + name + '=' + encodeURIComponent(value) +
			'; expires=' + expires + '; path=/' + DOMAIN_FLAG + SECURE_FLAG + SAMESITE;
		log('set', name, '=', value);
	}

	function getCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + PREFIX + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : null;
	}

	function getParam(name) {
		return new URLSearchParams(location.search).get(name);
	}

	var CLICK_IDS = [
		{ param: 'gclid',      source: 'google',    medium: 'cpc'     },
		{ param: 'dclid',      source: 'google',    medium: 'display' },
		{ param: 'gbraid',     source: 'google',    medium: 'cpc'     },
		{ param: 'wbraid',     source: 'google',    medium: 'cpc'     },
		{ param: 'gad_source', source: 'google',    medium: 'cpc'     },
		{ param: 'msclkid',    source: 'bing',      medium: 'cpc'     },
		{ param: 'fbclid',     source: 'facebook',  medium: 'cpc'     },
		{ param: 'ttclid',     source: 'tiktok',    medium: 'cpc'     },
		{ param: 'twclid',     source: 'twitter',   medium: 'cpc'     },
		{ param: 'li_fat_id',  source: 'linkedin',  medium: 'cpc'     },
		{ param: 'ScCid',      source: 'snapchat',  medium: 'cpc'     },
		{ param: 'epik',       source: 'pinterest', medium: 'cpc'     }
	];

	function detectClickId() {
		for (var i = 0; i < CLICK_IDS.length; i++) {
			var c = CLICK_IDS[i];
			var v = getParam(c.param);
			if (v) return { type: c.param, id: v, source: c.source, medium: c.medium };
		}
		return null;
	}

	var REFERRER_MAP = [
		{ match: /(^|\.)google\./,                    source: 'google',     medium: 'organic'   },
		{ match: /(^|\.)bing\./,                      source: 'bing',       medium: 'organic'   },
		{ match: /(^|\.)yahoo\./,                     source: 'yahoo',      medium: 'organic'   },
		{ match: /(^|\.)duckduckgo\.com$/,            source: 'duckduckgo', medium: 'organic'   },
		{ match: /(^|\.)baidu\.com$/,                 source: 'baidu',      medium: 'organic'   },
		{ match: /(^|\.)yandex\./,                    source: 'yandex',     medium: 'organic'   },
		{ match: /(^|\.)ecosia\.org$/,                source: 'ecosia',     medium: 'organic'   },
		{ match: /(^|\.)brave\.com$/,                 source: 'brave',      medium: 'organic'   },
		{ match: /(^|\.)facebook\.com$|^fb\.com$/,    source: 'facebook',   medium: 'social'    },
		{ match: /(^|\.)instagram\.com$/,             source: 'instagram',  medium: 'social'    },
		{ match: /(^|\.)linkedin\.com$|^lnkd\.in$/,   source: 'linkedin',   medium: 'social'    },
		{ match: /(^|\.)(twitter|x)\.com$|^t\.co$/,   source: 'twitter',    medium: 'social'    },
		{ match: /(^|\.)tiktok\.com$/,                source: 'tiktok',     medium: 'social'    },
		{ match: /(^|\.)youtube\.com$|^youtu\.be$/,   source: 'youtube',    medium: 'social'    },
		{ match: /(^|\.)pinterest\.com$|^pin\.it$/,   source: 'pinterest',  medium: 'social'    },
		{ match: /(^|\.)reddit\.com$|^redd\.it$/,     source: 'reddit',     medium: 'social'    },
		{ match: /(^|\.)snapchat\.com$/,              source: 'snapchat',   medium: 'social'    },
		{ match: /(^|\.)threads\.net$/,               source: 'threads',    medium: 'social'    },
		{ match: /(^|\.)whatsapp\.com$|^wa\.me$/,     source: 'whatsapp',   medium: 'messaging' },
		{ match: /(^|\.)telegram\.(org|me)$|^t\.me$/, source: 'telegram',   medium: 'messaging' },
		{ match: /(^|\.)github\.com$/,                source: 'github',         medium: 'referral'  },
		// Email service providers — classify medium=email so reports
		// separate email-driven traffic from organic referrals.
		{ match: /(^|\.)list-manage\.com$/,           source: 'mailchimp',      medium: 'email'     },
		{ match: /(^|\.)mailchimp\.com$/,             source: 'mailchimp',      medium: 'email'     },
		{ match: /(^|\.)mc\.us$/,                     source: 'mailchimp',      medium: 'email'     },
		{ match: /(^|\.)constantcontact\.com$/,       source: 'constantcontact', medium: 'email'    },
		{ match: /(^|\.)ccsend\.com$/,                source: 'constantcontact', medium: 'email'    },
		{ match: /(^|\.)r20\.rs6\.net$/,              source: 'constantcontact', medium: 'email'    },
		{ match: /(^|\.)mailerlite\.com$/,            source: 'mailerlite',     medium: 'email'     },
		{ match: /(^|\.)activecampaign\.com$/,        source: 'activecampaign', medium: 'email'     },
		{ match: /(^|\.)klaviyo\.com$/,               source: 'klaviyo',        medium: 'email'     },
		{ match: /(^|\.)hsmsend\.com$/,               source: 'hubspot',        medium: 'email'     },
		{ match: /(^|\.)convertkit\.com$/,            source: 'convertkit',     medium: 'email'     },
		{ match: /^ck\.email$/,                       source: 'convertkit',     medium: 'email'     },
		{ match: /(^|\.)substack\.com$/,              source: 'substack',       medium: 'email'     },
		{ match: /(^|\.)beehiiv\.com$/,               source: 'beehiiv',        medium: 'email'     },
		{ match: /(^|\.)sendgrid\.net$/,              source: 'sendgrid',       medium: 'email'     },
		{ match: /(^|\.)mailgun\.org$/,               source: 'mailgun',        medium: 'email'     }
	];

	function classifyReferrer() {
		if (!document.referrer) return { source: 'direct', medium: 'direct' };
		var host;
		try { host = new URL(document.referrer).hostname; }
		catch (e) { return { source: 'unknown', medium: 'referral' }; }
		if (host === location.hostname) return null; // internal nav
		for (var i = 0; i < REFERRER_MAP.length; i++) {
			if (REFERRER_MAP[i].match.test(host)) {
				return { source: REFERRER_MAP[i].source, medium: REFERRER_MAP[i].medium };
			}
		}
		return { source: host, medium: 'referral' };
	}

	function capture() {
		if (!consentGranted()) { log('consent not granted'); return Promise.resolve(); }

		// Per-URL session dedup. Same URL refreshed in same tab = no second
		// REST call. A different URL (or different query string) IS a new
		// touch worth recording, even if attribution cookies already exist
		// from an earlier visit. The server-side touch_id dedup catches
		// cross-tab refreshes via the 60-second bucket.
		var pageKey = STORAGE_KEY + ':' + location.pathname + location.search;
		try {
			if (sessionStorage.getItem(pageKey) === '1') {
				log('already captured this URL in this session');
				return Promise.resolve();
			}
		} catch (e) { /* sessionStorage disabled; proceed */ }

		// Prefer the REST endpoint so cookies land via Set-Cookie HTTP header
		// (ITP-friendly). Fall back to client-side document.cookie if REST is
		// unavailable. Note: we ALWAYS call REST, even if attribution cookies
		// already exist, so the multi-touch journal records every meaningful
		// visit. The server side decides whether to update cookies (per the
		// configured attribution model) or just record a touch.
		return captureViaRest().then(function (response) {
			try { sessionStorage.setItem(pageKey, '1'); } catch (e) {}
			// Phase 3: also issue cookies via the CNAMEd backend if configured.
			// This gets us HTTP-set cookies that defeat Safari ITP's 7-day cap
			// even on cached pages where the local PHP-side capture never ran.
			issueBackendCookies();
			return response;
		}).catch(function (err) {
			log('REST capture failed, falling back to client-side', err);
			// Client-side fallback only writes cookies if none exist (preserve
			// first-touch). Touches will not be recorded in this fallback path
			// since DB writes require server-side execution.
			if (!getCookie('utm_source')) {
				captureClientSide();
			}
			issueBackendCookies();
		});
	}

	function issueBackendCookies() {
		if (!cfg.backendUrl || !cfg.backendKey || !cfg.siteApex) return;
		if (typeof window.fetch !== 'function') return;

		var payload = {};
		var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
			'click_id', 'click_id_type', 'first_page', 'referrer'];
		for (var i = 0; i < keys.length; i++) {
			var v = getCookie(keys[i]);
			if (v) payload[keys[i]] = v;
		}
		// Visitor cookie comes via REST too; keep it in sync on the parent domain.
		var visitor = readRawCookie('leadstream_visitor');
		if (visitor) payload.visitor = visitor;

		if (Object.keys(payload).length === 0) return;

		fetch(cfg.backendUrl.replace(/\/$/, '') + '/v1/cookie', {
			method: 'POST',
			credentials: 'include',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + cfg.backendKey
			},
			body: JSON.stringify({
				domain: cfg.siteApex,
				cookies: payload,
				max_age_days: cfg.cookieMaxAgeDays || 365
			})
		}).then(function (response) {
			if (response.ok) {
				log('backend cookies issued for', cfg.siteApex);
			} else {
				log('backend cookie request failed', response.status);
			}
		}).catch(function (err) {
			log('backend cookie network error', err);
		});
	}

	function readRawCookie(fullName) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + fullName + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : null;
	}

	function captureViaRest() {
		if (typeof window.fetch !== 'function' || !cfg.restUrl || !cfg.restNonce) {
			return Promise.reject('REST unavailable or not configured');
		}
		var body = JSON.stringify({
			url: location.href,
			referrer: document.referrer || ''
		});
		return fetch(cfg.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce
			},
			body: body
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('REST capture returned ' + response.status);
			}
			try { sessionStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
			log('REST capture succeeded');
			return response;
		});
	}

	function captureClientSide() {
		var utmSource   = getParam('utm_source');
		var utmMedium   = getParam('utm_medium');
		var utmCampaign = getParam('utm_campaign');
		var utmTerm     = getParam('utm_term');
		var utmContent  = getParam('utm_content');
		var clickId     = detectClickId();

		var source, medium;
		if (utmSource) {
			source = utmSource;
			medium = utmMedium || (clickId ? clickId.medium : 'unknown');
		} else if (clickId) {
			source = clickId.source;
			medium = clickId.medium;
		} else {
			var ref = classifyReferrer();
			if (ref === null) {
				log('internal referrer, skipping first-touch overwrite');
				try { sessionStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
				return;
			}
			source = ref.source;
			medium = ref.medium;
		}

		setCookie('utm_source', source);
		setCookie('utm_medium', medium);
		setCookie('utm_campaign', utmCampaign);
		setCookie('utm_term', utmTerm);
		setCookie('utm_content', utmContent);
		if (clickId) {
			setCookie('click_id', clickId.id);
			setCookie('click_id_type', clickId.type);
		}
		setCookie('first_page', location.href);
		if (document.referrer) setCookie('referrer', document.referrer);

		try { sessionStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
	}

	var FIELD_KEYS = [
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'click_id', 'click_id_type', 'first_page', 'referrer'
	];

	// Platform-native click-ID field names. When a form uses one of these as
	// the hidden input name (rather than LeadStream's canonical `click_id`),
	// we fill it so integrations built around the native name work without
	// requiring users to rename their fields. Kept in sync with CLICK_IDS.
	var CLICK_ID_ALIASES = [
		'gclid', 'dclid', 'gbraid', 'wbraid', 'gad_source',
		'msclkid', 'fbclid', 'ttclid', 'twclid', 'li_fat_id',
		'ScCid', 'epik'
	];

	function fillForm(form) {
		if (!form || typeof form.querySelector !== 'function') return;
		for (var i = 0; i < FIELD_KEYS.length; i++) {
			var key   = FIELD_KEYS[i];
			var value = getCookie(key) || '';
			if (key === 'first_page' && !value) value = location.href;
			var field = form.querySelector('input[name*="' + key + '"]');
			if (field) {
				field.value = value;
				log('filled', field.name, '=', value);
			}
		}

		// Also fill any field whose name matches the detected click-ID type
		// (gclid, fbclid, msclkid, etc.). We only fill when the cookie's
		// click_id_type matches the field name, so a gclid cookie never ends
		// up populating a field named `fbclid` on a partially-migrated form.
		// Two patterns supported:
		//   1. Exact name: `<input name="gclid">` (vanilla HTML, CF7, WPForms)
		//   2. Bracketed:  `<input name="form_fields[gclid]">` (Elementor Pro)
		// Both anchor to a complete field name segment so we still avoid
		// clobbering fields like `gclid_captured_flag`.
		var clickIdValue = getCookie('click_id');
		var clickIdType  = getCookie('click_id_type');
		if (clickIdValue && clickIdType && CLICK_ID_ALIASES.indexOf(clickIdType) !== -1) {
			var aliasFields = form.querySelectorAll(
				'input[name="' + clickIdType + '"], input[name$="[' + clickIdType + ']"]'
			);
			for (var a = 0; a < aliasFields.length; a++) {
				aliasFields[a].value = clickIdValue;
				log('filled alias', aliasFields[a].name, '=', clickIdValue);
			}
		}
	}

	function fillKnownForms() {
		var forms = document.querySelectorAll('form.elementor-form, form[id^="gform_"]');
		for (var i = 0; i < forms.length; i++) fillForm(forms[i]);
	}

	// Universal injection: add attribution as hidden inputs to every form on
	// the page, not just Elementor and Gravity. Handles CF7, WPForms, Ninja,
	// Fluent, custom HTML forms, and any third-party form whose backend
	// accepts extra POST fields without complaint (which is nearly all of
	// them). Skipped for GET forms (search) and standard WP forms (login,
	// register, comment) where attribution would be noise.
	var UNIVERSAL_INJECT_KEYS = [
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'click_id', 'first_page', 'referrer'
	];
	var UNIVERSAL_SKIP_FORM_IDS = /^(loginform|registerform|lostpasswordform|resetpassform|commentform|searchform|adminbar-search)$/i;

	function shouldUniversalInject(form) {
		if (!form || typeof form.appendChild !== 'function') return false;
		var method = (form.getAttribute('method') || 'get').toLowerCase();
		if (method === 'get') return false;
		if (form.id && UNIVERSAL_SKIP_FORM_IDS.test(form.id)) return false;
		return true;
	}

	function ensureHiddenInput(form, name, value) {
		if (!value) return;
		// Dedupe against both vanilla and bracketed (Elementor) naming so we
		// do not stack a redundant `<input name="gclid">` next to an existing
		// `<input name="form_fields[gclid]">`.
		var existing = form.querySelector(
			'input[name="' + name + '"], input[name$="[' + name + ']"]'
		);
		if (existing) return;
		var input = document.createElement('input');
		input.type  = 'hidden';
		input.name  = name;
		input.value = value;
		input.setAttribute('data-leadstream-injected', '1');
		form.appendChild(input);
	}

	function injectAttribution(form) {
		if (!shouldUniversalInject(form)) return;

		for (var i = 0; i < UNIVERSAL_INJECT_KEYS.length; i++) {
			var key   = UNIVERSAL_INJECT_KEYS[i];
			var value = getCookie(key);
			if (key === 'first_page' && !value) value = location.href;
			ensureHiddenInput(form, key, value);
		}

		var clickIdValue = getCookie('click_id');
		var clickIdType  = getCookie('click_id_type');
		if (clickIdValue && clickIdType && CLICK_ID_ALIASES.indexOf(clickIdType) !== -1) {
			ensureHiddenInput(form, clickIdType, clickIdValue);
		}
	}

	function injectIntoAllForms() {
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) injectAttribution(forms[i]);
	}

	function watchForNewForms() {
		if (typeof window.MutationObserver !== 'function') return;
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					var node = added[j];
					if (!node || node.nodeType !== 1) continue;
					if (node.tagName === 'FORM') {
						injectAttribution(node);
					} else if (typeof node.querySelectorAll === 'function') {
						var nested = node.querySelectorAll('form');
						for (var k = 0; k < nested.length; k++) injectAttribution(nested[k]);
					}
				}
			}
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}

	function processForms() {
		fillKnownForms();
		if (cfg.universalInject !== false) {
			injectIntoAllForms();
			watchForNewForms();
		}
	}

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	// Capture returns a Promise; chain processForms after it settles so cookies
	// are guaranteed set before we read them. On cached pages where REST is
	// the only capture path, this prevents a race where forms get processed
	// before the Set-Cookie response arrives.
	var captureReady = capture();
	if (!captureReady || typeof captureReady.then !== 'function') {
		captureReady = Promise.resolve();
	}
	onReady(function () {
		captureReady.then(processForms, processForms);
	});

	if (typeof window.jQuery !== 'undefined') {
		// Elementor Pro forms rendered after initial DOM (popups, lazy-loaded sections).
		window.jQuery(document).on('elementor-pro/forms/new', function (event, form) {
			if (form && form.$el && form.$el[0]) {
				fillForm(form.$el[0]);
				injectAttribution(form.$el[0]);
			}
		});
		// Gravity Forms re-renders its wrapper after AJAX submit and pagination.
		window.jQuery(document).on('gform_post_render', function (event, formId) {
			var form = document.getElementById('gform_' + formId);
			if (form) {
				fillForm(form);
				injectAttribution(form);
			}
		});
	}
})();
