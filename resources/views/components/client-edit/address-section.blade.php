{{-- Current Address Section Component --}}
@props(['clientAddresses', 'searchRoute', 'detailsRoute', 'csrfToken'])

@php
    $currentAddress = collect($clientAddresses)->first();
@endphp

<section id="section-address" class="form-section">
    <div class="section-header">
        <h3><i class="fas fa-home"></i> Current Address</h3>
        <div class="section-actions">
            <button type="button" class="edit-section-btn" onclick="toggleEditMode('addressInfo')" title="Edit Address">
                <i class="fas fa-pen"></i>
            </button>
            @if($currentAddress)
            <button type="button" class="delete-section-btn" onclick="window.deleteCurrentAddress()" title="Delete Current Address">
                <i class="fas fa-trash"></i>
            </button>
            @endif
        </div>
    </div>
    
    {{-- Summary View --}}
    <div id="addressInfoSummary" class="summary-view">
        @if($currentAddress)
            <div class="address-entry-compact">
                <div class="address-compact-grid">
                    <div class="summary-item-inline">
                        <span class="summary-label">ADDRESS:</span>
                        <span class="summary-value">
                            @php
                                $addressParts = array_filter([
                                    $currentAddress->address_line_1,
                                    $currentAddress->address_line_2,
                                    $currentAddress->suburb,
                                    $currentAddress->state,
                                    $currentAddress->zip,
                                    ($currentAddress->country && $currentAddress->country !== 'Australia') ? $currentAddress->country : null
                                ]);
                                
                                if (!empty($addressParts)) {
                                    echo implode(', ', $addressParts);
                                } elseif ($currentAddress->address) {
                                    echo $currentAddress->address;
                                } else {
                                    echo 'Not set';
                                }
                            @endphp
                        </span>
                    </div>
                    @if($currentAddress->regional_code)
                    <div class="summary-item-inline">
                        <span class="summary-label">REGIONAL CODE:</span>
                        <span class="summary-value strong">{{ $currentAddress->regional_code }}</span>
                    </div>
                    @endif
                </div>
            </div>
        @else
            <div class="empty-state">
                <p>No current address on file.</p>
            </div>
        @endif
    </div>

    {{-- Edit View --}}
    <div id="addressInfoEdit" 
         class="edit-view hidden" 
         data-search-route="{{ $searchRoute }}"
         data-details-route="{{ $detailsRoute }}"
         data-csrf-token="{{ $csrfToken }}"
         data-address-count="{{ $currentAddress ? 1 : 0 }}">
        
        <div id="addresses-container">
            <x-client-edit.address-field 
                :index="0" 
                :address="$currentAddress" 
            />
        </div>

        <div class="edit-actions">
            <button type="button" class="btn btn-primary" onclick="saveAddressInfo()">Save</button>
            <button type="button" class="btn btn-secondary" onclick="cancelEdit('addressInfo')">Cancel</button>
        </div>
    </div>
</section>
