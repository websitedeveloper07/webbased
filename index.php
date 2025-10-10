<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CardX Check - Multi Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #333;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 10px;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: scale(1.02);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }
        .header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .header p {
            font-size: 1rem;
            color: white;
            opacity: 0.8;
            margin: 5px 0 0;
        }
        .menu-toggle {
            position: relative;
            cursor: pointer;
            color: #ffc107;
            font-size: 1.5rem;
            padding: 10px;
            transition: transform 0.2s ease;
        }
        .menu-toggle:hover {
            transform: scale(1.1);
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            display: none;
            z-index: 1000;
            animation: slideIn 0.2s ease-out;
        }
        .dropdown-menu.show {
            display: block;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dropdown-item {
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-control::placeholder {
            color: #999;
        }
        select.form-control {
            cursor: pointer;
        }
        .input-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        .btn-danger {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: white;
        }
        .btn-danger:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(245, 87, 108, 0.3);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.2s ease;
        }
        .stat-item:hover {
            transform: scale(1.03);
        }
        .stat-item.charged {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        .stat-item .label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 6px;
        }
        .stat-item .value {
            font-size: 24px;
            font-weight: 700;
        }
        .result-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        .result-card:hover {
            transform: scale(1.02);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
        }
        .result-title {
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .result-content {
            max-height: 400px;
            overflow-y: auto;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        .result-content::-webkit-scrollbar {
            width: 6px;
        }
        .result-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s ease;
        }
        .action-btn:hover {
            transform: scale(1.1);
        }
        .eye-btn { background: #6c757d; }
        .copy-btn { background: #28a745; }
        .trash-btn { background: #dc3545; }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .card-count {
            font-size: 13px;
            color: #555;
            margin-top: 8px;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: white;
            font-size: 14px;
            margin-top: 30px;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: inherit;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
            max-width: 360px;
            text-align: center;
        }
        .login-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.8rem; }
            .header p { font-size: 0.9rem; }
            .input-grid { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; align-items: center; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
            .result-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .card { padding: 15px; }
            .login-card { padding: 20px; }
            .dropdown-menu { min-width: 160px; }
        }
    </style>
</head>
<body>
    <!-- Login Panel -->
    <div class="login-container" id="loginContainer">
        <div class="login-card">
            <h2><i class="fas fa-lock"></i> CardX Check Login</h2>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" class="form-control" placeholder="Enter username" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" class="form-control" placeholder="Enter password">
            </div>
            <button class="btn btn-primary login-btn" id="loginBtn">Login</button>
        </div>
    </div>

    <!-- Main Checker UI -->
    <div class="container hidden" id="checkerContainer">
        <div class="header">
            <div>
                <h1><i class="fas fa-credit-card"></i> Card X CHK</h1>
                <p>Multi-Gateway Card Checker</p>
            </div>
            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-ellipsis-v"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <div class="dropdown-item" data-view="charged"><i class="fas fa-bolt" style="color: #ffc107;"></i> Charged Cards</div>
                    <div class="dropdown-item" data-view="approved"><i class="fas fa-check-circle" style="color: #28a745;"></i> Approved Cards</div>
                    <div class="dropdown-item" data-view="declined"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Declined Cards</div>
                </div>
            </div>
        </div>

        <!-- Input Section -->
        <div class="card">
            <div class="input-grid">
                <div class="form-group">
                    <label for="cards">Card List (Format: card|MM|YY or YYYY|CVV)</label>
                    <textarea id="cards" class="form-control" rows="6" placeholder="4147768578745265|04|26|168&#10;4242424242424242|12|2025|123"></textarea>
                    <div class="card-count" id="card-count">0 valid cards detected</div>
                </div>
                <div class="form-group">
                    <label for="gate">Select Gateway</label>
                    <select id="gate" class="form-control">
                        <option value="gate/stripeauth.php">Stripe Auth</option>
                        <option value="gate/paypal1$.php">PayPal 1$</option>
                        <option value="gate/shopify1$.php">Shopify 1$</option>
                        <option value="gate/paypal.php" disabled>PayPal (Coming Soon)</option>
                        <option value="gate/razorpay.php" disabled>Razorpay (Coming Soon)</option>
                    </select>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-play" id="startBtn">
                            <i class="fas fa-play"></i> Start Check
                        </button>
                        <button class="btn btn-danger btn-stop" id="stopBtn" disabled>
                            <i class="fas fa-stop"></i> Stop
                        </button>
                    </div>
                    <div class="loader" id="loader"></div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">Total</div>
                    <div class="value carregadas">0</div>
                </div>
                <div class="stat-item charged">
                    <div class="label">Charged</div>
                    <div class="value charged">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="label">Approved</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <div class="label">Declined</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="label">Checked</div>
                    <div class="value checked">0 / 0</div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="result-card">
            <div class="result-header">
                <h3 class="result-title" id="resultTitle"><i class="fas fa-bolt" style="color: #ffc107;"></i> Charged Cards</h3>
                <div class="action-buttons">
                    <button class="action-btn eye-btn" id="toggleResult" type="show"><i class="fas fa-eye-slash"></i></button>
                    <button class="action-btn copy-btn" id="copyResult"><i class="fas fa-copy"></i></button>
                    <button class="action-btn trash-btn" id="clearResult"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div id="resultContent" class="result-content" style="display: none;"></div>
        </div>
    </div>

    <footer class="hidden" id="footer">
        <p><strong>© 2025 Card X CheckHK - Multi-Gateway CHECKER</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            let isProcessing = false;
            let isStopping = false;
            let activeRequests = 0;
            let cardQueue = [];
            const MAX_CONCURRENT = 3;
            const MAX_RETRIES = 1;
            let abortControllers = [];
            let totalCards = 0;
            let chargedCards = [];
            let approvedCards = [];
            let declinedCards = [];
            let currentView = 'charged';

            // Login Logic
            const validUsername = 'admin';
            const validPassword = 'password123';

            function showCheckerUI() {
                $('#loginContainer').addClass('hidden');
                $('#checkerContainer').removeClass('hidden');
                $('#footer').removeClass('hidden');
            }

            $('#loginBtn').click(function() {
                const username = $('#username').val().trim();
                const password = $('#password').val().trim();

                if (username === validUsername && password === validPassword) {
                    showCheckerUI();
                } else {
                    Swal.fire({
                        title: 'Login Failed',
                        text: 'Invalid username or password',
                        icon: 'error',
                        confirmButtonText: 'Try Again',
                        confirmButtonColor: '#667eea'
                    });
                }
            });

            $('#username, #password').keypress(function(e) {
                if (e.which === 13) {
                    $('#loginBtn').click();
                }
            });

            // Card validation and counter
            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
                
                if ($(this).val().trim()) {
                    $('.carregadas').text('0');
                    $('.charged').text('0');
                    $('.approved').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                    chargedCards = [];
                    approvedCards = [];
                    declinedCards = [];
                    renderResult();
                }
            });

            // Three-dot menu toggle
            $('#menuToggle').click(function(e) {
                e.stopPropagation();
                $('#dropdownMenu').toggleClass('show');
            });

            $(document).click(function(e) {
                if (!$(e.target).closest('#menuToggle, #dropdownMenu').length) {
                    $('#dropdownMenu').removeClass('show');
                }
            });

            // View switching
            $('.dropdown-item').click(function() {
                const view = $(this).data('view');
                currentView = view;
                $('.dropdown-item').removeClass('active');
                $(this).addClass('active');
                $('#dropdownMenu').removeClass('show');
                renderResult();
            });

            function renderResult() {
                const viewConfig = {
                    charged: { title: 'Charged Cards', icon: 'fa-bolt', color: '#ffc107', data: chargedCards, clearable: true },
                    approved: { title: 'Approved Cards', icon: 'fa-check-circle', color: '#28a745', data: approvedCards, clearable: false },
                    declined: { title: 'Declined Cards', icon: 'fa-times-circle', color: '#dc3545', data: declinedCards, clearable: true }
                };
                const config = viewConfig[currentView];
                $('#resultTitle').html(`<i class="fas ${config.icon}" style="color: ${config.color};"></i> ${config.title}`);
                $('#resultContent').empty();
                if (config.data.length === 0) {
                    $('#resultContent').append('<span style="color: #555;">No cards yet</span>');
                } else {
                    config.data.forEach(item => {
                        $('#resultContent').append(`<span style="color: ${config.color}; font-family: 'Inter', sans-serif;">${item}</span><br>`);
                    });
                }
                $('#clearResult').toggle(config.clearable);
                const isHidden = $('#resultContent').is(':hidden');
                $('#toggleResult').html(`<i class="fas fa-${isHidden ? 'eye-slash' : 'eye'}"></i>`);
            }

            // UI Functions
            $('#toggleResult').click(function() {
                const isHidden = $('#resultContent').is(':hidden');
                $('#resultContent').toggle();
                $(this).html(`<i class="fas fa-${isHidden ? 'eye-slash' : 'eye'}"></i>`);
                $(this).attr('type', isHidden ? 'hidden' : 'show');
            });

            $('#copyResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards },
                    approved: { title: 'Approved cards', data: approvedCards },
                    declined: { title: 'Declined cards', data: declinedCards }
                };
                const config = viewConfig[currentView];
                const text = config.data.join('\n');
                if (!text) {
                    Swal.fire({
                        title: 'Nothing to copy!',
                        text: `${config.title} list is empty`,
                        icon: 'info',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Swal.fire({
                    title: `Copied ${config.title}!`,
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            $('#clearResult').click(function() {
                const viewConfig = {
                    charged: { title: 'Charged cards', data: chargedCards, counter: '.charged' },
                    declined: { title: 'Declined cards', data: declinedCards, counter: '.reprovadas' }
                };
                const config = viewConfig[currentView];
                if (!config) return;
                Swal.fire({
                    title: `Clear ${config.title.toLowerCase()}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, clear!',
                    confirmButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        config.data.length = 0;
                        $(config.counter).text('0');
                        renderResult();
                        $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);
                        Swal.fire('Cleared!', '', 'success');
                    }
                });
            });

            // Process a single card with retry
            async function processCard(card, controller, retryCount = 0) {
                if (!isProcessing) {
                    console.log(`Skipping card ${card.displayCard} due to stop`);
                    return null;
                }

                return new Promise((resolve) => {
                    const formData = new FormData();
                    let normalizedYear = card.exp_year;
                    if (normalizedYear.length === 2) {
                        normalizedYear = (parseInt(normalizedYear) < 50 ? '20' : '19') + normalizedYear;
                    }
                    formData.append('card[number]', card.number);
                    formData.append('card[exp_month]', card.exp_month);
                    formData.append('card[exp_year]', normalizedYear);
                    formData.append('card[cvc]', card.cvc);

                    console.log(`Sending card ${card.displayCard} to ${$('#gate').val()}`);

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000,
                        signal: controller.signal,
                        success: function(response) {
                            console.log(`Success for ${card.displayCard}: ${response}`);
                            const status = response.includes('CHARGED') ? 'CHARGED' : response.includes('APPROVED') ? 'APPROVED' : 'DECLINED';
                            resolve({
                                status: status,
                                response: response.trim(),
                                card: card,
                                displayCard: card.displayCard
                            });
                        },
                        error: function(xhr) {
                            if (xhr.statusText === 'abort') {
                                console.log(`Aborted card ${card.displayCard}`);
                                resolve(null);
                            } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                                console.warn(`Retrying card ${card.displayCard} (Attempt ${retryCount + 2}) due to error: ${xhr.statusText} (${xhr.status})`);
                                setTimeout(() => {
                                    processCard(card, controller, retryCount + 1).then(resolve);
                                }, 1000);
                            } else {
                                const errorMsg = xhr.responseText ? xhr.responseText.substring(0, 100) : 'Request failed';
                                console.error(`Failed card ${card.displayCard}: ${xhr.statusText} (HTTP ${xhr.status}) - ${errorMsg}`);
                                resolve({
                                    status: 'DECLINED',
                                    response: `DECLINED [Request failed: ${xhr.statusText} (HTTP ${xhr.status})] ${card.displayCard}`,
                                    card: card,
                                    displayCard: card.displayCard
                                });
                            }
                        }
                    });
                });
            }

            // Main processing function
            async function processCards() {
                if (isProcessing) {
                    console.warn('Processing already in progress');
                    return;
                }

                const cardText = $('#cards').val().trim();
                const lines = cardText.split('\n').filter(line => line.trim());
                const validCards = lines
                    .map(line => line.trim())
                    .filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line))
                    .map(line => {
                        const [number, exp_month, exp_year, cvc] = line.split('|');
                        return { number, exp_month, exp_year, cvc, displayCard: `${number}|${exp_month}|${exp_year}|${cvc}` };
                    });

                if (validCards.length === 0) {
                    Swal.fire({
                        title: 'No valid cards!',
                        text: 'Please check your card format',
                        icon: 'error',
                        confirmButtonColor: '#667eea'
                    });
                    console.error('No valid cards provided');
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire({
                        title: 'Limit exceeded!',
                        text: 'Maximum 1000 cards allowed',
                        icon: 'error',
                        confirmButtonColor: '#667eea'
                    });
                    console.error('Card limit exceeded');
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
                declinedCards = [];
                $('.carregadas').text(totalCards);
                $('.charged').text('0');
                $('.approved').text('0');
                $('.reprovadas').text('0');
                $('.checked').text(`0 / ${totalCards}`);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                console.log(`Starting processing for ${totalCards} cards`);
                renderResult();

                let requestIndex = 0;

                while (cardQueue.length > 0 && isProcessing) {
                    while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                        const card = cardQueue.shift();
                        if (!card) break;
                        activeRequests++;
                        const controller = new AbortController();
                        abortControllers.push(controller);

                        await new Promise(resolve => setTimeout(resolve, requestIndex * 200));
                        requestIndex++;

                        processCard(card, controller).then(result => {
                            if (result === null) return;

                            activeRequests--;
                            if (result.status === 'CHARGED') {
                                chargedCards.push(result.response);
                                $('.charged').text(chargedCards.length);
                            } else if (result.status === 'APPROVED') {
                                approvedCards.push(result.response);
                                $('.approved').text(approvedCards.length);
                            } else {
                                declinedCards.push(result.response);
                                $('.reprovadas').text(declinedCards.length);
                            }

                            $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);
                            console.log(`Completed ${chargedCards.length + approvedCards.length + declinedCards.length}/${totalCards}: ${result.response}`);

                            if (currentView === result.status.toLowerCase()) {
                                renderResult();
                            }

                            if (chargedCards.length + approvedCards.length + declinedCards.length >= totalCards || !isProcessing) {
                                finishProcessing();
                            }
                        });
                    }
                    if (isProcessing) {
                        await new Promise(resolve => setTimeout(resolve, 5));
                    }
                }
            }

            function finishProcessing() {
                isProcessing = false;
                isStopping = false;
                activeRequests = 0;
                cardQueue = [];
                abortControllers = [];
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                $('#cards').val('');
                $('#card-count').text('0 valid cards detected');
                Swal.fire({
                    title: 'Processing complete!',
                    text: 'All cards have been checked',
                    icon: 'success',
                    confirmButtonColor: '#667eea'
                });
                console.log('Processing completed');
                renderResult();
            }

            // Event handlers
            $('#startBtn').click(() => {
                console.log('Start Check clicked');
                processCards();
            });

            $('#stopBtn').click(() => {
                if (!isProcessing || isStopping) {
                    console.warn('Stop clicked but not processing or already stopping');
                    return;
                }

                isProcessing = false;
                isStopping = true;
                cardQueue = [];
                abortControllers.forEach(controller => controller.abort());
                abortControllers = [];
                activeRequests = 0;
                $('.checked').text(`${chargedCards.length + approvedCards.length + declinedCards.length} / ${totalCards}`);
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire({
                    title: 'Stopped!',
                    text: 'Processing has been stopped',
                    icon: 'warning',
                    confirmButtonColor: '#667eea',
                    allowOutsideClick: false
                });
                console.log('Processing stopped');
                renderResult();
            });

            $('#gate').change(function() {
                const selected = $(this).val();
                console.log(`Gateway changed to: ${selected}`);
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php') && !selected.includes('shopify1$.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth, PayPal 1$, and Shopify 1$ are currently available',
                        icon: 'info',
                        confirmButtonColor: '#667eea'
                    });
                    $(this).val('gate/stripeauth.php');
                    console.log('Reverted to stripeauth.php');
                }
            });

            // Initialize
            renderResult();
        });
    </script>
</body>
</html>
