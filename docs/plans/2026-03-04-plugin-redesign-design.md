# AI Itinerary Plugin v2 — Full Redesign Design Document

**Date:** 2026-03-04
**Status:** Pending Approval
**Site:** yoiner.gamercity.io

---

## 1. Overview

Complete rebuild of the AI Travel Itinerary Generator plugin. Replace the current jQuery-based widget with a React-powered chat-first interface. Integrate with the site's existing WooCommerce, Paid Member Subscriptions, and TranslatePress plugins instead of building standalone payment/auth/subscription systems.

### Goals
- Modern, polished chat UI (like Layla/Mindtrip)
- Claude AI for conversation + itinerary generation with streaming responses
- Beautiful branded PDF export with images and maps
- Affiliate monetization via Travelpayouts + Skyscanner + GetYourGuide
- Dual pricing: per-itinerary purchase ($3-5) + monthly subscription ($9.99)
- English + Spanish (via TranslatePress)
- Full admin dashboard with analytics and revenue charts

### Non-Goals (handled by existing plugins)
- Payment processing (WooCommerce + Stripe Gateway + PayPal Payments)
- Subscription management (Paid Member Subscriptions)
- User authentication (WordPress native + WooCommerce My Account)
- Translation infrastructure (TranslatePress)
- SEO (Rank Math)
- Security (Wordfence)

---

## 2. Tech Stack

| Layer | Technology | Why |
|-------|-----------|-----|
| Frontend | React 18 + Zustand + Vite | Component-based, streaming support, tree-shaking |
| Backend | WordPress REST API (PHP) | Standard WP integration, proper HTTP verbs |
| AI | Anthropic Claude API | Client preference, streaming support |
| PDF | DOMPDF v2.x (PHP) | No extra services, good CSS3 support, embedded images |
| Payments | WooCommerce (existing) | Already configured with Stripe + PayPal |
| Subscriptions | Paid Member Subscriptions (existing) | Already installed and configured |
| Affiliates | Travelpayouts API + Skyscanner API + GetYourGuide (existing widget) | Hotels, flights, activities covered |
| i18n | TranslatePress (existing) + WordPress __() | Auto-translates UI strings |
| Build | Vite | Fast dev server, optimized production builds |

---

## 3. Plugin Structure

```
ai-itinerary-plugin/
├── ai-itinerary-plugin.php            # Main plugin file, constants, autoloader
├── includes/
│   ├── class-aip-admin.php            # Admin panel: settings, dashboard, analytics
│   ├── class-aip-rest-api.php         # All REST API endpoints
│   ├── class-aip-claude.php           # Claude API client (streaming + non-streaming)
│   ├── class-aip-database.php         # Custom tables: itineraries, analytics, affiliate clicks, conversations
│   ├── class-aip-frontend.php         # Enqueue React bundle, render shortcode + widget
│   ├── class-aip-pdf.php              # DOMPDF generation with templates
│   ├── class-aip-woocommerce.php      # WC product creation, order hooks, checkout redirect
│   ├── class-aip-membership.php       # Paid Member Subscriptions: check level, gate features
│   ├── class-aip-skyscanner.php       # Skyscanner Flights API integration
│   └── class-aip-travelpayouts.php    # Travelpayouts hotel/activity link generation
├── frontend/                           # React source (dev only, not shipped)
│   ├── src/
│   │   ├── main.jsx                   # Entry point, mounts React app
│   │   ├── App.jsx                    # Root component, context providers
│   │   ├── components/
│   │   │   ├── Widget/
│   │   │   │   ├── WidgetTrigger.jsx  # Floating chat button
│   │   │   │   └── WidgetPanel.jsx    # Expandable panel container
│   │   │   ├── Chat/
│   │   │   │   ├── ChatView.jsx       # Chat container
│   │   │   │   ├── MessageList.jsx    # Scrollable message area
│   │   │   │   ├── BotMessage.jsx     # Left-aligned bot messages
│   │   │   │   ├── UserMessage.jsx    # Right-aligned user messages
│   │   │   │   ├── TypingIndicator.jsx # Animated dots
│   │   │   │   ├── ChatInput.jsx      # Text input + send button
│   │   │   │   └── GenerateButtons.jsx # Free/Premium choice (inline in chat)
│   │   │   ├── Itinerary/
│   │   │   │   ├── ItineraryPanel.jsx # Right column (full page) or expand (widget)
│   │   │   │   ├── DayCard.jsx        # Single day card
│   │   │   │   ├── ActivityItem.jsx   # Activity with time/location
│   │   │   │   ├── MealItem.jsx       # Meal recommendation
│   │   │   │   └── HotelItem.jsx      # Hotel recommendation (premium)
│   │   │   ├── Payment/
│   │   │   │   └── CheckoutButton.jsx # "Go to checkout" → WooCommerce
│   │   │   ├── Affiliate/
│   │   │   │   ├── AffiliateSection.jsx
│   │   │   │   └── AffiliateButton.jsx # Hotels/Flights/Activities
│   │   │   ├── PDF/
│   │   │   │   └── DownloadButton.jsx # Trigger PDF generation + download
│   │   │   └── Common/
│   │   │       ├── Header.jsx         # Widget/page header
│   │   │       ├── LimitCounter.jsx   # "3 free remaining"
│   │   │       └── CloseWarning.jsx   # "Download PDF first?"
│   │   ├── hooks/
│   │   │   ├── useChat.js            # Chat state + API calls
│   │   │   ├── useAuth.js            # WP auth state check
│   │   │   ├── useItinerary.js       # Itinerary data
│   │   │   └── useStreaming.js       # SSE streaming from Claude
│   │   ├── stores/
│   │   │   └── appStore.js           # Zustand global state
│   │   ├── api/
│   │   │   └── client.js             # REST API client (fetch wrapper)
│   │   └── i18n/
│   │       ├── en.json               # English strings
│   │       └── es.json               # Spanish strings
│   ├── vite.config.js
│   ├── package.json
│   └── index.html                     # Dev server entry
├── assets/
│   ├── dist/                          # Built React bundle (shipped in plugin)
│   │   ├── aip-widget.js
│   │   └── aip-widget.css
│   ├── css/
│   │   └── admin.css                  # Admin panel styles
│   └── js/
│       └── admin.js                   # Admin panel JS (Chart.js)
├── templates/
│   └── pdf/
│       ├── modern.php                 # PDF template: modern style
│       ├── luxury.php                 # PDF template: luxury style
│       └── minimal.php               # PDF template: minimal style
├── docs/
│   └── plans/
│       └── 2026-03-04-plugin-redesign-design.md  # This document
├── composer.json                      # DOMPDF dependency
└── README.md
```

---

## 4. User Experience Flow

### 4.1 Conversation Flow (State Machine)

```
[IDLE] → user opens widget → [CHAT_ACTIVE]
                                    │
                      Ask 6 questions one-by-one:
                      1. Destination → "Where do you want to go?"
                      2. Days → "How many days?"
                      3. Trip type → "Leisure, adventure, family, business, or honeymoon?"
                      4. Budget → "Low, medium, or high?"
                      5. Interests → "Any specific places or interests?"
                      6. Pace → "Relaxed or packed schedule?"
                                    │
                                    ▼
                          [ALL_INFO_COLLECTED]
                                    │
                      AI says: "Ready! Choose your plan:"
                      [Free Basic] [Premium Detailed] ← inline buttons
                                    │
                      ┌─────────────┴─────────────┐
                      │                             │
                [FREE]                        [PREMIUM]
                      │                             │
                      │              ┌──────────────┴──────────────┐
                      │              │                              │
                      │        [HAS_SUBSCRIPTION?]          [NO_SUBSCRIPTION]
                      │         (Paid Member Subs)                  │
                      │              │                     Redirect to WC checkout
                      │              │                     (single $3-5 or subscribe $9.99)
                      │              │                              │
                      │              │                     [PAYMENT_COMPLETE]
                      │              │                     (WC webhook)
                      │              │                              │
                      └──────────────┴──────────────────────────────┘
                                    │
                                    ▼
                           [GENERATING...]
                         Claude API streaming
                         (text appears word-by-word)
                                    │
                                    ▼
                        [ITINERARY_READY]
                         Display day-by-day
                              │
                    ┌─────────┼──────────┐
                    │         │          │
              [Download]  [Save]  [Affiliate Links]
                 PDF      to DB    Hotels/Flights/Activities
                    │
              [CLOSE_WARNING]
              "Download PDF first?"
```

### 4.2 Widget UX (Floating, all pages)

- Bottom-right chat bubble with travel icon
- Expands to 400px wide panel
- Full chat interface inside
- After generation: itinerary replaces chat area, with "Back to chat" option
- Mobile: goes full-screen

### 4.3 Full Page UX (/travel-planner shortcode or Elementor widget)

- Two-column layout: chat left (40%), itinerary preview right (60%)
- Chat is always visible on the left
- Right panel shows:
  - Empty state illustration before generation
  - Streaming itinerary during generation
  - Complete day-by-day view after generation
  - Action bar (Download PDF, Save, New Trip)
  - Affiliate section at bottom
- Mobile: stacks vertically (chat on top, itinerary below)

### 4.4 Auth Flow

Users can chat freely without logging in. Auth is required only when:
- They try to generate an itinerary (free or premium)
- They try to save an itinerary

Auth options:
1. **WordPress login** — modal with email/password, uses `wp_signon()`
2. **WordPress register** — first name, last name, email, password, uses `wp_create_user()`
3. **Google Sign-in** — one-tap, creates/links WP user account
4. **WooCommerce My Account** — "Already have an account? Log in" link

### 4.5 Close Warning

When user has an unsaved/undownloaded itinerary and tries to:
- Close the widget
- Navigate away from the page
- Close the browser tab

Show modal: "You have an itinerary ready! Download your PDF before leaving?"
- [Download PDF] — generates and downloads
- [Close anyway] — dismisses

---

## 5. AI Integration (Claude)

### 5.1 Conversation System Prompt

```
You are {bot_name}, a fun and friendly travel assistant. Always respectful.

RULES:
- Ask ONE question at a time
- Acknowledge each answer before asking the next
- Keep responses to 2-3 sentences max
- Do NOT mention payment/pricing until ALL 6 questions are answered
- After all info collected, present Free vs Premium options
- Never generate itinerary content during conversation — only after explicit generation

REQUIRED QUESTIONS (in order):
1. Destination
2. Number of days
3. Trip type
4. Budget range
5. Interests
6. Pace

TONE: Fun, friendly, respectful. Use occasional emojis.
LANGUAGE: Respond in {user_language}.
```

### 5.2 Itinerary Generation Prompt

```
Generate a {detail_level} {days}-day itinerary for {destination}.

User preferences:
- Trip type: {trip_type}
- Budget: {budget}
- Interests: {interests}
- Pace: {pace}

Language: {language}

Return as JSON:
{
  "destination": "city, country",
  "days": N,
  "summary": "2-3 sentence overview",
  "itinerary": [
    {
      "day": 1,
      "title": "Day title",
      "activities": [
        {
          "time": "09:00",
          "period": "morning",
          "title": "Activity name",
          "description": "Details",
          "location": "Location name",
          "coordinates": { "lat": 0, "lng": 0 },
          "duration": "2 hours",
          "cost_estimate": "$20-30"
        }
      ],
      "meals": {
        "breakfast": { "name": "Restaurant", "cuisine": "Type", "price_range": "$" },
        "lunch": { ... },
        "dinner": { ... }
      },
      "accommodation": { "name": "Hotel", "price_range": "$$", "area": "District" }
    }
  ],
  "tips": ["tip1", "tip2"],
  "budget_summary": {
    "total_estimate": "$500-800",
    "breakdown": { "accommodation": "$X", "food": "$X", "activities": "$X", "transport": "$X" }
  },
  "best_time_to_visit": "March-May",
  "packing_suggestions": ["item1", "item2"]
}
```

### 5.3 Streaming

- Use Claude's streaming API via Server-Sent Events (SSE)
- PHP endpoint opens Claude stream, relays chunks to React frontend
- React displays text appearing word-by-word in the chat
- For itinerary generation: stream the JSON, parse incrementally, update day cards as they arrive

---

## 6. Payment Integration (WooCommerce)

### 6.1 Product Setup

On plugin activation, auto-create two WooCommerce products:
1. **"Premium Travel Itinerary"** — Simple product, price from admin settings (default $5)
2. **"Travel Buddy Premium Monthly"** — Subscription product, $9.99/month (requires WC Subscriptions or use Paid Member Subscriptions)

### 6.2 Purchase Flow

```
User clicks "Premium" in chat
        │
        ▼
Plugin creates WC cart item with metadata:
  - product_id: premium itinerary product
  - aip_itinerary_data: serialized trip preferences
  - aip_user_id: current user
        │
        ▼
Redirect to WooCommerce checkout page
  (Stripe / PayPal already configured)
        │
        ▼
WC processes payment
        │
        ▼
Plugin hooks into woocommerce_order_status_completed:
  - Reads itinerary data from order meta
  - Triggers Claude generation
  - Saves itinerary to aip_itineraries
  - Redirects user back to widget with itinerary ready
```

### 6.3 Subscription Check

```php
// Check if user has active subscription via Paid Member Subscriptions
function aip_user_has_premium($user_id) {
    if (function_exists('pms_is_member_of_plan')) {
        return pms_is_member_of_plan($user_id, 'premium');
    }
    return false;
}
```

---

## 7. PDF Generation (DOMPDF)

### 7.1 Approach

- Use DOMPDF v2.x via Composer
- Build PDF from HTML/CSS templates (not raw PHP drawing)
- Embed destination images via Unsplash API (free, no API key needed for small usage)
- Embed static map images via OpenStreetMap tile server
- Support 3 PDF styles: modern, luxury, minimal (admin-configurable)

### 7.2 PDF Content (Premium)

```
┌─────────────────────────────────────┐
│          [Hero Image]               │
│     PARIS, FRANCE                   │
│     7-Day Adventure Itinerary       │
│     Generated by Travel Buddy       │
│─────────────────────────────────────│
│                                     │
│  DAY 1: Arrival & Champs-Elysees   │
│  ┌─────┐                           │
│  │ Map │  09:00 - Check in hotel    │
│  └─────┘  11:00 - Walk Champs-...  │
│           13:00 - Lunch at Cafe..   │
│           15:00 - Arc de Triomphe   │
│           19:00 - Dinner at ...     │
│                                     │
│  🏨 Hotel: Le Marais Boutique      │
│  💰 Day estimate: $150-200         │
│                                     │
│─────────────────────────────────────│
│  DAY 2: ...                         │
│─────────────────────────────────────│
│                                     │
│  BUDGET SUMMARY                     │
│  Total: $1,200 - $1,800            │
│  ├─ Hotels: $700-900               │
│  ├─ Food: $250-400                 │
│  ├─ Activities: $150-300           │
│  └─ Transport: $100-200            │
│                                     │
│  TRAVEL TIPS                        │
│  • Buy a Paris Museum Pass          │
│  • Metro is faster than taxis       │
│                                     │
│─────────────────────────────────────│
│  Book your trip:                    │
│  travelpayouts.com/...              │
│  skyscanner.com/...                 │
│                                     │
│  Powered by Travel Buddy            │
│  yoiner.gamercity.io                │
└─────────────────────────────────────┘
```

---

## 8. Affiliate Integration

### 8.1 Travelpayouts (activate existing plugin + API)

| Category | Link Format | Trigger |
|----------|-----------|---------|
| Hotels | `https://search.hotellook.com/?marker={token}&destination={city}&checkIn={date}&checkOut={date}` | After itinerary generated |
| Flights | `https://www.aviasales.com/?marker={token}&origin={origin}&destination={iata}` | After itinerary generated |
| Activities | Via GetYourGuide widget (already active) | Already on site |

### 8.2 Skyscanner (custom API integration)

- Use account SID + auth token to call Skyscanner Flights API
- Search for flights based on itinerary destination + dates
- Display top 3 cheapest flights in affiliate section
- Link to Skyscanner search results page

### 8.3 Display Style

Affiliate links appear AFTER itinerary generation in a "Book This Trip" section:
- Hidden by default (not pushy)
- Subtle branded buttons: [Book Hotels] [Find Flights] [Book Activities]
- Clicking opens affiliate link in new tab
- Track clicks in aip_affiliate_clicks table

---

## 9. REST API Endpoints

```
# Chat
POST   /wp-json/aip/v1/chat/message        Send message, get streaming AI response
POST   /wp-json/aip/v1/chat/reset           Reset conversation state

# Itinerary
POST   /wp-json/aip/v1/itinerary/generate   Generate itinerary (streaming)
GET    /wp-json/aip/v1/itinerary/{id}        Get saved itinerary
GET    /wp-json/aip/v1/itineraries           List user's itineraries
POST   /wp-json/aip/v1/itinerary/{id}/save   Save/bookmark itinerary

# PDF
POST   /wp-json/aip/v1/pdf/generate          Generate + return PDF download

# User
GET    /wp-json/aip/v1/user/status           Auth state + subscription + remaining free count
POST   /wp-json/aip/v1/auth/login            WP login (email + password)
POST   /wp-json/aip/v1/auth/register         WP register (first, last, email, password)
POST   /wp-json/aip/v1/auth/google           Google OAuth callback
POST   /wp-json/aip/v1/auth/logout           WP logout

# Affiliate
GET    /wp-json/aip/v1/affiliate/{dest}      Get affiliate links for destination
POST   /wp-json/aip/v1/affiliate/click       Track affiliate click

# Admin (requires manage_options)
GET    /wp-json/aip/v1/admin/analytics       Dashboard analytics data
GET    /wp-json/aip/v1/admin/revenue         Revenue data for charts
```

---

## 10. Database Schema

Only custom tables (everything else uses WP/WooCommerce):

```sql
-- Generated itineraries
CREATE TABLE {prefix}aip_itineraries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    days INT NOT NULL,
    type ENUM('free', 'premium') DEFAULT 'free',
    language VARCHAR(10) DEFAULT 'en',
    data LONGTEXT NOT NULL,              -- JSON: full itinerary
    wc_order_id BIGINT UNSIGNED DEFAULT NULL,  -- WooCommerce order link
    status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_destination (destination),
    INDEX idx_status (status)
);

-- Conversation state (temporary, cleared after generation)
CREATE TABLE {prefix}aip_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    session_id VARCHAR(64) DEFAULT NULL,  -- For guest users
    messages LONGTEXT NOT NULL,            -- JSON: chat history
    collected_data TEXT DEFAULT NULL,       -- JSON: extracted travel preferences
    ready_to_generate TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id)
);

-- Analytics events
CREATE TABLE {prefix}aip_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,       -- itinerary_generated, pdf_downloaded, etc.
    event_data TEXT DEFAULT NULL,           -- JSON: event metadata
    user_id BIGINT UNSIGNED DEFAULT 0,
    itinerary_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
);

-- Affiliate click tracking
CREATE TABLE {prefix}aip_affiliate_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT 0,
    itinerary_id BIGINT UNSIGNED DEFAULT NULL,
    provider VARCHAR(50) NOT NULL,         -- travelpayouts, skyscanner, getyourguide
    category VARCHAR(50) NOT NULL,         -- hotels, flights, activities
    destination VARCHAR(255) NOT NULL,
    link_url TEXT NOT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider (provider),
    INDEX idx_destination (destination),
    INDEX idx_clicked (clicked_at)
);

-- User itinerary count stored in wp_usermeta:
-- aip_free_count (INT) — number of free itineraries generated this month
-- aip_free_count_reset (DATE) — when to reset the monthly counter
```

---

## 11. Admin Panel

### 11.1 Menu Structure

```
AI Itinerary
├── Dashboard        — Stats overview + charts
├── Settings         — All configuration
│   ├── General      — Claude API key, bot name, tone, free limit, widget style
│   ├── Payment      — WC product IDs, pricing (references WooCommerce)
│   ├── Affiliates   — Travelpayouts token, Skyscanner SID/auth, link styles
│   ├── Auth         — Google OAuth client ID/secret
│   └── Branding     — Colors, logo, PDF style
├── Analytics        — Revenue charts, popular destinations, conversion rates
└── Itineraries      — Browse all generated itineraries
```

### 11.2 Dashboard Widgets

- Total itineraries (7d / 30d / 90d)
- Total revenue (from WooCommerce orders linked to our products)
- Free vs Premium conversion rate
- Most popular destinations (top 10)
- User signups over time
- Affiliate clicks by provider
- Revenue chart (daily, powered by Chart.js)

### 11.3 Settings Fields

**General:**
- Claude API Key (password field)
- Claude Model (select: claude-sonnet-4-6, claude-opus-4-6)
- Bot Name (text, default: "Travel Buddy")
- AI Tone (select: friendly, professional, casual)
- Free Itinerary Limit (number, per month)
- Widget Style (select: chat only)
- PDF Style (select: modern, luxury, minimal)

**Payment:**
- Per-Itinerary Price (number, syncs with WC product)
- Monthly Subscription Price (number, syncs with Paid Member Subs plan)
- WC Product ID for single itinerary (auto-created on activation)
- Subscription Plan ID (from Paid Member Subscriptions)

**Affiliates:**
- Travelpayouts API Token
- Skyscanner Account SID
- Skyscanner Auth Token
- Affiliate Link Style (select: hidden buttons, visible buttons)
- Enable/disable per provider

**Auth:**
- Google OAuth Client ID
- Google OAuth Client Secret
- Google OAuth Redirect URI (auto-generated)

**Branding:**
- Primary Color (color picker)
- Secondary Color (color picker)
- Logo URL (media uploader)

---

## 12. Security

- All REST endpoints use WordPress nonce verification
- Rate limiting: max 10 chat messages per minute per IP
- Rate limiting: max 5 itinerary generations per hour per user
- Input sanitization via WordPress sanitize_*() functions
- Output escaping via esc_html(), esc_attr(), wp_json_encode()
- Claude API key stored in wp_options (encrypted at rest by WP)
- CSRF protection on all forms
- Capability checks (manage_options) on all admin endpoints
- No direct file access (ABSPATH check on all PHP files)

---

## 13. Performance

- React bundle: ~80KB gzipped (code-split, tree-shaken)
- Lazy load: widget JS only loads when trigger button is in viewport
- Streaming: responses appear in real-time, no loading spinners
- PDF: generated server-side, cached for 24 hours per itinerary
- API calls: Claude response cached per conversation state hash
- Database: indexed queries, no N+1 patterns
- Images in PDF: fetched once, base64 encoded, cached

---

## 14. Credentials Summary

| Service | Credential | Admin Setting |
|---------|-----------|---------------|
| Claude (Anthropic) | API Key | aip_claude_api_key |
| Google OAuth | Client ID + Client Secret | aip_google_client_id, aip_google_client_secret |
| Travelpayouts | API Token | aip_travelpayouts_token |
| Skyscanner | Account SID + Auth Token | aip_skyscanner_sid, aip_skyscanner_auth_token |
| Stripe | Already configured in WooCommerce | N/A |
| PayPal | Already configured in WooCommerce | N/A |

---

## 15. What Gets Deleted From Current Plugin

The current plugin has these issues that will be removed in the rebuild:

1. **OpenAI integration** → replaced with Claude
2. **jQuery frontend** (~1500 lines) → replaced with React
3. **Custom auth forms** → replaced with WP native auth
4. **Custom payment modal** (broken Stripe/PayPal) → replaced with WooCommerce checkout
5. **Regex-based info extraction** (fragile) → replaced with Claude structured extraction
6. **PHP session usage** → replaced with WP transients for guest state
7. **console.log debug statements** → removed
8. **Hardcoded strings** → all use __() for TranslatePress
9. **alert() calls** → replaced with React modals
10. **Inline CSS/JS in PHP** → separated into React components

---

## 16. Migration Plan

Since this is a full rebuild, the migration is:

1. Build new plugin as `ai-itinerary-plugin-v2` alongside existing
2. Test on local (http://localhost)
3. Once working, deactivate old plugin on production
4. Activate new plugin
5. Run activation hook (creates DB tables, WC products)
6. Configure settings in admin panel
7. Old tables can be kept for reference, eventually deleted
