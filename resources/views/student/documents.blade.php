@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Documents')

@section('content')
@php
  $documents = array_values($documents ?? []);
  $locked = ! empty($student['profile_locked']);
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Documents</h1>
      <p class="meta">Marksheets and certificates are uploaded from their own record. Resume and other certificates are uploaded here.</p>
    </div>
  </div>

  <div class="stack">
    @include('student.partials.locked-banner')

    <section class="card" id="sec-upload" aria-labelledby="h-up">
      <div class="card-h"><h2 id="h-up">Upload a document</h2></div>
      <form data-ajax="upload" data-store="{{ route('student.x.documents.store') }}" novalidate>
        <div class="fgrid">
          <div class="field" data-field="document_type">
            <label for="up-type">Document type</label>
            <select id="up-type" name="document_type" data-upload-type @disabled($locked)>
              <option value="RESUME">Resume</option>
              <option value="CERT_OTHER">Other certificate</option>
            </select>
          </div>
          <div class="field" data-field="document">
            <label for="up-file">File</label>
            @include('student.partials.file-box', ['id' => 'up-file', 'name' => 'document', 'disabled' => $locked])
            <span class="hint">PDF, JPG or PNG, up to 5 MB.</span>
          </div>
          <div class="full" data-resume-note>
            @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'info', 'text' => 'If your current resume is approved, it stays primary until the new one is approved.'])
          </div>
        </div>
        <div style="margin-top:16px"><button type="submit" class="btn btn-primary" @disabled($locked)>{!! U::icon('up', 'sm') !!} Upload</button></div>
      </form>
    </section>

    <section class="card" aria-labelledby="h-docs">
      <div class="card-h">
        <h2 id="h-docs">Your documents</h2>
        <label class="chk"><input type="checkbox" data-nav-param="history" @checked($history)> Show replaced versions</label>
      </div>
      @if (count($documents))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Document</th><th>Version</th><th>Size</th><th>Status</th><th>Uploaded</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($documents as $d)
                <tr>
                  <td data-l="Document"><b>{{ U::docType($d['document_type']) }}</b>@if ($d['is_primary']) <span class="tag">Primary</span>@endif<span class="sub">{{ $d['original_name'] }}</span></td>
                  <td data-l="Version" class="num">{{ $d['version'] }}</td>
                  <td data-l="Size" class="num">{{ U::size($d['size_bytes']) }}</td>
                  <td data-l="Status"><div class="stk">{!! U::badge('doc', $d['status']) !!}@include('student.partials.rej-why', ['rej' => $d['rejection'] ?? null])</div></td>
                  <td data-l="Uploaded">{{ U::date($d['uploaded_at']) }}</td>
                  <td class="act"><a class="btn btn-secondary" href="{{ route('student.documents.download', $d['uuid']) }}">{!! U::icon('down', 'sm') !!} Download</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">No documents uploaded yet.</p>
      @endif
    </section>
  </div>
</div>
@endsection
