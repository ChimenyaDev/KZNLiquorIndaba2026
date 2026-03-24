# Resend Email Setup for KZN Liquor Indaba 2026

This guide helps you set up Resend for sending emails from `@kznera.org.za` addresses.

## Why Resend?

- ✅ **Free tier**: 3,000 emails/month (more than enough for the event)
- ✅ **Reliable**: Built for serverless platforms like Vercel
- ✅ **Custom domain**: Send from your own domain (`@kznera.org.za`)
- ✅ **No firewall issues**: Works from anywhere
- ✅ **Better deliverability**: Professional email infrastructure

---

## Setup Steps

### Step 1: Create Resend Account

1. Go to [https://resend.com/signup](https://resend.com/signup)
2. Sign up with your email (use a KZNERA email address)
3. Verify your email address
4. Log in to the Resend dashboard

### Step 2: Add Your Domain

1. In the Resend dashboard, go to **Domains**
2. Click **Add Domain**
3. Enter your domain: `kznera.org.za`
4. Click **Add**

### Step 3: Configure DNS Records

Resend will show you **3 DNS records** to add. You need to add these to your domain's DNS settings.

**Contact your IT team** to add these records:

#### Example DNS Records (yours will be different):

| Type | Name | Value |
|------|------|-------|
| **TXT** | `@` or `kznera.org.za` | `resend-verify=abc123xyz...` |
| **TXT** | `resend._domainkey` | `p=MIGfMA0GCS...` (DKIM key) |
| **MX** | `resend` | `feedback-smtp.resend.com` (priority: 10) |

> **Note**: The actual values will be shown in your Resend dashboard. Copy them exactly.

#### Where to Add DNS Records

Your IT team should add these records in your DNS management panel (wherever `kznera.org.za` DNS is managed — could be your domain registrar, hosting provider, or separate DNS service like Cloudflare).

**DNS propagation** usually takes 5–30 minutes, but can take up to 24 hours.

### Step 4: Verify Domain

1. After adding DNS records, go back to Resend dashboard
2. Click **Verify** next to your domain
3. If DNS records are correct, verification will succeed
4. Your domain status will change to **Verified** ✅

### Step 5: Create API Key

1. In Resend dashboard, go to **API Keys**
2. Click **Create API Key**
3. Give it a name: `KZN Liquor Indaba 2026`
4. Select permission: **Sending access**
5. Click **Create**
6. **Copy the API key** (it starts with `re_...`)
7. ⚠️ **Save it somewhere safe** — you won't be able to see it again!

### Step 6: Add to Vercel

1. Go to your Vercel project dashboard
2. Click **Settings** → **Environment Variables**
3. Add these new variables:

| Variable | Value |
|----------|-------|
| `RESEND_API_KEY` | `re_...` (the API key you copied) |
| `RESEND_FROM_EMAIL` | `nto.vinkhumbo@kznera.org.za` |
| `RESEND_FROM_NAME` | `KZN Liquor Indaba 2026` |

4. Click **Save**
5. **Redeploy** your application (Vercel → Deployments → Redeploy)

---

## Testing

After setup and redeployment, test email sending:

### Option 1: Use the Test Endpoint

Visit:
```
https://[your-vercel-domain]/api/test-email?token=YOUR_TOKEN&send=true&recipient=your@email.com
```

Replace `your@email.com` with your actual email address.

### Option 2: Send a Test Notification

1. Log in to your admin panel
2. Go to a registration
3. Send a test notification
4. Check the logs to confirm success

---

## Troubleshooting

### "Domain not verified"

- **Check DNS records**: Make sure all 3 records are added correctly
- **Wait for propagation**: DNS changes can take up to 24 hours
- **Check DNS with tool**: Use [https://mxtoolbox.com/](https://mxtoolbox.com/) to verify records

### "Invalid API key"

- **Check the key**: Make sure you copied it correctly (starts with `re_`)
- **Check Vercel env vars**: Verify `RESEND_API_KEY` is set correctly
- **Redeploy**: After changing env vars, you must redeploy

### "Emails not arriving"

- **Check spam folder**: First emails might go to spam
- **Verify domain status**: Go to Resend dashboard, ensure domain shows "Verified"
- **Check Resend logs**: Resend dashboard → Logs shows delivery status
- **Check email address**: Make sure recipient email is valid

### "Fallback to SMTP"

If you see "falling back to SMTP" in logs:
- `RESEND_API_KEY` is not set or is empty
- Add the API key to Vercel environment variables
- Redeploy the application

---

## DNS Record Details

### What Each Record Does

1. **TXT verification record** (`resend-verify=...`)
   - Proves you own the domain
   - Required for domain verification

2. **TXT DKIM record** (`resend._domainkey`)
   - Signs your emails cryptographically
   - Prevents spoofing and improves deliverability
   - Helps avoid spam filters

3. **MX feedback record** (`resend.kznera.org.za`)
   - Receives bounce notifications
   - Optional but recommended

### Adding Records Step-by-Step

**If using Cloudflare:**
1. Log in to Cloudflare
2. Select `kznera.org.za`
3. Click **DNS** → **Records**
4. Click **Add record**
5. Choose record type (TXT or MX)
6. Enter Name and Value from Resend dashboard
7. Click **Save**

**If using cPanel:**
1. Log in to cPanel
2. Find **Zone Editor**
3. Select `kznera.org.za`
4. Click **Add Record**
5. Enter details from Resend
6. Click **Save**

**If using other DNS provider:**
Contact your IT team or hosting provider for assistance.

---

## Cost

- **Free tier**: 3,000 emails/month
- **No credit card required** for free tier
- **Your usage**: Estimated ~500–1,000 emails for the event
- **Conclusion**: Completely free for this event

---

## Security Notes

- ✅ Never commit the API key to Git
- ✅ Only store it in Vercel environment variables
- ✅ Rotate the key after the event (delete it from Resend dashboard)
- ✅ DNS records are safe to keep permanently

---

## Support

**Resend Support:**
- Documentation: [https://resend.com/docs](https://resend.com/docs)
- Support: [https://resend.com/support](https://resend.com/support)

**For KZNERA IT Team:**
If you need help adding DNS records, contact your domain administrator or hosting provider.
