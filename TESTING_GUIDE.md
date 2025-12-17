# Setup & Testing Guide for AI Itinerary Plugin

## Step 1: Verify Plugin is Activated

1. Go to WordPress Admin → **Plugins**
2. Find **"AI Travel Itinerary Generator"**
3. Confirm it says **"Deactivate"** (if it says "Activate", click Activate)
4. If already activated, click **Deactivate** then **Activate** to reload everything

## Step 2: Configure OpenAI API

1. Go to WordPress Admin → **AI Itinerary** (left menu)
2. **OpenAI API Key**: 
   - Get from https://platform.openai.com/api-keys
   - Click "Create new secret key"
   - Copy and paste into the field
3. **Free User Prompts**: Leave as `3` for testing
4. **Premium Price**: Set to `9.99` if you want to test WooCommerce later
5. Click **Save Settings**

## Step 3: Add Widget to a Page

1. Go to WordPress Admin → **Pages** → **Add New** (or edit an existing page)
2. In the content editor, add:
   ```
   [ai_itinerary_widget]
   ```
3. Set the page title to something like "Plan Your Trip"
4. Click **Publish** (or **Update**)
5. Click **View Page** to see the frontend

## Step 4: Test the Widget on Frontend

### First Time Setup
- You should see a **"Plan trip"** button (bottom-right of page)
- Click it to open the widget panel
- You should see either:
  - **Chat interface**: Text area with "Describe your trip..."
  - **Form interface**: Fields for Destination, Start Date, End Date
- (To switch between chat/form, go back to Admin → AI Itinerary and change "Interface Type")

### Test: Generate an Itinerary (Free User)

1. Click **"Plan trip"** button
2. If using **Chat interface**:
   - Type: `Paris 3 days` (or similar)
   - Press Enter
3. If using **Form interface**:
   - Destination: `Paris`
   - Start Date: Pick today
   - End Date: Pick 3 days from now
   - Click Generate
4. You should see a **loading message** (2-5 seconds)
5. The AI response will appear showing the itinerary

**Expected output**: A detailed itinerary with day-by-day activities

### Test: Save Itinerary

1. After generating an itinerary, click **"Save"** button
2. You should see an alert: "Itinerary saved successfully!"
3. The itinerary is now in the database

### Test: Prompt Limit (Free Users)

1. Generate 3 itineraries (one per prompt)
2. On the 4th attempt, you should see error:
   **"You have reached your free prompt limit. Please upgrade to Premium."**

### Test: PDF Download (Coming Soon)

- Click **"Download PDF"** button
- Currently shows: "PDF download will be available soon!" (to be implemented next)

## Step 5: Test as Guest User

### Open in Private/Incognito Window

1. Open the page in an **incognito window** (Ctrl+Shift+N or Cmd+Shift+N)
2. Do NOT log in
3. Generate an itinerary the same way
4. You should be able to:
   - Generate itineraries (counts as guest)
   - Save itineraries (if "Allow Guest Save" is enabled)

### Check Guest Saving Restrictions

- If you disabled "Allow Guest Save" in admin settings:
  - Clicking "Save" shows: "Guest saves are not allowed. Please log in."
- If enabled, guests can save but itineraries are tied to their session (guest_session cookie)

## Step 6: Test WooCommerce Integration (Optional)

### If You Have WooCommerce

1. Create a product called "AI Itinerary Premium"
2. Set price: `$9.99`
3. Note the product ID (shown in URL when editing product)
4. In WordPress Admin → AI Itinerary:
   - Check "Enable WooCommerce integration for premium purchases"
   - Save
5. Have a test customer purchase the product
6. After purchase, that user gets unlimited prompts
7. Test: log in as that customer and generate unlimited itineraries

## Step 7: Troubleshooting

### Error: "API request failed"
- **Check**: Your OpenAI API key is correct (copy it again from platform.openai.com)
- **Check**: Your OpenAI account has credit/hasn't exceeded limits
- **Check**: Your server can make HTTPS requests (some hosting blocks it)

### Error: "Nonce verification failed"
- This usually means JavaScript couldn't load or the page isn't recognizing the plugin
- **Solution**: 
  - Clear browser cache (Ctrl+Shift+Delete)
  - Deactivate and reactivate plugin
  - Check browser console (F12) for errors

### Widget doesn't appear
- **Check**: Hard refresh page (Ctrl+Shift+R)
- **Check**: Shortcode `[ai_itinerary_widget]` is in page content
- **Check**: Admin → AI Itinerary → "Enable Shortcode" is checked
- **Check**: Console (F12) shows no JavaScript errors

### Prompt count not working
- **For guests**: Ensure cookies are enabled in browser
- **For logged-in users**: Admin can manually reset count:
  - WordPress Admin → Users → Edit User
  - Scroll to plugin section and reset count

## Step 8: Monitor Database

### View Saved Itineraries in Database

```sql
-- Log in to WordPress database
SELECT * FROM wp_ai_itineraries ORDER BY created_at DESC;

-- View an itinerary's content
SELECT data FROM wp_ai_itineraries WHERE id = 1;

-- Count itineraries for a user
SELECT COUNT(*) FROM wp_ai_itineraries WHERE user_id = 1;
```

### Reset All Data (for testing)

```sql
-- Delete all saved itineraries
DELETE FROM wp_ai_itineraries;

-- Reset all user prompt counts
DELETE FROM wp_usermeta WHERE meta_key = 'ai_prompt_count';
```

## Step 9: Test Settings Changes

Go to Admin → AI Itinerary and test changing:

1. **PDF Style**: Change from "minimal" to "modern" or "image-heavy"
   - Frontend widget will remember the setting
   - Future PDFs will use the new style

2. **Interface Type**: Change from "chat" to "form"
   - Widget UI should change immediately (after page refresh)

3. **Output Language**: Change from "en" to "fr" or "de"
   - Next generated itinerary will be in that language

4. **Allow Guest Save**: Toggle checkbox
   - Disabling it will prevent guest saves

5. **Widget Style**: Change from "floating" to "embedded"
   - Widget should stay visible instead of floating button

## Next Steps After Testing

Once everything works:

1. **Implement PDF Generation** (next phase)
   - Add PDF download functionality
   - Support multiple PDF styles

2. **Optimize API Calls**
   - Add caching for repeated destinations
   - Use cheaper GPT-3.5 or more expensive GPT-4 as needed

3. **Add Admin Analytics**
   - Show usage stats per user
   - Track API costs and revenue

4. **Deploy to Production**
   - Set real OpenAI API key
   - Promote WooCommerce product
   - Monitor API usage and costs

## Quick Test Checklist

```
□ Plugin activated
□ OpenAI API key configured
□ Widget appears on page
□ Can generate itinerary (chat or form)
□ Can save itinerary
□ Prompt counter works (3 max, then blocked)
□ Guest mode works
□ Save works for guests (if enabled)
□ Settings panel works
□ Can change interface type
□ Can change language
□ Error messages display correctly
□ Database stores itineraries
```

**You're ready to test!** 🚀

Questions? Check the README.md in the plugin folder for more details.
