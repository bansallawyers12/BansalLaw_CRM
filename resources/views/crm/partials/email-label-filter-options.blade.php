@php
    $excludeMailFolderLabels = ! empty($excludeMailFolderLabels);
    $includeMailFolderFilterOptions = ! empty($includeMailFolderFilterOptions);
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
@if($includeMailFolderFilterOptions)
    <optgroup label="Mail folder">
        <option value="__mail_all__" data-mail-folder="all" selected>All mail</option>
        <option value="__mail_inbox__" data-mail-folder="inbox">Incoming</option>
        <option value="__mail_sent__" data-mail-folder="sent">Sent</option>
    </optgroup>
@endif
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
