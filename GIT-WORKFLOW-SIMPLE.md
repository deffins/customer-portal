# Simple Git Workflow for Testing

## Your Setup

You have 2 branches that deploy to 2 different domains:
- **main** branch → deploys to **deffo.pro** (live site)
- **test** branch → deploys to **fons.lv** (test site)

## Daily Workflow

### Starting Work on New Feature

```bash
# 1. Switch to test branch
git checkout test

# 2. Make your changes in your editor
# Edit files...

# 3. Save and push your work
git add .
git commit -m "describe what you changed"
git push

# GitHub auto-deploys to fons.lv
# Test on http://fons.lv
```

### When Feature Works & You're Happy

```bash
# 1. Switch to main branch
git checkout main

# 2. Bring changes from test to main
git merge test

# 3. Push to GitHub
git push

# GitHub auto-deploys to deffo.pro (live site)
```

### If Something Breaks on Test Site

```bash
# Don't worry! Live site (deffo.pro) is still fine
# Just fix the code on test branch and push again

git add .
git commit -m "Fixed the issue"
git push

# Test again on fons.lv
# Live site was never affected!
```

## Quick Commands Reference

| What you want | Command |
|---------------|---------|
| See which branch you're on | `git branch` |
| Switch to test branch | `git checkout test` |
| Switch to main branch | `git checkout main` |
| Save changes | `git add .` then `git commit -m "message"` |
| Bring test changes to main | (on main branch) `git merge test` |
| See what changed | `git status` |
| See change history | `git log --oneline` |

## Example: Adding New Feature

```bash
# Start working on test branch
git checkout test

# Edit your code in your editor
# Save changes

git add .
git commit -m "Added new appointment feature"
git push

# → Auto-deploys to fons.lv
# Test on http://fons.lv - looks good!

# Move to main when ready
git checkout main
git merge test
git push

# → Auto-deploys to deffo.pro
# Done! Live site now has your new feature
```

## Tips

- ✅ Always work in **test** branch for new features
- ✅ Only merge to **main** when everything works
- ✅ Commit often (save your progress)
- ✅ Write simple commit messages describing what you did
- ❌ Don't work directly in main (always use test first)

## Important URLs

- **Test site:** http://fons.lv (test branch deploys here)
- **Live site:** http://deffo.pro (main branch deploys here)

## Current Branch

You are currently on: **test** branch

Start making your changes! Push to test fons.lv, then merge to main for deffo.pro.
