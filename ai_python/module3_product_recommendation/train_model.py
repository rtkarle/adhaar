"""
MODULE 3: Product Recommendation (KNN + Random Forest hybrid)
Primary Algorithm: K-Nearest Neighbors (KNN)
Why KNN?
  - "Collaborative filtering" approach — find products similar to what user liked
  - No training phase needed (lazy learner) — adapts instantly to new data
  - Intuitive: 'people who searched X also bought Y'
  - Works well for recommendation with small-medium feature spaces

We also train a Random Forest as a cross-validation benchmark.

Features:
  - Searched category (encoded)
  - Bought category (encoded)
  - Viewed category (encoded)
  - Product category (encoded)
  - Product rating
  - Product price (log-scaled)
  - Total sold (log-scaled)
  - Has discount
  - Is new listing
  - Category match flags (search, buy, view)

Target: relevance_label (0=not relevant, 1=somewhat, 2=relevant, 3=highly relevant)
"""

import pandas as pd
import numpy as np
from sklearn.neighbors import KNeighborsClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import StandardScaler
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import accuracy_score, classification_report
import joblib
import json
import os
import sys

BASE  = os.path.dirname(os.path.abspath(__file__))
DATA  = os.path.join(BASE, '..', 'datasets', 'product_recommendation.csv')
MODEL_KNN = os.path.join(BASE, '..', 'models', 'product_rec_knn.joblib')
MODEL_SCL = os.path.join(BASE, '..', 'models', 'product_rec_scaler.joblib')
META      = os.path.join(BASE, '..', 'models', 'product_recommendation_meta.json')


def train():
    print("=" * 55)
    print("  MODULE 3: Product Recommendation — Training")
    print("=" * 55)

    # ── 1. Load ───────────────────────────────────────────────
    df = pd.read_csv(DATA)
    print(f"\n[1] Dataset: {df.shape}")
    print(f"    Labels: {df['relevance_label'].value_counts().sort_index().to_dict()}")

    # ── 2. Feature engineering ────────────────────────────────
    df['log_price']     = np.log1p(df['product_price'])
    df['log_sold']      = np.log1p(df['total_sold'])
    df['rating_sq']     = df['product_rating'] ** 2  # amplify high ratings

    FEATURES = [
        'searched_category_enc',
        'bought_category_enc',
        'viewed_category_enc',
        'product_category_enc',
        'product_rating',
        'rating_sq',
        'log_price',
        'log_sold',
        'has_discount',
        'is_new_listing',
        'cat_match_search',
        'cat_match_buy',
        'cat_match_view',
    ]
    TARGET = 'relevance_label'

    # ── 3. Preprocessing ──────────────────────────────────────
    df = df.dropna(subset=FEATURES + [TARGET])
    X  = df[FEATURES].copy()
    y  = df[TARGET].astype(int)
    for col in FEATURES:
        if X[col].isna().any():
            X[col] = X[col].fillna(X[col].median())
    print(f"\n[2] Clean rows: {len(X)}")

    # ── 4. Train/test split ───────────────────────────────────
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    # KNN REQUIRES feature scaling (distance-based)
    scaler  = StandardScaler()
    Xtr_sc  = scaler.fit_transform(X_train)
    Xte_sc  = scaler.transform(X_test)
    print(f"\n[3] Scaling done. Train: {len(X_train)} | Test: {len(X_test)}")

    # ── 5. KNN with k search ──────────────────────────────────
    best_k, best_knn_acc = 5, 0
    for k in [3, 5, 7, 9, 11, 15]:
        knn = KNeighborsClassifier(
            n_neighbors = k,
            weights     = 'distance',   # closer = more weight
            metric      = 'euclidean',
            n_jobs      = -1
        )
        cv = cross_val_score(knn, Xtr_sc, y_train, cv=5).mean()
        print(f"    k={k}: CV={cv*100:.1f}%")
        if cv > best_knn_acc:
            best_knn_acc, best_k = cv, k

    knn = KNeighborsClassifier(n_neighbors=best_k, weights='distance', n_jobs=-1)
    knn.fit(Xtr_sc, y_train)
    y_pred = knn.predict(Xte_sc)
    knn_acc = accuracy_score(y_test, y_pred)
    print(f"\n[4] Best KNN (k={best_k}): {knn_acc*100:.2f}%")
    print(classification_report(y_test, y_pred, target_names=['not_relevant','somewhat','relevant','highly_relevant']))

    # ── 6. RF benchmark ───────────────────────────────────────
    rf = RandomForestClassifier(n_estimators=100, random_state=42, n_jobs=-1)
    rf.fit(X_train, y_train)
    rf_acc = accuracy_score(y_test, rf.predict(X_test))
    print(f"[5] RF benchmark: {rf_acc*100:.2f}%")

    # ── 7. Save ───────────────────────────────────────────────
    joblib.dump(knn,    MODEL_KNN, compress=3)
    joblib.dump(scaler, MODEL_SCL, compress=3)

    fi = dict(zip(FEATURES, rf.feature_importances_.round(4).tolist()))
    meta = {
        'model'       : 'KNeighborsClassifier',
        'module'      : 'product_recommendation',
        'knn_accuracy': round(knn_acc, 4),
        'rf_accuracy' : round(rf_acc,  4),
        'best_k'      : best_k,
        'features'    : FEATURES,
        'target'      : TARGET,
        'classes'     : ['not_relevant','somewhat','relevant','highly_relevant'],
        'rf_feature_importance': fi,
        'scaling'     : 'StandardScaler'
    }
    with open(META, 'w') as f:
        json.dump(meta, f, indent=2)

    print(f"\n[6] Models saved → {MODEL_KNN}")
    print(f"✅ MODULE 3 complete. KNN: {knn_acc*100:.2f}% | RF: {rf_acc*100:.2f}%")
    return knn_acc


if __name__ == '__main__':
    acc = train()
    sys.exit(0 if acc >= 0.55 else 1)
