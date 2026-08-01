"""
Adhaar AI — Master Training Script
Runs all 4 modules in sequence.
Run: python train_all.py
"""
import subprocess, sys, os, time

BASE = os.path.dirname(os.path.abspath(__file__))

def run(script, label):
    print(f"\n{'='*55}")
    print(f"  Training: {label}")
    print(f"{'='*55}")
    start = time.time()
    r = subprocess.run([sys.executable, script], capture_output=False)
    elapsed = round(time.time() - start, 1)
    status = "✅ DONE" if r.returncode == 0 else "❌ FAILED"
    print(f"\n{status} — {label} ({elapsed}s)")
    return r.returncode == 0

if __name__ == '__main__':
    # Step 0: Generate datasets
    print("\n[0] Generating datasets...")
    ok = run(os.path.join(BASE,'datasets','generate_datasets.py'), 'Dataset Generator')
    if not ok:
        print("❌ Dataset generation failed. Exiting."); sys.exit(1)

    # Train all 4 modules
    modules = [
        (os.path.join(BASE,'module1_donation_matching','train_model.py'),      'Module 1: Donation Matching (Random Forest)'),
        (os.path.join(BASE,'module2_volunteer_recommendation','train_model.py'),'Module 2: Volunteer Recommendation (Decision Tree)'),
        (os.path.join(BASE,'module3_product_recommendation','train_model.py'),  'Module 3: Product Recommendation (KNN)'),
        (os.path.join(BASE,'module4_analytics_prediction','train_model.py'),    'Module 4: Analytics Prediction (LR + RF Regressor)'),
    ]

    results = []
    for script, label in modules:
        ok = run(script, label)
        results.append((label, ok))

    print(f"\n{'='*55}")
    print("  TRAINING SUMMARY")
    print(f"{'='*55}")
    for label, ok in results:
        print(f"  {'✅' if ok else '❌'} {label}")
    all_ok = all(r for _, r in results)
    print(f"\n{'='*55}")
    print(f"  {'✅ All modules trained!' if all_ok else '⚠ Some modules failed'}")
    print(f"  Start API: python flask_api.py")
    print(f"{'='*55}")
    sys.exit(0 if all_ok else 1)
