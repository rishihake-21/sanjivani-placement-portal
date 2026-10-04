<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TPMS Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Public Sans', system-ui, sans-serif; }
    </style>
</head>
<body>
<div style="display:flex;height:100vh;background:#fff">
    <div style="flex:1;background:#3f4550;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:64px">
        <div style="display:flex;align-items:center;gap:16px">
            <img src="{{ asset('images/sanjivani logo.png') }}" alt="Sanjivani University" style="width:64px;height:64px;object-fit:contain;">
            <div style="font-weight:700;font-size:20px;letter-spacing:.04em">SANJIVANI UNIVERSITY</div>
        </div>
        <div>
            <h1 style="margin:0 0 16px;font-size:44px;line-height:1.15">Training & Placement<br>Management System</h1>
            <p style="margin:0;max-width:480px;font-size:18px;line-height:1.5;color:#d5d9e0">Build a verified profile, track your applications, and let the T&P cell see only what has been checked.</p>
        </div>
        <div style="font-size:14px;color:#d5d9e0">&copy; 2026 Sanjivani University</div>
    </div>
    <div style="width:520px;box-sizing:border-box;padding:96px 64px;max-width:100%;overflow-y:auto">
        <h2 style="margin:0 0 8px;font-size:28px">Sign in</h2>
        <p style="margin:0 0 8px;color:#4b5563;font-size:15px">Use your institutional account.</p>
        
        <form method="POST" action="{{ route('login') }}">
            @csrf
            
            <fieldset style="border:0;padding:0;margin:20px 0 0">
                <legend style="font-size:14px;font-weight:600;padding:0;margin-bottom:8px">Role</legend>
                <div style="display:flex;flex-wrap:wrap;gap:8px 20px">
                    <label class="rd"><input type="radio" name="role" value="student" checked> Student</label>
                    <label class="rd"><input type="radio" name="role" value="tp_coordinator"> T&P Coordinator</label>
                    <label class="rd"><input type="radio" name="role" value="tpo"> TPO</label>
                    <label class="rd"><input type="radio" name="role" value="system_admin"> System Admin</label>
                </div>
            </fieldset>
            
            <label class="lb" for="email">Institutional email</label>
            <input class="in" id="email" type="email" name="email" placeholder="name@sanjivani.edu.in" required autocomplete="email" value="{{ old('email') }}">
            
            <label class="lb" for="password">Password</label>
            <div style="position:relative">
                <input class="in" id="password" type="password" name="password" required autocomplete="current-password">
                <label class="rd" style="margin-top:12px;cursor:pointer">
                    <input type="checkbox" id="showPassword" onclick="togglePassword()"> Show password
                </label>
            </div>
            
            @if ($errors->any())
                <div style="margin-top:16px;background:#fee2e2;color:#991b1b;border-radius:4px;padding:12px;font-size:14px">
                    <ul style="margin:0;padding-left:20px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <div style="display:flex;gap:12px;margin-top:28px">
                <button type="submit" class="bt" style="background:#0f7a6b">Login</button>
                <button type="button" class="bt" style="background:#b3261e" onclick="window.location.href='{{ route('password.request') }}'">Forgot Password</button>
            </div>
        </form>
        
        <p style="margin:28px 0 0;font-size:15px">New student? <a href="{{ route('register') }}">Register with your university ID</a></p>
        <div style="margin-top:28px;background:#e3edf9;color:#1b3f6b;border-radius:4px;padding:16px;font-size:14px;line-height:1.5">Registration works only for IDs on the university master list. Contact your department T&P Coordinator if your ID is not found.</div>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const checkbox = document.getElementById('showPassword');
        passwordInput.type = checkbox.checked ? 'text' : 'password';
    }
</script>

<style>
    .in{width:100%;box-sizing:border-box;height:44px;border:1px solid #c9ced8;border-radius:4px;padding:0 12px;font:inherit;background:#eef3fb}
    .lb{display:block;font-size:14px;font-weight:600;margin:20px 0 6px}
    .bt{height:44px;border:0;border-radius:4px;padding:0 28px;font:inherit;font-weight:600;color:#fff;cursor:pointer}
    .rd{display:flex;align-items:center;gap:6px;font-size:14px}
    a{color:#0f6f61}a:hover{color:#0a4d43}
</style>
</body>
</html>