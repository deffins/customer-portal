# Troubleshooting Guide

## Issue: Can't Click on Checklists or Links Tabs

If you can login and see the Files tab but clicking on "Checklists" or "Links" tabs doesn't work, follow these steps:

### Step 1: Check Browser Console for Errors

1. Open your browser's Developer Tools:
   - **Chrome/Edge**: Press `F12` or `Ctrl+Shift+I` (Windows) / `Cmd+Option+I` (Mac)
   - **Firefox**: Press `F12` or `Ctrl+Shift+K`

2. Click on the **Console** tab

3. Try clicking the "Checklists" or "Links" tab

4. Look for any red error messages

**Common errors and solutions:**

- **"Uncaught TypeError: Cannot read property 'addEventListener'"** → Script loaded before DOM ready
- **"404 (Not Found) portal.js"** → JavaScript file not uploaded properly
- **"Uncaught ReferenceError: cpConfig is not defined"** → Script localization issue

### Step 2: Clear All Caches

The most common issue is cached files. Clear everything:

#### WordPress Cache
1. If using a caching plugin (WP Super Cache, W3 Total Cache, etc.):
   - Go to WordPress Admin → Plugin Settings
   - Click "Clear All Cache" or "Purge Cache"

2. If using Hostinger's built-in cache:
   - Go to **hPanel** → **Website**
   - Find **Cache Manager** or **Advanced** → **Cache**
   - Click **Clear Cache**

#### Browser Cache
1. **Hard Refresh** (most important!):
   - Windows: `Ctrl + F5` or `Ctrl + Shift + R`
   - Mac: `Cmd + Shift + R`

2. **Clear Site Data**:
   - Chrome/Edge: `F12` → `Application` tab → `Clear storage` → `Clear site data`
   - Firefox: `F12` → `Storage` tab → Right-click → `Clear All`

3. **Private/Incognito Window**:
   - Try opening the site in a private/incognito window to bypass all cache

### Step 3: Verify Files Uploaded Correctly

Check that all files are on the server:

1. Log in to **Hostinger File Manager** or use **FTP**

2. Navigate to: `/public_html/wp-content/plugins/customer-portal/`

3. Verify these files exist:
   ```
   /customer-portal/
   ├── customer-portal.php
   ├── assets/
   │   ├── css/
   │   │   └── portal.css
   │   └── js/
   │       └── portal.js
   └── includes/
       ├── class-portal-ajax.php
       ├── class-portal-admin.php
       ├── class-portal-database.php
       └── class-portal-frontend.php
   ```

4. Check the **file dates** - they should show today's date/time (when you deployed)

5. If files are old, the deployment didn't work. Check GitHub Actions logs.

### Step 4: Check if JavaScript is Loading

1. Open Developer Tools (`F12`)
2. Go to **Network** tab
3. Refresh the page (`F5`)
4. Look for `portal.js` in the list
5. Click on it and check:
   - **Status**: Should be `200 OK`
   - **Size**: Should be ~20KB
   - Click **Response** tab and verify it shows JavaScript code

### Step 5: Check WordPress Debug Mode

Enable WordPress debugging to see hidden errors:

1. Connect via **FTP** or **File Manager**

2. Edit `/public_html/wp-config.php`

3. Find this line:
   ```php
   define('WP_DEBUG', false);
   ```

4. Change it to:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

5. Refresh your portal page

6. Check `/public_html/wp-content/debug.log` for errors

7. **Important**: Turn debug mode OFF when done:
   ```php
   define('WP_DEBUG', false);
   ```

### Step 6: Deactivate and Reactivate Plugin

1. Go to WordPress Admin → **Plugins**
2. Find "Customer Portal with Telegram Auth"
3. Click **Deactivate**
4. Wait 3 seconds
5. Click **Activate**
6. Clear all caches again (Step 2)
7. Hard refresh browser (`Ctrl+F5`)

### Step 7: Check for JavaScript Conflicts

Other plugins might interfere with the tabs:

1. Temporarily deactivate **all other plugins** except Customer Portal
2. Test if tabs work now
3. If they work, reactivate plugins one by one to find the conflict
4. Report the conflicting plugin

### Step 8: Manual JavaScript Test

Test if JavaScript is working at all:

1. Open browser console (`F12` → Console)
2. Type this and press Enter:
   ```javascript
   document.querySelectorAll('.tab-button').length
   ```
3. Should return `3` (for Files, Checklists, Links)
4. If it returns `0`, the HTML structure didn't load

5. Try manually triggering a tab:
   ```javascript
   document.querySelector('.tab-button[data-tab="checklists"]').click()
   ```
6. If this works, the event listeners aren't attached properly

### Step 9: Version Check

Make sure you have the latest version:

1. Go to WordPress Admin → **Plugins**
2. Check version is **2.0.1** or higher
3. If still showing 2.0, the files didn't upload
4. Manually upload via FTP or check GitHub Actions

### Step 10: Check Page Type

The scripts only load on WordPress **Pages**:

1. Make sure you added the shortcode to a **Page**, not a **Post**
2. Go to **Pages** → Edit the portal page
3. Verify it contains: `[customer_portal]`
4. **Important**: Make sure "Text Editor" shows exactly `[customer_portal]`, not HTML encoded

---

## Still Not Working?

If none of the above work, please provide:

1. **Browser console errors** (screenshot or copy/paste from Step 1)
2. **WordPress debug.log** contents (from Step 5)
3. **Network tab** showing portal.js status (from Step 4)
4. **WordPress version** and **PHP version** (hPanel → Advanced → PHP Config)
5. **List of other active plugins**

You can share these in a GitHub issue: https://github.com/deffins/customer-portal/issues

---

## Quick Fix Checklist

If you just deployed and tabs don't work, do this in order:

- [ ] Hard refresh browser: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
- [ ] Clear WordPress cache in admin panel
- [ ] Clear Hostinger cache in hPanel
- [ ] Open in private/incognito window
- [ ] Deactivate → Activate plugin
- [ ] Check browser console for errors (`F12`)
- [ ] Verify files uploaded (check GitHub Actions tab)

99% of the time, it's a caching issue. The hard refresh usually fixes it!
