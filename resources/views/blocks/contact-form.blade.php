@php
    use App\Cms\Forms\ContactFormSchema;

    $action = safe_route('contact.submit', [], '#');
    $uid = $context['id'] ?? uniqid();

    // Several forms can sit on one page, and a redirect back carries only one
    // set of errors. Both are tagged with the form that was posted, so the
    // other forms on the page stay quiet.
    $bag = $errors ?? null;
    $posted = $bag !== null && old('_form') === $uid;
    $sent = session('contact_form') === $uid && session('status');

    // Half width fields pair up into rows; a full width one stands alone.
    $rows = [];

    foreach ($schema['fields'] as $field) {
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        if ($field['width'] === 'half' && $last !== null && count($last) === 1 && $last[0]['width'] === 'half') {
            $rows[array_key_last($rows)][] = $field;

            continue;
        }

        $rows[] = [$field];
    }
@endphp

<form class="cb-form" method="POST" action="{{ $action }}">
    @csrf
    {{-- Honeypot: invisible to people, irresistible to bots. --}}
    <input type="text" name="website" tabindex="-1" autocomplete="off" class="cb-hp" aria-hidden="true">
    <input type="hidden" name="_form" value="{{ $uid }}">
    <input type="hidden" name="{{ ContactFormSchema::TOKEN_INPUT }}" value="{{ $schemaToken }}">

    @if ($sent)
        <p class="cb-form__status" role="status">{{ session('status') }}</p>
    @endif

    @foreach ($rows as $row)
        <div class="cb-form__row @if (count($row) === 1) cb-form__row--single @endif">
            @foreach ($row as $field)
                @php
                    $name = ContactFormSchema::htmlName($field);
                    $input = ContactFormSchema::inputName($field);
                    $id = 'cb-'.$uid.'-'.$field['key'];
                    $value = $posted ? old($input) : null;
                    $error = $posted ? $bag->first($input) : null;
                @endphp

                @if ($field['type'] === 'checkbox')
                    <div class="cb-form__field cb-form__field--check">
                        <label for="{{ $id }}">
                            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
                                   @checked($value)
                                   @required($field['required'])>
                            <span>{{ $field['label'] }}@if ($field['required'])<em class="cb-form__req" aria-hidden="true">*</em>@endif</span>
                        </label>
                        @if ($error)
                            <p class="cb-form__error">{{ $error }}</p>
                        @endif
                    </div>
                @else
                    <label class="cb-form__field" for="{{ $id }}">
                        <span>
                            {{ $field['label'] }}
                            @if ($field['required'])<em class="cb-form__req" aria-hidden="true">*</em>@endif
                        </span>

                        @if ($field['type'] === 'textarea')
                            <textarea id="{{ $id }}" name="{{ $name }}" rows="5"
                                      @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif
                                      @required($field['required'])>{{ $value }}</textarea>
                        @elseif ($field['type'] === 'select')
                            <select id="{{ $id }}" name="{{ $name }}" @required($field['required'])>
                                <option value="">{{ $field['placeholder'] ?: 'Choose one' }}</option>
                                @foreach ($field['options'] as $option)
                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="{{ $field['type'] }}" id="{{ $id }}" name="{{ $name }}"
                                   value="{{ $value }}"
                                   @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif
                                   @required($field['required'])>
                        @endif

                        @if ($error)
                            <span class="cb-form__error">{{ $error }}</span>
                        @endif
                    </label>
                @endif
            @endforeach
        </div>
    @endforeach

    <div class="cb-form__actions">
        <button type="submit" class="cb-button cb-button--md">
            {{ $settings['button_text'] ?? 'Send message' }}
        </button>
    </div>
</form>
