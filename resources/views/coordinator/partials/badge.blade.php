{{-- Status badge for the coordinator module's enums. $kind: stage | drive | plc | req, $value: enum value. (Record, document and section badges come from StudentUi::badge.) --}}
@php
  $map = [
    'stage' => ['APPLIED' => ['neutral', 'Applied'], 'SHORTLISTED' => ['wait', 'Shortlisted'], 'APTITUDE' => ['wait', 'Aptitude'], 'TECHNICAL' => ['wait', 'Technical'], 'HR' => ['wait', 'HR'], 'SELECTED' => ['ok', 'Selected'], 'REJECTED' => ['no', 'Rejected'], 'OFFER_RECEIVED' => ['ok', 'Offer received'], 'PLACED' => ['ok', 'Placed'], 'WITHDRAWN' => ['neutral', 'Withdrawn']],
    'drive' => ['DRAFT' => ['neutral', 'Draft'], 'PUBLISHED' => ['ok', 'Published'], 'CLOSED' => ['neutral', 'Closed'], 'CANCELLED' => ['no', 'Cancelled']],
    'plc' => ['OFFERED' => ['wait', 'Offered'], 'PLACED' => ['ok', 'Placed'], 'DECLINED' => ['neutral', 'Declined']],
    'req' => ['OPEN' => ['wait', 'Open'], 'FULFILLED' => ['ok', 'Fulfilled'], 'CANCELLED' => ['neutral', 'Cancelled']],
  ];
  [$variant, $label] = $map[$kind][$value] ?? ['neutral', (string) $value];
@endphp
<span class="badge b-{{ $variant }}">{{ $label }}</span>
