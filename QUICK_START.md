A simple quick-start guide to activate and see the AI Itinerary widget:

## Step-by-Step to See the Widget

### 1. Activate the Plugin
- Go to **WordPress Admin Dashboard** → **Plugins**
- Find **"AI Travel Itinerary Generator"** in the list
- Click **"Activate"** 
- You should see a notice saying "Plugin activated."

### 2. Add the Widget to a Page
**Option A: Using Shortcode (Easiest)**
- Go to WordPress Admin → **Pages** → Create a new page or edit an existing one
- Add this shortcode to the page content:
  ```
  [ai_itinerary_widget]
  ```
- Click **Publish** (or **Update**)
- Visit the page on your website (front-end)
- You should see a **"Plan trip"** button on the page (bottom right corner)

**Option B: Using Floating Widget Everywhere**
- The widget is set to "Floating" by default, so if you add the shortcode to the homepage, it will float
- To place it on all pages, you'd need to add code to your theme template (more advanced)

### 3. Test the Widget
- Click the **"Plan trip"** button
- The widget panel should expand showing a chat area and buttons
- Try typing in the input field (it's just a text area for now—full AI isn't connected yet)
- Click **"Save"** or **"Download PDF"** to test the stub buttons

### 4. Adjust Settings
- Go to WordPress Admin → **AI Itinerary** menu (on the left sidebar)
- You can change:
  - PDF Style (minimal, modern, image-heavy)
  - Interface Type (chat vs form)
  - Language, Widget Style, etc.
- These changes will update the widget behavior

## Troubleshooting

### "I don't see the widget button"
- **Did you activate the plugin?** Check Plugins page to confirm status is "Activate" (not "Deactivate")
- **Did you add the shortcode?** Make sure the page has `[ai_itinerary_widget]` in the content
- **Did you publish the page?** Make sure the page status is "Published" not "Draft"
- **Clear your browser cache** (hard refresh: Ctrl+Shift+R or Cmd+Shift+R)

### "I see text but no button"
- The shortcode might not be rendering. Check:
  - WordPress Admin → **Settings** → ensure shortcodes are enabled (they should be by default)
  - Refresh the page

### "Plugin doesn't show in Admin menu"
- The plugin may not have loaded. Try:
  1. Go to Plugins → Deactivate the plugin
  2. Reactivate it
  3. Check if "AI Itinerary" menu appears on the left sidebar of Admin

## Next Steps (After Seeing the Widget)
Once you confirm the widget appears:
1. We'll connect the AI backend (OpenAI API integration)
2. We'll implement actual PDF generation
3. We'll add save functionality
4. We'll test WooCommerce premium gating
