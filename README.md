# AI Travel Itinerary Generator

A comprehensive WordPress plugin that generates AI-powered travel itineraries with premium features, payment integration, and affiliate support.

## Features

### Core Features
- **AI-Powered Itinerary Generation** using OpenAI (GPT-3.5 Turbo for free, GPT-4 for premium)
- **Dual Interface Options**: Chat interface and/or Form interface
- **Free & Premium Tiers**: Configurable free itinerary limits with paid premium upgrades
- **PDF Export**: Generate downloadable PDFs with customizable styling (Minimal, Modern, Luxury)
- **Multilingual Support**: English, Spanish, French, German, Italian, Portuguese

### Payment Integration
- **Stripe Integration**: Full Stripe payment support
- **PayPal Integration**: PayPal checkout option
- **Flexible Payment Options**: Choose Stripe, PayPal, or both
- **Account Requirements**: Optional account requirement before purchase

### User Authentication
- **Standard Registration**: Email/password signup with first name, last name
- **Google Sign-In**: OAuth integration for quick authentication
- **Guest Mode**: Limited features for non-registered users

### Affiliate Integration
- **Booking.com**: Hotel booking affiliate links
- **Skyscanner**: Flight search affiliate links
- **GetYourGuide**: Activity booking affiliate links
- **Link Display Options**: Hidden integration or visible buttons

### Admin Panel
- **Dashboard**: Overview with statistics and charts
- **Analytics**: Daily itinerary creation and revenue tracking
- **Revenue Charts**: Visual representation of earnings
- **Comprehensive Settings**:
  - General settings (API keys, limits, pricing)
  - Payment configuration
  - Affiliate IDs
  - Authentication settings
  - Branding customization

### User Experience
- **Floating Widget**: Always accessible chat/form widget
- **Warning System**: Alerts before closing unsaved itineraries
- **Save Functionality**: Registered users can save itineraries
- **Friendly AI Tone**: Configurable tone (Friendly, Professional, Casual)

## Installation

1. Upload the `ai-itinerary-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AI Itinerary > Settings** to configure the plugin

## Configuration

### Required Settings

1. **OpenAI API Key** (Required)
   - Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
   - Enter in: AI Itinerary > Settings > General

2. **Payment Method** (Required for premium features)
   - **Stripe**:
     - Get keys from [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
     - Enter Publishable Key and Secret Key
   - **PayPal**:
     - Get credentials from [PayPal Developer](https://developer.paypal.com/)
     - Enter Client ID and Client Secret

### Optional Settings

3. **Google OAuth** (Optional)
   - Enable Google Sign-In for users
   - Get credentials from [Google Cloud Console](https://console.cloud.google.com/)
   - Enter Client ID and Client Secret

4. **Affiliate IDs** (Optional)
   - Enter your affiliate IDs for Booking.com, Skyscanner, and GetYourGuide
   - Choose between hidden or visible affiliate link display

5. **Branding** (Optional)
   - Customize primary and secondary colors
   - Add your logo URL
   - Personalize the look and feel

## Usage

### For Site Visitors

1. **Access the Widget**: Click the floating button in the bottom-right corner
2. **Sign Up or Continue as Guest**: Register for full features or use limited guest mode
3. **Generate Itinerary**:
   - **Chat Mode**: Describe your trip naturally
   - **Form Mode**: Fill in destination, days, dates, preferences
4. **Choose Tier**: Select free (basic) or premium (detailed) itinerary
5. **Download PDF**: Export your itinerary as a styled PDF
6. **Save**: Registered users can save itineraries for later

### Using the Shortcode

Add the widget to any page using:
```
[ai_itinerary]
```

Optional attributes:
```
[ai_itinerary style="chat"]
[ai_itinerary style="form"]
[ai_itinerary style="both"]
```

## Premium Features

Premium itineraries include:
- More detailed recommendations
- Specific hotel suggestions with price ranges
- Restaurant recommendations
- Activity timing suggestions
- Transportation options
- Budget breakdown
- Booking recommendations
- Cultural insights

## Admin Dashboard

### Analytics Tracking
- Total itineraries generated
- Free vs Premium breakdown
- Revenue tracking
- Daily statistics
- User registration analytics

### Revenue Charts
- Daily revenue visualization
- Itinerary creation trends
- User activity monitoring

## Technical Details

### Database Tables
- `wp_aip_itineraries`: Stores generated itineraries
- `wp_aip_user_meta`: Extended user information and limits
- `wp_aip_payments`: Payment transaction records
- `wp_aip_analytics`: Event tracking and analytics

### File Structure
```
ai-itinerary-plugin/
├── ai-itinerary-plugin.php (Main plugin file)
├── includes/
│   ├── class-aip-database.php
│   ├── class-aip-admin.php
│   ├── class-aip-frontend.php
│   ├── class-aip-api.php
│   ├── class-aip-pdf.php
│   ├── class-aip-payment.php
│   ├── class-aip-auth.php
│   └── class-aip-affiliate.php
├── assets/
│   ├── css/
│   │   ├── frontend.css
│   │   └── admin.css
│   └── js/
│       ├── frontend.js
│       └── admin.js
└── README.md
```

### Security Features
- Nonce verification for all AJAX requests
- Data sanitization and validation
- SQL injection prevention with prepared statements
- XSS protection
- Direct file access prevention

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- OpenAI API account
- Stripe and/or PayPal account (for premium features)

## Support & Customization

### Customization Options
- Modify CSS in `assets/css/frontend.css` for styling
- Adjust JavaScript behavior in `assets/js/frontend.js`
- Customize PDF templates in `class-aip-pdf.php`
- Modify AI prompts in `class-aip-api.php`

### Common Customizations
1. **Change color scheme**: Admin Panel > Branding
2. **Modify free limits**: Admin Panel > General Settings
3. **Adjust pricing**: Admin Panel > General Settings
4. **Customize AI tone**: Admin Panel > General Settings

## Troubleshooting

### Plugin won't activate
- Check PHP version (7.4+)
- Verify WordPress version (5.8+)
- Check for PHP syntax errors in logs

### OpenAI API not working
- Verify API key is correct
- Check API key has credits
- Review error logs in WordPress

### Payment not processing
- Verify API keys are correct
- Check Stripe/PayPal dashboard for errors
- Ensure webhook URLs are configured (if using webhooks)

### Google Sign-In not working
- Verify Client ID and Secret
- Check authorized redirect URIs in Google Console
- Ensure Google Sign-In library is loading

## Changelog

### Version 1.0.0
- Initial release
- AI-powered itinerary generation
- Free and premium tiers
- Payment integration (Stripe & PayPal)
- Google OAuth authentication
- Affiliate link integration
- PDF export
- Admin dashboard with analytics
- Multilingual support

## License

GPL v2 or later

## Credits

Created with ❤️ for travel enthusiasts worldwide.

