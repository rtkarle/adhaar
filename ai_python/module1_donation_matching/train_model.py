"""
MODULE 1: Smart Donation Matching
Algorithm: Random Forest Classifier
Why Random Forest?
  - Handles mixed categorical + numerical features well
  - Robust to outliers and missing values
  - Provides feature importance for explainability
  - Works well with small-medium datasets (1000-10000 rows)
  - No feature scaling needed

Features:
  - Donation category (encoded)
  - Quantity
  - Urgency level (encoded)
  - Donor zone (encoded)
  - Beneficiary zone (encoded)
  - Zone distance
  - NGO required category (encoded)
  - NGO capacity
  - Capacity sufficient flag
  - Donor past donation count

Target: match_label (0=no_match, 1=partial, 2=good, 3=excellent)
"""

import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import (
    accuracy_score, classification_report, confusion_matrix
)
import joblib
import json
import os
import sys

# ── Paths ─────────────────────────────────────────────────────
BASE  = os.path.dirname(os.path.abspath(__file__))
DATA  = os.path.join(BASE, '..', 'datasets', 'donation_matching.csv')
MODEL = os.path.join(BASE, '..', 'models', 'donation_matching_rf.joblib')
META  = os.path.join(BASE, '..', 'models', 'donation_matching_meta.json')


def train():
    print("=" * 55)
    print("  MODULE 1: Smart Donation Matching — Training")
    print("=" * 55)

    # ── 1. Load dataset ───────────────────────────────────────
    df = pd.read_csv(DATA)
    print(f"\n[1] Dataset loaded: {df.shape[0]} rows × {df.shape[1]} cols")
    print(f"    Class distribution:\n{df['match_label'].value_counts().sort_index()}")

    # ── 2. Feature engineering ────────────────────────────────
    # We use pre-encoded columns from the dataset generator
    FEATURES = [
        'donation_category_enc',
        'quantity',
        'urgency_enc',
        'donor_zone_enc',
        'beneficiary_zone_enc',
        'zone_distance',
        'same_zone',
        'ngo_req_cat_enc',
        'ngo_capacity',
        'capacity_sufficient',
        'donor_past_donations',
    ]
    TARGET = 'match_label'

    # ── 3. Data preprocessing ─────────────────────────────────
    df = df.dropna(subset=FEATURES + [TARGET])  # remove rows with NaN
    X  = df[FEATURES].copy()
    y  = df[TARGET].astype(int)

    # Missing value handling (fill numeric cols with median)
    for col in FEATURES:
        if X[col].isna().any():
            X[col] = X[col].fillna(X[col].median())

    print(f"\n[2] After preprocessing: {X.shape[0]} clean rows")

    # ── 4. Train-test split ───────────────────────────────────
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    print(f"\n[3] Train: {len(X_train)} | Test: {len(X_test)}")

    # ── 5. Model training ─────────────────────────────────────
    model = RandomForestClassifier(
        n_estimators   = 200,     # 200 trees
        max_depth      = 12,      # prevent overfitting
        min_samples_split = 5,
        min_samples_leaf  = 2,
        class_weight   = 'balanced',  # handle class imbalance
        random_state   = 42,
        n_jobs         = -1
    )
    model.fit(X_train, y_train)
    print("\n[4] Model trained ✅")

    # ── 6. Model evaluation ───────────────────────────────────
    y_pred = model.predict(X_test)
    acc    = accuracy_score(y_test, y_pred)
    print(f"\n[5] Accuracy: {acc*100:.2f}%")
    print("\nClassification Report:")
    print(classification_report(
        y_test, y_pred,
        target_names=['no_match','partial','good','excellent']
    ))
    print("\nConfusion Matrix:")
    print(confusion_matrix(y_test, y_pred))

    # Feature importance
    fi = dict(zip(FEATURES, model.feature_importances_.round(4).tolist()))
    print(f"\n[6] Top features: {sorted(fi.items(), key=lambda x:-x[1])[:5]}")

    # ── 7. Save model + metadata ──────────────────────────────
    joblib.dump(model, MODEL, compress=3)
    meta = {
        'model'       : 'RandomForestClassifier',
        'module'      : 'donation_matching',
        'accuracy'    : round(acc, 4),
        'features'    : FEATURES,
        'target'      : TARGET,
        'classes'     : ['no_match','partial','good','excellent'],
        'feature_importance': fi,
        'n_estimators': 200,
        'trained_rows': len(X_train),
    }
    with open(META, 'w') as f:
        json.dump(meta, f, indent=2)

    print(f"\n[7] Model saved → {MODEL}")
    print(f"    Meta  saved → {META}")
    print(f"\n✅ MODULE 1 training complete. Accuracy: {acc*100:.2f}%")
    return acc


if __name__ == '__main__':
    acc = train()
    sys.exit(0 if acc >= 0.60 else 1)
