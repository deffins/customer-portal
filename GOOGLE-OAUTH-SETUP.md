# Google OAuth Setup Guide

## Phase 1: Database Migration ✅ COMPLETED

The database has been updated with the following changes:
- Added `google_id` column (unique)
- Added `auth_provider` column (telegram/google/both)
- Added `email_verified` column
- Made `telegram_id` nullable
- Added email index for faster lookups
- All existing users marked as `auth_provider = 'telegram'`

## Phase 2: Install Nextend Social Login Plugin

### Step 1: Install Plugin

1. Go to WordPress Admin → Plugins → Add New
2. Search for "Nextend Social Login"
3. Install and activate "Nextend Social Login and Register"

**OR** manually:
```bash
cd /path/to/wordpress/wp-content/plugins
wget https://downloads.wordpress.org/plugin/nextend-facebook-connect.latest-stable.zip
unzip nextend-facebook-connect.latest-stable.zip
rm nextend-facebook-connect.latest-stable.zip
```

Then activate via WordPress admin.

### Step 2: Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable Google+ API:
   - Go to "APIs & Services" → "Library"
   - Search for "Google+ API"
   - Click "Enable"

4. Create OAuth 2.0 credentials:
   - Go to "APIs & Services" → "Credentials"
   - Click "Create Credentials" → "OAuth client ID"
   - Application type: "Web application"
   - Name: "Customer Portal - [Your Domain]"
   - Authorized JavaScript origins:
     ```
     https://fons.lv
     https://deffo.pro
     ```
   - Authorized redirect URIs:
     ```
     https://fons.lv/wp-login.php?loginSocial=google
     https://deffo.pro/wp-login.php?loginSocial=google
     ```
   - Click "Create"
   - Copy the Client ID and Client Secret

### Step 3: Configure Plugin

1. Go to WordPress Admin → Settings → Nextend Social Login
2. Click on "Google" provider
3. Click "Getting Started"
4. Enter credentials:
   - **Client ID**: [paste from Google Console]
   - **Client Secret**: [paste from Google Console]
5. Click "Save Changes"

### Step 4: Test Configuration

1. Click "Verify Settings"
2. If successful, enable the provider
3. Configure button appearance and placement

## Phase 3: Custom Integration (Code Already Added)

The following functions are now available in the database class:

### New Database Functions:
- `get_user_by_google_id($google_id)` - Find user by Google ID
- `get_user_by_email($email)` - Find user by email (for linking)
- `save_google_user($google_id, $email, $first_name, $last_name)` - Create/update Google user
- `link_google_to_telegram_user($user_id, $google_id)` - Link accounts

### How It Works:

**When user logs in with Google:**
1. Plugin authenticates with Google
2. Gets user data: google_id, email, first_name, last_name
3. Calls `save_google_user()` which:
   - Checks if Google ID exists → Update user
   - Checks if email matches existing user → Link accounts
   - Otherwise → Create new user

**User Deduplication Logic:**
```php
// Priority:
1. Match by google_id (existing Google user)
2. Match by email (link Telegram + Google accounts)
3. Create new user
```

## Phase 4: Add Custom Hooks

Create file: `customer-portal-oauth-hooks.php` in the plugin root:

```php
<?php
/**
 * Custom hooks for Nextend Social Login integration
 */

// Hook into user creation/login
add_action('nsl_login', 'cp_handle_google_login', 10, 2);
add_action('nsl_register_new_user', 'cp_handle_google_registration', 10, 2);

function cp_handle_google_login($user_id, $provider) {
    if ($provider !== 'google') return;

    // Get Google user data
    $social_user = NSL\Notices::get_user_data($user_id);
    $google_id = $social_user->get_user_id();
    $email = $social_user->get_email();
    $first_name = $social_user->get_first_name();
    $last_name = $social_user->get_last_name();

    // Save/link user in our database
    CP()->database->save_google_user($google_id, $email, $first_name, $last_name);
}

function cp_handle_google_registration($user_id, $provider) {
    if ($provider !== 'google') return;

    // Same as login
    cp_handle_google_login($user_id, $provider);
}
```

Then include this file in `customer-portal.php`:
```php
require_once plugin_dir_path(__FILE__) . 'customer-portal-oauth-hooks.php';
```

## Phase 5: Email Prompt for Telegram Users

### Add Email Collection Prompt

When Telegram user logs in without email, show prompt:

**Location:** After successful Telegram authentication
**UI:** Modal or inline prompt
**Message:** "Link your account with email for easier login in the future"

**Implementation:**
```javascript
// In portal.js or auth.js
function promptForEmail(telegramUser) {
    if (!telegramUser.email) {
        showEmailPrompt({
            title: 'Add Your Email',
            message: 'Link your email for Google login access',
            optional: true, // Can dismiss
            onSubmit: function(email) {
                // AJAX call to save email
                saveUserEmail(telegramUser.id, email);
            }
        });
    }
}
```

**AJAX Handler:**
```php
// In class-portal-ajax.php
public function save_user_email() {
    check_ajax_referer('cp_nonce', 'nonce');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (!$user_id || !is_email($email)) {
        wp_send_json_error(['message' => 'Invalid data']);
        return;
    }

    CP()->database->update_user_email($user_id, $email);

    wp_send_json_success(['message' => 'Email saved successfully']);
}
```

## Phase 6: Testing Checklist

### Test Scenarios:

- [ ] **New Google User**
  - Click "Login with Google"
  - Verify new user created with google_id, email
  - Verify auth_provider = 'google'
  - Verify can access portal features

- [ ] **Existing Telegram User (with email) → Google Login**
  - Telegram user has email: test@example.com
  - Log out, login with Google using same email
  - Verify accounts linked (google_id added)
  - Verify auth_provider changed to 'both'

- [ ] **Existing Telegram User (no email) → Google Login**
  - Telegram user has NO email
  - Login with Google
  - Verify new Google account created (separate)
  - Manually add email to Telegram account
  - Verify accounts can be linked

- [ ] **Existing Google User → Telegram Login**
  - Google user exists with email
  - Login via Telegram
  - Prompt for email appears
  - Enter same email
  - Verify accounts linked

- [ ] **Account Switching**
  - User with both auth methods
  - Login with Telegram → Works
  - Logout, login with Google → Works
  - Both access same data

## Configuration Options

### Plugin Settings to Configure:

1. **Button Placement**
   - Login form
   - Registration form
   - Comment form (disable)

2. **Button Style**
   - Icon + text
   - Color scheme to match your theme

3. **Registration Settings**
   - Auto-create WordPress user: Yes
   - Send welcome email: Optional
   - Default role: Subscriber

4. **User Data Sync**
   - Sync avatar: Yes
   - Sync email: Yes
   - Update on login: Yes

## Security Notes

1. **Email Verification**
   - Google emails are automatically verified
   - Mark `email_verified = 1` for Google users

2. **Account Linking Notification**
   - Send email when accounts are linked
   - Add to: `save_google_user()` when linking

3. **Unlink Feature**
   - Add ability to unlink in user settings
   - Require password confirmation

## Admin Features to Add

### View Auth Methods

Add column to admin users list:
```php
// In class-portal-admin.php
function show_auth_provider($user) {
    if ($user->auth_provider === 'both') {
        echo '<span>Telegram + Google</span>';
    } else {
        echo '<span>' . ucfirst($user->auth_provider) . '</span>';
    }
}
```

## Next Steps

1. ✅ Database migration complete
2. ⏳ Install Nextend Social Login plugin
3. ⏳ Configure Google OAuth credentials
4. ⏳ Add custom hooks
5. ⏳ Add email prompt UI
6. ⏳ Test all scenarios

## Support Resources

- [Nextend Social Login Documentation](https://nextendweb.com/social-login-docs/)
- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
