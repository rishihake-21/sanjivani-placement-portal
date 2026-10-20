# UMS Sanjivani — Observed Page Map

> **Scope**: Pages discoverable from the login page HTML source *without* authenticating.
> **Constraint**: No authenticated pages have been inspected. No crawling or brute-forcing performed.

---

## Classification Key

| Tag | Meaning |
|-----|---------|
| ✅ **Observed** | URL or page reference directly present in the HTML source |
| 🔶 **Inferred** | Page likely exists based on control names, hidden fields, or JavaScript logic |
| ❓ **Unknown** | Existence suspected but no direct evidence |

---

## 1. Directly Observed Pages

### 1.1 Login Page — ✅ Observed

| Attribute | Value |
|-----------|-------|
| URL | `https://ums.sanjivani.edu.in/Login.aspx` |
| Method | `POST` (form-based) |
| Purpose | Primary authentication entry point |
| Roles | Staff (default), Student (triggers postback to reconfigure form) |
| Features | Username/password login, Google SSO, OTP login (staff), show/hide password, reCAPTCHA |

### 1.2 Passout Student Login — ✅ Observed

| Attribute | Value |
|-----------|-------|
| URL | `https://ums.sanjivani.edu.in/LoginForPassoutStudent.aspx` |
| Link text | "Passout Student Click Here" |
| Purpose | Separate login flow for alumni / passed-out students |
| Notes | Linked from `Login.aspx` via `<a>` tag |

---

## 2. Inferred Pages (Post-Authentication)

These pages are **not directly linked** in the login HTML but their existence is inferred from JavaScript logic, hidden fields, or control naming patterns.

### 2.1 React Student Panel — 🔶 Inferred

| Attribute | Value |
|-----------|-------|
| URL | ❓ Unknown (dynamically set via `yesUrl` parameter in `GNConfirmStudentPanel()`) |
| Trigger | After student login, a Bootbox confirm dialog asks whether to use the new React panel |
| Fallback | Clicking "No" triggers `btnReactPanelNo` hidden submit → falls back to WebForms panel |
| Evidence | `GNConfirmStudentPanel(message, yesUrl, noButtonId)` function |

### 2.2 WebForms Student Dashboard — 🔶 Inferred

| Attribute | Value |
|-----------|-------|
| URL | ❓ Unknown |
| Evidence | `btnReactPanelNo` fallback implies a WebForms-based student dashboard exists |
| Notes | `localStorage` keys `"Menu"`, `"StudentMenu"`, `"MenuFilter"` are cleared on login page load, implying menu state is stored client-side in the authenticated session |

### 2.3 Staff Dashboard — 🔶 Inferred

| Attribute | Value |
|-----------|-------|
| URL | ❓ Unknown |
| Evidence | Staff role is the default selection; `localStorage` key `"Menu"` implies a menu-driven staff interface |

### 2.4 Forgot Password Page/Flow — 🔶 Inferred

| Attribute | Value |
|-----------|-------|
| URL | ❓ Unknown (likely same page via postback, or a separate `.aspx` page) |
| Trigger | `btnForgotPassword` button with `ValidationGroup="vgForgotPassword"` |
| Evidence | Button exists in login form; hidden field `hfIsForgotPassword` |
| Notes | May use same-page panel switching (common WebForms pattern) or redirect |

---

## 3. Static Assets & Background Images — ✅ Observed

| Path | Purpose |
|------|---------|
| `~/Images/LoginPageBG/2.jpg` | Background slideshow image |
| `~/Images/LoginPageBG/3.jpg` | Background slideshow image |
| `~/Images/LoginPageBG/4.jpg` | Background slideshow image |
| `~/Images/google-play.png` | Google Play store badge |
| `~/Images/Iphone-app-store.png` | Apple App Store badge |
| University logo | Inline Base64-encoded PNG (`data:image/png;base64,…`) |

---

## 4. External Links — ✅ Observed

| Destination | Purpose |
|-------------|---------|
| `https://play.google.com/store/apps/details?id=com.gnums.staff.gnums_staff.saj` | Staff Android app |
| `https://apps.apple.com/us/app/sanjivani-staff/id6760995462` | Staff iOS app |
| `https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all` | Google Fonts |
| `https://www.google.com/recaptcha/api.js` | reCAPTCHA service |

---

## 5. Navigation Model — 🔶 Inferred

```
                    ┌──────────────────────────────────┐
                    │         Login.aspx                │
                    │  ┌──────────┐  ┌──────────────┐  │
                    │  │ Staff ●  │  │ Student ○    │  │
                    │  └──────────┘  └──────┬───────┘  │
                    │       │               │ (postback │
                    │       │               │  to swap  │
                    │       │               │  form)    │
                    └───┬───┘               │           │
                        │                   │           │
               ┌────────▼─────────┐  ┌──────▼───────┐  │
               │  Staff Dashboard │  │ Student      │  │
               │  (WebForms)      │  │ Login Flow   │  │
               │  Menu-driven     │  └──────┬───────┘  │
               └──────────────────┘         │          │
                                    ┌───────▼────────┐ │
                                    │ Panel Choice   │ │
                                    │ Dialog         │ │
                                    │ (Bootbox)      │ │
                                    └──┬──────────┬──┘ │
                                       │          │    │
                              ┌────────▼──┐  ┌────▼────┤
                              │ React     │  │WebForms ││
                              │ Student   │  │Student  ││
                              │ Panel     │  │Panel    ││
                              │ (new)     │  │(legacy) ││
                              └───────────┘  └─────────┘│
                                                        │
                    ┌───────────────────────────────────┘
                    │
                    ▼
          LoginForPassoutStudent.aspx
          (Separate entry point)
```

---

## 6. Evidence Required to Expand This Map

| What to discover | How |
|------------------|-----|
| Post-login URLs for Staff and Student dashboards | Log in and note the redirect URL |
| React panel URL | Log in as Student, accept the React panel prompt, note the URL |
| Menu structure and sub-pages | Inspect `localStorage` keys `"Menu"` / `"StudentMenu"` after login |
| AJAX-loaded content panels | Monitor Network tab (XHR filter) during authenticated navigation |
| Forgot Password flow | Click "Forgot Password" and observe whether it's a postback or redirect |
