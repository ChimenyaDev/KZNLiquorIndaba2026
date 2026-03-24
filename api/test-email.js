/**
 * KZN Liquor Indaba 2026 — Email Diagnostic Test Endpoint (Vercel Serverless)
 * 
 * GET /api/test-email?token=YOUR_TOKEN
 * GET /api/test-email?token=YOUR_TOKEN&send=true&recipient=email@example.com
 * 
 * Tests email configuration and connectivity for Vercel deployment.
 * Uses Resend API (primary) with SMTP fallback.
 */

export default async function handler(req, res) {
  // Only allow GET requests
  if (req.method !== 'GET') {
    return res.status(405).json({ success: false, message: 'Method not allowed' });
  }

  // Authentication check
  const authToken = req.headers['x-test-token'] || req.query.token;
  const expectedToken = process.env.EMAIL_TEST_TOKEN;
  
  if (!expectedToken) {
    return res.status(503).json({ 
      success: false, 
      message: 'EMAIL_TEST_TOKEN environment variable not configured',
      hint: 'Set EMAIL_TEST_TOKEN in Vercel Dashboard → Settings → Environment Variables'
    });
  }

  if (authToken !== expectedToken) {
    return res.status(401).json({ 
      success: false, 
      message: 'Unauthorized - invalid or missing token' 
    });
  }

  const results = {
    timestamp: new Date().toISOString(),
    vercelRegion: process.env.VERCEL_REGION || 'unknown',
    config: {},
    tests: {},
    recommendations: []
  };

  // Get Resend config from environment
  const resendConfig = {
    apiKey:    process.env.RESEND_API_KEY    || '',
    fromEmail: process.env.RESEND_FROM_EMAIL || '',
    fromName:  process.env.RESEND_FROM_NAME  || ''
  };

  // Get SMTP config from environment (fallback)
  const smtpConfig = {
    host: process.env.SMTP_HOST || '',
    port: process.env.SMTP_PORT || '',
    username: process.env.SMTP_USERNAME || '',
    password: process.env.SMTP_PASSWORD || '',
    fromEmail: process.env.SMTP_FROM_EMAIL || '',
    fromName: process.env.SMTP_FROM_NAME || '',
    encryption: process.env.SMTP_ENCRYPTION || 'tls'
  };

  const usingResend = !!resendConfig.apiKey;

  // Show config status (mask sensitive data)
  results.config = {
    emailMethod: usingResend ? 'Resend (primary)' : 'SMTP (fallback — Resend not configured)',
    resendApiKey: resendConfig.apiKey ? '✓ Set' : '✗ NOT SET',
    resendFromEmail: resendConfig.fromEmail || 'NOT SET (will use default)',
    resendFromName: resendConfig.fromName || 'NOT SET (will use default)',
    smtpHost: smtpConfig.host || 'NOT SET',
    smtpPort: smtpConfig.port || 'NOT SET',
    smtpUsername: smtpConfig.username ? '✓ Set' : '✗ NOT SET',
    smtpPassword: smtpConfig.password ? '✓ Set' : '✗ NOT SET',
    smtpEncryption: smtpConfig.encryption || 'NOT SET',
    fromEmail: usingResend ? (resendConfig.fromEmail || 'default') : (smtpConfig.fromEmail || 'NOT SET'),
    fromName: usingResend ? (resendConfig.fromName || 'default') : (smtpConfig.fromName || 'NOT SET')
  };

  // Validate configuration
  const configErrors = [];
  if (!usingResend) {
    // Only require SMTP config when Resend is not available
    if (!smtpConfig.host) configErrors.push('SMTP_HOST');
    if (!smtpConfig.port) configErrors.push('SMTP_PORT');
    if (!smtpConfig.username) configErrors.push('SMTP_USERNAME');
    if (!smtpConfig.password) configErrors.push('SMTP_PASSWORD');
    if (!smtpConfig.fromEmail) configErrors.push('SMTP_FROM_EMAIL');
  }

  results.tests.configuration = {
    success: usingResend || configErrors.length === 0,
    message: usingResend
      ? 'Resend API key is configured — emails will be sent via Resend'
      : configErrors.length === 0
        ? 'SMTP configuration is complete (Resend not configured)'
        : `Missing environment variables: ${configErrors.join(', ')}`,
    missingVars: configErrors.length > 0 ? configErrors : null
  };

  // Send test email if requested
  if (req.query.send === 'true' && req.query.recipient) {
    const recipient = req.query.recipient;
    
    // Validate email format
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) {
      results.tests.emailSend = {
        success: false,
        message: 'Invalid email address format'
      };
    } else if (!usingResend && configErrors.length > 0) {
      results.tests.emailSend = {
        success: false,
        message: 'Cannot send email - configuration incomplete',
        missingVars: configErrors
      };
    } else if (usingResend) {
      // Send via Resend API
      try {
        const { Resend } = await import('resend');
        const resend = new Resend(resendConfig.apiKey);
        const fromEmail = resendConfig.fromEmail || 'nto.vinkhumbo@kznera.org.za';
        const fromName  = resendConfig.fromName  || 'KZN Liquor Indaba 2026';

        const response = await resend.emails.send({
          from: `${fromName} <${fromEmail}>`,
          to: recipient,
          subject: 'KZN Liquor Indaba 2026 - Email Test (Resend)',
          text: `Email Test Successful\n\nThis test email was sent from the KZN Liquor Indaba 2026 system via Resend.\n\nTimestamp: ${new Date().toISOString()}\nMethod: Resend API via Vercel\nRegion: ${process.env.VERCEL_REGION || 'unknown'}\n\n✅ If you received this, your Resend configuration is working!`,
          html: `
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
              <h2 style="color:#1a3560;">Email Test Successful</h2>
              <p>This test email was sent from the KZN Liquor Indaba 2026 system via <strong>Resend</strong>.</p>
              <p><strong>Timestamp:</strong> ${new Date().toISOString()}</p>
              <p><strong>Method:</strong> Resend API via Vercel</p>
              <p><strong>Region:</strong> ${process.env.VERCEL_REGION || 'unknown'}</p>
              <div style="margin-top:20px;padding:15px;background:#f4f5f7;border-left:4px solid #1a3560;">
                ✅ If you received this, your Resend configuration is working!
              </div>
              <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0;" />
              <p style="font-size:0.85rem;color:#718096;">
                KZN Liquor Indaba 2026<br />
                KwaZulu-Natal Economic Regulatory Authority
              </p>
            </div>
          `
        });

        if (response.error) {
          results.tests.emailSend = {
            success: false,
            message: `Resend error: ${response.error.message}`,
            recipient,
            error: response.error.message
          };
        } else {
          results.tests.emailSend = {
            success: true,
            message: 'Email sent successfully via Resend',
            recipient,
            messageId: response.data?.id,
            method: 'resend'
          };
        }
      } catch (error) {
        results.tests.emailSend = {
          success: false,
          message: `Resend send failed: ${error.message}`,
          recipient,
          error: error.message
        };
      }
    } else {
      // Send via SMTP fallback
      try {
        const nodemailer = await import('nodemailer');
        
        // Create transporter
        const transporter = nodemailer.default.createTransport({
          host: smtpConfig.host,
          port: parseInt(smtpConfig.port, 10),
          secure: smtpConfig.encryption === 'ssl',
          auth: {
            user: smtpConfig.username,
            pass: smtpConfig.password
          },
          tls: {
            rejectUnauthorized: false
          }
        });

        // Send test email
        const info = await transporter.sendMail({
          from: `"${smtpConfig.fromName}" <${smtpConfig.fromEmail}>`,
          to: recipient,
          subject: 'KZN Liquor Indaba 2026 - Email Test (SMTP)',
          text: `Email Test Successful\n\nThis test email was sent from the KZN Liquor Indaba 2026 system via SMTP.\n\nTimestamp: ${new Date().toISOString()}\nMethod: SMTP via Vercel\nRegion: ${process.env.VERCEL_REGION || 'unknown'}\n\n✅ If you received this, your email configuration is working!`,
          html: `
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
              <h2 style="color:#1a3560;">Email Test Successful</h2>
              <p>This test email was sent from the KZN Liquor Indaba 2026 system via <strong>SMTP</strong>.</p>
              <p><strong>Timestamp:</strong> ${new Date().toISOString()}</p>
              <p><strong>Method:</strong> SMTP via Vercel</p>
              <p><strong>Region:</strong> ${process.env.VERCEL_REGION || 'unknown'}</p>
              <div style="margin-top:20px;padding:15px;background:#f4f5f7;border-left:4px solid #1a3560;">
                ✅ If you received this, your email configuration is working!
              </div>
              <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0;" />
              <p style="font-size:0.85rem;color:#718096;">
                KZN Liquor Indaba 2026<br />
                KwaZulu-Natal Economic Regulatory Authority
              </p>
            </div>
          `
        });

        results.tests.emailSend = {
          success: true,
          message: 'Email sent successfully via SMTP',
          recipient,
          messageId: info.messageId,
          method: 'smtp'
        };
      } catch (error) {
        results.tests.emailSend = {
          success: false,
          message: `Email send failed: ${error.message}`,
          recipient,
          error: error.message,
          errorCode: error.code || null
        };
      }
    }
  }

  // Generate recommendations
  if (usingResend) {
    results.recommendations.push('✅ Resend is configured — emails will be sent reliably via Resend API');
    results.recommendations.push('💡 See docs/RESEND-SETUP.md for domain verification and DNS setup');
  } else {
    results.recommendations.push('⚠️ Resend not configured — falling back to SMTP (may have connectivity issues from Vercel)');
    results.recommendations.push('💡 Set RESEND_API_KEY in Vercel Dashboard → Settings → Environment Variables');
    results.recommendations.push('💡 See docs/RESEND-SETUP.md for setup instructions');
  }

  if (configErrors.length > 0) {
    results.recommendations.push('❌ SMTP configuration incomplete — set missing environment variables in Vercel Dashboard');
    results.recommendations.push(`Missing: ${configErrors.join(', ')}`);
  }

  if (results.tests.emailSend) {
    if (results.tests.emailSend.success) {
      results.recommendations.push(`✅ Email sending works via ${results.tests.emailSend.method || 'configured method'}!`);
    } else {
      const error = results.tests.emailSend.error || '';
      results.recommendations.push(`❌ Email send failed: ${results.tests.emailSend.message}`);

      if (error.includes('auth') || error.includes('Authentication')) {
        results.recommendations.push('💡 Check SMTP_USERNAME and SMTP_PASSWORD are correct');
      }
      if (error.includes('connect') || error.includes('timeout') || error.includes('ECONNREFUSED')) {
        results.recommendations.push('💡 SMTP connection blocked — switch to Resend (see docs/RESEND-SETUP.md)');
      }
      if (error.includes('domain') || error.includes('not verified')) {
        results.recommendations.push('💡 Verify your domain in the Resend dashboard (see docs/RESEND-SETUP.md)');
      }
      if (error.includes('TLS') || error.includes('SSL') || error.includes('certificate')) {
        results.recommendations.push('💡 Try SMTP_ENCRYPTION=tls or ssl or leave empty');
      }
      if (error.includes('ENOTFOUND')) {
        results.recommendations.push('💡 DNS lookup failed - check SMTP_HOST is correct');
      }
    }
  } else {
    results.recommendations.push('💡 Add &send=true&recipient=your@email.com to test actual email sending');
  }

  return res.status(200).json({ 
    success: true, 
    results 
  });
}
