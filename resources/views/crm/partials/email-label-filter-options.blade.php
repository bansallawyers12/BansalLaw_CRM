@php
    $excludeMailFolderLabels = ! empty($excludeMailFolderLabels);
    $filterLabels = collect($emailLabelsForFilter ?? [])->filter(function ($label) use ($excludeMailFolderLabels) {
        if (! $excludeMailFolderLabels) {
            return true;
        }
        $name = strtolower(trim((string) ($label->name ?? '')));

        return ! in_array($name, ['inbox', 'sent'], true);
    });
    $systemFilterLabels = $filterLabels->where('type', 'system')->values();
    $customFilterLabels = $filterLabels->where('type', '!=', 'system')->values();
@endphp
@if($systemFilterLabels->isNotEmpty())
    <optgroup label="System labels">
        @foreach($systemFilterLabels as $label)
            <option value="{{ $label->id }}" data-label-type="system">{{ $label->name }}</option>
        @endforeach
    </optgroup>
@endif
@if($customFilterLabels->isNotEmpty())
    <optgroup label="Custom labels">
        @foreach($customFilterLabels as $label)
            <option value="{{ $label->id }}" data-label-type="custom">{{ $label->name }}</option>
        @endforeach
    </optgroup>
@endif
