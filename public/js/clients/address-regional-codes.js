/**
 * Address Regional Code Classification for Australian Migration
 * Determines regional classification based on Australian postcodes
 */

/**
 * Regional Code Classification Function for Australian Migration
 * @param {string|number} postCode - Australian postcode
 * @returns {string} Regional classification
 */
window.getRegionalCodeInfo = function(postCode) {
    // Convert to number for comparison
    postCode = parseInt(postCode);
    
    // NT - Northern Territory: 0800 to 0999 (All are Regional centres)
    if (postCode >= 800 && postCode <= 999) {
        return "Regional Centre NT";
    }

    // ACT - Australian Capital Territory: 0200 to 0299 and 2600 to 2639
    if ((postCode >= 200 && postCode <= 299) || (postCode >= 2600 && postCode <= 2639)) {
        return "Regional City ACT";
    }

    // NSW Regional City: 2259, 2264 to 2308, 2500 to 2526, 2528 to 2535 and 2574
    if (
        postCode === 2259 ||
        (postCode >= 2264 && postCode <= 2308) ||
        (postCode >= 2500 && postCode <= 2526) ||
        (postCode >= 2528 && postCode <= 2535) ||
        postCode === 2574
    ) {
        return "Regional City NSW";
    }

    // NSW Regional Centre: 2250 to 2258, 2260 to 2263, 2311 to 2490, 2527, 2536 to 2551, 
    // 2575 to 2599, 2640 to 2739, 2753 to 2754, 2756 to 2758 and 2773 to 2898
    if (
        (postCode >= 2250 && postCode <= 2258) ||
        (postCode >= 2260 && postCode <= 2263) ||
        (postCode >= 2311 && postCode <= 2490) ||
        postCode === 2527 ||
        (postCode >= 2536 && postCode <= 2551) ||
        (postCode >= 2575 && postCode <= 2599) ||
        (postCode >= 2640 && postCode <= 2739) ||
        (postCode >= 2753 && postCode <= 2754) ||
        (postCode >= 2756 && postCode <= 2758) ||
        (postCode >= 2773 && postCode <= 2898)
    ) {
        return "Regional Centre NSW";
    }

    // NSW Metro Area - All other NSW postcodes (2000-2999)
    if (postCode >= 2000 && postCode <= 2999) {
        return "Metro Area NSW";
    }

    // VIC Regional City: 3211 to 3232, 3235, 3240, 3328, 3330 to 3333, 3340 and 3342
    if (
        (postCode >= 3211 && postCode <= 3232) ||
        postCode === 3235 ||
        postCode === 3240 ||
        postCode === 3328 ||
        (postCode >= 3330 && postCode <= 3333) ||
        postCode === 3340 ||
        postCode === 3342
    ) {
        return "Regional City VIC";
    }

    // VIC Regional Centre: 3097 to 3099, 3139, 3233 to 3234, 3236 to 3239, 3241 to 3325, 3329, 3334, 3341,
    // 3345 to 3424, 3430 to 3799, 3809 to 3909, 3912 to 3971 and 3978 to 3996
    if (
        (postCode >= 3097 && postCode <= 3099) ||
        postCode === 3139 ||
        (postCode >= 3233 && postCode <= 3234) ||
        (postCode >= 3236 && postCode <= 3239) ||
        (postCode >= 3241 && postCode <= 3325) ||
        postCode === 3329 ||
        postCode === 3334 ||
        postCode === 3341 ||
        (postCode >= 3345 && postCode <= 3424) ||
        (postCode >= 3430 && postCode <= 3799) ||
        (postCode >= 3809 && postCode <= 3909) ||
        (postCode >= 3912 && postCode <= 3971) ||
        (postCode >= 3978 && postCode <= 3996)
    ) {
        return "Regional Centre VIC";
    }

    // VIC Metro Area - All other VIC postcodes (3000-3999)
    if (postCode >= 3000 && postCode <= 3999) {
        return "Metro Area VIC";
    }

    // QLD Regional City: 4019 to 4022, 4025, 4037, 4074, 4076 to 4078, 4207 to 4275, 4300 to 4301,
    // 4303 to 4305, 4500 to 4506, 4508 to 4512, 4514 to 4516, 4517 to 4519, 4521,
    // 4550 to 4551, 4553 to 4562, 4564 to 4569 and 4571 to 4575
    if (
        (postCode >= 4019 && postCode <= 4022) ||
        postCode === 4025 ||
        postCode === 4037 ||
        postCode === 4074 ||
        (postCode >= 4076 && postCode <= 4078) ||
        (postCode >= 4207 && postCode <= 4275) ||
        (postCode >= 4300 && postCode <= 4301) ||
        (postCode >= 4303 && postCode <= 4305) ||
        (postCode >= 4500 && postCode <= 4506) ||
        (postCode >= 4508 && postCode <= 4512) ||
        (postCode >= 4514 && postCode <= 4516) ||
        (postCode >= 4517 && postCode <= 4519) ||
        postCode === 4521 ||
        (postCode >= 4550 && postCode <= 4551) ||
        (postCode >= 4553 && postCode <= 4562) ||
        (postCode >= 4564 && postCode <= 4569) ||
        (postCode >= 4571 && postCode <= 4575)
    ) {
        return "Regional City QLD";
    }

    // QLD Regional Centre: 4124, 4125, 4133, 4183 to 4184, 4280 to 4287, 4306 to 4498, 4507, 4552, 4563,
    // 4570 and 4580 to 4895
    if (
        postCode === 4124 ||
        postCode === 4125 ||
        postCode === 4133 ||
        (postCode >= 4183 && postCode <= 4184) ||
        (postCode >= 4280 && postCode <= 4287) ||
        (postCode >= 4306 && postCode <= 4498) ||
        postCode === 4507 ||
        postCode === 4552 ||
        postCode === 4563 ||
        postCode === 4570 ||
        (postCode >= 4580 && postCode <= 4895)
    ) {
        return "Regional Centre QLD";
    }

    // QLD Metro Area - All other QLD postcodes (4000-4999)
    if (postCode >= 4000 && postCode <= 4999) {
        return "Metro Area QLD";
    }

    // WA Regional City: 6000 to 6038, 6050 to 6083, 6090 to 6182, 6208 to 6211, 6214 and 6556 to 6558
    else if (
        (postCode >= 6000 && postCode <= 6038) ||
        (postCode >= 6050 && postCode <= 6083) ||
        (postCode >= 6090 && postCode <= 6182) ||
        (postCode >= 6208 && postCode <= 6211) ||
        postCode === 6214 ||
        (postCode >= 6556 && postCode <= 6558)
    ) {
        return "Regional City WA";
    }

    // WA Regional centres - All other WA postcodes (6000-6999)
    else if (postCode >= 6000 && postCode <= 6999) {
        return "Regional Centre WA";
    }

    // SA Regional City: 5000 to 5171, 5173 to 5174, 5231 to 5235, 5240 to 5252, 5351 and 5950 to 5960
    if (
        (postCode >= 5000 && postCode <= 5171) ||
        (postCode >= 5173 && postCode <= 5174) ||
        (postCode >= 5231 && postCode <= 5235) ||
        (postCode >= 5240 && postCode <= 5252) ||
        postCode === 5351 ||
        (postCode >= 5950 && postCode <= 5960)
    ) {
        return "Regional City SA";
    }

    // SA Regional centres - All other SA postcodes (5000-5999)
    if (postCode >= 5000 && postCode <= 5999) {
        return "Regional Centre SA";
    }

    // TAS Regional City: 7000, 7004 to 7026, 7030 to 7109, 7140 to 7151 and 7170 to 7177
    if (
        postCode === 7000 ||
        (postCode >= 7004 && postCode <= 7026) ||
        (postCode >= 7030 && postCode <= 7109) ||
        (postCode >= 7140 && postCode <= 7151) ||
        (postCode >= 7170 && postCode <= 7177)
    ) {
        return "Regional City TAS";
    }

    // TAS Regional centres - All other TAS postcodes (7000-7999)
    if (postCode >= 7000 && postCode <= 7999) {
        return "Regional Centre TAS";
    }

    // Other Australian Territories (Christmas Island, Cocos Islands)
    if (postCode === 6798 || postCode === 6799) {
        return "Regional Centre - Other Territories";
    }

    return '';
};

/**
 * Helper function to validate Australian postcodes
 * @param {string|number} postcode - Postcode to validate
 * @returns {boolean} True if valid Australian postcode
 */
window.isValidAustralianPostcode = function(postcode) {
    return /^\d{4}$/.test(String(postcode));
};

/**
 * Initialize regional code calculation functionality
 */
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        console.log('🚀 Initializing address regional code functionality');
        
        // Auto-calculate regional code based on postcode input
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="zip[]"]')) {
                handlePostcodeInput(e.target);
            }
        });
        
        console.log('✅ Regional code functionality initialized');
        console.log('🔧 getRegionalCodeInfo available:', typeof window.getRegionalCodeInfo);
        console.log('🔧 isValidAustralianPostcode available:', typeof window.isValidAustralianPostcode);
        
        // Test function for debugging - call testRegionalCode() in console
        window.testRegionalCode = function(postcode) {
            console.log('🧪 Testing regional code for:', postcode);
            if (window.isValidAustralianPostcode(postcode)) {
                const result = window.getRegionalCodeInfo(postcode);
                console.log('✅ Result:', result);
                return result;
            } else {
                console.log('❌ Invalid postcode format');
                return null;
            }
        };
        
        console.log('🧪 Test function available: testRegionalCode(postcode)');
    }
    
    /**
     * Handle postcode input and calculate regional code
     */
    function handlePostcodeInput(input) {
        const postcode = input.value.trim();
        
        // Find the regional code input in the same address entry
        const wrapper = input.closest('.address-entry-wrapper');
        if (!wrapper) return;
        
        const regionalCodeInput = wrapper.querySelector('input[name="regional_code[]"]');
        if (!regionalCodeInput) return;
        
        if (postcode && window.isValidAustralianPostcode(postcode)) {
            const regionalInfo = window.getRegionalCodeInfo(postcode);
            regionalCodeInput.value = regionalInfo;
            console.log('🔢 Regional code calculated:', regionalInfo, 'from postcode:', postcode);
        } else {
            regionalCodeInput.value = '';
        }
    }
})();

