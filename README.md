# AI Travel Itinerary Generator Plugin

A WordPress plugin that uses OpenAI's GPT API to generate personalized travel itineraries with free and premium modes, PDF export, and multiple interface options.

## Features

✨ **Core Features**
- 🤖 AI-powered itinerary generation using OpenAI GPT-3.5/GPT-4
- 💬 Chat and Form-based interfaces
- 🎨 Multiple PDF export styles (minimal, modern, image-heavy)
- 💾 Save itineraries to database
- 🌍 Multilingual support (default: English)
- 🎯 Floating or embedded widget on any page

💰 **Pricing & Access Control**
- Free mode with limited prompts (configurable, default: 3 per 24 hours)
- Premium mode with unlimited access
- WooCommerce integration for premium purchases
- Guest user support with optional save restriction
- User-based prompt counting and tracking

🔒 **Security & UX**
- Nonce verification on all AJAX requests
- Per-user/per-session prompt rate limiting
- Optional warning on unsaved changes
- Input sanitization and validation
- Ownership verification for saved itineraries

## Installation

1. **Download and Activate**
   - Place the `ai-itinerary-plugin` folder in `/wp-content/plugins/`
   - Go to WordPress Admin → Plugins
   - Find "AI Travel Itinerary Generator" and click Activate

2. **Configure OpenAI API**
   - Go to WordPress Admin → **AI Itinerary** (left menu)
   - Enter your OpenAI API key (get one at https://platform.openai.com/api-keys)
   - Adjust free prompt limit (default: 3)
   - Set premium price if using WooCommerce

3. **Add Widget to Your Site**
   - Create or edit a page
   - Add this shortcode:
     ```
     [ai_itinerary_widget]
     ```
   - Publish the page
   - The "Plan trip" button will appear

## Configuration

### Admin Settings Panel

**Basic Settings**
- **OpenAI API Key**: Your API key from OpenAI
- **Free User Prompts**: Max prompts per 24h for free users (default: 3)
- **Premium Price**: Price for premium access (if using WooCommerce)

**Output Settings**
- **PDF Style**: Choose format (minimal, modern, image-heavy)
- **Output Language**: Language for AI responses (ISO code, e.g., `en`)
- **Widget Style**: Display as floating button or embedded
- **Interface Type**: Chat or form-based input

**Access Control**
- **Allow Guest Save**: Let non-logged-in users save itineraries
- **WooCommerce Integration**: Enable premium purchases via WooCommerce
- **Warn on Close**: Show warning if unsaved itinerary exists
- **Enable Shortcode**: Allow `[ai_itinerary_widget]` on pages

## AJAX Endpoints

All endpoints require a valid nonce. Responses are JSON.

### Generate Itinerary
```
POST /wp-admin/admin-ajax.php?action=ai_generate_itinerary
```

**Parameters:**
- `destination` (string, required): Travel destination
- `days` (int, optional, default: 1): Number of days
- `language` (string, optional): ISO language code
- `style` (string, optional): PDF style preference
- `nonce` (string, required): Nonce from `aiItinerary.nonce`

**Response:**
```json
{
  "success": true,
  "data": {
    "itinerary": "Day 1: ...",
    "destination": "Paris",
    "days": 3,
    "language": "en"
  }
}
```

### Save Itinerary
```
POST /wp-admin/admin-ajax.php?action=ai_save_itinerary
```

**Parameters:**
- `title` (string): Itinerary title
- `data` (JSON string): Itinerary data object
- `nonce` (string, required): Nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Itinerary saved successfully",
    "id": 123
  }
}
```

### Check Prompt Count
```
POST /wp-admin/admin-ajax.php?action=ai_check_prompt_count
```

**Response:**
```json
{
  "success": true,
  "data": {
    "can_use": true,
    "current_count": 1,
    "limit": 3,
    "remaining": 2
  }
}
```

## Database Schema

### `wp_ai_itineraries` Table
```sql
id              - Primary key, auto-increment
user_id         - WordPress user ID (NULL for guests)
title           - Itinerary title
data            - Serialized itinerary data
created_at      - Timestamp
updated_at      - Timestamp
```

## Prompt Counting

### For Logged-in Users
- Count stored in `user_meta` with key `ai_prompt_count`
- Persists across sessions
- Admins can manually reset via user profile or database

### For Guest Users
- Count stored in transient (expires in 24 hours)
- Tied to `ai_guest_session` cookie
- Resets daily automatically

## WooCommerce Integration

1. **Create a Premium Product**
   - Go to WooCommerce → Products → Add Product
   - Name it "AI Itinerary Premium"
   - Set a price
   - Save the product ID

2. **Link Product to Plugin**
   - Go to AI Itinerary settings
   - Note the product ID
   - It will be auto-detected after first purchase

3. **Premium Unlock**
   - After a customer completes an order with the premium product:
     - User gets `unlimited` prompts
     - Can save unlimited itineraries
     - Access all PDF styles

## Troubleshooting

### Widget doesn't appear
- [ ] Confirm plugin is activated (Admin → Plugins)
- [ ] Confirm shortcode `[ai_itinerary_widget]` is in page content
- [ ] Hard refresh browser (Ctrl+Shift+R)
- [ ] Check browser console for JavaScript errors

### "API request failed" error
- [ ] Verify OpenAI API key is set (Admin → AI Itinerary)
- [ ] Confirm API key is valid (test at https://platform.openai.com)
- [ ] Check OpenAI account has available credits
- [ ] Verify server can make outbound HTTPS requests

### Prompts not counting down
- [ ] Check browser console for nonce errors
- [ ] Confirm WordPress debug mode is off (can interfere with AJAX)
- [ ] Clear browser cache and try again
- [ ] For guests: ensure cookies are enabled

### PDF Download not working
- [ ] PDF generation not yet fully implemented (stub for now)
- [ ] Will be available in next update

## Development

### File Structure
```
ai-itinerary-plugin/
├── ai-itinerary-plugin.php         # Main plugin file
├── uninstall.php                   # Cleanup on uninstall
├── includes/
│   ├── class-ai-loader.php        # Loader/bootstrapper
│   ├── class-ai-admin.php         # Admin settings page
│   ├── class-ai-frontend.php      # Frontend widget & shortcode
│   ├── class-ai-api.php           # AJAX endpoints
│   ├── class-ai-database.php      # Database operations
│   └── class-ai-pdf.php           # PDF generation (stub)
├── assets/
│   ├── css/frontend.css           # Widget styles
│   └── js/frontend.js             # Widget JavaScript
└── templates/
    └── widget.php                 # Widget template (legacy)
```

### Extending the Plugin

**Add a custom PDF style:**
```php
// In includes/class-ai-pdf.php
public function generate($itinerary_data, $style = 'minimal') {
    if ($style === 'my-custom-style') {
        // Custom PDF generation logic
    }
}
```

**Add a new language:**
- Update admin settings to include language option
- Pass language to OpenAI in prompt

**Add payment integration:**
- Extend `AI_Api::is_premium_user()` to check your payment system
- Update `AI_Api::increment_prompt_count()` for premium users

## API Costs

- **GPT-3.5-turbo**: ~$0.002 per 1K tokens (very cheap)
- **GPT-4**: ~$0.03 per 1K tokens (more expensive, better quality)
- Average itinerary generation: 800-1500 tokens (~$0.002-0.005 per request)

## FAQ

**Q: Can I use this with GPT-4?**
A: Yes! Open `includes/class-ai-api.php` and change `"model" => "gpt-3.5-turbo"` to `"model" => "gpt-4"`

**Q: How do I reset a user's prompt count?**
A: In WordPress, go to Users → Edit User → Scroll to plugin section and reset count. Or via database:
```sql
DELETE FROM wp_usermeta WHERE meta_key='ai_prompt_count' AND user_id=123;
```

**Q: Can guests save itineraries permanently?**
A: By default, guest saves are allowed but only persist in the current session. To save across sessions, user must log in.

**Q: How do I customize the widget appearance?**
A: Edit `assets/css/frontend.css` to change colors, sizes, and layout. Reactivate plugin to clear cache.

## Support & Contributing

For issues, feature requests, or contributions:
- Check the troubleshooting section above
- Review your OpenAI API settings
- Ensure WordPress is up to date
- Check plugin activity log in WordPress

## License

This plugin is provided as-is for use in WordPress.

## Changelog

### v1.0.0 - Initial Release
- Core AI itinerary generation
- Free/Premium modes with prompt limiting
- Save itineraries to database
- Chat and form interfaces
- Multiple PDF styles (stub)
- WooCommerce integration ready
- Multilingual support
