(function() {
    'use strict';

    // Helper function to set cookie
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        // Set cookie with path=/ to ensure it's available across all pages
        // Don't set domain to allow cookie to work on localhost and subdomains
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    // Helper function to set permanent cookie (persists for 180 days by default)
    function setCookiePermanent(name, value, days) {
        var expires = '';
        // Default to 180 days if not specified
        var expiryDays = days !== null && days !== undefined ? days : 180;
        var date = new Date();
        date.setTime(date.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    // Helper function to get cookie
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }

    // Generate or get session ID (unique per browser session)
    function getSessionId() {
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                var sessionId = sessionStorage.getItem('cwsc_session_id');
                if (!sessionId) {
                    // Generate new session ID (timestamp + random)
                    sessionId = Date.now().toString(36) + Math.random().toString(36).substr(2);
                    sessionStorage.setItem('cwsc_session_id', sessionId);
                }
                return sessionId;
            }
        } catch (e) {
            // Fallback: generate without storage
            return Date.now().toString(36) + Math.random().toString(36).substr(2);
        }
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Capture and persist First Visit data (Landing Page and Referrer Source).
     * This runs IMMEDIATELY when script loads, before DOM ready.
     * Uses First Touch Attribution - NEVER overwrites once set.
     */
    function captureFirstVisit() {
        // Check if First Visit data already exists (never overwrite).
        var firstVisitSet = getCookie('cwsc_first_visit_set');
        var firstVisitOrderLink = getCookie('cwsc_first_visit_order_link');
        var firstVisitSource = getCookie('cwsc_first_visit_source');

        // If already set, do nothing (First Touch Attribution).
        if (firstVisitSet && firstVisitOrderLink && firstVisitSource) {
            return;
        }

        // This is the first visit - capture data immediately.
        var currentUrl = window.location.href;
        var currentReferrer = document.referrer || '';
        var isCheckoutPage = currentUrl.indexOf('checkout') !== -1 || currentUrl.indexOf('order-received') !== -1;

        // Don't set checkout/thank you page as initial URL.
        if (isCheckoutPage) {
            // Try to get from cookie if available (from previous page).
            var savedUrl = getCookie('cwsc_initial_url');
            if (savedUrl && savedUrl.indexOf('checkout') === -1 && savedUrl.indexOf('order-received') === -1) {
                currentUrl = savedUrl;
            } else {
                // Fallback: use current URL but this is not ideal.
                // The real landing page should have been captured earlier.
            }
        }

        // Detect source from current URL and referrer.
        var source = detectSource(currentUrl, currentReferrer);

        // Save permanently (180 days) - only set once (First Touch Attribution).
        setCookiePermanent('cwsc_first_visit_order_link', currentUrl, 180);
        setCookiePermanent('cwsc_first_visit_source', source, 180);
        setCookiePermanent('cwsc_first_visit_set', '1', 180);

        // Also save to sessionStorage for immediate access (current session).
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                sessionStorage.setItem('cwsc_first_visit_order_link', currentUrl);
                sessionStorage.setItem('cwsc_first_visit_source', source);
            }
        } catch (e) {
            // Ignore storage errors.
        }

        // Also save to temporary cookie for PHP to read (for current session).
        setCookie('cwsc_initial_url', currentUrl, 1);
        setCookie('cwsc_customer_source', source, 1);
    }

    /**
     * Get First Visit data (Landing Page).
     * Always returns the first visit URL, never overwritten.
     */
    function getFirstVisitUrl() {
        // First, check permanent cookie (source of truth for First Touch Attribution).
        var firstVisitUrl = getCookie('cwsc_first_visit_order_link');
        if (firstVisitUrl) {
            return firstVisitUrl;
        }

        // Fallback to sessionStorage (current session).
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                var url = sessionStorage.getItem('cwsc_first_visit_order_link');
                if (url) return url;
            }
        } catch (e) {
            // Ignore storage errors.
        }

        // Final fallback: current URL (should rarely happen).
        return window.location.href;
    }

    /**
     * Get First Visit Source.
     * Always returns the first visit source, never overwritten.
     */
    function getFirstVisitSource() {
        // First, check permanent cookie (source of truth for First Touch Attribution).
        var firstVisitSource = getCookie('cwsc_first_visit_source');
        if (firstVisitSource) {
            return firstVisitSource;
        }

        // Fallback to sessionStorage (current session).
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                var source = sessionStorage.getItem('cwsc_first_visit_source');
                if (source) return source;
            }
        } catch (e) {
            // Ignore storage errors.
        }

        // Final fallback: detect from current page.
        return detectSource(window.location.href, document.referrer || '');
    }

    /**
     * Detect source from URL and referrer.
     * 
     * @param {string} url Current URL.
     * @param {string} referrer Referrer URL.
     * @return {string} Detected source.
     */
    function detectSource(url, referrer) {
        var urlParams = new URLSearchParams((url.split('?')[1] || ''));
        var ua = (navigator.userAgent || '').toLowerCase();
        var lowerUrl = url.toLowerCase();
        
        function getHost(u) {
            try { return new URL(u).hostname.toLowerCase(); } catch (e) { return ''; }
        }
        var urlHost = getHost(url);
        var refHost = getHost(referrer);

        var utmSource = (urlParams.get('utm_source') || '').toLowerCase();

        // Priority 1: UTM source parameter (treat as Ads for known platforms)
        if (utmSource) {
            if (utmSource.indexOf('facebook') !== -1 || utmSource.indexOf('instagram') !== -1) {
                return (utmSource.indexOf('instagram') !== -1) ? 'Instagram Ads' : 'Facebook Ads';
            }
            if (utmSource.indexOf('google') !== -1) {
                return 'Google Ads';
            }
            if (utmSource.indexOf('tiktok') !== -1) {
                return 'TikTok Ads';
            }
            if (utmSource.indexOf('zalo') !== -1) {
                return 'Zalo Ads';
            }
            // Unknown paid source → return the raw utm_source
            return urlParams.get('utm_source');
        }

        // Priority 2: Ad click identifiers
        // Facebook/Instagram Ads: fbclid → if ref/host suggests instagram, label Instagram; else Facebook
        if (lowerUrl.indexOf('fbclid') !== -1 || urlParams.has('fbclid')) {
            if (urlHost.indexOf('instagram.com') !== -1 || refHost.indexOf('instagram.com') !== -1) {
                return 'Instagram Ads';
            }
            return 'Facebook Ads';
        }
        // Google Ads
        if (lowerUrl.indexOf('gclid') !== -1 || urlParams.has('gclid')) {
            return 'Google Ads';
        }
        // TikTok Ads
        if (lowerUrl.indexOf('ttclid') !== -1 || urlParams.has('ttclid')) {
            return 'TikTok Ads';
        }

        // Priority 3: Organic/Referral sources by known domains
        // Google SEO
        if (lowerUrl.indexOf('srsltid') !== -1 || urlParams.has('srsltid') || refHost.indexOf('google.') !== -1) {
            return 'Google SEO';
        }
        // Instagram
        if (urlHost.indexOf('instagram.com') !== -1 || refHost.indexOf('instagram.com') !== -1 || ua.indexOf('instagram') !== -1) {
            return 'Instagram';
        }
        // YouTube
        if (urlHost.indexOf('youtube.com') !== -1 || refHost.indexOf('youtube.com') !== -1 || urlHost.indexOf('youtu.be') !== -1 || refHost.indexOf('youtu.be') !== -1) {
            return 'YouTube';
        }
        // Zalo
        if (urlHost.indexOf('zalo') !== -1 || refHost.indexOf('zalo') !== -1 || ua.indexOf('zalo') !== -1) {
            return 'Zalo';
        }
        // TikTok
        if (urlHost.indexOf('tiktok.com') !== -1 || refHost.indexOf('tiktok.com') !== -1 || ua.indexOf('tiktok') !== -1) {
            return 'TikTok';
        }
        // Shopee
        if (urlHost.indexOf('shopee.vn') !== -1 || refHost.indexOf('shopee.vn') !== -1) {
            return 'Shopee';
        }
        // Cốc Cốc (Vietnamese browser)
        if (urlHost.indexOf('coccoc.com') !== -1 || refHost.indexOf('coccoc.com') !== -1) {
            return 'Coc Coc';
        }
        // Facebook (organic/app)
        if (urlHost.indexOf('facebook.com') !== -1 || refHost.indexOf('facebook.com') !== -1 || ua.indexOf('facebook') !== -1) {
            return 'Facebook';
        }

        return 'Direct Visit';
    }

    function getBuyLink() {
        // If Woo cart links are localized, use them for buy-link on purchase forms
        if (typeof window.cwsc_frontend !== 'undefined' && Array.isArray(window.cwsc_frontend.cart_links) && window.cwsc_frontend.cart_links.length > 0) {
            return window.cwsc_frontend.cart_links.join(', ');
        }
        // Fallback: current page URL
        return window.location.href;
    }

    function ensureHiddenInput(form, name, value) {
        var input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    /**
     * Hydrate forms with First Visit data.
     * Uses First Touch Attribution - always uses the first visit values.
     */
    function hydrateForms() {
        // Get First Visit data (never overwritten).
        var orderLink = getFirstVisitUrl();
        var source = getFirstVisitSource();
        var buyLink = getBuyLink();

        // Also save to temporary cookie for PHP to read (for current session).
        setCookie('cwsc_customer_source', source, 1);
        setCookie('cwsc_initial_url', orderLink, 1);

        // Target CF7 forms on the page.
        var forms = document.querySelectorAll('.wpcf7 form');
        if (!forms || forms.length === 0) {
            return;
        }

        forms.forEach(function(form) {
            ensureHiddenInput(form, 'customer-source', source);
            ensureHiddenInput(form, 'order-link', orderLink);
            ensureHiddenInput(form, 'buy-link', buyLink);
        });
    }

    // CRITICAL: Capture First Visit data IMMEDIATELY when script loads.
    // This runs before DOM ready to ensure we capture the landing page before any navigation.
    captureFirstVisit();

    // Run hydrateForms when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateForms);
    } else {
        hydrateForms();
    }
    
    // Re-hydrate forms before submission (for CF7).
    // Hook into CF7 before submit event.
    document.addEventListener('wpcf7beforesubmit', function() {
        hydrateForms();
    }, true);
    
    // Also hook into form submit events (fallback - update right before submit).
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form && (form.classList.contains('wpcf7-form') || form.closest('.wpcf7'))) {
            // Update values right before submit (synchronously).
            hydrateForms();
        }
    }, true);
})();
