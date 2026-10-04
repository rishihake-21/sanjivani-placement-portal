{{-- A document as a download link. $doc is a DocumentResource array or null. --}}
@if ($doc)<a class="linkbtn" href="{{ route('student.documents.download', $doc['uuid']) }}">{{ $doc['original_name'] }}</a>@else<span class="meta">No file</span>@endif
