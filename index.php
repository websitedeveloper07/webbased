<!DOCTYPE html>
<html class="loading">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-width=1.0, user-scalable=0, minimal-ui">
    <title>CARD X CHK</title>
    <link href="https://fonts.googleapis.com/css?family=Muli:300,300i,400,400i,600,600i,700,700i%7CComfortaa:300,400,700" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="theme-assets/css/vendors.css">
    <link rel="stylesheet" type="text/css" href="theme-assets/css/app-lite.css">
    <link rel="stylesheet" type="text/css" href="theme-assets/css/core/menu/menu-types/vertical-menu.css">
    <link rel="stylesheet" type="text/css" href="theme-assets/css/core/colors/palette-gradient.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="theme-assets/js/core/libraries/jquery.min.js" type="text/javascript"></script>
    <style>
        @import url("http://fonts.googleapis.com/css?family=Muli:300,300i,400,400i,600,600i,700,700i%7CComfortaa:300,400,700");
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Muli', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.45;
            margin: 0;
            text-align: left;
            color: #6b6f80;
            background: linear-gradient(-45deg, #000000, #1a1a1a, #000000, #1a1a1a);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        html {
            font-family: sans-serif;
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
            font-size: 14px;
            width: 100%;
            height: 100%;
        }
        .row {
            display: flex;
            margin-right: -15px;
            margin-left: -15px;
            flex-wrap: wrap;
        }
        .col-md-8 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        .col-md-4 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        .col-xl-12 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }
        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-control {
            color: #fff;
            border: 1px solid #fff;
            background-color: #000;
            border-radius: .25rem;
            padding: .5rem 1rem;
            font-size: 1rem;
            line-height: 1.25;
            width: 100%;
            resize: none;
            overflow: hidden;
        }
        .form-control:focus {
            color: #fff;
            border-color: #fff;
            background-color: #000;
            box-shadow: none;
        }
        select.form-control {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            height: calc(2.25rem + 2px);
        }
        
        /* New/Updated Style for the ONE LINE Stripe Element */
        #card-element {
            /* This is the outer container for the one-line card field */
            width: 100%;
            border: 1px solid #fff;
            border-radius: .25rem;
            min-height: 40px; 
            padding: 8px 12px; /* Set interior padding */
            background-color: #fff; /* Outer border color, will be overridden by style object for the input area */
            display: flex; /* Ensure proper sizing of the inner fields */
            align-items: center;
        }
        
        /* Ensure the input text color is black for the white box */
        #card-element iframe {
            color: #000 !important;
        }
        
        #card-errors {
            color: #ff000f;
            margin-top: 10px;
            text-align: center;
        }
        /* ... (rest of the non-payment related CSS is truncated for brevity) ... */

        .btn {
            font-weight: 600;
            letter-spacing: .8px;
            border-radius: .25rem;
            color: #fff;
        }
        .btn-bg-gradient-x-blue-cyan {
            background-image: linear-gradient(90deg, #382da2, #00daf7, #382da2);
        }
        .btn-bg-gradient-x-red-pink {
            background-image: linear-gradient(90deg, #cd0000, #8d003b, #cd0000);
        }
        .btn-success {
            background-color: #1cff00;
        }
        .btn-primary {
            background-color: #0500ff;
        }
        .btn-danger {
            background-color: #ff000f;
        }
        .badge {
            font-size: 85%;
            font-weight: 400;
            color: #fff;
            padding: .35em .4em;
            border-radius: .25rem;
        }
        .badge-dark {
            background-color: #000;
        }
        .badge-success {
            background-color: #1a810c;
        }
        .badge-info {
            background-color: #00bae7;
        }
        .badge-primary {
            background-color: #0500ff;
        }
        .badge-danger {
            background-color: #ff000f;
        }
        .card-body {
            background-color: #000;
            border: 1px solid #62008c;
        }
        .card-title, h5, .mb-2 {
            color: #fff;
        }
        .anime {
            position: relative;
            border: 0 !important;
            box-shadow: 0 0 10px 5px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }
        /* ... (rest of animation CSS truncated for brevity) ... */
        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        #gate, label {
            color: #fff;
        }
        footer {
            text-align: center;
            padding: 20px 0;
            color: #ff0000;
            font-size: 12px;
        }
    </style>
</head>
<body class="vertical-layout" data-color="bg-gradient-x-purple-blue">
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-wrapper-before mb-3"></div>
            <div class="content-body">
                <div class="mt-2"></div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body text-center anime">
                                <span></span><span></span><span></span><span></span>
                                <h4 class="mb-2"><strong>Card X Chk SK Based CHECKER</strong></h4>
                                
                                <div class="form-group">
                                    <label for="card-element">Card Details (Number, Exp, CVC)</label>
                                    <div id="card-element">
                                        </div>
                                </div>
                                
                                <div id="card-errors" role="alert"></div>
                                <div class="input-group">
                                    <textarea rows="1" class="form-control" id="pk" placeholder="PUBLISHABLE KEY (pk_test_ or pk_live_)"></textarea>
                                    <textarea rows="1" class="form-control" id="sk" placeholder="SECRET KEY (sk_test_ or sk_live_)"></textarea>
                                </div>
                                <textarea rows="1" class="form-control text-center" id="cst" placeholder="CUSTOM AMOUNT (e.g., 1 for $1)"></textarea>
                                <select name="gate" id="gate" class="form-control" style="margin: 10px 0;">
                                    <option style="background:rgba(16, 15, 154, 0.281);color:#fff" value="gate/charge.php">Stripe.js CCN Charged : $1</option>
                                </select>
                                <button class="btn btn-play btn-glow btn-bg-gradient-x-blue-cyan" style="width: 49%; float: left;"><i class="fa fa-play"></i> START</button>
                                <button class="btn btn-stop btn-glow btn-bg-gradient-x-red-pink" style="width: 49%; float: right;" disabled><i class="fa fa-stop"></i> STOP</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-2">
                            <div class="card-body">
                                <h5 style="margin-bottom:-0.2rem">TOTAL :<span class="badge badge-dark float-right carregadas">0</span></h5>
                                <hr>
                                <h5 style="margin-bottom:-0.2rem">CHARGED :<span class="badge badge-success float-right charge">0</span></h5>
                                <hr>
                                <h5 style="margin-bottom:-0.2rem">CVV :<span class="badge badge-info float-right cvvs">0</span></h5>
                                <hr>
                                <h5 style="margin-bottom:-0.2rem">CCN :<span class="badge badge-primary float-right aprovadas">0</span></h5>
                                <hr>
                                <h5 style="margin-bottom:-0.2rem">DEAD :<span class="badge badge-danger float-right reprovadas">0</span></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <button type="show" class="btn btn-primary btn-sm show-charge"><i class="fa fa-eye-slash"></i></button>
                                    <button class="btn btn-success btn-sm btn-copy1"><i class="fa fa-copy"></i></button>
                                </div>
                                <center>
                                    <h4 class="card-title mb-1" style="text-align:left;"><i class="fa fa-check-circle text-success"></i> CHARGED</h4>
                                    <div id="lista_charge" style="text-align:left;"></div>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <button type="show" class="btn btn-primary btn-sm show-live"><i class="fa fa-eye-slash"></i></button>
                                    <button class="btn btn-success btn-sm btn-copy2"><i class="fa fa-copy"></i></button>
                                </div>
                                <center>
                                    <h4 class="card-title mb-1" style="text-align:left;"><i class="fa fa-check text-success"></i> CVV</h4>
                                    <div id="lista_cvvs" style="text-align:left;"></div>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <button type="show" class="btn btn-primary btn-sm show-lives"><i class="fa fa-eye-slash"></i></button>
                                    <button class="btn btn-success btn-sm btn-copy"><i class="fa fa-copy"></i></button>
                                </div>
                                <center>
                                    <h4 class="card-title mb-1" style="text-align:left;"><i class="fa fa-times text-success"></i> CCN</h4>
                                    <div id="lista_aprovadas" style="text-align:left;"></div>
                                </center>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <button type="hidden" class="btn btn-primary btn-sm show-dies"><i class="fa fa-eye"></i></button>
                                    <button class="btn btn-danger btn-sm btn-trash"><i class="fa fa-trash"></i></button>
                                </div>
                                <center>
                                    <h4 class="card-title mb-1" style="text-align:left;"><i class="fa fa-times text-danger"></i> DECLINED</h4>
                                    <div id="lista_reprovadas" style="text-align:left;"></div>
                                </center>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer>
            <p><b>Developed by Kali Linux &copy; All Rights Reserved</b></p>
        </footer>
    </div>
    <script>
        let stripe;
        let cardElement; // Now a single element variable
        const cardErrors = $('#card-errors');

        // Function to initialize Stripe Elements
        function initializeStripeElements(publishableKey) {
            // Only re-initialize if the key has changed or stripe is not defined
            if (!stripe || stripe._apiKey !== publishableKey) {
                try {
                    // 1. Initialize Stripe with the provided Publishable Key
                    stripe = Stripe(publishableKey);
                    const elements = stripe.elements();

                    // Define the style with a white background for the input area
                    const style = {
                        base: {
                            color: '#000', // Text color black for white background
                            backgroundColor: '#fff', // **White background for the input area**
                            fontFamily: '"Muli", sans-serif',
                            fontSize: '16px',
                            '::placeholder': { color: '#aaa' }
                        },
                        invalid: {
                            color: '#ff000f'
                        }
                    };

                    // Clear previous element if it exists
                    if (cardElement) cardElement.unmount();
                    
                    // 2. Create and Mount the SINGLE 'card' Element
                    // This element automatically handles card number, expiry, and CVC on one line.
                    cardElement = elements.create('card', {
                        style: style,
                        classes: { base: 'form-control' }
                    });
                    cardElement.mount('#card-element'); // Mount to the single container div

                    // 3. Handle card input errors
                    const handleError = (event) => {
                        cardErrors.text(event.error ? `Card Error: ${event.error.message}` : '');
                    };
                    cardElement.on('change', handleError);

                } catch (e) {
                    console.error("Stripe Initialization Error:", e);
                    cardErrors.text('Error initializing Stripe. Check your console and API key.');
                }
            }
        }

        $(document).ready(function() {
            const swalWithBootstrapButtons = Swal.mixin({
                customClass: {
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-danger'
                },
                buttonsStyling: false
            });

            // ************************************************
            // INITIALIZE STRIPE ELEMENTS ON PAGE LOAD (CRUCIAL)
            // ************************************************
            // Use a dummy key to initialize fields immediately
            initializeStripeElements("pk_test_TYooMQauvdEDq54NiTphI7jx");

            // Event listener for PK changes to re-initialize Stripe
            $("#pk").on('change paste keyup', function() {
                const pk = $(this).val().trim();
                if (pk.match(/^(pk_test_|pk_live_)[A-Za-z0-9]+$/)) {
                    initializeStripeElements(pk);
                    Swal.fire({
                        title: 'Stripe Initialized!',
                        icon: 'info',
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                        timer: 1500
                    });
                }
            });
            
            // --- UI/Helper Functions (Copy/Toggle) ---
            $('.show-charge').click(function() {
                var type = $(this).attr('type');
                $('#lista_charge').slideToggle();
                $(this).html(type == 'show' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>');
                $(this).attr('type', type == 'show' ? 'hidden' : 'show');
            });

            $('.show-live').click(function() {
                var type = $(this).attr('type');
                $('#lista_cvvs').slideToggle();
                $(this).html(type == 'show' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>');
                $(this).attr('type', type == 'show' ? 'hidden' : 'show');
            });

            $('.show-lives').click(function() {
                var type = $(this).attr('type');
                $('#lista_aprovadas').slideToggle();
                $(this).html(type == 'show' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>');
                $(this).attr('type', type == 'show' ? 'hidden' : 'show');
            });

            $('.show-dies').click(function() {
                var type = $(this).attr('type');
                $('#lista_reprovadas').slideToggle();
                $(this).html(type == 'show' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>');
                $(this).attr('type', type == 'show' ? 'hidden' : 'show');
            });

            $('.btn-trash').click(function() {
                Swal.fire({ title: 'REMOVED DEAD', icon: 'success', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                $('#lista_reprovadas').text('');
            });

            function copyToClipboard(selector, title) {
                Swal.fire({ title: `COPIED ${title}`, icon: 'success', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                var lista_text = $(selector).text();
                var textarea = document.createElement("textarea");
                textarea.value = lista_text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            $('.btn-copy1').click(() => copyToClipboard('#lista_charge', 'CHARGED'));
            $('.btn-copy2').click(() => copyToClipboard('#lista_cvvs', 'CVV'));
            $('.btn-copy').click(() => copyToClipboard('#lista_aprovadas', 'CCN'));
            // --- END UI/Helper Functions ---


            // ************************************************
            // START BUTTON LOGIC (TOKENIZATION)
            // ************************************************
            $('.btn-play').click(async function() {
                const pk = $("#pk").val().trim();
                const sk = $("#sk").val().trim();
                const cst = $("#cst").val().trim() || "1"; // Default to $1
                const gate = $("#gate").val();

                // 1. Re-initialize Stripe with the user's PK if it's valid and different
                if (!pk.match(/^(pk_test_|pk_live_)[A-Za-z0-9]+$/)) {
                    Swal.fire({ title: 'Invalid Stripe publishable key.', icon: 'error', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                    return false;
                }
                initializeStripeElements(pk); // Ensure latest key is used

                // 2. Validate secret key and key mode consistency
                if (!sk.match(/^(sk_test_|sk_live_)[A-Za-z0-9]+$/)) {
                    Swal.fire({ title: 'Invalid Stripe secret key.', icon: 'error', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                    return false;
                }

                if ((pk.startsWith('pk_test_') && !sk.startsWith('sk_test_')) || (pk.startsWith('pk_live_') && !sk.startsWith('sk_live_'))) {
                    Swal.fire({ title: 'Key mode mismatch.', icon: 'error', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                    return false;
                }
                
                // 3. Create Payment Method (Tokenize Card)
                Swal.fire({ title: 'Tokenizing card...', icon: 'info', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });

                // Use the new, single cardElement
                const { paymentMethod, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement 
                });

                if (error) {
                    cardErrors.text(`Card Error: ${error.message}`);
                    Swal.fire({ title: `Card Error: ${error.message}`, icon: 'error', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                    return false;
                }

                // 4. Send Payment Method ID to your backend gate
                Swal.fire({ title: 'Checking card...', icon: 'success', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                $('.carregadas').text(1);
                $('.btn-play').attr('disabled', true);
                $('.btn-stop').attr('disabled', false);

                var callBack = $.ajax({
                    url: gate,
                    method: 'POST',
                    data: {
                        payment_method: paymentMethod.id, 
                        amount: cst,
                        lista: `${paymentMethod.card.brand}|${paymentMethod.card.last4}|${paymentMethod.card.exp_month}|${paymentMethod.card.exp_year}`,
                        pk: pk,
                        sk: sk
                    },
                    success: function(retorno) {
                        // ... (Success logic)
                        if (retorno.indexOf("CHARGED") >= 0) {
                            $('#lista_charge').append(retorno);
                            $('.charge').text(parseInt($('.charge').text()) + 1);
                        } else if (retorno.indexOf("CVV") >= 0) {
                            $('#lista_cvvs').append(retorno);
                            $('.cvvs').text(parseInt($('.cvvs').text()) + 1);
                        } else if (retorno.indexOf("CCN") >= 0) {
                            $('#lista_aprovadas').append(retorno);
                            $('.aprovadas').text(parseInt($('.aprovadas').text()) + 1);
                        } else {
                            $('#lista_reprovadas').append(retorno);
                            $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                        }
                        $('.testadas').text(parseInt($('.charge').text()) + parseInt($('.cvvs').text()) + parseInt($('.aprovadas').text()) + parseInt($('.reprovadas').text()));
                        
                        Swal.fire({ title: 'CARD CHECKED', icon: 'success', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                        $('.btn-play').attr('disabled', false);
                        $('.btn-stop').attr('disabled', true);
                        cardElement.clear(); // Clear the single element
                    },
                    error: function(xhr) {
                        // ... (Error logic)
                        var errorMessage = xhr.responseText || 'Server error occurred';
                        $('#lista_reprovadas').append(`<font color=red><b>DEAD [${errorMessage}]</b><br><span style="color: #ff4747; font-weight: bold;">${paymentMethod.card.brand}|${paymentMethod.card.last4}|${paymentMethod.card.exp_month}|${paymentMethod.card.exp_year}</span><br>`);
                        $('.reprovadas').text(parseInt($('.reprovadas').text()) + 1);
                        $('.testadas').text(parseInt($('.charge').text()) + parseInt($('.cvvs').text()) + parseInt($('.aprovadas').text()) + parseInt($('.reprovadas').text()));
                        Swal.fire({ title: `Error: ${errorMessage}`, icon: 'error', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                        $('.btn-play').attr('disabled', false);
                        $('.btn-stop').attr('disabled', true);
                        cardElement.clear(); // Clear the single element
                    }
                });

                $('.btn-stop').click(function() {
                    Swal.fire({ title: 'PAUSED', icon: 'warning', showConfirmButton: false, toast: true, position: 'top-end', timer: 3000 });
                    $('.btn-play').attr('disabled', false);
                    $('.btn-stop').attr('disabled', true);
                    callBack.abort();
                    cardElement.clear(); // Clear the single element
                });
            });
        });
    </script>
</body>
</html>
