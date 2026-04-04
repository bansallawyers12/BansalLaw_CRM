{{-- Occupation Field Component --}}
@props(['index', 'occupation'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Occupation" onclick="removeOccupationField(this)">
        <i class="fas fa-trash"></i>
    </button>
    
    <input type="hidden" name="occupation_id[{{ $index }}]" value="{{ $occupation->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Skill Assessment</label>
            <select name="skill_assessment_hidden[{{ $index }}]" class="skill-assessment-select">
                <option value="">Select</option>
                <option value="Yes" {{ ($occupation->skill_assessment ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
                <option value="No" {{ ($occupation->skill_assessment ?? '') == 'No' ? 'selected' : '' }}>No</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Nominated Occupation</label>
            <input type="text" 
                   name="nomi_occupation[{{ $index }}]" 
                   class="nomi_occupation" 
                   value="{{ $occupation->nomi_occupation ?? '' }}" 
                   placeholder="Enter Occupation">
            <div class="autocomplete-items"></div>
        </div>
        
        <div class="form-group">
            <label>Occupation Code</label>
            <input type="text" 
                   name="occupation_code[{{ $index }}]" 
                   class="occupation_code" 
                   value="{{ $occupation->occupation_code ?? '' }}" 
                   placeholder="Enter Code">
        </div>
        
        <div class="form-group">
            <label>Assessing Authority</label>
            <input type="text" 
                   name="list[{{ $index }}]" 
                   class="list" 
                   value="{{ $occupation->list ?? '' }}" 
                   placeholder="e.g., ACS, VETASSESS">
        </div>
        
        
        <div class="form-group">
            <label>Assessment Date</label>
            <input type="date" 
                   name="dates[{{ $index }}]" 
                   class="dates" 
                   value="{{ $occupation && $occupation->dates ? date('Y-m-d', strtotime($occupation->dates)) : '' }}">
        </div>
        
        <div class="form-group">
            <label>Expiry Date</label>
            <input type="date" 
                   name="expiry_dates[{{ $index }}]" 
                   class="expiry_dates" 
                   value="{{ $occupation && $occupation->expiry_dates ? date('Y-m-d', strtotime($occupation->expiry_dates)) : '' }}">
        </div>
        
        <div class="form-group">
            <label>Reference No</label>
            <input type="text" 
                   name="occ_reference_no[{{ $index }}]" 
                   value="{{ $occupation->occ_reference_no ?? '' }}" 
                   placeholder="Enter Reference No.">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label style="margin-bottom: 0;">Relevant Occupation</label>
            <input type="checkbox" 
                   name="relevant_occupation_hidden[{{ $index }}]" 
                   value="1" 
                   {{ ($occupation->relevant_occupation ?? false) ? 'checked' : '' }} 
                   style="margin-left: 10px;">
        </div>
    </div>
</div>
