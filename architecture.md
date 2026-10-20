# UMS Sanjivani — Evidence-Based Architecture Report

> **Subject**: `https://ums.sanjivani.edu.in/`
> **Evidence source**: Login page HTML source (`Login.aspx`), public web search results, CDN asset inspection.
> **Date**: 2026-10-17
> **Constraint**: No server-side source code access. No authentication bypass attempted.

---

## Classification Key

| Tag | Meaning |
|-----|---------|
| ✅ **Confirmed** | Direct, unambiguous evidence in the HTML source or publicly observable artefact |
| 🔶 **Strongly inferred** | Conclusion drawn from multiple corroborating signals, but not directly proven |
| ❓ **Unknown** | Insufficient evidence; listed so that targeted investigation can fill the gap |

---

## 1. Platform Identity

| Aspect | Value | Classification | Evidence |
|--------|-------|----------------|----------|
| Product name | **GNUMS** (GN University Management Software) | ✅ Confirmed | `<title>Sanjivani University \| GNUMS Login</title>` |
| Vendor | **GNWebSoft Pvt. Ltd.** | ✅ Confirmed | CDN domain `cdn.gnums.in`; vendor website `gnwebsoft.com` |
| Deployment model | Multi-tenant SaaS — shared CDN, per-institution subdomain | 🔶 Strongly inferred | Static assets served from `cdn.gnums.in/Default/…` (not the institution's own domain); multiple universities listed on vendor site |
| Product version | ❓ Unknown | ❓ Unknown | No version string found in HTML source or asset URLs |

---

## 2. Server-Side Stack

### 2.1 Application Framework — ✅ Confirmed: ASP.NET WebForms

The login page contains **twelve independent fingerprints** that collectively leave zero doubt:

| # | Fingerprint | Source |
|---|-------------|--------|
| 1 | Page extension `.aspx` | `action="./Login.aspx"` |
| 2 | `__VIEWSTATE` hidden field (~25 KB Base64 blob) | `<input type="hidden" name="__VIEWSTATE" …>` |
| 3 | `__VIEWSTATEENCRYPTED` | Empty hidden field — ViewState encryption is enabled |
| 4 | `__VIEWSTATEGENERATOR` | Value `C2EE9ABB` |
| 5 | `__EVENTVALIDATION` | Hidden field — anti-tampering for postback events |
| 6 | `__EVENTTARGET` / `__EVENTARGUMENT` | WebForms postback plumbing |
| 7 | `__doPostBack()` function | Classic WebForms postback dispatcher |
| 8 | `WebForm_OnSubmit()`, `WebForm_DoPostBackWithOptions()`, `WebForm_FireDefaultButton()` | ASP.NET client-side helpers |
| 9 | `WebResource.axd`, `ScriptResource.axd` | ASP.NET embedded resource handlers |
| 10 | `Sys.WebForms.PageRequestManager._initialize('sm', …)` | ASP.NET AJAX ScriptManager |
| 11 | `Page_Validators`, `ValidatorOnSubmit()`, `RequiredFieldValidatorEvaluateIsValid` | ASP.NET validation framework |
| 12 | `document.all` fallback in validator output | Legacy IE-compatible rendering from ASP.NET |

### 2.2 Hosting Environment

| Aspect | Value | Classification | Evidence |
|--------|-------|----------------|----------|
| Web server | IIS (Internet Information Services) | 🔶 Strongly inferred | ASP.NET WebForms requires IIS; `.axd` handlers are IIS-registered |
| Operating system | Windows Server | 🔶 Strongly inferred | Required by IIS + .NET Framework |
| .NET variant | .NET Framework (not .NET Core/5+) | 🔶 Strongly inferred | WebForms is not supported on .NET Core; `ScriptResource.axd` signature matches .NET Framework |
| .NET Framework version | ❓ Unknown | ❓ Unknown | Requires `X-AspNet-Version` response header (needs authenticated network inspection) |
| IIS version | ❓ Unknown | ❓ Unknown | Requires `Server` response header |
| HTTPS certificate issuer | ❓ Unknown | ❓ Unknown | Requires browser certificate viewer |

### 2.3 Database

| Aspect | Value | Classification | Evidence |
|--------|-------|----------------|----------|
| RDBMS | Microsoft SQL Server | 🔶 Strongly inferred | Standard pairing for ASP.NET WebForms enterprise apps; vendor documentation on `gnwebsoft.com` references SQL Server |
| SQL Server version | ❓ Unknown | ❓ Unknown | Not observable from client side |
| ORM / data access | ❓ Unknown | ❓ Unknown | Could be ADO.NET, Entity Framework, or Dapper — no evidence |
| Database schema | ❓ Unknown | ❓ Unknown | No source code access |

---

## 3. Client-Side Stack

### 3.1 UI Framework / Theme — ✅ Confirmed: Metronic Admin Template on Bootstrap 3

| Signal | Evidence |
|--------|----------|
| Directory layout | `assets/global/plugins/`, `assets/global/css/`, `assets/pages/` — Metronic's standard asset structure |
| CSS class names | `user-login-5`, `mt-login-5-bsfix`, `green-haze`, `mt-checkbox`, `mt-checkbox-outline` — Metronic-specific |
| Core script | `assets/global/scripts/app.min.js` — Metronic's application bootstrap |
| Login page variant | `assets/pages/css/login-5.min.css` — Metronic's "Login Page 5" template |
| Bootstrap version | 3.x | `col-xs-*` classes (removed in Bootstrap 4+); no `col-xl-*` |
| Font | Open Sans (400, 300, 600, 700) via Google Fonts |

### 3.2 JavaScript Libraries — ✅ Confirmed

| Library | Purpose | CDN Path |
|---------|---------|----------|
| jQuery | DOM, AJAX, events | `assets/global/plugins/jquery.min.js` |
| Bootstrap 3 JS | UI components (modals, dropdowns, tooltips) | `assets/global/plugins/bootstrap/js/bootstrap.min.js` |
| jQuery Validation | Client-side form validation | `assets/global/plugins/jquery-validation/js/jquery.validate.min.js` |
| Select2 | Enhanced `<select>` dropdowns | `assets/global/plugins/select2/js/select2.full.min.js` |
| jQuery BlockUI | Loading/blocking overlays | `assets/global/plugins/jquery.blockui.min.js` |
| jQuery Backstretch | Full-screen background image slideshow | `assets/global/plugins/backstretch/jquery.backstretch.min.js` |
| js.cookie | Cookie read/write helper | `assets/global/plugins/js.cookie.min.js` |
| Bootbox | Wrapper for Bootstrap modals (confirm/alert dialogs) | `assets/global/plugins/bootbox/bootbox.min.js` |
| Bootstrap Datepicker | Date picker widget | `assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js` |
| Google reCAPTCHA | Bot protection (loaded async, conditionally used) | `google.com/recaptcha/api.js` |

> **jQuery version**: ❓ Unknown — the minified filename does not contain a version string.

### 3.3 Custom GNUMS Assets — ✅ Confirmed

| File | Path | Likely Purpose |
|------|------|----------------|
| `GN_InitMain.js` | `cdn.gnums.in/Default/JS/GN_InitMain.js` | GNUMS app initialization, global config |
| `CommonJSFunctions.js` | `cdn.gnums.in/Default/JS/CommonJSFunctions.js` | Shared utility functions across pages |
| `gn-rahsag-common.css` | `cdn.gnums.in/Default/CSS/custom/gn-rahsag-common.css` | Custom CSS overrides |

### 3.4 CDN Architecture — ✅ Confirmed

| Aspect | Value |
|--------|-------|
| CDN domain | `cdn.gnums.in` |
| Asset path prefix | `/Default/` |
| Scope | Shared across all GNUMS-deployed institutions |
| Local assets | Background images served from origin: `~/Images/LoginPageBG/2.jpg, 3.jpg, 4.jpg` |

---

## 4. Authentication & Security

### 4.1 Authentication Methods — ✅ Confirmed

| Method | Evidence |
|--------|----------|
| Username + password (form POST) | `txtUsername`, `txtPassword` fields; `btnLogin` submit; username placeholder: "Mobile No. / Email / Staff Code" |
| Role selection | Radio buttons: Staff (default) / Student — selecting Student triggers `__doPostBack` (server-side role switch) |
| Google OAuth / SSO | `btnLoginWithGoogle` — "Sign in with Google" |
| OTP-based login (Staff) | Hidden field `hfIsStaffLoginWithUserNameOTP = "true"` |
| Passout student login | Separate page `LoginForPassoutStudent.aspx` (linked) |

### 4.2 Security Controls — ✅ Confirmed

| Control | Evidence |
|---------|----------|
| ViewState encryption | `__VIEWSTATEENCRYPTED` field present |
| Event validation | `__EVENTVALIDATION` field prevents forged postbacks |
| reCAPTCHA (conditional) | Script loaded; `IsValidCapcha()` gating function |
| `noindex, nofollow` | `<meta name="robots" content="noindex, nofollow" />` |
| Storage wipe on load | `localStorage.clear()`, `sessionStorage.clear()`, `localStorage.removeItem("Menu")` |
| Password constraints | `maxlength="20"`, case-sensitive (tooltip) |
| Autocomplete suppression | `autocomplete="off"` on inputs |
| Show/hide password toggle | Client-side `type` swap (`password` ↔ `text`) |

### 4.3 Session Management — ❓ Mostly Unknown

| Aspect | Classification | Notes |
|--------|----------------|-------|
| Session cookie name | ❓ Unknown | Likely `ASP.NET_SessionId` (standard); needs header inspection |
| Session timeout | ❓ Unknown | ScriptManager timeout is set to `90` (seconds?) — `PageRequestManager._initialize('sm', 'form1', [], [], [], 90, '')` |
| Forms authentication | ❓ Unknown | Likely `.ASPXAUTH` cookie; needs header inspection |

---

## 5. React Student Panel — ✅ Confirmed (Migration In Progress)

### Evidence

The login page contains an inline function `GNConfirmStudentPanel()`:

```javascript
function GNConfirmStudentPanel(message, yesUrl, noButtonId) {
    // Yes -> redirect to the new React student panel.
    // No -> click the hidden button so the server runs the default execution unchanged.
    function onYes() { window.location.href = yesUrl; }
    function onNo() {
        var btn = document.getElementById(noButtonId);
        if (btn) { btn.click(); }
    }
    // … bootbox confirm dialog …
}
```

And a hidden submit button: `<input type="submit" name="btnReactPanelNo" id="btnReactPanelNo" style="display:none" />`

### Interpretation

| Conclusion | Classification |
|------------|----------------|
| A React-based student panel exists and is being offered as an alternative | ✅ Confirmed |
| It is served at a different URL (passed as `yesUrl` parameter) | ✅ Confirmed |
| The WebForms panel remains the fallback (triggered by `btnReactPanelNo` click) | ✅ Confirmed |
| The prompt is shown **after login**, not before (function is called server-side post-auth) | 🔶 Strongly inferred |
| The React panel URL pattern | ❓ Unknown — the `yesUrl` is set dynamically by the server |
| React framework version, bundler, state management | ❓ Unknown — requires inspecting the React app's HTML/JS |

---

## 6. Mobile Applications — ✅ Confirmed

| App | Platform | Identifier |
|-----|----------|------------|
| Sanjivani Staff | Android | `com.gnums.staff.gnums_staff.saj` (Google Play) |
| Sanjivani Staff | iOS | App Store ID `6760995462` |
| Student app | ❓ Unknown | Links likely appear when "Student" role is selected (triggers postback to change the login form) |

---

## 7. Integrations — 🔶 Strongly Inferred (from vendor documentation)

These are **not directly observable** in the login page but are documented on `gnwebsoft.com` and `sanjivani.edu.in`:

| Integration | Classification |
|-------------|----------------|
| Razorpay payment gateway | 🔶 Strongly inferred |
| ICICI Bank gateway | 🔶 Strongly inferred |
| UPI / Net Banking | 🔶 Strongly inferred |
| DigiLocker | 🔶 Strongly inferred |
| ABC-ID (Academic Bank of Credits) | 🔶 Strongly inferred |

---

## 8. Catalogue of Unknowns

These require specific investigation steps (none requiring auth bypass):

| Unknown | How to Resolve |
|---------|----------------|
| .NET Framework version | Inspect `X-AspNet-Version` response header (F12 → Network → first page load after login) |
| IIS version | Inspect `Server` response header |
| SQL Server version | Not observable from client; vendor documentation may disclose |
| jQuery version | Fetch `cdn.gnums.in/Default/assets/global/plugins/jquery.min.js` and inspect the header comment |
| React panel URL and framework details | Log in as Student, observe the `GNConfirmStudentPanel` prompt, choose "Yes", inspect the resulting page source |
| API design (REST, SOAP, WebService.asmx, Handler.ashx) | Observe XHR requests in Network tab after login |
| Session cookie names | Inspect `Set-Cookie` response headers after login |
| HTTPS certificate details | Click the padlock icon in Chrome address bar |
| Post-login page structure and navigation | Browse authenticated pages and document URL patterns |
| Metronic version | Inspect `app.min.js` header comment or compare class names against Metronic changelog |
