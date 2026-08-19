# Adhaar AI Python System

## Setup & Run

```bash
# 1. Install Python dependencies
pip install -r requirements.txt

# 2. Generate datasets + train all models
python train_all.py

# 3. Start Flask API server
python flask_api.py
```

## API Endpoints

| Method | Endpoint | Module |
|--------|----------|--------|
| POST | /api/v1/donation_match | Module 1: Donation Matching (Random Forest) |
| POST | /api/v1/volunteer_recommend | Module 2: Volunteer Recommendation (Decision Tree) |
| POST | /api/v1/product_recommend | Module 3: Product Recommendation (KNN) |
| POST | /api/v1/analytics_predict | Module 4: Analytics Prediction (LR + RF Regressor) |
| POST | /api/v1/ai_chat | Gemini LLM Assistant |
| GET | /api/v1/health | Health check |

## PHP Integration

```php
require_once 'config/ai_python.php';

// Module 1
$match = ai_donation_match('food', 50, 'high', 'pune_urban', 'pune_rural', 'food', 100, 5);
echo $match['data']['match_quality']; // 'excellent'

// Module 2
$vols = ai_volunteer_recommend('pune_urban', [
    ['email'=>'vol@test.com','name'=>'Rahul','zone'=>'pune_urban','is_available'=>1,'tasks_completed'=>15,'active_tasks'=>1,'skill_match_count'=>2,'response_rate'=>0.9,'days_since_last'=>3]
]);
echo $vols['data']['top_volunteer']['name'];

// Module 3
$recs = ai_product_recommend('textile', 'handicraft', 'jewelry', [
    ['id'=>1,'name'=>'Saree','category'=>'textile','rating'=>4.5,'price'=>850,'total_sold'=>32,'has_discount'=>1,'is_new_listing'=>0]
]);
echo $recs['data']['top_recommendations'][0]['relevance']; // 'highly_relevant'

// Module 4
$forecast = ai_analytics_predict(['week_number'=>52,'month'=>8,'season_index'=>1.3,'prev_food'=>12,'prev_cloth'=>6,'prev_total'=>18,'rolling_avg'=>16,'food_donations'=>14,'cloth_donations'=>7,'total_donations'=>21]);
echo $forecast['data']['next_week_donation_forecast'];

// Gemini LLM Chat
$reply = ai_chat('मला खाद्य दान कसे करायचे आहे?', 'donor', 'mr');
echo $reply['data']['reply'];
```

## Algorithms Used

| Module | Algorithm | Why |
|--------|-----------|-----|
| Donation Matching | Random Forest Classifier | Handles mixed features, robust to outliers, high accuracy |
| Volunteer Recommendation | Decision Tree Classifier | Fast inference, human-readable rules, threshold-based decisions |
| Product Recommendation | K-Nearest Neighbors (KNN) | Collaborative filtering, adapts to new data, no retraining needed |
| Donation Trend | Linear Regression | Simple, interpretable, works well for linear time-series |
| Sales Prediction | Random Forest Regressor | Non-linear seasonal patterns, complex feature interactions |
| LLM Assistant | Google Gemini 1.5 Flash | Multi-language (EN/HI/MR), context-aware, generative responses |

## Deployment

On live server:
1. Install Python 3.10+
2. `pip install -r requirements.txt`
3. `python train_all.py` (one-time)
4. `gunicorn -w 2 -b 0.0.0.0:5000 flask_api:app` (production)
5. Set GEMINI_API_KEY environment variable for LLM chat
