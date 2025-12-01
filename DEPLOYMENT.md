# Automatic Deployment Setup

This guide will help you set up automatic deployment from GitHub to your Hostinger WordPress site.

## Prerequisites

- GitHub repository: https://github.com/deffins/customer-portal
- Hostinger Starter plan with WordPress installed
- FTP access to your Hostinger account

## Setup Instructions

### Step 1: Get Your Hostinger FTP Credentials

1. Log in to your **Hostinger hPanel**
2. Go to **Files** → **FTP Accounts**
3. Note down or create FTP credentials:
   - **FTP Server/Host**: Usually `ftp.yourdomain.com` or an IP address
   - **Username**: Your FTP username
   - **Password**: Your FTP password
   - **Port**: Usually 21 (FTP) or 22 (SFTP)

> **Tip**: If you can't find FTP Accounts, go to **Advanced** → **FTP Accounts**

### Step 2: Add Secrets to GitHub Repository

1. Go to your GitHub repository: https://github.com/deffins/customer-portal
2. Click **Settings** (top navigation)
3. In the left sidebar, click **Secrets and variables** → **Actions**
4. Click **New repository secret** and add these three secrets:

   **Secret 1:**
   - Name: `FTP_SERVER`
   - Value: Your FTP host (e.g., `ftp.yourdomain.com` or `123.45.67.89`)

   **Secret 2:**
   - Name: `FTP_USERNAME`
   - Value: Your FTP username

   **Secret 3:**
   - Name: `FTP_PASSWORD`
   - Value: Your FTP password

### Step 3: Verify Plugin Path on Hostinger

Make sure the plugin should be deployed to:
```
/public_html/wp-content/plugins/customer-portal/
```

If your WordPress is in a subdirectory or you use a different structure, update the `server-dir` in `.github/workflows/deploy.yml`

### Step 4: Test the Deployment

1. Commit and push any change to the `main` branch:
   ```bash
   git add .
   git commit -m "Test deployment"
   git push origin main
   ```

2. Go to your GitHub repository → **Actions** tab
3. You should see the workflow running
4. Once completed (green checkmark), check your WordPress site

### Step 5: Activate the Plugin

1. Log in to your WordPress admin panel
2. Go to **Plugins**
3. Find "Customer Portal with Telegram Auth"
4. Click **Activate**

## How It Works

- Every time you push to the `main` branch, GitHub Actions automatically:
  1. Checks out your code
  2. Connects to your Hostinger FTP
  3. Uploads only changed files to `/wp-content/plugins/customer-portal/`
  4. Excludes unnecessary files (.git, README, etc.)

## Troubleshooting

### Deployment fails with "Login authentication failed"
- Double-check your FTP credentials in GitHub Secrets
- Make sure you're using the correct FTP host (try with and without `ftp://` prefix)
- Try using IP address instead of domain name

### Deployment succeeds but changes don't appear
- Clear WordPress cache (if using a caching plugin)
- Clear browser cache (Ctrl+Shift+R)
- Check if the correct path is being used in the workflow file

### Permission denied errors
- Contact Hostinger support to ensure your FTP user has write permissions
- Check if the `/public_html/wp-content/plugins/` directory exists and is writable

### Using SFTP instead of FTP
If your Hostinger account uses SFTP (port 22), update the workflow:
```yaml
protocol: sftp
port: 22
```

## Alternative Methods (if FTP doesn't work)

### Manual Git Deployment via SSH (if available)
Some Hostinger plans allow SSH access. Check if you have SSH enabled in hPanel.

### File Manager Upload
As a last resort, you can manually upload files through Hostinger's File Manager in hPanel.

## Support

For issues:
- GitHub Actions logs: Check the **Actions** tab in your repository
- Hostinger support: https://www.hostinger.com/cpanel-login
- Repository issues: https://github.com/deffins/customer-portal/issues
