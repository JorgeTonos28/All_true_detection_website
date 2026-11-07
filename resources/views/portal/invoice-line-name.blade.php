@if(!empty($line['option_name']))
    <strong>{{ $line['option_name'] }}</strong><br><small>{{ $line['service_name'] }}</small>
@else
    {{ $line['service_name'] }}
@endif
