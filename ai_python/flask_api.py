"""
Adhaar AI — Flask API Server
Serves all 4 AI modules + Gemini LLM assistant.

Endpoints:
  POST /api/v1/donation_match      → Module 1: Donation Matching
  POST /api/v1/volunteer_recommend → Module 2: Volunteer Recommendation
  POST /api/v1/product_recommend   → Module 3: Product Recommendation
  POST /api/v1/analytics_predict   → Module 4: Analytics / Trend Prediction
  POST /api/v1/ai_chat             → Gemini LLM Assistant
  GET  /api/v1/health              → Health check

Run:
  pip install flask pandas numpy scikit-learn joblib google-generativeai
  python flask_api.py

Then in PHP call: http://localhost:5000/api/v1/{endpoint}
"""

from flask import Flask, request, jsonify
import pandas as pd
import numpy as np
import joblib
import json
import os
import traceback
from functools import lru_cache
from datetime import datetime

app = Flask(__name__)
app.config['JSON_SORT_KEYS'] = False

BASE   = os.path.dirname(os.path.abspath(__file__))
MODELS = os.path.join(BASE, 'models')

# ── Encodings (must match train scripts) ─────────────────────
CATEGORIES  = ['food','clothes','medicine','books','toys','utensils']
CITY_ZONES  = ['pune_urban','pune_rural','nashik','ahmednagar','aurangabad','mumbai','nagpur','kolhapur']
PROD_CATS   = ['handicraft','textile','food_product','jewelry','art','pottery','organic','other']
URGENCY_MAP = {'low':0,'medium':1,'high':2,'critical':3}


# ── Model loader (cached) ─────────────────────────────────────
@lru_cache(maxsize=None)
def load_model(name):
    path = os.path.join(MODELS, name)
    if not os.path.exists(path):
        return None
    return joblib.load(path)


def models_ready():
    """Check which models are loaded"""
    needed = [
        'donation_matching_rf.joblib',
        'volunteer_recommendation_dt.joblib',
        'product_rec_knn.joblib',
        'product_rec_scaler.joblib',
        'donation_trend_lr.joblib',
        'sales_trend_rfr.joblib',
    ]
    return {n: os.path.exists(os.path.join(MODELS, n)) for n in needed}


def encode_zone(zone_str):
    """Map city/zone string to integer index"""
    z = zone_str.lower().replace(' ','_')
    return CITY_ZONES.index(z) if z in CITY_ZONES else 0


def encode_category(cat_str):
    c = cat_str.lower()
    return CATEGORIES.index(c) if c in CATEGORIES else 0


def encode_prod_cat(cat_str):
    c = cat_str.lower()
    return PROD_CATS.index(c) if c in PROD_CATS else 7  # 'other'


# ── Helpers ───────────────────────────────────────────────────
def success(data, status=200):
    return jsonify({'success': True, 'data': data, 'timestamp': datetime.utcnow().isoformat()}), status


def error(msg, status=400):
    return jsonify({'success': False, 'error': msg}), status


# ══════════════════════════════════════════════════════════════
# MODULE 1: Donation Matching
# ══════════════════════════════════════════════════════════════
@app.route('/api/v1/donation_match', methods=['POST'])
def donation_match():
    """
    Input JSON:
    {
      "donation_category": "food",
      "quantity": 50,
      "urgency": "high",
      "donor_zone": "pune_urban",
      "beneficiary_zone": "pune_rural",
      "ngo_required_category": "food",
      "ngo_capacity": 100,
      "donor_past_donations": 12
    }

    Returns ranked match quality + top NGO recommendation.
    """
    try:
        d = request.get_json(force=True)
        model = load_model('donation_matching_rf.joblib')
        if model is None:
            return error("Model not trained. Run module1_donation_matching/train_model.py")

        don_cat   = encode_category(d.get('donation_category','food'))
        urg       = URGENCY_MAP.get(d.get('urgency','medium'), 1)
        d_zone    = encode_zone(d.get('donor_zone',''))
        b_zone    = encode_zone(d.get('beneficiary_zone',''))
        ngo_cat   = encode_category(d.get('ngo_required_category','food'))
        qty       = int(d.get('quantity', 10))
        ngo_cap   = int(d.get('ngo_capacity', 100))
        past_don  = int(d.get('donor_past_donations', 0))

        zone_dist = abs(d_zone - b_zone)
        same_zone = int(d_zone == b_zone)
        cap_suf   = int(ngo_cap >= qty)

        X = pd.DataFrame([{
            'donation_category_enc'   : don_cat,
            'quantity'                : qty,
            'urgency_enc'             : urg,
            'donor_zone_enc'          : d_zone,
            'beneficiary_zone_enc'    : b_zone,
            'zone_distance'           : zone_dist,
            'same_zone'               : same_zone,
            'ngo_req_cat_enc'         : ngo_cat,
            'ngo_capacity'            : ngo_cap,
            'capacity_sufficient'     : cap_suf,
            'donor_past_donations'    : past_don,
        }])

        label  = int(model.predict(X)[0])
        proba  = model.predict_proba(X)[0].tolist()
        labels = ['no_match','partial','good','excellent']

        return success({
            'match_label'    : label,
            'match_quality'  : labels[label],
            'confidence'     : round(max(proba) * 100, 1),
            'probabilities'  : {labels[i]: round(p*100,1) for i,p in enumerate(proba)},
            'recommendation' : _donation_recommendation(label, d),
            'ai_score'       : round(max(proba) * 100, 1),
        })
    except Exception as e:
        return error(f"Prediction failed: {str(e)}")


def _donation_recommendation(label, d):
    recs = {
        0: "❌ Poor match. Consider different beneficiary zone or donation category.",
        1: "⚠️ Partial match. May proceed if no better NGO available.",
        2: "✅ Good match. Recommended for this donation.",
        3: "🌟 Excellent match! Highest priority routing recommended.",
    }
    urgency = d.get('urgency','medium')
    urgent_note = " ⚡ URGENT — immediate action required!" if urgency in ['high','critical'] else ""
    return recs.get(label, "Undetermined") + urgent_note


# ══════════════════════════════════════════════════════════════
# MODULE 2: Volunteer Recommendation
# ══════════════════════════════════════════════════════════════
@app.route('/api/v1/volunteer_recommend', methods=['POST'])
def volunteer_recommend():
    """
    Input JSON: list of volunteer candidates + donation location
    {
      "donation_zone": "pune_urban",
      "volunteers": [
        {
          "email": "vol1@example.com",
          "name": "Rahul Patil",
          "zone": "pune_urban",
          "is_available": 1,
          "tasks_completed": 15,
          "active_tasks": 1,
          "skill_match_count": 2,
          "response_rate": 0.85,
          "days_since_last": 3
        }
      ]
    }
    """
    try:
        d        = request.get_json(force=True)
        model    = load_model('volunteer_recommendation_dt.joblib')
        if model is None:
            return error("Model not trained. Run module2_volunteer_recommendation/train_model.py")

        don_zone = encode_zone(d.get('donation_zone',''))
        vols     = d.get('volunteers', [])
        if not vols:
            return error("No volunteers provided")

        results = []
        for v in vols:
            vol_zone   = encode_zone(v.get('zone',''))
            zone_dist  = abs(don_zone - vol_zone)
            log_tasks  = float(np.log1p(v.get('tasks_completed', 0)))
            workload   = min(5, int(v.get('active_tasks', 0)))

            X = pd.DataFrame([{
                'volunteer_zone_enc': vol_zone,
                'donation_zone_enc' : don_zone,
                'zone_distance'     : zone_dist,
                'is_available'      : int(v.get('is_available', 1)),
                'log_tasks'         : log_tasks,
                'workload_penalty'  : workload,
                'skill_match_count' : int(v.get('skill_match_count', 0)),
                'response_rate'     : float(v.get('response_rate', 0.8)),
                'days_since_last'   : int(v.get('days_since_last', 7)),
            }])

            label = int(model.predict(X)[0])
            proba = model.predict_proba(X)[0]
            labels = ['poor','fair','good','best']
            results.append({
                'email'          : v.get('email',''),
                'name'           : v.get('name',''),
                'suitability'    : labels[label],
                'suitability_score': label,
                'confidence'     : round(float(max(proba)) * 100, 1),
                'distance_zones' : zone_dist,
                'is_available'   : bool(v.get('is_available', True)),
            })

        # Sort best first
        results.sort(key=lambda x: (-x['suitability_score'], -x['confidence']))
        top = results[0] if results else None

        return success({
            'top_volunteer'       : top,
            'all_scored'          : results,
            'recommendation'      : f"🤖 AI recommends {top['name']} (suitability: {top['suitability']}, confidence: {top['confidence']}%)" if top else "No suitable volunteer found",
        })
    except Exception as e:
        return error(f"Recommendation failed: {str(e)}\n{traceback.format_exc()}")


# ══════════════════════════════════════════════════════════════
# MODULE 3: Product Recommendation
# ══════════════════════════════════════════════════════════════
@app.route('/api/v1/product_recommend', methods=['POST'])
def product_recommend():
    """
    Input JSON:
    {
      "user_searched_category": "textile",
      "user_bought_category": "handicraft",
      "user_viewed_category": "jewelry",
      "products": [
        {
          "id": 1,
          "name": "Hand-woven Saree",
          "category": "textile",
          "rating": 4.5,
          "price": 850,
          "total_sold": 32,
          "has_discount": 1,
          "is_new_listing": 0
        }
      ]
    }
    Returns each product scored by relevance.
    """
    try:
        d     = request.get_json(force=True)
        knn   = load_model('product_rec_knn.joblib')
        scaler= load_model('product_rec_scaler.joblib')
        if knn is None:
            return error("Model not trained. Run module3_product_recommendation/train_model.py")

        s_cat = encode_prod_cat(d.get('user_searched_category','other'))
        b_cat = encode_prod_cat(d.get('user_bought_category','other'))
        v_cat = encode_prod_cat(d.get('user_viewed_category','other'))
        prods = d.get('products', [])
        if not prods:
            return error("No products provided")

        labels = ['not_relevant','somewhat_relevant','relevant','highly_relevant']
        scored = []
        for p in prods:
            p_cat = encode_prod_cat(p.get('category','other'))
            X = pd.DataFrame([{
                'searched_category_enc': s_cat,
                'bought_category_enc'  : b_cat,
                'viewed_category_enc'  : v_cat,
                'product_category_enc' : p_cat,
                'product_rating'       : float(p.get('rating', 3.0)),
                'rating_sq'            : float(p.get('rating', 3.0)) ** 2,
                'log_price'            : float(np.log1p(p.get('price', 100))),
                'log_sold'             : float(np.log1p(p.get('total_sold', 0))),
                'has_discount'         : int(p.get('has_discount', 0)),
                'is_new_listing'       : int(p.get('is_new_listing', 0)),
                'cat_match_search'     : int(p_cat == s_cat),
                'cat_match_buy'        : int(p_cat == b_cat),
                'cat_match_view'       : int(p_cat == v_cat),
            }])
            X_sc = scaler.transform(X)
            label_idx = int(knn.predict(X_sc)[0])
            proba     = knn.predict_proba(X_sc)[0]
            scored.append({
                'product_id'   : p.get('id'),
                'product_name' : p.get('name',''),
                'category'     : p.get('category',''),
                'relevance'    : labels[label_idx],
                'relevance_score': label_idx,
                'confidence'   : round(float(max(proba)) * 100, 1),
                'recommended'  : label_idx >= 2,
            })

        scored.sort(key=lambda x: (-x['relevance_score'], -x['confidence']))
        top5 = [p for p in scored if p['recommended']][:5]

        return success({
            'top_recommendations': top5,
            'all_scored'         : scored,
            'algorithm'          : 'KNN (K-Nearest Neighbors) + StandardScaler',
        })
    except Exception as e:
        return error(f"Recommendation failed: {str(e)}")


# ══════════════════════════════════════════════════════════════
# MODULE 4: Analytics Prediction
# ══════════════════════════════════════════════════════════════
@app.route('/api/v1/analytics_predict', methods=['POST'])
def analytics_predict():
    """
    Input JSON:
    {
      "week_number": 52,
      "month": 8,
      "season_index": 1.3,
      "prev_food": 12,
      "prev_cloth": 6,
      "prev_total": 18,
      "rolling_avg": 16,
      "food_donations": 14,
      "cloth_donations": 7,
      "total_donations": 21
    }
    Returns next-week donation forecast + marketplace sales prediction.
    """
    try:
        d   = request.get_json(force=True)
        pkg = load_model('donation_trend_lr.joblib')
        rfr = load_model('sales_trend_rfr.joblib')
        if pkg is None:
            return error("Model not trained. Run module4_analytics_prediction/train_model.py")

        lr     = pkg['model']
        scaler = pkg['scaler']
        feat   = pkg['features']

        month    = int(d.get('month', 8))
        sin_m    = float(np.sin(2 * np.pi * month / 12))
        cos_m    = float(np.cos(2 * np.pi * month / 12))

        row = {
            'week_number' : int(d.get('week_number', 52)),
            'month'       : month,
            'season_index': float(d.get('season_index', 1.0)),
            'prev_food'   : float(d.get('prev_food', 10)),
            'prev_cloth'  : float(d.get('prev_cloth', 5)),
            'prev_total'  : float(d.get('prev_total', 15)),
            'rolling_avg' : float(d.get('rolling_avg', 14)),
            'sin_month'   : sin_m,
            'cos_month'   : cos_m,
        }
        X_trend  = scaler.transform(pd.DataFrame([row])[feat])
        next_don = max(0, int(lr.predict(X_trend)[0]))

        # Sales prediction
        sales_row = dict(row)
        sales_row.update({
            'food_donations'  : int(d.get('food_donations', 10)),
            'cloth_donations' : int(d.get('cloth_donations', 5)),
            'total_donations' : int(d.get('total_donations', 15)),
        })
        rfr_feats = [
            'week_number','month','season_index','prev_food','prev_cloth',
            'prev_total','rolling_avg','sin_month','cos_month',
            'food_donations','cloth_donations','total_donations'
        ]
        X_sales = pd.DataFrame([sales_row])[rfr_feats]
        next_sales = max(0, int(rfr.predict(X_sales)[0]))

        trend_pct = round((next_don / max(1, row['prev_total']) - 1) * 100, 1)
        trend_dir = '↑ Increasing' if trend_pct > 5 else ('↓ Decreasing' if trend_pct < -5 else '→ Stable')

        return success({
            'next_week_donation_forecast': next_don,
            'next_week_sales_forecast_inr': next_sales,
            'trend_direction'            : trend_dir,
            'trend_pct_change'           : trend_pct,
            'monthly_estimate'           : next_don * 4,
            'insight'                    : f"AI predicts {next_don} donations next week ({trend_dir}). Expected ₹{next_sales:,} in sales.",
        })
    except Exception as e:
        return error(f"Prediction failed: {str(e)}")


# ══════════════════════════════════════════════════════════════
# Gemini LLM Assistant
# ══════════════════════════════════════════════════════════════
@app.route('/api/v1/ai_chat', methods=['POST'])
def ai_chat():
    """
    Gemini AI Assistant for Adhaar platform.
    Supports English, Hindi, Marathi queries.
    Input: { "message": "...", "context": "donor|volunteer|admin", "language": "en" }
    """
    try:
        import google.generativeai as genai

        GEMINI_API_KEY = os.environ.get('GEMINI_API_KEY', '')
        if not GEMINI_API_KEY:
            # Fallback to rule-based response if no key
            return _rule_based_chat(request.get_json(force=True))

        genai.configure(api_key=GEMINI_API_KEY)
        d       = request.get_json(force=True)
        message = d.get('message', '')
        context = d.get('context', 'donor')
        lang    = d.get('language', 'en')

        lang_instruction = {
            'en': 'Respond in English.',
            'hi': 'हिंदी में जवाब दें।',
            'mr': 'मराठीत उत्तर द्या.',
        }.get(lang, 'Respond in English.')

        system_prompt = f"""You are the AI assistant for Adhaar – The SoulServe, an AI-powered donation and rural marketplace platform in Maharashtra, India.

Platform modules:
1. Donors can donate food and clothing, track status, buy from shop.
2. Volunteers pick up and deliver donations.
3. Sellers (rural artisans, women entrepreneurs) sell handmade products.
4. Admin manages the platform with AI insights.

User context: {context}
{lang_instruction}

Be concise, helpful, compassionate. Always encourage donations and volunteering.
If user asks about NGO recommendations, donation status, or product discovery — assist based on platform data."""

        model = genai.GenerativeModel('gemini-1.5-flash')
        chat  = model.start_chat(history=[])
        resp  = chat.send_message(f"{system_prompt}\n\nUser: {message}")
        reply = resp.text.strip()

        return success({'reply': reply, 'model': 'gemini-1.5-flash', 'language': lang})

    except ImportError:
        return _rule_based_chat(request.get_json(force=True))
    except Exception as e:
        return _rule_based_chat(request.get_json(force=True))


def _rule_based_chat(d):
    """Fallback chat without Gemini API"""
    msg = d.get('message','').lower()
    if any(w in msg for w in ['donate','donation','food','cloth']):
        reply = "To donate, go to your Donor Dashboard and click 'Donate Now'. Choose Food 🍱 or Clothing 👕, fill the form and submit. A volunteer will pick it up!"
    elif any(w in msg for w in ['volunteer','pickup','deliver']):
        reply = "To volunteer, register as a Volunteer. Accept tasks from your dashboard. Our AI assigns the nearest task to you automatically."
    elif any(w in msg for w in ['sell','shop','product','artisan']):
        reply = "Sellers can set up a store in the Seller Dashboard. Add products, set prices, and start earning. Admin verifies and activates your store."
    elif any(w in msg for w in ['impact','stats','how many']):
        reply = "Check our Live Impact page at /pages/impact.php for real-time donation stats powered by our AI analytics engine."
    else:
        reply = "I'm Adhaar AI Assistant. I can help with donations, volunteering, selling products, or platform questions. What can I help you with today?"
    return success({'reply': reply, 'model': 'rule-based', 'language': d.get('language','en')})


# ── Health check ──────────────────────────────────────────────
@app.route('/api/v1/health', methods=['GET'])
def health():
    status = models_ready()
    all_ok = all(status.values())
    return jsonify({
        'status'     : 'healthy' if all_ok else 'partial',
        'models'     : status,
        'message'    : '✅ All AI models loaded' if all_ok else '⚠ Some models not trained yet. Run train_all.py',
        'timestamp'  : datetime.utcnow().isoformat(),
    }), 200 if all_ok else 206


if __name__ == '__main__':
    print("=" * 55)
    print("  Adhaar AI Flask Server — Starting")
    print("=" * 55)
    print(f"  Models directory: {MODELS}")
    mstatus = models_ready()
    for name, ok in mstatus.items():
        print(f"  {'✅' if ok else '❌'} {name}")
    print("=" * 55)
    print("  Running on http://localhost:5000")
    print("  API docs: http://localhost:5000/api/v1/health")
    print("=" * 55)
    app.run(host='0.0.0.0', port=5000, debug=False)
