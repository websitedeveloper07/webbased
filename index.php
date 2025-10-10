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
            padding: 30px;
            min-height: 100vh;
            color: #333;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 3px 12px rgba(0, 0, 0, 0.3);
        }
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        .form-control::placeholder {
            color: #999;
        }
        select.form-control {
            cursor: pointer;
        }
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.4);
        }
        .btn-danger {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: white;
        }
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(245, 87, 108, 0.4);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-item:hover {
            transform: translateY(-3px);
        }
        .stat-item .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
        }
        .results-grid {
            display: grid;
            gap: 30px;
        }
        .result-card {
            background: rgba(255, 255, 255, 0.97);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-5px);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
        }
        .result-title {
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .result-content {
            max-height: 350px;
            overflow-y: auto;
            padding: 25px;
            font-family: 'Courier New', monospace;
            font-size: 15px;
            line-height: 1.7;
        }
        .result-content::-webkit-scrollbar {
            width: 8px;
        }
        .result-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        .action-btn {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 15px;
            transition: transform 0.3s ease;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .eye-btn { background: #6c757d; }
        .copy-btn { background: #28a745; }
        .trash-btn { background: #dc3545; }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
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
        .card-count {
            font-size: 14px;
            color: #555;
            margin-top: 10px;
        }
        footer {
            text-align: center;
            padding: 30px;
            color: white;
            font-size: 15px;
            margin-top: 50px;
        }
        /* Login Panel Styles */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: inherit;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-card h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .header h1 { font-size: 2.2rem; }
            .header p { font-size: 1rem; }
            .btn-group { 
                flex-direction: column;
                align-items: center;
            }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
            .result-header { 
                flex-direction: column; 
                gap: 15px; 
                align-items: flex-start;
            }
            .card { padding: 20px; }
            .login-card { padding: 20px; }
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
            <h1><i class="fas fa-credit-card"></i> Card X CHK</h1>
            <p>Multi-Gateway Card Checker</p>
        </div>

        <!-- Input Section -->
        <div class="card">
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
                    <i class="fas fa-stop"></i> Stop
                </button>
            </div>
            <div class="loader" id="loader"></div>
        </div>

        <!-- Stats -->
        <div class="card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">Total</div>
                    <div class="value carregadas">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="label">HIT|Approved</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <div class="label">DEAD|Declined</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="label">Checked</div>
                    <div class="value checked">0 / 0</div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="results-grid">
            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-check-circle text-success"></i> Approved Cards</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-approved" type="show"><i class="fas fa-eye-slash"></i></button>
                        <button class="action-btn copy-btn btn-copy-approved"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div id="lista_approved" class="result-content" style="display: none;"></div>
            </div>

            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-times-circle text-danger"></i> Declined Cards</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-declined" type="show"><i class="fas fa-eye-slash"></i></button>
                        <button class="action-btn copy-btn btn-copy-declined"><i class="fas fa-copy"></i></button>
                        <button class="action-btn trash-btn btn-trash"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div id="lista_declined" class="result-content" style="display: none;"></div>
            </div>
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
            const MAX_CONCURRENT = 3; // 3 concurrent POST requests
            const MAX_RETRIES = 1; // Retry once on failure
            let abortControllers = [];
            let totalCards = 0;

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
                        confirmButtonText: 'Try Again'
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
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
                
                if ($(this).val().trim()) {
                    $('.carregadas').text('0');
                    $('.approved').text('0');
                    $('.reprovadas').text('0');
                    $('.checked').text('0 / 0');
                }
            });

            // UI Functions
            function toggleVisibility(btn, sectionId) {
                const section = $(sectionId);
                const isHidden = section.is(':hidden');
                section.toggle();
                btn.html(`<i class="fas fa-${isHidden ? 'eye-slash' : 'eye'}"></i>`);
                btn.attr('type', isHidden ? 'hidden' : 'show');
            }

            function copyToClipboard(selector, title) {
                const text = $(selector).text();
                if (!text) {
                    Swal.fire('Nothing to copy!', `${title} list is empty`, 'info');
                    return;
                }
                
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                Swal.fire({
                    title: `Copied ${title}!`,
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    timer: 2000
                });
            }

            // Event Listeners
            $('.show-approved').click(() => toggleVisibility($('.show-approved'), '#lista_approved'));
            $('.show-declined').click(() => toggleVisibility($('.show-declined'), '#lista_declined'));
            
            $('.btn-copy-approved').click(() => copyToClipboard('#lista_approved', 'Approved cards'));
            $('.btn-copy-declined').click(() => copyToClipboard('#lista_declined', 'Declined cards'));
            
            $('.btn-trash').click(() => {
                Swal.fire({
                    title: 'Clear declined?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, clear!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#lista_declined').empty();
                        $('.reprovadas').text('0');
                        Swal.fire('Cleared!', '', 'success');
                    }
                });
            });

            // Process a single card with retry
            async function processCard(card, controller, retryCount = 0) {
                if (!isProcessing) {
                    console.log(`Skipping card ${card.displayCard} due to stop`); // Debug
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

                    console.log(`Sending card ${card.displayCard} to ${$('#gate').val()}`); // Debug

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 55000, // 55s to accommodate 50s backend timeout
                        signal: controller.signal,
                        success: function(response) {
                            console.log(`Success for ${card.displayCard}: ${response}`); // Debug
                            resolve({
                                success: response.includes('APPROVED'),
                                response: response.trim(),
                                card: card,
                                displayCard: card.displayCard
                            });
                        },
                        error: function(xhr) {
                            if (xhr.statusText === 'abort') {
                                console.log(`Aborted card ${card.displayCard}`); // Debug
                                resolve(null);
                            } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                                console.warn(`Retrying card ${card.displayCard} (Attempt ${retryCount + 2}) due to error: ${xhr.statusText} (${xhr.status})`); // Debug
                                setTimeout(() => {
                                    processCard(card, controller, retryCount + 1).then(resolve);
                                }, 1000); // 1s delay for retry
                            } else {
                                const errorMsg = xhr.responseText ? xhr.responseText.substring(0, 100) : 'Request failed';
                                console.error(`Failed card ${card.displayCard}: ${xhr.statusText} (HTTP ${xhr.status}) - ${errorMsg}`); // Debug
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

            // Main processing function with 3 concurrent requests and staggered delays
            async function processCards() {
                if (isProcessing) {
                    console.warn('Processing already in progress'); // Debug
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
                    Swal.fire('No valid cards!', 'Please check your card format', 'error');
                    console.error('No valid cards provided'); // Debug
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire('Limit exceeded!', 'Maximum 1000 cards allowed', 'error');
                    console.error('Card limit exceeded'); // Debug
                    return;
                }

                isProcessing = true;
                isStopping = false;
                activeRequests = 0;
                abortControllers = [];
                cardQueue = [...validCards];
                totalCards = validCards.length;
                $('.carregadas').text(totalCards);
                $('.approved').text('0');
                $('.reprovadas').text('0');
                $('.checked').text(`0 / ${totalCards}`);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                console.log(`Starting processing for ${totalCards} cards`); // Debug

                const results = [];
                let completed = 0;
                let requestIndex = 0;

                while (cardQueue.length > 0 && isProcessing) {
                    while (activeRequests < MAX_CONCURRENT && cardQueue.length > 0 && isProcessing) {
                        const card = cardQueue.shift();
                        if (!card) break; // Ensure no processing after queue clear
                        activeRequests++;
                        const controller = new AbortController();
                        abortControllers.push(controller);

                        // Stagger requests to avoid overwhelming the API
                        await new Promise(resolve => setTimeout(resolve, requestIndex * 200));
                        requestIndex++;

                        processCard(card, controller).then(result => {
                            if (result === null) return;

                            results.push(result);
                            completed++;
                            activeRequests--;

                            if (result.response.includes('APPROVED')) {
                                $('#lista_approved').append(`<span style="color: green; font-family: 'Inter', sans-serif;">${result.response}</span><br>`);
                                $('.approved').text(parseInt($('.approved').text()) + 1);
                            } else {
                                $('#lista_declined').append(`<span style="color: red; font-family: 'Inter', sans-serif;">${result.response}</span><br>`);
                                $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                            }

                            $('.checked').text(`${completed} / ${totalCards}`);
                            console.log(`Completed ${completed}/${totalCards}: ${result.response}`); // Debug

                            if (completed >= totalCards || !isProcessing) {
                                finishProcessing();
                            }
                        });
                    }
                    if (isProcessing) {
                        await new Promise(resolve => setTimeout(resolve, 5)); // Minimal delay
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
                
                Swal.fire('Processing complete!', 'All cards have been checked', 'success');
                console.log('Processing completed'); // Debug
            }

            // Event handlers
            $('#startBtn').click(() => {
                console.log('Start Check clicked'); // Debug
                processCards();
            });

            $('#stopBtn').click(() => {
                if (!isProcessing || isStopping) {
                    console.warn('Stop clicked but not processing or already stopping'); // Debug
                    return;
                }

                isProcessing = false;
                isStopping = true;
                cardQueue = []; // Clear queue to prevent new requests
                abortControllers.forEach(controller => controller.abort());
                abortControllers = [];
                activeRequests = 0;
                $('.checked').text(`${parseInt($('.approved').text()) + parseInt($('.reprovadas').text())} / ${totalCards}`);
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire({
                    title: 'Stopped!',
                    text: 'Processing has been stopped',
                    icon: 'warning',
                    allowOutsideClick: false
                });
                console.log('Processing stopped'); // Debug
            });

            // Gateway change handler
            $('#gate').change(function() {
                const selected = $(this).val();
                console.log(`Gateway changed to: ${selected}`); // Debug
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth and PayPal 1$ are currently available',
                        icon: 'info'
                    });
                    $(this).val('gate/stripeauth.php');
                    console.log('Reverted to stripeauth.php'); // Debug
                }
            });
        });
    </script>
</body>
</html>
