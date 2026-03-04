# AI Travel Itinerary Generator

A WordPress plugin that generates personalized travel itineraries using AI (Claude by Anthropic), with a chat-based interface, PDF export, and affiliate integration.

## Features

- **AI Chat Interface** — Conversational travel planning powered by Claude API
- **Smart Itinerary Generation** — Free (concise) and Premium (detailed) itineraries
- **PDF Export** — Download styled PDF itineraries via DOMPDF
- **WooCommerce Integration** — Premium itinerary purchases via Stripe/PayPal
- **Membership Support** — Paid Member Subscriptions (PMS) integration
- **Affiliate Links** — Travelpayouts (hotels, flights, activities) + Skyscanner
- **Google Sign-In** — One-click authentication
- **Multi-language** — English and Spanish included
- **Auto-updates** — Updates directly from GitHub releases

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce (for premium purchases)
- Anthropic API key ([get one here](https://console.anthropic.com/))

## Installation

### From GitHub Release (Recommended)

1. Go to [Releases](https://github.com/sherazp995/ai-itinerary-plugin/releases)
2. Download the latest `.zip` asset
3. In WordPress Admin: **Plugins > Add New > Upload Plugin**
4. Upload the zip and activate

After the first install, future updates appear automatically in the Plugins page.

### From Source

```bash
cd wp-content/plugins/
git clone https://github.com/sherazp995/ai-itinerary-plugin.git
cd ai-itinerary-plugin
composer install --no-dev
cd frontend && npm install && npm run build
```

## Configuration

After activation, go to **AI Itinerary > Settings** in WP Admin:

| Tab | Settings |
|-----|----------|
| **General** | Claude API key, model, bot name, free itinerary limit |
| **Payment** | WooCommerce product IDs for free/premium |
| **Affiliates** | Travelpayouts token, Skyscanner API key/partner ID |
| **Auth** | Google OAuth Client ID |
| **Branding** | Primary color |

## Usage

The plugin provides two ways to display the itinerary generator:

- **Floating widget** — Appears automatically on all pages (bottom-right corner)
- **Shortcode** — `[ai_itinerary]` for a full-page experience

### Admin Dashboard

- **Dashboard** — Stats overview with Chart.js charts (daily itineraries, top destinations)
- **Settings** — All configuration options across 5 tabs
- **Itineraries** — Browse all generated itineraries

## Architecture

```
ai-itinerary-plugin/
├── ai-itinerary-plugin.php          # Bootstrap + activation hooks
├── includes/
│   ├── class-aip-database.php       # 4 custom tables, CRUD operations
│   ├── class-aip-claude.php         # Anthropic API (streaming + non-streaming)
│   ├── class-aip-rest-api.php       # 15 REST API endpoints
│   ├── class-aip-woocommerce.php    # Payment flow + order completion
│   ├── class-aip-membership.php     # PMS subscription checks
│   ├── class-aip-travelpayouts.php  # Hotel/flight/activity affiliate links
│   ├── class-aip-skyscanner.php     # Flight comparison links
│   ├── class-aip-pdf.php            # DOMPDF PDF generation
│   ├── class-aip-admin.php          # Admin panel (dashboard, settings)
│   ├── class-aip-frontend.php       # Widget + shortcode rendering
│   └── class-aip-updater.php        # GitHub release auto-updater
├── frontend/                        # React 18 + Zustand + Vite
│   └── src/
│       ├── components/              # Widget, Chat, Auth, Itinerary, Affiliate
│       ├── stores/                  # Zustand state management
│       ├── hooks/                   # useChat, useAuth, useItinerary
│       └── i18n/                    # en.json, es.json
├── assets/
│   ├── dist/                        # Built frontend (aip-widget.js/css)
│   └── admin.css                    # Admin styles
└── tests/                           # PHPUnit test suite
```

## REST API

All endpoints are under `/wp-json/aip/v1/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/chat/message` | Send chat message |
| POST | `/chat/reset` | Reset conversation |
| POST | `/itinerary/generate` | Generate itinerary (free/premium) |
| GET | `/itinerary/{id}` | Get single itinerary |
| GET | `/itineraries` | List user's itineraries |
| POST | `/itinerary/{id}/save` | Save itinerary |
| POST | `/pdf/generate` | Generate PDF download |
| GET | `/user/status` | Auth status + usage limits |
| POST | `/auth/login` | Login |
| POST | `/auth/register` | Register |
| POST | `/auth/google` | Google Sign-In |
| POST | `/auth/logout` | Logout |
| GET | `/affiliate/{destination}` | Get affiliate links |
| POST | `/affiliate/click` | Track affiliate click |
| GET | `/admin/analytics` | Admin analytics data |

## Database Tables

| Table | Purpose |
|-------|---------|
| `wp_aip_itineraries` | Generated itineraries (title, destination, days, data, type) |
| `wp_aip_conversations` | Chat conversation state per user/session |
| `wp_aip_analytics` | Event tracking (views, generations, downloads) |
| `wp_aip_affiliate_clicks` | Affiliate link click tracking |

## Testing

```bash
composer install
vendor/bin/phpunit
```

50 tests, 125 assertions covering:
- **Unit tests** — Database, Claude API, Membership, Travelpayouts, Skyscanner, PDF
- **Integration tests** — All REST API endpoints with mocked Claude responses

## Auto-Updates

The plugin checks GitHub releases every 12 hours. To release an update:

```bash
# Bump version in ai-itinerary-plugin.php (both header and AIP_VERSION constant)
git tag v2.1.0
git push origin master --tags
```

Then create a GitHub release from the tag. WordPress sites will see the update in **Plugins**.

## Premium Features

Premium itineraries include:
- Detailed day-by-day activities with timing
- Specific hotel and restaurant recommendations
- Budget breakdown
- Local tips and cultural insights
- Transportation details
- Affiliate booking links

## Troubleshooting

### Pretty permalinks not working (404 on /wp-json/)
- Set permalink structure in **Settings > Permalinks** (e.g. Post name)
- Ensure Apache has `AllowOverride All` for the WordPress directory
- Check `.htaccess` exists with WordPress rewrite rules

### Chat returns "Something went wrong"
- Verify your Anthropic API key in **AI Itinerary > Settings**
- Check your Anthropic account has sufficient credits

### Plugin won't activate
- Check PHP version (7.4+)
- Verify Composer dependencies are installed (`composer install`)

## Changelog

### Version 2.0.0
- Complete rewrite with React frontend
- Switched from OpenAI to Claude (Anthropic) API
- Added streaming chat support
- WooCommerce integration (replaces direct Stripe/PayPal)
- Paid Member Subscriptions support
- Travelpayouts + Skyscanner affiliate integration
- DOMPDF for server-side PDF generation
- GitHub-based auto-updater
- PHPUnit test suite (50 tests)

### Version 1.0.0
- Initial release with OpenAI, Stripe/PayPal, basic chat

## License

GPL v2 or later
