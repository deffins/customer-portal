# Google OAuth Integration Plan

## Overview
Add Google login with email-based user deduplication to prevent duplicate accounts when users authenticate via both Telegram and Google.

## Architecture

### Authentication Flow
```
User → Google OAuth → Get email → Check existing user by email
  ├─ Email match found → Link to existing account
  └─ No match → Create new user account
```

### Database Schema Changes

#### customer_portal_users table updates:
```sql
ALTER TABLE wp_customer_portal_users
  MODIFY telegram_id bigint(20) NULL,  -- Make nullable for Google-only users
  ADD COLUMN google_id varchar(255) NULL AFTER telegram_id,
  ADD COLUMN auth_provider varchar(50) DEFAULT 'telegram' AFTER google_id,
  ADD COLUMN email_verified tinyint(1) DEFAULT 0 AFTER email,
  ADD UNIQUE KEY google_id (google_id),
  ADD KEY email_idx (email);
```

#### New Fields:
- `google_id` - Google user ID (unique)
- `auth_provider` - Values: 'telegram', 'google', 'both'
- `email_verified` - Track if email is verified

### User Matching Logic

**Priority order for deduplication:**
1. **Email match** (primary) - If email exists and matches
2. **Google ID** - If user already logged in with Google before
3. **Telegram ID** - If user already logged in with Telegram before

### WordPress Plugin
**Nextend Social Login** (Free)
- Download: https://wordpress.org/plugins/nextend-facebook-connect/
- Supports: Google, Facebook, Apple, Twitter
- Features:
  - Automatic account linking
  - Email verification
  - Avatar sync
  - Custom hooks for user management

## Implementation Steps

### Phase 1: Database Migration ✅
1. Add new columns to users table
2. Update existing records (set auth_provider = 'telegram')
3. Add indexes for performance

### Phase 2: Install & Configure Plugin
1. Install Nextend Social Login plugin
2. Create Google OAuth app in Google Cloud Console
3. Configure plugin settings
4. Set up custom hooks for user creation/linking

### Phase 3: Custom Integration Code
1. Add hook to handle new Google users
2. Implement email-based matching
3. Update existing user records when linking accounts
4. Add admin UI to view auth methods per user

### Phase 4: User Experience
1. Add "Login with Google" button to portal
2. Add email collection prompt for Telegram users without email
3. Add account linking UI in user settings
4. Add notification when accounts are linked

### Phase 5: Testing
1. Test new Google user creation
2. Test email-based linking
3. Test Telegram user with no email
4. Test user with both accounts

## User Scenarios

### Scenario 1: New Google User
```
User clicks "Login with Google"
  → Authenticates with Google
  → Gets email from Google profile
  → No existing user with that email
  → Create new user record:
      - google_id: "123456789"
      - email: "user@gmail.com"
      - auth_provider: "google"
      - telegram_id: NULL
```

### Scenario 2: Existing Telegram User (with email) Logs in with Google
```
User clicks "Login with Google"
  → Authenticates with Google
  → Gets email: "user@gmail.com"
  → Find existing user with email "user@gmail.com"
  → Update user record:
      - google_id: "123456789"
      - auth_provider: "both" (was "telegram")
  → Log user in with linked account
```

### Scenario 3: Existing Telegram User (no email) Logs in with Google
```
User clicks "Login with Google"
  → Authenticates with Google
  → Gets email: "user@gmail.com"
  → No match found (Telegram user has no email)
  → Create temporary Google account
  → Show merge prompt: "Do you have a Telegram account? Enter your phone number"
  → User enters phone → Find Telegram account
  → Link accounts + add email to Telegram record
```

### Scenario 4: Existing Google User Uses Telegram
```
User logs in via Telegram bot
  → Telegram provides: telegram_id, name
  → Check if user exists by telegram_id → No
  → Prompt: "Please provide your email for account linking"
  → User provides: "user@gmail.com"
  → Find existing Google user with that email
  → Link accounts:
      - telegram_id: 987654321
      - auth_provider: "both" (was "google")
```

## Security Considerations

1. **Email Verification**
   - Google emails are pre-verified
   - Telegram emails need verification if manually entered

2. **Account Takeover Prevention**
   - Require email verification before merging
   - Send notification email when accounts are linked
   - Allow users to unlink in settings

3. **Privacy**
   - Store only necessary Google data
   - Allow users to delete Google connection
   - Clear consent for data usage

## Admin Features

### Admin Panel Additions
1. View user's auth methods (Telegram/Google/Both)
2. Manually link/unlink accounts
3. View login history by auth method
4. Export user data with auth method

### Reports
- Count users by auth method
- Track login method usage
- Identify duplicate accounts that need merging

## Migration Plan for Existing Users

### For Existing Telegram Users:
1. Email collection campaign (optional)
2. Gradual migration - no forced changes
3. Benefits messaging: "Link your Google account for easier login"

### For New Users:
1. Offer both login methods upfront
2. Recommend Google login (easier)
3. Automatic linking if email matches

## Timeline Estimate

- Phase 1 (Database): 1-2 hours
- Phase 2 (Plugin Setup): 2-3 hours
- Phase 3 (Custom Code): 4-6 hours
- Phase 4 (UX): 3-4 hours
- Phase 5 (Testing): 2-3 hours

**Total: 12-18 hours**

## Next Steps

1. Review and approve plan
2. Create database migration script
3. Set up Google Cloud Console project
4. Install WordPress plugin
5. Implement custom hooks
