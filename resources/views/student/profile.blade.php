@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Profile')

@section('content')
@php
  $locked = ! empty($student['profile_locked']);
  $confirmed = ! empty($student['identity_confirmed_at']);
  $opted = ! empty($student['opted_out_of_placement']);
  $yesterday = date('Y-m-d', strtotime('yesterday'));
  $status = fn ($key) => U::section($completeness, $key)['status'];
  $other = [['C_academic', 'student.academic', 'sec-academic', 'Academic records'], ['E_experience', 'student.experience', 'sec-experience', 'Experience'], ['G_resume', 'student.documents', 'sec-upload', 'Resume']];
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Profile</h1>
      <p class="meta">Sections A, B, D and F. Academic records, experience and resume have their own pages.</p>
    </div>
  </div>

  <div class="stack">
    @include('student.partials.locked-banner')

    {{-- A · Identity --}}
    <section class="card" id="sec-identity" aria-labelledby="h-identity">
      <form data-ajax="profile" novalidate>
        <div class="card-h"><h2 id="h-identity">A &middot; Identity</h2>{!! U::badge('sec', $status('A_identity')) !!}</div>
        <dl class="dl" style="margin:0 0 16px">
          <div><dt>University ID</dt><dd>{{ $student['university_id'] }}</dd></div>
          <div><dt>Full name</dt><dd>{{ $student['full_name'] }}</dd></div>
          <div><dt>Department</dt><dd>{{ $student['department']['name'] }}</dd></div>
          <div><dt>Branch</dt><dd>{{ $student['branch']['name'] }}</dd></div>
          <div><dt>Admission type</dt><dd>{{ U::admission($student['admission_type']) }}</dd></div>
          <div><dt>Admission year</dt><dd>{{ $student['admission_year'] }}</dd></div>
          <div><dt>Graduation year</dt><dd>{{ $student['graduation_year'] }}</dd></div>
          <div><dt>Current semester</dt><dd>{{ $student['current_semester'] }}</dd></div>
        </dl>
        <p class="meta" style="margin-bottom:24px">These details come from the university master list and cannot be edited here.</p>
        <div class="fgrid">
          @include('student.partials.input', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'value' => $student['date_of_birth'] ?? '', 'min' => '1960-01-02', 'maxv' => $yesterday, 'req' => true, 'disabled' => $locked])
          <div class="field" data-field="gender">
            <label for="f-gender">Gender</label>
            <select id="f-gender" name="gender" data-nullable @disabled($locked)>
              <option value="">Select</option>
              @foreach (U::GENDER as $code => $name)
                <option value="{{ $code }}"@if (($student['gender'] ?? null) === $code) selected @endif>{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <div class="full" data-field="confirm_identity">
            @if ($confirmed)
              <p>{!! U::icon('check', 'sm') !!} Identity confirmed on {{ U::date($student['identity_confirmed_at']) }}</p>
            @else
              <label class="chk"><input type="checkbox" id="f-confirm_identity" name="confirm_identity" @disabled($locked)> I confirm that the identity details above are correct.</label>
            @endif
          </div>
        </div>
        <div style="margin-top:24px" class="row wrap"><button type="submit" class="btn btn-primary" @disabled($locked)>Save changes</button></div>
      </form>
    </section>

    {{-- B · Contact --}}
    <section class="card" id="sec-contact" aria-labelledby="h-contact">
      <form data-ajax="profile" novalidate>
        <div class="card-h"><h2 id="h-contact">B &middot; Contact</h2>{!! U::badge('sec', $status('B_contact')) !!}</div>
        <div class="fgrid">
          @include('student.partials.input', ['name' => 'personal_email', 'label' => 'Personal email', 'type' => 'email', 'value' => $student['personal_email'] ?? '', 'max' => 190, 'req' => true, 'disabled' => $locked])
          @include('student.partials.input', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'value' => $student['phone'] ?? '', 'inputmode' => 'tel', 'ph' => '+919876543210', 'req' => true, 'hint' => '10 digits starting with 6 to 9, optional +91.', 'disabled' => $locked])
          @include('student.partials.input', ['name' => 'current_city', 'label' => 'Current city', 'value' => $student['current_city'] ?? '', 'max' => 100, 'req' => true, 'disabled' => $locked])
          @include('student.partials.input', ['name' => 'permanent_city', 'label' => 'Permanent city', 'value' => $student['permanent_city'] ?? '', 'max' => 100, 'req' => true, 'disabled' => $locked])
        </div>
        <div style="margin-top:24px" class="row wrap"><button type="submit" class="btn btn-primary" @disabled($locked)>Save changes</button></div>
      </form>
    </section>

    {{-- D · Skills and links --}}
    <section class="card" id="sec-skills" aria-labelledby="h-skills">
      <form data-ajax="profile" novalidate>
        <div class="card-h"><h2 id="h-skills">D &middot; Skills and links</h2>{!! U::badge('sec', $status('D_skills_links')) !!}</div>
        <div class="fgrid">
          <div class="field full" data-field="skills">
            <label for="f-skills">Skills <span class="req" aria-hidden="true">*</span></label>
            @include('student.partials.tags', ['name' => 'skills', 'label' => 'skill', 'values' => $student['skills'] ?? [], 'max' => 30, 'len' => 40, 'disabled' => $locked])
            <span class="hint">Up to 30 skills. Press Enter after each one.</span>
          </div>
          <div class="field full" data-field="languages">
            <label for="f-languages">Languages</label>
            @include('student.partials.tags', ['name' => 'languages', 'label' => 'language', 'values' => $student['languages'] ?? [], 'max' => 10, 'len' => 40, 'disabled' => $locked])
            <span class="hint">Up to 10 languages.</span>
          </div>
          @include('student.partials.input', ['name' => 'github_url', 'label' => 'GitHub', 'type' => 'url', 'value' => $student['github_url'] ?? '', 'ph' => 'https://github.com/…', 'nullable' => true, 'disabled' => $locked])
          @include('student.partials.input', ['name' => 'linkedin_url', 'label' => 'LinkedIn', 'type' => 'url', 'value' => $student['linkedin_url'] ?? '', 'ph' => 'https://www.linkedin.com/in/…', 'nullable' => true, 'disabled' => $locked])
          @include('student.partials.input', ['name' => 'portfolio_url', 'label' => 'Portfolio', 'type' => 'url', 'value' => $student['portfolio_url'] ?? '', 'ph' => 'https://…', 'nullable' => true, 'disabled' => $locked])
        </div>
        <div style="margin-top:24px" class="row wrap"><button type="submit" class="btn btn-primary" @disabled($locked)>Save changes</button></div>
      </form>
    </section>

    {{-- F · Preferences --}}
    <section class="card" id="sec-preferences" aria-labelledby="h-preferences">
      <form data-ajax="profile" novalidate>
        <div class="card-h"><h2 id="h-preferences">F &middot; Preferences</h2>{!! U::badge('sec', $status('F_preferences')) !!}</div>
        <div class="fgrid">
          <div class="field full" data-field="preferred_roles">
            <label for="f-preferred_roles">Preferred roles</label>
            @include('student.partials.tags', ['name' => 'preferred_roles', 'label' => 'role', 'values' => $student['preferred_roles'] ?? [], 'max' => 10, 'len' => 60, 'disabled' => $locked])
            <span class="hint">Up to 10 roles.</span>
          </div>
          <div class="field full" data-field="preferred_locations">
            <label for="f-preferred_locations">Preferred locations</label>
            @include('student.partials.tags', ['name' => 'preferred_locations', 'label' => 'location', 'values' => $student['preferred_locations'] ?? [], 'max' => 10, 'len' => 60, 'disabled' => $locked])
            <span class="hint">Up to 10 locations.</span>
          </div>
          <div class="full"><label class="chk"><input type="checkbox" id="f-opted_out_of_placement" name="opted_out_of_placement" data-optout @checked($opted) @disabled($locked)> Opt out of placement</label></div>
          <div class="field full" data-field="opt_out_reason">
            <label for="f-opt_out_reason">Reason for opting out <span class="req" data-reason-req aria-hidden="true"@if (! $opted) hidden @endif>*</span></label>
            <textarea id="f-opt_out_reason" name="opt_out_reason" maxlength="500" data-nullable @disabled(! $opted || $locked)>{{ $student['opt_out_reason'] ?? '' }}</textarea>
          </div>
        </div>
        <div style="margin-top:24px" class="row wrap"><button type="submit" class="btn btn-primary" @disabled($locked)>Save changes</button></div>
      </form>
    </section>

    <section class="card">
      <div class="card-h"><h2>Other sections</h2></div>
      <div class="list">
        @foreach ($other as [$key, $route, $anchor, $name])
          @php
  $sec = U::section($completeness, $key);
@endphp
          <div class="li">
            <div class="l"><div class="t">{{ $sec['letter'] }} &middot; {{ $name }}</div><div class="meta">{{ $sec['hint'] }}</div></div>
            <div class="r">{!! U::badge('sec', $sec['status']) !!}<a class="btn btn-secondary" href="{{ route($route) }}#{{ $anchor }}">Open</a></div>
          </div>
        @endforeach
      </div>
    </section>
  </div>
</div>
@endsection
