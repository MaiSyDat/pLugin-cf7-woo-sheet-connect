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

    // Persist the very first landing URL and referrer in both sessionStorage and cookie
    // This runs immediately when script loads, not waiting for DOM ready
    function initInitialUrl() {
        var initialUrl = null;
        var initialReferrer = null;
        var currentUrl = window.location.href;
        var currentReferrer = document.referrer || '';
        var isCheckoutPage = currentUrl.indexOf('checkout') !== -1 || currentUrl.indexOf('order-received') !== -1;
        var sessionId = getSessionId();
        
        // First, check sessionStorage (this is the source of truth for current session)
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                initialUrl = sessionStorage.getItem('cwsc_initial_url');
                initialReferrer = sessionStorage.getItem('cwsc_initial_referrer');
                if (initialUrl) {
                    // If we have stored URL in sessionStorage, use it and update cookie with session ID
                    setCookie('cwsc_initial_url', initialUrl, 1);
                    setCookie('cwsc_session_id', sessionId, 1);
                    if (initialReferrer) {
                        setCookie('cwsc_initial_referrer', initialReferrer, 1);
                    }
                    return initialUrl;
                }
            }
        } catch (e) {
            // ignore storage errors
        }
        
        // Check cookie ONLY if session ID matches (same session)
        var cookieSessionId = getCookie('cwsc_session_id');
        var cookieUrl = getCookie('cwsc_initial_url');
        var cookieReferrer = getCookie('cwsc_initial_referrer');
        
        // Only use cookie if session ID matches (same browser session)
        if (cookieSessionId === sessionId && cookieUrl && cookieUrl.indexOf('checkout') === -1 && cookieUrl.indexOf('order-received') === -1) {
            initialUrl = cookieUrl;
            initialReferrer = cookieReferrer;
            try {
                if (typeof window !== 'undefined' && window.sessionStorage) {
                    sessionStorage.setItem('cwsc_initial_url', initialUrl);
                    if (initialReferrer) {
                        sessionStorage.setItem('cwsc_initial_referrer', initialReferrer);
                    }
                }
            } catch (e) {
                // ignore
            }
            return initialUrl;
        }

        // If on checkout/thank you page and don't have initial URL yet, don't set it
        if (isCheckoutPage) {
            // Don't set checkout page as initial URL, return current URL as fallback
            return currentUrl;
        }

        // First time visit in this session - set current URL and referrer as initial
        initialUrl = currentUrl;
        initialReferrer = currentReferrer;
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                sessionStorage.setItem('cwsc_initial_url', initialUrl);
                if (initialReferrer) {
                    sessionStorage.setItem('cwsc_initial_referrer', initialReferrer);
                }
            }
        } catch (e) {
            // ignore
        }

        // Save to cookie for PHP to read with session ID
        setCookie('cwsc_initial_url', initialUrl, 1);
        setCookie('cwsc_session_id', sessionId, 1);
        if (initialReferrer) {
            setCookie('cwsc_initial_referrer', initialReferrer, 1);
        }

        return initialUrl;
    }

    function getInitialUrl() {
        // Always prefer sessionStorage (current session)
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                var url = sessionStorage.getItem('cwsc_initial_url');
                if (url) return url;
            }
        } catch (e) {
            // ignore
        }
        
        // Check cookie with session ID match
        var sessionId = getSessionId();
        var cookieSessionId = getCookie('cwsc_session_id');
        var cookieUrl = getCookie('cwsc_initial_url');
        
        // Only use cookie if session ID matches (same browser session)
        if (cookieSessionId === sessionId && cookieUrl) {
            return cookieUrl;
        }
        
        // If no initial URL found, return current URL (but this should rarely happen)
        // This is only for first page load before initInitialUrl() runs
        return window.location.href;
    }

    function getInitialReferrer() {
        try {
            if (typeof window !== 'undefined' && window.sessionStorage) {
                var ref = sessionStorage.getItem('cwsc_initial_referrer');
                if (ref) return ref;
            }
        } catch (e) {
            // ignore
        }
        
        var cookieRef = getCookie('cwsc_initial_referrer');
        return cookieRef || '';
    }

    function detectSource() {
        var initialUrl = getInitialUrl();
        var initialReferrer = getInitialReferrer();
        var urlParams = new URLSearchParams((initialUrl.split('?')[1] || ''));
        var ua = (navigator.userAgent || '').toLowerCase();
        var lowerUrl = initialUrl.toLowerCase();
        
        function getHost(u) {
            try { return new URL(u).hostname.toLowerCase(); } catch (e) { return ''; }
        }
        var initialHost = getHost(initialUrl);
        var refHost = getHost(initialReferrer);

        var utmSource = (urlParams.get('utm_source') || '').toLowerCase();

        // Priority 1: UTM source parameter (treat as Ads for known platforms)
        if (utmSource) {
            if (utmSource.indexOf('facebook') !== -1 || utmSource.indexOf('instagram') !== -1) {
                return (utmSource.indexOf('instagram') !== -1) ? 'Quảng Cáo Instagram' : 'Quảng Cáo Facebook';
            }
            if (utmSource.indexOf('google') !== -1) {
                return 'Quảng Cáo Google';
            }
            if (utmSource.indexOf('tiktok') !== -1) {
                return 'Quảng Cáo TikTok';
            }
            if (utmSource.indexOf('zalo') !== -1) {
                return 'Quảng Cáo Zalo';
            }
            if (utmSource.indexOf('twitter') !== -1 || utmSource.indexOf('x.com') !== -1 || utmSource.indexOf('x') !== -1) {
                return 'Quảng Cáo X';
            }
            if (utmSource.indexOf('bing') !== -1) {
                return 'Quảng Cáo Bing';
            }
            // Unknown paid source → return the raw utm_source
            return urlParams.get('utm_source');
        }

        // Priority 2: Ad click identifiers
        // Facebook/Instagram Ads: fbclid → if ref/host suggests instagram, label Instagram; else Facebook
        if (lowerUrl.indexOf('fbclid') !== -1 || urlParams.has('fbclid')) {
            if (initialHost.indexOf('instagram.com') !== -1 || refHost.indexOf('instagram.com') !== -1) {
                return 'Quảng Cáo Instagram';
            }
            return 'Quảng Cáo Facebook';
        }
        // Google Ads
        if (lowerUrl.indexOf('gclid') !== -1 || urlParams.has('gclid')) {
            return 'Quảng Cáo Google';
        }
        // Bing Ads
        if (lowerUrl.indexOf('msclkid') !== -1 || urlParams.has('msclkid')) {
            return 'Quảng Cáo Bing';
        }
        // TikTok Ads
        if (lowerUrl.indexOf('ttclid') !== -1 || urlParams.has('ttclid')) {
            return 'Quảng Cáo TikTok';
        }
        // X (Twitter) Ads
        if (lowerUrl.indexOf('twclid') !== -1 || urlParams.has('twclid')) {
            return 'Quảng Cáo X';
        }

        // Priority 3: Organic/Referral sources by known domains
        // Google SEO
        if (lowerUrl.indexOf('srsltid') !== -1 || urlParams.has('srsltid') || refHost.indexOf('google.') !== -1) {
            return 'SEO Google';
        }
        // Instagram
        if (initialHost.indexOf('instagram.com') !== -1 || refHost.indexOf('instagram.com') !== -1 || ua.indexOf('instagram') !== -1) {
            return 'Instagram';
        }
        // X (Twitter)
        if (initialHost.indexOf('x.com') !== -1 || refHost.indexOf('x.com') !== -1 || initialHost.indexOf('twitter.com') !== -1 || refHost.indexOf('twitter.com') !== -1 || initialHost.indexOf('t.co') !== -1 || refHost.indexOf('t.co') !== -1) {
            return 'X';
        }
        // YouTube
        if (initialHost.indexOf('youtube.com') !== -1 || refHost.indexOf('youtube.com') !== -1 || initialHost.indexOf('youtu.be') !== -1 || refHost.indexOf('youtu.be') !== -1) {
            return 'YouTube';
        }
        // Zalo
        if (initialHost.indexOf('zalo') !== -1 || refHost.indexOf('zalo') !== -1 || ua.indexOf('zalo') !== -1) {
            return 'Zalo';
        }
        // TikTok
        if (initialHost.indexOf('tiktok.com') !== -1 || refHost.indexOf('tiktok.com') !== -1 || ua.indexOf('tiktok') !== -1) {
            return 'TikTok';
        }
        // Shopee
        if (initialHost.indexOf('shopee.vn') !== -1 || refHost.indexOf('shopee.vn') !== -1) {
            return 'Shopee';
        }
        // Lazada
        if (initialHost.indexOf('lazada.vn') !== -1 || refHost.indexOf('lazada.vn') !== -1) {
            return 'Lazada';
        }
        // Tiki
        if (initialHost.indexOf('tiki.vn') !== -1 || refHost.indexOf('tiki.vn') !== -1) {
            return 'Tiki';
        }
        // Sendo
        if (initialHost.indexOf('sendo.vn') !== -1 || refHost.indexOf('sendo.vn') !== -1) {
            return 'Sendo';
        }
        // Chợ Tốt
        if (initialHost.indexOf('chotot.com') !== -1 || refHost.indexOf('chotot.com') !== -1) {
            return 'Chợ Tốt';
        }
        // Cốc Cốc
        if (initialHost.indexOf('coccoc.com') !== -1 || refHost.indexOf('coccoc.com') !== -1) {
            return 'Cốc Cốc';
        }
        // Facebook (organic/app)
        if (initialHost.indexOf('facebook.com') !== -1 || refHost.indexOf('facebook.com') !== -1 || ua.indexOf('facebook') !== -1) {
            return 'Facebook';
        }

        return 'Trực Tiếp Trên Web';
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

    function hydrateForms() {
        // Ensure initial URL is set first
        initInitialUrl();
        
        var source = detectSource();
        var orderLink = getInitialUrl(); // Get fresh value after init
        var buyLink = getBuyLink();

        // Save to cookie for PHP to read
        setCookie('cwsc_customer_source', source, 1);
        setCookie('cwsc_initial_url', orderLink, 1); // Update cookie with current initial URL

        // Target CF7 forms on the page
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

    // Initialize on page load (run immediately)
    initInitialUrl();

    // Run hydrateForms when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateForms);
    } else {
        hydrateForms();
    }
    
    // Re-hydrate forms before submission (for CF7)
    // Hook into CF7 before submit event
    document.addEventListener('wpcf7beforesubmit', function() {
        hydrateForms();
    }, true);
    
    // Also hook into form submit events (fallback - update right before submit)
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form && (form.classList.contains('wpcf7-form') || form.closest('.wpcf7'))) {
            // Update values right before submit (synchronously)
            hydrateForms();
        }
    }, true);
})();


