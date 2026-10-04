@extends('layouts.auth')
@section('title', 'Create account')

@section('content')
  <h1>Create your account</h1>
  <p class="meta">Use the university ID and email on record with the T&amp;P office.</p>
  <form method="post" action="{{ url('register') }}" novalidate>
    @csrf
    <div class="field{{ $errors->has('university_id') ? ' bad' : '' }}">
      <label for="university_id">University ID</label>
      <input id="university_id" name="university_id" type="text" value="{{ old('university_id') }}" maxlength="30" autocomplete="off" required autofocus @if ($errors->has('university_id')) aria-invalid="true" aria-describedby="university_id-e"@endif>
      @if ($errors->has('university_id'))<span class="err" id="university_id-e" role="alert">{{ $errors->first('university_id') }}</span>@endif
    </div>
    <div class="field{{ $errors->has('email') ? ' bad' : '' }}">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-e"@endif>
      @if ($errors->has('email'))<span class="err" id="email-e" role="alert">{{ $errors->first('email') }}</span>@endif
    </div>
    <div class="field{{ $errors->has('password') ? ' bad' : '' }}">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-e"@endif>
      @if ($errors->has('password'))<span class="err" id="password-e" role="alert">{{ $errors->first('password') }}</span>@else<span class="hint">At least 10 characters with upper and lower case letters and a number.</span>@endif
    </div>
    <div class="field">
      <label for="password_confirmation">Confirm password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn btn-primary">Create account</button>
  </form>
  <p class="alt">Already registered? <a href="{{ route('login') }}">Sign in</a>.</p>
@endsection
