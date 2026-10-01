@foreach($emailLabelsForFilter ?? [] as $label)
    @php
        $mailFolderAttr = ($label->type === 'system' && in_array(strtolower($label->name), ['inbox', 'sent'], true))
            ? strtolower($label->name)
            : null;
    @endphp
    <option value="{{ $label->id }}"@if($mailFolderAttr) data-mail-folder="{{ $mailFolderAttr }}"@endif>{{ $label->name }}</option>
@endforeach
