# Nextend Social Login Configuration

## What We've Done

✅ Created Google OAuth credentials in Google Cloud Console
✅ Added Google login button to Customer Portal page
✅ Created custom hooks to save Google users to database
✅ Implemented email-based account linking

## Final Configuration Steps

### Step 1: Disable Google Login on WordPress Admin

We need to tell Nextend to ONLY show the Google button on the Customer Portal page, NOT on the WordPress admin login.

1. Go to: **WordPress Admin → Settings → Nextend Social Login**
2. Click on **"Global Settings"** or **"Settings"** tab
3. Find **"Login form"** checkbox
4. **UNCHECK** "Login form" (this disables it on `/wp-login.php`)
5. Find **"Registration form"** checkbox
6. **UNCHECK** "Registration form" if checked
7. Click **"Save Changes"**

**Result:** Google button will ONLY appear on your custom portal page (via the PHP code we added)

### Step 2: Configure Button Appearance (Optional)

1. Still in Nextend settings → **"Design"** tab
2. Choose button style:
   - Icon + text (recommended)
   - Icon only
   - Text only
3. Button color: Default (Google brand colors)
4. Click **"Save Changes"**

### Step 3: Configure OAuth Consent Screen (IMPORTANT for Production)

To prevent refresh tokens from expiring after 7 days:

1. Go to: [Google Cloud Console - OAuth Consent Screen](https://console.cloud.google.com/apis/credentials/consent)
2. If status shows **"Testing"** → Click **"PUBLISH APP"**
3. Confirm publishing to Production mode
4. In Production mode, user logins persist indefinitely

**Why this matters:** In "Testing" mode, users get logged out after 7 days and can't log in again without re-authorization.

### Step 4: Test the Integration

#### Test Scenario 1: New Google User
1. Go to your Customer Portal page (fons.lv)
2. Click "Continue with Google"
3. Authenticate with a Google account that has NEVER logged in before
4. After login, you should see the portal dashboard
5. **Verify in Admin:** Go to Customer Portal → Users
   - New user should appear with `google_id` and `auth_provider = 'google'`

#### Test Scenario 2: Existing Telegram User + Google Login (Email Match)
1. Create a Telegram user with email: `test@gmail.com`
2. Log out
3. Click "Continue with Google" using the same email: `test@gmail.com`
4. **Expected Result:** Accounts are automatically linked
5. **Verify in Admin:**
   - User should have BOTH `telegram_id` AND `google_id`
   - `auth_provider` should be `'both'`

#### Test Scenario 3: Existing Telegram User + Google Login (Different Email)
1. Telegram user has email: `user@example.com`
2. Log in with Google using: `different@gmail.com`
3. **Expected Result:** Two separate accounts (no linking)
4. **Future Enhancement:** Prompt user to link accounts manually

### Step 5: Configure Redirect After Login (Optional)

After Google login, users are redirected to the Customer Portal page. If you want to customize this:

1. The redirect is handled in `customer-portal-oauth-hooks.php`
2. Function: `cp_redirect_after_google_login()`
3. Currently searches for page with `[customer_portal]` shortcode
4. You can hardcode a specific page URL if needed

## How It Works

### Login Flow:
```
User clicks "Continue with Google"
  ↓
Google OAuth popup appears
  ↓
User authenticates with Google
  ↓
Google returns: google_id, email, first_name, last_name
  ↓
Nextend creates WordPress user (if new)
  ↓
Our custom hook fires: cp_handle_google_login()
  ↓
save_google_user() checks:
  - Existing user with same google_id? → Update
  - Existing user with same email? → Link accounts (auth_provider = 'both')
  - No match? → Create new Customer Portal user
  ↓
User redirected to Customer Portal dashboard
```

### Database Changes:
When user logs in with Google, our database table gets updated:
```sql
-- New Google-only user
INSERT INTO wp_customer_portal_users
(google_id, email, first_name, last_name, auth_provider, email_verified)
VALUES ('123456789', 'user@gmail.com', 'John', 'Doe', 'google', 1);

-- Linking to existing Telegram user (email match)
UPDATE wp_customer_portal_users
SET google_id = '123456789', auth_provider = 'both', email_verified = 1
WHERE email = 'user@gmail.com';
```

## Troubleshooting

### Google button not showing on portal page
- Clear WordPress cache (if using caching plugin)
- Verify Nextend plugin is activated
- Check browser console for JavaScript errors
- Verify `the_nsl_login_buttons()` function exists

### Google button still showing on /wp-login.php
- Go to Nextend settings
- Uncheck "Login form" and "Registration form"
- Save changes
- Clear browser cache

### User authenticated but not saved to database
- Check PHP error logs: `/wp-content/debug.log`
- Verify `customer-portal-oauth-hooks.php` is being loaded
- Check if `nsl_login` hook is firing (add `error_log()` to debug)

### "Invalid redirect_uri" error
- Verify redirect URIs in Google Cloud Console match exactly:
  - `https://fons.lv/wp-login.php?loginSocial=google`
  - `https://deffo.pro/wp-login.php?loginSocial=google`
- No trailing slashes
- HTTPS required (not HTTP)

### Email-based linking not working
- User must have email set in Telegram account
- Emails must match EXACTLY (case-insensitive)
- Check database: `SELECT * FROM wp_customer_portal_users WHERE email = 'user@gmail.com'`

## Testing Checklist

- [ ] Nextend "Login form" setting is UNCHECKED
- [ ] Google button appears on Customer Portal page
- [ ] Google button does NOT appear on `/wp-login.php`
- [ ] New Google user creates record in database
- [ ] Email matching links Telegram + Google accounts
- [ ] `auth_provider` updates correctly ('telegram', 'google', 'both')
- [ ] User can switch between Telegram and Google login
- [ ] OAuth consent screen published to Production mode
- [ ] Tested on both fons.lv (test) and deffo.pro (production)

## Next Phase: Email Prompt UI

After basic Google login works, we can add:
- Prompt for Telegram users to add email (for account linking)
- Manual account linking interface in user settings
- Admin notification when accounts are linked
- Unlink feature

See `GOOGLE-OAUTH-SETUP.md` Phase 5 for details.
