{{-- A file as a card: name, size, version, status, and the audited download link. $doc = DocumentResource array or null. --}}
@use('App\Support\StudentUi', 'U')
@if ($doc)
  <div class="doc-box">
    <div>
      <b>{{ U::docType($doc['document_type']) }}</b>
      <div class="meta">{{ $doc['original_name'] }} &middot; {{ U::size($doc['size_bytes']) }} &middot; Version {{ $doc['version'] }}</div>
    </div>
    <div class="row wrap">
      {!! U::badge('doc', $doc['status']) !!}
      <a class="btn btn-secondary" href="{{ route('coordinator.documents.download', $doc['uuid']) }}" target="_blank" rel="noopener">{!! U::icon('down', 'sm') !!} Open file</a>
    </div>
  </div>
@else
  <p class="meta">No file is attached.</p>
@endif
