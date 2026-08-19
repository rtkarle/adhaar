"""
MODULE 2: Volunteer Recommendation
Algorithm: Decision Tree Classifier
Why Decision Tree?
  - Directly mirrors the rule-based logic humans use (distance, availability, skills)
  - Produces human-readable decision paths (explainability)
  - Fast inference — critical for real-time volunteer assignment
  - Works well when features have clear threshold-based splits
  - No scaling needed; handles categorical + numeric naturally

Features:
  - Volunteer zone (encoded)
  - Donation zone (encoded)
  - Zone distance
  - Is available (binary)
  - Tasks completed (log-scaled)
  - Active tasks (workload)
  - Skill match count
  - Response rate
  - Days since last task

Target: suitability_label (0=poor, 1=fair, 2=good, 3=best)
"""

import pandas as pd
import numpy as np
from sklearn.tree import DecisionTreeClassifier, export_text
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import (
    accuracy_score, classification_report, confusion_matrix
)
import joblib
import json
import os
import sys

# ── Paths ─────────────────────────────────────────────────────
BASE  = os.path.dirname(os.path.abspath(__file__))
DATA  = os.path.join(BASE, '..', 'datasets', 'volunteer_recommendation.csv')
MODEL = os.path.join(BASE, '..', 'models', 'volunteer_recommendation_dt.joblib')
META  = os.path.join(BASE, '..', 'models', 'volunteer_recommendation_meta.json')


def train():
    print("=" * 55)
    print("  MODULE 2: Volunteer Recommendation — Training")
    print("=" * 55)

    # ── 1. Load ───────────────────────────────────────────────
    df = pd.read_csv(DATA)
    print(f"\n[1] Dataset: {df.shape[0]} rows × {df.shape[1]} cols")
    print(f"    Labels: {df['suitability_label'].value_counts().sort_index().to_dict()}")

    # ── 2. Feature engineering ────────────────────────────────
    df['log_tasks'] = np.log1p(df['tasks_completed'])      # log scale for tasks
    df['workload_penalty'] = df['active_tasks'].clip(0, 5) # cap at 5

    FEATURES = [
        'volunteer_zone_enc',
        'donation_zone_enc',
        'zone_distance',
        'is_available',
        'log_tasks',
        'workload_penalty',
        'skill_match_count',
        'response_rate',
        'days_since_last',
    ]
    TARGET = 'suitability_label'

    # ── 3. Preprocessing ──────────────────────────────────────
    df = df.dropna(subset=FEATURES + [TARGET])
    X  = df[FEATURES].copy()
    y  = df[TARGET].astype(int)
    for col in FEATURES:
        if X[col].isna().any():
            X[col] = X[col].fillna(X[col].median())
    print(f"\n[2] Clean rows: {len(X)}")

    # ── 4. Train-test split ───────────────────────────────────
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    print(f"\n[3] Train: {len(X_train)} | Test: {len(X_test)}")

    # ── 5. Hyperparameter search (simple grid) ────────────────
    best_acc, best_model = 0, None
    for depth in [6, 8, 10, 12]:
        for min_split in [5, 10]:
            m = DecisionTreeClassifier(
                max_depth=depth,
                min_samples_split=min_split,
                min_samples_leaf=3,
                class_weight='balanced',
                random_state=42
            )
            cv = cross_val_score(m, X_train, y_train, cv=5).mean()
            if cv > best_acc:
                best_acc, best_model = cv, m

    best_model.fit(X_train, y_train)
    print(f"\n[4] Best CV accuracy: {best_acc*100:.2f}%")

    # ── 6. Evaluation ─────────────────────────────────────────
    y_pred = best_model.predict(X_test)
    acc    = accuracy_score(y_test, y_pred)
    print(f"\n[5] Test Accuracy: {acc*100:.2f}%")
    print("\nClassification Report:")
    print(classification_report(
        y_test, y_pred,
        target_names=['poor','fair','good','best']
    ))
    print("\nDecision Tree Rules (first 20 lines):")
    tree_rules = export_text(best_model, feature_names=FEATURES, max_depth=3)
    print(tree_rules[:800])

    fi = dict(zip(FEATURES, best_model.feature_importances_.round(4).tolist()))
    print(f"\n[6] Top features: {sorted(fi.items(), key=lambda x:-x[1])[:5]}")

    # ── 7. Save ───────────────────────────────────────────────
    joblib.dump(best_model, MODEL, compress=3)
    meta = {
        'model'            : 'DecisionTreeClassifier',
        'module'           : 'volunteer_recommendation',
        'accuracy'         : round(acc, 4),
        'features'         : FEATURES,
        'target'           : TARGET,
        'classes'          : ['poor','fair','good','best'],
        'feature_importance': fi,
        'max_depth'        : best_model.max_depth,
    }
    with open(META, 'w') as f:
        json.dump(meta, f, indent=2)

    print(f"\n[7] Model saved → {MODEL}")
    print(f"✅ MODULE 2 complete. Accuracy: {acc*100:.2f}%")
    return acc


if __name__ == '__main__':
    acc = train()
    sys.exit(0 if acc >= 0.60 else 1)
