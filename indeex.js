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
    let activityRequestTimeout = null;
    let globalStatsInterval = null;
    let topUsersInterval = null;
    
    // Dynamic MAX_CONCURRENT based on selected gateway
    let maxConcurrent = 5;
    
    // Preload data immediately when DOM is loaded, even before login
    preloadInitialData();
    
    // Function to preload initial data
    function preloadInitialData() {
        console.log("Preloading initial data...");
        
        // Update global stats immediately
        updateGlobalStats();
        
        // Update top users immediately
        updateTopUsers();
        
        // Update online users immediately
        updateUserActivity();
        
        // Initialize intervals for regular updates
        initializeGlobalStatsUpdates();
        initializeTopUsersUpdates();
        initializeActivityUpdates();
    }
    
    // Function to update maxConcurrent based on selected gateway
    function updateMaxConcurrent() {
        if (selectedGateway === 'gate/stripe1$.php' || selectedGateway === 'gate/stripe5$.php') {
            maxConcurrent = 5;
            console.log(`Set maxConcurrent to 5 for ${selectedGateway}`);
        } else if (selectedGateway === 'gate/paypal0.1$.php') {
            maxConcurrent = 3;
            console.log(`Set maxConcurrent to 3 for ${selectedGateway}`);
        } else {
            maxConcurrent = 3;
            console.log(`Set maxConcurrent to 3 for ${selectedGateway}`);
        }
    }
    
    // Function to format the response by removing status prefix and brackets
    function formatResponse(response) {
        const statusPrefixPattern = /^(APPROVED|CHARGED|DECLINED|3DS)\s*\[(.*)\]$/i;
        const match = response.match(statusPrefixPattern);
        
        if (match) {
            return match[2];
        }
        
        const bracketsPattern = /^\[(.*)\]$/;
        const bracketsMatch = response.match(bracketsPattern);
        
        if (bracketsMatch) {
            return bracketsMatch[1];
        }
        
        return response;
    }
    
    // Function to update global statistics
    function updateGlobalStats() {
        console.log("Updating global statistics at", new Date().toISOString());
        
        fetch('/stats.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            console.log("Global stats response status:", response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Global stats response:", data);
            
            if (data.success) {
                const totalUsersElement = document.getElementById('gTotalUsers');
                const totalHitsElement = document.getElementById('gTotalHits');
                const chargeCardsElement = document.getElementById('gChargeCards');
                const liveCardsElement = document.getElementById('gLiveCards');
                
                if (totalUsersElement) {
                    totalUsersElement.textContent = data.data.totalUsers;
                    console.log("Updated total users to:", data.data.totalUsers);
                } else {
                    console.error("Element #gTotalUsers not found");
                }
                
                if (totalHitsElement) {
                    totalHitsElement.textContent = data.data.totalChecked;
                    console.log("Updated total hits to:", data.data.totalChecked);
                } else {
                    console.error("Element #gTotalHits not found");
                }
                
                if (chargeCardsElement) {
                    chargeCardsElement.textContent = data.data.totalCharged;
                    console.log("Updated charge cards to:", data.data.totalCharged);
                } else {
                    console.error("Element #gChargeCards not found");
                }
                
                if (liveCardsElement) {
                    liveCardsElement.textContent = data.data.totalApproved;
                    console.log("Updated live cards to:", data.data.totalApproved);
                } else {
                    console.error("Element #gLiveCards not found");
                }
                
                console.log("Global statistics updated successfully");
            } else {
                console.error('Failed to update global statistics:', data.message);
            }
        })
        .catch(error => {
            console.error('Error updating global statistics:', error);
            
            const homePage = document.getElementById('page-home');
            if (homePage && homePage.classList.contains('active') && window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Failed to update global statistics',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    }
    
    // Function to fetch and display top users
    function updateTopUsers() {
        console.log("Updating top users at", new Date().toISOString());
        
        fetch('/gate/topusers.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            console.log("Top users response status:", response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Top users response:", data);
            
            if (data.success) {
                if (data.users) {
                    displayTopUsers(data.users);
                    displayMobileTopUsers(data.users);
                }
            } else {
                console.error('Failed to update top users:', data.message);
            }
        })
        .catch(error => {
            console.error('Error updating top users:', error);
            
            const homePage = document.getElementById('page-home');
            if (homePage && homePage.classList.contains('active') && window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Failed to update top users',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    }
    
    // Function to display top users in the UI
    function displayTopUsers(users) {
        console.log("displayTopUsers called with users:", users);
        
        const topUsersList = document.getElementById('topUsersList');
        if (!topUsersList) {
            console.error("Element #topUsersList not found in DOM");
            return;
        }
        
        topUsersList.innerHTML = '';
        
        if (!Array.isArray(users) || users.length === 0) {
            topUsersList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Top Users</h3>
                    <p>No top users data available</p>
                </div>`;
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        users.forEach((user, index) => {
            const name = (user.name && typeof user.name === 'string') ? user.name.trim() : 'Unknown User';
            const username = (user.username && typeof user.username === 'string') ? user.username : '';
            const photoUrl = (user.photo_url && typeof user.photo_url === 'string') ? 
                user.photo_url : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=8b5cf6&color=fff&size=64`;
            const hits = (user.total_hits && typeof user.total_hits === 'number') ? user.total_hits : 0;
            
            const userItem = document.createElement('div');
            userItem.className = 'top-user-item';
            userItem.setAttribute('data-user-id', username || `unknown-${index}`);
            
            if (username === '@K4LNX') {
                userItem.classList.add('admin');
            }
            
            const avatarContainer = document.createElement('div');
            avatarContainer.className = 'top-user-avatar-container';
            
            const avatar = document.createElement('img');
            avatar.src = photoUrl;
            avatar.alt = name;
            avatar.className = 'top-user-avatar';
            avatar.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=8b5cf6&color=fff&size=64`;
            };
            
            avatarContainer.appendChild(avatar);
            
            const userInfo = document.createElement('div');
            userInfo.className = 'top-user-info';
            
            const nameElement = document.createElement('div');
            nameElement.className = 'top-user-name';
            
            if (username === '@K4LNX') {
                const adminBadge = document.createElement('span');
                adminBadge.className = 'admin-badge';
                adminBadge.textContent = 'ADMIN';
                nameElement.appendChild(document.createTextNode(name));
                nameElement.appendChild(adminBadge);
            } else {
                nameElement.textContent = name;
            }
            
            if (username) {
                const usernameElement = document.createElement('div');
                usernameElement.className = 'top-user-username';
                usernameElement.textContent = username;
                userInfo.appendChild(nameElement);
                userInfo.appendChild(usernameElement);
            } else {
                userInfo.appendChild(nameElement);
            }
            
            const hitsElement = document.createElement('div');
            hitsElement.className = 'top-user-hits';
            hitsElement.textContent = `${hits} Hits`;
            
            userItem.appendChild(avatarContainer);
            userItem.appendChild(userInfo);
            userItem.appendChild(hitsElement);
            
            fragment.appendChild(userItem);
        });
        
        topUsersList.innerHTML = '';
        topUsersList.appendChild(fragment);
        
        console.log("Successfully rendered top users list");
    }
    
    // Function to display top users in mobile view
    function displayMobileTopUsers(users) {
        console.log("displayMobileTopUsers called with users:", users);
        
        const mobileTopUsersList = document.getElementById('mobileTopUsersList');
        if (!mobileTopUsersList) {
            console.error("Element #mobileTopUsersList not found in DOM");
            return;
        }
        
        mobileTopUsersList.innerHTML = '';
        
        if (!Array.isArray(users) || users.length === 0) {
            mobileTopUsersList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Top Users</h3>
                    <p>No top users data available</p>
                </div>`;
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        users.forEach((user, index) => {
            const name = (user.name && typeof user.name === 'string') ? user.name.trim() : 'Unknown User';
            const username = (user.username && typeof user.username === 'string') ? user.username : '';
            const photoUrl = (user.photo_url && typeof user.photo_url === 'string') ? 
                user.photo_url : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=8b5cf6&color=fff&size=64`;
            const hits = (user.total_hits && typeof user.total_hits === 'number') ? user.total_hits : 0;
            
            const userItem = document.createElement('div');
            userItem.className = 'mobile-top-user-item';
            
            if (username === '@K4LNX') {
                userItem.classList.add('admin');
            }
            
            userItem.setAttribute('data-user-id', username || `unknown-${index}`);
            
            const avatar = document.createElement('img');
            avatar.src = photoUrl;
            avatar.alt = name;
            avatar.className = 'mobile-top-user-avatar';
            avatar.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=8b5cf6&color=fff&size=64`;
            };
            
            const userInfo = document.createElement('div');
            userInfo.className = 'mobile-top-user-info';
            
            const nameElement = document.createElement('div');
            nameElement.className = 'mobile-top-user-name';
            
            if (username === '@K4LNX') {
                const adminBadge = document.createElement('span');
                adminBadge.className = 'admin-badge';
                adminBadge.textContent = 'ADMIN';
                nameElement.appendChild(document.createTextNode(name));
                nameElement.appendChild(adminBadge);
            } else {
                nameElement.textContent = name;
            }
            
            if (username) {
                const usernameElement = document.createElement('div');
                usernameElement.className = 'mobile-top-user-username';
                usernameElement.textContent = username;
                userInfo.appendChild(nameElement);
                userInfo.appendChild(usernameElement);
            } else {
                userInfo.appendChild(nameElement);
            }
            
            const hitsElement = document.createElement('div');
            hitsElement.className = 'mobile-top-user-hits';
            hitsElement.textContent = `${hits} Hits`;
            
            userItem.appendChild(avatar);
            userItem.appendChild(userInfo);
            userItem.appendChild(hitsElement);
            
            fragment.appendChild(userItem);
        });
        
        mobileTopUsersList.innerHTML = '';
        mobileTopUsersList.appendChild(fragment);
        
        console.log("Successfully rendered mobile top users list");
    }
    
    // Initialize top users updates
    function initializeTopUsersUpdates() {
        if (topUsersInterval) {
            clearInterval(topUsersInterval);
        }
        
        updateTopUsers();
        
        topUsersInterval = setInterval(() => {
            updateTopUsers();
        }, 30000);
        
        console.log("Top users updates initialized. Users will update every 30 seconds.");
    }
    
    // Function to escape text for HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Function to load user profile data
    function loadUserProfile() {
        const userData = {
            name: document.querySelector('.user-name') ? document.querySelector('.user-name').textContent : 'Unknown User',
            username: document.querySelector('.user-username') ? document.querySelector('.user-username').textContent : '@unknown',
            photo_url: document.querySelector('.user-avatar') ? document.querySelector('.user-avatar').src : 'https://ui-avatars.com/api/?name=U&background=3b82f6&color=fff&size=120'
        };
        
        const profileName = document.getElementById('profileName');
        const profileUsername = document.getElementById('profileUsername');
        const profileAvatar = document.getElementById('profileAvatar');
        
        if (profileName) profileName.textContent = userData.name || 'Unknown User';
        if (profileUsername) profileUsername.textContent = userData.username || '@unknown';
        if (profileAvatar) profileAvatar.src = userData.photo_url || 'https://ui-avatars.com/api/?name=U&background=3b82f6&color=fff&size=120';
        
        loadUserStatistics();
    }
    
    // Function to load user statistics
    function loadUserStatistics() {
        const stats = getUserStatistics();
        
        updateProfileStat('charged', stats.charged || 0);
        updateProfileStat('approved', stats.approved || 0);
        updateProfileStat('threeds', stats.threeds || 0);
        updateProfileStat('declined', stats.declined || 0);
        updateProfileStat('checked', stats.total || 0);
    }
    
    // Function to get user statistics
    function getUserStatistics() {
        let stats = localStorage.getItem('userStats');
        if (stats) {
            return JSON.parse(stats);
        }
        
        return {
            total: 0,
            charged: 0,
            approved: 0,
            threeds: 0,
            declined: 0
        };
    }
    
    // Function to update a single profile statistic
    function updateProfileStat(type, value) {
        const element = document.getElementById(`profile-${type}-value`);
        if (element) {
            element.textContent = value;
            
            element.style.transform = 'scale(1.2)';
            setTimeout(() => {
                element.style.transform = 'scale(1)';
            }, 300);
        }
    }
    
    // Function to update user statistics
    function updateUserStatistics(result) {
        const stats = getUserStatistics();
        
        stats.total = (stats.total || 0) + 1;
        
        if (result.status === 'CHARGED') {
            stats.charged = (stats.charged || 0) + 1;
        } else if (result.status === 'APPROVED') {
            stats.approved = (stats.approved || 0) + 1;
        } else if (result.status === '3DS') {
            stats.threeds = (stats.threeds || 0) + 1;
        } else {
            stats.declined = (stats.declined || 0) + 1;
        }
        
        localStorage.setItem('userStats', JSON.stringify(stats));
        
        if (document.getElementById('page-profile').classList.contains('active')) {
            loadUserStatistics();
        }
    }
    
    // Initialize the application
    function initializeApp() {
        console.log("Initializing application...");
        
        // Disable copy, context menu, and dev tools
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
        
        // Initialize maxConcurrent based on selected gateway
        updateMaxConcurrent();
        
        console.log("Application initialized successfully");
    }
    
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
            
            if (pageName === 'profile') {
                loadUserProfile();
            }
            
            if (pageName === 'home') {
                updateGlobalStats();
                updateTopUsers();
            }
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

    // Sidebar functions with improved animation
    function closeSidebar() {
        sidebarOpen = false;
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        
        // Use requestAnimationFrame for smoother animation
        requestAnimationFrame(() => {
            if (sidebar) {
                sidebar.classList.remove('open');
                // Force reflow to ensure transition starts
                void sidebar.offsetWidth;
            }
            if (mainContent) {
                mainContent.classList.remove('sidebar-open');
                // Force reflow to ensure transition starts
                void mainContent.offsetWidth;
            }
        });
    }

    // Gateway modal functions
    function openGatewayModal() {
        const modal = document.getElementById('gatewayModal');
        if (modal) {
            modal.classList.add('active');
            showProviderSelection();
            loadSavedGatewaySettings();
        }
    }

    function closeGatewayModal() {
        const modal = document.getElementById('gatewayModal');
        if (modal) {
            modal.classList.remove('active');
            
            setTimeout(() => {
                showProviderSelection();
            }, 300);
        }
    }

    function showProviderSelection() {
        const providerSelection = document.getElementById('providerSelection');
        const gatewaySelection = document.getElementById('gatewaySelection');
        const gatewayBtnBack = document.getElementById('gatewayBtnBack');
        
        if (providerSelection) providerSelection.classList.remove('hidden');
        if (gatewaySelection) gatewaySelection.classList.remove('active');
        if (gatewayBtnBack) gatewayBtnBack.style.display = 'none';
    }

    function showProviderGateways(provider) {
        const gatewayGroups = document.querySelectorAll('.gateway-group');
        gatewayGroups.forEach(group => {
            group.style.display = 'none';
        });
        
        const providerGateways = document.getElementById(`${provider}-gateways`);
        if (providerGateways) {
            providerGateways.style.display = 'block';
        }
        
        const providerSelection = document.getElementById('providerSelection');
        const gatewaySelection = document.getElementById('gatewaySelection');
        const gatewayBtnBack = document.getElementById('gatewayBtnBack');
        
        if (providerSelection) providerSelection.classList.add('hidden');
        if (gatewaySelection) gatewaySelection.classList.add('active');
        if (gatewayBtnBack) gatewayBtnBack.style.display = 'flex';
    }

    function loadSavedGatewaySettings() {
        const savedGateway = localStorage.getItem('selectedGateway');
        if (savedGateway) {
            const radioInput = document.querySelector(`input[name="gateway"][value="${savedGateway}"]`);
            if (radioInput) {
                radioInput.checked = true;
            }
        }
    }

    function saveGatewaySettings() {
        const selected = document.querySelector('input[name="gateway"]:checked');
        if (selected) {
            selectedGateway = selected.value;
            
            updateMaxConcurrent();
            
            const gatewayName = selected.parentElement.querySelector('.gateway-option-name');
            const nameText = gatewayName ? gatewayName.textContent.trim() : 'Unknown Gateway';
            
            localStorage.setItem('selectedGateway', selectedGateway);
            
            closeGatewayModal();
            
            setTimeout(() => {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'Gateway Updated!',
                        text: `Now using: ${nameText}`,
                        confirmButtonColor: '#10b981',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            }, 300);
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

    // Legacy functions for backward compatibility
    function openGatewaySettings() {
        openGatewayModal();
    }

    function closeGatewaySettings() {
        closeGatewayModal();
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
        
        const formattedResponse = formatResponse(response);
        
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
                    <div class="stat-label" style="color: ${color}; font-size: 0.7rem;">${status} - ${formattedResponse}</div>
                </div>
                <button class="copy-btn"><i class="fas fa-copy"></i></button>
            </div>
        `;
        
        const copyButton = resultDiv.querySelector('.copy-btn');
        if (copyButton) {
            copyButton.addEventListener('click', () => copyToClipboard(card.displayCard));
        }
        
        if (resultsList.classList.contains('empty-state')) {
            resultsList.classList.remove('empty-state');
            resultsList.innerHTML = '';
        }
        
        resultsList.insertBefore(resultDiv, resultsList.firstChild);
        
        addActivityItem(card, status);
        
        updateUserStatistics({ status });
        
        setTimeout(() => updateGlobalStats(), 1000);
        
        if (status === 'CHARGED') {
            setTimeout(() => updateTopUsers(), 1000);
        }
    }

    // Activity feed function
    function addActivityItem(card, status) {
        const activityList = document.getElementById('activityList');
        if (!activityList) return;
        
        if (activityList.querySelector('.empty-state')) {
            activityList.innerHTML = '';
        }
        
        const activityItem = document.createElement('div');
        activityItem.className = `activity-item ${status.toLowerCase()}`;
        
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
        
        activityList.insertBefore(activityItem, activityList.firstChild);
        
        while (activityList.children.length > 5) {
            activityList.removeChild(activityList.lastChild);
        }
    }

    // Generated cards display function
    function displayGeneratedCards(cards) {
        const cardsList = document.getElementById('generatedCardsList');
        if (!cardsList) return;
        
        if (cardsList.classList.contains('empty-state')) {
            cardsList.classList.remove('empty-state');
            cardsList.innerHTML = '';
        }
        
        const cardsContainer = document.createElement('div');
        cardsContainer.className = 'generated-cards-container';
        cardsContainer.textContent = cards.join('\n');
        
        cardsList.innerHTML = '';
        cardsList.appendChild(cardsContainer);
        
        const copyAllBtn = document.getElementById('copyAllBtn');
        const clearAllBtn = document.getElementById('clearAllBtn');
        if (copyAllBtn) copyAllBtn.style.display = 'flex';
        if (clearAllBtn) clearAllBtn.style.display = 'flex';
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
        
        if (typeof response === 'string') {
            try {
                response = JSON.parse(response);
            } catch (e) {
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
                }
                
                message = response;
                return { status, message };
            }
        }
        
        if (typeof response === 'object') {
            if (response.status) {
                status = String(response.status).toUpperCase();
            } else if (response.result) {
                status = String(response.result).toUpperCase();
            } else if (response.response) {
                const responseStr = String(response.response).toUpperCase();
                if (responseStr.includes('CHARGED')) {
                    status = 'CHARGED';
                } else if (responseStr.includes('APPROVED')) {
                    status = 'APPROVED';
                } else if (responseStr.includes('3D') || responseStr.includes('THREE_D')) {
                    status = '3DS';
                }
            }
            
            message = response.message || 
                     response.response || 
                     response.result || 
                     response.error || 
                     response.description ||
                     response.reason ||
                     JSON.stringify(response);
        }
        
        if (status !== 'CHARGED' && status !== 'APPROVED' && status !== '3DS') {
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

            const statusLog = document.getElementById('statusLog');
            if (statusLog) statusLog.textContent = `Processing card: ${card.displayCard}`;
            console.log(`Starting request for card: ${card.displayCard}`);

            fetch(selectedGateway, {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
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

                let errorResponse = `Declined [Request failed: ${error.message}]`;
                if (error.message.includes('HTTP error')) {
                    try {
                        const errorData = JSON.parse(error.message.split('HTTP error! ')[1]);
                        const parsedError = parseGatewayResponse(errorData);
                        errorResponse = parsedError.message;
                    } catch (e) {
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

        const workers = Array(maxConcurrent).fill(null).map(() => ({
            busy: false,
            currentCard: null,
            controller: null
        }));

        const assignCardToWorker = async (workerIndex) => {
            if (!isProcessing || cardQueue.length === 0 || isStopping) {
                if (workers.every(w => !w.busy)) {
                    finishProcessing();
                }
                return;
            }

            const worker = workers[workerIndex];
            if (worker.busy) return;

            const card = cardQueue.shift();
            if (!card) {
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
                worker.busy = false;
                worker.currentCard = null;
                activeRequests--;
                
                setTimeout(() => assignCardToWorker(workerIndex), 0);
            }
        };

        for (let i = 0; i < maxConcurrent; i++) {
            setTimeout(() => assignCardToWorker(i), i * 10);
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
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
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
            
            if (window.Swal) {
                Swal.fire({
                    title: 'Error!',
                    text: `Failed to generate cards: ${error.message}`,
                    icon: 'error',
                    confirmButtonColor: '#ec4899'
                });
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
                    if (activityUpdateInterval) {
                        clearInterval(activityUpdateInterval);
                    }
                    
                    if (globalStatsInterval) {
                        clearInterval(globalStatsInterval);
                    }
                    
                    if (topUsersInterval) {
                        clearInterval(topUsersInterval);
                    }
                    
                    if (window.activityRequest) {
                        window.activityRequest.abort();
                        window.activityRequest = null;
                    }
                    
                    if (activityRequestTimeout) {
                        clearTimeout(activityRequestTimeout);
                        activityRequestTimeout = null;
                    }
                    
                    sessionStorage.clear();
                    localStorage.clear();
                    window.location.href = 'login.php';
                }
            });
        } else {
            if (confirm('Are you sure you want to logout?')) {
                if (activityUpdateInterval) {
                    clearInterval(activityUpdateInterval);
                }
                
                if (globalStatsInterval) {
                    clearInterval(globalStatsInterval);
                }
                
                if (topUsersInterval) {
                    clearInterval(topUsersInterval);
                }
                
                if (window.activityRequest) {
                    window.activityRequest.abort();
                    window.activityRequest = null;
                }
                
                if (activityRequestTimeout) {
                    clearTimeout(activityRequestTimeout);
                    activityRequestTimeout = null;
                }
                
                sessionStorage.clear();
                localStorage.clear();
                window.location.href = 'login.php';
            }
        }
    }

    // User activity update function
    function updateUserActivity() {
        console.log("Updating user activity at", new Date().toISOString());
        
        if (window.activityRequest) {
            console.log("Previous activity request still pending, skipping...");
            return;
        }
        
        const controller = new AbortController();
        window.activityRequest = controller;
        
        if (activityRequestTimeout) {
            clearTimeout(activityRequestTimeout);
        }
        
        activityRequestTimeout = setTimeout(() => {
            if (window.activityRequest === controller) {
                controller.abort();
                window.activityRequest = null;
                console.log("Activity request timed out after 60 seconds");
            }
        }, 60000);
        
        fetch('/update_activity.php', {
            method: 'POST',
            signal: controller.signal,
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            },
            body: JSON.stringify({ timestamp: Date.now() })
        })
        .then(response => {
            if (activityRequestTimeout) {
                clearTimeout(activityRequestTimeout);
                activityRequestTimeout = null;
            }
            window.activityRequest = null;
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log("Activity update response:", data);
            
            if (data.success) {
                const onlineCountElement = document.getElementById('onlineCount');
                if (onlineCountElement) {
                    onlineCountElement.textContent = data.count;
                    console.log("Updated online count to:", data.count);
                } else {
                    console.error("Element #onlineCount not found");
                }
                
                const mobileOnlineCountElement = document.getElementById('mobileOnlineCount');
                if (mobileOnlineCountElement) {
                    mobileOnlineCountElement.textContent = data.count;
                    console.log("Updated mobile online count to:", data.count);
                } else {
                    console.error("Element #mobileOnlineCount not found");
                }
                
                const currentUser = data.users ? data.users.find(u => u.is_currently_online) : null;
                if (currentUser) {
                    const profilePicElement = document.querySelector('.user-avatar');
                    if (profilePicElement) {
                        profilePicElement.src = currentUser.photo_url || 
                            `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.name ? currentUser.name[0] : 'U')}&background=3b82f6&color=fff&size=64`;
                        console.log("Updated profile picture");
                    } else {
                        console.error("Element .user-avatar not found");
                    }
                    
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
                
                if (data.users) {
                    displayOnlineUsers(data.users);
                    displayMobileOnlineUsers(data.users);
                }
                
            } else {
                console.error('Activity update failed:', data.message || 'No error message provided');
            }
        })
        .catch(error => {
            if (activityRequestTimeout) {
                clearTimeout(activityRequestTimeout);
                activityRequestTimeout = null;
            }
            window.activityRequest = null;
            
            console.error('Activity update error:', error);
            
            if (error.name !== 'AbortError') {
                let errorMessage = 'Error fetching online users';
                
                if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'Network error - please check your connection';
                } else if (error.message.includes('HTTP error')) {
                    errorMessage = 'Server error - please try again later';
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
        
        onlineUsersList.innerHTML = '';
        
        if (!Array.isArray(users) || users.length === 0) {
            onlineUsersList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Users Online</h3>
                    <p>No users are currently online</p>
                </div>`;
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        users.forEach((user, index) => {
            const name = (user.name && typeof user.name === 'string') ? user.name.trim() : 'Unknown User';
            const username = (user.username && typeof user.username === 'string') ? user.username : '';
            const photoUrl = (user.photo_url && typeof user.photo_url === 'string') ? 
                user.photo_url : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
            
            const userItem = document.createElement('div');
            userItem.className = 'online-user-item';
            
            if (username === '@K4LNX') {
                userItem.classList.add('admin');
            }
            
            userItem.setAttribute('data-user-id', username || `unknown-${index}`);
            
            const avatarContainer = document.createElement('div');
            avatarContainer.className = 'online-user-avatar-container';
            
            const avatar = document.createElement('img');
            avatar.src = photoUrl;
            avatar.alt = name;
            avatar.className = 'online-user-avatar';
            avatar.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
            };
            
            const indicator = document.createElement('div');
            indicator.className = 'online-indicator';
            
            avatarContainer.appendChild(avatar);
            avatarContainer.appendChild(indicator);
            
            const userInfo = document.createElement('div');
            userInfo.className = 'online-user-info';
            
            const nameElement = document.createElement('div');
            nameElement.className = 'online-user-name';
            
            if (username === '@K4LNX') {
                const adminBadge = document.createElement('span');
                adminBadge.className = 'admin-badge';
                adminBadge.textContent = 'ADMIN';
                nameElement.appendChild(document.createTextNode(name));
                nameElement.appendChild(adminBadge);
            } else {
                nameElement.textContent = name;
            }
            
            if (username) {
                const usernameElement = document.createElement('div');
                usernameElement.className = 'online-user-username';
                usernameElement.textContent = username;
                userInfo.appendChild(nameElement);
                userInfo.appendChild(usernameElement);
            } else {
                userInfo.appendChild(nameElement);
            }
            
            userItem.appendChild(avatarContainer);
            userItem.appendChild(userInfo);
            
            fragment.appendChild(userItem);
        });
        
        onlineUsersList.innerHTML = '';
        onlineUsersList.appendChild(fragment);
        
        console.log("Successfully rendered online users list");
    }
    
    // Display mobile online users function
    function displayMobileOnlineUsers(users) {
        console.log("displayMobileOnlineUsers called with users:", users);
        
        const mobileOnlineUsersList = document.getElementById('mobileOnlineUsersList');
        if (!mobileOnlineUsersList) {
            console.error("Element #mobileOnlineUsersList not found in DOM");
            return;
        }
        
        mobileOnlineUsersList.innerHTML = '';
        
        if (!Array.isArray(users) || users.length === 0) {
            mobileOnlineUsersList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Users Online</h3>
                    <p>No users are currently online</p>
                </div>`;
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        users.forEach((user, index) => {
            const name = (user.name && typeof user.name === 'string') ? user.name.trim() : 'Unknown User';
            const username = (user.username && typeof user.username === 'string') ? user.username : '';
            const photoUrl = (user.photo_url && typeof user.photo_url === 'string') ? 
                user.photo_url : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
            
            const userItem = document.createElement('div');
            userItem.className = 'mobile-online-user-item';
            
            if (username === '@K4LNX') {
                userItem.classList.add('admin');
            }
            
            userItem.setAttribute('data-user-id', username || `unknown-${index}`);
            
            const avatar = document.createElement('img');
            avatar.src = photoUrl;
            avatar.alt = name;
            avatar.className = 'mobile-online-user-avatar';
            avatar.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name.charAt(0) || 'U')}&background=3b82f6&color=fff&size=64`;
            };
            
            const nameElement = document.createElement('span');
            nameElement.className = 'mobile-online-user-name';
            
            if (username === '@K4LNX') {
                const adminBadge = document.createElement('span');
                adminBadge.className = 'admin-badge';
                adminBadge.textContent = 'ADMIN';
                nameElement.appendChild(document.createTextNode(name));
                nameElement.appendChild(adminBadge);
            } else {
                nameElement.textContent = name;
            }
            
            userItem.appendChild(avatar);
            userItem.appendChild(nameElement);
            
            fragment.appendChild(userItem);
        });
        
        mobileOnlineUsersList.innerHTML = '';
        mobileOnlineUsersList.appendChild(fragment);
        
        console.log("Successfully rendered mobile online users list");
    }

    // Initialize activity updates when the page loads
    function initializeActivityUpdates() {
        if (activityUpdateInterval) {
            clearInterval(activityUpdateInterval);
        }
        
        updateUserActivity();
        
        activityUpdateInterval = setInterval(() => {
            if (!window.activityRequest) {
                updateUserActivity();
            }
        }, 15000);
        
        if (window.$) {
            $(document).on('click mousemove keypress scroll', function() {
                const now = new Date().getTime();
                if (now - lastActivityUpdate >= 15000 && !window.activityRequest) {
                    console.log("User interaction detected, updating activity...");
                    updateUserActivity();
                    lastActivityUpdate = now;
                }
            });
        }
        
        if (window.$) {
            $(window).on('beforeunload', function() {
                if (activityUpdateInterval) {
                    clearInterval(activityUpdateInterval);
                }
                if (window.activityRequest) {
                    window.activityRequest.abort();
                    window.activityRequest = null;
                }
                if (activityRequestTimeout) {
                    clearTimeout(activityRequestTimeout);
                    activityRequestTimeout = null;
                }
                if (globalStatsInterval) {
                    clearInterval(globalStatsInterval);
                }
                if (topUsersInterval) {
                    clearInterval(topUsersInterval);
                }
                console.log("Cleared intervals on page unload");
            });
        }
    }

    // Initialize global stats updates
    function initializeGlobalStatsUpdates() {
        if (globalStatsInterval) {
            clearInterval(globalStatsInterval);
        }
        
        updateGlobalStats();
        
        globalStatsInterval = setInterval(() => {
            updateGlobalStats();
        }, 30000);
        
        console.log("Global stats updates initialized. Stats will update every 30 seconds.");
    }

    // Make functions globally accessible
    window.toggleTheme = toggleTheme;
    window.showPage = showPage;
    window.closeSidebar = closeSidebar;
    window.openGatewayModal = openGatewayModal;
    window.closeGatewayModal = closeGatewayModal;
    window.showProviderSelection = showProviderSelection;
    window.showProviderGateways = showProviderGateways;
    window.loadSavedGatewaySettings = loadSavedGatewaySettings;
    window.updateCardCount = updateCardCount;
    window.filterResults = filterResults;
    window.setYearRnd = setYearRnd;
    window.setCvvRnd = setCvvRnd;
    window.logout = logout;
    window.loadUserProfile = loadUserProfile;
    window.updateUserStatistics = updateUserStatistics;
    window.updateTopUsers = updateTopUsers;
    window.displayTopUsers = displayTopUsers;
    window.displayMobileTopUsers = displayMobileTopUsers;

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
            if (stopBtn) {
                stopBtn.addEventListener('click', function() {
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
            }

            if (clearBtn) clearBtn.addEventListener('click', function() {
                if (cardInput && cardInput.value.trim()) {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Clear Input?', text: 'Remove all entered cards',
                            icon: 'warning', showCancelButton: true,
                            confirmButtonColor: '#ef4444', 
                            confirmButtonText: 'Yes, clear'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                cardInput.value = '';
                                updateCardCount();
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
                        });
                    } else {
                        if (confirm('Remove all entered cards?')) {
                            cardInput.value = '';
                            updateCardCount();
                        }
                    }
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
                        toast: true, 
                        position: 'top-end', 
                        icon: 'success',
                        title: 'Exported!', 
                        showConfirmButton: false, 
                        timer: 1500
                    });
                }
            });

            if (cardInput) cardInput.addEventListener('input', updateCardCount);

            // Gateway modal event listeners
            const gatewayBtnBack = document.getElementById('gatewayBtnBack');
            const gatewayBtnSave = document.getElementById('gatewayBtnSave');
            const gatewayBtnCancel = document.getElementById('gatewayBtnCancel');
            const gatewayModalClose = document.getElementById('gatewayModalClose');
            
            if (gatewayBtnBack) {
                gatewayBtnBack.addEventListener('click', function() {
                    showProviderSelection();
                });
            }
            
            if (gatewayBtnSave) {
                gatewayBtnSave.addEventListener('click', function() {
                    saveGatewaySettings();
                });
            }
            
            if (gatewayBtnCancel) {
                gatewayBtnCancel.addEventListener('click', function() {
                    closeGatewayModal();
                });
            }
            
            if (gatewayModalClose) {
                gatewayModalClose.addEventListener('click', function() {
                    closeGatewayModal();
                });
            }
            
            document.addEventListener('click', function(e) {
                const modal = document.getElementById('gatewayModal');
                if (modal && modal.classList.contains('active') && 
                    e.target === modal) {
                    closeGatewayModal();
                }
            });

            if (menuToggle) menuToggle.addEventListener('click', function() {
                sidebarOpen = !sidebarOpen;
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                
                // Use requestAnimationFrame for smoother animation
                requestAnimationFrame(() => {
                    if (sidebar) {
                        sidebar.classList.toggle('open', sidebarOpen);
                        // Force reflow to ensure transition starts
                        void sidebar.offsetWidth;
                    }
                    if (mainContent) {
                        mainContent.classList.toggle('sidebar-open', sidebarOpen);
                        // Force reflow to ensure transition starts
                        void mainContent.offsetWidth;
                    }
                });
            });
            
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            const themeIcon = document.querySelector('.theme-toggle-slider i');
            if (themeIcon) themeIcon.className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            
            console.log("Page loaded, initializing user activity update...");
            
            // Initialize the app
            initializeApp();
            
            // RAZORPAY GATEWAY MAINTENANCE FEATURE
            const razorpayGateway = document.querySelector('input[name="gateway"][value="gate/razorpay.php"]');
            if (razorpayGateway) {
                razorpayGateway.disabled = true;
                
                const parentLabel = razorpayGateway.closest('label');
                if (parentLabel) {
                    parentLabel.style.opacity = '0.6';
                    parentLabel.style.cursor = 'not-allowed';
                    parentLabel.style.position = 'relative';
                    
                    const badge = document.createElement('span');
                    badge.textContent = 'Maintenance';
                    badge.style.position = 'absolute';
                    badge.style.top = '5px';
                    badge.style.right = '5px';
                    badge.style.backgroundColor = '#ef4444';
                    badge.style.color = 'white';
                    badge.style.padding = '2px 6px';
                    badge.style.borderRadius = '6px';
                    badge.style.fontSize = '0.7rem';
                    badge.style.fontWeight = 'bold';
                    badge.textTransform = 'uppercase';
                    parentLabel.appendChild(badge);
                    
                    parentLabel.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Gateway Under Maintenance',
                                text: 'The Razorpay gateway is currently undergoing maintenance. Please select another gateway.',
                                icon: 'error',
                                confirmButtonColor: '#ef4444',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Gateway under maintenance. Please select another gateway.');
                        }
                    });
                }
            }
        });
    }
});
