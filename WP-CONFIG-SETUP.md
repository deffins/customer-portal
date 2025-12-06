# wp-config.php Configuration Guide

## Overview

This guide explains how to configure environment-specific Telegram bot credentials using WordPress wp-config.php constants. This allows deffo.pro (production) and fons.lv (test) to use different Telegram bots while running the same plugin code.

## Why Use wp-config.php Constants?

**Benefits:**
- **Environment-specific:** Each WordPress site has its own wp-config.php (not in git)
- **Secure:** More secure than database storage, follows WordPress best practices
- **Automated:** Pushing test → main doesn't affect bot configuration
- **Fallback:** Plugin works with or without constants (backward compatible)

## Telegram Bot Configuration

### Step 1: Get Your Bot Credentials

For each environment, you'll need to create a separate Telegram bot:

1. Open Telegram and search for `@BotFather`
2. Send `/newbot` to create a new bot
3. Follow the prompts to name your bot
   - For production: e.g., "Deffo Customer Portal Bot"
   - For test: e.g., "Deffo Test Bot"
4. BotFather will provide:
   - **Bot Token** (long string like `1234567890:AAH...`)
   - **Bot Username** (like `deffo_portal_bot`)
5. Save these credentials for the next step

**Important:** Create TWO separate bots - one for production, one for test!

### Step 2: Edit wp-config.php on Each Site

You need to add constants to wp-config.php on **both** deffo.pro and fons.lv.

#### Location

The wp-config.php file is located in your WordPress root directory:
- For deffo.pro: `/domains/deffo.pro/public_html/wp-config.php`
- For fons.lv: `/domains/fons.lv/public_html/wp-config.php`

#### How to Edit

**Via Hostinger File Manager:**
1. Log into Hostinger control panel
2. Navigate to File Manager
3. Find wp-config.php in the root directory
4. Right-click → Edit
5. Find the line: `/* That's all, stop editing! Happy publishing. */`
6. Add the configuration **BEFORE** that line
7. Save the file

**Via SSH/SFTP:**
1. Connect to your server
2. Navigate to WordPress root
3. Edit wp-config.php with your preferred editor
4. Add configuration before `/* That's all, stop editing! */`
5. Save and upload

#### Configuration for deffo.pro (Production)

Add these lines to `/domains/deffo.pro/public_html/wp-config.php`:

```php
// Customer Portal - Production Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', 'YOUR_PRODUCTION_BOT_TOKEN_HERE');
define('CP_TELEGRAM_BOT_USERNAME', 'your_prod_bot_username'); // without @
```

**Example:**
```php
// Customer Portal - Production Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', '1234567890:AAHd7gF2s_8KLmN0PqRsTuVwXyZ');
define('CP_TELEGRAM_BOT_USERNAME', 'deffo_portal_bot');

/* That's all, stop editing! Happy publishing. */
```

#### Configuration for fons.lv (Test)

Add these lines to `/domains/fons.lv/public_html/wp-config.php`:

```php
// Customer Portal - Test Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', 'YOUR_TEST_BOT_TOKEN_HERE');
define('CP_TELEGRAM_BOT_USERNAME', 'your_test_bot_username'); // without @
```

**Example:**
```php
// Customer Portal - Test Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', '9876543210:BBH3kF9g_1MLpQ4RvSwTxYz');
define('CP_TELEGRAM_BOT_USERNAME', 'deffo_test_bot');

/* That's all, stop editing! Happy publishing. */
```

### Step 3: Verify Configuration

After adding the constants:

1. Go to WordPress Admin → **Customer Portal** → **Settings**
2. Under "Telegram Bot" section, you should see:
   - Fields are **disabled** (greyed out)
   - Info notice: "Telegram bot settings are defined in wp-config.php"
   - Token shows first 20 characters + "(defined in wp-config.php)"
   - Username shows full value + "(defined in wp-config.php)"

**If fields are still editable:**
- Constants are not defined or empty
- Check constant spelling: `CP_TELEGRAM_BOT_TOKEN` (exact case)
- Verify placement: before "stop editing" line
- Check for PHP syntax errors in wp-config.php

### Step 4: Test Telegram Authentication

**On fons.lv (Test):**
1. Visit the customer portal page
2. Telegram login widget should show your TEST bot
3. Click to authenticate
4. Should redirect to your test bot for login
5. After authentication, you should be logged in

**On deffo.pro (Production):**
1. Visit the customer portal page
2. Telegram login widget should show your PRODUCTION bot
3. Authenticate with production bot
4. Verify login works

## Constant Reference

### CP_TELEGRAM_BOT_TOKEN

**Type:** String
**Required:** No (falls back to WordPress option)
**Description:** Telegram bot API token from @BotFather
**Format:** `<bot_id>:<api_hash>` (e.g., `1234567890:AAH...`)
**Security:** Keep this secret! Never commit to git.

**Example:**
```php
define('CP_TELEGRAM_BOT_TOKEN', '1234567890:AAHd7gF2s_8KLmN0PqRsTuVwXyZ');
```

### CP_TELEGRAM_BOT_USERNAME

**Type:** String
**Required:** No (falls back to WordPress option)
**Description:** Telegram bot username (without @ symbol)
**Format:** Lowercase alphanumeric with underscores (e.g., `my_bot_name`)
**Note:** Do NOT include @ symbol

**Example:**
```php
define('CP_TELEGRAM_BOT_USERNAME', 'deffo_portal_bot');
```

## Fallback to WordPress Options

If constants are **not defined**, the plugin uses WordPress options from the database instead.

### When to Use Options Instead of Constants

Use WordPress options (via admin settings page) when:
- Quick testing with different bots
- Development environment without wp-config.php access
- Temporary bot configuration
- Single-environment setup

### How to Use Options

1. Go to WordPress Admin → Customer Portal → Settings
2. Enter bot token and username in the form
3. Click "Save Settings"
4. Plugin will use these values until constants are defined

### Priority

Constants **always override** options:
1. Plugin checks for `CP_TELEGRAM_BOT_TOKEN` constant first
2. If constant exists and is not empty, use it
3. Otherwise, use `get_option('cp_telegram_bot_token')`

## Troubleshooting

### Settings page shows editable fields instead of disabled

**Cause:** Constants are not defined or empty

**Solutions:**
- Check constant spelling: `CP_TELEGRAM_BOT_TOKEN` (exact case)
- Verify placement: before `/* That's all, stop editing! */`
- Ensure values are not empty strings
- Check for PHP syntax errors in wp-config.php
- Clear any object/page cache

### Authentication fails with "Invalid authentication"

**Cause:** Incorrect bot token

**Solutions:**
- Verify token matches your bot exactly (copy from @BotFather)
- Check for extra spaces or quotes around the token
- Ensure token corresponds to the bot username
- Test with @BotFather using `/token` command

### Widget shows wrong bot

**Cause:** Bot username mismatch

**Solutions:**
- Verify `CP_TELEGRAM_BOT_USERNAME` matches your bot
- Username should NOT include @ symbol
- Check spelling and case (usernames are lowercase)
- Clear browser cache and reload page

### Changes to constants not taking effect

**Cause:** WordPress or server-level caching

**Solutions:**
- Clear WordPress object cache
- Clear page cache (if using caching plugin)
- Clear PHP opcache (if enabled)
- Restart PHP-FPM or web server
- Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)

### Accidentally committed wp-config.php to git

**Action Required:** IMMEDIATELY change your bot tokens!

**Steps:**
1. Create new bots via @BotFather
2. Update wp-config.php with new tokens
3. Remove old bots or revoke tokens via @BotFather
4. Remove wp-config.php from git history (use git filter-branch)
5. Verify .gitignore includes wp-config.php

## Security Best Practices

### Do's
✅ Keep bot tokens in wp-config.php (not database)
✅ Use different bots for production and test
✅ Verify .gitignore excludes wp-config.php
✅ Restrict wp-config.php file permissions (chmod 600 or 640)
✅ Use strong, unique tokens from @BotFather
✅ Regularly rotate bot tokens (recreate bots periodically)

### Don'ts
❌ Never commit wp-config.php to git
❌ Never share bot tokens publicly
❌ Never use the same bot for production and test
❌ Never hardcode tokens in plugin files
❌ Never expose tokens in error messages or logs
❌ Never store tokens in client-side JavaScript

## File Permissions

Recommended permissions for wp-config.php:

```bash
# Owner read/write only
chmod 600 wp-config.php

# Or owner read/write, group read
chmod 640 wp-config.php
```

Verify ownership:
```bash
ls -la wp-config.php
# Should be owned by web server user
```

## Environment Workflow

### Development Workflow

1. **Make changes** on test branch
2. **Push to test** → Auto-deploys to fons.lv
3. **Test with test bot** (defined in fons.lv wp-config.php)
4. **Merge to main** → Auto-deploys to deffo.pro
5. **Production uses production bot** (defined in deffo.pro wp-config.php)

### No Manual Intervention Required!

Once wp-config.php constants are set up:
- Pushing code doesn't affect bot configuration
- Each site automatically uses its own bot
- Same code, different environments
- Zero-downtime deployments

## Additional Notes

### Google Credentials

Google Calendar/Drive credentials are **NOT** environment-specific:
- Still stored in WordPress options (database)
- Same credentials used on both sites
- Configured via admin settings page
- No constants needed

### Updating Bots

To change bot for a specific environment:
1. Create new bot via @BotFather
2. Edit wp-config.php on that site only
3. Update constants with new values
4. Save file (no code deployment needed)
5. Clear cache if necessary

### Removing Constants

To revert to WordPress options:
1. Remove or comment out constants in wp-config.php
2. Clear cache
3. Go to admin settings page
4. Fields should now be editable
5. Enter bot credentials and save

## Support

For issues or questions:
- Check TROUBLESHOOTING.md in the repository
- Review TWO-DOMAIN-SETUP.md for deployment info
- Check WordPress debug.log for PHP errors
- Inspect browser console for JavaScript errors

## Summary Checklist

Setup checklist for each environment:

**For deffo.pro (Production):**
- [ ] Create production bot via @BotFather
- [ ] Copy bot token and username
- [ ] Edit `/domains/deffo.pro/public_html/wp-config.php`
- [ ] Add `CP_TELEGRAM_BOT_TOKEN` constant
- [ ] Add `CP_TELEGRAM_BOT_USERNAME` constant
- [ ] Save file
- [ ] Verify fields are disabled in admin settings
- [ ] Test Telegram login on customer portal

**For fons.lv (Test):**
- [ ] Create test bot via @BotFather
- [ ] Copy bot token and username
- [ ] Edit `/domains/fons.lv/public_html/wp-config.php`
- [ ] Add `CP_TELEGRAM_BOT_TOKEN` constant
- [ ] Add `CP_TELEGRAM_BOT_USERNAME` constant
- [ ] Save file
- [ ] Verify fields are disabled in admin settings
- [ ] Test Telegram login on customer portal

**Final Verification:**
- [ ] Both sites work independently
- [ ] Each site uses its own bot
- [ ] Pushing test → main doesn't break configuration
- [ ] wp-config.php not in git repository
