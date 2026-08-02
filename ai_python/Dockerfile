# ═══════════════════════════════════════════════════════════════
#  Adhaar – Flask AI API Dockerfile
#  Build context: repo ROOT (not ai_python/)
#  Dockerfile path: ./ai_python/Dockerfile
#  Python 3.11 + Gunicorn production server
# ═══════════════════════════════════════════════════════════════

FROM python:3.11-slim

WORKDIR /app

# ── System deps ───────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    build-essential \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ── Copy requirements from ai_python/ subfolder ───────────────
# Build context is repo ROOT so path is ai_python/requirements.txt
COPY ai_python/requirements.txt .

# ── Install Python packages ───────────────────────────────────
RUN pip install --no-cache-dir -r requirements.txt

# ── Copy all AI files from ai_python/ subfolder ───────────────
COPY ai_python/ .

# ── Ensure dirs exist ─────────────────────────────────────────
RUN mkdir -p /app/models /app/datasets

# ── Expose port ───────────────────────────────────────────────
EXPOSE 5000

# ── Health check ──────────────────────────────────────────────
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:5000/api/v1/health || exit 1

# ── Start Gunicorn ────────────────────────────────────────────
CMD ["gunicorn", \
     "--bind", "0.0.0.0:5000", \
     "--workers", "2", \
     "--timeout", "120", \
     "--access-logfile", "-", \
     "--error-logfile", "-", \
     "flask_api:app"]
