<!DOCTYPE html>
<html lang="en" class="loading">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>Card X CHK</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Muli:400,600,700|Comfortaa:400,700" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- External Libraries -->
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        /* Global Reset and Typography */
        @import url("https://fonts.googleapis.com/css?family=Muli:400,600,700|Comfortaa:400,700");
        * { box-sizing: border-box; }
        body {
            font-family: 'Muli', sans-serif;
            font-size: 14px;
            margin: 0;
            color: #E0E0E0;
            background-color: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            line-height: 1.6;
        }
        html { height: 100%; }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #0f0c29);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2; /* Place behind all content */
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Layout Structure (Basic Grid/Flex replacement) */
        .container {
            width: 95%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 10px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .col-main {
            flex: 1 1 65%; /* 65% on desktop */
            min-width: 300px;
        }
        .col-side {
            flex: 1 1 30%; /* 30% on desktop */
            min-width: 250px;
        }
        .col-full {
            flex: 1 1 100%;
        }

        /* Card and Components */
        .card {
            background-color: rgba(0, 0, 0, 0.8);
            border: 1px solid #302b63;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Border (kept and refined) */
        .anime { border: 0 !important; }
        .anime span:nth-child(1), .anime span:nth-child(2), .anime span:nth-child(3), .anime span:nth-child(4) {
            position: absolute;
            background: linear-gradient(to right, transparent, #3bff3b);
        }
        .anime span:nth-child(1) { top: 0; left: -100%; width: 100%; height: 2px; animation: animate1 15s linear infinite; }
        .anime span:nth-child(2) { top: -100%; right: 0; width: 2px; height: 100%; animation: animate2 15s linear infinite; animation-delay: 3.75s; }
        .anime span:nth-child(3) { bottom: 0; right: -100%; width: 100%; height: 2px; animation: animate3 15s linear infinite; animation-delay: 7.5s; }
        .anime span:nth-child(4) { bottom: -100%; left: 0; width: 2px; height: 100%; animation: animate4 15s linear infinite; animation-delay: 11.25s; }

        @keyframes animate1 { 0% { left: -100%; } 100% { left: 100%; } }
        @keyframes animate2 { 0% { top: -100%; } 100% { top: 100%; } }
        @keyframes animate3 { 0% { right: -100%; } 100% { right: 100%; } }
        @keyframes animate4 { 0% { bottom: -100%; } 100% { bottom: 100%; } }

        /* Form Inputs */
        .form-control, #card-element {
            color: #fff;
            border: 1px solid #4a4a4a;
            background-color: #1a1a1a;
            border-radius: 5px;
            padding: 12px 15px;
            font-size: 15px;
            line-height: 1.2;
            width: 100%;
            transition: border-color 0.3s, box-shadow 0.3s;
            resize: none;
            overflow: hidden;
            height: auto; /* Allow textarea to size content */
        }
        .form-control:focus, #card-element:focus-within {
            border-color: #00daf7;
            background-color: #2a2a2a;
            box-shadow: 0 0 8px rgba(0, 218, 247, 0.4);
            outline: none;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #fff;
        }
        .input-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .input-group .form-control {
            flex: 1;
        }

        /* Stripe Element Styling (Fixes Space Issue) */
        #card-element {
            height: 50px; /* Ensure sufficient height for Stripe's iframe */
            display: flex; /* Helps ensure iframe is correctly sized */
            align-items: center;
        }
        /* Custom styling injected into Stripe.js call handles text color inside the iframe */
        
        #card-errors {
            color: #ff4747;
            margin-top: 10px;
            text-align: center;
            font-weight: 700;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-radius: 5px;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-play {
            background-image: linear-gradient(90deg, #382da2, #00daf7);
        }
        .btn-stop {
            background-image: linear-gradient(90deg, #cd0000, #8d003b);
        }
        .btn:hover:not(:disabled) {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* Badges and Results */
        .badge {
            font-size: 90%;
            font-weight: 700;
            padding: .3em .6em;
            border-radius: 5px;
            min-width: 40px;
            text-align: center;
        }
        .badge-dark { background-color: #333; }
        .badge-success { background-color: #00a800; }
        .badge-info { background-color: #007bff; }
        .badge-primary { background-color: #ffc107; color: #000;}
        .badge-danger { background-color: #dc3545; }

        .result-list {
            padding: 10px 0;
            max-height: 250px;
            overflow-y: auto;
            border-top: 1px solid #302b63;
            margin-top: 10px;
        }
        .result-list div {
            padding: 5px 0;
            border-bottom: 1px dotted #2a2a2a;
        }
        /* Footer */
        footer {
            text-align: center;
            padding: 20px 0;
            color: #888;
            font-size: 12px;
            width: 100%;
            margin-top: 20px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }
            .col-main, .col-side, .col-full {
                flex: 1 1 100%;
                min-width: auto;
            }
            .input-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <!-- Main Checker Panel -->
            <div class="col-main">
                <div class="card anime">
                    <span></span><span></span><span></span><span></span>
                    <h2 style="text-align: center; color: #00daf7; margin-bottom: 20px;">Stripe SK Checker</h2>
                    
                    <div class="input-group">
                        <textarea rows="1" class="form-control" id="pk" placeholder="PK TEST/PK LIVE" spellcheck="false"></textarea>
                        <textarea rows="1" class="form-control" id="sk" placeholder="SK TEST/SK LIVE" spellcheck="false"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="card-element">Card Details (Number, MM/YY, CVC)</label>
                        <div id="card-element">
                            <!-- Stripe Element will be mounted here -->
                        </div>
                        <div id="card-errors" role="alert"></div>
                    </div>
                    
                    <textarea rows="1" class="form-control text-center" id="cst" placeholder="CUSTOM AMOUNT (e.g., 1 for $1)" style="margin-bottom: 15px;"></textarea>

                    <select name="gate" id="gate" class="form-control" style="margin-bottom: 20px; height: 50px;">
                        <option value="gate/charge.php">Stripe CCN Charged: $1</option>
                    </select>

                    <div style="display: flex; gap: 20px;">
                        <button class="btn btn-play" style="flex: 1;"><i class="fa fa-play"></i> START CHECK</button>
                        <button class="btn btn-stop" style="flex: 1;" disabled><i class="fa fa-stop"></i> STOP</button>
                    </div>
                </div>
            </div>

            <!-- Stats Panel -->
            <div class="col-side">
                <div class="card">
                    <h4 style="margin-bottom: 15px; color: #00daf7;">LIVE STATS</h4>
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #2a2a2a;">
                        TOTAL TESTED: <span class="badge badge-dark float-right carregadas">0</span>
                    </div>
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #2a2a2a;">
                        <i class="fa fa-money text-success"></i> CHARGED: <span class="badge badge-success float-right charge">0</span>
                    </div>
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #2a2a2a;">
                        <i class="fa fa-check text-info"></i> CVV LIVE: <span class="badge badge-info float-right cvvs">0</span>
                    </div>
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #2a2a2a;">
                        <i class="fa fa-credit-card text-warning"></i> CCN LIVE: <span class="badge badge-primary float-right aprovadas">0</span>
                    </div>
                    <div style="margin-bottom: 5px;">
                        <i class="fa fa-times text-danger"></i> DECLINED: <span class="badge badge-danger float-right reprovadas">0</span>
                    </div>
                </div>
            </div>
            
            <!-- Result Panels -->
            <div class="col-full">
                <!-- CHARGED LIST -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 class="card-title"><i class="fa fa-check-circle" style="color: #00a800;"></i> CHARGED</h4>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm show-charge"><i class="fa fa-eye-slash"></i></button>
                            <button class="btn btn-success btn-sm btn-copy1"><i class="fa fa-copy"></i></button>
                        </div>
                    </div>
                    <div id="lista_charge" class="result-list" style="display: none; text-align: left;"></div>
                </div>
                
                <!-- CVV LIST -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 class="card-title"><i class="fa fa-check" style="color: #007bff;"></i> CVV LIVE</h4>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm show-live"><i class="fa fa-eye-slash"></i></button>
                            <button class="btn btn-success btn-sm btn-copy2"><i class="fa fa-copy"></i></button>
                        </div>
                    </div>
                    <div id="lista_cvvs" class="result-list" style="display: none; text-align: left;"></div>
                </div>

                <!-- CCN LIST -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 class="card-title"><i class="fa fa-credit-card" style="color: #ffc107;"></i> CCN LIVE</h4>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm show-lives"><i class="fa fa-eye-slash"></i></button>
                            <button class="btn btn-success btn-sm btn-copy"><i class="fa fa-copy"></i></button>
                        </div>
                    </div>
                    <div id="lista_aprovadas" class="result-list" style="display: none; text-align: left;"></div>
                </div>

                <!-- DECLINED LIST -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 class="card-title"><i class="fa fa-times" style="color: #dc3545;"></i> DECLINED</h4>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm show-dies"><i class="fa fa-eye"></i></button>
                            <button class="btn btn-danger btn-sm btn-trash"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>
                    <div id="lista_reprovadas" class="result-list" style="text-align: left;"></div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p><b>Developed by Kali Linux &copy; All Rights Reserved</b></p>
    </footer>

    <script>
        $(document).ready(function() {
            let cardElement = null; // Store the Stripe Element instance
            const cardErrors = $('#card-errors');

            // Function to initialize or re-initialize Stripe Elements
            const initializeStripe = (pk) => {
                try {
                    if (cardElement) {
                        cardElement.destroy();
                        cardElement = null;
                    }
                    const stripe = Stripe(pk);
                    const elements = stripe.elements();
                    
                    // The 'card' element handles card number, expiration, and CVC in a single field set
                    cardElement = elements.create('card', {
                        style: {
                            base: {
                                color: '#fff',
                                backgroundColor: '#1a1a1a',
                                fontFamily: '"Muli", sans-serif',
                                fontSize: '15px',
                                '::placeholder': {
                                    color: '#777'
                                }
                            },
                            invalid: {
                                color: '#ff4747'
                            }
                        }
                    });

                    // Mount the element into the container
                    cardElement.mount('#card-element');

                    // Handle real-time validation errors from Stripe.js
                    cardElement.on('change', function(event) {
                        cardErrors.text(event.error ? event.error.message : '');
                    });

                    return { stripe, cardElement };

                } catch (e) {
                    Swal.fire({
                        title: 'Stripe Error',
                        text: 'Failed to initialize Stripe: ' + e.message,
                        icon: 'error',
                        toast: true,
                        position: 'top-end',
                        timer: 4000
                    });
                    return { stripe: null, cardElement: null };
                }
            };

            // Toggle visibility for result sections
            $('.show-charge, .show-live, .show-lives, .show-dies').on('click', function() {
                const button = $(this);
                const targetId = button.closest('.card').find('.result-list').attr('id');
                const target = $('#' + targetId);
                
                target.slideToggle(300, function() {
                    const isVisible = target.is(':visible');
                    button.html(isVisible ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>');
                });
            });

            // Trash button for Declines
            $('.btn-trash').on('click', function() {
                $('#lista_reprovadas').text('');
                $('.reprovadas').text('0');
                Swal.fire({
                    title: 'CLEARED DECLINED',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            });

            // Copy buttons
            $('.btn-copy1, .btn-copy2, .btn-copy').on('click', function() {
                let target;
                let title;
                if ($(this).hasClass('btn-copy1')) {
                    target = $('#lista_charge');
                    title = 'CHARGED';
                } else if ($(this).hasClass('btn-copy2')) {
                    target = $('#lista_cvvs');
                    title = 'CVV LIVE';
                } else {
                    target = $('#lista_aprovadas');
                    title = 'CCN LIVE';
                }

                const content = target.text().trim();
                if (content) {
                    const textarea = document.createElement("textarea");
                    textarea.value = content;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);

                    Swal.fire({
                        title: `COPIED ${title}`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        title: `NOTHING TO COPY`,
                        icon: 'warning',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            });

            let currentCheck = null; // To hold the AJAX call object for stopping

            $('.btn-play').on('click', async function() {
                const pk = $("#pk").val().trim();
                const sk = $("#sk").val().trim();
                const cst = $("#cst").val().trim() || "1"; // Default to $1
                const gate = $("#gate").val();

                // Validation
                if (!pk.match(/^(pk_test_|pk_live_)[A-Za-z0-9]+$/)) {
                    return Swal.fire({ title: 'Invalid Publishable Key', icon: 'error', toast: true, position: 'top-end', timer: 3000 });
                }
                if (!sk.match(/^(sk_test_|sk_live_)[A-Za-z0-9]+$/)) {
                    return Swal.fire({ title: 'Invalid Secret Key', icon: 'error', toast: true, position: 'top-end', timer: 3000 });
                }
                if ((pk.startsWith('pk_test_') !== sk.startsWith('sk_test_'))) {
                    return Swal.fire({ title: 'Key Mode Mismatch', text: 'Both keys must be test or both must be live.', icon: 'error', toast: true, position: 'top-end', timer: 4000 });
                }

                // 1. Initialize Stripe Elements with the user's PK
                const { stripe, cardElement: newCardElement } = initializeStripe(pk);
                if (!stripe || !newCardElement) return;

                // Disable play button, enable stop button
                $('.btn-play').attr('disabled', true);
                $('.btn-stop').attr('disabled', false);
                
                // Show checking status
                Swal.fire({
                    title: 'Tokenizing Card...',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });

                // 2. Tokenize the card data using Stripe.js
                const { paymentMethod, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: newCardElement
                });

                if (error) {
                    newCardElement.clear();
                    newCardElement.destroy();
                    $('.btn-play').attr('disabled', false);
                    $('.btn-stop').attr('disabled', true);
                    cardErrors.text(error.message);
                    return Swal.fire({ title: `Card Error: ${error.message}`, icon: 'error', toast: true, position: 'top-end', timer: 4000 });
                }
                
                // Clear the input fields visually after successful tokenization
                newCardElement.clear(); 
                
                // Show checking status
                Swal.fire({
                    title: 'Token Success! Checking card with gate...',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });

                // Prepare data for server-side check
                const cardInfo = `${paymentMethod.card.brand} - ${paymentMethod.card.funding}|${paymentMethod.card.last4}|${paymentMethod.card.exp_month}|${paymentMethod.card.exp_year}|CVC: XXX`;
                
                // 3. Send token and keys to the server for charge attempt
                currentCheck = $.ajax({
                    url: gate,
                    method: 'POST',
                    data: {
                        payment_method: paymentMethod.id,
                        amount: cst,
                        lista: cardInfo,
                        pk: pk,
                        sk: sk
                    },
                    success: function(retorno) {
                        const status = retorno.split('[')[0].trim();
                        
                        if (status.includes("CHARGED")) {
                            $('#lista_charge').prepend(`<div>${retorno}</div>`);
                            $('.charge').text(parseInt($('.charge').text()) + 1);
                            Swal.fire({ title: 'CHARGED SUCCESS!', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        } else if (status.includes("CVV")) {
                            $('#lista_cvvs').prepend(`<div>${retorno}</div>`);
                            $('.cvvs').text(parseInt($('.cvvs').text()) + 1);
                            Swal.fire({ title: 'CVV LIVE!', icon: 'info', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        } else if (status.includes("CCN")) {
                            $('#lista_aprovadas').prepend(`<div>${retorno}</div>`);
                            $('.aprovadas').text(parseInt($('.aprovadas').text()) + 1);
                            Swal.fire({ title: 'CCN LIVE!', icon: 'warning', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        } else {
                            $('#lista_reprovadas').prepend(`<div>${retorno}</div>`);
                            $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                        }
                        
                        // Update total tested count
                        $('.carregadas').text(
                            parseInt($('.charge').text()) + 
                            parseInt($('.cvvs').text()) + 
                            parseInt($('.aprovadas').text()) + 
                            parseInt($('.reprovadas').text())
                        );

                        // Cleanup and reset UI for next check
                        newCardElement.destroy();
                        cardElement = null; // Reset global pointer
                        $('.btn-play').attr('disabled', false);
                        $('.btn-stop').attr('disabled', true);
                    },
                    error: function(xhr, status, error) {
                        const errorMessage = xhr.responseText || `Server/Network Error: ${status}`;
                        $('#lista_reprovadas').prepend(`<div><font color=red><b>DEAD [${errorMessage}]</b><br><span style="color: #ff4747; font-weight: bold;">${cardInfo}</span></div>`);
                        $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                        $('.carregadas').text(
                            parseInt($('.charge').text()) + 
                            parseInt($('.cvvs').text()) + 
                            parseInt($('.aprovadas').text()) + 
                            parseInt($('.reprovadas').text())
                        );
                        Swal.fire({ title: `Error: ${errorMessage}`, icon: 'error', toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 });

                        // Cleanup and reset UI
                        newCardElement.destroy();
                        cardElement = null; // Reset global pointer
                        $('.btn-play').attr('disabled', false);
                        $('.btn-stop').attr('disabled', true);
                    }
                });
            });

            // Stop button handler
            $('.btn-stop').on('click', function() {
                if (currentCheck) {
                    currentCheck.abort();
                }
                if (cardElement) {
                    cardElement.destroy();
                    cardElement = null;
                }
                
                Swal.fire({
                    title: 'CHECK STOPPED',
                    icon: 'warning',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
                $('.btn-play').attr('disabled', false);
                $('.btn-stop').attr('disabled', true);
            });
            
            // Initial Stripe Element setup prompt
            $('#card-element').html('<div style="color: #555; text-align: center; line-height: 25px;">Enter your keys and click START to enable card input fields.</div>');
        });
    </script>
</body>
</html>
