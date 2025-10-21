<?php
// security_header.php - Outputs security parameters for JavaScript

// Prevent direct access
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access denied');
}

// Include security configuration
require_once 'security_config.php';

// Refresh tokens if needed
if (time() > $_SESSION['token_expires']) {
    $newTokens = refreshSecurityTokens();
    $securityToken = $newTokens['securityToken'];
    $apiKey = $newTokens['apiKey'];
    $csrfToken = $newTokens['csrfToken'];
} else {
    $securityParams = getSecurityParams();
    $securityToken = $securityParams['securityToken'];
    $apiKey = $securityParams['apiKey'];
    $csrfToken = $securityParams['csrfToken'];
}

// Get current domain and session ID
 $siteDomain = $_SERVER['HTTP_HOST'];
 $sessionId = session_id();
?>

<script>
    // Security parameters (non-sensitive only)
    const securityToken = "<?php echo $securityToken; ?>";
    const apiKey = "<?php echo $apiKey; ?>";
    const csrfToken = "<?php echo $csrfToken; ?>";
    const siteDomain = "<?php echo $siteDomain; ?>";
    const sessionId = "<?php echo $sessionId; ?>";
    
    // Function to add security parameters to FormData
    function addSecurityParams(formData, additionalData = {}) {
        // Add required security parameters
        formData.append('security_token', securityToken);
        formData.append('api_key', apiKey);
        formData.append('domain', siteDomain);
        formData.append('session_id', sessionId);
        formData.append('timestamp', Math.floor(Date.now() / 1000));
        formData.append('csrf_token', csrfToken);
        
        // Add additional data
        Object.keys(additionalData).forEach(key => {
            formData.append(key, additionalData[key]);
        });
        
        return formData;
    }
    
    // Function to add security parameters to URL (for GET requests)
    function addSecurityParamsToUrl(baseUrl, additionalData = {}) {
        const timestamp = Math.floor(Date.now() / 1000);
        
        // Build URL with parameters
        let url = baseUrl.includes('?') ? baseUrl + '&' : baseUrl + '?';
        url += 'security_token=' + encodeURIComponent(securityToken);
        url += '&api_key=' + encodeURIComponent(apiKey);
        url += '&domain=' + encodeURIComponent(siteDomain);
        url += '&session_id=' + encodeURIComponent(sessionId);
        url += '&timestamp=' + timestamp;
        url += '&csrf_token=' + encodeURIComponent(csrfToken);
        
        // Add additional parameters
        Object.keys(additionalData).forEach(key => {
            url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(additionalData[key]);
        });
        
        return url;
    }
    
    // Function to add security headers to fetch requests
    function addSecurityHeaders(headers = {}, additionalData = {}) {
        const timestamp = Math.floor(Date.now() / 1000);
        
        // Add security headers
        const securityHeaders = {
            'X-Security-Token': securityToken,
            'X-API-Key': apiKey,
            'X-Domain': siteDomain,
            'X-Session-ID': sessionId,
            'X-Timestamp': timestamp,
            'X-CSRF-Token': csrfToken
        };
        
        // Merge with existing headers
        return { ...headers, ...securityHeaders };
    }
    
    // Function to refresh tokens
    async function refreshTokens() {
        try {
            const response = await fetch('refresh_tokens.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });
            const data = await response.json();
            if (data.success) {
                // Update global variables
                window.securityToken = data.securityToken;
                window.apiKey = data.apiKey;
                window.csrfToken = data.csrfToken;
                
                console.log("Security tokens refreshed successfully");
                
                // Show notification
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Security tokens refreshed',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                console.error('Token refresh failed:', data.error);
            }
        } catch (error) {
            console.error('Token refresh error:', error);
        }
    }
    
    // Set up automatic token refresh
    setInterval(refreshTokens, 15 * 60 * 1000); // Every 15 minutes
    
    // Function to check if tokens are about to expire
    function checkTokenExpiration() {
        // This is a simplified check - in a real app, you would track the actual expiration time
        // For now, we'll just refresh tokens periodically
        refreshTokens();
    }
    
    // Add CSRF token to all forms
    document.addEventListener('DOMContentLoaded', function() {
        // Add CSRF token to all forms
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        });
        
        // Check token expiration on page load
        checkTokenExpiration();
    });
    
    // Secure AJAX setup for jQuery
    if (typeof $ !== 'undefined') {
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                // Add CSRF token to non-GET requests
                if (settings.type !== 'GET' && !settings.crossDomain) {
                    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                }
            }
        });
    }
</script>
