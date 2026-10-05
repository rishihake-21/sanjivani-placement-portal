{{-- Field table. $fields = list of ['label','cur','prev'] (strings), $hasPrev = show the "Currently verified" column and mark changes. --}}
<table class="cmp">
  <thead><tr><th>Field</th>@if ($hasPrev)<th>Currently verified</th>@endif<th>{{ $hasPrev ? 'Submitted' : 'Value' }}</th></tr></thead>
  <tbody>
    @foreach ($fields as $f)
      <tr>
        <td class="fn">{{ $f['label'] }}</td>
        @if ($hasPrev)<td>{{ $f['prev'] }}</td>@endif
        <td @if ($hasPrev && $f['prev'] !== $f['cur'])class="chg"@endif>{{ $f['cur'] }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
