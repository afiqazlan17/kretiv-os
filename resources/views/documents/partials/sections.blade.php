{{-- Item rows, grouped under a heading per job when one document covers a
     whole project (numbering runs on across the groups). --}}
@php $start = 0; @endphp
@foreach ($doc['sections'] ?? [['label' => null, 'items' => $doc['items']]] as $section)
    @if ($section['label'])
        <tr class="first last sec"><td></td><td colspan="{{ $cols }}">{{ $section['label'] }}</td></tr>
    @endif
    @include('documents.partials.items', ['items' => $section['items'], 'money' => $money, 'start' => $start])
    @php $start += count($section['items']); @endphp
@endforeach
