@php
    $flashes = [
        'status' => 'success',
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'error',
    ];
@endphp

<div class="zn-wrap">
    @foreach ($flashes as $key => $tone)
        @if (session($key))
            <div class="zn-flash zn-flash--{{ $tone }}" role="status">
                <span style="flex: 1;">{{ session($key) }}</span>
                <button type="button" aria-label="Dismiss notification" onclick="this.parentNode.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 18px; padding: 0 4px;">&times;</button>
            </div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="zn-flash zn-flash--error" role="alert">
            <ul style="margin: 0; padding-left: 20px; flex: 1;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" aria-label="Dismiss alert" onclick="this.parentNode.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 18px; padding: 0 4px;">&times;</button>
        </div>
    @endif
</div>
