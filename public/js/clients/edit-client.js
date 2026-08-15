/**
 * Client Edit Page JavaScript
 * Contains all functionality for the client edit form
 */

/**
 * Absolute URL for app routes (fixes subdirectory installs e.g. /BansalLaw_CRM/public).
 * Set window.editClientConfig.rootUrl from Blade via rtrim(url('/'), '/').
 */
function crmClientUrl(path) {
    var root = (typeof window.editClientConfig !== 'undefined' && window.editClientConfig.rootUrl)
        ? String(window.editClientConfig.rootUrl).replace(/\/$/, '')
        : '';
    if (!path || typeof path !== 'string') {
        path = '';
    }
    if (!path.startsWith('/')) {
        path = '/' + path;
    }
    return root ? root + path : path;
}

// ===== SCROLL-TO-SECTION FUNCTIONALITY =====

// Define scrollToSection function IMMEDIATELY in global scope
window.scrollToSection = function(sectionId) {
    try {
        const section = document.getElementById(sectionId);
        if (section) {
            // Smooth scroll to section
            section.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
            
            // Update active tab button
            updateActiveTabButton(sectionId);
        } else {
            console.error('Section not found:', sectionId);
        }
    } catch (error) {
        console.error('Error in scrollToSection function:', error);
    }
};

// Update active nav item based on section
function updateActiveTabButton(sectionId) {
    // Remove active class from all nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to the corresponding nav item
    const items = document.querySelectorAll('.nav-item');
    items.forEach(item => {
        const onclick = item.getAttribute('onclick');
        if (onclick && onclick.includes(sectionId)) {
            item.classList.add('active');
        }
    });
}

// Toggle sidebar for mobile
window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebarNav');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
};

/** Ensure contact summary container exists (phone/email use contact-row-list, not summary-grid). */
function ensureContactRowList(summaryView) {
    if (!summaryView) {
        return null;
    }
    let list = summaryView.querySelector('.contact-row-list');
    if (!list) {
        summaryView.innerHTML = '<div class="contact-row-list"></div>';
        list = summaryView.querySelector('.contact-row-list');
    }
    return list;
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebarNav');
    if (sidebar && window.innerWidth <= 1024) {
        sidebar.classList.remove('open');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebarNav');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (sidebar && toggle && window.innerWidth <= 1024) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Close sidebar when clicking a primary tab on mobile
document.addEventListener('click', function(event) {
    if (event.target.closest('.sidebar-primary-tab') && window.innerWidth <= 1024) {
        closeMobileSidebar();
    }
});

// ===== GO TO TOP BUTTON FUNCTIONALITY =====

// Scroll to top function
window.scrollToTop = function() {
    try {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    } catch (error) {
        console.error('Error scrolling to top:', error);
        // Fallback for older browsers
        document.documentElement.scrollTop = 0;
    }
};

// Show/hide Go to Top button based on scroll position
function initGoToTopButton() {
    const goToTopBtn = document.getElementById('goToTopBtn');
    if (!goToTopBtn) return;
    
    function toggleGoToTopButton() {
        const scrollPosition = window.scrollY;
        const showThreshold = 300; // Show button after scrolling 300px
        
        if (scrollPosition > showThreshold) {
            if (!goToTopBtn.classList.contains('show')) {
                goToTopBtn.classList.remove('hide');
                goToTopBtn.classList.add('show');
            }
        } else {
            if (goToTopBtn.classList.contains('show')) {
                goToTopBtn.classList.remove('show');
                goToTopBtn.classList.add('hide');
                
                // Remove hide class after animation completes
                setTimeout(() => {
                    goToTopBtn.classList.remove('hide');
                }, 300);
            }
        }
    }
    
    // Throttle scroll events for better performance
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                toggleGoToTopButton();
                ticking = false;
            });
            ticking = true;
        }
    });
    
    // Initial call
    toggleGoToTopButton();
}





// ===== LEGACY TAB FUNCTIONALITY (KEPT FOR COMPATIBILITY) =====

// Define openTab function IMMEDIATELY in global scope (legacy support)
window.openTab = function(evt, tabName) {
    try {
        // Prevent default behavior
        if (evt && evt.preventDefault) {
            evt.preventDefault();
        }
        
        // Hide all tab content
        var tabcontent = document.getElementsByClassName("tab-content");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        // Remove active class from all tab buttons
        var tablinks = document.getElementsByClassName("tab-button");
        for (var i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        
        // Show the selected tab content
        var targetTab = document.getElementById(tabName);
        if (targetTab) {
            targetTab.style.display = "block";
        } else {
            console.error('Tab content not found:', tabName);
        }
        
        // Add active class to the clicked button
        if (evt && evt.currentTarget) {
            evt.currentTarget.className += " active";
        }
        
        // Fallback: if evt.currentTarget is not available, find the button by onclick attribute
        if (!evt || !evt.currentTarget) {
            var buttons = document.querySelectorAll('.tab-button');
            for (var i = 0; i < buttons.length; i++) {
                if (buttons[i].getAttribute('onclick') && buttons[i].getAttribute('onclick').includes(tabName)) {
                    buttons[i].className += " active";
                    break;
                }
            }
        }
        
        
    } catch (error) {
        console.error('Error in openTab function:', error);
        // Fallback: try to show the tab content directly
        try {
            var fallbackTab = document.getElementById(tabName);
            if (fallbackTab) {
                // Hide all tabs first
                var allTabs = document.getElementsByClassName("tab-content");
                for (var i = 0; i < allTabs.length; i++) {
                    allTabs[i].style.display = "none";
                }
                // Show target tab
                fallbackTab.style.display = "block";
            }
        } catch (fallbackError) {
            console.error('Fallback also failed:', fallbackError);
        }
    }
};

// Ensure openTab is globally available
window.openTab = openTab;

// Initialize tab functionality when DOM is ready
function initializeTabs() {
    try {
        // Set up event listeners for tab buttons as backup
        var tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(function(button) {
            // Remove existing onclick to prevent conflicts
            var onclickAttr = button.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes('openTab')) {
                // Extract tab name from onclick
                var match = onclickAttr.match(/openTab\(event,\s*['"]([^'"]+)['"]\)/);
                if (match) {
                    var tabName = match[1];
                    // Add event listener as backup
                    button.addEventListener('click', function(e) {
                        openTab(e, tabName);
                    });
                }
            }
        });
        
        // Ensure first tab is active by default
        var firstTab = document.querySelector('.tab-button');
        var firstTabContent = document.getElementById('personalTab');
        if (firstTab && firstTabContent) {
            firstTab.classList.add('active');
            firstTabContent.style.display = 'block';
        }
        
        
    } catch (error) {
        console.error('Error initializing tabs:', error);
    }
}

// Define validateForm function IMMEDIATELY to prevent "not defined" errors
window.validateForm = function() {
    const form = document.getElementById('editClientForm');
    const errors = [];
    
    // Check all required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(function(field) {
        const fieldName = field.name;
        let isValid = false;
        
        // Debug log for gender fields
        if (fieldName && (fieldName.includes('gender') || fieldName.includes('siblings_gender'))) {
            const tabContent = field.closest('.tab-content');
            const tabId = tabContent ? tabContent.id : 'unknown';
        }
        
        // Handle different field types
        if (field.tagName === 'SELECT') {
            // For select fields, check if a valid option is selected (not the first empty option)
            isValid = field.selectedIndex > 0 && field.value !== '';
        } else {
            // For other fields (input, textarea), check if they have a value
            isValid = field.value && field.value.trim() !== '';
        }
        
        // If field is valid, skip to next field
        if (isValid) {
            return;
        }
        
        // Field is invalid, add error with more context
        const label = field.closest('.form-group')?.querySelector('label')?.textContent?.trim() || field.name || 'Unknown field';
        
        // Add context about which tab/section the field is in
        let context = '';
        const tabContent = field.closest('.tab-content');
        if (tabContent) {
            const tabId = tabContent.id;
            if (tabId === 'personalTab') {
                context = ' (Personal tab)';
            } else if (tabId === 'familyTab') {
                context = ' (Family Information tab)';
            } else if (tabId === 'visaPassportCitizenshipTab') {
                context = ' (Visa, Passport & Citizenship tab)';
            } else if (tabId === 'addressTravelTab') {
                context = ' (Address & Travel tab)';
            } else if (tabId === 'skillsEducationTab') {
                context = ' (Skills & Education tab)';
            } else if (tabId === 'otherInformationTab') {
                context = ' (Other Information tab)';
            }
        }
        
        errors.push(`"${label}" is required${context}`);
    });
    
    // If there are errors, show them in alert and prevent submission
    if (errors.length > 0) {
        const errorMessage = 'Please fix the following errors:\n\n' + errors.join('\n');
        alert(errorMessage);
        return false;
    }
    
    return true;
};

// ADDITIONAL FALLBACK: Set up event listeners as backup
document.addEventListener('DOMContentLoaded', function() {
    var tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(function(button) {
        var onclickAttr = button.getAttribute('onclick');
        if (onclickAttr && onclickAttr.includes('openTab')) {
            var match = onclickAttr.match(/openTab\(event,\s*['"]([^'"]+)['"]\)/);
            if (match) {
                var tabName = match[1];
                // Add event listener as backup
                button.addEventListener('click', function(e) {
                    if (typeof window.openTab === 'function') {
                        window.openTab(e, tabName);
                    } else {
                        // Direct fallback if function still not available
                        try {
                            var tabcontent = document.getElementsByClassName("tab-content");
                            for (var i = 0; i < tabcontent.length; i++) {
                                tabcontent[i].style.display = "none";
                            }
                            var tablinks = document.getElementsByClassName("tab-button");
                            for (var i = 0; i < tablinks.length; i++) {
                                tablinks[i].className = tablinks[i].className.replace(" active", "");
                            }
                            var targetTab = document.getElementById(tabName);
                            if (targetTab) {
                                targetTab.style.display = "block";
                            }
                            e.currentTarget.className += " active";
                        } catch (directError) {
                            console.error('Direct fallback failed:', directError);
                        }
                    }
                });
            }
        }
    });
});

// ===== END TAB FUNCTIONALITY =====

// Initialize arrays to track IDs of records marked for deletion
let phoneNumbersToDelete = [];
let emailsToDelete = [];

// Cache visa types to avoid multiple AJAX calls
let visaTypesCache = null;
let countriesCache = null;







/**
 * Legacy hooks — current address is a single entry; multi-address UI removed.
 */
function addAnotherAddress() {}

function removeAddressEntry() {}

function addAddress() {
    toggleEditMode('addressInfo');
}


/**
 * Function to calculate age from date of birth (expects dd/mm/yyyy format)
 */
function calculateAge(dob) {
    if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';

    try {
        const [day, month, year] = dob.split('/').map(Number);
        const dobDate = new Date(year, month - 1, day);
        if (isNaN(dobDate.getTime())) return ''; // Invalid date

        const today = new Date();
        let years = today.getFullYear() - dobDate.getFullYear();
        let months = today.getMonth() - dobDate.getMonth();

        if (months < 0) {
            years--;
            months += 12;
        }

        if (today.getDate() < dobDate.getDate()) {
            months--;
            if (months < 0) {
                years--;
                months += 12;
            }
        }

        return years + ' years ' + months + ' months';
    } catch (e) {
        return '';
    }
}

/**
 * Add Phone Number (Updated to exclude verification slider in repeatable section)
 */
function addPhoneNumber() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('phoneNumbersSummary');
    const editView = document.getElementById('phoneNumbersEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('phoneNumbers');
    }
    
    const container = document.getElementById('phoneNumbersContainer');
    const index = container.children.length;
    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)"><i class="fa-solid fa-trash"></i></button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="contact_type_hidden[${index}]" class="contact-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Mobile">Mobile</option>
                        <option value="Business">Business</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Others">Others</option>
                        <option value="Partner">Partner</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number</label>
                    <div class="cus_field_input" style="display:flex;">
                        <div class="country_code">
                            <select name="country_code[${index}]" class="country-code-input">
                                <option value="+61">🇦🇺 +61</option>
                                <option value="+64">🇳🇿 +64</option>
                                <option value="+91">🇮🇳 +91</option>
                                <option value="+1">🇺🇸 +1</option>
                                <option value="+44">🇬🇧 +44</option>
                                <option value="+49">🇩🇪 +49</option>
                                <option value="+33">🇫🇷 +33</option>
                                <option value="+86">🇨🇳 +86</option>
                                <option value="+81">🇯🇵 +81</option>
                                <option value="+82">🇰🇷 +82</option>
                                <option value="+65">🇸🇬 +65</option>
                                <option value="+60">🇲🇾 +60</option>
                                <option value="+66">🇹🇭 +66</option>
                                <option value="+63">🇵🇭 +63</option>
                                <option value="+84">🇻🇳 +84</option>
                                <option value="+62">🇮🇩 +62</option>
                                <option value="+39">🇮🇹 +39</option>
                                <option value="+34">🇪🇸 +34</option>
                                <option value="+7">🇷🇺 +7</option>
                                <option value="+55">🇧🇷 +55</option>
                                <option value="+52">🇲🇽 +52</option>
                                <option value="+54">🇦🇷 +54</option>
                                <option value="+56">🇨🇱 +56</option>
                                <option value="+57">🇨🇴 +57</option>
                                <option value="+51">🇵🇪 +51</option>
                                <option value="+58">🇻🇪 +58</option>
                                <option value="+27">🇿🇦 +27</option>
                                <option value="+20">🇪🇬 +20</option>
                                <option value="+234">🇳🇬 +234</option>
                                <option value="+254">🇰🇪 +254</option>
                                <option value="+233">🇬🇭 +233</option>
                                <option value="+212">🇲🇦 +212</option>
                                <option value="+213">🇩🇿 +213</option>
                                <option value="+216">🇹🇳 +216</option>
                                <option value="+218">🇱🇾 +218</option>
                                <option value="+220">🇬🇲 +220</option>
                                <option value="+221">🇸🇳 +221</option>
                                <option value="+222">🇲🇷 +222</option>
                                <option value="+223">🇲🇱 +223</option>
                                <option value="+224">🇬🇳 +224</option>
                                <option value="+225">🇨🇮 +225</option>
                                <option value="+226">🇧🇫 +226</option>
                                <option value="+227">🇳🇪 +227</option>
                                <option value="+228">🇹🇬 +228</option>
                                <option value="+229">🇧🇯 +229</option>
                                <option value="+230">🇲🇺 +230</option>
                                <option value="+231">🇱🇷 +231</option>
                                <option value="+232">🇸🇱 +232</option>
                                <option value="+233">🇬🇭 +233</option>
                                <option value="+234">🇳🇬 +234</option>
                                <option value="+235">🇹🇩 +235</option>
                                <option value="+236">🇨🇫 +236</option>
                                <option value="+237">🇨🇲 +237</option>
                                <option value="+238">🇨🇻 +238</option>
                                <option value="+239">🇸🇹 +239</option>
                                <option value="+240">🇬🇶 +240</option>
                                <option value="+241">🇬🇦 +241</option>
                                <option value="+242">🇨🇬 +242</option>
                                <option value="+243">🇨🇩 +243</option>
                                <option value="+244">🇦🇴 +244</option>
                                <option value="+245">🇬🇼 +245</option>
                                <option value="+246">🇮🇴 +246</option>
                                <option value="+247">🇦🇨 +247</option>
                                <option value="+248">🇸🇨 +248</option>
                                <option value="+249">🇸🇩 +249</option>
                                <option value="+250">🇷🇼 +250</option>
                                <option value="+251">🇪🇹 +251</option>
                                <option value="+252">🇸🇴 +252</option>
                                <option value="+253">🇩🇯 +253</option>
                                <option value="+254">🇰🇪 +254</option>
                                <option value="+255">🇹🇿 +255</option>
                                <option value="+256">🇺🇬 +256</option>
                                <option value="+257">🇧🇮 +257</option>
                                <option value="+258">🇲🇿 +258</option>
                                <option value="+260">🇿🇲 +260</option>
                                <option value="+261">🇲🇬 +261</option>
                                <option value="+262">🇷🇪 +262</option>
                                <option value="+263">🇿🇼 +263</option>
                                <option value="+264">🇳🇦 +264</option>
                                <option value="+265">🇲🇼 +265</option>
                                <option value="+266">🇱🇸 +266</option>
                                <option value="+267">🇧🇼 +267</option>
                                <option value="+268">🇸🇿 +268</option>
                                <option value="+269">🇰🇲 +269</option>
                                <option value="+290">🇸🇭 +290</option>
                                <option value="+291">🇪🇷 +291</option>
                                <option value="+297">🇦🇼 +297</option>
                                <option value="+298">🇫🇴 +298</option>
                                <option value="+299">🇬🇱 +299</option>
                                <option value="+30">🇬🇷 +30</option>
                                <option value="+31">🇳🇱 +31</option>
                                <option value="+32">🇧🇪 +32</option>
                                <option value="+33">🇫🇷 +33</option>
                                <option value="+34">🇪🇸 +34</option>
                                <option value="+351">🇵🇹 +351</option>
                                <option value="+352">🇱🇺 +352</option>
                                <option value="+353">🇮🇪 +353</option>
                                <option value="+354">🇮🇸 +354</option>
                                <option value="+355">🇦🇱 +355</option>
                                <option value="+356">🇲🇹 +356</option>
                                <option value="+357">🇨🇾 +357</option>
                                <option value="+358">🇫🇮 +358</option>
                                <option value="+359">🇧🇬 +359</option>
                                <option value="+36">🇭🇺 +36</option>
                                <option value="+370">🇱🇹 +370</option>
                                <option value="+371">🇱🇻 +371</option>
                                <option value="+372">🇪🇪 +372</option>
                                <option value="+373">🇲🇩 +373</option>
                                <option value="+374">🇦🇲 +374</option>
                                <option value="+375">🇧🇾 +375</option>
                                <option value="+376">🇦🇩 +376</option>
                                <option value="+377">🇲🇨 +377</option>
                                <option value="+378">🇸🇲 +378</option>
                                <option value="+380">🇺🇦 +380</option>
                                <option value="+381">🇷🇸 +381</option>
                                <option value="+382">🇲🇪 +382</option>
                                <option value="+383">🇽🇰 +383</option>
                                <option value="+385">🇭🇷 +385</option>
                                <option value="+386">🇸🇮 +386</option>
                                <option value="+387">🇧🇦 +387</option>
                                <option value="+389">🇲🇰 +389</option>
                                <option value="+39">🇮🇹 +39</option>
                                <option value="+40">🇷🇴 +40</option>
                                <option value="+41">🇨🇭 +41</option>
                                <option value="+42">🇨🇿 +42</option>
                                <option value="+43">🇦🇹 +43</option>
                                <option value="+44">🇬🇧 +44</option>
                                <option value="+45">🇩🇰 +45</option>
                                <option value="+46">🇸🇪 +46</option>
                                <option value="+47">🇳🇴 +47</option>
                                <option value="+48">🇵🇱 +48</option>
                                <option value="+49">🇩🇪 +49</option>
                                <option value="+90">🇹🇷 +90</option>
                                <option value="+92">🇵🇰 +92</option>
                                <option value="+93">🇦🇫 +93</option>
                                <option value="+94">🇱🇰 +94</option>
                                <option value="+95">🇲🇲 +95</option>
                                <option value="+960">🇲🇻 +960</option>
                                <option value="+961">🇱🇧 +961</option>
                                <option value="+962">🇯🇴 +962</option>
                                <option value="+963">🇸🇾 +963</option>
                                <option value="+964">🇮🇶 +964</option>
                                <option value="+965">🇰🇼 +965</option>
                                <option value="+966">🇸🇦 +966</option>
                                <option value="+967">🇾🇪 +967</option>
                                <option value="+968">🇴🇲 +968</option>
                                <option value="+970">🇵🇸 +970</option>
                                <option value="+971">🇦🇪 +971</option>
                                <option value="+972">🇮🇱 +972</option>
                                <option value="+973">🇧🇭 +973</option>
                                <option value="+974">🇶🇦 +974</option>
                                <option value="+975">🇧🇹 +975</option>
                                <option value="+976">🇲🇳 +976</option>
                                <option value="+977">🇳🇵 +977</option>
                                <option value="+992">🇹🇯 +992</option>
                                <option value="+993">🇹🇲 +993</option>
                                <option value="+994">🇦🇿 +994</option>
                                <option value="+995">🇬🇪 +995</option>
                                <option value="+996">🇰🇬 +996</option>
                                <option value="+998">🇺🇿 +998</option>
                            </select>
                        </div>
                                                    <input type="tel" name="phone[${index}]" placeholder="Phone Number" class="phone-number-input" style="width: 140px;" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    `);
    validatePersonalPhoneNumbers();
}

/**
 * Function to validate that "Personal" phone numbers are unique
 */
function validatePersonalPhoneNumbers() {
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.getElementsByClassName('repeatable-section');
    const personalPhones = {};

    // Clear previous error messages
    Array.from(sections).forEach(section => {
        const errorSpan = section.querySelector('.text-danger');
        if (errorSpan) errorSpan.remove();
    });

    // Check for duplicate "Personal" phone numbers
    Array.from(sections).forEach((section, index) => {
        const type = section.querySelector('.contact-type-selector').value;
        const countryCode = section.querySelector('.country-code-input').value;
        const phone = section.querySelector('.phone-number-input').value;
        const fullPhone = countryCode + phone;

        if (type === 'Personal' && phone) {
            // Validate phone number first
            const validation = validatePhoneNumber(phone);
            if (!validation.valid) {
                const errorMessage = `<span class="text-danger">Personal phone number: ${validation.message}</span>`;
                section.querySelector('.content-grid').insertAdjacentHTML('afterend', errorMessage);
                // Disable the submit button
                const submitButton = document.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
                return;
            }

            // Skip duplicate check for placeholder numbers
            if (!validation.isPlaceholder && personalPhones[fullPhone]) {
                // Duplicate found
                const errorMessage = `<span class="text-danger">Personal phone number ${fullPhone} is already used in another entry.</span>`;
                section.querySelector('.content-grid').insertAdjacentHTML('afterend', errorMessage);
                // Disable the submit button
                const submitButton = document.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
            } else if (!validation.isPlaceholder) {
                personalPhones[fullPhone] = true;
            }
        }
    });

    // Re-enable the submit button if no duplicates are found
    if (!Object.keys(personalPhones).some(phone => personalPhones[phone] === true && Object.keys(personalPhones).filter(p => p === phone).length > 1)) {
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

/**
 * Add Email Address (Updated to exclude verification slider in repeatable section)
 */
function addEmailAddress() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('emailAddressesSummary');
    const editView = document.getElementById('emailAddressesEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('emailAddresses');
    }
    
    const container = document.getElementById('emailAddressesContainer');
    const index = container.children.length;
    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)"><i class="fa-solid fa-trash"></i></button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="email_type_hidden[${index}]" class="email-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Business">Business</option>
                        <option value="Mobile">Mobile</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Others">Others</option>
                        <option value="Partner">Partner</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email[${index}]" placeholder="Enter Email Address">
                </div>
            </div>
        </div>
    `);
    validatePersonalEmailTypes();
}

/**
 * Function to validate that there is at most one "Personal" type for emails
 */
function validatePersonalEmailTypes() {
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.getElementsByClassName('repeatable-section');
    let personalCount = 0;

    // Clear previous error messages
    Array.from(sections).forEach(section => {
        const errorSpan = section.querySelector('.text-danger-email-personal');
        if (errorSpan) errorSpan.remove();
    });

    // Count "Personal" types
    Array.from(sections).forEach((section, index) => {
        const type = section.querySelector('.email-type-selector').value;
        if (type === 'Personal') {
            personalCount++;
            if (personalCount > 1) {
                // Display error message
                const errorMessage = `<span class="text-danger text-danger-email-personal">Only one email address can be of type Personal.</span>`;
                section.querySelector('.form-group').insertAdjacentHTML('afterend', errorMessage);
            }
        }
    });

    // Enable or disable the submit button based on validation
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        if (personalCount > 1) {
            submitButton.disabled = true;
        } else {
            submitButton.disabled = false;
        }
    }

    return personalCount <= 1;
}


/**
 * Initialize Flatpickr datepickers for both empty and non-empty fields
 */
function initializeDatepickers() {
    if (typeof flatpickr === 'undefined') {
        console.warn('⚠️ Flatpickr not loaded, skipping datepicker initialization');
        return;
    }

    $('.date-picker').each(function() {
        const $this = $(this);
        const element = this;
        const currentValue = $this.val(); // Get the current value of the field
        const isPastOnly = $this.hasClass('date-picker-past-only');

        // Skip if already initialized
        if ($this.data('flatpickr')) {
            return;
        }

        // Initialize Flatpickr
        // Past-only fields (DOB, address, travel, employment, passport issue, visa grant): maxDate = today
        // Other fields (passport expiry, visa expiry): allow future
        const maxDateObj = isPastOnly ? 'today' : new Date(new Date().getFullYear() + 50, 11, 31);
        
        const fp = flatpickr(element, {
            dateFormat: 'd/m/Y',
            allowInput: true,
            clickOpens: true,
            defaultDate: currentValue || null,
            minDate: '01/01/1000',
            maxDate: maxDateObj,
            locale: {
                firstDayOfWeek: 1 // Monday
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Update the input value when date is selected
                $this.val(dateStr);
                $this.trigger('change'); // Trigger change event for any listeners
            }
        });

        // Store instance for later reference
        $this.data('flatpickr', fp);
    });
}




/**
 * Initialize Google Maps autocomplete for address inputs
 */
function initGoogleMaps() {
    const inputs = document.querySelectorAll('.address-input');
    inputs.forEach(input => {
        const autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            fields: ['formatted_address', 'address_components']
        });

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.formatted_address) {
                return;
            }

            input.value = place.formatted_address;

            const row = input.closest('.repeatable-section');
            if (row) {
                const postcodeInput = row.querySelector('.postcode-input, input[name*="zip"]');
                
                let postcode = '';
                if (place.address_components) {
                    place.address_components.forEach(component => {
                        if (component.types.includes('postal_code')) {
                            postcode = component.long_name;
                        }
                    });
                }
                
                if (postcodeInput && postcode) {
                    postcodeInput.value = postcode;
                }
            }
        });
    });
}


// ===== NEW SUMMARY/EDIT MODE FUNCTIONALITY =====

/**
 * Toggle edit mode for sections
 */
window.toggleEditMode = function(sectionType) {
    const summaryView = document.getElementById(sectionType + 'Summary');
    const editView = document.getElementById(sectionType + 'Edit');
    
    if (summaryView && editView) {
        // Hide summary view (support both inline styles and classes)
        summaryView.style.display = 'none';
        summaryView.classList.add('hidden');
        
        // Show edit view (support both inline styles and classes)
        editView.style.display = 'block';
        editView.classList.remove('hidden');
        
        // Section-specific initialization
        if (sectionType === 'addressInfo') {
            // Re-initialize datepickers when entering edit mode for address section
            setTimeout(function() {
                initializeDatepickers();
            }, 100);
        } else if (sectionType === 'emailAddresses') {
            // Start email verification polling when opening email section
            setTimeout(function() {
                initializeEmailSectionPolling();
            }, 100);
        }
    }
};

/**
 * Cancel edit mode and return to summary view
 */
window.cancelEdit = function(sectionType) {
    const summaryView = document.getElementById(sectionType + 'Summary');
    const editView = document.getElementById(sectionType + 'Edit');
    
    if (summaryView && editView) {
        // Hide edit view (support both inline styles and classes)
        editView.style.display = 'none';
        editView.classList.add('hidden');
        
        // Show summary view (support both inline styles and classes)
        summaryView.style.display = 'block';
        summaryView.classList.remove('hidden');
        
        // Section-specific cleanup
        if (sectionType === 'emailAddresses') {
            // Stop email verification polling when leaving email section
            stopAllEmailPolling();
            
            // Do a final refresh of email statuses
            setTimeout(function() {
                initializeEmailSectionPolling();
            }, 100);
        }
    }
};

/**
 * Save basic information and update summary
 */
/**
 * Generic function to save section data via AJAX
 */
window.saveSectionData = function(sectionName, formData, successCallback) {
    const form = document.getElementById('editCompanyForm') || document.getElementById('editClientForm') || document.getElementById('editLeadForm');
    if (!form) {
        showNotification('Form not found. Please refresh the page and try again.', 'error');
        return;
    }
    const clientId = form.querySelector('input[name="id"]')?.value;
    const type = form.querySelector('input[name="type"]')?.value;
    if (!clientId || !type) {
        showNotification('Invalid form data. Please refresh the page and try again.', 'error');
        return;
    }
    
    // Get CSRF token from meta tag or form
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                     || document.querySelector('input[name="_token"]')?.value 
                     || '';
    
    // Add section data to form data
    formData.append('_token', csrfToken);
    formData.append('id', clientId);
    formData.append('type', type);
    formData.append('section', sectionName);
    
    fetch(crmClientUrl('/clients/save-section'), {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Handle non-200 responses
        if (!response.ok) {
            return response.json().then(data => {
                throw { status: response.status, data: data };
            }).catch(error => {
                if (error.status) throw error;
                throw { status: response.status, data: { message: 'Server error occurred' } };
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            successCallback(data);  // Pass the response data to the callback
            showNotification(data.message || `${sectionName} updated successfully!`, 'success');
        } else {
            showNotification(data.message || `Error updating ${sectionName}`, 'error');
            if (data.errors) {
                displaySectionErrors(sectionName, data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Handle validation errors (422 status)
        if (error.status === 422 && error.data && error.data.errors) {
            displaySectionErrors(sectionName, error.data.errors);
            showNotification('Please fix the validation errors', 'error');
        } else {
            const message = error.data?.message || `Error updating ${sectionName}. Please try again.`;
            showNotification(message, 'error');
        }
    });
};

/**
 * Display errors for a specific section
 */
window.displaySectionErrors = function(sectionName, errors) {
    const editView = document.getElementById(sectionName + 'Edit');
    if (!editView) return;
    
    // Clear previous errors
    editView.querySelectorAll('.field-error').forEach(error => error.remove());
    
    // Display new errors
    Object.keys(errors).forEach(fieldName => {
        const field = editView.querySelector(`[name*="${fieldName}"]`);
        if (field) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error text-danger';
            errorDiv.textContent = errors[fieldName][0];
            field.parentNode.appendChild(errorDiv);
        }
    });
};

window.saveBasicInfo = function() {
    // Validate required fields
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const clientId = document.getElementById('clientId').value.trim();
    
    if (!firstName || !lastName || !clientId) {
        showNotification('Please fill in all required fields (First Name, Last Name, Client ID)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('first_name', firstName);
    formData.append('last_name', lastName);
    formData.append('client_id', clientId);
    formData.append('dob', document.getElementById('dob').value);
    formData.append('age', document.getElementById('age').value);
    formData.append('gender', document.getElementById('gender').value);
    formData.append('marital_status', document.getElementById('maritalStatus').value);

    const leadStageEl = document.getElementById('lead_pipeline_status_edit');
    if (leadStageEl) {
        formData.append('lead_status', leadStageEl.value);
        if (leadStageEl.value === 'follow_up') {
            const fuEl = document.getElementById('lead_followup_date_edit');
            if (fuEl) {
                formData.append('followup_date', fuEl.value || '');
            }
        }
        const asEl = document.getElementById('assigned_staff_id_edit');
        if (asEl) {
            formData.append('assigned_staff_id', asEl.value);
        }
    }
    
    saveSectionData('basicInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('basicInfoSummary');
        const summaryGrid = summaryView.querySelector('.summary-grid');
        
        summaryGrid.innerHTML = `
            <div class="summary-item">
                <span class="summary-label">Name:</span>
                <span class="summary-value">${firstName} ${lastName}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Client ID:</span>
                <span class="summary-value">${clientId}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Date of Birth:</span>
                <span class="summary-value">${document.getElementById('dob').value || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Age:</span>
                <span class="summary-value">${document.getElementById('age').value || 'Not calculated'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Gender:</span>
                <span class="summary-value">${document.getElementById('gender').value || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Marital Status:</span>
                <span class="summary-value">${document.getElementById('maritalStatus').value || 'Not set'}</span>
            </div>
        `;

        if (leadStageEl) {
            const stageText = leadStageEl.options[leadStageEl.selectedIndex]?.text || leadStageEl.value;
            let extra = `
            <div class="summary-item">
                <span class="summary-label">Lead stage:</span>
                <span class="summary-value">${stageText}</span>
            </div>`;
            const fuEl = document.getElementById('lead_followup_date_edit');
            let fuDisplay = '—';
            if (leadStageEl.value === 'follow_up' && fuEl && fuEl.value) {
                const p = fuEl.value.split('-');
                if (p.length === 3) {
                    fuDisplay = `${p[2]}/${p[1]}/${p[0]}`;
                }
            }
            extra += `
            <div class="summary-item">
                <span class="summary-label">Follow-up date:</span>
                <span class="summary-value">${fuDisplay}</span>
            </div>`;
            const asEl = document.getElementById('assigned_staff_id_edit');
            const asText = asEl ? (asEl.options[asEl.selectedIndex]?.text || '') : '';
            extra += `
            <div class="summary-item">
                <span class="summary-label">Assigned to:</span>
                <span class="summary-value">${asText || '—'}</span>
            </div>`;
            summaryGrid.innerHTML += extra;
        }
        
        // Return to summary view
        cancelEdit('basicInfo');
    });
};

/**
 * Save phone numbers and update summary
 */
window.savePhoneNumbers = function() {
    // Get all phone number entries
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const phoneNumbers = [];
    
    sections.forEach((section, index) => {
        const type = section.querySelector('.contact-type-selector').value;
        const countryCode = section.querySelector('.country-code-input').value;
        const phone = section.querySelector('.phone-number-input').value;
        const contactId = section.querySelector('input[name*="contact_id"]')?.value;
        
        if (type && phone) {
            phoneNumbers.push({
                id: contactId || '',
                contact_type: type,
                country_code: countryCode,
                phone: phone
            });
        }
    });
    
    const formData = new FormData();
    formData.append('phone_numbers', JSON.stringify(phoneNumbers));
    
    saveSectionData('phoneNumbers', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('phoneNumbersSummary');
        const contactList = ensureContactRowList(summaryView);

        if (phoneNumbers.length > 0 && contactList) {
            contactList.innerHTML = phoneNumbers.map((phone) => {
                // Check if it's a placeholder number
                const isPlaceholder = isPlaceholderNumber(phone.phone);
                
                // Show verify button only for saved Australian numbers (not placeholders, not unsaved)
                let verificationButton = '';
                if (phone.country_code === '+61' && !isPlaceholder) {
                    if (phone.id && phone.id !== 'pending') {
                        verificationButton = `<button type="button" class="btn-verify-phone" onclick="sendOTP('${phone.id}', '${phone.phone}', '${phone.country_code}')" data-contact-id="${phone.id}">
                            <i class="fa-solid fa-lock"></i> Verify
                         </button>`;
                    } else {
                        verificationButton = `<span class="text-muted" style="font-size: 12px;" title="Save the phone first to enable verification">
                            <i class="fa-solid fa-circle-info"></i> Save to verify
                         </span>`;
                    }
                }
                
                return `
                    <div class="summary-item contact-row">
                        <span class="summary-label">${phone.contact_type}:</span>
                        <span class="summary-value">${phone.country_code}${phone.phone}</span>
                        ${verificationButton}
                    </div>
                `;
            }).join('');
        } else if (summaryView) {
            summaryView.innerHTML = '<div class="empty-state"><p>No phone numbers added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('phoneNumbers');
        
        // Refresh the page to get updated verification status from server
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
};

/**
 * Save email addresses and update summary
 */
window.saveEmailAddresses = function() {
    // Get all email entries
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const emails = [];
    
    sections.forEach(section => {
        const type = section.querySelector('.email-type-selector').value;
        const email = section.querySelector('input[type="email"]').value;
        const emailId = section.querySelector('input[name*="email_id"]')?.value;
        
        if (type && email) {
            emails.push({
                email_id: emailId || '',
                email_type: type,
                email: email
            });
        }
    });
    
    const formData = new FormData();
    formData.append('emails', JSON.stringify(emails));
    
    saveSectionData('emailAddresses', formData, function(response) {
        // Use the email IDs returned from the server (not the old ones)
        const savedEmails = response && response.emails ? response.emails : [];
        
        // Update summary view on success
        const summaryView = document.getElementById('emailAddressesSummary');
        const contactList = ensureContactRowList(summaryView);
        
        if (savedEmails.length > 0 && contactList) {
            contactList.innerHTML = savedEmails.map((email) => {
                // Show verify button only for saved emails with valid ID
                let verificationButton = '';
                if (email.is_verified) {
                    verificationButton = `<span class="verified-badge">
                        <i class="fa-solid fa-circle-check"></i> Verified
                     </span>`;
                } else if (email.id && email.id > 0) {
                    // Use email.id from server response (guaranteed to be correct)
                    verificationButton = `<button type="button" class="btn-verify-email" onclick="sendEmailVerification(${email.id}, '${email.email}')" data-email-id="${email.id}">
                        <i class="fa-solid fa-lock"></i> Verify
                     </button>`;
                } else {
                    verificationButton = `<span class="text-muted" style="font-size: 12px;" title="Save the email first to enable verification">
                        <i class="fa-solid fa-circle-info"></i> Save to verify
                     </span>`;
                }
                
                return `
                    <div class="summary-item contact-row">
                        <span class="summary-label">${email.email_type}:</span>
                        <span class="summary-value">${email.email}</span>
                        ${verificationButton}
                    </div>
                `;
            }).join('');
        } else if (summaryView) {
            summaryView.innerHTML = '<div class="empty-state"><p>No email addresses added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('emailAddresses');
        
        // Start polling for newly saved unverified emails
        setTimeout(() => {
            const newEmailVerifyButtons = document.querySelectorAll('.btn-verify-email');
            newEmailVerifyButtons.forEach(button => {
                const emailId = button.getAttribute('data-email-id');
                
                // Validate email ID before starting polling
                if (isValidEmailId(emailId)) {
                    startEmailVerificationPolling(parseInt(emailId));
                }
            });
        }, 1000);
    });
};

/**
 * Edit individual phone number
 */
window.editPhoneNumber = function(index) {
    // Switch to edit mode
    toggleEditMode('phoneNumbers');
    
    // Focus on the specific phone number field
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    if (sections[index]) {
        const phoneInput = sections[index].querySelector('.phone-number-input');
        if (phoneInput) {
            phoneInput.focus();
        }
    }
};

/**
 * Edit individual email address
 */
window.editEmailAddress = function(index) {
    // Switch to edit mode
    toggleEditMode('emailAddresses');
    
    // Focus on the specific email field
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    if (sections[index]) {
        const emailInput = sections[index].querySelector('input[type="email"]');
        if (emailInput) {
            emailInput.focus();
        }
    }
};

/**
 * Remove phone number
 */
window.removePhoneNumber = function(id, index) {
    if (confirm('Are you sure you want to remove this phone number?')) {
        if (id) {
            // Mark for deletion in database
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_contact_ids[]';
            hiddenInput.value = id;
            document.getElementById('editClientForm').appendChild(hiddenInput);
        }
        
        // Remove from DOM
        const container = document.getElementById('phoneNumbersContainer');
        const sections = container.querySelectorAll('.repeatable-section');
        if (sections[index]) {
            sections[index].remove();
        }
        
        // Update summary
        savePhoneNumbers();
    }
};

/**
 * Remove email address
 */
window.removeEmailAddress = function(id, index) {
    if (confirm('Are you sure you want to remove this email address?')) {
        if (id) {
            // Mark for deletion in database
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_email_ids[]';
            hiddenInput.value = id;
            document.getElementById('editClientForm').appendChild(hiddenInput);
        }
        
        // Remove from DOM
        const container = document.getElementById('emailAddressesContainer');
        const sections = container.querySelectorAll('.repeatable-section');
        if (sections[index]) {
            sections[index].remove();
        }
        
        // Update summary
        saveEmailAddresses();
    }
};



/**
 * Save address information and update summary
 */
window.saveAddressInfo = function() {
    
    const $addressesContainer = $('#addresses-container');
    if (!$addressesContainer.length) {
        console.error('❌ #addresses-container not found!');
        alert('Error: Address container not found. Please refresh the page and try again.');
        return;
    }
    
    const $addressEntries = $addressesContainer.find('.address-entry-wrapper').first();
    
    if (!$addressEntries.length) {
        console.error('❌ No current address form found!');
        alert('Error: Address form not found. Please refresh the page and try again.');
        return;
    }
    
    // Validation: Only require country and suburb
    let validationErrors = [];
    let hasAtLeastOneValidAddress = false;
    
    const $entry = $addressEntries;
    const addressLine1 = $.trim($entry.find('input[name="address_line_1[]"]').val() || '');
    const suburb = $.trim($entry.find('input[name="suburb[]"]').val() || '');
    const state = $.trim($entry.find('input[name="state[]"]').val() || '');
    const zip = $.trim($entry.find('input[name="zip[]"]').val() || '');
    const country = $.trim($entry.find('input[name="country[]"]').val() || '');

    const hasAnyData = addressLine1 || suburb || state || zip || country;

    if (hasAnyData) {
        const missingFields = [];
        if (!suburb) missingFields.push('Suburb');
        if (!country) missingFields.push('Country');
        if (country && country.toLowerCase().includes('australia') && !zip) {
            missingFields.push('Postcode');
        }

        if (missingFields.length > 0) {
            validationErrors.push(`Current address is incomplete. Missing: ${missingFields.join(', ')}`);
        } else {
            hasAtLeastOneValidAddress = true;
        }
    }
    
    // Show validation errors
    if (validationErrors.length > 0) {
        console.error('❌ Validation failed:', validationErrors);
        alert('Please fix the following errors:\n\n' + validationErrors.join('\n'));
        return;
    }
    
    // Check if we have at least one valid address
    if (!hasAtLeastOneValidAddress) {
        console.error('❌ No valid addresses found');
        alert('Please enter suburb and country for the current address before saving.');
        return;
    }
    
    
    const formData = new FormData();
    const addressId = $entry.find('input[name="address_id[]"]').val();
    const addressLine2 = $entry.find('input[name="address_line_2[]"]').val();
    const regionalCode = $entry.find('input[name="regional_code[]"]').val();

    formData.append('address_id[]', addressId || '');
    formData.append('address_line_1[]', addressLine1 || '');
    formData.append('address_line_2[]', addressLine2 || '');
    formData.append('suburb[]', suburb || '');
    formData.append('state[]', state || '');
    formData.append('country[]', country || 'Australia');
    formData.append('zip[]', zip || '');
    formData.append('regional_code[]', regionalCode || '');
    formData.append('address_start_date[]', '');
    formData.append('address_end_date[]', '');
    
    // Check if saveSectionData exists
    if (typeof saveSectionData !== 'function') {
        console.error('❌ saveSectionData function not found!');
        alert('Error: Save function not available. Please refresh the page and try again.');
        return;
    }
    
    
    saveSectionData('addressInfo', formData, function() {
        window.location.reload();
    });
    
};

/**
 * Delete the current address with confirmation
 */
window.deleteCurrentAddress = function() {
    const $entry = $('#addresses-container .address-entry-wrapper').first();
    const addressId = $entry.find('input[name="address_id[]"]').val();

    if (!addressId) {
        showNotification('No address found to delete.', 'error');
        return;
    }

    if (!confirm('Are you sure you want to delete the current address? This action cannot be undone.')) {
        return;
    }

    if (typeof saveSectionData !== 'function') {
        showNotification('Error: Save function not available. Please refresh the page and try again.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('delete_address', '1');
    formData.append('address_id', addressId);

    saveSectionData('addressInfo', formData, function() {
        window.location.reload();
    });
};

function updateAddressSummary($entries) {
    const summaryView = document.getElementById('addressInfoSummary');
    if (!summaryView) {
        return;
    }

    const $entry = ($entries && typeof $entries.first === 'function')
        ? $entries.first()
        : $('#addresses-container .address-entry-wrapper').first();

    if (!$entry.length) {
        summaryView.innerHTML = '<div class="empty-state"><p>No current address on file.</p></div>';
        return;
    }

    const addressLine1 = $entry.find('input[name="address_line_1[]"]').val();
    const addressLine2 = $entry.find('input[name="address_line_2[]"]').val();
    const suburb = $entry.find('input[name="suburb[]"]').val();
    const state = $entry.find('input[name="state[]"]').val();
    const zip = $entry.find('input[name="zip[]"]').val();
    const country = $entry.find('input[name="country[]"]').val();
    const regionalCode = $entry.find('input[name="regional_code[]"]').val();

    const fullAddress = [addressLine1, addressLine2, suburb, state, zip, country]
        .filter(Boolean).join(', ');

    summaryView.innerHTML = `
        <div>
            <div class="address-entry-compact">
                <div class="address-compact-grid">
                    <div class="summary-item-inline">
                        <span class="summary-label">ADDRESS:</span>
                        <span class="summary-value">${fullAddress || 'Not set'}</span>
                    </div>
                    ${regionalCode ? `<div class="summary-item-inline">
                        <span class="summary-label">REGIONAL CODE:</span>
                        <span class="summary-value strong">${regionalCode}</span>
                    </div>` : ''}
                </div>
            </div>
        </div>
    `;
}






// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAdditionalInfo();
});


/**
 * Save "Refer by" (Additional Information tab)
 */
window.saveReferByInfo = function () {
    const input = document.getElementById('client_refer_by');
    if (!input) {
        return;
    }
    const msg = document.getElementById('referBySaveMsg');
    if (msg) {
        msg.textContent = '';
    }
    const formData = new FormData();
    formData.append('refer_by', input.value || '');
    saveSectionData('referBy', formData, function () {
        if (msg) {
            msg.textContent = 'Saved.';
            window.setTimeout(function () {
                msg.textContent = '';
            }, 3000);
        }
    });
};





/**
 * Remove phone field with confirmation
 */
window.removePhoneField = function(button) {
    if (confirm('Are you sure you want to remove this phone number?')) {
        button.closest('.repeatable-section').remove();
        validatePersonalPhoneNumbers();
    }
};

/**
 * Remove email field with confirmation
 */
window.removeEmailField = function(button) {
    if (confirm('Are you sure you want to remove this email address?')) {
        button.closest('.repeatable-section').remove();
        validatePersonalEmailTypes();
    }
};



/**
 * Remove address field with confirmation
 */
window.removeAddressField = function(button) {
    if (confirm('Are you sure you want to remove this address?')) {
        button.closest('.repeatable-section').remove();
    }
};





/**
 * Show notification message
 */
function showNotification(message, type = 'info') {
    // Determine icon based on notification type
    let icon = 'circle-info';
    if (type === 'success') {
        icon = 'circle-check';
    } else if (type === 'error') {
        icon = 'circle-exclamation';
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fa-solid fa-${icon}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds for errors, 3 seconds for others
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// Make functions globally available
// Global function assignments
window.initGoogleMaps = initGoogleMaps;




window.addAddress = addAddress;
window.addAnotherAddress = addAnotherAddress;
window.removeAddressEntry = removeAddressEntry;

window.calculateAge = calculateAge;
window.addPhoneNumber = addPhoneNumber;
window.validatePersonalPhoneNumbers = validatePersonalPhoneNumbers;
window.addEmailAddress = addEmailAddress;
window.validatePersonalEmailTypes = validatePersonalEmailTypes;
window.initializeDatepickers = initializeDatepickers;

// New scroll and modal functions
window.scrollToSection = scrollToSection;
window.toggleSidebar = toggleSidebar;
window.scrollToTop = scrollToTop;

// ===== DOCUMENT READY =====
$(document).ready(function() {
    // Call initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTabs);
    } else {
        initializeTabs();
    }
    
    // Initialize datepickers on page load
    initializeDatepickers();
    
    // Initialize Go to Top button
    initGoToTopButton();

    // Initialize age on page load and set up datepicker for DOB
    const dobInput = document.getElementById('dob');
    const ageInput = document.getElementById('age');

    if (dobInput && ageInput) {
        // Initialize age if DOB exists
        if (dobInput.value) {
            ageInput.value = calculateAge(dobInput.value);
        }

        // Function to update age
        const updateAge = function() {
            const dobValue = dobInput.value;
            ageInput.value = calculateAge(dobValue);
        };

        // Handle manual input changes (e.g., typing or pasting)
        dobInput.addEventListener('input', updateAge);

        // Initialize Flatpickr for DOB field with age calculation
        if (typeof flatpickr !== 'undefined') {
            // Check if already initialized
            if (!$(dobInput).data('flatpickr')) {
                flatpickr(dobInput, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: dobInput.value || null,
                    maxDate: 'today', // DOB cannot be in the future
                    minDate: '01/01/1000',
                    locale: {
                        firstDayOfWeek: 1 // Monday
                    },
                    onChange: function(selectedDates, dateStr, instance) {
                        // Update the input value and calculate age when a date is selected
                        dobInput.value = dateStr;
                        updateAge();
                    }
                });
            }
        } else {
            console.warn('⚠️ Flatpickr not loaded for DOB field');
        }
        
        // Fallback for any direct changes
        $(dobInput).on('change', updateAge);
    }


// Add event listeners for real-time validation and form submission
    const phoneNumbersContainer = document.getElementById('phoneNumbersContainer');
    if (phoneNumbersContainer) {
        // Validate on change of type
        phoneNumbersContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('contact-type-selector')) {
                validatePersonalPhoneNumbers();
            }
        });

        // Validate on form submission
        const editClientForm = document.getElementById('editClientForm');
        if (editClientForm) {
            editClientForm.addEventListener('submit', function(e) {
                validatePersonalPhoneNumbers();
            });
        }

        // Initial validation on page load
        validatePersonalPhoneNumbers();
    }

    // Phone Verification Functions
    let currentContactId = null;
    let otpTimer = null;
    let resendTimer = null;
    let otpExpiryTime = null;

    /**
     * Send OTP to phone number
     */
    window.sendOTP = function(contactId, phone, countryCode) {
        // Validate contact ID
        if (!contactId || contactId === 'pending') {
            alert('Please save the phone number first before verifying');
            return;
        }
        
        currentContactId = contactId;
        const fullPhone = countryCode + phone;
        
        // Show modal
        document.getElementById('otpPhoneDisplay').textContent = fullPhone;
        document.getElementById('otpVerificationModal').style.display = 'block';
        
        // Clear any previous messages
        hideOTPMessages();
        
        // Clear OTP inputs
        clearOTPInputs();
        
        // Disable verify button initially
        document.getElementById('verifyOTPBtn').disabled = true;
        
        // Focus first input
        setTimeout(() => {
            document.querySelector('.otp-digit[data-index="0"]').focus();
        }, 100);
        
        // Send OTP request
        fetch(crmClientUrl('/clients/phone/send-otp'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('Verification code sent to client! Please ask them to provide the code.');
                startOTPTimer(data.expires_in_seconds || 300);
                startResendTimer(30);
            } else {
                showOTPErrorMessage(data.message || 'Failed to send verification code');
            }
        })
        .catch(error => {
            console.error('Error sending OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
        });
    }

    /**
     * Verify OTP
     */
    window.verifyOTP = function() {
        const otpCode = getOTPCode();
        
        if (otpCode.length !== 6) {
            showOTPErrorMessage('Please enter all 6 digits');
            return;
        }
        
        // Disable verify button
        document.getElementById('verifyOTPBtn').disabled = true;
        
        fetch(crmClientUrl('/clients/phone/verify-otp'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: currentContactId,
                otp_code: otpCode
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('Phone number verified successfully!');
                
                // Update UI after a short delay
                setTimeout(() => {
                    updateVerificationStatus(currentContactId, true);
                    closeOTPModal();
                }, 1500);
            } else {
                showOTPErrorMessage(data.message || 'Invalid verification code');
                document.getElementById('verifyOTPBtn').disabled = false;
                
                // Clear OTP inputs on error
                if (data.message && data.message.includes('Invalid')) {
                    clearOTPInputs();
                    document.querySelector('.otp-digit[data-index="0"]').focus();
                }
            }
        })
        .catch(error => {
            console.error('Error verifying OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
            document.getElementById('verifyOTPBtn').disabled = false;
        });
    }

    /**
     * Resend OTP
     */
    window.resendOTP = function() {
        if (!currentContactId) return;
        
        // Disable resend button temporarily
        document.getElementById('resendOTPBtn').disabled = true;
        
        fetch(crmClientUrl('/clients/phone/resend-otp'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: currentContactId
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('New verification code sent to client! Please ask them for the updated code.');
                clearOTPInputs();
                startOTPTimer(data.expires_in_seconds || 300);
                startResendTimer(30);
            } else {
                showOTPErrorMessage(data.message || 'Failed to resend verification code');
                document.getElementById('resendOTPBtn').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error resending OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
            document.getElementById('resendOTPBtn').disabled = false;
        });
    }

    /**
     * Close OTP modal
     */
    window.closeOTPModal = function() {
        document.getElementById('otpVerificationModal').style.display = 'none';
        currentContactId = null;
        clearOTPTimers();
        clearOTPInputs();
        hideOTPMessages();
    }

    /**
     * Get OTP code from inputs
     */
    function getOTPCode() {
        let otpCode = '';
        for (let i = 0; i < 6; i++) {
            const digit = document.querySelector(`.otp-digit[data-index="${i}"]`)?.value || '';
            otpCode += digit;
        }
        return otpCode;
    }
    
    /**
     * Check if OTP is complete and enable/disable verify button
     */
    function checkOTPComplete() {
        const otpCode = getOTPCode();
        const verifyBtn = document.getElementById('verifyOTPBtn');
        if (verifyBtn) {
            verifyBtn.disabled = otpCode.length !== 6;
        }
    }

    /**
     * Clear OTP inputs
     */
    function clearOTPInputs() {
        for (let i = 0; i < 6; i++) {
            const input = document.querySelector(`.otp-digit[data-index="${i}"]`);
            input.value = '';
            input.classList.remove('filled');
        }
    }

    /**
     * Start OTP expiry timer
     */
    function startOTPTimer(seconds) {
        clearOTPTimers();
        
        let timeLeft = seconds;
        const timerElement = document.getElementById('timerCountdown');
        
        otpTimer = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;
            timerElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(otpTimer);
                showOTPErrorMessage('Verification code has expired');
                document.getElementById('verifyOTPBtn').disabled = true;
            }
            
            timeLeft--;
        }, 1000);
    }

    /**
     * Start resend timer
     */
    function startResendTimer(seconds) {
        let timeLeft = seconds;
        const resendBtn = document.getElementById('resendOTPBtn');
        const resendTimerDisplay = document.getElementById('resendTimer');
        const countdownElement = document.getElementById('resendCountdown');
        
        resendBtn.disabled = true;
        resendTimerDisplay.style.display = 'inline';
        
        resendTimer = setInterval(() => {
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(resendTimer);
                resendBtn.disabled = false;
                resendTimerDisplay.style.display = 'none';
            }
            
            timeLeft--;
        }, 1000);
    }

    /**
     * Clear OTP timers
     */
    function clearOTPTimers() {
        if (otpTimer) {
            clearInterval(otpTimer);
            otpTimer = null;
        }
        if (resendTimer) {
            clearInterval(resendTimer);
            resendTimer = null;
        }
    }

    /**
     * Show OTP error message
     */
    function showOTPErrorMessage(message) {
        const errorElement = document.getElementById('otpErrorMessage');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        document.getElementById('otpSuccessMessage').style.display = 'none';
    }

    /**
     * Show OTP success message
     */
    function showOTPSuccessMessage(message) {
        const successElement = document.getElementById('otpSuccessMessage');
        successElement.textContent = message;
        successElement.style.display = 'block';
        document.getElementById('otpErrorMessage').style.display = 'none';
    }

    /**
     * Hide OTP messages
     */
    function hideOTPMessages() {
        document.getElementById('otpErrorMessage').style.display = 'none';
        document.getElementById('otpSuccessMessage').style.display = 'none';
    }

    /**
     * Update verification status in UI
     */
    function updateVerificationStatus(contactId, isVerified) {
        const verifyBtn = document.querySelector(`button[data-contact-id="${contactId}"]`);
        if (verifyBtn) {
            if (isVerified) {
                const summaryItem = verifyBtn.closest('.summary-item');
                if (summaryItem) {
                    const verifiedBadge = document.createElement('span');
                    verifiedBadge.className = 'verified-badge';
                    verifiedBadge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Verified';
                    verifiedBadge.title = 'Verified on ' + new Date().toLocaleString();
                    
                    verifyBtn.replaceWith(verifiedBadge);
                }
            }
        }
    }

    // OTP Input Event Listeners
    // Run immediately if DOM already loaded, otherwise wait for DOMContentLoaded
    function initializeOTPListeners() {
        
        // Handle OTP input auto-focus and validation
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('otp-digit')) {
                const index = parseInt(e.target.dataset.index);
                let value = e.target.value;
                
                // Allow only digits
                value = value.replace(/[^0-9]/g, '');
                e.target.value = value;
                
                // Add filled class for styling
                if (value) {
                    e.target.classList.add('filled');
                    
                    // Auto-focus next input
                    if (index < 5) {
                        const nextInput = document.querySelector(`.otp-digit[data-index="${index + 1}"]`);
                        if (nextInput) {
                            nextInput.focus();
                        }
                    }
                } else {
                    e.target.classList.remove('filled');
                }
                
                // Enable/disable verify button based on completion
                checkOTPComplete();
            }
        });
        
        // Handle backspace navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('otp-digit') && e.key === 'Backspace') {
                const index = parseInt(e.target.dataset.index);
                
                if (!e.target.value && index > 0) {
                    // Move to previous input if current is empty
                    const prevInput = document.querySelector(`.otp-digit[data-index="${index - 1}"]`);
                    if (prevInput) {
                        prevInput.focus();
                    }
                }
            }
        });
        
        // Handle paste event
        document.addEventListener('paste', function(e) {
            if (e.target.classList.contains('otp-digit')) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                
                if (pastedData.length > 0) {
                    const startIndex = parseInt(e.target.dataset.index);
                    for (let i = 0; i < pastedData.length && (startIndex + i) < 6; i++) {
                        const input = document.querySelector(`.otp-digit[data-index="${startIndex + i}"]`);
                        if (input) {
                            input.value = pastedData[i];
                            input.classList.add('filled');
                        }
                    }
                    checkOTPComplete();
                    
                    // Focus last filled input or first empty one
                    const lastIndex = Math.min(startIndex + pastedData.length - 1, 5);
                    const nextEmptyIndex = Math.min(startIndex + pastedData.length, 5);
                    const focusInput = document.querySelector(`.otp-digit[data-index="${nextEmptyIndex}"]`);
                    if (focusInput) {
                        focusInput.focus();
                    }
                }
            }
        });
        
        // Handle Enter key to submit
        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('otp-digit') && e.key === 'Enter') {
                const otpCode = getOTPCode();
                if (otpCode.length === 6) {
                    verifyOTP();
                }
            }
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('otpVerificationModal').style.display === 'block') {
                closeOTPModal();
            }
        });
    }
    
    // Initialize OTP listeners immediately or on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOTPListeners);
    } else {
        // DOM already loaded, run immediately
        initializeOTPListeners();
    }

    // Add event listeners for real-time validation and form submission (emails)
    const emailAddressesContainer = document.getElementById('emailAddressesContainer');
    if (emailAddressesContainer) {
        // Validate on change of type
        emailAddressesContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('email-type-selector')) {
                validatePersonalEmailTypes();
            }
        });

        // Validate on form submission
        document.getElementById('editClientForm').addEventListener('submit', function(e) {
            if (!validatePersonalEmailTypes()) {
                e.preventDefault();
                alert('Only one email address can be of type Personal. Please correct the entries.');
            }
        });

        // Initial validation on page load
        validatePersonalEmailTypes();
    }

    // Handle qualification removal
    const qualificationsContainer = document.getElementById('qualificationsContainer');
    if (qualificationsContainer) {
        qualificationsContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const qualificationIdInput = section.querySelector('input[name^="qualification_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this qualification?');

                if (confirmDelete) {
                    if (qualificationIdInput) {
                        const qualificationId = qualificationIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_qualification_ids[]';
                        hiddenInput.value = qualificationId;
                        document.getElementById('editClientForm').appendChild(hiddenInput);
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle experience removal
    const experienceContainer = document.getElementById('experienceContainer');
    if (experienceContainer) {
        experienceContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const experienceIdInput = section.querySelector('input[name^="experience_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this experience?');

                if (confirmDelete) {
                    if (experienceIdInput) {
                        const experienceId = experienceIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_experience_ids[]';
                        hiddenInput.value = experienceId;
                        document.getElementById('editClientForm').appendChild(hiddenInput);
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle occupation removal
    const occupationContainer = document.getElementById('occupationContainer');
    if (occupationContainer) {
        occupationContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const occupationIdInput = section.querySelector('input[name^="occupation_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this occupation?');

                if (confirmDelete) {
                    if (occupationIdInput) {
                        const occupationId = occupationIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_occupation_ids[]';
                        hiddenInput.value = occupationId;
                        document.getElementById('editClientForm').appendChild(hiddenInput);
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle test score removal
    const testScoresContainer = document.getElementById('testScoresContainer');
    if (testScoresContainer) {
        testScoresContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const testScoreIdInput = section.querySelector('input[name^="test_score_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this test score?');

                if (confirmDelete) {
                    if (testScoreIdInput) {
                        const testScoreId = testScoreIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_test_score_ids[]';
                        hiddenInput.value = testScoreId;
                        document.getElementById('editClientForm').appendChild(hiddenInput);
                    }
                    section.remove();
                }
            }
        });
    }

    // One-time check of email verification status on page load
    // (Does NOT start continuous polling - polling starts when email section is opened)
    setTimeout(function() {
        const emailVerifyButtons = document.querySelectorAll('.btn-verify-email');
        if (emailVerifyButtons.length > 0) {
            emailVerifyButtons.forEach(button => {
                const emailId = button.getAttribute('data-email-id');
                if (isValidEmailId(emailId)) {
                    checkEmailVerificationStatus(parseInt(emailId));
                }
            });
        }
    }, 1000); // Wait 1 second after page load
});

/**
 * Leave client/company edit and open the unified CRM detail page (same encoded id).
 *
 * - Uses location.pathname only so ?query and #hash never corrupt the id (unlike splitting href).
 * - Anchors on /clients/edit/{id} so a stray "edit" earlier in the path cannot steal the segment.
 * - Works with subdirectory installs (e.g. /app/public/clients/edit/…).
 * - Falls back to history.back() when the path does not match or on error.
 */
window.goBackWithRefresh = function() {
    try {
        var pathname = window.location.pathname || '';
        var match = /\/clients\/edit\/([^/]+)\/?$/.exec(pathname);
        if (!match || !match[1]) {
            window.history.back();
            return;
        }
        var clientId = match[1];

        var typeInput = document.querySelector('#editCompanyForm input[name="type"]') || document.querySelector('#editClientForm input[name="type"]');
        var clientType = (typeInput ? typeInput.value : window.currentClientType || '').toLowerCase();
        var detailPath = '/clients/detail/' + clientId;

        // Matter ref in URL matches ClientsController@detail expectations for individuals (not leads).
        if (clientType === 'client') {
            var latestMatterRef = window.latestClientMatterRef || null;
            if (latestMatterRef) {
                detailPath += '/' + encodeURIComponent(latestMatterRef);
            }
        }

        window.location.href = crmClientUrl(detailPath);
    } catch (e) {
        window.history.back();
    }
};

/**
 * Email Verification Functions
 */

// Send email verification
window.sendEmailVerification = function(emailId, emailAddress) {
    // Validate email ID
    if (!emailId || emailId === 'pending' || !emailAddress) {
        alert('Please save the email first before verifying');
        return;
    }

    if (!confirm(`Send verification email to ${emailAddress}?`)) {
        return;
    }

    // Show loading state
    const button = document.querySelector(`button[data-email-id="${emailId}"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    button.disabled = true;

    fetch(crmClientUrl('/clients/email/send-verification'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email_id: emailId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Verification email sent successfully! Please ask the client to check their email and click the verification link.');
            
            // Update button to show resend option with polling indicator
            button.innerHTML = '<i class="fa-solid fa-arrow-rotate-right"></i> Resend <i class="fa-solid fa-spinner fa-spin" style="margin-left: 5px; font-size: 10px;"></i>';
            button.onclick = function() { resendEmailVerification(emailId, emailAddress); };
            
            // Start polling for verification status
            startEmailVerificationPolling(emailId);
        } else {
            alert('Error: ' + (data.message || 'Failed to send verification email'));
            button.innerHTML = originalContent;
        }
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error sending verification email:', error);
        alert('Network error. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
};

// Resend email verification
function resendEmailVerification(emailId, emailAddress) {
    // Validate email ID
    if (!emailId || emailId === 'pending') {
        alert('Please save the email first before verifying');
        return;
    }
    
    if (!confirm(`Resend verification email to ${emailAddress}?`)) {
        return;
    }

    const button = document.querySelector(`button[data-email-id="${emailId}"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    button.disabled = true;

    fetch(crmClientUrl('/clients/email/resend-verification'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email_id: emailId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Verification email resent successfully!');
            
            // Start polling for verification status
            startEmailVerificationPolling(emailId);
        } else {
            alert('Error: ' + (data.message || 'Failed to resend verification email'));
        }
        button.innerHTML = originalContent;
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error resending verification email:', error);
        alert('Network error. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Update verification status in UI
function updateEmailVerificationStatus(emailId, isVerified) {
    const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
    if (verifyBtn) {
        if (isVerified) {
            const summaryItem = verifyBtn.closest('.summary-item');
            if (summaryItem) {
                const verifiedBadge = document.createElement('span');
                verifiedBadge.className = 'verified-badge';
                verifiedBadge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Verified';
                verifiedBadge.title = 'Verified on ' + new Date().toLocaleString();
                
                // Replace the verify button with verified badge
                verifyBtn.parentNode.replaceChild(verifiedBadge, verifyBtn);
            }
        }
    }
    
    // Also update any detail view icons
    updateDetailViewEmailIcons(emailId, isVerified);
}

// Update email verification icons in detail views
function updateDetailViewEmailIcons(emailId, isVerified) {
    // Find the email address in detail views and update its icon
    const emailElements = document.querySelectorAll('span, div');
    emailElements.forEach(element => {
        if (element.textContent && element.textContent.includes('@')) {
            // Check if this element contains an email address
            const emailMatch = element.textContent.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
            if (emailMatch) {
                const emailAddress = emailMatch[1];
                
                // Find the corresponding ClientEmail record (this would need to be passed from the backend)
                // For now, we'll update based on the email address pattern
                const iconElement = element.querySelector('i');
                if (iconElement) {
                    if (isVerified) {
                        iconElement.className = 'fa-solid fa-circle-check verified-icon fa-lg';
                        iconElement.style.color = '#28a745';
                        iconElement.title = 'Verified on ' + new Date().toLocaleString();
                    } else {
                        iconElement.className = 'fa-regular fa-circle unverified-icon fa-lg';
                        iconElement.style.color = '#6c757d';
                        iconElement.title = 'Not verified';
                    }
                }
            }
        }
    });
}

/**
 * Validate if email ID is valid for polling
 */
function isValidEmailId(emailId) {
    return emailId && 
           emailId !== 'pending' && 
           emailId !== 'null' && 
           emailId !== 'undefined' &&
           emailId !== '' &&
           emailId !== '0' &&
           !isNaN(parseInt(emailId)) && 
           parseInt(emailId) > 0;
}

/**
 * Store active polling intervals for cleanup
 */
const activeEmailPollingIntervals = new Map();

// Check email verification status
function checkEmailVerificationStatus(emailId) {
    // Validate email ID before making request
    if (!isValidEmailId(emailId)) {
        console.warn(`Invalid email ID for status check: ${emailId}`);
        stopEmailVerificationPolling(emailId);
        return;
    }
    
    fetch(crmClientUrl('/clients/email/status/' + emailId), {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        // Check if response is ok (status 200-299)
        if (!response.ok) {
            // Handle 404 - email record doesn't exist
            if (response.status === 404) {
                console.warn(`Email record with ID ${emailId} not found (404). Stopping polling.`);
                stopEmailVerificationPolling(emailId);
                
                // Update UI to show error state
                const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
                if (verifyBtn) {
                    verifyBtn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Email Not Found';
                    verifyBtn.disabled = true;
                    verifyBtn.style.opacity = '0.6';
                    verifyBtn.title = 'Email record not found. Please refresh the page.';
                }
                return null;
            }
            // For other errors, throw to be caught by catch block
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Skip if data is null (404 was handled above)
        if (!data) return;
        
        if (data.success && data.is_verified) {
            updateEmailVerificationStatus(emailId, true);
            
            // Show success notification
            showNotification('Email verified successfully!', 'success');
            
            // Stop polling since email is verified
            stopEmailVerificationPolling(emailId);
            
            // Refresh the page after a short delay to update all views
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else if (data.success === false && data.message) {
            // Handle other error responses from the API
            console.warn(`Email status check failed: ${data.message}`);
            // Don't stop polling for other errors, just log them
        }
    })
    .catch(error => {
        console.error('Error checking email verification status:', error);
        // Don't stop polling on network errors, they might be temporary
    });
}

// Stop polling for email verification status
function stopEmailVerificationPolling(emailId) {
    if (activeEmailPollingIntervals.has(emailId)) {
        clearInterval(activeEmailPollingIntervals.get(emailId));
        activeEmailPollingIntervals.delete(emailId);
    }
}

// Start polling for email verification status
function startEmailVerificationPolling(emailId) {
    // Validate email ID before starting polling
    if (!isValidEmailId(emailId)) {
        console.warn(`Cannot start polling: Invalid email ID ${emailId}`);
        return;
    }
    
    // Stop any existing polling for this email ID
    if (activeEmailPollingIntervals.has(emailId)) {
        clearInterval(activeEmailPollingIntervals.get(emailId));
        activeEmailPollingIntervals.delete(emailId);
    }
    
    
    // Check immediately
    checkEmailVerificationStatus(emailId);
    
    // Then check every 5 seconds for 2 minutes
    let pollCount = 0;
    const maxPolls = 24; // 2 minutes (24 * 5 seconds)
    
    const pollInterval = setInterval(() => {
        pollCount++;
        
        // Check if button still exists (not verified yet)
        const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
        if (!verifyBtn) {
            // Button was replaced with verified badge, stop polling
            clearInterval(pollInterval);
            activeEmailPollingIntervals.delete(emailId);
            return;
        }
        
        checkEmailVerificationStatus(emailId);
        
        // Stop polling after max attempts
        if (pollCount >= maxPolls) {
            clearInterval(pollInterval);
            activeEmailPollingIntervals.delete(emailId);
            // Remove spinner from button
            if (verifyBtn && verifyBtn.innerHTML.includes('fa-spinner')) {
                verifyBtn.innerHTML = verifyBtn.innerHTML.replace('<i class="fa-solid fa-spinner fa-spin" style="margin-left: 5px; font-size: 10px;"></i>', '');
            }
        }
    }, 5000); // Check every 5 seconds
    
    // Store interval for cleanup
    activeEmailPollingIntervals.set(emailId, pollInterval);
}

/**
 * Stop all email verification polling
 */
function stopAllEmailPolling() {
    activeEmailPollingIntervals.forEach((interval, emailId) => {
        clearInterval(interval);
    });
    activeEmailPollingIntervals.clear();
}

/**
 * Initialize email section polling (one-time status check + start polling for unverified)
 */
function initializeEmailSectionPolling() {
    
    const emailSection = document.getElementById('emailAddressesSummary');
    if (!emailSection) {
        console.warn('⚠️ Email section not found');
        return;
    }
    
    const emailVerifyButtons = emailSection.querySelectorAll('.btn-verify-email');
    
    if (emailVerifyButtons.length === 0) {
        return;
    }
    
    
    // First, do a one-time refresh of all email statuses
    emailVerifyButtons.forEach(button => {
        const emailId = button.getAttribute('data-email-id');
        
        if (isValidEmailId(emailId)) {
            // Single check, not continuous polling yet
            checkEmailVerificationStatus(parseInt(emailId));
        } else {
            console.warn(`  ↳ Invalid email ID, skipping: ${emailId}`);
        }
    });
    
    // Then start continuous polling only for valid emails
    setTimeout(() => {
        emailVerifyButtons.forEach(button => {
            const emailId = button.getAttribute('data-email-id');
            
            if (isValidEmailId(emailId)) {
                startEmailVerificationPolling(parseInt(emailId));
            }
        });
    }, 1000); // Delay to avoid race condition with initial check
}
















// Update expiry date when assessment date changes (native date inputs)
document.addEventListener('DOMContentLoaded', function() {
    // Handle change events for native date inputs
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('dates')) {
            handleExpiryDateCalculation(e.target);
        }
    });
});

// ===== INITIALIZATION FUNCTIONS =====

// Initialize test score validation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize test score validation for existing fields
    const existingTestSelectors = document.querySelectorAll('.test-type-selector');
    existingTestSelectors.forEach((selector, index) => {
        if (selector.value) {
            updateTestScoreValidation(selector, index);
        }
    });
    
    initializeRelatedFilesTomSelect();

    setTimeout(function () {
        var el = document.getElementById('relatedFiles');
        if (el && !el.tomselect) {
            initializeRelatedFilesTomSelect();
        }
    }, 1000);

    setTimeout(function () {
        var el = document.getElementById('relatedFiles');
        if (el && !el.tomselect) {
            initializeRelatedFilesTomSelect();
        }
    }, 3000);
});






/**
 * Check if phone number is a placeholder
 */
function isPlaceholderNumber(phone) {
    // Remove any non-digit characters
    const cleaned = phone.replace(/\D/g, '');
    
    // Check if it starts with 4444444444 (placeholder pattern)
    return cleaned.startsWith('4444444444');
}

/**
 * Validate phone number using standardized rules
 */
function validatePhoneNumber(phone) {
    if (!phone || phone.trim() === '') {
        return {
            valid: false,
            message: 'Phone number is required'
        };
    }

    // Remove any non-digit characters for validation
    const cleaned = phone.replace(/\D/g, '');

    // Check if it's a placeholder number (allow it)
    if (isPlaceholderNumber(cleaned)) {
        return {
            valid: true,
            message: 'Placeholder number detected',
            isPlaceholder: true
        };
    }

    // Check length
    if (cleaned.length < 10) {
        return {
            valid: false,
            message: 'Phone number must be at least 10 digits'
        };
    }

    if (cleaned.length > 15) {
        return {
            valid: false,
            message: 'Phone number must not exceed 15 digits'
        };
    }

    // Check if it contains only digits
    const phoneRegex = /^[0-9]{10,15}$/;
    if (!phoneRegex.test(cleaned)) {
        return {
            valid: false,
            message: 'Phone number must contain only digits'
        };
    }

    return {
        valid: true,
        message: 'Valid phone number',
        isPlaceholder: false
    };
}

