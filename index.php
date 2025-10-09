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
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
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
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
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
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-danger {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: white;
        }
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 87, 108, 0.3);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-group {
            display: flex;
            gap: 15px;
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
        }
        .stat-item .label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .stat-item .value {
            font-size: 24px;
            font-weight: 700;
        }
        .results-grid {
            display: grid;
            gap: 20px;
        }
        .result-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
        }
        .result-content {
            max-height: 300px;
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
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }
        .eye-btn { background: #6c757d; }
        .copy-btn { background: #28a745; }
        .trash-btn { background: #dc3545; }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .card-count {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: white;
            font-size: 14px;
            margin-top: 40px;
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header h1 { font-size: 2rem; }
            .btn-group { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .result-header { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> CardX Check</h1>
            <p>Multi-Gateway Card Validator</p>
        </div>

        <!-- Input Section -->
        <div class="card">
            <div class="form-group">
                <label for="cards">Card List (Format: card|MM|YYYY|CVV)</label>
                <textarea id="cards" class="form-control" rows="6" placeholder="4147768578745265|04|2026|168&#10;4242424242424242|12|2025|123"></textarea>
                <div class="card-count" id="card-count">0 valid cards detected</div>
            </div>
            <div class="form-group">
                <label for="gate">Select Gateway</label>
                <select id="gate" class="form-control">
                    <option value="gate/stripeauth.php">Stripe Auth</option>
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
                    <div class="label">Approved</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <div class="label">Declined</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="label">Processing</div>
                    <div class="value processing">0</div>
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

    <footer>
        <p><strong>© 2025 CardX Check - Multi-Gateway Validator</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            let isProcessing = false;
            let activeRequests = 0;
            const MAX_CONCURRENT = 4;
            let abortController = null;

            // Card validation and counter
            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} valid cards detected (max 1000)`);
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
                if (!text) return;
                
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

            // Process single card
            async function processCard(cardData, index) {
                return new Promise((resolve) => {
                    const formData = new FormData();
                    formData.append('card[number]', cardData.number);
                    formData.append('card[exp_month]', cardData.exp_month);
                    formData.append('card[exp_year]', cardData.exp_year);
                    formData.append('card[cvc]', cardData.cvc);

                    $.ajax({
                        url: $('#gate').val(),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 30000,
                        success: function(response) {
                            resolve({ success: true, response, card: cardData });
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseText || 'Request failed';
                            resolve({ 
                                success: false, 
                                response: `DECLINED [${errorMsg}] ${cardData.number}|${cardData.exp_month}|${cardData.exp_year}|${cardData.cvc}`,
                                card: cardData 
                            });
                        }
                    });
                });
            }

            // Main processing function with concurrency control
            async function processCards() {
                const cardText = $('#cards').val().trim();
                const lines = cardText.split('\n').filter(line => line.trim());
                const validCards = lines
                    .map(line => line.trim())
                    .filter(line => /^\d{13,19}\|\d{1,2}\|\d{4}\|\d{3,4}$/.test(line))
                    .map(line => {
                        const [number, exp_month, exp_year, cvc] = line.split('|');
                        return { number, exp_month, exp_year, cvc };
                    });

                if (validCards.length === 0) {
                    Swal.fire('No valid cards!', 'Please check your card format', 'error');
                    return;
                }

                if (validCards.length > 1000) {
                    Swal.fire('Limit exceeded!', 'Maximum 1000 cards allowed', 'error');
                    return;
                }

                isProcessing = true;
                activeRequests = 0;
                $('.carregadas').text(validCards.length);
                $('.processing').text(0);
                $('#startBtn').prop('disabled', true);
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();

                const results = [];
                let completed = 0;

                for (let i = 0; i < validCards.length; i++) {
                    while (activeRequests >= MAX_CONCURRENT) {
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }

                    activeRequests++;
                    $('.processing').text(activeRequests);

                    processCard(validCards[i], i).then(result => {
                        results.push(result);
                        completed++;
                        activeRequests--;

                        // Update UI
                        if (result.response.includes('APPROVED')) {
                            $('#lista_approved').append(`${result.response}<br>`);
                            $('.approved').text(parseInt($('.approved').text()) + 1);
                        } else {
                            $('#lista_declined').append(`${result.response}<br>`);
                            $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                        }

                        $('.processing').text(activeRequests);
                        
                        // Progress update
                        Swal.fire({
                            title: `Processing: ${completed}/${validCards.length}`,
                            toast: true,
                            position: 'top-end',
                            timer: 1000,
                            showConfirmButton: false
                        });

                        if (completed === validCards.length) {
                            finishProcessing();
                        }
                    });
                }
            }

            function finishProcessing() {
                isProcessing = false;
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                $('#cards').val('');
                $('#card-count').text('0 valid cards detected');
                
                Swal.fire('Processing complete!', 'All cards have been checked', 'success');
            }

            // Event handlers
            $('#startBtn').click(processCards);

            $('#stopBtn').click(() => {
                isProcessing = false;
                $('#startBtn').prop('disabled', false);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                Swal.fire('Stopped!', 'Processing has been stopped', 'warning');
            });

            // Gateway change handler
            $('#gate').change(function() {
                const selected = $(this).val();
                if (!selected.includes('stripeauth.php')) {
                    Swal.fire({
                        title: 'Gateway not implemented',
                        text: 'Only Stripe Auth is currently available',
                        icon: 'info'
                    });
                    $(this).val('gate/stripeauth.php');
                }
            });
        });
    </script>
</body>
</html>
