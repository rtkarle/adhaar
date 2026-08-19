# 🚀 Adhaar – Render Deployment Guide

## Architecture

```
GitHub Repo
    ├── Dockerfile              → PHP App (Service 1)
    ├── ai_python/Dockerfile    → Flask AI (Service 2)
    └── render.yaml             → Blueprint (deploys both)

Render Services:
    adhaar-php  → https://adhaar-php.onrender.com   (PHP + Apache)
    adhaar-ai   → https://adhaar-ai.onrender.com    (Python Flask)
```

---

## Step 1 — MySQL Database

Use **Aiven** (free tier) or **PlanetScale** or **Railway MySQL**:

### Option A — Aiven (Recommended, free 30 days → $19/month after)
1. Go to https://aiven.io → Create Free MySQL service
2. Download SSL cert
3. Note: `host`, `port`, `user`, `password`, `database`

### Option B — Railway MySQL (Free $5 credit/month)
1. https://railway.app → New Project → Add MySQL
2. Click MySQL → Variables tab → copy credentials

### Import Database
```bash
mysql -h HOST -u USER -p DATABASE < database/adhaar_full_schema.sql
mysql -h HOST -u USER -p DATABASE < database/fix_missing_columns.sql
```

---

## Step 2 — Push to GitHub

```bash
# From c:\xampp\htdocs\adhaar\
git init
git add .
git commit -m "Initial commit — Adhaar SoulServe"
git branch -M main
git remote add origin https://github.com/rtkarle/adhaar.git
git push -u origin main
```

> ⚠️ Make sure `.gitignore` excludes `vendor/`, `uploads/`, `.env`

---

## Step 3 — Deploy on Render

### Option A — Blueprint (Automatic — deploys BOTH services)
1. Go to https://render.com → Sign up with GitHub
2. Click **New** → **Blueprint**
3. Connect your GitHub repo
4. Render reads `render.yaml` → creates both services automatically
5. Set environment variables (see Step 4)

### Option B — Manual (Two separate services)

**Service 1 — PHP App:**
1. New → Web Service → Connect repo
2. Runtime: **Docker**
3. Dockerfile Path: `./Dockerfile`
4. Name: `adhaar-php`

**Service 2 — Flask AI:**
1. New → Web Service → Connect repo
2. Runtime: **Docker**
3. Dockerfile Path: `./ai_python/Dockerfile`
4. Name: `adhaar-ai`

---

## Step 4 — Environment Variables

### adhaar-php service — set these in Render Dashboard:

| Key | Value |
|-----|-------|
| `APP_URL` | `https://adhaar-php.onrender.com` |
| `DB_HOST` | Your MySQL host |
| `DB_USER` | Your MySQL user |
| `DB_PASS` | Your MySQL password |
| `DB_NAME` | `adhaar_db` |
| `MAIL_USERNAME` | Your SMTP username |
| `MAIL_PASSWORD` | Your SMTP app password |
| `CLOUDINARY_API_KEY` | Your Cloudinary API key |
| `CLOUDINARY_API_SECRET` | Your Cloudinary API secret |
| `ADMIN_REGISTRATION_KEY` | A newly generated admin-registration key |
| `DB_IMPORT_KEY` | A temporary database-import key; remove after import |
| `AI_FLASK_URL` | `https://adhaar-ai.onrender.com` |

### adhaar-ai service — set these:

| Key | Value |
|-----|-------|
| `PORT` | `5000` |
| `GEMINI_API_KEY` | (optional) Your Gemini API key |

---

## Step 5 — Train AI Models (First Time Only)

The `.joblib` model files need to be included in the repo OR trained at build time.

**Option A — Include models in repo (recommended for demo):**
```bash
# Models are already in ai_python/models/ — just commit them
git add ai_python/models/
git commit -m "Add trained AI models"
git push
```

**Option B — Train on first deploy (add to Dockerfile):**
Add this line to `ai_python/Dockerfile` before CMD:
```dockerfile
RUN python train_all.py
```

---

## Step 6 — Verify Deployment

```
# Check PHP app
https://adhaar-php.onrender.com

# Check Flask AI health
https://adhaar-ai.onrender.com/api/v1/health

# Expected response:
{
  "status": "healthy",
  "models": { ... all true ... },
  "message": "✅ All AI models loaded"
}
```

---

## Free Tier Limitations on Render

| Limitation | Details |
|------------|---------|
| Sleep after 15 min | Free services sleep when inactive. First request ~30s |
| 512 MB RAM | Enough for PHP + ML inference |
| 0.1 CPU | Shared, enough for demo |
| No persistent disk | Uploads go to Cloudinary ✅ (already set up) |

> 💡 **Tip:** For presentation, open both URLs 5 minutes before to wake up the services.

---

## Upgrade Path (Production)

| Service | Plan | Cost |
|---------|------|------|
| adhaar-php | Starter | $7/month |
| adhaar-ai | Starter | $7/month |
| MySQL (Aiven) | Business | $19/month |
| **Total** | | **~₹2,800/month** |

Or use **Railway** for ~$10/month total (PHP + Python + MySQL in one platform).

---

## Local Development (XAMPP)

No changes needed — `config/config.php` auto-falls back to localhost values when env vars are not set.

```
DB_HOST    → localhost
APP_URL    → http://localhost/adhaar
AI_FLASK   → http://localhost:5000
```
