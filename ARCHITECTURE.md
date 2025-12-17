# Feature Architecture & Data Flow

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                          FRONTEND                              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │   Widget HTML (Floating Button + Panel)                 │  │
│  │   ├─ Chat Interface: textarea + chat area               │  │
│  │   ├─ Form Interface: destination + date inputs          │  │
│  │   ├─ Save Button                                        │  │
│  │   └─ Download PDF Button                                │  │
│  └────────────────────────┬─────────────────────────────────┘  │
│                           │ AJAX + jQuery                      │
└───────────────────────────┼──────────────────────────────────────┘
                            │
              ┌─────────────┼─────────────┐
              │             │             │
              ▼             ▼             ▼
    ┌──────────────┐ ┌────────────┐ ┌──────────────┐
    │  Generate    │ │   Save     │ │  Check Prompt│
    │  Itinerary   │ │ Itinerary  │ │    Count     │
    └──────┬───────┘ └────┬───────┘ └──────┬───────┘
           │              │                 │
           └──────────────┼─────────────────┘
                          │
            ┌─────────────▼──────────────┐
            │   AJAX Endpoints           │
            │  (class-ai-api.php)        │
            └──────┬──────────┬──────────┘
                   │          │
        ┌──────────┘          └──────────┐
        │                                 │
        ▼                                 ▼
    ┌──────────────────┐       ┌─────────────────────┐
    │  OpenAI API Call │       │  Database Queries   │
    │  (GPT-3.5-turbo) │       │ (class-ai-database)│
    └──────────────────┘       └─────────────────────┘
        │                              │
        ▼                              ▼
    ┌──────────────────┐       ┌──────────────────────┐
    │  Itinerary Text  │       │ wp_ai_itineraries   │
    │  (JSON)          │       │ Table               │
    └──────────────────┘       └──────────────────────┘
```

## Data Flow: Generating an Itinerary

```
1. User Input
   ├─ Chat: Types "Paris 3 days" + hits Enter
   └─ Form: Fills Destination + dates, clicks Generate

2. Frontend (frontend.js)
   ├─ Validates input (non-empty destination)
   ├─ Checks prompt limit (via AJAX)
   ├─ Sends AJAX request to ai_generate_itinerary
   └─ Shows loading message

3. Backend (class-ai-api.php::generate_itinerary)
   ├─ Verifies nonce (security)
   ├─ Gets user ID (or 0 for guests)
   ├─ Checks prompt count
   │  ├─ Free users: Must have count < limit
   │  └─ Premium: Unlimited
   ├─ Increments prompt counter
   ├─ Calls OpenAI API
   │  └─ Prompt: "Create X day itinerary for [destination]..."
   ├─ Parses AI response
   └─ Returns JSON to frontend

4. Frontend Displays
   ├─ Hides loading message
   ├─ Shows itinerary text
   ├─ Stores in state.currentItinerary
   └─ Enables Save/Download buttons

5. Result
   └─ User sees formatted itinerary ready to save
```

## Data Flow: Saving an Itinerary

```
1. User Clicks "Save" Button
   └─ Checks if state.currentItinerary exists

2. Frontend (frontend.js::saveItinerary)
   ├─ Creates title: "Paris - 3 days"
   ├─ Serializes itinerary to JSON
   ├─ Sends AJAX to ai_save_itinerary
   └─ Shows "Saving..." message

3. Backend (class-ai-api.php::save_itinerary)
   ├─ Verifies nonce
   ├─ Gets user ID (or 0 for guests)
   ├─ Checks: Are guest saves allowed?
   │  ├─ If guest AND guest saves disabled → Error
   │  └─ If allowed → Continue
   ├─ Calls AI_Database::save()
   │  └─ Inserts into wp_ai_itineraries table
   └─ Returns new itinerary ID

4. Database
   ├─ INSERT row:
   │  ├─ user_id: NULL (guest) or 123 (user)
   │  ├─ title: "Paris - 3 days"
   │  ├─ data: {serialized JSON}
   │  ├─ created_at: 2025-12-03 12:34:56
   │  └─ updated_at: 2025-12-03 12:34:56
   └─ Returns inserted ID = 456

5. Frontend
   ├─ Shows alert "Saved successfully!"
   ├─ Clears state.currentItinerary
   └─ Disables Save/Download until next generation
```

## Prompt Counting Logic

```
User Makes Request
│
├─ Get user_id
│  ├─ If logged-in (> 0)
│  │  └─ Count = wp_usermeta[ai_prompt_count]
│  └─ If guest (= 0)
│     └─ Count = transient[ai_guest_prompts_{session_id}]
│
├─ Check Limit
│  ├─ Count < limit? → ALLOWED
│  │  └─ Increment count by 1
│  │  └─ Store back to usermeta/transient
│  │  └─ Process request
│  └─ Count >= limit? → BLOCKED
│     ├─ Check: Is user premium?
│     │  ├─ Admin? → ALLOWED (unlimited)
│     │  ├─ WooCommerce product owner? → ALLOWED
│     │  └─ Neither? → BLOCKED
│     └─ Return error
│
└─ Result
   ├─ Logged-in free user: 3 prompts per 24h (persistent)
   ├─ Guest user: 3 prompts per 24h (resets daily)
   └─ Premium user: Unlimited
```

## Premium/Free Access Matrix

```
╔════════════════╦═════════════════╦═════════════════╗
║     Feature    ║    Free User    ║  Premium User   ║
╠════════════════╬═════════════════╬═════════════════╣
║ Generate Items │  3 per 24h      │  Unlimited      ║
║ Save Itinerary │  Yes            │  Unlimited      ║
║ PDF Styles     │  Minimal only   │  All 3 styles   ║
║ Chat Responses │  Standard       │  Priority queue ║
║ API Model      │  GPT-3.5-turbo  │  GPT-4 (future) ║
╚════════════════╩═════════════════╩═════════════════╝

How to Unlock Premium:
├─ Purchase via WooCommerce (if enabled)
├─ Admin manual override (user meta flag)
└─ Current user: Always has unlimited (admins)
```

## Database Schema Visualization

```
wp_ai_itineraries
┌──────────┬────────────┬───────────────┬────────────────────┐
│    id    │  user_id   │     title     │       data         │
├──────────┼────────────┼───────────────┼────────────────────┤
│    1     │    123     │ Paris - 3 day │ {json serialized}  │
│    2     │   NULL     │ Rome - 5 day  │ {json serialized}  │
│    3     │    456     │ Tokyo - 7 day │ {json serialized}  │
│    4     │   NULL     │ NYC - 4 day   │ {json serialized}  │
└──────────┴────────────┴───────────────┴────────────────────┘

NULL user_id = Guest user (if allowed to save)
Numeric user_id = Logged-in user's itinerary
```

## Widget States & Transitions

```
                  ┌─────────────────┐
                  │   App Starts    │
                  └────────┬────────┘
                           │
                           ▼
                   ┌───────────────────┐
                   │  Widget Closed    │◄─────┐
                   │  (Button visible) │      │
                   └─────┬─────────────┘      │
                         │ Click button      │
                         ▼                   │
                   ┌───────────────────┐     │
                   │  Widget Open      │─────┘
                   │  (Panel visible)  │ Click close
                   └─────┬─────────────┘
                         │ Generate
                         ▼
                   ┌───────────────────┐
                   │  Loading...       │
                   │  (Spinner)        │
                   └─────┬─────────────┘
                         │
                    ┌────┴────┐
                    │          │
                    ▼          ▼
            ┌────────────┐ ┌────────────┐
            │ Success    │ │ Error      │
            │ Show text  │ │ Show error │
            └────────────┘ └────────────┘
                    │          │
                    └────┬─────┘
                         ▼
                ┌──────────────────┐
                │ Save/Download    │ ─────┐
                │ Options visible  │      │
                └──────────────────┘      │
                         │                │
                    ┌────┴────────────────┘
                    │
            Generate again or close
```

## API Request Payloads

### Generate Itinerary Request
```json
{
  "action": "ai_generate_itinerary",
  "nonce": "abc123xyz",
  "destination": "Paris",
  "days": 3,
  "language": "en",
  "style": "minimal"
}
```

### Save Itinerary Request
```json
{
  "action": "ai_save_itinerary",
  "nonce": "abc123xyz",
  "title": "Paris - 3 days",
  "data": "{\"content\": \"...\", \"destination\": \"Paris\", ...}"
}
```

### Check Prompt Count Request
```json
{
  "action": "ai_check_prompt_count",
  "nonce": "abc123xyz"
}
```

## API Response Examples

### Generate Success
```json
{
  "success": true,
  "data": {
    "itinerary": "Day 1: Arrive in Paris...",
    "destination": "Paris",
    "days": 3,
    "language": "en"
  }
}
```

### Generate Error (Prompt Limit)
```json
{
  "success": false,
  "data": {
    "message": "You have reached your free prompt limit. Please upgrade to Premium."
  }
}
```

### Save Success
```json
{
  "success": true,
  "data": {
    "message": "Itinerary saved successfully",
    "id": 456
  }
}
```

### Prompt Count Check
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

## Caching & Performance Considerations

```
Prompt Count Lookups
├─ Logged-in users: wp_usermeta (database)
└─ Guests: Transients (WordPress cache, 24h TTL)

API Response Caching (Not implemented yet)
├─ Could cache by: destination + language + style
├─ TTL: 7 days (since itineraries don't change much)
└─ Benefit: Reduce API calls by 30-40%

Database Indexes
├─ Primary key: id (auto)
├─ Foreign key: user_id (for lookups)
└─ Would help: Queries like "all itineraries for user X"
```

This architecture ensures scalability, security, and great UX! 🎉
