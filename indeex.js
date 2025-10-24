document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing JavaScript...');
    
    // Global variables
    let selectedGateway = 'gate/stripe1$.php';
    let isProcessing = false;
    let isStopping = false;
    let activeRequests = 0;
    let cardQueue = [];
    const MAX_RETRIES = 2;
    let abortControllers = [];
    let totalCards = 0;
    let chargedCards = [];
    let approvedCards = [];
    let threeDSCards = [];
    let declinedCards = [];
    let sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    let sidebarOpen = false;
    let generatedCardsData = [];
    let activityUpdateInterval = null;
    let lastActivityUpdate = 0;
    let API_KEY = null; // Will be loaded from refresh_cache.php
    let keyRotationInterval = null;
    let isApiKeyValid = false;
    const ROTATE_SECRET_KEY = 'vF8mP2YkQ9rGxBzH1tEwU7sJcL0dNqR'; // Hardcoded secret key

    // Dynamic MAX_CONCURRENT based on selected gateway
    let maxConcurrent = 10; // Default for stripe1$ 
    // Load API key from refresh_cache.php using POST with secret key
    function loadApiKey() {
        return fetch('/refresh_cache.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            },
            body: JSON.stringify({ lund: ROTATE_SECRET_KEY })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Failed to load API key: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.apiKey) {
                API_KEY = data.apiKey;
                console.log('API key loaded successfully');
                
                // Validate the API key
                return validateApiKey(API_KEY).then(valid => {
                    if (valid) {
                        isApiKeyValid = true;
                        console.log('API key validated successfully');
                        return true;
                    } else {
                        console.error('API key validation failed');
                        isApiKeyValid = false;
                        return false;
                    }
                });
            } else {
                throw new Error('Invalid API key response');
            }
        })
        .catch(error => {
            console.error('Error loading API key:', error);
            isApiKeyValid = false;
            return false;
        });
    }

    // Validate API key using validkey.php
    function validateApiKey(apiKey) {
        return fetch('/gate/validkey.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-KEY': apiKey
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Validation failed: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            return data.valid === true;
        })
        .catch(error => {
            console.error('API key validation error:', error);
            return false;
        });
    }

    // Start rotating API keys every hour
    function startKeyRotation() {
        // Clear any existing interval
        if (keyRotationInterval) {
            clearInterval(keyRotationInterval);
        }
        
        // Rotate keys every hour (3600000 milliseconds)
        keyRotationInterval = setInterval(() => {
            console.log('Rotating API key...');
            loadApiKey().then(success => {
                if (success) {
                    console.log('API key rotated successfully');
                    // Show notification
                    if (window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: 'API Key Rotated',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                } else {
                    console.error('Failed to rotate API key');
                }
            });
        }, 3600000);
        
        console.log('API key rotation started. Keys will rotate every hour.');
    }

    // Get current API key with fallback
    function getCurrentApiKey() {
        if (!API_KEY) {
            console.warn('API key is not set!');
        }
        return API_KEY || '';
    }

    // Check if API key is valid
    function isApiKeyValidated() {
        return isApiKeyValid;
    }

    // Initialize the application
    function initializeApp() {
        // Disable copy, context menu, and dev tools, but allow pasting in the textarea
        document.addEventListener('contextmenu', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && 
                e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') {
                e.preventDefault();
            }
        });
        
        document.addEventListener('copy', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && 
                e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') {
                e.preventDefault();
            }
        });
        
        document.addEventListener('cut', e => {
            if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && 
                e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') {
                e.preventDefault();
            }
        });
        
        document.addEventListener('paste', e => {
            if (e.target.id === 'cardInput' || e.target.id === 'binInput' || 
                e.target.id === 'cvvInput' || e.target.id === 'yearInput') {
                const pastedText = e.clipboardData.getData('text');
                const cursorPos = e.target.selectionStart;
                const textBefore = e.target.value.substring(0, cursorPos);
                const textAfter = e.target.value.substring(e.target.selectionEnd);
                e.target.value = textBefore + pastedText + textAfter;
                e.target.selectionStart = e.target.selectionEnd = cursorPos + pastedText.length;
                e.preventDefault();
                if (e.target.id === 'cardInput') updateCardCount();
            } else {
                e.preventDefault();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 85 || e.keyCode === 73 || 
                e.keyCode === 74 || e.keyCode === 83)) {
                if (e.target.id !== 'cardInput' && e.target.id !== 'binInput' && 
                    e.target.id !== 'cvvInput' && e.target.id !== 'yearInput') {
                    e.preventDefault();
                }
            } else if (e.keyCode === 123 || (e.ctrlKey && e.shiftKey && 
                (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67))) {
                e.preventDefault();
            }
        });

        localStorage.setItem('theme', 'light');
        document.body.setAttribute('data-theme', 'light');

        // Theme toggle function
        function toggleTheme() {
            const body = document.body;
            const theme = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = document.querySelector('.theme-toggle-slider i');
            if (icon) {
                icon.className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            }
            
            if (window.Swal) {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'success',
                    title: `${theme === 'light' ? 'Light' : 'Dark'} Mode`,
                    showConfirmButton: false, 
                    timer: 1500
                });
            }
        }

        // Page navigation function
        function showPage(pageName) {
            document.querySelectorAll('.page-section').forEach(page => {
                page.classList.remove('active');
            });
            const pageElement = document.getElementById('page-' + pageName);
            if (pageElement) {
                pageElement.classList.add('active');
            }
            
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active');
            });
            
            if (event && event.target) {
                const eventTarget = event.target.closest('.sidebar-link');
                if (eventTarget) {
                    eventTarget.classList.add('active');
                }
            }
        }

        // Sidebar functions
        function closeSidebar() {
            sidebarOpen = false;
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            if (sidebar) sidebar.classList.remove('open');
            if (mainContent) mainContent.classList.remove('sidebar-open');
        }

        // Gateway settings functions
        function openGatewaySettings() {
            const gatewaySettings = document.getElementById('gatewaySettings');
            if (gatewaySettings) {
                gatewaySettings.classList.add('active');
                const radio = document.querySelector(`input[value="${selectedGateway}"]`);
                if (radio) radio.checked = true;
            }
        }

        function closeGatewaySettings() {
            const gatewaySettings = document.getElementById('gatewaySettings');
            if (gatewaySettings) {
                gatewaySettings.classList.remove('active');
            }
        }

        function saveGatewaySettings() {
            const selected = document.querySelector('input[name="gateway"]:checked');
            if (selected) {
                selectedGateway = selected.value;
                
                // Update maxConcurrent based on selected gateway
                if (selectedGateway === 'gate/stripe1$.php') {
                    maxConcurrent = 10; // 10 concurrent requests for Stripe 1$                 } else if (selectedGateway === 'gate/stripegbp.php' || selectedGateway === 'gate/paypal0.1$.php') {
                    maxConcurrent = 5; // 5 concurrent requests for Stripe GBP and PayPal
                } else {
                    maxConcurrent = 3; // 3 concurrent requests for all other gateways
                }
                
                const gatewayName = selected.parentElement.querySelector('.gateway-option-name');
                const nameText = gatewayName ? gatewayName.textContent.trim() : 'Unknown Gateway';
                
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'Gateway Updated!',
                        text: `Now using: ${nameText}`,
                        confirmButtonColor: '#10b981'
                    });
                }
                closeGatewaySettings();
            } else {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning', 
                        title: 'No Gateway Selected',
                        text: 'Please select a gateway', 
                        confirmButtonColor: '#f59e0b'
                    });
                }
            }
        }

        // Card counting function
        function updateCardCount() {
            const cardInput = document.getElementById('cardInput');
            const cardCount = document.getElementById('cardCount');
            if (cardInput && cardCount) {
                const lines = cardInput.value.trim().split('\n').filter(line => line.trim() !== '');
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                cardCount.innerHTML = `<i class="fas fa-list"></i> ${validCards.length} valid cards detected (max 1000)`;
            }
        }

        // Stats update function
        function updateStats(total, charged, approved, threeDS, declined) {
            const totalElement = document.getElementById('total-value');
            const chargedElement = document.getElementById('charged-value');
            const approvedElement = document.getElementById('approved-value');
            const threedElement = document.getElementById('threed-value');
            const declinedElement = document.getElementById('declined-value');
            const checkedElement = document.getElementById('checked-value');
            
            if (totalElement) totalElement.textContent = total;
            if (chargedElement) chargedElement.textContent = charged;
            if (approvedElement) approvedElement.textContent = approved;
            if (threedElement) threedElement.textContent = threeDS;
            if (declinedElement) declinedElement.textContent = declined;
            if (checkedElement) checkedElement.textContent = `${charged + approved + threeDS + declined} / ${total}`;
        }

        // Result display function
        function addResult(card, status, response) {
            const resultsList = document.getElementById('checkingResultsList');
            if (!resultsList) return;
            
            const cardClass = status.toLowerCase();
            const icon = (status === 'APPROVED' || status === 'CHARGED' || status === '3DS') ? 
                'fas fa-check-circle' : 'fas fa-times-circle';
            const color = (status === 'APPROVED' || status === 'CHARGED' || status === '3DS') ? 
                'var(--success-green)' : 'var(--declined-red)';
            
            const resultDiv = document.createElement('div');
            resultDiv.className = `stat-card ${cardClass} result-item`;
            resultDiv.innerHTML = `
                <div class="stat-icon" style="background: rgba(var(${color}), 0.15); color: ${color}; width: 20px; height: 20px; font-size: 0.8rem;">
                    <i class="${icon}"></i>
                </div>
                <div class="stat-content">
                    <div>
                        <div class="stat-value" style="font-size: 0.9rem;">${card.displayCard}</div>
                        <div class="stat-label" style="color: ${color}; font-size: 0.7rem;">${status} - ${response}</div>
                    </div>
                    <button class="copy-btn"><i class="fas fa-copy"></i></button>
                </div>
            `;
            
            // Add event listener to the copy button
            const copyButton = resultDiv.querySelector('.copy-btn');
            if (copyButton) {
                copyButton.addEventListener('click', () => copyToClipboard(card.displayCard));
            }
            
            // Remove empty state if exists
            if (resultsList.classList.contains('empty-state')) {
                resultsList.classList.remove('empty-state');
                resultsList.innerHTML = '';
            }
            
            resultsList.insertBefore(resultDiv, resultsList.firstChild);
            
            // Add to activity feed
            addActivityItem(card, status);
        }

        // Activity feed function
        function addActivityItem(card, status) {
            const activityList = document.getElementById('activityList');
            if (!activityList) return;
            
            // Remove empty state if it exists
            if (activityList.querySelector('.empty-state')) {
                activityList.innerHTML = '';
            }
            
            const activityItem = document.createElement('div');
            activityItem.className = `activity-item ${status.toLowerCase()}`;
            
            // Format time
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            activityItem.innerHTML = `
                <div class="activity-icon">
                    ${status === 'CHARGED' ? '<i class="fas fa-bolt"></i>' : 
                      status === 'APPROVED' ? '<i class="fas fa-check-circle"></i>' :
                      status === '3DS' ? '<i class="fas fa-lock"></i>' :
                      '<i class="fas fa-times-circle"></i>'}
                </div>
                <div class="activity-content">
                    <div class="activity-card">${card.displayCard}</div>
                    <div class="activity-status">${status}</div>
                </div>
                <div class="activity-time">${timeString}</div>
            `;
            
            // Add to the top of the list
            activityList.insertBefore(activityItem, activityList.firstChild);
            
            // Keep only the last 5 activities
            while (activityList.children.length > 5) {
                activityList.removeChild(activityList.lastChild);
            }
        }

        // Generated cards display function
        function displayGeneratedCards(cards) {
            const cardsList = document.getElementById('generatedCardsList');
            if (!cardsList) return;
            
            // Remove empty state if exists
            if (cardsList.classList.contains('empty-state')) {
                cardsList.classList.remove('empty-state');
                cardsList.innerHTML = '';
            }
            
            // Create a single container for all cards
            const cardsContainer = document.createElement('div');
            cardsContainer.className = 'generated-cards-container';
            cardsContainer.textContent = cards.join('\n');
            
            // Clear previous cards and add the new container
            cardsList.innerHTML = '';
            cardsList.appendChild(cardsContainer);
            
            // Show action buttons
            const copyAllBtn = document.getElementById('copyAllBtn');
            const clearAllBtn = document.getElementById('clearAllBtn');
            if (copyAllBtn) copyAllBtn.style.display = 'flex';
            if (clearAllBtn) clearAllBtn.style.display = 'flex';
            
            // Store the cards data
            generatedCardsData = cards;
        }

        // Clipboard functions
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                if (window.Swal) {
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        icon: 'success',
                        title: 'Copied!', 
                        showConfirmButton: false, 
                        timer: 1500
                    });
                }
            }).catch(err => {
                console.error('Failed to copy: ', err);
                if (window.Swal) {
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        icon: 'error',
                        title: 'Failed to copy!', 
                        showConfirmButton: false, 
                        timer: 1500
                    });
                }
            });
        }

        function copyAllGeneratedCards() {
            if (generatedCardsData.length === 0) {
                if (window.Swal) {
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        icon: 'warning',
                        title: 'No cards to copy', 
                        showConfirmButton: false, 
                        timer: 1500
                    });
                }
                return;
            }
            
            const allCardsText = generatedCardsData.join('\n');
            navigator.clipboard.writeText(allCardsText).then(() => {
                if (window.Swal) {
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        icon: 'success',
                        title: 'All cards copied!', 
                        showConfirmButton: false, 
                        timer: 1500
                    });
                }
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        function clearAllGeneratedCards() {
            const cardsList = document.getElementById('generatedCardsList');
            if (cardsList) {
                cardsList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Cards Generated Yet</h3>
                        <p>Generate cards to see them here</p>
                    </div>
                `;
            }
            
            const copyAllBtn = document.getElementById('copyAllBtn');
            const clearAllBtn = document.getElementById('clearAllBtn');
            if (copyAllBtn) copyAllBtn.style.display = 'none';
            if (clearAllBtn) clearAllBtn.style.display = 'none';
            generatedCardsData = [];
            
            if (window.Swal) {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'success',
                    title: 'Cleared!', 
                    showConfirmButton: false, 
                    timer: 1500
                });
            }
        }

        // Filter results function
        function filterResults(filter) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (event && event.target) {
                event.target.classList.add('active');
            }
            
            const items = document.querySelectorAll('.result-item');
            items.forEach(item => {
                const status = item.className.split(' ')[1];
                item.style.display = filter === 'all' || status === filter ? 'block' : 'none';
            });
            
            if (window.Swal) {
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    icon: 'info',
                    title: `Filter: ${filter.charAt(0).toUpperCase() + filter.slice(1)}`,
                    showConfirmButton: false, 
                    timer: 1500
                });
            }
        }

        // Gateway response parser
        function parseGatewayResponse(response) {
            let status = 'DECLINED';
            let message = 'Card declined';
            
            // Handle different response types
            if (typeof response === 'string') {
                // Try to parse as JSON first
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    // Not JSON, continue with string processing
                    const responseStr = response.toUpperCase();
                    
                    if (responseStr.includes('CHARGED')) {
                        status = 'CHARGED';
                    } else if (responseStr.includes('APPROVED')) {
                        status = 'APPROVED';
                    } else if (responseStr.includes('3D_AUTHENTICATION') || 
                              responseStr.includes('3DS') || 
                              responseStr.includes('THREE_D_SECURE') ||
                              responseStr.includes('REDIRECT')) {
                        status = '3DS';
                    } else if (responseStr.includes('AUTHENTICATION FAILED') || 
                              responseStr.includes('INVALID API KEY') || 
                              responseStr.includes('MISSING API KEY') ||
                              responseStr.includes('UNAUTHORIZED') ||
                              responseStr.includes('401')) {
                        status = 'ERROR';
                        message = 'Authentication failed: Invalid or missing API key';
                    }
                    
                    message = response;
                    return { status, message };
                }
            }
            
            // Now we have a JSON object
            if (typeof response === 'object') {
                // Check for status field in various formats
                if (response.status) {
                    status = String(response.status).toUpperCase();
                } else if (response.result) {
                    status = String(response.result).toUpperCase();
                } else if (response.response) {
                    // Try to extract status from response field
                    const responseStr = String(response.response).toUpperCase();
                    if (responseStr.includes('CHARGED')) {
                        status = 'CHARGED';
                    } else if (responseStr.includes('APPROVED')) {
                        status = 'APPROVED';
                    } else if (responseStr.includes('3D') || responseStr.includes('THREE_D')) {
                        status = '3DS';
                    } else if (responseStr.includes('AUTHENTICATION FAILED') || 
                              responseStr.includes('INVALID API KEY') || 
                              responseStr.includes('MISSING API KEY') ||
                              responseStr.includes('UNAUTHORIZED') ||
                              responseStr.includes('401')) {
                        status = 'ERROR';
                        message = 'Authentication failed: Invalid or missing API key';
                    }
                }
                
                // Get message from various possible fields
                message = response.message || 
                         response.response || 
                         response.result || 
                         response.error || 
                         response.description ||
                         response.reason ||
                         JSON.stringify(response);
                         
                // Check for HTTP status code if available
                if (response.httpCode && response.httpCode === 401) {
                    status = 'ERROR';
                    message = 'Authentication failed: Invalid or missing API key';
                }
            }
            
            // Normalize status to one of our standard values
            if (status !== 'CHARGED' && status !== 'APPROVED' && status !== '3DS' && status !== 'ERROR') {
                status = 'DECLINED';
            }
            
            return { status, message };
        }

        // Card processing function
        function processCard(card, controller, retryCount = 0) {
            if (!isProcessing) return null;

            return new Promise((resolve) => {
                const formData = new FormData();
                let normalizedYear = card.exp_year;
                if (normalizedYear.length === 2) {
                    normalizedYear = (parseInt(normalizedYear) < 50 ? '20' : '19') + normalizedYear;
                }
                formData.append('card[number]', card.number);
                formData.append('card[exp_month]', card.exp_month);
                formData.append('card[exp_year]', normalizedYear);
                formData.append('card[cvc]', card.cvv);

                const apiKey = getCurrentApiKey();
                console.log(`X-API-KEY header: ${apiKey ? '[REDACTED]' : 'NOT SET'}`);

                const statusLog = document.getElementById('statusLog');
                if (statusLog) statusLog.textContent = `Processing card: ${card.displayCard}`;
                console.log(`Starting request for card: ${card.displayCard}`);

                fetch(selectedGateway, {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-API-KEY': apiKey
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        // Check for 401 Unauthorized
                        if (response.status === 401) {
                            throw new Error('Authentication failed: Invalid or missing API key');
                        }
                        throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(data => {
                    let parsedData;
                    try {
                        parsedData = JSON.parse(data);
                    } catch (e) {
                        parsedData = data;
                    }
                    const parsedResponse = parseGatewayResponse(parsedData);
                    console.log(`Completed request for card: ${card.displayCard}, Status: ${parsedResponse.status}, Response: ${parsedResponse.message}`);
                    
                    if (parsedResponse.status === 'ERROR') {
                        // Show authentication error
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Authentication Error',
                                text: parsedResponse.message,
                                icon: 'error',
                                confirmButtonColor: '#ec4899'
                            });
                        }
                        
                        // Try to refresh the API key
                        refreshApiKey();
                    }
                    
                    resolve({
                        status: parsedResponse.status,
                        response: parsedResponse.message,
                        card: card,
                        displayCard: card.displayCard
                    });
                })
                .catch(error => {
                    const statusLog = document.getElementById('statusLog');
                    if (statusLog) statusLog.textContent = `Error on card: ${card.displayCard} - ${error.message}`;
                    console.error(`Error for card: ${card.displayCard}, Error: ${error.message}`);

                    if (error.name === 'AbortError') {
                        resolve(null);
                        return;
                    }

                    // Check for authentication error
                    if (error.message.includes('Authentication failed') || error.message.includes('401')) {
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Authentication Error',
                                text: error.message,
                                icon: 'error',
                                confirmButtonColor: '#ec4899'
                            });
                        }
                        
                        // Try to refresh the API key
                        refreshApiKey().then(() => {
                            // Retry the request with the new API key
                            if (retryCount < MAX_RETRIES && isProcessing) {
                                setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
                            } else {
                                resolve({
                                    status: 'ERROR',
                                    response: error.message,
                                    card: card,
                                    displayCard: card.displayCard
                                });
                            }
                        });
                        return;
                    }

                    let errorResponse = `Declined [Request failed: ${error.message}]`;
                    if (error.message.includes('HTTP error')) {
                        try {
                            const errorData = JSON.parse(error.message.split('HTTP error! ')[1]);
                            const parsedError = parseGatewayResponse(errorData);
                            errorResponse = parsedError.message;
                        } catch (e) {
                            // Use raw error message
                        }
                    }

                    if ((error.message.includes('HTTP error') && error.message.match(/status: (0|5\d{2})/)) && retryCount < MAX_RETRIES && isProcessing) {
                        setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
                    } else {
                        resolve({
                            status: 'DECLINED',
                            response: errorResponse,
                            card: card,
                            displayCard: card.displayCard
                        });
                    }
                });
            });
        }

        // Function to refresh API key when authentication fails
        function refreshApiKey() {
            console.log('Refreshing API key due to authentication error...');
            
            return fetch('/refresh_cache.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify({ secret_key: ROTATE_SECRET_KEY })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to refresh API key: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.apiKey) {
                    API_KEY = data.apiKey;
                    
                    // Validate the new API key
                    return validateApiKey(API_KEY).then(valid => {
                        if (valid) {
                            isApiKeyValid = true;
                            console.log('API key refreshed and validated successfully');
                            
                            if (window.Swal) {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'success',
                                    title: 'API Key Refreshed',
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            }
                            
                            return true;
                        } else {
                            console.error('Refreshed API key validation failed');
                            isApiKeyValid = false;
                            
                            if (window.Swal) {
                                Swal.fire({
                                    title: 'API Key Validation Failed',
                                    text: 'The refreshed API key is not valid. Please try again later.',
                                    icon: 'error',
                                    confirmButtonColor: '#ec4899'
                                });
                            }
                            
                            return false;
                        }
                    });
                } else {
                    throw new Error('Invalid API key response during refresh');
                }
            })
            .catch(error => {
                console.error('Error refreshing API key:', error);
                
                if (window.Swal) {
                    Swal.fire({
                        title: 'API Key Refresh Failed',
                        text: 'Could not refresh API key. Please try again later.',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                
                return false;
            });
        }

        // Main card processing function
        async function processCards() {
            if (isProcessing) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Processing in progress',
                        text: 'Please wait until current process completes',
                        icon: 'warning',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }

            // Check if API key is valid before processing
            if (!isApiKeyValidated()) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'API Key Invalid',
                        text: 'The API key is not valid. Attempting to refresh...',
                        icon: 'warning',
                        confirmButtonColor: '#f59e0b'
                    });
                }
                
                const refreshed = await refreshApiKey();
                if (!refreshed) {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'API Key Error',
                            text: 'Could not validate API key. Please try again later.',
                            icon: 'error',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                    return;
                }
            }

            const cardInput = document.getElementById('cardInput');
            const cardText = cardInput ? cardInput.value.trim() : '';
            const lines = cardText.split('\n').filter(line => line.trim());
            const validCards = lines
                .map(line => line.trim())
                .filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()))
                .map(line => {
                    const [number, exp_month, exp_year, cvc] = line.split('|');
                    return { number, exp_month, exp_year, cvv: cvc, displayCard: `${number}|${exp_month}|${exp_year}|${cvc}` };
                });

            if (validCards.length === 0) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'No valid cards!',
                        text: 'Please check your card format',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }

            if (validCards.length > 1000) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Limit exceeded!',
                        text: 'Maximum 1000 cards allowed!',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }

            isProcessing = true;
            isStopping = false;
            activeRequests = 0;
            abortControllers = [];
            cardQueue = [...validCards];
            totalCards = validCards.length;
            chargedCards = [];
            approvedCards = [];
            threeDSCards = [];
            declinedCards = [];
            
            // Store in session storage
            sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
            sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
            sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
            sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
            
            updateStats(totalCards, 0, 0, 0, 0);
            
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const loader = document.getElementById('loader');
            const checkingResultsList = document.getElementById('checkingResultsList');
            const statusLog = document.getElementById('statusLog');
            
            if (startBtn) startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = false;
            if (loader) loader.style.display = 'block';
            if (checkingResultsList) checkingResultsList.innerHTML = '';
            if (statusLog) statusLog.textContent = `Starting processing with ${maxConcurrent} concurrent requests...`;

            // Create a worker pool for true concurrency
            const workers = Array(maxConcurrent).fill(null).map(() => ({
                busy: false,
                currentCard: null,
                controller: null
            }));

            // Function to assign a card to a worker
            const assignCardToWorker = async (workerIndex) => {
                if (!isProcessing || cardQueue.length === 0 || isStopping) {
                    // Check if all workers are idle
                    if (workers.every(w => !w.busy)) {
                        finishProcessing();
                    }
                    return;
                }

                const worker = workers[workerIndex];
                if (worker.busy) return;

                // Get next card from queue
                const card = cardQueue.shift();
                if (!card) {
                    // No more cards, check if all workers are idle
                    if (workers.every(w => !w.busy)) {
                        finishProcessing();
                    }
                    return;
                }

                worker.busy = true;
                worker.currentCard = card;
                worker.controller = new AbortController();
                abortControllers.push(worker.controller);
                activeRequests++;

                console.log(`Worker ${workerIndex} processing card: ${card.displayCard} (Active: ${activeRequests}/${maxConcurrent})`);

                try {
                    const result = await processCard(card, worker.controller);
                    
                    if (result) {
                        const cardEntry = { response: result.response, displayCard: result.displayCard };
                        if (result.status === 'CHARGED') {
                            chargedCards.push(cardEntry);
                            sessionStorage.setItem(`chargedCards-${sessionId}`, JSON.stringify(chargedCards));
                        } else if (result.status === 'APPROVED') {
                            approvedCards.push(cardEntry);
                            sessionStorage.setItem(`approvedCards-${sessionId}`, JSON.stringify(approvedCards));
                        } else if (result.status === '3DS') {
                            threeDSCards.push(cardEntry);
                            sessionStorage.setItem(`threeDSCards-${sessionId}`, JSON.stringify(threeDSCards));
                        } else {
                            declinedCards.push(cardEntry);
                            sessionStorage.setItem(`declinedCards-${sessionId}`, JSON.stringify(declinedCards));
                        }

                        addResult(card, result.status, result.response);
                        updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
                    }
                } catch (error) {
                    console.error(`Worker ${workerIndex} error:`, error);
                } finally {
                    // Mark worker as idle and assign next card
                    worker.busy = false;
                    worker.currentCard = null;
                    activeRequests--;
                    
                    // Immediately assign next card to this worker
                    setTimeout(() => assignCardToWorker(workerIndex), 0);
                }
            };

            // Start all workers
            for (let i = 0; i < maxConcurrent; i++) {
                setTimeout(() => assignCardToWorker(i), i * 10); // Stagger start slightly to avoid browser limits
            }
        }

        // Finish processing
        function finishProcessing() {
            isProcessing = false;
            isStopping = false;
            activeRequests = 0;
            cardQueue = [];
            abortControllers = [];
            
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const loader = document.getElementById('loader');
            const cardInput = document.getElementById('cardInput');
            const statusLog = document.getElementById('statusLog');
            
            if (startBtn) startBtn.disabled = false;
            if (stopBtn) stopBtn.disabled = true;
            if (loader) loader.style.display = 'none';
            if (cardInput) cardInput.value = '';
            if (statusLog) statusLog.textContent = 'Processing completed.';
            
            updateCardCount();
            
            if (window.Swal) {
                Swal.fire({
                    title: 'Processing complete!',
                    text: 'All cards results below.',
                    icon: 'success',
                    confirmButtonColor: '#ec4899'
                });
            }
        }

        // Card generator functions
        function setYearRnd() {
            const yearInput = document.getElementById('yearInput');
            if (yearInput) yearInput.value = 'rnd';
        }

        function setCvvRnd() {
            const cvvInput = document.getElementById('cvvInput');
            if (cvvInput) cvvInput.value = 'rnd';
        }

        function generateCards() {
            const binInput = document.getElementById('binInput');
            const monthSelect = document.getElementById('monthSelect');
            const yearInput = document.getElementById('yearInput');
            const cvvInput = document.getElementById('cvvInput');
            const numCardsInput = document.getElementById('numCardsInput');
            
            const bin = binInput ? binInput.value.trim() : '';
            const month = monthSelect ? monthSelect.value : 'rnd';
            let year = yearInput ? yearInput.value.trim() : 'rnd';
            const cvv = cvvInput ? cvvInput.value.trim() : 'rnd';
            const numCards = numCardsInput ? parseInt(numCardsInput.value) : 10;
            
            if (!/^\d{6,8}$/.test(bin)) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Invalid BIN!',
                        text: 'Please enter a valid 6-8 digit BIN',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }
            
            if (isNaN(numCards) || numCards < 1 || numCards > 5000) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Invalid Number!',
                        text: 'Please enter a number between 1 and 5000',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }
            
            if (numCards > 1000) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Large Number of Cards',
                        text: `You are about to generate ${numCards} cards. This may take a while and use significant resources. Continue?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, generate'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            continueGenerateCards(bin, month, year, cvv, numCards);
                        }
                    });
                }
            } else {
                continueGenerateCards(bin, month, year, cvv, numCards);
            }
        }

        function continueGenerateCards(bin, month, year, cvv, numCards) {
            if (year !== 'rnd') {
                if (year.length === 2) {
                    const currentYear = new Date().getFullYear();
                    const currentCentury = Math.floor(currentYear / 100) * 100;
                    const twoDigitYear = parseInt(year);
                    year = (twoDigitYear < 50 ? currentCentury : currentCentury - 100) + twoDigitYear;
                }
                
                if (!/^\d{4}$/.test(year) || parseInt(year) < 2000 || parseInt(year) > 2099) {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Invalid Year!',
                            text: 'Please enter a valid year (e.g., 2025, 30, or "rnd")',
                            icon: 'error',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                    return;
                }
            }
            
            if (cvv !== 'rnd' && !/^\d{3,4}$/.test(cvv)) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Invalid CVV!',
                        text: 'Please enter a valid 3-4 digit CVV or "rnd"',
                        icon: 'error',
                        confirmButtonColor: '#ec4899'
                    });
                }
                return;
            }
            
            let params = bin;
            if (month !== 'rnd') params += '|' + month;
            if (year !== 'rnd') params += '|' + year;
            if (cvv !== 'rnd') params += '|' + cvv;
            
            const genLoader = document.getElementById('genLoader');
            const genStatusLog = document.getElementById('genStatusLog');
            
            if (genLoader) genLoader.style.display = 'block';
            if (genStatusLog) genStatusLog.textContent = 'Generating cards...';
            
            const url = `/gate/ccgen.php?bin=${encodeURIComponent(params)}&num=${numCards}&format=0`;
            console.log(`Fetching cards from: ${url}`);
            
            const apiKey = getCurrentApiKey();
            console.log(`X-API-KEY header for ccgen: ${apiKey ? '[REDACTED]' : 'NOT SET'}`);
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-API-KEY': apiKey
                }
            })
            .then(response => {
                if (!response.ok) {
                    // Check for 401 Unauthorized
                    if (response.status === 401) {
                        throw new Error('Authentication failed: Invalid or missing API key');
                    }
                    throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
                }
                return response.json();
            })
            .then(response => {
                if (genLoader) genLoader.style.display = 'none';
                
                if (response.cards && Array.isArray(response.cards) && response.cards.length > 0) {
                    if (genStatusLog) genStatusLog.textContent = `Generated ${response.cards.length} cards successfully!`;
                    displayGeneratedCards(response.cards);
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Success!',
                            text: `Generated ${response.cards.length} cards`,
                            icon: 'success',
                            confirmButtonColor: '#10b981'
                        });
                    }
                } else if (response.error) {
                    if (genStatusLog) genStatusLog.textContent = 'Error: ' + response.error;
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Error!',
                            text: response.error,
                            icon: 'error',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                } else {
                    if (genStatusLog) genStatusLog.textContent = 'No cards generated';
                    if (window.Swal) {
                        Swal.fire({
                            title: 'No Cards!',
                            text: 'Could not generate cards with the provided parameters',
                            icon: 'warning',
                            confirmButtonColor: '#f59e0b'
                        });
                    }
                }
            })
            .catch(error => {
                if (genLoader) genLoader.style.display = 'none';
                if (genStatusLog) genStatusLog.textContent = 'Error: ' + error.message;
                console.error(`Card generation error: ${error.message}`);
                
                // Check for authentication error
                if (error.message.includes('Authentication failed') || error.message.includes('401')) {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Authentication Error',
                            text: error.message,
                            icon: 'error',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                    
                    // Try to refresh the API key
                    refreshApiKey();
                } else {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Error!',
                            text: `Failed to generate cards: ${error.message}`,
                            icon: 'error',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                }
            });
        }

        // Logout function
        function logout() {
            if (window.Swal) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You will be logged out and returned to the login page.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#d1d5db',
                    confirmButtonText: 'Yes, logout'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Clear key rotation interval
                        if (keyRotationInterval) {
                            clearInterval(keyRotationInterval);
                        }
                        
                        sessionStorage.clear();
                        window.location.href = 'login.php';
                    }
                });
            } else {
                // Fallback if Swal is not available
                if (confirm('Are you sure you want to logout?')) {
                    if (keyRotationInterval) {
                        clearInterval(keyRotationInterval);
                    }
                    sessionStorage.clear();
                    window.location.href = 'login.php';
                }
            }
        }

        // User activity update function
        function updateUserActivity() {
            console.log("Updating user activity at", new Date().toISOString());
            
            // Skip if an update is already in progress
            if (window.activityRequest) {
                console.log("Previous activity request still pending, skipping...");
                return;
            }
            
            // Create a new AbortController for this request
            const controller = new AbortController();
            window.activityRequest = controller;
            
            // Set a longer timeout (30 seconds) to prevent premature abortion
            const timeoutId = setTimeout(() => {
                if (window.activityRequest === controller) {
                    controller.abort();
                    window.activityRequest = null;
                    console.log("Activity request timed out after 30 seconds");
                }
            }, 30000);
            
            const apiKey = getCurrentApiKey();
            console.log(`X-API-KEY header for activity update: ${apiKey ? '[REDACTED]' : 'NOT SET'}`);
            
            fetch('/update_activity.php', {
                method: 'GET',
                signal: controller.signal,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache',
                    'X-API-KEY': apiKey
                }
            })
            .then(response => {
                // Clear the timeout
                clearTimeout(timeoutId);
                window.activityRequest = null;
                
                if (!response.ok) {
                    // Check for 401 Unauthorized
                    if (response.status === 401) {
                        throw new Error('Authentication failed: Invalid or missing API key');
                    }
                    throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
                }
                
                return response.json();
            })
            .then(data => {
                console.log("Activity update response:", data);
                
                if (data.success) {
                    // Update online count
                    const onlineCountElement = document.getElementById('onlineCount');
                    if (onlineCountElement) {
                        onlineCountElement.textContent = data.count;
                        console.log("Updated online count to:", data.count);
                    } else {
                        console.error("Element #onlineCount not found");
                    }
                    
                    // Update current user profile
                    const currentUser = data.users ? data.users.find(u => u.is_currently_online) : null;
                    if (currentUser) {
                        // Update profile picture
                        const profilePicElement = document.querySelector('.user-avatar');
                        if (profilePicElement) {
                            profilePicElement.src = currentUser.photo_url || 
                                `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.name ? currentUser.name[0] : 'U')}&background=3b82f6&color=fff&size=64`;
                            console.log("Updated profile picture");
                        } else {
                            console.error("Element .user-avatar not found");
                        }
                        
                        // Update user name
                        const userNameElement = document.querySelector('.user-name');
                        if (userNameElement) {
                            userNameElement.textContent = currentUser.name || 'Unknown User';
                            console.log("Updated user name to:", currentUser.name);
                        } else {
                            console.error("Element .user-name not found");
                        }
                    } else {
                        console.error("Current user not found in response");
                    }
                    
                    // Display online users
                    if (data.users) {
                        displayOnlineUsers(data.users);
                    }
                    
                } else {
                    console.error('Activity update failed:', data.message || 'No error message provided');
                }
            })
            .catch(error => {
                // Clear the timeout
                clearTimeout(timeoutId);
                window.activityRequest = null;
                
                console.error('Activity update error:', error);
                
                if (error.name !== 'AbortError') {
                    let errorMessage = 'Error fetching online users';
                    
                    if (error.message.includes('Failed to fetch')) {
                        errorMessage = 'Network error - please check your connection';
                    } else if (error.message.includes('HTTP error')) {
                        errorMessage = 'Server error - please try again later';
                    } else if (error.message.includes('Authentication failed') || error.message.includes('401')) {
                        errorMessage = 'Authentication error - please refresh the page';
                        // Try to refresh the API key
                        refreshApiKey();
                    }
                    
                    const homePage = document.getElementById('page-home');
                    if (homePage && homePage.classList.contains('active') && window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: errorMessage,
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                }
            });
        }

        // Display online users function
        function displayOnlineUsers(users) {
            console.log("displayOnlineUsers called with users:", users);
            
            const onlineUsersList = document.getElementById('onlineUsersList');
            if (!onlineUsersList) {
                console.error("Element #onlineUsersList not found in DOM");
                return;
            }
            
            console.log("onlineUsersList element found:", onlineUsersList);
            
            // Clear existing content
            onlineUsersList.innerHTML = '';
            
            if (!Array.isArray(users) || users.length === 0) {
                console.log("No users to display or invalid users array");
                onlineUsersList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Users Online</h3>
                        <p>No users are currently online</p>
                    </div>`;
                return;
            }
            
            console.log("Rendering", users.length, "users");
            
            // Create a document fragment to improve performance
            const fragment = document.createDocumentFragment();
            
            users.forEach((user, index) => {
                console.log(`Processing user ${index + 1}:`, user);
                
                // Safely extract user data with defaults
                const name = (user.name && typeof user.name === 'string') ? user.name.trim() : 'Unknown User';
                const username = (user.username && typeof user.username === 'string') ? user.username : '';
                const photoUrl = (user.photo_url && typeof user.photo_url === 'string') ? 
                    user.photo_url : 
                    `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
                
                // Create user item element
                const userItem = document.createElement('div');
                userItem.className = 'online-user-item';
                userItem.setAttribute('data-user-id', username || `unknown-${index}`);
                
                // Create avatar container
                const avatarContainer = document.createElement('div');
                avatarContainer.className = 'online-user-avatar-container';
                
                // Create avatar image
                const avatar = document.createElement('img');
                avatar.src = photoUrl;
                avatar.alt = name;
                avatar.className = 'online-user-avatar';
                avatar.onerror = function() {
                    this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
                };
                
                // Create online indicator
                const indicator = document.createElement('div');
                indicator.className = 'online-indicator';
                
                // Assemble avatar container
                avatarContainer.appendChild(avatar);
                avatarContainer.appendChild(indicator);
                
                // Create user info container
                const userInfo = document.createElement('div');
                userInfo.className = 'online-user-info';
                
                // Create name element
                const nameElement = document.createElement('div');
                nameElement.className = 'online-user-name';
                nameElement.textContent = name;
                
                // Create username element only if username exists
                if (username) {
                    const usernameElement = document.createElement('div');
                    usernameElement.className = 'online-user-username';
                    usernameElement.textContent = username;
                    userInfo.appendChild(nameElement);
                    userInfo.appendChild(usernameElement);
                } else {
                    userInfo.appendChild(nameElement);
                }
                
                // Assemble user item
                userItem.appendChild(avatarContainer);
                userItem.appendChild(userInfo);
                
                // Add to fragment
                fragment.appendChild(userItem);
            });
            
            // Clear the list and add all users at once
            onlineUsersList.innerHTML = '';
            onlineUsersList.appendChild(fragment);
            
            console.log("Successfully rendered online users list");
        }

        // Initialize activity updates when the page loads
        function initializeActivityUpdates() {
            // Clear any existing interval
            if (activityUpdateInterval) {
                clearInterval(activityUpdateInterval);
            }
            
            // Initial update
            updateUserActivity();
            
            // Set up interval to update every 25 seconds
            activityUpdateInterval = setInterval(() => {
                if (!window.activityRequest) {
                    updateUserActivity();
                }
            }, 25000);
            
            // Update on user interaction, but not more than once every 25 seconds
            if (window.$) {
                $(document).on('click mousemove keypress scroll', function() {
                    const now = new Date().getTime();
                    if (now - lastActivityUpdate >= 25000 && !window.activityRequest) {
                        console.log("User interaction detected, updating activity...");
                        updateUserActivity();
                        lastActivityUpdate = now;
                    }
                });
            }
            
            // Clean up on page unload
            if (window.$) {
                $(window).on('unload', function() {
                    if (activityUpdateInterval) {
                        clearInterval(activityUpdateInterval);
                    }
                    if (window.activityRequest) {
                        window.activityRequest.abort();
                    }
                    if (keyRotationInterval) {
                        clearInterval(keyRotationInterval);
                    }
                    console.log("Cleared intervals on page unload");
                });
            }
        }

        // Make functions globally accessible
        window.toggleTheme = toggleTheme;
        window.showPage = showPage;
        window.closeSidebar = closeSidebar;
        window.openGatewaySettings = openGatewaySettings;
        window.closeGatewaySettings = closeGatewaySettings;
        window.saveGatewaySettings = saveGatewaySettings;
        window.updateCardCount = updateCardCount;
        window.filterResults = filterResults;
        window.setYearRnd = setYearRnd;
        window.setCvvRnd = setCvvRnd;
        window.logout = logout;

        // Initialize everything when jQuery is ready
        if (window.$) {
            $(document).ready(function() {
                console.log("jQuery ready, initializing...");
                
                // Set up event listeners
                const startBtn = document.getElementById('startBtn');
                const generateBtn = document.getElementById('generateBtn');
                const copyAllBtn = document.getElementById('copyAllBtn');
                const clearAllBtn = document.getElementById('clearAllBtn');
                const stopBtn = document.getElementById('stopBtn');
                const clearBtn = document.getElementById('clearBtn');
                const clearGenBtn = document.getElementById('clearGenBtn');
                const exportBtn = document.getElementById('exportBtn');
                const cardInput = document.getElementById('cardInput');
                const menuToggle = document.getElementById('menuToggle');
                
                if (startBtn) startBtn.addEventListener('click', processCards);
                if (generateBtn) generateBtn.addEventListener('click', generateCards);
                if (copyAllBtn) copyAllBtn.addEventListener('click', copyAllGeneratedCards);
                if (clearAllBtn) clearAllBtn.addEventListener('click', clearAllGeneratedCards);

                if (stopBtn) stopBtn.addEventListener('click', function() {
                    if (!isProcessing || isStopping) return;

                    isProcessing = false;
                    isStopping = true;
                    cardQueue = [];
                    abortControllers.forEach(controller => controller.abort());
                    abortControllers = [];
                    activeRequests = 0;
                    updateStats(totalCards, chargedCards.length, approvedCards.length, threeDSCards.length, declinedCards.length);
                    
                    if (startBtn) startBtn.disabled = false;
                    if (stopBtn) stopBtn.disabled = true;
                    const loader = document.getElementById('loader');
                    const statusLog = document.getElementById('statusLog');
                    if (loader) loader.style.display = 'none';
                    if (statusLog) statusLog.textContent = 'Processing stopped.';
                    
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Stopped!',
                            text: 'Checking has been stopped',
                            icon: 'warning',
                            confirmButtonColor: '#ec4899'
                        });
                    }
                });

                if (clearBtn) clearBtn.addEventListener('click', function() {
                    if (cardInput && cardInput.value.trim()) {
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Clear Input?', text: 'Remove all entered cards',
                                icon: 'warning', showCancelButton: true,
                                confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, clear'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    cardInput.value = '';
                                    updateCardCount();
                                    if (window.Swal) {
                                        Swal.fire({
                                            toast: true, position: 'top-end', icon: 'success',
                                            title: 'Cleared!', showConfirmButton: false, timer: 1500
                                        });
                                    }
                                }
                            });
                        } else {
                            // Fallback if Swal is not available
                            if (confirm('Remove all entered cards?')) {
                                cardInput.value = '';
                                updateCardCount();
                            }
                        }
                    }
                });

                if (clearGenBtn) clearGenBtn.addEventListener('click', function() {
                    const binInput = document.getElementById('binInput');
                    const monthSelect = document.getElementById('monthSelect');
                    const yearInput = document.getElementById('yearInput');
                    const cvvInput = document.getElementById('cvvInput');
                    const numCardsInput = document.getElementById('numCardsInput');
                    const generatedCardsList = document.getElementById('generatedCardsList');
                    const genStatusLog = document.getElementById('genStatusLog');
                    
                    if (binInput) binInput.value = '';
                    if (monthSelect) monthSelect.value = 'rnd';
                    if (yearInput) yearInput.value = '';
                    if (cvvInput) cvvInput.value = '';
                    if (numCardsInput) numCardsInput.value = '10';
                    if (generatedCardsList) generatedCardsList.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No Cards Generated Yet</h3><p>Generate cards to see them here</p></div>';
                    if (genStatusLog) genStatusLog.textContent = '';
                    generatedCardsData = [];
                    
                    if (copyAllBtn) copyAllBtn.style.display = 'none';
                    if (clearAllBtn) clearAllBtn.style.display = 'none';
                    
                    if (window.Swal) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Cleared!', showConfirmButton: false, timer: 1500
                        });
                    }
                });

                if (exportBtn) exportBtn.addEventListener('click', function() {
                    const allCards = [...chargedCards, ...approvedCards, ...threeDSCards, ...declinedCards];
                    if (allCards.length === 0) {
                        if (window.Swal) {
                            Swal.fire({
                                title: 'No data to export!',
                                text: 'Please check some cards first.',
                                icon: 'warning',
                                confirmButtonColor: '#ec4899'
                            });
                        }
                        return;
                    }
                    let csvContent = "Card,Status,Response\n";
                    allCards.forEach(card => {
                        const status = card.response.includes('CHARGED') ? 'CHARGED' :
                                     card.response.includes('APPROVED') ? 'APPROVED' :
                                     card.response.includes('3DS') ? '3DS' : 'DECLINED';
                        csvContent += `${card.displayCard},${status},${card.response}\n`;
                    });
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `card_results_${new Date().toISOString().split('T')[0]}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    if (window.Swal) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Exported!', showConfirmButton: false, timer: 1500
                        });
                    }
                });

                if (cardInput) cardInput.addEventListener('input', updateCardCount);

                document.addEventListener('click', function(e) {
                    if (e.target === document.getElementById('gatewaySettings')) {
                        closeGatewaySettings();
                    }
                });

                if (menuToggle) menuToggle.addEventListener('click', function() {
                    sidebarOpen = !sidebarOpen;
                    const sidebar = document.getElementById('sidebar');
                    const mainContent = document.querySelector('.main-content');
                    if (sidebar) sidebar.classList.toggle('open', sidebarOpen);
                    if (mainContent) mainContent.classList.toggle('sidebar-open', sidebarOpen);
                });
                
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.body.setAttribute('data-theme', savedTheme);
                const themeIcon = document.querySelector('.theme-toggle-slider i');
                if (themeIcon) themeIcon.className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
                
                console.log("Page loaded, initializing user activity update...");
                
                // Initialize activity updates
                initializeActivityUpdates();
            });
        } else {
            console.error("jQuery not loaded, some functionality may not work");
        }
    }

    // Load API key and initialize the app
    loadApiKey().then(success => {
        if (!success) {
            console.warn('Proceeding without valid API key');
            // Show a warning if API key loading fails
            if (window.Swal) {
                Swal.fire({
                    title: 'API Key Issue',
                    text: 'Could not load API key. Some features may not work properly.',
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b'
                });
            }
        }
        
        // Start key rotation
        startKeyRotation();
        
        // Initialize the app
        initializeApp();
    });
});
