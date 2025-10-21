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
    $signatureKey = $newTokens['signatureKey'];
    $csrfToken = $newTokens['csrfToken'];
} else {
    $securityParams = getSecurityParams();
    $securityToken = $securityParams['securityToken'];
    $apiKey = $securityParams['apiKey'];
    $signatureKey = $securityParams['signatureKey'];
    $csrfToken = $securityParams['csrfToken'];
}

// Get current domain and session ID
 $siteDomain = $_SERVER['HTTP_HOST'];
 $sessionId = session_id();
?>

<script>
    // Security parameters
    const securityToken = "<?php echo $securityToken; ?>";
    const apiKey = "<?php echo $apiKey; ?>";
    const signatureKey = "<?php echo $signatureKey; ?>";
    const csrfToken = "<?php echo $csrfToken; ?>";
    const siteDomain = "<?php echo $siteDomain; ?>";
    const sessionId = "<?php echo $sessionId; ?>";
    
    // Function to sign requests
    function signRequest(data) {
        // Create a string with all parameters sorted alphabetically
        const sortedParams = Object.keys(data)
            .sort()
            .map(key => `${key}=${data[key]}`)
            .join('&');
        
        // Add the signature key
        const stringToSign = sortedParams + signatureKey;
        
        // Create a simple hash
        let hash = 0;
        for (let i = 0; i < stringToSign.length; i++) {
            const char = stringToSign.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        
        return Math.abs(hash).toString(16);
    }
    
    // Function to add security parameters to FormData
    function addSecurityParams(formData, additionalData = {}) {
        // Add required security parameters
        formData.append('security_token', securityToken);
        formData.append('api_key', apiKey);
        formData.append('domain', siteDomain);
        formData.append('session_id', sessionId);
        formData.append('timestamp', Math.floor(Date.now() / 1000));
        
        // Create a data object for signing
        const requestData = {
            security_token: securityToken,
            api_key: apiKey,
            domain: siteDomain,
            session_id: sessionId,
            timestamp: Math.floor(Date.now() / 1000),
            ...additionalData
        };
        
        // Add signature
        const signature = signRequest(requestData);
        formData.append('signature', signature);
        
        return formData;
    }
    
    // Function to add security parameters to URL (for GET requests)
    function addSecurityParamsToUrl(baseUrl, additionalData = {}) {
        const timestamp = Math.floor(Date.now() / 1000);
        
        // Create a data object for signing
        const requestData = {
            security_token: securityToken,
            api_key: apiKey,
            domain: siteDomain,
            session_id: sessionId,
            timestamp: timestamp,
            ...additionalData
        };
        
        // Add signature
        const signature = signRequest(requestData);
        
        // Build URL with parameters
        let url = baseUrl.includes('?') ? baseUrl + '&' : baseUrl + '?';
        url += 'security_token=' + encodeURIComponent(securityToken);
        url += '&api_key=' + encodeURIComponent(apiKey);
        url += '&domain=' + encodeURIComponent(siteDomain);
        url += '&session_id=' + encodeURIComponent(sessionId);
        url += '&timestamp=' + timestamp;
        url += '&signature=' + encodeURIComponent(signature);
        
        // Add additional parameters
        Object.keys(additionalData).forEach(key => {
            url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(additionalData[key]);
        });
        
        return url;
    }
    
    // Function to add security headers to fetch requests
    function addSecurityHeaders(headers = {}, additionalData = {}) {
        const timestamp = Math.floor(Date.now() / 1000);
        
        // Create a data object for signing
        const requestData = {
            security_token: securityToken,
            api_key: apiKey,
            domain: siteDomain,
            session_id: sessionId,
            timestamp: timestamp,
            ...additionalData
        };
        
        // Add signature
        const signature = signRequest(requestData);
        
        // Add security headers
        const securityHeaders = {
            'X-Security-Token': securityToken,
            'X-API-Key': apiKey,
            'X-Domain': siteDomain,
            'X-Session-ID': sessionId,
            'X-Timestamp': timestamp,
            'X-Signature': signature
        };
        
        // Merge with existing headers
        return { ...headers, ...securityHeaders };
    }
    
    // Function to refresh tokens
    async function refreshTokens() {
        try {
            const response = await fetch('refresh_tokens.php', {
                method: 'POST',
                credentials: 'include'
            });
            const data = await response.json();
            if (data.success) {
                // Update global variables
                window.securityToken = data.securityToken;
                window.apiKey = data.apiKey;
                window.signatureKey = data.signatureKey;
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
</script>
