/**
 * KZN Liquor Indaba 2026 — Email Diagnostic Test Endpoint (Vercel Serverless)
 * 
 * GET /api/test-email?token=YOUR_TOKEN
 * GET /api/test-email?token=YOUR_TOKEN&send=true&recipient=email@example.com
 * 
 * Tests email configuration and connectivity for Vercel deployment.
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

  // Get SMTP config from environment
  const smtpConfig = {
    host: process.env.SMTP_HOST || '',
    port: process.env.SMTP_PORT || '',
    username: process.env.SMTP_USERNAME || '',
    password: process.env.SMTP_PASSWORD || '',
    fromEmail: process.env.SMTP_FROM_EMAIL || '',
    fromName: process.env.SMTP_FROM_NAME || '',
    encryption: process.env.SMTP_ENCRYPTION || 'tls'
  };

  // Show config status (mask sensitive data)
  results.config = {
    smtpHost: smtpConfig.host || 'NOT SET',
    smtpPort: smtpConfig.port || 'NOT SET',
    smtpUsername: smtpConfig.username ? '✓ Set' : '✗ NOT SET',
    smtpPassword: smtpConfig.password ? '✓ Set' : '✗ NOT SET',
    smtpEncryption: smtpConfig.encryption || 'NOT SET',
    fromEmail: smtpConfig.fromEmail || 'NOT SET',
    fromName: smtpConfig.fromName || 'NOT SET'
  };

  // Validate configuration
  const configErrors = [];
  if (!smtpConfig.host) configErrors.push('SMTP_HOST');
  if (!smtpConfig.port) configErrors.push('SMTP_PORT');
  if (!smtpConfig.username) configErrors.push('SMTP_USERNAME');
  if (!smtpConfig.password) configErrors.push('SMTP_PASSWORD');
  if (!smtpConfig.fromEmail) configErrors.push('SMTP_FROM_EMAIL');

  results.tests.configuration = {
    success: configErrors.length === 0,
    message: configErrors.length === 0 
      ? 'All required configuration is set' 
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
    } else if (configErrors.length > 0) {
      results.tests.emailSend = {
        success: false,
        message: 'Cannot send email - configuration incomplete',
        missingVars: configErrors
      };
    } else {
      // Try to send email using nodemailer
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
          subject: 'KZN Liquor Indaba 2026 - Email Test',
          text: `Email Test Successful\n\nThis test email was sent from the KZN Liquor Indaba 2026 system.\n\nTimestamp: ${new Date().toISOString()}\nMethod: SMTP via Vercel\nRegion: ${process.env.VERCEL_REGION || 'unknown'}\n\n✅ If you received this, your email configuration is working!`,
          html: `
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
              <h2 style="color:#1a3560;">Email Test Successful</h2>
              <p>This test email was sent from the KZN Liquor Indaba 2026 system.</p>
              <p><strong>Timestamp:</strong> ${new Date().toISOString()}</p>
              <p><strong>Method:</strong> SMTP via Vercel</p>
              <p><strong>Region:</strong> ${process.env.VERCEL_REGION || 'unknown'}</p>
              <div style="margin-top:20px;padding:15px;background:#f4f5f7;border-left:4px solid:#1a3560;">
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
          message: 'Email sent successfully',
          recipient: recipient,
          messageId: info.messageId
        };
      } catch (error) {
        results.tests.emailSend = {
          success: false,
          message: `Email send failed: ${error.message}`,
          recipient: recipient,
          error: error.message,
          errorCode: error.code || null
        };
      }
    }
  }

  // Generate recommendations
  if (configErrors.length > 0) {
    results.recommendations.push('❌ Configuration incomplete - set missing environment variables in Vercel Dashboard');
    results.recommendations.push(`Missing: ${configErrors.join(', ')}`);
    results.recommendations.push('💡 Go to: Vercel Dashboard → Your Project → Settings → Environment Variables');
  } else {
    results.recommendations.push('✅ Configuration is complete');
  }

  if (results.tests.emailSend) {
    if (results.tests.emailSend.success) {
      results.recommendations.push('✅ Email sending works! Your notifications should work correctly.');
    } else {
      const error = results.tests.emailSend.error || '';
      results.recommendations.push(`❌ Email send failed: ${results.tests.emailSend.message}`);
      
      if (error.includes('auth') || error.includes('Authentication')) {
        results.recommendations.push('💡 Check SMTP_USERNAME and SMTP_PASSWORD are correct');
      }
      if (error.includes('connect') || error.includes('timeout') || error.includes('ECONNREFUSED')) {
        results.recommendations.push('💡 Check SMTP_HOST and SMTP_PORT values');
        results.recommendations.push('💡 Verify Vercel can reach mail.kznera.org.za');
        results.recommendations.push('💡 Contact KZNERA IT to whitelist Vercel IP ranges');
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