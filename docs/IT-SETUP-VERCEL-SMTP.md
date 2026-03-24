# IT Setup Guide: Whitelist Vercel IPs for SMTP Access

## Purpose

The KZN Liquor Indaba 2026 application is hosted on **Vercel** (a serverless cloud platform). When it sends email notifications to registrants and stakeholders, it connects outbound to the KZNERA mail server (`mail.kznera.org.za`, IP `41.163.7.178`) on port 587.

Vercel's serverless functions originate from dynamic cloud IP addresses. If those IPs are not permitted by the mail server firewall, the connection is refused or times out, causing email delivery failures such as:

```
connect ETIMEDOUT 41.163.7.178:587
```

To resolve this, the KZNERA IT team must whitelist Vercel's outbound IP ranges on the mail server firewall.

---

## Vercel IP Ranges

Vercel uses dynamic IP addresses across its global edge network. The official documentation and tools for managing outbound IPs are:

- **Vercel Static IPs** (Pro/Enterprise): <https://vercel.com/docs/connectivity/static-ips>
- **Vercel KB — how to allowlist IPs**: <https://vercel.com/kb/guide/how-to-allowlist-deployment-ip-address>

> **Recommended approach**: Enable the **Static IPs** feature in your Vercel project's *Connectivity* settings (available on Pro and Enterprise plans). This assigns dedicated outbound IP addresses that can be precisely whitelisted. The assigned IPs are displayed in the Vercel dashboard after enabling the feature.
>
> If Static IPs are not available on your plan, contact Vercel support for the current dynamic IP ranges used by your deployment region.

Because the IP pool may change over time, it is recommended to review and refresh the whitelist whenever Vercel notifies you of an IP change.

---

## Firewall Configuration Steps

### Target server details

| Property | Value |
|----------|-------|
| Mail server hostname | `mail.kznera.org.za` |
| Mail server IP | `41.163.7.178` |
| Protocol | SMTP (STARTTLS) |
| Port | `587` |

### Steps

1. **Obtain the Vercel outbound IP addresses**
   - **Option A (recommended)**: Enable Static IPs in your Vercel project (*Project → Settings → Connectivity → Static IPs*). The assigned IP addresses will be displayed in the dashboard. Use these exact IPs.
   - **Option B**: If Static IPs are not enabled, contact Vercel support or refer to <https://vercel.com/kb/guide/how-to-allowlist-deployment-ip-address> for current guidance on whitelisting dynamic egress IPs.

2. **Add inbound allow rules on the mail server firewall**
   - For each Vercel CIDR block, add an inbound `ALLOW` rule:
     - **Source**: `<Vercel CIDR block>`
     - **Destination**: `41.163.7.178` (mail.kznera.org.za)
     - **Port**: `587`
     - **Protocol**: `TCP`

3. **Whitelist both IPv4 and IPv6** (if the mail server supports IPv6)
   - Vercel delivers traffic over both protocols. Include IPv6 ranges where supported.

4. **Apply and reload the firewall rules**
   - After updating the rules, reload/restart the firewall service to activate the changes.

5. **Verify connectivity** (see Testing section below)

---

## Testing the Configuration

After applying the firewall changes, use the built-in diagnostic endpoint to verify that emails can be sent from Vercel:

```
https://[your-vercel-domain]/api/test-email.js?send=true&recipient=test@kznera.org.za
```

Replace `[your-vercel-domain]` with the actual Vercel deployment URL (e.g., `kzn-liquor-indaba-2026.vercel.app`).

The endpoint will:
1. Check that all required SMTP environment variables are set
2. Attempt to send a test email to the specified recipient
3. Return a JSON response indicating success or failure, along with any error details

A successful response looks like:

```json
{
  "success": true,
  "results": {
    "tests": {
      "configuration": { "success": true },
      "emailSend": { "success": true, "messageId": "<...>" }
    }
  }
}
```

---

## Troubleshooting

### Connection still timing out after whitelisting

- **Confirm the correct IP was whitelisted**: Run `nslookup mail.kznera.org.za` to verify the resolved IP matches `41.163.7.178`.
- **Check if the rule is active**: Review the active firewall ruleset to confirm the Vercel CIDR blocks appear in the `ALLOW` list for port 587.
- **Check firewall logs**: Look for blocked TCP connections from Vercel IPs in the firewall logs. This confirms whether traffic is reaching the server but being dropped.

### Port 587 remains blocked

If port 587 cannot be opened for Vercel IPs, an alternative is to use **port 465 (SMTPS/SSL)**:

1. Confirm the mail server accepts connections on port 465.
2. Whitelist the Vercel IP ranges for port 465 instead of (or in addition to) port 587.
3. Update the Vercel environment variables for the application:
   - `SMTP_PORT=465`
   - `SMTP_ENCRYPTION=ssl`

### Authentication errors after connectivity is restored

If the connection succeeds but email sending still fails with an authentication error:

- Verify `SMTP_USERNAME` and `SMTP_PASSWORD` in the Vercel project's environment variables are correct.
- Ensure the SMTP account (`nto.vinkhumbo@kznera.org.za`) has permission to relay mail via the server.
- Check whether the account requires an app-specific password or has multi-factor authentication enabled.

### Vercel IP list has changed

If emails start failing again after a period of working correctly, Vercel may have updated its IP ranges. Repeat the whitelisting process with the updated IP list from the Vercel documentation.

---

## Contact

For application-level issues (environment variables, email templates, logs), contact the development team.

For firewall and mail server configuration, contact the KZNERA IT infrastructure team.
