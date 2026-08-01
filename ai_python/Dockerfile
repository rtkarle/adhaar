# ═══════════════════════════════════════════════════════════════
#  Adhaar – Flask AI API Dockerfile
#  Python 3.11 + Gunicorn production server
#  Serves: Donation Matching, Volunteer Rec, Product Rec,
#          Analytics Prediction, Gemini Chat
# ═══════════════════════════════════════════════════════════════

FROM python:3.11-slim

# ── Set working directory ─────────────────────────────────────
WORKDIR /app

# ── Install system deps ───────────────────────────────────────
RUN apt-get update && apt-get install -y \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# ── Copy requirements first (Docker layer cache) ──────────────
COPY requirements.txt .

# ── Install Python packages ───────────────────────────────────
RUN pip install --no-cache-dir -r requirements.txt

# ── Copy all AI files ─────────────────────────────────────────
COPY . .

# ── Ensure models directory exists ───────────────────────────
RUN mkdir -p /app/models /app/datasets

# ── Expose Flask port ─────────────────────────────────────────
EXPOSE 5000

# ── Health check ─────────────────────────────────────────────
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:5000/api/v1/health || exit 1

# ── Start Gunicorn (production WSGI server) ───────────────────
# 2 workers, timeout 120s for ML inference
CMD ["gunicorn", \
     "--bind", "0.0.0.0:5000", \
     "--workers", "2", \
     "--timeout", "120", \
     "--access-logfile", "-", \
     "--error-logfile", "-", \
     "flask_api:app"]
