"""
MODULE 4: Analytics / Trend Prediction
Algorithms:
  - Linear Regression → Donation trend prediction (simple, explainable)
  - Random Forest Regressor → Marketplace sales prediction (non-linear patterns)

Why these algorithms?
  - Linear Regression: weekly donation count is a fairly linear time series
    → Simple, fast, interpretable by non-technical stakeholders
  - RF Regressor: sales data has complex seasonal patterns and interactions
    → Captures non-linearity without overfitting like deep models

Features:
  - Week number (time index)
  - Month
  - Season index
  - Previous food count
  - Previous cloth count
  - Previous total donations

Targets:
  - next_week_food (Linear Regression)
  - next_week_total (Linear Regression + RF Regressor for sales)
"""

import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import (
    mean_absolute_error, mean_squared_error, r2_score
)
from sklearn.preprocessing import StandardScaler
import joblib
import json
import os
import sys

BASE  = os.path.dirname(os.path.abspath(__file__))
DATA  = os.path.join(BASE, '..', 'datasets', 'analytics_prediction.csv')
MODEL_LR  = os.path.join(BASE, '..', 'models', 'donation_trend_lr.joblib')
MODEL_RFR = os.path.join(BASE, '..', 'models', 'sales_trend_rfr.joblib')
META      = os.path.join(BASE, '..', 'models', 'analytics_prediction_meta.json')


def eval_reg(name, y_true, y_pred):
    mae  = mean_absolute_error(y_true, y_pred)
    rmse = np.sqrt(mean_squared_error(y_true, y_pred))
    r2   = r2_score(y_true, y_pred)
    print(f"  {name}: MAE={mae:.2f}  RMSE={rmse:.2f}  R²={r2:.4f}")
    return r2


def train():
    print("=" * 55)
    print("  MODULE 4: Analytics Prediction — Training")
    print("=" * 55)

    # ── 1. Load ───────────────────────────────────────────────
    df = pd.read_csv(DATA)
    print(f"\n[1] Dataset: {df.shape}")

    # ── 2. Feature engineering ────────────────────────────────
    # Lag features — use previous week's counts
    df = df.sort_values('week_number').reset_index(drop=True)
    df['prev_food']   = df['food_donations'].shift(1).bfill()
    df['prev_cloth']  = df['cloth_donations'].shift(1).bfill()
    df['prev_total']  = df['total_donations'].shift(1).bfill()
    df['rolling_avg'] = df['total_donations'].rolling(4, min_periods=1).mean()
    df['sin_month']   = np.sin(2 * np.pi * df['month'] / 12)  # cyclical month feature
    df['cos_month']   = np.cos(2 * np.pi * df['month'] / 12)

    FEATURES_TREND = [
        'week_number', 'month', 'season_index',
        'prev_food', 'prev_cloth', 'prev_total',
        'rolling_avg', 'sin_month', 'cos_month'
    ]
    FEATURES_SALES = FEATURES_TREND + ['food_donations', 'cloth_donations', 'total_donations']

    TARGET_TREND = 'next_week_total'
    TARGET_SALES = 'marketplace_sales_inr'

    # ── 3. Preprocessing ──────────────────────────────────────
    df = df.dropna(subset=FEATURES_SALES + [TARGET_TREND, TARGET_SALES])
    print(f"\n[2] Clean rows: {len(df)}")

    # ── 4. TASK A: Donation Trend (Linear Regression) ─────────
    X_tr  = df[FEATURES_TREND]
    y_tr  = df[TARGET_TREND]
    X_tr_train, X_tr_test, y_tr_train, y_tr_test = train_test_split(
        X_tr, y_tr, test_size=0.2, random_state=42
    )

    scaler_tr = StandardScaler()
    Xtr_sc_tr = scaler_tr.fit_transform(X_tr_train)
    Xte_sc_tr = scaler_tr.transform(X_tr_test)

    lr = LinearRegression()
    lr.fit(Xtr_sc_tr, y_tr_train)
    y_pred_lr = lr.predict(Xte_sc_tr)
    r2_lr = eval_reg("[3a] Linear Regression (donation trend)", y_tr_test, y_pred_lr)

    # ── 5. TASK B: Sales Prediction (Random Forest Regressor) ─
    X_sales     = df[FEATURES_SALES]
    y_sales     = df[TARGET_SALES]
    Xs_train, Xs_test, ys_train, ys_test = train_test_split(
        X_sales, y_sales, test_size=0.2, random_state=42
    )

    rfr = RandomForestRegressor(
        n_estimators = 200,
        max_depth    = 12,
        min_samples_split = 4,
        random_state = 42,
        n_jobs       = -1
    )
    rfr.fit(Xs_train, ys_train)
    y_pred_rfr = rfr.predict(Xs_test)
    r2_rfr = eval_reg("[3b] Random Forest Regressor (sales)", ys_test, y_pred_rfr)

    # ── 6. Key insights ───────────────────────────────────────
    fi_sales = dict(zip(FEATURES_SALES, rfr.feature_importances_.round(4).tolist()))
    print(f"\n[4] Top sales predictors: {sorted(fi_sales.items(), key=lambda x:-x[1])[:5]}")

    # Donation trend coefficients
    coeff = dict(zip(FEATURES_TREND, lr.coef_.round(4).tolist()))
    print(f"    LR coefficients: {coeff}")

    # ── 7. Save models + scalers ──────────────────────────────
    joblib.dump({'model': lr, 'scaler': scaler_tr, 'features': FEATURES_TREND}, MODEL_LR, compress=3)
    joblib.dump(rfr, MODEL_RFR, compress=3)

    meta = {
        'donation_trend': {
            'model'    : 'LinearRegression',
            'r2_score' : round(r2_lr, 4),
            'features' : FEATURES_TREND,
            'target'   : TARGET_TREND,
            'coeff'    : coeff,
        },
        'sales_prediction': {
            'model'              : 'RandomForestRegressor',
            'r2_score'           : round(r2_rfr, 4),
            'features'           : FEATURES_SALES,
            'target'             : TARGET_SALES,
            'feature_importance' : fi_sales,
        }
    }
    with open(META, 'w') as f:
        json.dump(meta, f, indent=2)

    print(f"\n[5] Models saved → {MODEL_LR}, {MODEL_RFR}")
    print(f"✅ MODULE 4 complete. LR R²={r2_lr:.4f} | RFR R²={r2_rfr:.4f}")
    return r2_lr, r2_rfr


if __name__ == '__main__':
    r1, r2 = train()
    sys.exit(0 if (r1 >= 0.50 and r2 >= 0.70) else 1)
