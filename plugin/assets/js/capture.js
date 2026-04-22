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
		{ match: /(^|\.)github\.com$/,                source: 'github',     medium: 'referral'  }
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
		if (!consentGranted()) { log('consent not granted'); return; }
		try {
			if (sessionStorage.getItem(STORAGE_KEY) === '1') { log('already captured'); return; }
		} catch (e) { /* sessionStorage disabled; proceed */ }

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
	}

	function fillElementorForms() {
		var forms = document.querySelectorAll('form.elementor-form');
		for (var i = 0; i < forms.length; i++) fillForm(forms[i]);
	}

	capture();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fillElementorForms);
	} else {
		fillElementorForms();
	}

	// Elementor Pro forms rendered after initial DOM (popups, lazy-loaded sections).
	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(document).on('elementor-pro/forms/new', function (event, form) {
			if (form && form.$el && form.$el[0]) fillForm(form.$el[0]);
		});
	}
})();
