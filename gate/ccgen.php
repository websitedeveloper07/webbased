<?php

// Function to perform Luhn checksum validation
function luhn_checksum($number) {
    $number = strrev(preg_replace('/[^0-9]/', '', $number));
    $sum = 0;
    for ($i = 0; $i < strlen($number); $i++) {
        $digit = (int)$number[$i];
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return $sum % 10 == 0;
}

// Function to generate a random string of digits
function generate_random_digits($length) {
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= rand(0, 9);
    }
    return $digits;
}

// Main card generation function
function generate_cards($input, $num_cards = 10, $format_index = 0) {
    $cards = [];
    $parts = explode("|", $input);
    $card_base = preg_replace('/[^0-9]/', '', $parts[0]); // Clean card base
    $extra_mm = isset($parts[1]) && ctype_digit($parts[1]) ? str_pad($parts[1], 2, '0', STR_PAD_LEFT) : null;
    $extra_yyyy = isset($parts[2]) && ctype_digit($parts[2]) ? $parts[2] : null;
    $extra_cvv = isset($parts[3]) && ctype_digit($parts[3]) ? $parts[3] : null;

    // Validate card base
    if (!ctype_digit($card_base) || strlen($card_base) < 6 || strlen($card_base) > 16) {
        return ['error' => "BIN/sequence must be 6 to 16 digits."];
    }

    // Determine card length (default to 16, or use provided length up to 16)
    $card_length = 16; // Standard length for most cards (e.g., Maestro, Visa)

    // Generate cards
    $attempts = 0;
    $max_attempts = $num_cards * 100;
    while (count($cards) < $num_cards && $attempts < $max_attempts) {
        $attempts++;
        $suffix_len = $card_length - strlen($card_base);
        if ($suffix_len < 0) {
            return ['error' => "Card base is longer than 16 digits."];
        }

        $card_number = $card_base . generate_random_digits($suffix_len);
        if (!luhn_checksum($card_number)) {
            continue;
        }

        // Generate or use provided MM, YYYY, CVV
        $mm = $extra_mm ?? str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $yyyy = $extra_yyyy ?? (date('Y') + rand(1, 5));
        $cvv = $extra_cvv ?? str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); // 3-digit CVV

        // Generate all supported formats
        $yy = substr($yyyy, -2);
        $card_formats = [
            "$card_number|$mm|$yy|$cvv",  // card|mm|yy|cvv
            "$card_number|$mm|$yyyy|$cvv", // card|mm|yyyy|cvv
            "$card_number|$mm|$yy",       // card|mm|yy
            "$card_number|$mm|",          // card|mm|
            "$card_number/$mm|$yy"        // card/mm|yy
        ];

        $cards[] = $card_formats[$format_index];
    }

    if (count($cards) == 0) {
        return ['error' => "Failed to generate valid cards after maximum attempts."];
    }

    return ['cards' => $cards];
}

// Handle HTTP request
$input = isset($_GET['bin']) ? trim($_GET['bin']) : '';
$num_cards = isset($_GET['num']) && ctype_digit($_GET['num']) ? (int)$_GET['num'] : 10;
$format_index = isset($_GET['format']) && ctype_digit($_GET['format']) && $_GET['format'] < 5 ? (int)$_GET['format'] : 0;

if (empty($input)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Please provide BIN or sequence (at least 6 digits). Usage: ccgen.php?bin=414740&num=10&format=0"]);
    exit;
}

$result = generate_cards($input, $num_cards, $format_index);

// Handle errors
if (isset($result['error'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $result['error']]);
    exit;
}

// Output cards
$cards = $result['cards'];
if ($num_cards > 10) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="generated_cards.txt"');
    foreach ($cards as $card) {
        echo $card . "\n";
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['cards' => $cards]);
}

?>
