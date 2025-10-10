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
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #141e30, #243b55);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            margin: 0;
            padding: 30px;
            min-height: 100vh;
            color: #e0e0e0;
            overflow-x: hidden;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(45deg, rgba(255,255,255,0.05) 0, rgba(255,255,255,0.05) 1px, transparent 1px, transparent 20px);
            pointer-events: none;
            z-index: -2;
            animation: textureFlow 10s linear infinite;
        }
        @keyframes textureFlow {
            0% { transform: translateY(0); }
            100% { transform: translateY(-20px); }
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        /* Rain Effect */
        .rain {
            position: fixed;
            left: 0;
            width: 100%;
            height: 100%;
            top: 0;
            z-index: -1;
            pointer-events: none;
        }
        .drop {
            position: absolute;
            bottom: 100%;
            width: 2px;
            height: 100px;
            background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.3));
            animation: fall linear infinite;
        }
        @keyframes fall {
            0% { transform: translateY(0); opacity: 1; }
            100% { transform: translateY(100vh); opacity: 0; }
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative;
            z-index: 1;
        }
        .card {
            background: rgba(20, 20, 30, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3), 0 0 40px rgba(118, 75, 162, 0.2);
            padding: 35px;
            margin-bottom: 35px;
            border: 1px solid rgba(102, 126, 234, 0.4);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5), 0 0 60px rgba(118, 75, 162, 0.4);
        }
        .header {
            text-align: center;
            color: #ffffff;
            margin-bottom: 45px;
            text-shadow: 0 0 15px rgba(102, 126, 234, 0.7);
        }
        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin: 0;
            animation: neonGlow 1.5s ease-in-out infinite alternate;
        }
        @keyframes neonGlow {
            from { text-shadow: 0 0 10px #fff, 0 0 20px #667eea, 0 0 30px #764ba2; }
            to { text-shadow: 0 0 20px #fff, 0 0 30px #667eea, 0 0 40px #764ba2; }
        }
        .header p {
            font-size: 1.3rem;
            opacity: 0.85;
        }
        .form-group {
            margin-bottom: 30px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: #cccccc;
            text-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.4s ease;
            background: rgba(30, 30, 40, 0.7);
            color: #e0e0e0;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.25);
        }
        .form-control::placeholder {
            color: #888888;
        }
        select.form-control {
            cursor: pointer;
        }
        .btn {
            padding: 16px 35px;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            font-size: 17px;
            cursor: pointer;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: #ffffff;
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.5);
        }
        .btn-danger {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: #ffffff;
        }
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(245, 87, 108, 0.5);
        }
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 35px;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        .stat-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            color: #ffffff;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
            border: 1px solid rgba(102, 126, 234, 0.4);
        }
        .stat-item:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(102, 126, 234, 0.5);
        }
        .stat-item .label {
            font-size: 15px;
            opacity: 0.9;
            margin-bottom: 10px;
            text-shadow: 0 0 5px rgba(255,255,255,0.5);
        }
        .stat-item .value {
            font-size: 32px;
            font-weight: 800;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #ffffff;
            text-shadow: 0 0 15px #667eea, 0 0 25px #764ba2;
            animation: neonGlow 1.5s ease-in-out infinite alternate;
        }
        .result-card {
            background: rgba(20, 20, 30, 0.85);
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
            transition: transform 0.4s ease;
            border: 1px solid rgba(102, 126, 234, 0.4);
        }
        .result-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-bottom: 1px solid rgba(102, 126, 234, 0.3);
        }
        .result-title {
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
        .result-content {
            max-height: 400px;
            overflow-y: auto;
            padding: 30px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            line-height: 1.8;
            color: #e0e0e0;
        }
        .result-content::-webkit-scrollbar {
            width: 10px;
        }
        .result-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        .action-btn {
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            color: #ffffff;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }
        .eye-btn { background: linear-gradient(45deg, #6c757d, #495057); }
        .copy-btn { background: linear-gradient(45deg, #28a745, #218838); }
        .trash-btn { background: linear-gradient(45deg, #dc3545, #c82333); }
        .loader {
            border: 5px solid rgba(102, 126, 234, 0.3);
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1.2s linear infinite;
            margin: 35px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .card-count {
            font-size: 15px;
            color: #bbbbbb;
            margin-top: 12px;
        }
        footer {
            text-align: center;
            padding: 35px;
            color: #cccccc;
            font-size: 16px;
            margin-top: 60px;
            border-top: 1px solid rgba(102, 126, 234, 0.3);
        }
        .sidebar {
            height: 100%;
            width: 0;
            position: fixed;
            z-index: 10;
            top: 0;
            right: 0;
            background: rgba(20, 20, 30, 0.95);
            backdrop-filter: blur(10px);
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 70px;
            box-shadow: -15px 0 30px rgba(0, 0, 0, 0.5);
            border-left: 1px solid rgba(102, 126, 234, 0.4);
        }
        .sidebar a {
            padding: 15px 10px 15px 40px;
            text-decoration: none;
            font-size: 22px;
            color: #dddddd;
            display: block;
            transition: 0.4s;
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
        }
        .sidebar a:hover {
            color: #ffffff;
            background: rgba(102, 126, 234, 0.2);
            text-shadow: 0 0 10px #667eea;
        }
        .sidebar .closebtn {
            position: absolute;
            top: 0;
            right: 30px;
            font-size: 40px;
            margin-left: 60px;
            color: #bbbbbb;
        }
        .openbtn {
            font-size: 28px;
            cursor: pointer;
            color: #ffffff;
            position: absolute;
            top: 25px;
            right: 25px;
            z-index: 2;
            transition: color 0.4s ease, transform 0.4s ease;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
        .openbtn:hover {
            color: #667eea;
            transform: rotate(90deg);
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header h1 { font-size: 2.5rem; }
            .header p { font-size: 1.1rem; }
            .btn-group { flex-direction: column; gap: 15px; }
            .stats-container { grid-template-columns: 1fr; }
            .result-header { flex-direction: column; gap: 20px; align-items: flex-start; }
            .card { padding: 25px; }
            .sidebar a { font-size: 20px; padding-left: 30px; }
        }
    </style>
</head>
<body>
    <!-- Rain Effect -->
    <div class="rain"></div>

    <!-- Hamburger Menu -->
    <div class="openbtn" onclick="openNav()"><i class="fas fa-ellipsis-v"></i></div>
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <a href="#" onclick="showSection('checker')">Checker</a>
        <a href="#" onclick="showSection('charged')">Charged Cards (<span class="charged">0</span>)</a>
        <a href="#" onclick="showSection('approved')">Approved Cards (<span class="approved">0</span>)</a>
        <a href="#" onclick="showSection('declined')">Declined Cards (<span class="reprovadas">0</span>)</a>
    </div>

    <!-- Main Checker UI -->
    <div class="container section" id="checkerContainer">
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
        <div class="stats-container">
            <div class="stat-item">
                <div class="label">Total</div>
                <div class="value carregadas">0</div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.3), rgba(32, 201, 151, 0.3));">
                <div class="label">Charged</div>
                <div class="value charged">0</div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.3), rgba(32, 201, 151, 0.3));">
                <div class="label">Approved</div>
                <div class="value approved">0</div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.3), rgba(200, 35, 51, 0.3));">
                <div class="label">Declined</div>
                <div class="value reprovadas">0</div>
            </div>
            <div class="stat-item" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.3), rgba(253, 126, 20, 0.3));">
                <div class="label">Checked</div>
                <div class="value checked">0 / 0</div>
            </div>
        </div>
    </div>

    <!-- Charged Logs -->
    <div class="container section hidden" id="chargedContainer">
        <h2 class="section-title">CHARGED CARDS LOGS</h2>
        <div class="result-card">
            <div class="result-header">
                <h3 class="result-title"><i class="fas fa-check-circle" style="color: #17a2b8;"></i> Charged Cards</h3>
                <div class="action-buttons">
                    <button class="action-btn eye-btn show-charged" type="show"><i class="fas fa-eye-slash"></i></button>
                    <button class="action-btn copy-btn btn-copy-charged"><i class="fas fa-copy"></i></button>
                </div>
            </div>
            <div id="lista_charged" class="result-content" style="display: none;"></div>
        </div>
    </div>

    <!-- Approved Logs -->
    <div class="container section hidden" id="approvedContainer">
        <h2 class="section-title">APPROVED CARDS LOGS</h2>
        <div class="result-card">
            <div class="result-header">
                <h3 class="result-title"><i class="fas fa-check-circle" style="color: #28a745;"></i> Approved Cards</h3>
                <div class="action-buttons">
                    <button class="action-btn eye-btn show-approved" type="show"><i class="fas fa-eye-slash"></i></button>
                    <button class="action-btn copy-btn btn-copy-approved"><i class="fas fa-copy"></i></button>
                </div>
            </div>
            <div id="lista_approved" class="result-content" style="display: none;"></div>
        </div>
    </div>

    <!-- Declined Logs -->
    <div class="container section hidden" id="declinedContainer">
        <h2 class="section-title">DECLINED CARDS LOGS</h2>
        <div class="result-card">
            <div class="result-header">
                <h3 class="result-title"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Declined Cards</h3>
                <div class="action-buttons">
                    <button class="action-btn eye-btn show-declined" type="show"><i class="fas fa-eye-slash"></i></button>
                    <button class="action-btn copy-btn btn-copy-declined"><i class="fas fa-copy"></i></button>
                    <button class="action-btn trash-btn btn-trash-declined"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div id="lista_declined" class="result-content" style="display: none;"></div>
        </div>
    </div>

    <footer id="footer">
        <p><strong>© 2025 Card X Check - Multi-Gateway CHECKER</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            // Create rain drops
            const rain = $('.rain');
            for (let i = 0; i < 100; i++) {
                const drop = $('<div class="drop"></div>');
                drop.css({
                    left: `${Math.random() * 100}%`,
                    animationDuration: `${Math.random() * 1 + 0.5}s`,
                    animationDelay: `${Math.random() * 2}s`,
                    height: `${Math.random() * 50 + 50}px`
                });
                rain.append(drop);
            }

            let isProcessing = false;
            let isStopping = false;
            let activeRequests = 0;
            let cardQueue = [];
            const MAX_CONCURRENT = 3;
            const MAX_RETRIES = 1;
            let abortControllers = [];
            let totalCards = 0;

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

            // Event Listeners for Logs
            $('.show-charged').click(() => toggleVisibility($('.show-charged'), '#lista_charged'));
            $('.btn-copy-charged').click(() => copyToClipboard('#lista_charged', 'Charged cards'));

            $('.show-approved').click(() => toggleVisibility($('.show-approved'), '#lista_approved'));
            $('.btn-copy-approved').click(() => copyToClipboard('#lista_approved', 'Approved cards'));
            
            $('.show-declined').click(() => toggleVisibility($('.show-declined'), '#lista_declined'));
            $('.btn-copy-declined').click(() => copyToClipboard('#lista_declined', 'Declined cards'));
            
            $('.btn-trash-declined').click(() => {
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
                            resolve({
                                success: response.includes('APPROVED'),
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
                    Swal.fire('No valid cards!', 'Please check your card format', 'error');
                    console.error('No valid cards provided');
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire('Limit exceeded!', 'Maximum 1000 cards allowed', 'error');
                    console.error('Card limit exceeded');
                    return;
                }

                isProcessing = true;
                isStopping = false;
                activeRequests = 0;
                abortControllers = [];
                cardQueue = [...validCards];
                totalCards = validCards.length;
                $('.carregadas').text(totalCards);
                $('.charged').text('0');
                $('.approved').text('0');
                $('.reprovadas').text('0');
                $('.checked').text(`0 / ${totalCards}`);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                console.log(`Starting processing for ${totalCards} cards`);

                const results = [];
                let completed = 0;
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

                            results.push(result);
                            completed++;
                            activeRequests--;

                            if (result.success) {
                                const gate = $('#gate').val();
                                if (gate.includes('paypal1$')) {
                                    $('#lista_charged').append(`<span style="color: #17a2b8; font-family: 'Inter', sans-serif;">${result.response}</span><br>`);
                                    $('.charged').text(parseInt($('.charged').text()) + 1);
                                } else {
                                    $('#lista_approved').append(`<span style="color: #28a745; font-family: 'Inter', sans-serif;">${result.response}</span><br>`);
                                    $('.approved').text(parseInt($('.approved').text()) + 1);
                                }
                            } else {
                                $('#lista_declined').append(`<span style="color: #dc3545; font-family: 'Inter', sans-serif;">${result.response}</span><br>`);
                                $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                            }

                            $('.checked').text(`${completed} / ${totalCards}`);
                            console.log(`Completed ${completed}/${totalCards}: ${result.response}`);

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
                
                Swal.fire({
                    title: 'Processing complete!',
                    text: 'All cards have been checked. View logs by clicking the 3 dots menu.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
                console.log('Processing completed');
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
                $('.checked').text(`${parseInt($('.charged').text()) + parseInt($('.approved').text()) + parseInt($('.reprovadas').text())} / ${totalCards}`);
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire({
                    title: 'Stopped!',
                    text: 'Processing has been stopped. View logs by clicking the 3 dots menu.',
                    icon: 'warning',
                    allowOutsideClick: false
                });
                console.log('Processing stopped');
            });

            // Gateway change handler
            $('#gate').change(function() {
                const selected = $(this).val();
                console.log(`Gateway changed to: ${selected}`);
                if (!selected.includes('stripeauth.php') && !selected.includes('paypal1$.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth and PayPal 1$ are currently available',
                        icon: 'info'
                    });
                    $(this).val('gate/stripeauth.php');
                    console.log('Reverted to stripeauth.php');
                }
            });
        });

        function openNav() {
            document.getElementById("mySidebar").style.width = "280px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
        }

        function showSection(section) {
            $('.section').addClass('hidden');
            $('#' + section + 'Container').removeClass('hidden');
            closeNav();
        }
    </script>
</body>
</html>
