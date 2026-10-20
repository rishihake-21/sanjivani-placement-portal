# UMS Sanjivani — Observed Request Flow

> **Scope**: HTTP requests observable from the login page HTML source.
> **Constraint**: No authenticated requests have been inspected. No network capture performed.
> All request flows are reconstructed from the HTML source, inline JavaScript, and ASP.NET WebForms conventions.

---

## Classification Key

| Tag | Meaning |
|-----|---------|
| ✅ **Confirmed** | Request is explicitly triggered by elements in the HTML source |
| 🔶 **Inferred** | Request is expected based on ASP.NET WebForms behaviour and page structure |
| ❓ **Unknown** | Request likely occurs but details cannot be determined without network capture |

---

## 1. Initial Page Load — `GET /Login.aspx`

### Request — ✅ Confirmed

```
GET /Login.aspx HTTP/1.1
Host: ums.sanjivani.edu.in
```

### Response — ✅ Confirmed (from HTML source)

The server returns a full HTML document containing:

1. **Inline scripts** at the very top that clear all client-side storage:
   ```javascript
   localStorage.clear();
   sessionStorage.clear();
   ```

2. **CSS resources** loaded from `cdn.gnums.in`:
   - `bootstrap.min.css`
   - `font-awesome.min.css`
   - `simple-line-icons.min.css`
   - `select2.min.css`, `select2-bootstrap.min.css`
   - `bootstrap-datepicker3.min.css`
   - `components.min.css`, `plugins.min.css`
   - `login-5.min.css`
   - `gn-rahsag-common.css`

3. **External font** from Google Fonts:
   - `fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all`

4. **ASP.NET resource handlers** from the origin server:
   - `WebResource.axd?d=…&t=638883469099574018` (×2)
   - `ScriptResource.axd?d=…&t=ffffffff96313960` (×1)
   - `ScriptResource.axd?d=…&t=254cc609` (×2)

5. **JavaScript libraries** loaded from `cdn.gnums.in` (at page bottom):
   - `jquery.min.js`, `bootstrap.min.js`, `js.cookie.min.js`
   - `jquery.blockui.min.js`, `jquery.validate.min.js`
   - `app.min.js`, `select2.full.min.js`
   - `jquery.backstretch.min.js`
   - `bootstrap-datepicker.min.js`, `components-date-time-pickers.min.js`
   - `GN_InitMain.js`, `CommonJSFunctions.js`
   - `bootbox.min.js`

6. **Google reCAPTCHA** loaded async:
   - `www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit`

7. **Background images** loaded via jQuery Backstretch (triggered in `$(document).ready`):
   - `~/Images/LoginPageBG/2.jpg`
   - `~/Images/LoginPageBG/3.jpg`
   - `~/Images/LoginPageBG/4.jpg`

### Request Count Estimate — 🔶 Inferred

| Category | Count | Source |
|----------|-------|--------|
| HTML document | 1 | Origin server |
| CSS files | 10 | `cdn.gnums.in` |
| Google Fonts | 1+ | `fonts.googleapis.com` |
| ASP.NET resources (`.axd`) | 5 | Origin server |
| JavaScript files | 14 | `cdn.gnums.in` |
| reCAPTCHA | 1+ | `www.google.com` |
| Background images | 3 | Origin server |
| University logo | 0 | Inline Base64 (no request) |
| **Estimated total** | **~35+** | |

---

## 2. Role Switch (Student Selection) — ✅ Confirmed Postback

### Trigger
User clicks the "Student" radio button.

### Mechanism
```html
<input id="rblRole_1" type="radio" name="rblRole" value="Student"
       onclick="javascript:setTimeout('__doPostBack(\'rblRole$1\',\'\')', 0)" />
```

### Request — 🔶 Inferred

```
POST /Login.aspx HTTP/1.1
Host: ums.sanjivani.edu.in
Content-Type: application/x-www-form-urlencoded

__EVENTTARGET=rblRole%241
&__EVENTARGUMENT=
&__VIEWSTATE=<…massive blob…>
&__VIEWSTATEGENERATOR=C2EE9ABB
&__VIEWSTATEENCRYPTED=
&__EVENTVALIDATION=<…blob…>
&rblRole=Student
```

### Expected Response — 🔶 Inferred

Full page reload with the form reconfigured for the Student role. This may:
- Show/hide OTP fields (`hfIsStudentLoginWithUserNameOTP`)
- Change the username placeholder
- Show student-specific app download links
- Update the `__VIEWSTATE` and `__EVENTVALIDATION` values

---

## 3. Login Submission — ✅ Confirmed Postback

### Trigger
User fills in username and password, clicks "Login".

### Client-Side Validation — ✅ Confirmed

```javascript
function IsValidCapcha() {
    if (Page_ClientValidate("vgLogin")) {
        return true;
    }
    return false;
}
```

Before the form submits:
1. `IsValidCapcha()` calls `Page_ClientValidate("vgLogin")`
2. ASP.NET validators check:
   - `rvtxtUsername`: Username is not empty
   - `rvtxtPassword`: Password is not empty
3. If validation passes, the form POSTs

### Request — 🔶 Inferred

```
POST /Login.aspx HTTP/1.1
Host: ums.sanjivani.edu.in
Content-Type: application/x-www-form-urlencoded

__EVENTTARGET=
&__EVENTARGUMENT=
&__VIEWSTATE=<…>
&__VIEWSTATEGENERATOR=C2EE9ABB
&__VIEWSTATEENCRYPTED=
&__EVENTVALIDATION=<…>
&txtUsername=<user input>
&txtPassword=<user input>
&rblRole=Staff
&btnLogin=Login
```

### Expected Response — 🔶 Inferred

**On success**:
- HTTP 302 redirect to the authenticated dashboard
- `Set-Cookie` headers for session and auth cookies (likely `ASP.NET_SessionId`, `.ASPXAUTH`)
- For Student role: server may invoke `GNConfirmStudentPanel()` to offer the React panel choice

**On failure**:
- HTTP 200 with the login page re-rendered
- Error message displayed (mechanism unknown — could be label, panel, or alert)

---

## 4. Forgot Password — ✅ Confirmed Postback

### Trigger
User clicks "Forgot Password" button.

### Mechanism
```html
<input type="submit" name="btnForgotPassword" value="Forgot Password"
       onclick="javascript:WebForm_DoPostBackWithOptions(
           new WebForm_PostBackOptions('btnForgotPassword', '', true,
               'vgForgotPassword', '', false, false))" />
```

### Request — 🔶 Inferred

```
POST /Login.aspx HTTP/1.1

…standard WebForms fields…
&btnForgotPassword=Forgot+Password
```

### Expected Behaviour — ❓ Unknown

The `ValidationGroup="vgForgotPassword"` is defined but **no validators** for this group are present in the observed HTML. This suggests the Forgot Password button may trigger a server-side redirect or panel switch without client-side validation.

---

## 5. Google Sign-In — ✅ Confirmed Postback

### Trigger
User clicks "Sign in with Google" button.

### Mechanism
```html
<input type="submit" name="btnLoginWithGoogle" value="Sign in with Google"
       id="btnLoginWithGoogle" class="login-with-google-btn" />
```

### Request — 🔶 Inferred

```
POST /Login.aspx HTTP/1.1

…standard WebForms fields…
&btnLoginWithGoogle=Sign+in+with+Google
```

### Expected Behaviour — 🔶 Inferred

The server receives the postback and likely issues an HTTP 302 redirect to Google's OAuth 2.0 authorization endpoint (`accounts.google.com/o/oauth2/v2/auth`), with:
- `client_id` (GNUMS's Google OAuth client ID)
- `redirect_uri` (callback URL on `ums.sanjivani.edu.in`)
- `scope` (email, profile)
- `response_type=code`

After Google authentication, the user is redirected back to the UMS callback URL.

---

## 6. React Panel Choice (Post-Login) — ✅ Confirmed (Client-Side)

### Trigger
Server-side code (after successful student login) renders JavaScript that calls `GNConfirmStudentPanel()`.

### Client-Side Flow — ✅ Confirmed

```javascript
GNConfirmStudentPanel(message, yesUrl, noButtonId)
```

1. **Bootbox confirm dialog** appears with the server-provided `message`
2. **User clicks "Yes"**: `window.location.href = yesUrl` → browser navigates to the React student panel
3. **User clicks "No"**: `document.getElementById(noButtonId).click()` → triggers `btnReactPanelNo` hidden submit → standard WebForms postback → loads the legacy WebForms student panel

### "No" Path Request — 🔶 Inferred

```
POST /Login.aspx HTTP/1.1

…standard WebForms fields…
&btnReactPanelNo=
```

---

## 7. Background Image Slideshow — ✅ Confirmed (Client-Side)

### Trigger
`$(document).ready` callback.

### Mechanism
```javascript
var DBImage = "~/Images/LoginPageBG/2.jpg,~/Images/LoginPageBG/3.jpg,~/Images/LoginPageBG/4.jpg";
DBImage = replaceAll(DBImage, '~', window.location.origin);
var ImageBGStrings = DBImage.split(',');
SetLoginImages(ImageBGStrings);

function SetLoginImages(backgroundImagePath) {
    $(".login-bg").backstretch(backgroundImagePath, { fade: 1e3, duration: 8e3 });
}
```

### Requests — ✅ Confirmed

```
GET /Images/LoginPageBG/2.jpg HTTP/1.1
GET /Images/LoginPageBG/3.jpg HTTP/1.1
GET /Images/LoginPageBG/4.jpg HTTP/1.1
```

Images rotate every 8 seconds with a 1-second fade transition.

---

## 8. Summary of Request Patterns

| Action | HTTP Method | URL | Classification |
|--------|-------------|-----|----------------|
| Page load | GET | `/Login.aspx` | ✅ Confirmed |
| Static assets | GET | `cdn.gnums.in/Default/…` | ✅ Confirmed |
| ASP.NET resources | GET | `/WebResource.axd`, `/ScriptResource.axd` | ✅ Confirmed |
| reCAPTCHA | GET | `google.com/recaptcha/api.js` | ✅ Confirmed |
| Background images | GET | `/Images/LoginPageBG/*.jpg` | ✅ Confirmed |
| Role switch | POST | `/Login.aspx` (postback) | ✅ Confirmed |
| Login | POST | `/Login.aspx` (postback) | ✅ Confirmed |
| Forgot Password | POST | `/Login.aspx` (postback) | ✅ Confirmed |
| Google Sign-In | POST | `/Login.aspx` → 302 → Google OAuth | 🔶 Inferred |
| React panel "No" | POST | `/Login.aspx` (postback) | 🔶 Inferred |
| React panel "Yes" | GET | `<yesUrl>` (unknown) | 🔶 Inferred |

---

## 9. Evidence Required to Complete This Flow Map

| Unknown | How to Resolve |
|---------|----------------|
| Response headers (`Server`, `X-Powered-By`, `X-AspNet-Version`, `Set-Cookie`) | F12 → Network → inspect first response headers after login |
| Post-login redirect URL | F12 → Network → observe 302 redirect after successful login |
| React panel URL | Accept the React panel prompt as Student; note the navigation URL |
| XHR/API calls in authenticated session | F12 → Network → XHR filter during authenticated navigation |
| Forgot Password flow | Click Forgot Password; observe whether it's a postback or redirect |
| Error handling on failed login | Attempt login with incorrect credentials; observe response |
