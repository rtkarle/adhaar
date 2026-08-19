"""
Adhaar AI — Dataset Generator
Generates 1000+ synthetic records for all 4 modules.
Run: python generate_datasets.py
"""

import pandas as pd
import numpy as np
import random
import json
import os

random.seed(42)
np.random.seed(42)

# ── Config ────────────────────────────────────────────────────
CATEGORIES  = ['food', 'clothes', 'medicine', 'books', 'toys', 'utensils']
URGENCY     = ['low', 'medium', 'high', 'critical']
CITY_ZONES  = ['pune_urban', 'pune_rural', 'nashik', 'ahmednagar', 'aurangabad', 'mumbai', 'nagpur', 'kolhapur']
CONDITIONS  = ['new', 'good', 'fair', 'worn']
SKILLS      = ['driving', 'first_aid', 'coordination', 'local_knowledge', 'logistics']
N           = 1200   # records per dataset

# ── MODULE 1: Donation Matching Dataset ───────────────────────
def gen_donation_matching():
    rows = []
    for i in range(N):
        cat           = random.choice(CATEGORIES)
        donor_zone    = random.choice(CITY_ZONES)
        ben_zone      = random.choice(CITY_ZONES)
        qty           = random.randint(1, 100)
        urgency_score = ['low','medium','high','critical'].index(random.choice(URGENCY))
        same_zone     = int(donor_zone == ben_zone)
        zone_dist     = abs(CITY_ZONES.index(donor_zone) - CITY_ZONES.index(ben_zone))
        ngo_need_cat  = random.choice(CATEGORIES)
        ngo_capacity  = random.randint(10, 500)
        past_don_cnt  = random.randint(0, 50)
        match_score   = (
            (5 if cat == ngo_need_cat else 0) +
            same_zone * 3 +
            max(0, 3 - zone_dist) +
            (urgency_score * 2) +
            min(4, past_don_cnt // 10) +
            (2 if ngo_capacity >= qty else -1)
        )
        # Match label: 0=no_match, 1=partial, 2=good, 3=excellent
        match_label = min(3, max(0, match_score // 4))
        rows.append({
            'donation_category'   : cat,
            'donation_category_enc': CATEGORIES.index(cat),
            'quantity'            : qty,
            'urgency'             : random.choice(URGENCY),
            'urgency_enc'         : urgency_score,
            'donor_zone'          : donor_zone,
            'donor_zone_enc'      : CITY_ZONES.index(donor_zone),
            'beneficiary_zone'    : ben_zone,
            'beneficiary_zone_enc': CITY_ZONES.index(ben_zone),
            'zone_distance'       : zone_dist,
            'same_zone'           : same_zone,
            'ngo_required_category': ngo_need_cat,
            'ngo_req_cat_enc'     : CATEGORIES.index(ngo_need_cat),
            'ngo_capacity'        : ngo_capacity,
            'capacity_sufficient' : int(ngo_capacity >= qty),
            'donor_past_donations': past_don_cnt,
            'match_label'         : match_label,          # target
        })
    df = pd.DataFrame(rows)
    path = os.path.join(os.path.dirname(__file__), 'donation_matching.csv')
    df.to_csv(path, index=False)
    print(f"[OK] donation_matching.csv — {len(df)} rows")
    return df


# ── MODULE 2: Volunteer Recommendation Dataset ────────────────
def gen_volunteer_recommendation():
    rows = []
    for i in range(N):
        vol_zone       = random.choice(CITY_ZONES)
        don_zone       = random.choice(CITY_ZONES)
        zone_dist      = abs(CITY_ZONES.index(vol_zone) - CITY_ZONES.index(don_zone))
        available      = random.choice([0, 1, 1, 1])   # 75% available
        tasks_done     = random.randint(0, 80)
        active_tasks   = random.randint(0, 5)
        skill_match    = random.randint(0, 3)           # 0-3 matching skills
        response_rate  = round(random.uniform(0.5, 1.0), 2)
        days_since     = random.randint(0, 60)

        score = (
            available * 30 +
            max(0, 20 - zone_dist * 5) +        # distance penalty
            min(25, tasks_done // 3) +
            max(0, 15 - active_tasks * 4) +
            skill_match * 4 +
            int(response_rate * 10) -
            min(10, days_since // 6)
        )
        # Label: 0=poor, 1=fair, 2=good, 3=best
        label = min(3, max(0, score // 20))
        rows.append({
            'volunteer_zone'    : vol_zone,
            'volunteer_zone_enc': CITY_ZONES.index(vol_zone),
            'donation_zone'     : don_zone,
            'donation_zone_enc' : CITY_ZONES.index(don_zone),
            'zone_distance'     : zone_dist,
            'is_available'      : available,
            'tasks_completed'   : tasks_done,
            'active_tasks'      : active_tasks,
            'skill_match_count' : skill_match,
            'response_rate'     : response_rate,
            'days_since_last'   : days_since,
            'suitability_label' : label,    # target
        })
    df = pd.DataFrame(rows)
    path = os.path.join(os.path.dirname(__file__), 'volunteer_recommendation.csv')
    df.to_csv(path, index=False)
    print(f"[OK] volunteer_recommendation.csv — {len(df)} rows")
    return df


# ── MODULE 3: Product Recommendation Dataset ──────────────────
def gen_product_recommendation():
    PROD_CATS = ['handicraft','textile','food_product','jewelry','art','pottery','organic','other']
    rows = []
    for i in range(N):
        searched_cat    = random.choice(PROD_CATS)
        bought_cat      = random.choice(PROD_CATS)
        viewed_cat      = random.choice(PROD_CATS)
        prod_cat        = random.choice(PROD_CATS)
        rating          = round(random.uniform(1.0, 5.0), 1)
        price           = random.randint(50, 5000)
        total_sold      = random.randint(0, 500)
        has_discount    = random.choice([0, 1])
        is_new_listing  = random.choice([0, 0, 1])
        cat_match_search= int(prod_cat == searched_cat)
        cat_match_buy   = int(prod_cat == bought_cat)
        cat_match_view  = int(prod_cat == viewed_cat)

        score = (
            cat_match_search * 30 +
            cat_match_buy * 50 +
            cat_match_view * 20 +
            int(rating * 8) +
            min(30, int(np.log1p(total_sold) * 6)) +
            has_discount * 10 +
            is_new_listing * 8
        )
        # Label: 0=not relevant, 1=somewhat, 2=relevant, 3=highly relevant
        label = min(3, score // 40)
        rows.append({
            'searched_category_enc' : PROD_CATS.index(searched_cat),
            'bought_category_enc'   : PROD_CATS.index(bought_cat),
            'viewed_category_enc'   : PROD_CATS.index(viewed_cat),
            'product_category_enc'  : PROD_CATS.index(prod_cat),
            'product_rating'        : rating,
            'product_price'         : price,
            'total_sold'            : total_sold,
            'has_discount'          : has_discount,
            'is_new_listing'        : is_new_listing,
            'cat_match_search'      : cat_match_search,
            'cat_match_buy'         : cat_match_buy,
            'cat_match_view'        : cat_match_view,
            'relevance_label'       : label,   # target
        })
    df = pd.DataFrame(rows)
    path = os.path.join(os.path.dirname(__file__), 'product_recommendation.csv')
    df.to_csv(path, index=False)
    print(f"[OK] product_recommendation.csv — {len(df)} rows")
    return df


# ── MODULE 4: Analytics / Trend Prediction Dataset ────────────
def gen_analytics_prediction():
    """Weekly donation trend data for regression"""
    rows = []
    base_food  = 8
    base_cloth = 4
    for week in range(N):
        month       = (week // 4) % 12 + 1
        year        = 2024 + week // 52
        season_food = 1.3 if month in [6,7,8] else (1.1 if month in [11,12,1] else 1.0)
        season_clt  = 1.4 if month in [10,11,12,1] else 1.0
        trend_food  = base_food + week * 0.04 + np.random.normal(0, 1.5)
        trend_cloth = base_cloth + week * 0.02 + np.random.normal(0, 0.8)
        food_cnt    = max(0, int(trend_food  * season_food))
        cloth_cnt   = max(0, int(trend_cloth * season_clt))
        sales       = food_cnt * random.randint(80, 150) + cloth_cnt * random.randint(200, 400)
        rows.append({
            'week_number'   : week,
            'month'         : month,
            'year'          : year,
            'season_index'  : round(season_food, 2),
            'food_donations': food_cnt,
            'cloth_donations': cloth_cnt,
            'total_donations': food_cnt + cloth_cnt,
            'marketplace_sales_inr': int(sales),
            'next_week_food'   : max(0, int(trend_food * season_food + np.random.normal(0,1.5))),   # target 1
            'next_week_total'  : max(0, food_cnt + cloth_cnt + int(np.random.normal(1,2))),          # target 2
        })
    df = pd.DataFrame(rows)
    path = os.path.join(os.path.dirname(__file__), 'analytics_prediction.csv')
    df.to_csv(path, index=False)
    print(f"[OK] analytics_prediction.csv — {len(df)} rows")
    return df


if __name__ == '__main__':
    gen_donation_matching()
    gen_volunteer_recommendation()
    gen_product_recommendation()
    gen_analytics_prediction()
    print("\n✅ All 4 datasets generated successfully.")
