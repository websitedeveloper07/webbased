<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CardX Check</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* --- Color Variables (Refined) --- */
        :root {
            --color-primary: #5c62ec; /* Adjusted slightly */
            --color-secondary: #8c56e3; /* Adjusted slightly */
            --color-success: #1cdb7f; /* Brighter Green */
            --color-danger: #ff5e69; /* Punchier Red */
            --color-warning: #ffb740; /* Golden Yellow */
            --color-text-dark: #333;
            --color-text-label: #555;
            --color-card-bg: rgba(255, 255, 255, 0.95);
            --color-card-shadow: rgba(0, 0, 0, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            /* Original Live Animating Background */
            background: linear-gradient(-45deg, var(--color-primary) 0%, var(--color-secondary) 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            padding: 20px;
            min-height: 100vh;
            color: var(--color-text-dark);
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
            background: var(--color-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 30px var(--color-card-shadow);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3); /* Enhanced glass border */
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 20px 40px var(--color-card-shadow);
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 800; /* Made header bolder */
            margin: 0;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.4);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--color-text-label);
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
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(92, 98, 236, 0.15); /* More prominent focus shadow */
        }
        .form-control::placeholder {
            color: #999;
        }
        .btn {
            padding: 14px 28px; /* Slightly larger buttons */
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--color-primary), var(--color-secondary));
            color: white;
            box-shadow: 0 5px 15px rgba(92, 98, 236, 0.4);
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(92, 98, 236, 0.5);
        }
        .btn-danger {
            background: linear-gradient(45deg, var(--color-danger), #ff8289);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 94, 105, 0.4);
        }
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 94, 105, 0.5);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-group {
            display: flex;
            gap: 15px;
        }
        
        /* --- Stats Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-item {
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s;
        }
        .stat-item:hover {
            transform: translateY(-3px);
        }
        /* Adjusted Status Item Colors */
        .stat-total { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-approved { background: linear-gradient(135deg, var(--color-success), #17bf6b); }
        .stat-declined { background: linear-gradient(135deg, var(--color-danger), #dc3545); }
        .stat-processing { background: linear-gradient(135deg, var(--color-warning), #ffd700); }
        
        .stat-item .label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .stat-item .value {
            font-size: 30px;
            font-weight: 800;
        }
        
        /* --- Results Section --- */
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }
        .result-card {
            background: var(--color-card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px var(--color-card-shadow);
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa; /* Lighter header background */
            border-bottom: 1px solid #dee2e6;
        }
        .result-title {
            font-weight: 700;
            color: var(--color-text-dark);
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* --- Log Content FIX: Using PRE for proper log format --- */
        .result-content {
            max-height: 350px;
            overflow-y: auto;
            padding: 0; 
            background: #ffffff;
            font-size: 14px;
            line-height: 1.6;
        }
        .result-content pre {
            margin: 0;
            padding: 20px;
            white-space: pre-wrap; /* Preserve formatting but wrap lines */
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
        }
        #lista_approved pre { color: var(--color-success); }
        #lista_declined pre { color: var(--color-danger); }

        .result-content::-webkit-scrollbar {
            width: 8px;
        }
        .result-content::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 4px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .action-btn {
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            color: white; /* Ensure text/icon color is white for visibility */
        }
        .eye-btn { background: #6c757d; }
        .eye-btn:hover { background: #5a6268; }
        .copy-btn { background: var(--color-success); }
        .copy-btn:hover { background: #17bf6b; }
        .trash-btn { background: var(--color-danger); }
        .trash-btn:hover { background: #c82333; }
        
        /* Utility Colors */
        .text-success { color: var(--color-success); }
        .text-danger { color: var(--color-danger); }
        
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--color-primary);
            border-radius: 50%;
            width: 35px;
            height: 35px;
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
            color: #6c757d;
            margin-top: 5px;
            font-weight: 500;
        }
        footer {
            text-align: center;
            padding: 20px;
            color: white;
            font-size: 14px;
            margin-top: 40px;
            opacity: 0.8;
        }
        @media (max-width: 768px) {
            .btn-group { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .result-header { flex-direction: column; gap: 10px; }
            .result-header .action-buttons { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> CARD X CHK</h1>
            <p>Multi-Gateway Card CHECKER</p>
        </div>

        <div class="card">
            <div class="form-group">
                <label for="cards">CARD LIST (FORMAT: CARD|MM|YY/YYYY|CVV)</label>
                <textarea id="cards" class="form-control" rows="6" placeholder="4147768578745265|04|2026|168&#10;4242424242424242|12|25|123"></textarea>
                <div class="card-count" id="card-count">0 valid cards detected</div>
            </div>
            <div class="form-group">
                <label for="gate">SELECT GATEWAY PROTOCOL</label>
                <select id="gate" class="form-control">
                    <option value="/gatestripeauth.php">STRIPE AUTH ✅</option>
                    <option value="gate/paypal.php" disabled>PAYPAL (Coming Soon)</option>
                    <option value="gate/razorpay.php" disabled>RAZORPAY (Coming Soon)</option>
                    <option value="gate/shopify.php" disabled>SHOPIFY (Coming Soon)</option>
                </select>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary btn-play" id="startBtn">
                    <i class="fas fa-play"></i> START CHECK
                </button>
                <button class="btn btn-danger btn-stop" id="stopBtn" disabled>
                    <i class="fas fa-stop"></i> STOP
                </button>
            </div>
            <div class="loader" id="loader"></div>
        </div>

        <div class="card">
            <div class="stats-grid">
                <div class="stat-item stat-total">
                    <div class="label">TOTAL LOADED</div>
                    <div class="value carregadas">0</div>
                </div>
                <div class="stat-item stat-approved">
                    <div class="label">HIT | APPROVED</div>
                    <div class="value approved">0</div>
                </div>
                <div class="stat-item stat-declined">
                    <div class="label">DEAD | DECLINED</div>
                    <div class="value reprovadas">0</div>
                </div>
                <div class="stat-item stat-processing">
                    <div class="label">ACTIVE THREADS</div>
                    <div class="value processing">0</div>
                </div>
            </div>
        </div>

        <div class="results-grid">
            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-check-circle text-success"></i> APPROVED LOG</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-approved" type="hidden" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                        <button class="action-btn copy-btn btn-copy-approved" title="Copy Log"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div id="lista_approved" class="result-content" style="display: none;"><pre></pre></div>
            </div>

            <div class="result-card">
                <div class="result-header">
                    <h3 class="result-title"><i class="fas fa-times-circle text-danger"></i> DECLINED LOG</h3>
                    <div class="action-buttons">
                        <button class="action-btn eye-btn show-declined" type="hidden" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                        <button class="action-btn copy-btn btn-copy-declined" title="Copy Log"><i class="fas fa-copy"></i></button>
                        <button class="action-btn trash-btn btn-trash" title="Clear Log"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div id="lista_declined" class="result-content" style="display: none;"><pre></pre></div>
            </div>
        </div>
    </div>

    <footer>
        <p><strong>© 2025 CARD X CHK | STATUS: ONLINE</strong></p>
    </footer>

    <script>
        $(document).ready(function() {
            let isProcessing = false;
            let activeRequests = 0;
            const MAX_CONCURRENT = 4;
            const BATCH_DELAY = 500; // 500ms delay between batches

            // Card validation and counter
            $('#cards').on('input', function() {
                const lines = $(this).val().trim().split('\n').filter(line => line.trim());
                const validCards = lines.filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line.trim()));
                $('#card-count').text(`${validCards.length} VALID CARDS DETECTED (max 1000)`);
            });

            // UI Functions
            function toggleVisibility(btn, sectionId) {
                const section = $(sectionId);
                const isHidden = section.is(':hidden');
                section.slideToggle(300);
                btn.html(`<i class="fas fa-${isHidden ? 'eye-slash' : 'eye'}"></i>`);
                btn.attr('type', isHidden ? 'show' : 'hidden');
            }

            function copyToClipboard(selector, title) {
                const text = $(selector).find('pre').text();
                if (!text.trim()) {
                    Swal.fire({title: `NO ${title.toUpperCase()} DATA!`, icon: 'info', toast: true, position: 'top-end', timer: 2000});
                    return;
                }
                
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                Swal.fire({title: `COPIED ${title.toUpperCase()}!`, icon: 'success', toast: true, position: 'top-end', timer: 2000});
            }

            // Event Listeners
            $('.show-approved').click(function() { toggleVisibility($(this), '#lista_approved'); });
            $('.show-declined').click(function() { toggleVisibility($(this), '#lista_declined'); });
            
            $('.btn-copy-approved').click(() => copyToClipboard('#lista_approved', 'APPROVED LOG'));
            $('.btn-copy-declined').click(() => copyToClipboard('#lista_declined', 'DECLINED LOG'));
            
            $('.btn-trash').click(() => {
                Swal.fire({
                    title: 'CLEAR DECLINED LOG',
                    text: 'Erase all entries in Declined Log?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'YES, CLEAR',
                    confirmButtonColor: 'var(--color-danger)',
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#lista_declined pre').empty();
                        $('.reprovadas').text('0');
                        Swal.fire({title: 'LOG CLEARED!', icon: 'success', toast: true, position: 'top-end', timer: 2000});
                    }
                });
            });

            // Process single card
            async function processCard(cardData) {
                return new Promise((resolve) => {
                    const fullCard = `${cardData.number}|${cardData.exp_month}|${cardData.exp_year}|${cardData.cvc}`;
                    const gateway = $('#gate').val();

                    $.ajax({
                        url: gateway,
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            card: {
                                number: cardData.number,
                                exp_month: cardData.exp_month,
                                exp_year: cardData.exp_year,
                                cvc: cardData.cvc
                            }
                        }),
                        success: function(response) {
                            const isApproved = response.toUpperCase().startsWith('APPROVED');
                            resolve({
                                response: response,
                                success: isApproved,
                                card: cardData
                            });
                        },
                        error: function(xhr, status, error) {
                            resolve({
                                response: `DECLINED [API Error: ${status} - ${error}] ${fullCard}`,
                                success: false,
                                card: cardData
                            });
                        }
                    });
                });
            }

            // Main processing function
            async function processCards() {
                if (isProcessing) return;

                const cardText = $('#cards').val().trim();
                const lines = cardText.split('\n').filter(line => line.trim());
                
                const validCards = lines
                    .map(line => line.trim())
                    .filter(line => /^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/.test(line))
                    .map(line => {
                        const [number, exp_month, exp_year, cvc] = line.split('|');
                        let year = exp_year.length === 2 ? `20${exp_year}` : exp_year;
                        return { number, exp_month, exp_year: year, cvc };
                    });

                if (validCards.length === 0) {
                    Swal.fire({title: 'INPUT REQUIRED', text: 'Please enter valid card data into the stream.', icon: 'error'});
                    return;
                }

                // Reset stats and UI
                isProcessing = true;
                activeRequests = 0;
                let completed = 0;
                $('.carregadas').text(validCards.length);
                $('.approved').text(0);
                $('.reprovadas').text(0);
                $('.processing').text(0);
                $('#lista_approved pre').empty();
                $('#lista_declined pre').empty();
                $('#startBtn').prop('disabled', true).html('<i class="fas fa-sync-alt fa-spin"></i> RUNNING...');
                $('#stopBtn').prop('disabled', false);
                $('#loader').show();
                $('#cards').val('');

                // Processing loop with batch delay
                const promises = [];
                for (let i = 0; i < validCards.length && isProcessing; i += MAX_CONCURRENT) {
                    const batch = validCards.slice(i, i + MAX_CONCURRENT);
                    const batchPromises = batch.map(cardData => {
                        activeRequests++;
                        $('.processing').text(activeRequests);
                        
                        return processCard(cardData).then(result => {
                            activeRequests--;
                            completed++;
                            
                            const logTarget = result.success ? $('#lista_approved pre') : $('#lista_declined pre');
                            logTarget.append(result.response + '\n');
                            
                            if (result.success) {
                                $('.approved').text(parseInt($('.approved').text()) + 1);
                            } else {
                                $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                            }

                            $('.processing').text(activeRequests);
                            $('#startBtn').html(`<i class="fas fa-sync-alt fa-spin"></i> RUNNING (${completed}/${validCards.length})`);
                            
                            return result;
                        });
                    });
                    promises.push(...batchPromises);
                    
                    // Wait for batch to complete and add delay
                    await Promise.all(batchPromises);
                    if (isProcessing && i + MAX_CONCURRENT < validCards.length) {
                        await new Promise(resolve => setTimeout(resolve, BATCH_DELAY));
                    }
                }

                await Promise.allSettled(promises);
                
                finishProcessing();
            }

            function finishProcessing() {
                if (!isProcessing) return;
                
                isProcessing = false;
                const totalCards = $('.carregadas').text();
                const approvedCount = $('.approved').text();
                
                $('#startBtn').prop('disabled', false).html(`<i class="fas fa-play"></i> START CHECK`);
                $('#stopBtn').prop('disabled', true);
                $('#loader').hide();
                
                if (parseInt(totalCards) > 0) {
                    Swal.fire({
                        title: 'FLOW COMPLETE',
                        text: `Processing finished. ${approvedCount} Hits detected out of ${totalCards}.`,
                        icon: 'success'
                    });
                }
                
                $('#card-count').text('0 VALID CARDS DETECTED');
            }

            // Event handlers
            $('#startBtn').click(processCards);

            $('#stopBtn').click(() => {
                isProcessing = false;
                Swal.fire({title: 'FLOW HALTED!', text: 'Processing suspended by operator command.', icon: 'warning'});
                if (activeRequests === 0) finishProcessing();
            });
            
            // Initial check for card count on load
            $('#cards').trigger('input');
        });
    </script>
</body>
</html>
