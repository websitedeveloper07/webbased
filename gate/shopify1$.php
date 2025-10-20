// In the JavaScript section of index.php, find the MAX_CONCURRENT constant
const MAX_CONCURRENT = 3; // Changed from 5 to 3

// Also update the processCards function to handle 504 errors better
async function processCard(card, controller, retryCount = 0) {
    if (!isProcessing) return null;

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

        $('#statusLog').text(`Processing card: ${card.displayCard}`);
        console.log(`Starting request for card: ${card.displayCard}`);

        $.ajax({
            url: selectedGateway,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 65000, // Increased timeout to 65 seconds (slightly more than backend)
            signal: controller.signal,
            success: function(response) {
                // Use our improved response parser
                const parsedResponse = parseGatewayResponse(response);
                
                console.log(`Completed request for card: ${card.displayCard}, Status: ${parsedResponse.status}, Response: ${parsedResponse.message}`);
                resolve({
                    status: parsedResponse.status,
                    response: parsedResponse.message,
                    card: card,
                    displayCard: card.displayCard
                });
            },
            error: function(xhr) {
                $('#statusLog').text(`Error on card: ${card.displayCard} - ${xhr.statusText} (HTTP ${xhr.status})`);
                console.error(`Error for card: ${card.displayCard}, Status: ${xhr.status}, Text: ${xhr.statusText}, Response: ${xhr.responseText}`);
                
                // Special handling for 504 errors
                if (xhr.status === 504) {
                    $('#statusLog').text(`Gateway timeout for card: ${card.displayCard}, retrying...`);
                    if (retryCount < MAX_RETRIES && isProcessing) {
                        setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
                        return;
                    }
                }
                
                // Try to parse error response
                let errorResponse = `Declined [Request failed: ${xhr.statusText} (HTTP ${xhr.status})]`;
                
                if (xhr.responseText) {
                    try {
                        // Try to parse as JSON first
                        const errorJson = JSON.parse(xhr.responseText);
                        if (errorJson) {
                            // Use our improved parser for error responses too
                            const parsedError = parseGatewayResponse(errorJson);
                            errorResponse = parsedError.message;
                        }
                    } catch (e) {
                        // Not JSON, use the raw response text
                        errorResponse = xhr.responseText;
                    }
                }
                
                if (xhr.statusText === 'abort') {
                    resolve(null);
                } else if ((xhr.status === 0 || xhr.status >= 500) && retryCount < MAX_RETRIES && isProcessing) {
                    setTimeout(() => processCard(card, controller, retryCount + 1).then(resolve), 2000);
                } else {
                    resolve({
                        status: 'DECLINED',
                        response: errorResponse,
                        card: card,
                        displayCard: card.displayCard
                    });
                }
            }
        });
    });
}
