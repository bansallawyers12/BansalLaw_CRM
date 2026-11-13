/**
 * Lead Form JavaScript Functions
 * 
 * Handles dynamic form fields for phone numbers and emails
 */

/**
 * Add Phone Number Field
 */
function addPhoneNumber() {
    const container = document.getElementById('phoneNumbersContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const phoneNumberHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
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
                        <option value="Partner">Partner</option>
                        <option value="Others">Others</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Country Code</label>
                    <select name="country_code[${index}]" class="country-code-selector">
                        <option value="+61">+61 (Australia)</option>
                        <option value="+1">+1 (USA/Canada)</option>
                        <option value="+44">+44 (UK)</option>
                        <option value="+91">+91 (India)</option>
                        <option value="+86">+86 (China)</option>
                        <option value="+81">+81 (Japan)</option>
                        <option value="+82">+82 (South Korea)</option>
                        <option value="+65">+65 (Singapore)</option>
                        <option value="+64">+64 (New Zealand)</option>
                        <option value="+63">+63 (Philippines)</option>
                        <option value="+60">+60 (Malaysia)</option>
                        <option value="+66">+66 (Thailand)</option>
                        <option value="+84">+84 (Vietnam)</option>
                        <option value="+62">+62 (Indonesia)</option>
                        <option value="+92">+92 (Pakistan)</option>
                        <option value="+94">+94 (Sri Lanka)</option>
                        <option value="+880">+880 (Bangladesh)</option>
                        <option value="+977">+977 (Nepal)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone[${index}]" class="phone-number-input" placeholder="Enter phone number" pattern="[0-9]*" inputmode="numeric" required>
                    <div class="phone-error phone-error-${index}" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', phoneNumberHTML);
}

/**
 * Remove Phone Number Field
 */
function removePhoneField(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        // Check if it's the last phone field
        const container = document.getElementById('phoneNumbersContainer');
        if (container && container.children.length > 1) {
            section.remove();
        } else {
            crmAlert('At least one phone number is required.');
        }
    }
}

/**
 * Add Email Address Field
 */
function addEmailAddress() {
    const container = document.getElementById('emailAddressesContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const emailHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="email_type_hidden[${index}]" class="email-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Business">Business</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Partner">Partner</option>
                        <option value="Others">Others</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email[${index}]" class="email-input" placeholder="Enter email address" required>
                    <div class="email-error email-error-${index}" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', emailHTML);
}

/**
 * Remove Email Address Field
 */
function removeEmailField(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        // Check if it's the last email field
        const container = document.getElementById('emailAddressesContainer');
        if (container && container.children.length > 1) {
            section.remove();
        } else {
            crmAlert('At least one email address is required.');
        }
    }
}

// Make phone and email helpers globally available
window.addPhoneNumber = addPhoneNumber;
window.removePhoneField = removePhoneField;
window.addEmailAddress = addEmailAddress;
window.removeEmailField = removeEmailField;
