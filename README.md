# KZN Liquor Indaba 2026 — Delegate Registration & Management System

> **Hosted by** KwaZulu-Natal Economic Regulatory Authority (KZNERA)
> **In partnership with** Department of Economic Development, Tourism and Environmental Affairs (EDTEA)
> **Event Dates:** Friday 8 May – Saturday 9 May 2026 | Durban, KwaZulu-Natal

---

## Overview

This project is a two-file, self-contained web application built for the **Invitations and Registration Committee** of the KZN Liquor Indaba 2026. It provides a public delegate registration portal and a secure admin dashboard for managing all 8,862+ potential licensee delegates across KwaZulu-Natal's 11 district municipalities.

The system was designed to be deployable for free with zero server infrastructure, using **Vercel** for hosting and **Google Sheets** as a live centralised database.

---

## Files

| File | Description |
|------|-------------|
| `registration.html` | Public-facing delegate registration form |
| `admin.html` | Secure admin dashboard for the Invitations & Registration Committee |
| `README.md` | This file |

---

## Features

### `registration.html` — Public Registration Portal

- **4-step wizard** with progress indicator:
  1. **Personal Information** — name, email, mobile, preferred communication method (Email / SMS / Both)
  2. **Business Details** — organisation, liquor licence number, delegate category, district, job title
  3. **Attendance Preferences** — days attending, Gala Dinner, shuttle transport, accommodation, dietary requirements, accessibility needs
  4. **POPIA Consent & Declaration** — three explicit consent checkboxes fully compliant with the Protection of Personal Information Act 4 of 2013

- **POPIA Compliant** — delegates explicitly consent to data collection, communications, and confirm no third-party sharing

- **Google Sheets integration** — on submission, registration data is POSTed directly to Google Sheets (when configured)

- **Offline fallback** — all submissions are saved to browser `localStorage` as a backup, even when Google Sheets is not configured

- **Unique reference number** generated per registration (format: `IND2026-XXXXXX`)

- **Responsive design** using KZNERA brand colours (navy `#1a3560`, green `#2da44e`, orange `#f4a020`) with both the KZNERA and EDTEA logos embedded

---

### `admin.html` — Admin Dashboard

#### Authentication
Role-based login with the following default credentials:

| Username | Password | Role |
|----------|----------|------|
| `admin` | `indaba2026` | Administrator |
| `ntombi` | `kznera2026` | Committee Chair |
| `nosipho` | `liquor2026` | R&I Intern |
| `nicole` | `portal2026` | Registration Desk |

> ⚠️ **Change these credentials** before deploying to production by editing the `USERS` object in `admin.html`.

#### Dashboard Views

**Dashboard**
- Live stats: total registered, Day 1 count, Day 2 count, total notifications sent
- District representation bar chart
- Delegate category donut chart
- Recent registrations table

**Delegate Register**
- Full searchable, filterable, sortable table of all delegates
- Filter by district, category, attending day
- View full delegate detail (all fields + notification history) in modal
- Send notification shortcut per delegate
- Remove delegate from register
- Pagination (15 records per page)

**Send Notifications**
- Compose Email, SMS, or Both
- Select notification type: Invitation, Save the Date, Registration Confirmation, Joining Instructions, Digital Information Pack, Event Reminder, Programme Update, Transport Arrangements, Post-Event Survey, Thank You, Custom Message
- Target by recipient group: All Delegates, Day 1 only, Day 2 only, Gala Dinner Attendees, Shuttle Required, Email Preference, SMS Preference
- Tracks notification count per delegate with full type/date history

**Notification Log**
- Complete history of all communications sent
- Columns: date/time, type, notification category, subject/message, recipient count, sent by

**Reports & Exports**
One-click CSV download for 6 report types:
1. Full Delegate Register
2. Day 1 Attendance List (Friday 8 May)
3. Day 2 Attendance List (Saturday 9 May)
4. District Representation Report
5. Transport & Shuttle List
6. Notification Activity Report

Summary statistics table with percentages across all key metrics.

**Google Sheets Sync**
- "Sync from Sheet" button in topbar pulls all live records from Google Sheets
- Smart merge: Sheet is master, local notification history is preserved
- Auto-sync on login when `SHEET_URL` is configured
- Push-on-submit: every new registration automatically sent to Sheet

---

## Setup & Deployment

### Quick Start (Offline / Demo Mode)

Simply open `registration.html` in any browser. Registrations will be saved to `localStorage`. Open `admin.html` and log in to see demo data and manage registrations. No server or internet connection required beyond Google Fonts.

---

### Free Production Deployment

#### Step 1 — Deploy to Netlify

1. Go to [vercel.com](https://vercel.com) and create a free account
2. From your dashboard: **Add new site → Deploy manually**
3. Drag and drop both `registration.html` and `admin.html` into the upload box or link a GitHub repository
4. Vercel assigns a live URL immediately, e.g. `https://kzn-liquor-indaba2026.vercel.app`
5. Optional: set a custom subdomain under **Site configuration → Change site name**

Your URLs will be:
- Registration: `https://kzn-liquor-indaba2026.vercel.app/registration.html`
- Admin: `https://kzn-liquor-indaba2026.vercel.app/admin.html`

---

#### Step 2 — Connect Google Sheets (Live Database)

##### 2a. Create your Google Sheet

Create a new Google Sheet with these exact column headers in **Row 1**:

```
Timestamp | Reference | Title | FirstName | LastName | IDNumber | Email | Mobile | CommPref | OrgName | LicenceNo | Category | District | Address | JobTitle | Days | GalaDinner | Shuttle | Accommodation | Dietary | Accessibility | HeardAbout | Topics | Status
```

##### 2b. Deploy the Apps Script API

In your Google Sheet: **Extensions → Apps Script**

Paste the following code and delete any existing code:

```javascript
function doPost(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const data = JSON.parse(e.postData.contents);

  // Ignore notification log pushes
  if (data.action === 'log') {
    return ContentService.createTextOutput('ok').setMimeType(ContentService.MimeType.TEXT);
  }

  sheet.appendRow([
    new Date(data.timestamp || new Date()).toLocaleString('en-ZA'),
    data.ref || '',
    data.title || '',
    data.firstName || '',
    data.lastName || '',
    data.idNumber || '',
    data.email || '',
    data.mobile || '',
    data.commPref || '',
    data.orgName || '',
    data.licenceNo || '',
    data.delegateCategory || '',
    data.district || '',
    data.address || '',
    data.jobTitle || '',
    (data.days || []).join('+'),
    data.galaDinner || '',
    data.shuttle || '',
    data.accommodation || '',
    (data.dietary || []).join('+'),
    data.accessibility || '',
    data.heardAbout || '',
    data.topics || '',
    data.status || 'Registered'
  ]);

  return ContentService
    .createTextOutput(JSON.stringify({ status: 'success', ref: data.ref }))
    .setMimeType(ContentService.MimeType.JSON);
}

function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const rows = sheet.getDataRange().getValues();
  const headers = rows[0];
  const data = rows.slice(1).map(row => {
    const obj = {};
    headers.forEach((h, i) => { obj[h] = row[i]; });
    return obj;
  });
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}
```

Then:
1. Click **Deploy → New deployment**
2. Type: **Web app**
3. Execute as: **Me**
4. Who has access: **Anyone**
5. Click **Deploy** — authorise when prompted
6. **Copy the Web App URL** (format: `https://script.google.com/macros/s/XXXXXXXX/exec`)

> ⚠️ Every time you modify the Apps Script, you must create a **new deployment** (not update existing) to get a fresh URL.

##### 2c. Add the URL to both HTML files

Open both `registration.html` and `admin.html` in a text editor. Find this line in each:

```javascript
const SHEET_URL = '';
```

Replace with your URL:

```javascript
const SHEET_URL = 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec';
```

Save both files and re-upload to Netlify (drag and drop again — it updates automatically).

---

### Alternative: GitHub Pages Deployment

1. Create a free account at [github.com](https://github.com)
2. Create a new repository: `kzn-indaba-2026`
3. Upload `registration.html` and `admin.html`
4. Go to **Settings → Pages → Source: main branch**
5. Live at: `https://yourusername.github.io/kzn-indaba-2026/registration.html`

---

## Data & Privacy

### POPIA Compliance

The registration form includes full POPIA (Protection of Personal Information Act 4 of 2013) compliance:

- Delegates explicitly consent to data collection for specified purposes
- Clear statement that data will **not** be shared with third parties
- Delegates can request data access, correction, or deletion by emailing `indaba@kznera.co.za`
- Data is used solely for KZN Liquor Indaba 2026 logistics and KZN liquor regulatory matters

### Data Storage

| Storage Layer | What it stores | When used |
|---------------|----------------|-----------|
| Browser `localStorage` | All registrations + notification history | Always (offline backup) |
| Google Sheets | All registration fields | When `SHEET_URL` is configured |

> **Note:** `localStorage` is browser-specific. If admins use different browsers or devices, they must use the "Sync from Sheet" button to load the centralised Google Sheets data.

---

## Registration Form Fields

| Field | Required | Notes |
|-------|----------|-------|
| Title | ✅ | Mr, Ms, Mrs, Dr, Prof, Adv, Other |
| First Name | ✅ | |
| Surname | ✅ | |
| ID / Passport Number | — | Optional |
| Email Address | ✅ | Validated format |
| Mobile Number | ✅ | |
| Communication Preference | ✅ | Email / SMS / Both |
| Organisation / Business Name | ✅ | |
| Liquor Licence Number | — | Optional |
| Delegate Category | ✅ | Licensee, Government, Other Stakeholder |
| District / Municipality | ✅ | All 11 KZN districts + SADC |
| Physical Address / Town | — | Optional |
| Job Title / Role | — | Optional |
| Alternative Contact Number | — | Optional |
| Days Attending | ✅ | Day 1, Day 2, or both |
| Gala Dinner | — | Yes / No |
| Shuttle Transport Required | — | Yes / No |
| Accommodation Required | — | Yes / No |
| Dietary Requirements | — | Multi-select tags |
| Accessibility Requirements | — | Free text |
| How Did You Hear About the Indaba | — | Dropdown |
| Topics to Address | — | Free text |
| POPIA Consent (×3) | ✅ | All three required to submit |

---

## Delegate Categories

### Licensees
- Tavern / Shebeen Operator
- Restaurant / On-Consumption
- Bottle Store / Off-Consumption
- Microbrewer / Craft Producer
- Distributor / Wholesaler
- Large Manufacturer (SAB, Heineken, etc.)

### Government & Regulatory
- KZNERA Staff
- EDTEA Official
- National Liquor Authority
- Local Government / Municipality
- SAPS / Law Enforcement
- SARS Representative

### Other Stakeholders
- Financial Institution / DFI
- FMCG / Industry Partner
- Trade Association
- Media Representative
- SADC Representative
- NGO / Community Organisation
- Academic / Researcher
- Other

---

## District Coverage

- eThekwini Metropolitan
- uMgungundlovu District
- King Cetshwayo District
- iLembe District
- Harry Gwala District
- uThukela District
- uMkhanyakude District
- Zululand District
- uMzinyathi District
- Amajuba District
- uThungulu (Richards Bay area)
- Outside KwaZulu-Natal
- International / SADC

---

## Technical Notes

- **No build process required** — both files are single self-contained HTML files with embedded CSS, JavaScript, and base64-encoded logos
- **No external dependencies at runtime** beyond Google Fonts (loaded via CDN) — the app works fully offline if fonts are cached
- **Google Sheets API** is accessed via Google Apps Script acting as a simple REST proxy — no API keys required
- **Browser compatibility** — tested in Chrome, Firefox, Edge, Safari (modern versions)
- **Mobile responsive** — all views adapt to small screens
- **Demo data** is seeded automatically in the admin dashboard when `SHEET_URL` is empty and `localStorage` is empty, to demonstrate all features

---

## Customisation

### Changing Admin Credentials

Edit the `USERS` object in `admin.html`:

```javascript
const USERS = {
  'username': { pass: 'password', name: 'Full Name', role: 'Role Description' },
  // add more users here
};
```

### Changing Brand Colours

Edit the CSS variables in the `<style>` block of either file:

```css
:root {
  --navy: #1a3560;       /* Primary KZNERA navy */
  --green: #2da44e;      /* KZNERA green */
  --orange: #f4a020;     /* KZNERA orange */
}
```

### Adding New Delegate Categories or Districts

In `registration.html`, find the `<select id="delegateCategory">` and `<select id="district">` elements and add `<option>` tags as needed. Mirror the same values in the filter dropdowns in `admin.html`.

---

## Email Configuration

The PHP API supports two email-sending methods. Set `EMAIL_METHOD` to choose between them.

### Exchange Web Services (EWS) — Recommended

EWS communicates directly with the Exchange server over HTTPS and is the preferred method
when SMTP is blocked or unreliable.

Set these environment variables (e.g. in Azure App Service → Configuration → Application settings):

| Variable | Description | Default |
|----------|-------------|---------|
| `EMAIL_METHOD` | Sending method: `ews` or `smtp` | `ews` |
| `EWS_ENDPOINT` | EWS ASMX URL | `https://mail.kznera.org.za/EWS/Exchange.asmx` |
| `EWS_USERNAME` | Exchange mailbox username | `nto.vinkhumbo@kznera.org.za` |
| `EWS_PASSWORD` | Exchange mailbox password (**required**) | _(empty)_ |
| `EWS_FROM_EMAIL` | Sender address | `nto.vinkhumbo@kznera.org.za` |
| `EWS_FROM_NAME` | Sender display name | `KZN Liquor Indaba 2026` |
| `EWS_VERSION` | Exchange server version | `Exchange2013_SP1` |

### SMTP — Fallback

Used automatically when EWS fails, or when `EMAIL_METHOD=smtp`.

| Variable | Description | Default |
|----------|-------------|---------|
| `SMTP_HOST` | Mail server hostname | `mail.kznera.org.za` |
| `SMTP_PORT` | SMTP port (`25`, `587`, or `465`) | `587` |
| `SMTP_USERNAME` | SMTP account username | `nto.vinkhumbo@kznera.org.za` |
| `SMTP_PASSWORD` | SMTP account password (**required**) | _(empty)_ |
| `SMTP_FROM_EMAIL` | Sender address | `nto.vinkhumbo@kznera.org.za` |
| `SMTP_FROM_NAME` | Sender display name | `KZN Liquor Indaba 2026` |
| `SMTP_ENCRYPTION` | Encryption: `tls`, `ssl`, or _(empty)_ | `tls` |

### Troubleshooting Email Issues

#### Connection Timeout Errors

If you see `connect ETIMEDOUT` errors in the logs:

1. **Verify SMTP settings** are correct in Vercel environment variables
2. **Check firewall rules** - Vercel IPs must be whitelisted on your mail server
   - See `docs/IT-SETUP-VERCEL-SMTP.md` for detailed instructions
3. **Test the connection** using the diagnostic endpoint:
   ```
   https://[your-domain]/api/test-email.js?send=true&recipient=your@email.com
   ```
4. **Alternative ports**: If port 587 is blocked, try port 465 with SSL:
   - Set `SMTP_PORT=465`
   - Set `SMTP_ENCRYPTION=ssl`

#### Authentication Errors

If you see authentication failures:
- Verify `SMTP_USERNAME` and `SMTP_PASSWORD` are correct
- Check if the account requires app-specific passwords
- Ensure the account has permission to send via SMTP

#### General Debugging

Enable debug logging by checking the communication logs in the admin panel under the "Logs" section.

### Verbose Logging

Set `EMAIL_DEBUG=1` to write step-by-step connection and SOAP details to the PHP error log.
Useful for troubleshooting. **Do not leave enabled in production.**

### Testing Email Configuration

Run the diagnostic script from the CLI (SSH into the App Service or Kudu console):

```bash
php api/test_email.php --to=your.email@example.com
```

The script will:
1. Check DNS resolution for the mail server
2. Test TCP connectivity on ports 25, 587, 465, 443
3. Verify EWS endpoint reachability
4. Attempt to send a test email via EWS
5. Attempt to send a test email via SMTP
6. Print a recommendation based on the results

---

## Committee Responsibilities (Invitations & Registration Committee)

| Role | Name | Key Tasks |
|------|------|-----------|
| Chair | Ntombizanele Vinkhumbo | Oversee system, data management, survey design |
| R&I Intern | Nosipho Cele | Database, invitations, registration form, data collection |
| Social Responsibility | Yoliswa Zikhundla | Onboarding/registration desk, attendance registers |
| Registration Team | Nicole van der Walt & Team | Registration desk, portal showcase, data collection |
| Exec Assistant to CEO | Thokozani Gumede | Onboarding desk, attendance registers |
| IT Team | Mahmood L., Luntu N., Nhlaka M., Sherwin C. | IT stations, portal showcase setup, L&A query table |
| Licensing & Admin | Juliet v.d.B, Nkululeko T. & team | Profile registration, portal showcase, live query resolution |

---

## Event Programme Summary

### Day 1 — Friday, 8 May 2026
- 08:00 Registration & Exhibition Opens
- 09:00 Official Welcome (CEO, KZNERA)
- 09:25 Strategic Context (Chairperson, Board)
- 09:40 Keynote Address (MEC, EDTEA)
- 10:00 Online Renewal System Launch
- 11:00 Panel 1: Innovative Regulation & Provincial Integration
- 13:30 Panel 2: Responsible Trading, Community Governance & Crime
- 15:30 Panel 3: Inclusive Growth, Market Access & Community Engagement
- 18:00 Gala Dinner & KZN Liquor Industry Awards

### Day 2 — Saturday, 9 May 2026 (Half-Day)
- 09:00 Panel 4: Combatting Illicit Trade & Responsibility in Practice
- 11:00 Closing Address & Way Forward
- 12:00 Event Close

---

## Contact

For technical support or enquiries related to this system:

**KZN Liquor Indaba 2026 — Invitations & Registration Committee**
Email: indaba@kznera.co.za

**KwaZulu-Natal Economic Regulatory Authority (KZNERA)**
In partnership with the Department of Economic Development, Tourism and Environmental Affairs (EDTEA)

---

*Built for the KZN Liquor Indaba 2026 Invitations and Registration Committee. Personal information handled in accordance with POPIA (Act 4 of 2013).*
