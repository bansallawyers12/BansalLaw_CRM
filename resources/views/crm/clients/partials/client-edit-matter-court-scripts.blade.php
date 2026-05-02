    // =====================================================
    // Matter Type Selector — Dynamic Form
    // =====================================================
    window.MATTER_PARTY_ROLES_BY_STREAM = @json(config('matter_streams.party_roles_by_stream', []));
    var selectedMatterTypeId = null;
    var selectedMatterTypeNick = null;

    function getDynMatterStream() {
        var sel = document.getElementById('matterTypeDropdown');
        if (!sel) return 'general';
        var opt = sel.options[sel.selectedIndex];
        var s = opt && opt.getAttribute('data-stream');
        return (s && String(s).trim() !== '') ? String(s).trim() : 'general';
    }

    function rebuildDynOurPartyRole() {
        var map = window.MATTER_PARTY_ROLES_BY_STREAM || {};
        var stream = getDynMatterStream();
        var roles = map[stream] || map['general'] || {};
        var pr = document.getElementById('dyn_our_party_role');
        if (!pr) return;
        var cur = pr.value;
        pr.innerHTML = '';
        var o0 = document.createElement('option');
        o0.value = '';
        o0.textContent = '\u2014';
        pr.appendChild(o0);
        Object.keys(roles).forEach(function (k) {
            var o = document.createElement('option');
            o.value = k;
            o.textContent = roles[k];
            pr.appendChild(o);
        });
        if (cur) { pr.value = cur; }
    }

    function dynAppendOpposingRow(name, role) {
        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (!wrap) return;
        var row = document.createElement('div');
        row.className = 'row mb-2 dyn-opp-row';
        row.style.alignItems = 'flex-end';
        row.innerHTML =
            '<div class="col-md-5"><label class="small mb-0 d-block">Name</label>' +
            '<input type="text" class="form-control dyn-opp-name" maxlength="500" value=""></div>' +
            '<div class="col-md-5"><label class="small mb-0 d-block">Their role</label>' +
            '<input type="text" class="form-control dyn-opp-role" maxlength="255" placeholder="e.g. co-defendant" value=""></div>' +
            '<div class="col-md-2"><label class="small mb-0 d-block">&nbsp;</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger w-100 dyn-opp-remove">Remove</button></div>';
        row.querySelector('.dyn-opp-name').value = name || '';
        row.querySelector('.dyn-opp-role').value = role || '';
        row.querySelector('.dyn-opp-remove').addEventListener('click', function () { row.remove(); });
        wrap.appendChild(row);
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'dyn_add_opposing_party_btn') {
            e.preventDefault();
            dynAppendOpposingRow('', '');
        }
    });

    var matterSpecificFieldsConfig = {
        'CIV': {
            label: 'Civil Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Civil Matter', required: true,
                options: ['Money Recovery Suit','Injunction','Specific Performance','Declaratory Suit','Partition Suit','Breach of Contract','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Cause of Action', type: 'date' },
                { id: 'dyn_opposing_party', label: 'Opposing Party / Defendant', type: 'text', placeholder: 'Name of the opposing party' },
                { id: 'dyn_court_name', label: 'Court Name', type: 'text', placeholder: 'e.g. Civil Court, Saket' }
            ],
            subTypeFields: {
                'Money Recovery Suit': [
                    { id: 'dyn_amount', label: 'Amount Claimed (₹)', type: 'number', placeholder: 'e.g. 500000' },
                    { id: 'dyn_basis', label: 'Basis of Claim', type: 'select', options: ['Loan','Agreement','Cheque','Invoice','Other'] },
                    { id: 'dyn_due_date', label: 'Due Date', type: 'date' }
                ],
                'Injunction': [
                    { id: 'dyn_injunction_type', label: 'Nature of Injunction', type: 'select', options: ['Temporary','Permanent','Mandatory','Prohibitory'] },
                    { id: 'dyn_subject', label: 'Subject Property / Matter', type: 'text', placeholder: 'Details of subject matter' }
                ],
                'Specific Performance': [
                    { id: 'dyn_agreement_date', label: 'Agreement Date', type: 'date' },
                    { id: 'dyn_subject', label: 'Subject of Agreement', type: 'text', placeholder: 'e.g. Sale of property at...' }
                ],
                'Declaratory Suit': [
                    { id: 'dyn_declaration', label: 'Declaration Sought', type: 'text', placeholder: 'Nature of declaration sought' }
                ],
                'Partition Suit': [
                    { id: 'dyn_property_detail', label: 'Property Details', type: 'text', placeholder: 'Address / description' },
                    { id: 'dyn_co_owners', label: 'Total Co-owners', type: 'number', placeholder: 'No. of co-owners' },
                    { id: 'dyn_share', label: 'Share Claimed', type: 'text', placeholder: 'e.g. 1/4th' }
                ],
                'Breach of Contract': [
                    { id: 'dyn_contract_date', label: 'Contract Date', type: 'date' },
                    { id: 'dyn_breach_date', label: 'Date of Breach', type: 'date' },
                    { id: 'dyn_damages', label: 'Damages Claimed (₹)', type: 'number', placeholder: 'e.g. 1000000' }
                ]
            }
        },
        'CRM': {
            label: 'Criminal Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Criminal Matter', required: true,
                options: ['Bail Application','Anticipatory Bail','Quashing Petition','Trial Defence','Private Complaint','Criminal Appeal','Revision Petition','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Incident / FIR', type: 'date' },
                { id: 'dyn_fir_no', label: 'FIR / Case Number', type: 'text', placeholder: 'e.g. FIR 123/2024' },
                { id: 'dyn_police_station', label: 'Police Station', type: 'text', placeholder: 'Name of police station' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. Sessions Court, Delhi' }
            ],
            subTypeFields: {
                'Bail Application': [
                    { id: 'dyn_charges', label: 'Charges / Sections', type: 'text', placeholder: 'e.g. IPC 302, 420' },
                    { id: 'dyn_arrest_date', label: 'Date of Arrest', type: 'date' },
                    { id: 'dyn_jail', label: 'Jail / Detention Place', type: 'text', placeholder: 'Name of jail' }
                ],
                'Anticipatory Bail': [
                    { id: 'dyn_charges', label: 'Apprehended Charges', type: 'text', placeholder: 'e.g. IPC 406, 420' },
                    { id: 'dyn_reason', label: 'Reason for Apprehension', type: 'text', placeholder: 'Why arrest is feared' }
                ],
                'Quashing Petition': [
                    { id: 'dyn_charges', label: 'Sections Challenged', type: 'text', placeholder: 'e.g. IPC 498A' },
                    { id: 'dyn_grounds', label: 'Grounds for Quashing', type: 'text', placeholder: 'Brief grounds' }
                ],
                'Trial Defence': [
                    { id: 'dyn_charges', label: 'Charges / Sections', type: 'text', placeholder: 'e.g. IPC 302, 34' },
                    { id: 'dyn_trial_stage', label: 'Stage of Trial', type: 'select', options: ['Pre-charge','Charge Framed','Prosecution Evidence','Defence Evidence','Final Arguments','Other'] }
                ],
                'Private Complaint': [
                    { id: 'dyn_accused', label: 'Accused Name', type: 'text', placeholder: 'Name of accused' },
                    { id: 'dyn_offence', label: 'Nature of Offence', type: 'text', placeholder: 'Brief description' }
                ],
                'Criminal Appeal': [
                    { id: 'dyn_original_case', label: 'Original Case Number', type: 'text', placeholder: 'Lower court case no.' },
                    { id: 'dyn_conviction_date', label: 'Conviction Date', type: 'date' },
                    { id: 'dyn_sentence', label: 'Sentence Details', type: 'text', placeholder: 'e.g. 5 years RI' }
                ],
                'Revision Petition': [
                    { id: 'dyn_order_date', label: 'Original Order Date', type: 'date' },
                    { id: 'dyn_lower_court', label: 'Lower Court', type: 'text', placeholder: 'Court whose order is challenged' }
                ]
            }
        },
        'FAM': {
            label: 'Family Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Family Matter', required: true,
                options: ['Divorce','Child Custody','Maintenance / Alimony','Domestic Violence','Adoption','Guardianship','Restitution of Conjugal Rights','Other'] },
            commonFields: [
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of the other party' },
                { id: 'dyn_court_name', label: 'Court / Family Court', type: 'text', placeholder: 'e.g. Family Court, Rohini' }
            ],
            subTypeFields: {
                'Divorce': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_separation_date', label: 'Date of Separation', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds for Divorce', type: 'select', options: ['Mutual Consent','Cruelty','Desertion','Adultery','Mental Disorder','Conversion','Presumption of Death','Other'] },
                    { id: 'dyn_children', label: 'Children', type: 'select', options: ['No Children','1 Child','2 Children','3+ Children'] }
                ],
                'Child Custody': [
                    { id: 'dyn_child_name', label: 'Child Name(s)', type: 'text', placeholder: 'Name(s) of child(ren)' },
                    { id: 'dyn_child_age', label: 'Child Age(s)', type: 'text', placeholder: 'e.g. 5 yrs, 8 yrs' },
                    { id: 'dyn_custody_type', label: 'Custody Sought', type: 'select', options: ['Sole Custody','Joint Custody','Visitation Rights'] },
                    { id: 'dyn_current_custodian', label: 'Current Custodian', type: 'text', placeholder: 'Who currently has custody' }
                ],
                'Maintenance / Alimony': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_pet_income', label: "Petitioner's Monthly Income (₹)", type: 'number', placeholder: 'e.g. 50000' },
                    { id: 'dyn_resp_income', label: "Respondent's Monthly Income (₹)", type: 'number', placeholder: 'e.g. 80000' },
                    { id: 'dyn_amount_sought', label: 'Maintenance Sought (₹/month)', type: 'number', placeholder: 'e.g. 25000' }
                ],
                'Domestic Violence': [
                    { id: 'dyn_date_of_incidence', label: 'Date of Incident', type: 'date' },
                    { id: 'dyn_violence_type', label: 'Nature of Violence', type: 'select', options: ['Physical','Emotional / Mental','Economic','Sexual','Verbal','Multiple'] },
                    { id: 'dyn_protection_order', label: 'Protection Order Sought', type: 'select', options: ['Yes','No'] },
                    { id: 'dyn_relationship', label: 'Relationship with Respondent', type: 'select', options: ['Husband','In-Laws','Live-in Partner','Other'] }
                ],
                'Adoption': [
                    { id: 'dyn_child_name', label: 'Child Name', type: 'text', placeholder: 'Name of child' },
                    { id: 'dyn_child_age', label: 'Child Age', type: 'text', placeholder: 'Age of child' },
                    { id: 'dyn_adoption_type', label: 'Adoption Type', type: 'select', options: ['Domestic','Inter-country'] },
                    { id: 'dyn_agency', label: 'Agency / CARA Reg. No.', type: 'text', placeholder: 'Agency name or registration no.' }
                ],
                'Guardianship': [
                    { id: 'dyn_ward_name', label: 'Ward Name', type: 'text', placeholder: 'Name of person' },
                    { id: 'dyn_ward_age', label: 'Ward Age', type: 'text', placeholder: 'Age' },
                    { id: 'dyn_relation', label: 'Relationship with Ward', type: 'text', placeholder: 'e.g. Uncle, Grandparent' }
                ],
                'Restitution of Conjugal Rights': [
                    { id: 'dyn_marriage_date', label: 'Date of Marriage', type: 'date' },
                    { id: 'dyn_desertion_date', label: 'Date of Desertion', type: 'date' }
                ]
            }
        },
        'PROP': {
            label: 'Property & Real Estate',
            subType: { id: 'dyn_sub_type', label: 'Type of Property Matter', required: true,
                options: ['Title Dispute','Possession Dispute','Encroachment','Sale / Purchase Dispute','Tenancy / Eviction','Builder / RERA','Land Acquisition','Other'] },
            commonFields: [
                { id: 'dyn_property_address', label: 'Property Address', type: 'text', placeholder: 'Full address of the property' },
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of other party' },
                { id: 'dyn_court_name', label: 'Court / Forum', type: 'text', placeholder: 'e.g. District Court, Gurugram' }
            ],
            subTypeFields: {
                'Title Dispute': [
                    { id: 'dyn_survey_no', label: 'Khasra / Survey No.', type: 'text', placeholder: 'Property identification no.' },
                    { id: 'dyn_area', label: 'Area (sq. ft / bigha)', type: 'text', placeholder: 'e.g. 500 sq ft' },
                    { id: 'dyn_docs', label: 'Documents Available', type: 'text', placeholder: 'e.g. Sale Deed, Registry' }
                ],
                'Possession Dispute': [
                    { id: 'dyn_occupied_by', label: 'Occupied By', type: 'text', placeholder: 'Name of occupant' },
                    { id: 'dyn_since_when', label: 'Occupation Since', type: 'text', placeholder: 'e.g. Jan 2020' },
                    { id: 'dyn_basis', label: 'Basis of Possession', type: 'text', placeholder: 'e.g. Lease, Trespass' }
                ],
                'Encroachment': [
                    { id: 'dyn_encroached_area', label: 'Area Encroached', type: 'text', placeholder: 'e.g. 200 sq ft' },
                    { id: 'dyn_encroacher', label: 'Encroacher Details', type: 'text', placeholder: 'Name / details' }
                ],
                'Sale / Purchase Dispute': [
                    { id: 'dyn_agreement_date', label: 'Agreement Date', type: 'date' },
                    { id: 'dyn_sale_amount', label: 'Sale Consideration (₹)', type: 'number', placeholder: 'e.g. 5000000' },
                    { id: 'dyn_seller', label: 'Seller / Builder Name', type: 'text', placeholder: 'Name of seller or builder' }
                ],
                'Tenancy / Eviction': [
                    { id: 'dyn_rent', label: 'Monthly Rent (₹)', type: 'number', placeholder: 'e.g. 15000' },
                    { id: 'dyn_tenancy_since', label: 'Tenancy Since', type: 'date' },
                    { id: 'dyn_rent_agreement', label: 'Rent Agreement Exists', type: 'select', options: ['Yes','No'] }
                ],
                'Builder / RERA': [
                    { id: 'dyn_builder', label: 'Builder Name', type: 'text', placeholder: 'Name of builder / developer' },
                    { id: 'dyn_project', label: 'Project Name', type: 'text', placeholder: 'Project / society name' },
                    { id: 'dyn_unit_no', label: 'Unit / Flat No.', type: 'text', placeholder: 'e.g. A-1204' },
                    { id: 'dyn_booking_date', label: 'Booking Date', type: 'date' },
                    { id: 'dyn_amount_paid', label: 'Amount Paid (₹)', type: 'number', placeholder: 'e.g. 3000000' },
                    { id: 'dyn_possession_due', label: 'Possession Due Date', type: 'date' }
                ],
                'Land Acquisition': [
                    { id: 'dyn_khasra', label: 'Khasra / Plot No.', type: 'text', placeholder: 'Land identification' },
                    { id: 'dyn_village', label: 'Village / Tehsil / District', type: 'text', placeholder: 'Location details' },
                    { id: 'dyn_land_area', label: 'Land Area', type: 'text', placeholder: 'e.g. 2 bigha' },
                    { id: 'dyn_compensation', label: 'Compensation Offered (₹)', type: 'number', placeholder: 'Amount offered' },
                    { id: 'dyn_award_date', label: 'Award Date', type: 'date' }
                ]
            }
        },
        'CORP': {
            label: 'Corporate & Business Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Corporate Matter', required: true,
                options: ['Contract / Commercial Dispute','Shareholder / Director Dispute','Insolvency (IBC / NCLT)','Merger & Acquisition','Partnership Dispute','Regulatory / Compliance','Other'] },
            commonFields: [
                { id: 'dyn_company_name', label: 'Company / Entity Name', type: 'text', placeholder: 'Name of the company involved' },
                { id: 'dyn_opposing_party', label: 'Opposing Party', type: 'text', placeholder: 'Name of other party / company' }
            ],
            subTypeFields: {
                'Contract / Commercial Dispute': [
                    { id: 'dyn_contract_date', label: 'Contract Date', type: 'date' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_amount', label: 'Claim Amount (₹)', type: 'number', placeholder: 'e.g. 1000000' }
                ],
                'Shareholder / Director Dispute': [
                    { id: 'dyn_shareholding', label: 'Shareholding %', type: 'text', placeholder: 'e.g. 25%' },
                    { id: 'dyn_position', label: 'Position Held', type: 'text', placeholder: 'e.g. Director, Shareholder' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' }
                ],
                'Insolvency (IBC / NCLT)': [
                    { id: 'dyn_nclt_bench', label: 'NCLT Bench', type: 'text', placeholder: 'e.g. New Delhi' },
                    { id: 'dyn_nature', label: 'Nature', type: 'select', options: ['CIRP','Liquidation','Pre-IBC Settlement','Other'] },
                    { id: 'dyn_amount', label: 'Claim / Default Amount (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Merger & Acquisition': [
                    { id: 'dyn_target', label: 'Target / Acquirer Company', type: 'text', placeholder: 'Company name' },
                    { id: 'dyn_txn_type', label: 'Transaction Type', type: 'select', options: ['Merger','Acquisition','Demerger','Amalgamation'] },
                    { id: 'dyn_value', label: 'Estimated Value (₹)', type: 'number', placeholder: 'Transaction value' }
                ],
                'Partnership Dispute': [
                    { id: 'dyn_firm_name', label: 'Firm Name', type: 'text', placeholder: 'Name of partnership firm' },
                    { id: 'dyn_partners', label: 'Partner Names', type: 'text', placeholder: 'Names of partners' },
                    { id: 'dyn_since', label: 'Partnership Since', type: 'date' }
                ],
                'Regulatory / Compliance': [
                    { id: 'dyn_regulator', label: 'Regulator', type: 'select', options: ['SEBI','RBI','MCA','CCI','Other'] },
                    { id: 'dyn_proceedings', label: 'Nature of Proceedings', type: 'text', placeholder: 'Brief description' }
                ]
            }
        },
        'LAB': {
            label: 'Labour & Employment',
            subType: { id: 'dyn_sub_type', label: 'Type of Labour Matter', required: true,
                options: ['Wrongful Termination','Unpaid Salary / Dues','Sexual Harassment (POSH)','PF / ESI Dispute','Industrial Dispute','Discrimination','Other'] },
            commonFields: [
                { id: 'dyn_employer', label: 'Employer / Company Name', type: 'text', placeholder: 'Name of employer' },
                { id: 'dyn_court_name', label: 'Labour Court / Tribunal', type: 'text', placeholder: 'e.g. Labour Court, Faridabad' }
            ],
            subTypeFields: {
                'Wrongful Termination': [
                    { id: 'dyn_joining_date', label: 'Date of Joining', type: 'date' },
                    { id: 'dyn_termination_date', label: 'Date of Termination', type: 'date' },
                    { id: 'dyn_designation', label: 'Last Designation', type: 'text', placeholder: 'e.g. Senior Manager' },
                    { id: 'dyn_salary', label: 'Last Salary (₹/month)', type: 'number', placeholder: 'e.g. 80000' }
                ],
                'Unpaid Salary / Dues': [
                    { id: 'dyn_amount', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'e.g. 200000' },
                    { id: 'dyn_period', label: 'Period Unpaid', type: 'text', placeholder: 'e.g. Jan 2024 – Mar 2024' },
                    { id: 'dyn_last_working', label: 'Last Working Date', type: 'date' }
                ],
                'Sexual Harassment (POSH)': [
                    { id: 'dyn_icc', label: 'ICC Constituted', type: 'select', options: ['Yes','No','Unknown'] },
                    { id: 'dyn_complaint_date', label: 'Complaint Date', type: 'date' }
                ],
                'PF / ESI Dispute': [
                    { id: 'dyn_uan', label: 'UAN / PF Number', type: 'text', placeholder: 'e.g. 100123456789' },
                    { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_issue', label: 'Issue', type: 'text', placeholder: 'e.g. Non-deposit, Wrong calculation' }
                ],
                'Industrial Dispute': [
                    { id: 'dyn_union', label: 'Union Name', type: 'text', placeholder: 'Trade union name (if any)' },
                    { id: 'dyn_dispute_nature', label: 'Nature of Dispute', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_workmen', label: 'Workmen Count', type: 'number', placeholder: 'Number affected' }
                ],
                'Discrimination': [
                    { id: 'dyn_ground', label: 'Ground', type: 'select', options: ['Gender','Caste','Religion','Disability','Age','Other'] },
                    { id: 'dyn_details', label: 'Details', type: 'text', placeholder: 'Brief description of discrimination' }
                ]
            }
        },
        'CONS': {
            label: 'Consumer Law',
            subType: { id: 'dyn_sub_type', label: 'Type of Consumer Matter', required: true,
                options: ['Defective Product','Deficient Service','Unfair Trade Practice','E-Commerce Dispute','Insurance Claim Rejection','Medical Negligence','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Complaint / Incident', type: 'date' },
                { id: 'dyn_forum', label: 'Consumer Forum / Commission', type: 'text', placeholder: 'e.g. District Consumer Forum, Delhi' }
            ],
            subTypeFields: {
                'Defective Product': [
                    { id: 'dyn_product', label: 'Product Name', type: 'text', placeholder: 'Name of the product' },
                    { id: 'dyn_seller', label: 'Manufacturer / Seller', type: 'text', placeholder: 'Company name' },
                    { id: 'dyn_purchase_date', label: 'Purchase Date', type: 'date' },
                    { id: 'dyn_amount', label: 'Invoice Amount (₹)', type: 'number', placeholder: 'Amount paid' },
                    { id: 'dyn_defect', label: 'Defect Description', type: 'text', placeholder: 'What is wrong with the product' }
                ],
                'Deficient Service': [
                    { id: 'dyn_provider', label: 'Service Provider', type: 'text', placeholder: 'Company / person name' },
                    { id: 'dyn_service_type', label: 'Service Type', type: 'text', placeholder: 'e.g. Telecom, Banking, Education' },
                    { id: 'dyn_amount', label: 'Amount Paid (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Unfair Trade Practice': [
                    { id: 'dyn_company', label: 'Company Name', type: 'text', placeholder: 'Name of company' },
                    { id: 'dyn_practice', label: 'Nature of Practice', type: 'text', placeholder: 'Description of unfair practice' },
                    { id: 'dyn_loss', label: 'Loss Incurred (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'E-Commerce Dispute': [
                    { id: 'dyn_platform', label: 'Platform Name', type: 'text', placeholder: 'e.g. Amazon, Flipkart' },
                    { id: 'dyn_order_no', label: 'Order Number', type: 'text', placeholder: 'Order ID' },
                    { id: 'dyn_amount', label: 'Order Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_issue', label: 'Issue', type: 'text', placeholder: 'e.g. Non-delivery, Wrong product' }
                ],
                'Insurance Claim Rejection': [
                    { id: 'dyn_insurer', label: 'Insurance Company', type: 'text', placeholder: 'Name of insurer' },
                    { id: 'dyn_policy_no', label: 'Policy Number', type: 'text', placeholder: 'Policy no.' },
                    { id: 'dyn_claim_amount', label: 'Claim Amount (₹)', type: 'number', placeholder: 'Amount claimed' },
                    { id: 'dyn_rejection_date', label: 'Rejection Date', type: 'date' },
                    { id: 'dyn_reason', label: 'Reason for Rejection', type: 'text', placeholder: 'As stated by insurer' }
                ],
                'Medical Negligence': [
                    { id: 'dyn_hospital', label: 'Hospital / Doctor Name', type: 'text', placeholder: 'Name of hospital or doctor' },
                    { id: 'dyn_treatment_date', label: 'Treatment Date', type: 'date' },
                    { id: 'dyn_negligence', label: 'Nature of Negligence', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_damages', label: 'Compensation Claimed (₹)', type: 'number', placeholder: 'Amount' }
                ]
            }
        },
        'BANK': {
            label: 'Banking & Finance',
            subType: { id: 'dyn_sub_type', label: 'Type of Banking Matter', required: true,
                options: ['Cheque Bounce (S.138 NI Act)','Loan Recovery / Default','SARFAESI Proceedings','Banking Fraud','Credit / Debit Card Dispute','DRT Proceedings','Other'] },
            commonFields: [
                { id: 'dyn_bank_name', label: 'Bank / NBFC Name', type: 'text', placeholder: 'Name of bank or financial institution' },
                { id: 'dyn_account_no', label: 'Account / Loan Number', type: 'text', placeholder: 'Account or loan number' }
            ],
            subTypeFields: {
                'Cheque Bounce (S.138 NI Act)': [
                    { id: 'dyn_cheque_no', label: 'Cheque Number', type: 'text', placeholder: 'Cheque no.' },
                    { id: 'dyn_cheque_date', label: 'Cheque Date', type: 'date' },
                    { id: 'dyn_amount', label: 'Cheque Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_dishonour_date', label: 'Dishonour Date', type: 'date' },
                    { id: 'dyn_notice_date', label: 'Legal Notice Sent Date', type: 'date' }
                ],
                'Loan Recovery / Default': [
                    { id: 'dyn_loan_type', label: 'Loan Type', type: 'select', options: ['Home Loan','Personal Loan','Business Loan','Vehicle Loan','Gold Loan','Other'] },
                    { id: 'dyn_outstanding', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'Amount due' },
                    { id: 'dyn_last_emi', label: 'Last EMI Date', type: 'date' }
                ],
                'SARFAESI Proceedings': [
                    { id: 'dyn_notice_date', label: 'S.13(2) Notice Date', type: 'date' },
                    { id: 'dyn_property', label: 'Property Under Auction', type: 'text', placeholder: 'Property details' },
                    { id: 'dyn_outstanding', label: 'Outstanding Amount (₹)', type: 'number', placeholder: 'Amount' }
                ],
                'Banking Fraud': [
                    { id: 'dyn_fraud_amount', label: 'Fraud Amount (₹)', type: 'number', placeholder: 'Amount defrauded' },
                    { id: 'dyn_fraud_type', label: 'Fraud Type', type: 'select', options: ['Online Fraud','Identity Theft','Phishing','Unauthorised Transaction','Other'] },
                    { id: 'dyn_fir', label: 'FIR Filed', type: 'select', options: ['Yes','No'] }
                ],
                'Credit / Debit Card Dispute': [
                    { id: 'dyn_card_last4', label: 'Card Last 4 Digits', type: 'text', placeholder: 'e.g. 4567' },
                    { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Amount' },
                    { id: 'dyn_txn_date', label: 'Transaction Date', type: 'date' }
                ],
                'DRT Proceedings': [
                    { id: 'dyn_drt_case', label: 'DRT Case Number', type: 'text', placeholder: 'Case no.' },
                    { id: 'dyn_drt_location', label: 'DRT Location', type: 'text', placeholder: 'e.g. DRT-I, Delhi' },
                    { id: 'dyn_amount', label: 'Amount (₹)', type: 'number', placeholder: 'Claim amount' }
                ]
            }
        },
        'TAX': {
            label: 'Taxation',
            subType: { id: 'dyn_sub_type', label: 'Type of Tax Matter', required: true,
                options: ['Income Tax Assessment / Appeal','GST Dispute','Property Tax','Customs & Excise','Tax Evasion Defence','TDS / Refund Issue','Other'] },
            commonFields: [
                { id: 'dyn_pan_gstin', label: 'PAN / GSTIN', type: 'text', placeholder: 'PAN or GSTIN number' },
                { id: 'dyn_amount', label: 'Disputed Amount (₹)', type: 'number', placeholder: 'Tax amount in dispute' }
            ],
            subTypeFields: {
                'Income Tax Assessment / Appeal': [
                    { id: 'dyn_ay', label: 'Assessment Year', type: 'text', placeholder: 'e.g. 2024-25' },
                    { id: 'dyn_section', label: 'Section', type: 'select', options: ['143(1)','143(3)','144','147','148','263','Other'] },
                    { id: 'dyn_authority', label: 'Authority / Forum', type: 'select', options: ['CIT(A)','ITAT','High Court','Other'] }
                ],
                'GST Dispute': [
                    { id: 'dyn_period', label: 'Tax Period', type: 'text', placeholder: 'e.g. Apr 2024 – Mar 2025' },
                    { id: 'dyn_nature', label: 'Nature', type: 'select', options: ['Input Credit Denial','Classification','Penalty','Demand','Refund','Other'] }
                ],
                'Property Tax': [
                    { id: 'dyn_property_address', label: 'Property Address', type: 'text', placeholder: 'Address of property' },
                    { id: 'dyn_authority', label: 'Municipal Authority', type: 'text', placeholder: 'e.g. MCD, Noida Authority' },
                    { id: 'dyn_period', label: 'Period', type: 'text', placeholder: 'e.g. 2020-2024' }
                ],
                'Customs & Excise': [
                    { id: 'dyn_ie_code', label: 'IE Code / CHA', type: 'text', placeholder: 'Import-Export code' },
                    { id: 'dyn_bill_no', label: 'Bill of Entry / Shipping Bill', type: 'text', placeholder: 'Document no.' }
                ],
                'Tax Evasion Defence': [
                    { id: 'dyn_charges', label: 'Prosecution Section', type: 'text', placeholder: 'e.g. Section 276C' },
                    { id: 'dyn_authority', label: 'Authority', type: 'text', placeholder: 'Investigating authority' }
                ],
                'TDS / Refund Issue': [
                    { id: 'dyn_ay', label: 'Assessment Year', type: 'text', placeholder: 'e.g. 2024-25' },
                    { id: 'dyn_refund_amount', label: 'Refund Amount (₹)', type: 'number', placeholder: 'Expected refund' },
                    { id: 'dyn_status', label: 'Current Status', type: 'text', placeholder: 'e.g. Pending, Partially processed' }
                ]
            }
        },
        'IP': {
            label: 'Intellectual Property',
            subType: { id: 'dyn_sub_type', label: 'Type of IP Matter', required: true,
                options: ['Trademark Infringement','Copyright Infringement','Patent Filing / Dispute','Design Registration','Trade Secret','Domain Name Dispute','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Infringement', type: 'date' },
                { id: 'dyn_opposing_party', label: 'Opposing / Infringing Party', type: 'text', placeholder: 'Name of other party' }
            ],
            subTypeFields: {
                'Trademark Infringement': [
                    { id: 'dyn_tm_name', label: 'Trademark Name / No.', type: 'text', placeholder: 'Trademark or registration no.' },
                    { id: 'dyn_class', label: 'Trademark Class', type: 'text', placeholder: 'e.g. Class 25' },
                    { id: 'dyn_infringement', label: 'Nature of Infringement', type: 'text', placeholder: 'Brief description' }
                ],
                'Copyright Infringement': [
                    { id: 'dyn_work_title', label: 'Work Title', type: 'text', placeholder: 'Title of copyrighted work' },
                    { id: 'dyn_reg_no', label: 'Registration Number', type: 'text', placeholder: 'Copyright reg. no.' },
                    { id: 'dyn_author', label: 'Author / Owner', type: 'text', placeholder: 'Name of author' }
                ],
                'Patent Filing / Dispute': [
                    { id: 'dyn_patent_no', label: 'Patent / Application No.', type: 'text', placeholder: 'Patent number' },
                    { id: 'dyn_invention', label: 'Invention Title', type: 'text', placeholder: 'Title of invention' }
                ],
                'Design Registration': [
                    { id: 'dyn_design_name', label: 'Design Name', type: 'text', placeholder: 'Name of design' },
                    { id: 'dyn_reg_no', label: 'Registration Number', type: 'text', placeholder: 'Design reg. no.' },
                    { id: 'dyn_class', label: 'Design Class', type: 'text', placeholder: 'Locarno class' }
                ],
                'Trade Secret': [
                    { id: 'dyn_secret_nature', label: 'Nature of Trade Secret', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_nda', label: 'NDA / Agreement Exists', type: 'select', options: ['Yes','No'] }
                ],
                'Domain Name Dispute': [
                    { id: 'dyn_domain', label: 'Domain Name', type: 'text', placeholder: 'e.g. example.com' },
                    { id: 'dyn_registrant', label: 'Registrant', type: 'text', placeholder: 'Domain registrant name' },
                    { id: 'dyn_dp_forum', label: 'Forum', type: 'select', options: ['INDRP','WIPO','ICANN','Other'] }
                ]
            }
        },
        'CONST': {
            label: 'Constitutional & Writ',
            subType: { id: 'dyn_sub_type', label: 'Type of Writ / Constitutional Matter', required: true,
                options: ['Habeas Corpus','Mandamus','Certiorari','Prohibition','Quo Warranto','Fundamental Rights Violation','PIL','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Filing / Violation', type: 'date' },
                { id: 'dyn_authority', label: 'Authority / Respondent', type: 'text', placeholder: 'Govt. body / authority name' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. High Court Delhi' }
            ],
            subTypeFields: {
                'Habeas Corpus': [
                    { id: 'dyn_detained', label: 'Detained Person Name', type: 'text', placeholder: 'Name of detained person' },
                    { id: 'dyn_detaining_auth', label: 'Detaining Authority', type: 'text', placeholder: 'Authority / place of detention' },
                    { id: 'dyn_detention_date', label: 'Date of Detention', type: 'date' }
                ],
                'Mandamus': [
                    { id: 'dyn_duty', label: 'Duty to be Performed', type: 'text', placeholder: 'What should the authority do' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'Specific relief requested' }
                ],
                'Certiorari': [
                    { id: 'dyn_tribunal', label: 'Court / Tribunal', type: 'text', placeholder: 'Whose order is challenged' },
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' }
                ],
                'Prohibition': [
                    { id: 'dyn_tribunal', label: 'Court / Tribunal', type: 'text', placeholder: 'Proceedings to be restrained' }
                ],
                'Fundamental Rights Violation': [
                    { id: 'dyn_right', label: 'Fundamental Right', type: 'select', options: ['Article 14 (Equality)','Article 19 (Freedom)','Article 21 (Life & Liberty)','Article 25 (Religion)','Other'] },
                    { id: 'dyn_violation', label: 'Nature of Violation', type: 'text', placeholder: 'How the right was violated' }
                ],
                'PIL': [
                    { id: 'dyn_pi_issue', label: 'Issue / Cause', type: 'text', placeholder: 'Public interest issue' },
                    { id: 'dyn_affected', label: 'Affected Class', type: 'text', placeholder: 'Who is affected' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'Specific relief requested' }
                ]
            }
        },
        'REV': {
            label: 'Revenue & Land',
            subType: { id: 'dyn_sub_type', label: 'Type of Revenue Matter', required: true,
                options: ['Mutation / Inheritance','Land Acquisition Compensation','Revenue Record Correction','Consolidation Dispute','Other'] },
            commonFields: [
                { id: 'dyn_khasra', label: 'Khasra / Khata / Plot No.', type: 'text', placeholder: 'Land identification number' },
                { id: 'dyn_village', label: 'Village', type: 'text', placeholder: 'Village name' },
                { id: 'dyn_tehsil', label: 'Tehsil / District', type: 'text', placeholder: 'Tehsil and district' }
            ],
            subTypeFields: {
                'Mutation / Inheritance': [
                    { id: 'dyn_prev_owner', label: 'Previous Owner', type: 'text', placeholder: 'Name of previous owner' },
                    { id: 'dyn_basis', label: 'Basis of Claim', type: 'select', options: ['Sale Deed','Will','Inheritance','Gift','Other'] }
                ],
                'Land Acquisition Compensation': [
                    { id: 'dyn_award_no', label: 'Award Number', type: 'text', placeholder: 'Compensation award no.' },
                    { id: 'dyn_land_area', label: 'Land Area', type: 'text', placeholder: 'e.g. 2 bigha' },
                    { id: 'dyn_comp_offered', label: 'Compensation Offered (₹)', type: 'number', placeholder: 'Amount offered' },
                    { id: 'dyn_market_value', label: 'Claimed Market Value (₹)', type: 'number', placeholder: 'Value claimed' }
                ],
                'Revenue Record Correction': [
                    { id: 'dyn_doc_type', label: 'Document Type', type: 'text', placeholder: 'e.g. Jamabandi, Girdawari' },
                    { id: 'dyn_error', label: 'Error Details', type: 'text', placeholder: 'What needs correction' }
                ],
                'Consolidation Dispute': [
                    { id: 'dyn_plot_no', label: 'Plot Number', type: 'text', placeholder: 'Consolidated plot no.' },
                    { id: 'dyn_claimant', label: 'Claimant Details', type: 'text', placeholder: 'Name of other claimant' }
                ]
            }
        },
        'MACT': {
            label: 'Motor Accident (MACT)',
            subType: { id: 'dyn_sub_type', label: 'Type of Claim', required: true,
                options: ['Fatal Accident','Permanent Disability','Temporary Disability','Property Damage','Hit and Run','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Date of Accident', type: 'date' },
                { id: 'dyn_accident_location', label: 'Accident Location', type: 'text', placeholder: 'Where the accident occurred' },
                { id: 'dyn_vehicle_no', label: 'Offending Vehicle Number', type: 'text', placeholder: 'e.g. DL 01 AB 1234' },
                { id: 'dyn_insurance', label: 'Insurance Company', type: 'text', placeholder: 'Name of insurer' }
            ],
            subTypeFields: {
                'Fatal Accident': [
                    { id: 'dyn_deceased', label: 'Deceased Name', type: 'text', placeholder: 'Name of deceased' },
                    { id: 'dyn_age', label: 'Age at Death', type: 'number', placeholder: 'Age' },
                    { id: 'dyn_occupation', label: 'Occupation', type: 'text', placeholder: 'e.g. Business, Govt. service' },
                    { id: 'dyn_income', label: 'Monthly Income (₹)', type: 'number', placeholder: 'e.g. 40000' },
                    { id: 'dyn_dependents', label: 'No. of Dependents', type: 'number', placeholder: 'e.g. 4' }
                ],
                'Permanent Disability': [
                    { id: 'dyn_disability_pct', label: 'Disability %', type: 'number', placeholder: 'e.g. 45' },
                    { id: 'dyn_injury', label: 'Nature of Injury', type: 'text', placeholder: 'e.g. Fracture, Amputation' },
                    { id: 'dyn_hospital', label: 'Treating Hospital', type: 'text', placeholder: 'Hospital name' }
                ],
                'Temporary Disability': [
                    { id: 'dyn_injury', label: 'Nature of Injury', type: 'text', placeholder: 'e.g. Fracture, Soft tissue' },
                    { id: 'dyn_treatment_period', label: 'Treatment Period', type: 'text', placeholder: 'e.g. 3 months' },
                    { id: 'dyn_income_loss', label: 'Loss of Income (₹)', type: 'number', placeholder: 'Amount lost' }
                ],
                'Property Damage': [
                    { id: 'dyn_claimant_vehicle', label: "Claimant's Vehicle No.", type: 'text', placeholder: 'Vehicle number' },
                    { id: 'dyn_damage_amount', label: 'Damage Amount (₹)', type: 'number', placeholder: 'Repair / loss amount' }
                ],
                'Hit and Run': [
                    { id: 'dyn_fir', label: 'FIR Filed', type: 'select', options: ['Yes','No'] },
                    { id: 'dyn_witnesses', label: 'Witnesses Available', type: 'select', options: ['Yes','No'] }
                ]
            }
        },
        'MERITS': {
            label: 'Merits Review',
            subType: { id: 'dyn_sub_type', label: 'Type of Review', required: true,
                options: ['Visa Refusal Review','Permit Cancellation Review','Deportation Order Review','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Decision Date', type: 'date' },
                { id: 'dyn_application_no', label: 'Application / File Number', type: 'text', placeholder: 'Reference number' },
                { id: 'dyn_review_body', label: 'Review Body', type: 'text', placeholder: 'e.g. AAT, ITAT' }
            ],
            subTypeFields: {
                'Visa Refusal Review': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'e.g. Student, Work' },
                    { id: 'dyn_grounds', label: 'Grounds of Refusal', type: 'text', placeholder: 'Reason stated' }
                ],
                'Permit Cancellation Review': [
                    { id: 'dyn_permit_type', label: 'Permit Type', type: 'text', placeholder: 'Type of permit' },
                    { id: 'dyn_cancel_date', label: 'Cancellation Date', type: 'date' }
                ],
                'Deportation Order Review': [
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Basis of deportation' },
                    { id: 'dyn_deadline', label: 'Review Deadline', type: 'date' }
                ]
            }
        },
        'JR': {
            label: 'Judicial Review',
            subType: { id: 'dyn_sub_type', label: 'Type of Judicial Review', required: true,
                options: ['Administrative Decision','Tribunal Order','Statutory Body Decision','Other'] },
            commonFields: [
                { id: 'dyn_date_of_incidence', label: 'Decision / Order Date', type: 'date' },
                { id: 'dyn_authority', label: 'Authority / Body', type: 'text', placeholder: 'Whose decision is challenged' },
                { id: 'dyn_court_name', label: 'Court', type: 'text', placeholder: 'e.g. High Court' }
            ],
            subTypeFields: {
                'Administrative Decision': [
                    { id: 'dyn_decision', label: 'Decision Challenged', type: 'text', placeholder: 'Brief description' },
                    { id: 'dyn_grounds', label: 'Grounds for Review', type: 'text', placeholder: 'e.g. Violation of natural justice' }
                ],
                'Tribunal Order': [
                    { id: 'dyn_tribunal', label: 'Tribunal Name', type: 'text', placeholder: 'Name of tribunal' },
                    { id: 'dyn_order_no', label: 'Order Number', type: 'text', placeholder: 'Order reference' },
                    { id: 'dyn_grounds', label: 'Grounds for Review', type: 'text', placeholder: 'Brief grounds' }
                ],
                'Statutory Body Decision': [
                    { id: 'dyn_body', label: 'Statutory Body', type: 'text', placeholder: 'Name of body' },
                    { id: 'dyn_section', label: 'Section / Act', type: 'text', placeholder: 'Relevant section' },
                    { id: 'dyn_relief', label: 'Relief Sought', type: 'text', placeholder: 'What relief is requested' }
                ]
            }
        },
        'NOICC': {
            label: 'Notice of Intention to Consider Cancellation',
            subType: { id: 'dyn_sub_type', label: 'Type of NOICC', required: true,
                options: ['Visa Cancellation','Character / Conduct Grounds','Compliance Failure','Other'] },
            commonFields: [
                { id: 'dyn_notice_date', label: 'Notice Date', type: 'date' },
                { id: 'dyn_response_deadline', label: 'Response Deadline', type: 'date' },
                { id: 'dyn_authority', label: 'Issuing Authority', type: 'text', placeholder: 'Authority name' }
            ],
            subTypeFields: {
                'Visa Cancellation': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Type of visa' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Stated grounds for cancellation' }
                ],
                'Character / Conduct Grounds': [
                    { id: 'dyn_specific_grounds', label: 'Specific Grounds', type: 'text', placeholder: 'Details of character grounds' }
                ],
                'Compliance Failure': [
                    { id: 'dyn_condition', label: 'Condition Breached', type: 'text', placeholder: 'Which condition was breached' }
                ]
            }
        },
        'IMM': {
            label: 'Immigration Matter',
            subType: { id: 'dyn_sub_type', label: 'Type of Immigration Matter', required: true,
                options: ['Work Permit / Employment Visa','Permanent Residency','Student Visa','Visa Refusal / Appeal','Deportation / Removal','Citizenship / Naturalisation','Other'] },
            commonFields: [
                { id: 'dyn_country', label: 'Country', type: 'text', placeholder: 'Destination country' },
                { id: 'dyn_application_no', label: 'Application / File Number', type: 'text', placeholder: 'e.g. IMM/2024/00123' },
                { id: 'dyn_authority', label: 'Authority / Department', type: 'text', placeholder: 'e.g. FRRO, Embassy' }
            ],
            subTypeFields: {
                'Work Permit / Employment Visa': [
                    { id: 'dyn_employer', label: 'Employer Name', type: 'text', placeholder: 'Employer in destination country' },
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Specific visa category' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' }
                ],
                'Permanent Residency': [
                    { id: 'dyn_stream', label: 'Stream / Program', type: 'text', placeholder: 'e.g. Express Entry, PNP' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' },
                    { id: 'dyn_residence_period', label: 'Period of Residence', type: 'text', placeholder: 'e.g. 3 years' }
                ],
                'Student Visa': [
                    { id: 'dyn_institution', label: 'Institution Name', type: 'text', placeholder: 'University / college name' },
                    { id: 'dyn_course', label: 'Course / Program', type: 'text', placeholder: 'e.g. MBA, B.Tech' },
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' }
                ],
                'Visa Refusal / Appeal': [
                    { id: 'dyn_visa_type', label: 'Visa Type', type: 'text', placeholder: 'Type of visa refused' },
                    { id: 'dyn_refusal_date', label: 'Refusal Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds of Refusal', type: 'text', placeholder: 'Reason given' }
                ],
                'Deportation / Removal': [
                    { id: 'dyn_order_date', label: 'Order Date', type: 'date' },
                    { id: 'dyn_grounds', label: 'Grounds', type: 'text', placeholder: 'Basis of deportation' },
                    { id: 'dyn_appeal_deadline', label: 'Appeal Deadline', type: 'date' }
                ],
                'Citizenship / Naturalisation': [
                    { id: 'dyn_application_date', label: 'Application Date', type: 'date' },
                    { id: 'dyn_residence_period', label: 'Period of Residence', type: 'text', placeholder: 'e.g. 5 years' }
                ]
            }
        }
    };

    var matterIconMapJS = {
        'CIV':   { icon: 'fa-balance-scale', color: '#4a6fa5' },
        'CRM':   { icon: 'fa-gavel',         color: '#c0392b' },
        'FAM':   { icon: 'fa-heart',         color: '#e67e22' },
        'PROP':  { icon: 'fa-home',          color: '#27ae60' },
        'CORP':  { icon: 'fa-building',      color: '#8e44ad' },
        'LAB':   { icon: 'fa-briefcase',     color: '#2980b9' },
        'CONS':  { icon: 'fa-shopping-cart', color: '#16a085' },
        'BANK':  { icon: 'fa-university',    color: '#d35400' },
        'TAX':   { icon: 'fa-calculator',    color: '#7f8c8d' },
        'IP':    { icon: 'fa-lightbulb',     color: '#f39c12' },
        'CONST': { icon: 'fa-scroll',        color: '#1a5276' },
        'REV':   { icon: 'fa-map',           color: '#117a65' },
        'MACT':  { icon: 'fa-car-crash',     color: '#922b21' },
        'MERITS':{ icon: 'fa-clipboard-list',color: '#5d6d7e' },
        'JR':    { icon: 'fa-search',        color: '#1f618d' },
        'NOICC': { icon: 'fa-bell',          color: '#b7950b' },
        'IMM':   { icon: 'fa-passport',      color: '#154360' },
    };

    function onMatterDropdownChange(sel) {
        var opt = sel.options[sel.selectedIndex];
        var matterId  = opt.value;
        var matterNick  = opt.getAttribute('data-nick') || '';
        var matterTitle = opt.getAttribute('data-title') || '';

        var preview = document.getElementById('matterDropdownPreview');
        var cta     = document.getElementById('matterDropdownCTA');

        if (!matterId) {
            preview.style.display = 'none';
            cta.style.display     = 'none';
            return;
        }

        // Show icon preview
        var iconData = matterIconMapJS[matterNick] || { icon: 'fa-folder-open', color: '#555' };
        var iconEl   = document.getElementById('matterDropdownIcon');
        var labelEl  = document.getElementById('matterDropdownLabel');
        iconEl.className = 'fas ' + iconData.icon;
        iconEl.style.color = iconData.color;
        labelEl.textContent = matterTitle;
        preview.style.display = 'flex';
        cta.style.display     = 'block';

        var dynSec = document.getElementById('matterDynamicFormSection');
        if (dynSec && dynSec.style.display !== 'none' && typeof rebuildDynOurPartyRole === 'function') {
            rebuildDynOurPartyRole();
        }
    }

    function openMatterFormFromDropdown() {
        var sel = document.getElementById('matterTypeDropdown');
        var opt = sel.options[sel.selectedIndex];
        var matterId    = opt.value;
        var matterNick  = opt.getAttribute('data-nick') || '';
        var matterTitle = opt.getAttribute('data-title') || '';
        if (!matterId) return;
        selectMatterType(parseInt(matterId, 10), matterTitle, matterNick);
    }

    function resetMatterDropdown() {
        var sel = document.getElementById('matterTypeDropdown');
        sel.value = '';
        document.getElementById('matterDropdownPreview').style.display = 'none';
        document.getElementById('matterDropdownCTA').style.display = 'none';
        clearMatterTypeSelection();
    }

    function selectMatterType(matterId, matterTitle, matterNick) {
        selectedMatterTypeId   = matterId;
        selectedMatterTypeNick = matterNick;

        // Update dynamic form title
        var iconData = matterIconMapJS[matterNick] || { icon: 'fa-folder-plus', color: '#3b5bdb' };
        document.getElementById('matterDynamicFormTitle').innerHTML =
            '<i class="fas ' + iconData.icon + '" style="color:' + iconData.color + ';margin-right:6px;"></i> New Matter: ' + matterTitle;

        // Show selected badge
        var badge = document.getElementById('selectedMatterBadge');
        if (badge) {
            badge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px;background:#e8f0fe;color:#3b5bdb;padding:6px 14px;border-radius:20px;font-weight:600;font-size:0.9em;">' +
                '<i class="fas fa-check-circle"></i> ' + matterTitle + '</span>';
        }

        // Build matter-specific fields
        buildMatterSpecificFields(matterNick);

        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (wrap) {
            wrap.innerHTML = '';
            dynAppendOpposingRow('', '');
        }
        rebuildDynOurPartyRole();

        // Show the dynamic form
        document.getElementById('matterDynamicFormSection').style.display = '';
        document.getElementById('matterDynamicFormSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function buildMatterSpecificFields(matterNick) {
        var container = document.getElementById('matterSpecificFields');
        container.innerHTML = '';
        var config = matterSpecificFieldsConfig[matterNick];

        if (!config) {
            container.innerHTML = '<div class="row"><div class="col-md-6"><div class="form-group">' +
                '<label>Date of Incident</label>' +
                '<input type="date" class="form-control dyn-select" id="dyn_date_of_incidence">' +
                '</div></div></div>';
            return;
        }

        var html = '';

        if (config.subType) {
            html += '<div style="background:#f0f4ff;border:1px solid #c5d4f5;border-radius:8px;padding:1rem 1.2rem;margin-bottom:1rem;">';
            html += '<p style="font-weight:600;color:#3b5bdb;margin-bottom:0.8rem;font-size:0.95em;"><i class="fas fa-info-circle"></i> ' + config.label + ' — Specific Details</p>';
            html += '<div class="form-group" style="margin-bottom:0.5rem;">';
            html += '<label>' + config.subType.label;
            if (config.subType.required) html += ' <span class="dyn-required">*</span>';
            html += '</label>';
            html += '<select class="form-control dyn-select" id="' + config.subType.id + '" onchange="onSubTypeChange(\'' + matterNick + '\')"' + (config.subType.required ? ' required' : '') + '>';
            html += '<option value="">— Select ' + config.subType.label + ' —</option>';
            config.subType.options.forEach(function(opt) {
                html += '<option value="' + opt + '">' + opt + '</option>';
            });
            html += '</select>';
            html += '</div></div>';
        }

        if (config.commonFields && config.commonFields.length > 0) {
            html += '<div class="row">';
            config.commonFields.forEach(function(f) {
                html += '<div class="col-md-6"><div class="form-group"><label>' + f.label + '</label>';
                if (f.type === 'select') {
                    html += '<select class="form-control dyn-select" id="' + f.id + '"><option value="">— Select —</option>';
                    (f.options || []).forEach(function(opt) { html += '<option value="' + opt + '">' + opt + '</option>'; });
                    html += '</select>';
                } else {
                    html += '<input type="' + f.type + '" class="form-control dyn-select" id="' + f.id + '" maxlength="255" placeholder="' + (f.placeholder || '') + '">';
                }
                html += '</div></div>';
            });
            html += '</div>';
        }

        html += '<div id="subTypeFieldsContainer"></div>';
        container.innerHTML = html;
    }

    function onSubTypeChange(matterNick) {
        var container = document.getElementById('subTypeFieldsContainer');
        if (!container) return;
        container.innerHTML = '';

        var config = matterSpecificFieldsConfig[matterNick];
        if (!config || !config.subTypeFields) return;

        var subTypeEl = document.getElementById('dyn_sub_type');
        if (!subTypeEl || !subTypeEl.value) return;

        var fields = config.subTypeFields[subTypeEl.value];
        if (!fields || fields.length === 0) return;

        var html = '<div style="background:#fff9f0;border:1px solid #f0d9b5;border-radius:8px;padding:1rem 1.2rem;margin-top:0.5rem;margin-bottom:0.5rem;">';
        html += '<p style="font-weight:600;color:#e67e22;margin-bottom:0.8rem;font-size:0.9em;"><i class="fas fa-clipboard-list"></i> ' + subTypeEl.value + ' — Details</p>';
        html += '<div class="row">';
        fields.forEach(function(f) {
            html += '<div class="col-md-6"><div class="form-group"><label>' + f.label + '</label>';
            if (f.type === 'select') {
                html += '<select class="form-control dyn-select" id="' + f.id + '"><option value="">— Select —</option>';
                (f.options || []).forEach(function(opt) { html += '<option value="' + opt + '">' + opt + '</option>'; });
                html += '</select>';
            } else {
                html += '<input type="' + f.type + '" class="form-control dyn-select" id="' + f.id + '" maxlength="255" placeholder="' + (f.placeholder || '') + '">';
            }
            html += '</div></div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function clearMatterTypeSelection() {
        selectedMatterTypeId   = null;
        selectedMatterTypeNick = null;
        document.getElementById('matterDynamicFormSection').style.display = 'none';
        document.getElementById('matterSpecificFields').innerHTML = '';
        document.getElementById('editAddMatterMsg2').innerHTML = '';
        var wrap = document.getElementById('dyn_opposing_parties_wrap');
        if (wrap) wrap.innerHTML = '';
        var pr = document.getElementById('dyn_our_party_role');
        if (pr) { pr.innerHTML = '<option value="">\u2014</option>'; }
    }

    async function submitDynamicMatter() {
        var msgEl = document.getElementById('editAddMatterMsg2');
        var btn = document.getElementById('dynSubmitMatterBtn');
        msgEl.innerHTML = '';

        if (!selectedMatterTypeId) {
            msgEl.innerHTML = '<div class="alert alert-warning">Please select a matter type first.</div>';
            return;
        }

        var config = matterSpecificFieldsConfig[selectedMatterTypeNick || ''];
        var subTypeEl = document.getElementById('dyn_sub_type');

        if (config && config.subType && config.subType.required) {
            if (!subTypeEl || !subTypeEl.value) {
                msgEl.innerHTML = '<div class="alert alert-warning">Please select the <strong>' + config.subType.label + '</strong> (required).</div>';
                if (subTypeEl) { subTypeEl.focus(); subTypeEl.style.borderColor = '#c0392b'; }
                return;
            }
        }

        var lpEl = document.getElementById('dyn_legal_practitioner');

        var baseCaseDetail = document.getElementById('dyn_case_detail') ? document.getElementById('dyn_case_detail').value.trim() : '';

        var oppRows = [];
        document.querySelectorAll('#dyn_opposing_parties_wrap .dyn-opp-row').forEach(function (row) {
            var n = row.querySelector('.dyn-opp-name');
            var r = row.querySelector('.dyn-opp-role');
            var name = n ? n.value.trim() : '';
            var prole = r ? r.value.trim() : '';
            if (name !== '') oppRows.push({ name: name, party_role: prole });
        });

        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        var clientPk = String(window.currentClientId || '{{ $fetchedData->id }}').trim();
        fd.append('client_id', clientPk);
        fd.append('matter_id', selectedMatterTypeId);
        if (lpEl && lpEl.value) fd.append('legal_practitioner', lpEl.value);
        var doi = document.getElementById('dyn_date_of_incidence');
        if (doi && doi.value) fd.append('date_of_incidence', doi.value);
        if (subTypeEl && subTypeEl.value) fd.append('incidence_type', subTypeEl.value);
        if (baseCaseDetail) fd.append('case_detail', baseCaseDetail);
        var opr = document.getElementById('dyn_our_party_role');
        if (opr && opr.value) fd.append('our_party_role', opr.value);
        fd.append('opposing_parties_json', JSON.stringify(oppRows));

        btn.disabled = true;
        try {
            var res = await fetch(window.storeLeadMatterFromEditUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken },
                body: fd
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Matter created successfully.') + '</div>';
                setTimeout(function() { window.location.reload(); }, 800);
                return;
            }
            var errText = data.message || 'Could not create matter.';
            if (data.errors) errText += ' ' + Object.values(data.errors).flat().join(' ');
            msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        } finally {
            btn.disabled = false;
        }
    }

    // =====================================================
    // Court Hearings CRUD
    // =====================================================
    var hearingStoreUrl = @json(route('clients.courtHearings.store'));
    var hearingClientId = @json($fetchedData->id);

    async function submitHearing() {
        var msgEl = document.getElementById('hearingFormMsg');
        msgEl.innerHTML = '';
        var dateEl = document.getElementById('hearing_date');
        if (!dateEl || !dateEl.value) {
            msgEl.innerHTML = '<div class="alert alert-warning">Hearing Date is required.</div>';
            return;
        }
        var fd = new FormData();
        fd.append('_token', window.editClientConfig.csrfToken);
        fd.append('client_id', hearingClientId);
        fd.append('hearing_date', dateEl.value);
        var timeEl = document.getElementById('hearing_time');
        if (timeEl && timeEl.value) fd.append('hearing_time', timeEl.value);
        var typeEl = document.getElementById('hearing_type');
        if (typeEl && typeEl.value) fd.append('hearing_type', typeEl.value);
        var courtEl = document.getElementById('hearing_court_name');
        if (courtEl && courtEl.value.trim()) fd.append('court_name', courtEl.value.trim());
        var caseEl = document.getElementById('hearing_case_number');
        if (caseEl && caseEl.value.trim()) fd.append('case_number', caseEl.value.trim());
        var judgeEl = document.getElementById('hearing_judge_name');
        if (judgeEl && judgeEl.value.trim()) fd.append('judge_name', judgeEl.value.trim());
        var matterEl = document.getElementById('hearing_matter_id');
        if (matterEl && matterEl.value) fd.append('client_matter_id', matterEl.value);
        var statusEl = document.getElementById('hearing_status');
        if (statusEl && statusEl.value) fd.append('status', statusEl.value);
        var notesEl = document.getElementById('hearing_notes');
        if (notesEl && notesEl.value.trim()) fd.append('notes', notesEl.value.trim());

        try {
            var res = await fetch(hearingStoreUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken },
                body: fd
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">Hearing added successfully!</div>';
                setTimeout(function() { window.location.reload(); }, 700);
                return;
            }
            var errText = (data.message || 'Could not save hearing.');
            if (data.errors) errText += ' ' + Object.values(data.errors).flat().join(' ');
            msgEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
        }
    }

    async function deleteHearing(hearingId) {
        if (!confirm('Delete this court hearing record?')) return;
        var deleteUrl = window.editClientConfig.rootUrl + '/clients/court-hearings/' + hearingId + '/delete';
        try {
            var res = await fetch(deleteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.editClientConfig.csrfToken }
            });
            var data = await res.json().catch(function() { return {}; });
            if (res.ok && data.success) {
                var row = document.getElementById('hearing-row-' + hearingId);
                if (row) row.remove();
                return;
            }
            alert('Could not delete hearing. ' + (data.message || ''));
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }
