// Theme Toggle
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    body.setAttribute('data-theme', newTheme);
    
    // Save theme preference to localStorage
    localStorage.setItem('theme', newTheme);
    
    // Update theme toggle icon
    const icon = document.querySelector('.theme-toggle-slider i');
    icon.className = newTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
}

// Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    sidebar.classList.toggle('open');
    mainContent.classList.toggle('sidebar-open');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    sidebar.classList.remove('open');
    mainContent.classList.remove('sidebar-open');
}

// Page Navigation
function showPage(pageId) {
    // Hide all pages
    const pages = document.querySelectorAll('.page-section');
    pages.forEach(page => page.classList.remove('active'));
    
    // Show selected page
    document.getElementById(`page-${pageId}`).classList.add('active');
    
    // Update sidebar active state
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    sidebarLinks.forEach(link => link.classList.remove('active'));
    
    // Find and activate the corresponding sidebar link
    sidebarLinks.forEach(link => {
        if (link.getAttribute('onclick').includes(pageId)) {
            link.classList.add('active');
        }
    });
    
    // Load page-specific data
    if (pageId === 'home') {
        loadDashboardData();
        loadOnlineUsers();
    }
}

// Logout Function
function logout() {
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, logout'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to logout script
            window.location.href = 'logout.php';
        }
    });
}

// Gateway Settings
function openGatewaySettings() {
    document.getElementById('gatewaySettings').classList.add('active');
}

function closeGatewaySettings() {
    document.getElementById('gatewaySettings').classList.remove('active');
}

function saveGatewaySettings() {
    const selectedGateway = document.querySelector('input[name="gateway"]:checked');
    
    if (selectedGateway) {
        // Save gateway preference to localStorage
        localStorage.setItem('selectedGateway', selectedGateway.value);
        
        Swal.fire({
            title: 'Settings Saved',
            text: 'Gateway settings have been saved successfully.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
        
        closeGatewaySettings();
    } else {
        Swal.fire({
            title: 'No Gateway Selected',
            text: 'Please select a payment gateway.',
            icon: 'warning',
            confirmButtonColor: '#3b82f6'
        });
    }
}

// Card Checker Functions
document.addEventListener('DOMContentLoaded', function() {
    const cardInput = document.getElementById('cardInput');
    const cardCount = document.getElementById('cardCount');
    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const clearBtn = document.getElementById('clearBtn');
    const exportBtn = document.getElementById('exportBtn');
    const loader = document.getElementById('loader');
    const statusLog = document.getElementById('statusLog');
    
    // Update card count when input changes
    if (cardInput) {
        cardInput.addEventListener('input', function() {
            const cards = this.value.trim().split('\n').filter(line => line.trim());
            const validCards = cards.filter(card => {
                const parts = card.split('|');
                return parts.length === 4 && parts[0].replace(/\s/g, '').length >= 13 && parts[0].replace(/\s/g, '').length <= 19;
            });
            
            cardCount.innerHTML = `<i class="fas fa-list"></i> ${validCards.length} valid cards detected`;
        });
    }
    
    // Start button click handler
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            const cards = cardInput.value.trim().split('\n').filter(line => line.trim());
            
            if (cards.length === 0) {
                Swal.fire({
                    title: 'No Cards',
                    text: 'Please enter at least one card to check.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            // Disable buttons and show loader
            startBtn.disabled = true;
            stopBtn.disabled = false;
            clearBtn.disabled = true;
            exportBtn.disabled = true;
            cardInput.disabled = true;
            loader.style.display = 'block';
            statusLog.textContent = 'Starting card check...';
            
            // Get selected gateway
            const selectedGateway = document.querySelector('input[name="gateway"]:checked');
            if (!selectedGateway) {
                Swal.fire({
                    title: 'No Gateway Selected',
                    text: 'Please select a payment gateway in settings.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    // Reset UI
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                    clearBtn.disabled = false;
                    exportBtn.disabled = false;
                    cardInput.disabled = false;
                    loader.style.display = 'none';
                    statusLog.textContent = '';
                });
                return;
            }
            
            // Process cards
            processCards(cards, selectedGateway.value);
        });
    }
    
    // Stop button click handler
    if (stopBtn) {
        stopBtn.addEventListener('click', function() {
            // Reset UI
            startBtn.disabled = false;
            stopBtn.disabled = true;
            clearBtn.disabled = false;
            exportBtn.disabled = false;
            cardInput.disabled = false;
            loader.style.display = 'none';
            statusLog.textContent = 'Card checking stopped by user.';
        });
    }
    
    // Clear button click handler
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            cardInput.value = '';
            cardCount.innerHTML = '<i class="fas fa-list"></i> 0 valid cards detected';
            statusLog.textContent = '';
        });
    }
    
    // Export button click handler
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportResults();
        });
    }
});

// Process cards function
function processCards(cards, gateway) {
    const resultsList = document.getElementById('checkingResultsList');
    const statusLog = document.getElementById('statusLog');
    let processedCount = 0;
    let results = [];
    
    // Clear previous results
    resultsList.innerHTML = '';
    
    // Process each card
    cards.forEach((card, index) => {
        setTimeout(() => {
            // Simulate API call to check card
            checkCard(card, gateway, function(result) {
                results.push(result);
                processedCount++;
                
                // Update status
                statusLog.textContent = `Checking card ${processedCount} of ${cards.length}...`;
                
                // Add result to UI
                addResultToUI(result, resultsList);
                
                // Update dashboard stats
                updateDashboardStats();
                
                // Check if all cards are processed
                if (processedCount === cards.length) {
                    // Reset UI
                    document.getElementById('startBtn').disabled = false;
                    document.getElementById('stopBtn').disabled = true;
                    document.getElementById('clearBtn').disabled = false;
                    document.getElementById('exportBtn').disabled = false;
                    document.getElementById('cardInput').disabled = false;
                    document.getElementById('loader').style.display = 'none';
                    
                    statusLog.textContent = `Completed checking ${cards.length} cards.`;
                    
                    // Show summary
                    showResultsSummary(results);
                }
            });
        }, index * 1000); // Process one card per second
    });
}

// Check card function (simulated)
function checkCard(card, gateway, callback) {
    // Parse card details
    const parts = card.split('|');
    const cardNumber = parts[0].replace(/\s/g, '');
    const month = parts[1];
    const year = parts[2];
    const cvv = parts[3];
    
    // Simulate API call with random results
    setTimeout(() => {
        const random = Math.random();
        let status, response;
        
        if (random < 0.1) {
            status = 'charged';
            response = 'Charged $1.00 successfully';
        } else if (random < 0.2) {
            status = 'approved';
            response = 'Approved - $0.00 authorized';
        } else if (random < 0.3) {
            status = '3ds';
            response = '3D Secure verification required';
        } else {
            status = 'declined';
            response = 'Declined - Insufficient funds';
        }
        
        const result = {
            card: card,
            cardNumber: cardNumber,
            month: month,
            year: year,
            cvv: cvv,
            status: status,
            response: response,
            gateway: gateway,
            timestamp: new Date().toISOString()
        };
        
        callback(result);
    }, 500 + Math.random() * 1000); // Random delay between 0.5-1.5 seconds
}

// Add result to UI
function addResultToUI(result, container) {
    // Check if container is empty
    if (container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    // Create result item
    const resultItem = document.createElement('div');
    resultItem.className = `result-item ${result.status}`;
    
    // Format card number (show only last 4 digits)
    const maskedCard = result.cardNumber.replace(/\d(?=\d{4})/g, '*');
    
    // Create result HTML
    resultItem.innerHTML = `
        <div class="result-header">
            <div class="result-card">${maskedCard} | ${result.month} | ${result.year} | ${result.cvv}</div>
            <div class="result-status ${result.status}">${result.status.toUpperCase()}</div>
        </div>
        <div class="result-details">
            <div class="result-response">${result.response}</div>
            <div class="result-gateway">${result.gateway}</div>
            <div class="result-time">${formatTime(result.timestamp)}</div>
        </div>
    `;
    
    // Add to container
    container.appendChild(resultItem);
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
}

// Format time
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // Difference in seconds
    
    if (diff < 60) {
        return 'Just now';
    } else if (diff < 3600) {
        return `${Math.floor(diff / 60)} minutes ago`;
    } else if (diff < 86400) {
        return `${Math.floor(diff / 3600)} hours ago`;
    } else {
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
}

// Update dashboard stats
function updateDashboardStats() {
    // Get all results
    const results = document.querySelectorAll('.result-item');
    
    // Count statuses
    let total = results.length;
    let charged = 0;
    let approved = 0;
    let threeds = 0;
    let declined = 0;
    
    results.forEach(result => {
        if (result.classList.contains('charged')) charged++;
        else if (result.classList.contains('approved')) approved++;
        else if (result.classList.contains('3ds')) threeds++;
        else if (result.classList.contains('declined')) declined++;
    });
    
    // Update UI
    document.getElementById('total-value').textContent = total;
    document.getElementById('charged-value').textContent = charged;
    document.getElementById('approved-value').textContent = approved;
    document.getElementById('threed-value').textContent = threeds;
    document.getElementById('declined-value').textContent = declined;
    document.getElementById('checked-value').textContent = `${total} / ${total}`;
    
    // Update activity feed
    updateActivityFeed();
}

// Update activity feed
function updateActivityFeed() {
    const activityList = document.getElementById('activityList');
    const results = document.querySelectorAll('.result-item');
    
    // Clear previous activity
    activityList.innerHTML = '';
    
    // Get last 5 results
    const recentResults = Array.from(results).slice(-5).reverse();
    
    if (recentResults.length === 0) {
        activityList.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Activity Yet</h3>
                <p>Start checking cards to see activity here</p>
            </div>
        `;
        return;
    }
    
    // Add recent results to activity feed
    recentResults.forEach(result => {
        const activityItem = document.createElement('div');
        activityItem.className = `activity-item ${result.classList.contains('charged') ? 'charged' : 
                                                 result.classList.contains('approved') ? 'approved' : 
                                                 result.classList.contains('3ds') ? 'threeds' : 'declined'}`;
        
        const cardElement = result.querySelector('.result-card');
        const statusElement = result.querySelector('.result-status');
        const timeElement = result.querySelector('.result-time');
        
        activityItem.innerHTML = `
            <div class="activity-icon">
                <i class="fas ${result.classList.contains('charged') ? 'fa-bolt' : 
                               result.classList.contains('approved') ? 'fa-check-circle' : 
                               result.classList.contains('3ds') ? 'fa-lock' : 'fa-times-circle'}"></i>
            </div>
            <div class="activity-content">
                <div class="activity-card">${cardElement.textContent}</div>
                <div class="activity-status">${statusElement.textContent}</div>
            </div>
            <div class="activity-time">${timeElement.textContent}</div>
        `;
        
        activityList.appendChild(activityItem);
    });
}

// Show results summary
function showResultsSummary(results) {
    const charged = results.filter(r => r.status === 'charged').length;
    const approved = results.filter(r => r.status === 'approved').length;
    const threeds = results.filter(r => r.status === '3ds').length;
    const declined = results.filter(r => r.status === 'declined').length;
    
    Swal.fire({
        title: 'Check Complete',
        html: `
            <div style="text-align: left;">
                <p><strong>Total Checked:</strong> ${results.length}</p>
                <p><strong>Charged:</strong> <span style="color: #22c55e;">${charged}</span></p>
                <p><strong>Approved:</strong> <span style="color: #22c55e;">${approved}</span></p>
                <p><strong>3D Secure:</strong> <span style="color: #22c55e;">${threeds}</span></p>
                <p><strong>Declined:</strong> <span style="color: #ef4444;">${declined}</span></p>
            </div>
        `,
        icon: 'success',
        confirmButtonColor: '#3b82f6'
    });
}

// Export results
function exportResults() {
    const results = document.querySelectorAll('.result-item');
    
    if (results.length === 0) {
        Swal.fire({
            title: 'No Results',
            text: 'There are no results to export.',
            icon: 'warning',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    // Create CSV content
    let csvContent = "Card Number,Month,Year,CVV,Status,Response,Gateway,Timestamp\n";
    
    results.forEach(result => {
        const cardElement = result.querySelector('.result-card');
        const statusElement = result.querySelector('.result-status');
        const responseElement = result.querySelector('.result-response');
        const gatewayElement = result.querySelector('.result-gateway');
        const timeElement = result.querySelector('.result-time');
        
        const cardParts = cardElement.textContent.split(' | ');
        
        csvContent += `"${cardParts[0]}","${cardParts[1]}","${cardParts[2]}","${cardParts[3]}","${statusElement.textContent}","${responseElement.textContent}","${gatewayElement.textContent}","${timeElement.textContent}"\n`;
    });
    
    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `card_check_results_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    Swal.fire({
        title: 'Export Complete',
        text: 'Results have been exported successfully.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

// Filter results
function filterResults(filter) {
    const results = document.querySelectorAll('.result-item');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Update active filter button
    filterButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter results
    results.forEach(result => {
        if (filter === 'all') {
            result.style.display = 'block';
        } else if (filter === 'charged' && result.classList.contains('charged')) {
            result.style.display = 'block';
        } else if (filter === 'approved' && result.classList.contains('approved')) {
            result.style.display = 'block';
        } else if (filter === '3ds' && result.classList.contains('3ds')) {
            result.style.display = 'block';
        } else if (filter === 'declined' && result.classList.contains('declined')) {
            result.style.display = 'block';
        } else {
            result.style.display = 'none';
        }
    });
}

// Card Generator Functions
document.addEventListener('DOMContentLoaded', function() {
    const binInput = document.getElementById('binInput');
    const monthSelect = document.getElementById('monthSelect');
    const yearInput = document.getElementById('yearInput');
    const cvvInput = document.getElementById('cvvInput');
    const numCardsInput = document.getElementById('numCardsInput');
    const generateBtn = document.getElementById('generateBtn');
    const clearGenBtn = document.getElementById('clearGenBtn');
    const genLoader = document.getElementById('genLoader');
    const genStatusLog = document.getElementById('genStatusLog');
    const copyAllBtn = document.getElementById('copyAllBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    
    // Generate button click handler
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            const bin = binInput.value.trim();
            const month = monthSelect.value;
            const year = yearInput.value.trim();
            const cvv = cvvInput.value.trim();
            const numCards = parseInt(numCardsInput.value);
            
            // Validate inputs
            if (!bin || bin.length < 6 || bin.length > 8) {
                Swal.fire({
                    title: 'Invalid BIN',
                    text: 'Please enter a valid BIN (6-8 digits).',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            if (numCards < 1 || numCards > 5000) {
                Swal.fire({
                    title: 'Invalid Number',
                    text: 'Please enter a number between 1 and 5000.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            // Disable buttons and show loader
            generateBtn.disabled = true;
            clearGenBtn.disabled = true;
            binInput.disabled = true;
            monthSelect.disabled = true;
            yearInput.disabled = true;
            cvvInput.disabled = true;
            numCardsInput.disabled = true;
            genLoader.style.display = 'block';
            genStatusLog.textContent = 'Generating cards...';
            
            // Generate cards
            generateCards(bin, month, year, cvv, numCards);
        });
    }
    
    // Clear button click handler
    if (clearGenBtn) {
        clearGenBtn.addEventListener('click', function() {
            binInput.value = '';
            monthSelect.value = 'rnd';
            yearInput.value = '';
            cvvInput.value = '';
            numCardsInput.value = '10';
            genStatusLog.textContent = '';
            
            // Clear generated cards
            document.getElementById('generatedCardsList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Cards Generated Yet</h3>
                    <p>Generate cards to see them here</p>
                </div>
            `;
            
            // Hide action buttons
            copyAllBtn.style.display = 'none';
            clearAllBtn.style.display = 'none';
        });
    }
    
    // Copy all button click handler
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function() {
            const cardsContainer = document.getElementById('generatedCardsList');
            const cardsText = cardsContainer.textContent;
            
            navigator.clipboard.writeText(cardsText).then(() => {
                Swal.fire({
                    title: 'Copied',
                    text: 'All cards have been copied to clipboard.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }).catch(err => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to copy cards to clipboard.',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                });
            });
        });
    }
    
    // Clear all button click handler
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            document.getElementById('generatedCardsList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Cards Generated Yet</h3>
                    <p>Generate cards to see them here</p>
                </div>
            `;
            
            // Hide action buttons
            copyAllBtn.style.display = 'none';
            clearAllBtn.style.display = 'none';
        });
    }
});

// Set year to random
function setYearRnd() {
    const currentYear = new Date().getFullYear();
    const randomYear = currentYear + Math.floor(Math.random() * 5) + 1;
    document.getElementById('yearInput').value = randomYear.toString().slice(-2);
}

// Set CVV to random
function setCvvRnd() {
    const randomCvv = Math.floor(Math.random() * 900) + 100;
    document.getElementById('cvvInput').value = randomCvv.toString();
}

// Generate cards function
function generateCards(bin, month, year, cvv, numCards) {
    const cardsContainer = document.getElementById('generatedCardsList');
    const genStatusLog = document.getElementById('genStatusLog');
    const copyAllBtn = document.getElementById('copyAllBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    
    // Clear previous cards
    cardsContainer.innerHTML = '';
    
    // Create container for generated cards
    const cardsTextContainer = document.createElement('div');
    cardsTextContainer.className = 'generated-cards-container';
    
    // Generate cards
    let generatedCards = [];
    
    for (let i = 0; i < numCards; i++) {
        // Generate card number
        const cardNumber = generateCardNumber(bin);
        
        // Generate month
        let cardMonth = month;
        if (month === 'rnd') {
            cardMonth = Math.floor(Math.random() * 12) + 1;
            cardMonth = cardMonth < 10 ? '0' + cardMonth : cardMonth.toString();
        }
        
        // Generate year
        let cardYear = year;
        if (!year) {
            const currentYear = new Date().getFullYear();
            cardYear = (currentYear + Math.floor(Math.random() * 5) + 1).toString().slice(-2);
        } else if (year.length === 2) {
            cardYear = year;
        } else if (year.length === 4) {
            cardYear = year.slice(-2);
        }
        
        // Generate CVV
        let cardCvv = cvv;
        if (!cvv) {
            cardCvv = Math.floor(Math.random() * 900) + 100;
            cardCvv = cardCvv.toString();
        }
        
        // Create card string
        const cardString = `${cardNumber}|${cardMonth}|${cardYear}|${cardCvv}`;
        generatedCards.push(cardString);
        
        // Update status
        genStatusLog.textContent = `Generated ${i + 1} of ${numCards} cards...`;
        
        // Add card to container
        cardsTextContainer.textContent += cardString + '\n';
    }
    
    // Add cards to UI
    cardsContainer.appendChild(cardsTextContainer);
    
    // Show action buttons
    copyAllBtn.style.display = 'flex';
    clearAllBtn.style.display = 'flex';
    
    // Reset UI
    document.getElementById('generateBtn').disabled = false;
    document.getElementById('clearGenBtn').disabled = false;
    document.getElementById('binInput').disabled = false;
    document.getElementById('monthSelect').disabled = false;
    document.getElementById('yearInput').disabled = false;
    document.getElementById('cvvInput').disabled = false;
    document.getElementById('numCardsInput').disabled = false;
    document.getElementById('genLoader').style.display = 'none';
    
    genStatusLog.textContent = `Successfully generated ${numCards} cards.`;
    
    // Show success message
    Swal.fire({
        title: 'Cards Generated',
        text: `Successfully generated ${numCards} cards.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

// Generate card number with Luhn algorithm
function generateCardNumber(bin) {
    // Get bin length
    const binLength = bin.length;
    
    // Determine card length based on bin
    let cardLength = 16; // Default
    if (bin.startsWith('34') || bin.startsWith('37')) {
        cardLength = 15; // American Express
    } else if (bin.startsWith('4')) {
        cardLength = 16; // Visa
    } else if (bin.startsWith('5') || bin.startsWith('2')) {
        cardLength = 16; // Mastercard
    } else if (bin.startsWith('65') || bin.startsWith('6011')) {
        cardLength = 16; // Discover
    }
    
    // Generate random digits to fill the card number (except last digit)
    let cardNumber = bin;
    for (let i = binLength; i < cardLength - 1; i++) {
        cardNumber += Math.floor(Math.random() * 10).toString();
    }
    
    // Calculate and add check digit using Luhn algorithm
    cardNumber += calculateLuhnCheckDigit(cardNumber);
    
    return cardNumber;
}

// Calculate Luhn check digit
function calculateLuhnCheckDigit(number) {
    const digits = number.split('').map(d => parseInt(d, 10));
    let sum = 0;
    let isSecond = false;
    
    // Double every second digit from the right
    for (let i = digits.length - 1; i >= 0; i--) {
        let d = digits[i];
        
        if (isSecond) {
            d = d * 2;
            if (d > 9) {
                d = d - 9;
            }
        }
        
        sum += d;
        isSecond = !isSecond;
    }
    
    // Calculate check digit
    const checkDigit = (10 - (sum % 10)) % 10;
    
    return checkDigit.toString();
}

// Load dashboard data
function loadDashboardData() {
    // This would typically make an API call to get dashboard data
    // For now, we'll just update the UI with placeholder data
    
    // Update stats
    document.getElementById('total-value').textContent = '0';
    document.getElementById('charged-value').textContent = '0';
    document.getElementById('approved-value').textContent = '0';
    document.getElementById('threed-value').textContent = '0';
    document.getElementById('declined-value').textContent = '0';
    document.getElementById('checked-value').textContent = '0 / 0';
    
    // Update activity feed
    updateActivityFeed();
}

// Load online users
function loadOnlineUsers() {
    const onlineUsersList = document.getElementById('onlineUsersList');
    const onlineCount = document.getElementById('onlineCount');
    
    // Show loading state
    onlineUsersList.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h3>Loading Users</h3>
            <p>Fetching online users...</p>
        </div>
    `;
    
    // Fetch online users from API
    fetch('https://cxchk.site/update_activity.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        mode: 'cors'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('API Response:', data); // Debug log
        
        if (data.success) {
            // Update online count
            onlineCount.textContent = data.count;
            
            // Clear previous users
            onlineUsersList.innerHTML = '';
            
            if (data.count === 0 || !data.users || data.users.length === 0) {
                onlineUsersList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Users Online</h3>
                        <p>No other users are currently online</p>
                    </div>
                `;
                return;
            }
            
            // Add users to the list
            data.users.forEach(user => {
                const userItem = document.createElement('div');
                userItem.className = 'online-user-item';
                
                // Use provided photo_url or generate avatar
                const photoUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=3b82f6&color=fff&size=64`;
                
                // Format username
                const username = user.username ? (user.username.startsWith('@') ? user.username : '@' + user.username) : '';
                
                userItem.innerHTML = `
                    <div class="online-user-avatar-container">
                        <img src="${photoUrl}" alt="${user.name}" class="online-user-avatar">
                        <div class="online-indicator"></div>
                    </div>
                    <div class="online-user-info">
                        <div class="online-user-name">${user.name}</div>
                        ${username ? `<div class="online-user-username">${username}</div>` : ''}
                    </div>
                `;
                
                onlineUsersList.appendChild(userItem);
            });
        } else {
            // Handle API error
            console.error('API returned error:', data);
            onlineUsersList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Users</h3>
                    <p>Unable to load online users at this time</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error fetching online users:', error);
        onlineUsersList.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error Loading Users</h3>
                <p>Unable to load online users at this time</p>
            </div>
        `;
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    
    // Update theme toggle icon
    const icon = document.querySelector('.theme-toggle-slider i');
    icon.className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
    
    // Load saved gateway settings
    const savedGateway = localStorage.getItem('selectedGateway');
    if (savedGateway) {
        const gatewayOption = document.querySelector(`input[name="gateway"][value="${savedGateway}"]`);
        if (gatewayOption) {
            gatewayOption.checked = true;
        }
    }
    
    // Initialize dashboard
    loadDashboardData();
    
    // Initialize online users
    loadOnlineUsers();
    
    // Update online users every 20 seconds (20000 milliseconds)
    setInterval(loadOnlineUsers, 20000);
    
    // Set up menu toggle
    document.getElementById('menuToggle').addEventListener('click', toggleSidebar);
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
