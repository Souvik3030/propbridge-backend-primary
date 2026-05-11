<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>@yield('title')</title>
    <!--[if mso]>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    <style>
      table {border-collapse: collapse;}
      td,th,div,p,a,h1,h2,h3,h4,h5,h6 {font-family: "Segoe UI", sans-serif; mso-line-height-rule: exactly;}
    </style>
    <![endif]-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body, td, th, div, p, a, h1, h2, h3, h4, h5, h6 {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        body {
            background-color: #f8fafc;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f8fafc;
            padding: 40px 20px;
        }
        .main {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border-spacing: 0;
            overflow: hidden;
        }
        .gold-bar {
            background-color: #ccab59;
            background-image: linear-gradient(135deg, #dfc37a 0%, #ccab59 100%);
            height: 6px;
            width: 100%;
        }
        .header {
            padding: 40px 40px 20px 40px;
            text-align: center;
        }
        .logo {
            font-weight: 800;
            font-size: 26px;
            color: #ccab59;
            letter-spacing: -0.5px;
            text-decoration: none;
        }
        .content {
            padding: 0 40px 40px 40px;
            text-align: center;
        }
        .hero-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            letter-spacing: -0.8px;
            line-height: 1.2;
            text-align: center;
        }
        .hero-subtitle {
            font-size: 16px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 32px;
            font-weight: 400;
            text-align: center;
        }
        .company-badge {
            display: inline-block;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 32px;
            width: 80%;
            max-width: 300px;
        }
        .company-name {
            font-weight: 700;
            color: #ccab59;
            font-size: 18px;
        }
        .cta-button {
            display: inline-block;
            background-color: #ccab59;
            color: #ffffff !important;
            padding: 16px 36px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 14px 0 rgba(204, 171, 89, 0.39);
        }
        .footer {
            padding: 32px 40px;
            text-align: center;
            background-color: #0f172a;
            color: #94a3b8;
        }
        .footer-logo {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .footer p {
            font-size: 13px;
            margin: 6px 0;
            color: #64748b;
        }
        .social-link {
            color: #ccab59;
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body, .wrapper { background-color: #020617 !important; }
            .main { background-color: #0f172a !important; border-color: #1e293b !important; box-shadow: none !important; }
            .hero-title { color: #f8fafc !important; }
            .hero-subtitle { color: #94a3b8 !important; }
            .company-badge { background-color: #1e293b !important; border-color: #334155 !important; }
            .footer { border-top: 1px solid #1e293b !important; }
        }
        
        /* Mobile Responsiveness */
        @media screen and (max-width: 600px) {
            .wrapper { padding: 20px 10px !important; }
            .header, .content, .footer { padding-left: 24px !important; padding-right: 24px !important; }
            .hero-title { font-size: 24px !important; }
            .company-badge { width: 100% !important; max-width: 100% !important; box-sizing: border-box; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <center>
            <table class="main" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr><td class="gold-bar"></td></tr>
                <tr>
                    <td class="header">
                        <a href="#" class="logo">PropBridge</a>
                    </td>
                </tr>
                <tr>
                    <td class="content">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <div class="footer-logo">PROPBRIDGE PLATFORM</div>
                        <p>Innovation Tower, Business Bay</p>
                        <p>Dubai, United Arab Emirates</p>
                        <p style="margin-top: 20px;">
                            <a href="#" class="social-link">Support</a> &nbsp;&bull;&nbsp; 
                            <a href="#" class="social-link">Privacy Policy</a>
                        </p>
                    </td>
                </tr>
            </table>
        </center>
    </div>
</body>
</html>