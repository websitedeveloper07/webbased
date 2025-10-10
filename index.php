<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 - Beast Mode Multi-Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* BASE & THEME */
        :root {
            --color-dark-bg: #121212;
            --color-card-bg: rgba(30, 30, 30, 0.95);
            --color-text-light: #e0e0e0;
            --color-text-muted: #999;
            --color-primary: #8a2be2; /* BlueViolet */
            --color-primary-dark: #6a1b9a;
            --color-secondary: #00bcd4; /* Cyan */
            --color-success: #4caf50;
            --color-danger: #f44336;
            --border-radius: 12px;
            --shadow-elevation: 0 8px 25px rgba(0, 0, 0, 0.5);
            --shadow-inset: inset 0 0 10px rgba(0, 0, 0, 0.3);
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-dark-bg);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: var(--color-text-light);
            overflow-x: hidden;
            position: relative;
        }

        /* LIVE ANIMATION EFFECT (Rain/Particles) */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
            opacity: 0.2;
        }
        .drop {
            position: absolute;
            background: linear-gradient(to bottom, var(--color-primary), var(--color-secondary));
            border-radius: 50%;
            animation: rain ease-in-out infinite;
        }
        @keyframes rain {
            0% { transform: translateY(-100vh) scale(0.5); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(100vh) scale(1.2); opacity: 0.5; }
        }

        /* UTILITY */
        .hidden { display: none !important; }

        /* LAYOUT & CONTAINERS */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 15px;
            z-index: 10;
            position: relative;
        }
        .card {
            background: var(--color-card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-elevation);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevation), 0 0 20px var(--color-primary-dark);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin: 0;
            color: var(--color-secondary);
            text-shadow: 0 5px 15px rgba(0, 188, 212, 0.4);
        }
        .header p {
            font-size: 1.1rem;
            opacity: 0.7;
            margin-top: 5px;
        }

        /* FORM ELEMENTS */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--color-text-light);
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--color-primary);
            border-radius: var(--border-radius);
            font-size: 16px;
            color: var(--color-text-light);
            background: rgba(40, 40, 40, 0.8);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-inset);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-secondary);
            box-shadow: 0 0 10px rgba(0, 188, 212, 0.5);
        }
        .form-control::placeholder { color: var(--color-text-muted); }

        /* BUTTONS */
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--color-primary), var(--color-primary-dark));
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(138, 43, 226, 0.5);
        }
        .btn-danger {
            background: linear-gradient(45deg, var(--color-danger), #b71c1c);
            color: white;
        }
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(244, 67, 54, 0.5);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            background: var(--color-primary-dark);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .stat-item .label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .stat-item .value {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
        }
        /* Specific Stat Colors */
        .stat-item:nth-child(2) { background: linear-gradient(135deg, var(--color-success), #1b5e20); }
        .stat-item:nth-child(3) { background: linear-gradient(135deg, var(--color-danger), #b71c1c); }
        .stat-item:nth-child(4) { background: linear-gradient(135deg, var(--color-secondary), #00838f); }

        /* RESULTS AREA */
        .results-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        .result-card {
            background: var(--color-card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-elevation);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .result-title {
            font-weight: 600;
            color: var(--color-text-light);
            margin: 0;
            font-size: 1.2rem;
        }
        .result-content {
            max-height: 350px;
            overflow-y: auto;
            padding: 25px;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--color-text-light);
        }
        .result-content::-webkit-scrollbar { width: 8px; }
        .result-content::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 4px;
        }

        /* ACTIONS */
        .action-buttons { display: flex; gap: 10px; }
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        .eye-btn { background: #607d8b; } /* Blue Grey */
        .copy-btn { background: var(--color-success); }
        .trash-btn { background: var(--color-danger); }
        .action-btn:hover { opacity: 0.8; }

        /* LOADER */
        .loader {
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid var(--color-secondary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 30px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* LOGIN PANEL */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: inherit;
        }
        .login-card {
            background: var(--color-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--shadow-elevation);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .login-card h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-secondary);
            margin-bottom: 25px;
            text-shadow: 0 3px 10px rgba(0, 188, 212, 0.3);
        }
        .login-btn {
            width: 100%;
            padding: 15px;
            margin-top: 20px;
        }

        /* FOOTER */
        footer {
            text-align: center;
            padding: 30px;
            color: var(--color-text-muted);
            font-size: 14px;
            margin-top: 50px;
        }

        /* MEDIA QUERIES */
        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .header h1 { font-size: 2.5rem; }
            .stat-item .value { font-size: 1.8rem; }
            .btn-group { flex-direction: column; gap: 10px; }
            .btn { width: 100%; }
            .result-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>

    <div class="background-animation" id="backgroundAnimation"></div>

    <div class="login-container" id="loginContainer">
        <div class="login-card">
            <h2><i class="fas fa-lock"></i> System Access</h2>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" class="form-control" placeholder="Enter username" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" class="form-control" placeholder="Enter password">
            </div>
            <button class="btn btn-primary login-btn" id="loginBtn">ACCESS</button>
        </div>
    </div>

    <div class="container hidden" id="checkerContainer">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> CARD X CHK</h1>
            <p>High-Throughput Multi-Gateway Processing</p>
        </div>

        <div class="card" id="control-card">
            <div class="form-group">
                <label for="cards">Card List <span style="font-weight: 300;">(Format: card|MM|YY or YYYY|CVV)</span></label>
                <textarea id="cards" class="form-control" rows="6" placeholder="4147768578745265|04|26|168&#10;4242424242424242|12|2025|123"></textarea>
                <div class="card-count" style="font-size: 14px; color: var(--color-text-muted); margin-top: 10px;" id="card-count">0 valid cards detected</div>
            </div>
            <div class="form-group">
                <label for="gate">Select Gateway</label>
                <select id="gate" class="form-control">
                    <option value="gate/stripeauth.php">Stripe Auth</option>
                    <option value="gate/paypal1$.php">PayPal 1$</option>
                    <option value="gate/paypal.php" disabled>PayPal (Coming Soon)</option>
                    <option value="gate/razorpay.php" disabled>Razorpay (Coming Soon)</option>
                    <option value="gate/shopify.php" disabled>Shopify (Coming Soon)</option>
                </select>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary btn-play" id="startBtn">
                    <i class="fas fa-play"></i> Start Check
                </button>
                <button class="btn btn-danger btn-stop" id="stopBtn" disabled>
                    <i class="fas fa-stop"></i> Stop Processing
                </button>
            </div>
            <div class="loader" id="loader"></div>
        </div>

        <div class="card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">Total Loaded</div>
                    <div class="value carregadas">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Approved (HIT)</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Declined (DEAD)</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Checked Progress</div>
                    <div class="value checked">0 / 0</div>
                </div>
            </div>
        </div>

        <div class="results-grid">
            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-check-circle" style="color: var(--color-success);"></i> Approved Cards</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-approved" type="show" title="Toggle visibility"><i class="fas fa-eye-slash"></i></button>
                        <button class="action-btn copy-btn btn-copy-approved" title="Copy to clipboard"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div id="lista_approved" class="result-content" style="display: none;"></div>
            </div>

            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-times-circle" style="color: var(--color-danger);"></i> Declined Cards</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-declined" type="show" title="Toggle visibility"><i class="fas fa-eye-slash"></i></button>
                        <button class="action-btn copy-btn btn-copy-declined" title="Copy to clipboard"><i class="fas fa-copy"></i></button>
                        <button class="action-btn trash-btn btn-trash" title="Clear declined list"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div id="lista_declined" class="result-content" style="display: none;"></div>
            </div>
        </div>
    </div>

    <footer class="hidden" id="footer">
        <p><strong>© 2025 Card X CHK - Beast Mode Multi-Gateway CHECKER</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            // --- UI Setup: Background Animation ---
            const rainContainer = $('#backgroundAnimation');
            const numDrops = 50;

            for (let i = 0; i < numDrops; i++) {
                const drop = $('<div></div>').addClass('drop').css({
                    left: `${Math.random() * 100}%`,
                    width: `${Math.random() * 2 + 1}px`,
                    height: `${Math.random() * 50 + 20}px`,
                    animationDelay: `-${Math.random() * 10}s`,
                    animationDuration: `${Math.random() * 8 + 7}s`
                });
                rainContainer.append(drop);
            }

            // --- Core Checker Logic Variables (UNCHANGED) ---
            let isProcessing = false;
            let isStopping = false;
            let activeRequests = 0;
            let cardQueue = [];
            const MAX_CONCURRENT = 3; // 3 concurrent POST requests (UNCHANGED)
            const MAX_RETRIES = 1; // Retry once on failure (UNCHANGED)
            let abortControllers = [];
            let totalCards = 0;

            // --- Login Logic (UNCHANGED) ---
            const validUsername = 'admin'; // (UNCHANGED)
            const validPassword = 'password123'; // (UNCHANGED)

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
                        title: 'ACCESS DENIED',
                        text: 'Invalid credentials. Try again.',
                        icon: 'error',
                        confirmButtonText: 'Understood'
                    });
                }
            });

            // Allow Enter key to trigger login
            $('#username, #password').keypress(function(e) {
                if (e.which === 13) {
                    $('#loginBtn').click();
                }
            });

            // Card validation and counter
            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                // Card regex logic (UNCHANGED)
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
                
                // Clear stats on new input
                if ($(this).val().trim()) {
                    $('.carregadas').text('0');
                    $('.approved').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                }
            });

            // --- UI Functions ---
            function toggleVisibility(btn, sectionId) {
                const section = $(sectionId);
                const isHidden = section.is(':hidden');
                section.slideToggle(200);
                btn.html(`<i class="fas fa-${isHidden ? 'eye-slash' : 'eye'}"></i>`);
                btn.attr('type', isHidden ? 'hidden' : 'show');
            }

            function copyToClipboard(selector, title) {
                const text = $(selector).text().trim();
                if (!text) {
                    Swal.fire('No Data', `${title} list is empty`, 'info');
                    return;
                }
                
                const textarea = document.createElement('textarea');
                textarea.value = text.replace(/<br>/g, '\n'); // Replace HTML breaks with newlines
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                Swal.fire({
                    title: `COPIED!`,
                    text: `${title} copied to clipboard.`,
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    timer: 2500,
                    showConfirmButton: false
                });
            }

            // Event Listeners for UI buttons
            $('.show-approved').click(() => toggleVisibility($('.show-approved'), '#lista_approved'));
            $('.show-declined').click(() => toggleVisibility($('.show-declined'), '#lista_declined'));
            
            $('.btn-copy-approved').click(() => copyToClipboard('#lista_approved', 'Approved Cards'));
            $('.btn-copy-declined').click(() => copyToClipboard('#lista_declined', 'Declined Cards'));
            
            $('.btn-trash').click(() => {
                Swal.fire({
                    title: 'Clear Declined Log?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: var('--color-danger'),
                    confirmButtonText: 'Yes, CLEAR IT'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#lista_declined').empty();
                        $('.reprovadas').text('0');
                        Swal.fire('Cleared!', 'Declined log wiped.', 'success');
                    }
                });
            });

            // Gateway change handler (UNCHANGED LOGIC)
            $('#gate').change(function() {
                const selected = $(this).val();
                if ($(this).find(':selected').is(':disabled')) {
                    Swal.fire({
                        title: 'Gateway Unavailable',
                        text: 'This gateway is marked as "Coming Soon" and cannot be selected.',
                        icon: 'info'
                    });
                    $(this).val('gate/stripeauth.php'); // Revert to a default working gateway
                }
            });

            // --- Card Processing Core (UNCHANGED LOGIC) ---
            
            // Process a single card with retry (UNCHANGED LOGIC)
            async function processCard(card, controller, retryCount = 0) {
                if (!isProcessing) {
                    return null;
                }

                return new Promise((resolve) => {
                    const formData = new FormData();
                    let normalizedYear = card.exp_year;
                    // Year normalization logic (UNCHANGED)
                    if (normalizedYear.length === 2) {
                        normalizedYear = (parseInt(normalizedYear) < 50 ? '20' : '19') + normalizedYear;
                    }
                    formData.append('card[number]', card.number);
                    formData.append('card[exp_month]', card.exp_month);
                    formData.append('card[exp_year]', normalizedYear);
                    formData.append('card[cvc]', card.cvc);

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000, // 55s to accommodate 50s backend timeout (UNCHANGED)
                        signal: controller.signal,
                        success: function(response) {
                            resolve({
                                success: response.includes('APPROVED'),
                                response: response.trim(),
                                card: card,
                                displayCard: card.displayCard
                            });
                        },
                        error: function(xhr) {
                            if (xhr.statusText === 'abort') {
                                resolve(null);
                            } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                                // Retry logic (UNCHANGED)
                                setTimeout(() => {
                                    processCard(card, controller, retryCount + 1).then(resolve);
                                }, 1000); // 1s delay for retry
                            } else {
                                const errorMsg = xhr.responseText ? xhr.responseText.substring(0, 100) : 'Request failed';
                                resolve({
                                    success: false,
                                    response: `DECLINED [Request failed: ${xhr.statusText} (HTTP ${xhr.status})] ${card.displayCard}`,
                                    card: card,
                                    displayCard: card.displayCard
                                });
                            }
                        }
                    });
                });
            }

            // Main processing function with 3 concurrent requests and staggered delays (UNCHANGED LOGIC)
            async function processCards() {
                if (isProcessing) return;

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
                    Swal.fire('No Valid Cards', 'Please ensure cards are in the correct format: card|MM|YY|CVV', 'error');
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire('Limit Exceeded', 'Maximum 1000 cards allowed per batch.', 'warning');
                    return;
                }

                // Reset state
                isProcessing = true;
                isStopping = false;
                activeRequests = 0;
                abortControllers = [];
                cardQueue = [...validCards];
                totalCards = validCards.length;
                
                // UI updates
                $('.carregadas').text(totalCards);
                $('.approved').text('0');
                $('.reprovadas').text('0');
                $('.checked').text(`0 / ${totalCards}`);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                $('#lista_approved').empty();
                $('#lista_declined').empty();

                let completed = 0;
                let requestIndex = 0;

                while (cardQueue.length > 0 && isProcessing) {
                    while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                        const card = cardQueue.shift();
                        if (!card) break; 
                        activeRequests++;
                        const controller = new AbortController();
                        abortControllers.push(controller);

                        // Stagger requests logic (UNCHANGED)
                        await new Promise(resolve => setTimeout(resolve, requestIndex * 200));
                        requestIndex++;

                        processCard(card, controller).then(result => {
                            if (result === null) return;

                            completed++;
                            activeRequests--;

                            // Update results and stats (UNCHANGED logic for result handling)
                            if (result.response.includes('APPROVED')) {
                                $('#lista_approved').append(`<span style="color: var(--color-success);">${result.response}</span><br>`);
                                $('.approved').text(parseInt($('.approved').text()) + 1);
                            } else {
                                $('#lista_declined').append(`<span style="color: var(--color-danger);">${result.response}</span><br>`);
                                $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                            }

                            $('.checked').text(`${completed} / ${totalCards}`);

                            if (completed >= totalCards || !isProcessing) {
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
                
                Swal.fire('PROCESSING FINISHED', 'All queued cards have been checked.', 'success');
            }

            // --- Button Handlers (UNCHANGED LOGIC) ---
            $('#startBtn').click(processCards);

            $('#stopBtn').click(() => {
                if (!isProcessing || isStopping) return;

                isProcessing = false;
                isStopping = true;
                cardQueue = []; // Clear queue
                abortControllers.forEach(controller => controller.abort());
                abortControllers = [];
                activeRequests = 0;
                
                // Update final checked count
                $('.checked').text(`${parseInt($('.approved').text()) + parseInt($('.reprovadas').text())} / ${totalCards}`);
                
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();

                Swal.fire({
                    title: 'STOPPED!',
                    text: 'Processing manually halted.',
                    icon: 'warning',
                    allowOutsideClick: false
                });
            });
        });
    </script>
</body>
</html>
