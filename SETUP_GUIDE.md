# Quick Setup Guide

## 🎉 Your Plugin is Ready!

The **AI Travel Itinerary Generator** plugin has been created from scratch with all the features you requested.

## ✅ What's Included

### Core Features
✓ AI-powered itinerary generation (Free & Premium tiers)
✓ Dual interface: Chat and Form modes
✓ PDF export with 3 style options
✓ Multilingual support (6 languages)
✓ User authentication with Google Sign-In
✓ Stripe & PayPal payment integration
✓ Affiliate integration (Booking.com, Skyscanner, GetYourGuide)
✓ Admin dashboard with analytics & revenue charts
✓ Customizable branding

## 🚀 Activation Steps

1. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "AI Travel Itinerary Generator"
   - Click "Activate"

2. **Configure Essential Settings**
   Go to **AI Itinerary → Settings**

### Step 1: General Settings (REQUIRED)
- **OpenAI API Key**: Get from https://platform.openai.com/api-keys
- **Free Itinerary Limit**: Set to 3 (default)
- **Premium Price**: Set your price (default $5.00)
- **Default Language**: Choose your language
- **Widget Style**: Chat, Form, or Both
- **AI Tone**: Friendly & Respectful (as requested)

### Step 2: Payment Settings (REQUIRED for Premium)

**For Stripe:**
- Get keys from https://dashboard.stripe.com/apikeys
- Enter Publishable Key
- Enter Secret Key
- Set Currency (USD, EUR, GBP)

**For PayPal:**
- Get credentials from https://developer.paypal.com/
- Enter Client ID
- Enter Client Secret

### Step 3: Affiliate Settings (OPTIONAL)
- Enter your Booking.com Affiliate ID
- Enter your Skyscanner Affiliate ID
- Enter your GetYourGuide Affiliate ID
- Choose "Hidden" for button style (as requested)

### Step 4: Authentication (OPTIONAL)
**For Google Sign-In:**
- Go to https://console.cloud.google.com/
- Create OAuth 2.0 credentials
- Add your site URL to authorized origins
- Enter Client ID and Client Secret

### Step 5: Branding (OPTIONAL)
- Set Primary Color
- Set Secondary Color
- Add Logo URL

## 📋 Configuration Checklist

Before going live, make sure:

- [ ] OpenAI API key is configured
- [ ] Payment method is set up (Stripe or PayPal)
- [ ] Free itinerary limit is set
- [ ] Premium price is set
- [ ] Widget appears on your site
- [ ] Test free itinerary generation
- [ ] Test premium itinerary with payment
- [ ] Test PDF download
- [ ] Affiliate IDs are configured (if using)
- [ ] Google Sign-In works (if enabled)

## 🎯 User Experience Flow

1. **Visitor arrives** → Sees floating widget button
2. **Opens widget** → Can sign up, login, or continue as guest
3. **Describes trip** → Via chat or form
4. **Chooses tier** → Free (basic) or Premium (detailed)
5. **Generates itinerary** → AI creates personalized plan
6. **Payment** (if premium) → Stripe or PayPal checkout
7. **Downloads PDF** → Styled itinerary document
8. **Affiliate links** → Hidden booking buttons integrated

## 🔧 Important Settings Explained

### Account Requirements
- **"Require account before purchase: Yes"** - Users must register to buy premium
- Guests can still generate free itineraries (within limits)

### Save Functionality
- **"Allow saving itineraries: Yes"** - Registered users can save
- Warning before closing unsaved itineraries

### Free Limits
- Applies per user (if logged in)
- Per session (if guest)
- Configurable in General Settings

## 📊 Admin Dashboard

Access via **AI Itinerary → Dashboard**

**Metrics shown:**
- Total itineraries (last 30 days)
- Total revenue
- Free vs Premium breakdown
- Daily charts for itineraries and revenue

**Analytics page:**
- Choose time period (7, 30, 90 days)
- View detailed statistics

## 💡 Tips & Best Practices

1. **API Costs**: Premium uses GPT-4 (more expensive). Monitor OpenAI usage.
2. **Pricing**: Consider your costs when setting premium price
3. **Free Limit**: 3 is good for conversion without giving too much free
4. **Affiliate Strategy**: Hidden links work better for user experience
5. **Testing**: Always test the full flow before going live

## 🎨 Customization

### Colors
- Set in Admin Panel → Settings → Branding
- Affects buttons, headers, and branding elements

### Tone
- Set to "Friendly & Respectful" as requested
- AI will use this tone in all responses

### Widget Placement
- Automatically appears on all pages as floating widget
- Use shortcode `[ai_itinerary]` to embed on specific pages

## 🐛 Troubleshooting

### Plugin won't activate
```bash
# Check PHP version
php -v  # Must be 7.4+

# Check for errors
tail -f /path/to/wordpress/wp-content/debug.log
```

### Widget doesn't appear
- Clear browser cache
- Check if jQuery is loaded
- Inspect browser console for JS errors

### AI generation fails
- Verify OpenAI API key is correct
- Check API key has credits
- Review WordPress error logs

### Payment fails
- Test API keys in Stripe/PayPal test mode first
- Check webhook configurations
- Verify correct currency

## 📱 User Data Collected

As requested, during signup we collect:
- First Name
- Last Name
- Email Address
- Password (encrypted)
- Google ID (if using Google Sign-In)

## 🔐 Security Features

✓ Nonce verification on all AJAX requests
✓ SQL injection prevention
✓ XSS protection
✓ Data sanitization
✓ Direct file access prevention
✓ Payment data security

## 📞 Next Steps

1. **Activate the plugin**
2. **Configure settings** (especially OpenAI API key)
3. **Test thoroughly** with test API keys
4. **Get affiliate IDs** from partners
5. **Set up Google OAuth** (optional)
6. **Customize branding** with your colors/logo
7. **Go live!**

## 🎊 You're All Set!

Your plugin is production-ready and includes all the features you requested. Test it thoroughly, configure your settings, and you're ready to help travelers plan amazing trips!

---

**Need Help?**
Check the detailed README.md for comprehensive documentation.

