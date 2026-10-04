@props(['id', 'cols', 'selectable' => false])
{{--
  Responsive table of the design (.tbl). `cols` are the header labels; an empty string is an action column.
  data-cols lets TPMS.fillRows() add the data-l labels the stacked mobile layout needs.
  selectable=true adds a leading select-all checkbox column (#{id}-all).
--}}
@php($labels = $selectable ? array_merge([''], $cols) : $cols)
<div class="tbl-wrap">
  <table class="tbl" data-cols='@json($labels)'>
    <thead>
      <tr>
        @if ($selectable)
          <th class="sel"><input type="checkbox" id="{{ $id }}-all" aria-label="Select all rows"></th>
        @endif
        @foreach ($cols as $col)
          <th>@if ($col === '')<span class="sr">Actions</span>@else{{ $col }}@endif</th>
        @endforeach
      </tr>
    </thead>
    <tbody id="{{ $id }}"><tr><td colspan="{{ count($labels) }}"><div class="skel" style="width:60%"></div></td></tr></tbody>
  </table>
</div>
