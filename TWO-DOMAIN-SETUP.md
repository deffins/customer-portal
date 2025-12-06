# Two-Domain Test Environment Setup

## What You Have Now

**TWO separate WordPress sites on different domains:**

1. **deffo.pro** - Live/Production Site
   - Uses: **main branch**
   - Plugin folder: `/domains/deffo.pro/public_html/wp-content/plugins/customer-portal/`
   - For: Your real users/clients
   - Stable, production-ready code

2. **fons.lv** - Test/Development Site
   - Uses: **test branch**
   - Plugin folder: `/home/u226352978/domains/fons.lv/public_html/wp-content/plugins/customer-portal/`
   - For: YOU to test new features safely
   - Can break without affecting live users

## How It Works

```
Your Git Repository
├── main branch → auto-deploys to → deffo.pro (LIVE)
└── test branch → auto-deploys to → fons.lv (TEST)

Two Completely Separate WordPress Sites:
- Different databases
- Different users
- Different settings
- No conflicts possible!
```

## Your Daily Workflow

### 1. Working on New Feature

```bash
# Switch to test branch
git checkout test

# Make your code changes in your editor
# Edit files...

# Save and push
git add .
git commit -m "Testing new booking feature"
git push
```

**What happens:**
- GitHub automatically deploys to **fons.lv**
- You can test on http://fons.lv
- Live site (deffo.pro) is NOT affected
- Safe to break things!

### 2. Testing Your Changes

1. Visit **http://fons.lv** in browser
2. Test your new feature
3. Check if everything works
4. Make more changes if needed (repeat step 1)

### 3. Moving to Production (When Ready)

```bash
# Switch to main branch
git checkout main

# Bring in your tested changes
git merge test

# Deploy to live site
git push
```

**What happens:**
- GitHub automatically deploys to **deffo.pro**
- Live users see your new feature
- Confident because you tested on fons.lv first!

## Benefits of This Setup

✅ **Complete Safety** - Test site can't break live site
✅ **Real Environment** - Test on actual hosting, not local computer
✅ **Same Data** - Copy of real site for realistic testing
✅ **Automatic Deployment** - Push code, it deploys automatically
✅ **No Conflicts** - Separate databases, separate everything
✅ **Easy Management** - Both on same Hostinger account

## Quick Command Reference

| What You Want | Command |
|---------------|---------|
| Start working on new feature | `git checkout test` |
| Save your work | `git add .` then `git commit -m "description"` |
| Deploy to test site | `git push` (while on test branch) |
| Check which branch you're on | `git branch` |
| Move tested code to live | `git checkout main` then `git merge test` then `git push` |
| See what changed | `git status` |

## Visual Workflow

```
┌─────────────────────────────────────────────┐
│  YOU: Make changes on test branch          │
└─────────────────┬───────────────────────────┘
                  │
                  │ git push
                  ↓
┌─────────────────────────────────────────────┐
│  fons.lv - TEST SITE                        │
│  Try it out, break things, no problem!      │
└─────────────────┬───────────────────────────┘
                  │
                  │ Works? Merge to main!
                  ↓
┌─────────────────────────────────────────────┐
│  deffo.pro - LIVE SITE                      │
│  Users see polished, tested features        │
└─────────────────────────────────────────────┘
```

## Environment-Specific Configuration

### Telegram Bot Setup (Important!)

Each WordPress site needs its own Telegram bot configuration to avoid conflicts:

**Why?** If both sites used the same Telegram bot, users would get confused seeing the same bot on both test and production, and authentication tokens would be shared.

**Solution:** Use wp-config.php constants (one for each site, NOT in git)

#### Setup Steps

**1. Create Two Telegram Bots:**
- Open Telegram, search for `@BotFather`
- Create production bot: `/newbot` → Name it (e.g., "Deffo Customer Portal")
- Copy production bot token and username
- Create test bot: `/newbot` → Name it (e.g., "Deffo Test Bot")
- Copy test bot token and username

**2. Configure deffo.pro (Production):**
Edit `/domains/deffo.pro/public_html/wp-config.php` and add before `/* That's all, stop editing! */`:

```php
// Customer Portal - Production Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', 'your-production-bot-token-here');
define('CP_TELEGRAM_BOT_USERNAME', 'your_prod_bot_username'); // without @
```

**3. Configure fons.lv (Test):**
Edit `/home/u226352978/domains/fons.lv/public_html/wp-config.php` and add before `/* That's all, stop editing! */`:

```php
// Customer Portal - Test Telegram Bot
define('CP_TELEGRAM_BOT_TOKEN', 'your-test-bot-token-here');
define('CP_TELEGRAM_BOT_USERNAME', 'your_test_bot_username'); // without @
```

**4. Verify Setup:**
- Go to WordPress Admin → Customer Portal → Settings
- You should see telegram fields are **disabled** with notice "defined in wp-config.php"
- This confirms constants are working!

**See WP-CONFIG-SETUP.md for detailed instructions.**

### How This Works

```
Same Plugin Code (from git)
         ↓
    ┌────────┴────────┐
    ↓                 ↓
deffo.pro          fons.lv
wp-config.php      wp-config.php
Production Bot     Test Bot
    ↓                 ↓
Users see          You test with
prod bot           test bot
```

**Key Point:** Pushing code from test → main does NOT affect bot configuration!
- Code is the same on both sites
- Each site reads its own wp-config.php
- Bots stay environment-specific
- No manual intervention needed!

### Google Credentials

Google Calendar/Drive credentials are **the same** on both sites:
- Still configured via WordPress admin settings page
- No wp-config.php constants needed
- Shared between environments (same Google project)

## Current Status

- ✅ Test branch configured to deploy to **fons.lv**
- ✅ Main branch configured to deploy to **deffo.pro**
- ✅ You are currently on: **test** branch
- ✅ WordPress copied to fons.lv (if clone completed)
- ⚠️ **TODO:** Configure Telegram bots in wp-config.php (see above)

## Next Steps

1. **Verify fons.lv works:**
   - Visit http://fons.lv
   - Make sure site loaded correctly
   - Check that plugin is active

2. **Test the deployment:**
   - Make a small change (add a comment in code)
   - Push to test branch
   - Check if it appears on fons.lv

3. **Start developing:**
   - Work on test branch
   - Test on fons.lv
   - Merge to main when ready!

## Important Notes

- **Always work on test branch first** - Don't edit main directly
- **Test thoroughly on fons.lv** before merging to main
- **Commit often** - Save your progress regularly
- **Write clear commit messages** - "Added booking feature" not "stuff"
- **fons.lv is YOUR playground** - Break things, learn, iterate!

## Troubleshooting

### Push doesn't deploy
- Check GitHub Actions tab in your repository
- Look for deployment logs
- Verify FTP credentials in GitHub secrets

### Changes not showing on fons.lv
- Clear browser cache
- Check if you're on test branch: `git branch`
- Verify deployment succeeded in GitHub Actions

### Want to test something risky
- Perfect! That's what fons.lv is for!
- Test branch → fons.lv → can't break live site
- If it breaks, just revert changes and push again

Happy coding! 🚀
