<?php
/**
 * Adhaar AI Engine v1.0
 * Pure PHP rule-based + statistical AI — no external API needed.
 * Functions: volunteer scoring, donation validity check,
 *            demand forecasting, impact prediction, smart recommendations.
 */
require_once __DIR__ . '/../config/db.php';

class AdhaarAI {

    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /* ── 1. VOLUNTEER SCORING ────────────────────────────────
     * Scores volunteers 0–100 for a given donation.
     * Factors: city match (40pts), past completions (30pts),
     *          current workload (20pts), last active (10pts)
     */
    public function scoreVolunteers(int $donation_id, string $donation_type): array {
        $table = ($donation_type === 'food') ? 'food_donations' : 'cloth_donations';
        $don = $this->conn->query("SELECT pickup_address FROM $table WHERE id=$donation_id")->fetch_assoc();
        if (!$don) return [];

        // Extract city from address (last meaningful word)
        $addr_words = preg_split('/[\s,]+/', trim($don['pickup_address']));
        $city_hint  = strtolower($addr_words[count($addr_words)-1] ?? '');

        $volunteers = $this->conn->query(
            "SELECT r.email, r.name, r.address,
             (SELECT COUNT(*) FROM food_donations WHERE volunteer_email=r.email AND status='delivered')
             + (SELECT COUNT(*) FROM cloth_donations WHERE volunteer_email=r.email AND status='delivered') AS completed,
             (SELECT COUNT(*) FROM food_donations WHERE volunteer_email=r.email AND status IN ('scheduled','out_for_pickup','picked_up'))
             + (SELECT COUNT(*) FROM cloth_donations WHERE volunteer_email=r.email AND status IN ('scheduled','out_for_pickup','picked_up')) AS active_tasks,
             (SELECT MAX(created_at) FROM volunteer_tasks WHERE volunteer_email=r.email) AS last_task_at
             FROM register r WHERE r.role='volunteer' AND r.verified=1"
        )->fetch_all(MYSQLI_ASSOC);

        $scored = [];
        foreach ($volunteers as $v) {
            $score = 0;

            // City match — 40 pts
            $vol_addr = strtolower($v['address'] ?? '');
            if ($city_hint && strpos($vol_addr, $city_hint) !== false) $score += 40;
            elseif (strlen($city_hint) > 3) {
                // Partial city match
                similar_text($city_hint, $vol_addr, $pct);
                $score += (int)($pct * 0.4);
            }

            // Completed tasks — up to 30 pts (log scale)
            $completed = (int)$v['completed'];
            $score += min(30, (int)(log(max(1,$completed)+1, 2) * 10));

            // Workload penalty — deduct up to 20 pts for active tasks
            $active = (int)$v['active_tasks'];
            $score += max(0, 20 - ($active * 7));

            // Last active bonus — up to 10 pts (active in last 7 days = 10, 30 days = 5)
            if ($v['last_task_at']) {
                $days_ago = (time() - strtotime($v['last_task_at'])) / 86400;
                if ($days_ago <= 7)  $score += 10;
                elseif ($days_ago <= 30) $score += 5;
            } else {
                $score += 5; // New volunteer bonus
            }

            $scored[] = [
                'email'        => $v['email'],
                'name'         => $v['name'],
                'score'        => min(100, max(0, $score)),
                'completed'    => $completed,
                'active_tasks' => $active,
                'city_match'   => $city_hint && strpos($vol_addr,$city_hint) !== false,
            ];
        }

        usort($scored, fn($a,$b) => $b['score'] - $a['score']);
        return $scored;
    }

    /* ── 2. DONATION VALIDITY CHECK ─────────────────────────
     * Checks if a food donation is still safe to accept.
     * Returns: ['valid'=>bool, 'reason'=>string, 'urgency'=>string]
     */
    public function checkFoodValidity(int $donation_id): array {
        $d = $this->conn->query(
            "SELECT food_time, safe_hours, priority, quantity FROM food_donations WHERE id=$donation_id"
        )->fetch_assoc();
        if (!$d) return ['valid'=>false,'reason'=>'Donation not found','urgency'=>'unknown'];

        $prepared_at  = strtotime($d['food_time'] ?? 'now');
        $safe_seconds = (int)$d['safe_hours'] * 3600;
        $expires_at   = $prepared_at + $safe_seconds;
        $now          = time();
        $remaining_h  = round(($expires_at - $now) / 3600, 1);

        if ($now > $expires_at) {
            return ['valid'=>false,'reason'=>"Food expired {$remaining_h}h ago",'urgency'=>'expired'];
        }

        $urgency = 'low';
        if ($remaining_h <= 2)  $urgency = 'critical';
        elseif ($remaining_h <= 4)  $urgency = 'high';
        elseif ($remaining_h <= 8)  $urgency = 'medium';

        return [
            'valid'       => true,
            'reason'      => "Valid for {$remaining_h}h more",
            'urgency'     => $urgency,
            'remaining_h' => $remaining_h,
            'feeds'       => (int)$d['quantity'],
        ];
    }

    /* ── 3. DEMAND FORECAST ──────────────────────────────────
     * Analyses donation trends over past 4 weeks.
     * Returns: weekly averages, trend direction, predicted next week.
     */
    public function demandForecast(): array {
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $from = date('Y-m-d', strtotime("-".($i+1)." weeks"));
            $to   = date('Y-m-d', strtotime("-$i weeks"));
            $food  = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations WHERE created_at BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
            $cloth = (int)$this->conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE created_at BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
            $weeks[] = ['week'=>"Week ".($i===0?'(current)':"-$i"), 'food'=>$food,'cloth'=>$cloth,'total'=>$food+$cloth];
        }

        // Simple linear regression for next week prediction
        $totals = array_column($weeks,'total');
        $n = count($totals);
        $sum_x = $sum_y = $sum_xy = $sum_x2 = 0;
        foreach ($totals as $i => $y) {
            $sum_x  += $i; $sum_y  += $y;
            $sum_xy += $i * $y; $sum_x2 += $i * $i;
        }
        $slope = $n > 1 ? ($n*$sum_xy - $sum_x*$sum_y) / max(1, $n*$sum_x2 - $sum_x*$sum_x) : 0;
        $intercept = ($sum_y - $slope*$sum_x) / $n;
        $predicted_next = max(0, round($intercept + $slope * $n));

        $trend = $slope > 0.5 ? 'increasing' : ($slope < -0.5 ? 'decreasing' : 'stable');

        return [
            'weeks'          => $weeks,
            'trend'          => $trend,
            'slope'          => round($slope, 2),
            'predicted_next' => $predicted_next,
            'avg_per_week'   => $n > 0 ? round(array_sum($totals)/$n,1) : 0,
        ];
    }

    /* ── 4. IMPACT PREDICTION ────────────────────────────────
     * Predicts real-world impact numbers from donation counts.
     */
    public function predictImpact(): array {
        $food_del  = (int)$this->conn->query("SELECT COALESCE(SUM(quantity),0) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c'];
        $cloth_del = (int)$this->conn->query("SELECT COALESCE(SUM(quantity),0) c FROM cloth_donations WHERE status='delivered'")->fetch_assoc()['c'];
        $vols      = (int)$this->conn->query("SELECT COUNT(*) c FROM register WHERE role='volunteer' AND verified=1")->fetch_assoc()['c'];
        $areas     = $this->conn->query("SELECT COUNT(DISTINCT SUBSTRING_INDEX(pickup_address,' ',-1)) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c'];

        // AI multipliers based on NGO research data
        $people_fed      = (int)($food_del * 3.2);   // avg 3.2 people per food unit
        $co2_saved_kg    = round(($food_del * 2.5) + ($cloth_del * 1.8), 1); // kg CO2 saved
        $water_saved_ltr = round($food_del * 950, 0); // litres of water saved
        $economic_value  = round(($food_del * 120) + ($cloth_del * 250), 0); // ₹ value

        return [
            'people_fed'      => $people_fed,
            'co2_saved_kg'    => $co2_saved_kg,
            'water_saved_ltr' => $water_saved_ltr,
            'economic_value'  => $economic_value,
            'food_delivered'  => $food_del,
            'cloth_delivered' => $cloth_del,
            'volunteers'      => $vols,
            'areas_covered'   => (int)$areas,
        ];
    }

    /* ── 5. SMART RECOMMENDATIONS ───────────────────────────
     * For admin: what needs attention right now.
     */
    public function getAdminRecommendations(): array {
        $recs = [];

        // High-priority food pending
        $hp = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations WHERE status='pending' AND priority='high'")->fetch_assoc()['c'];
        if ($hp > 0) $recs[] = ['type'=>'urgent','icon'=>'🔴','msg'=>"$hp high-priority food donation".($hp>1?'s':'')." need immediate acceptance."];

        // Expiring food
        $exp = $this->conn->query("SELECT id, safe_hours, food_time FROM food_donations WHERE status='pending' AND food_time IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
        $expiring = 0;
        foreach ($exp as $e) {
            $expires = strtotime($e['food_time']) + $e['safe_hours']*3600;
            if ($expires - time() < 7200) $expiring++;
        }
        if ($expiring > 0) $recs[] = ['type'=>'urgent','icon'=>'⏰','msg'=>"$expiring food donation".($expiring>1?'s':'')." expiring within 2 hours!"];

        // Unverified sellers
        $uv = (int)$this->conn->query("SELECT COUNT(*) c FROM seller_stores WHERE is_verified=0")->fetch_assoc()['c'];
        if ($uv > 0) $recs[] = ['type'=>'info','icon'=>'🏪','msg'=>"$uv seller store".($uv>1?'s':'')." awaiting verification."];

        // Pending tasks not accepted
        $pt = (int)$this->conn->query("SELECT COUNT(*) c FROM volunteer_tasks WHERE task_status='pending_acceptance'")->fetch_assoc()['c'];
        if ($pt > 0) $recs[] = ['type'=>'warn','icon'=>'📋','msg'=>"$pt volunteer task".($pt>1?'s':'')." not yet accepted by volunteers."];

        // Return requests
        $rr = (int)$this->conn->query("SELECT COUNT(*) c FROM return_requests WHERE status='requested'")->fetch_assoc()['c'];
        if ($rr > 0) $recs[] = ['type'=>'warn','icon'=>'↩️','msg'=>"$rr return request".($rr>1?'s':'')." awaiting admin action."];

        // Positive: delivery rate
        $total = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations")->fetch_assoc()['c']
                +(int)$this->conn->query("SELECT COUNT(*) c FROM cloth_donations")->fetch_assoc()['c'];
        $del   = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c']
                +(int)$this->conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE status='delivered'")->fetch_assoc()['c'];
        if ($total > 0) {
            $rate = round($del/$total*100);
            $recs[] = ['type'=>'success','icon'=>'📈','msg'=>"Delivery rate: $rate% — ".($rate>=80?'Excellent! Keep it up.':($rate>=50?'Good, but room to improve.':'Needs attention.'))];
        }

        if (empty($recs)) $recs[] = ['type'=>'success','icon'=>'✅','msg'=>'All systems operational. No immediate actions needed.'];
        return $recs;
    }

    /* ── 6. DONOR SUGGESTIONS ───────────────────────────────
     * Personalised suggestions for a specific donor.
     */
    public function getDonorSuggestions(string $donor_email): array {
        $food_count  = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations WHERE donor_email='".mysqli_real_escape_string($this->conn,$donor_email)."'")->fetch_assoc()['c'];
        $cloth_count = (int)$this->conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE donor_email='".mysqli_real_escape_string($this->conn,$donor_email)."'")->fetch_assoc()['c'];
        $last_don    = $this->conn->query("SELECT MAX(created_at) last FROM (SELECT created_at FROM food_donations WHERE donor_email='".mysqli_real_escape_string($this->conn,$donor_email)."' UNION ALL SELECT created_at FROM cloth_donations WHERE donor_email='".mysqli_real_escape_string($this->conn,$donor_email)."') x")->fetch_assoc()['last'];

        $suggestions = [];
        $days_since = $last_don ? round((time()-strtotime($last_don))/86400) : 999;

        // What's needed most on platform
        $food_pending  = (int)$this->conn->query("SELECT COUNT(*) c FROM food_donations WHERE status='pending'")->fetch_assoc()['c'];
        $cloth_pending = (int)$this->conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE status='pending'")->fetch_assoc()['c'];
        $needed_most   = $food_pending > $cloth_pending ? 'food 🍱' : 'clothing 👕';

        $suggestions[] = ['icon'=>'🤖','text'=>"The platform currently needs <strong>$needed_most</strong> donations most. Your contribution will make an immediate difference."];

        if ($days_since > 30) {
            $suggestions[] = ['icon'=>'⏰','text'=>"It's been <strong>$days_since days</strong> since your last donation. A small donation today can feed a family tonight."];
        }

        if ($food_count === 0) {
            $suggestions[] = ['icon'=>'🍱','text'=>"You haven't donated food yet. Cooked food donations are the most urgent — they help families the same day!"];
        } elseif ($cloth_count === 0) {
            $suggestions[] = ['icon'=>'👕','text'=>"Try a <strong>clothing donation</strong>! Unused clothes have a huge impact, especially for children in rural areas."];
        }

        // Impact calculator
        $impact_food  = $food_count * 15;  // avg 15 people fed per food donation
        $impact_cloth = $cloth_count * 3;  // avg 3 people per clothing donation
        $suggestions[] = ['icon'=>'💚','text'=>"Your <strong>$food_count food + $cloth_count clothing</strong> donations have impacted approximately <strong>".($impact_food+$impact_cloth)." people</strong> so far."];

        // Seasonal suggestion
        $month = (int)date('n');
        if ($month >= 11 || $month <= 2) {
            $suggestions[] = ['icon'=>'❄️','text'=>"Winter is here! <strong>Warm clothing donations</strong> — jackets, sweaters, blankets — are urgently needed for rural communities."];
        } elseif ($month >= 6 && $month <= 8) {
            $suggestions[] = ['icon'=>'🌧️','text'=>"Monsoon season: <strong>food donations</strong> are critical as outdoor activities are limited and families face hardship."];
        }

        return $suggestions;
    }

    /* ── 8. PRODUCT RECOMMENDATIONS ────────────────────────
     * Returns personalised product recommendations for a user
     * based on: search history, view history, purchase history, and category preference.
     * Returns array of product rows scored and ranked.
     */
    public function getProductRecommendations(string $user_email, int $current_product_id = 0, int $limit = 6): array {
        $me = mysqli_real_escape_string($this->conn, $user_email);

        // ── Collect signals ──────────────────────────────────
        // 1. Categories from search history (last 30 days)
        $search_cats = [];
        $sh_exist = $this->conn->query("SHOW TABLES LIKE 'product_search_history'")->num_rows > 0;
        if ($sh_exist) {
            $sq = $this->conn->query(
                "SELECT category, COUNT(*) w FROM product_search_history
                 WHERE user_email='$me' AND searched_at > NOW() - INTERVAL 30 DAY
                 AND category IS NOT NULL GROUP BY category ORDER BY w DESC LIMIT 5"
            );
            while ($r = $sq->fetch_assoc()) $search_cats[$r['category']] = (int)$r['w'];
        }

        // 2. Products viewed (last 30 days)
        $viewed_ids = [];
        $vh_exist = $this->conn->query("SHOW TABLES LIKE 'product_view_history'")->num_rows > 0;
        if ($vh_exist) {
            $vq = $this->conn->query(
                "SELECT product_id, view_count FROM product_view_history
                 WHERE user_email='$me' AND last_viewed > NOW() - INTERVAL 30 DAY
                 ORDER BY view_count DESC, last_viewed DESC LIMIT 20"
            );
            while ($r = $vq->fetch_assoc()) $viewed_ids[$r['product_id']] = (int)$r['view_count'];
        }

        // 3. Categories from purchase history
        $bought_cats = [];
        $bq = $this->conn->query(
            "SELECT p.category, COUNT(*) w FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             JOIN products p ON p.id=oi.product_id
             WHERE o.buyer_email='$me'
             GROUP BY p.category ORDER BY w DESC LIMIT 5"
        );
        while ($r = $bq->fetch_assoc()) $bought_cats[$r['category']] = (int)$r['w'];

        // ── Build category preference score ──────────────────
        $cat_scores = [];
        foreach ($search_cats as $cat => $w) $cat_scores[$cat] = ($cat_scores[$cat] ?? 0) + $w * 3; // search = 3x weight
        foreach ($bought_cats as $cat => $w) $cat_scores[$cat] = ($cat_scores[$cat] ?? 0) + $w * 5; // purchase = 5x weight
        // Viewed products also boost their category
        if (!empty($viewed_ids)) {
            $id_list = implode(',', array_map('intval', array_keys($viewed_ids)));
            $vcq = $this->conn->query("SELECT category FROM products WHERE id IN ($id_list)");
            while ($r = $vcq->fetch_assoc()) {
                $cat_scores[$r['category']] = ($cat_scores[$r['category']] ?? 0) + 2;
            }
        }
        arsort($cat_scores);
        $top_cats = array_keys(array_slice($cat_scores, 0, 3, true));

        // ── Fetch candidate products ──────────────────────────
        $exclude = $current_product_id > 0 ? "AND p.id != $current_product_id" : '';
        $all_q = $this->conn->query(
            "SELECT p.*, s.store_name, s.village, s.state
             FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email
             WHERE p.is_active=1 AND s.is_active=1 $exclude
             ORDER BY p.total_sold DESC, p.avg_rating DESC LIMIT 100"
        );
        $candidates = $all_q->fetch_all(MYSQLI_ASSOC);

        // ── Score each candidate ──────────────────────────────
        foreach ($candidates as &$prod) {
            $s = 0;
            $cat = $prod['category'];

            // Category preference
            $s += ($cat_scores[$cat] ?? 0) * 8;

            // Popularity (sold count)
            $s += min(30, (int)log(max(1, $prod['total_sold']) + 1, 2) * 6);

            // Rating boost
            $s += (float)$prod['avg_rating'] * 8;

            // Already viewed → slight boost (familiarity effect)
            if (isset($viewed_ids[$prod['id']])) $s += 5;

            // Discount available → boost
            if ($prod['mrp'] > 0 && $prod['price'] < $prod['mrp']) $s += 10;

            // Freshness (listed in last 30 days)
            if (strtotime($prod['created_at']) > strtotime('-30 days')) $s += 8;

            $prod['_rec_score'] = round($s, 2);
        }
        unset($prod);

        // ── Sort by score, return top N ───────────────────────
        usort($candidates, fn($a,$b) => $b['_rec_score'] <=> $a['_rec_score']);
        return array_slice($candidates, 0, $limit);
    }

    /* ── 9. SCORE VOLUNTEERS WITH DISTANCE ──────────────────
     * Enhanced volunteer scoring with:
     *   - Pincode-based distance estimation (40 pts)
     *   - Past completion rate (25 pts)
     *   - Current availability / workload (20 pts)
     *   - Last active recency (10 pts)
     *   - Response rate (5 pts)
     */
    public function scoreVolunteersWithDistance(int $donation_id, string $donation_type): array {
        $table = ($donation_type === 'food') ? 'food_donations' : 'cloth_donations';

        // Get donation address and pincode
        $don = $this->conn->query(
            "SELECT pickup_address, donor_pincode FROM $table WHERE id=$donation_id"
        )->fetch_assoc();
        if (!$don) return [];

        $donor_pin  = trim($don['donor_pincode'] ?? '');
        $donor_addr = strtolower($don['pickup_address'] ?? '');

        // Extract city hint from address (last word before pincode)
        $addr_words = preg_split('/[\s,]+/', $donor_addr);
        // Find 6-digit pincode in address if not stored
        if (!$donor_pin) {
            foreach ($addr_words as $w) {
                if (preg_match('/^\d{6}$/', $w)) { $donor_pin = $w; break; }
            }
        }
        $city_hint = '';
        foreach (array_reverse($addr_words) as $w) {
            if (strlen($w) > 3 && !is_numeric($w)) { $city_hint = $w; break; }
        }

        // Get all volunteers with pincode
        $volunteers = $this->conn->query(
            "SELECT r.email, r.name, r.address, r.mobile,
             COALESCE(r.pincode,'') AS vol_pincode,
             (SELECT COUNT(*) FROM food_donations WHERE volunteer_email=r.email AND status='delivered')
             + (SELECT COUNT(*) FROM cloth_donations WHERE volunteer_email=r.email AND status='delivered') AS completed,
             (SELECT COUNT(*) FROM food_donations WHERE volunteer_email=r.email AND status IN ('scheduled','out_for_pickup','picked_up'))
             + (SELECT COUNT(*) FROM cloth_donations WHERE volunteer_email=r.email AND status IN ('scheduled','out_for_pickup','picked_up')) AS active_tasks,
             (SELECT COUNT(*) FROM volunteer_tasks WHERE volunteer_email=r.email) AS total_tasks,
             (SELECT COUNT(*) FROM volunteer_tasks WHERE volunteer_email=r.email AND task_status='accepted') AS accepted_tasks,
             (SELECT MAX(assigned_at) FROM volunteer_tasks WHERE volunteer_email=r.email) AS last_task_at
             FROM register r WHERE r.role='volunteer' AND r.verified=1"
        )->fetch_all(MYSQLI_ASSOC);

        $scored = [];
        foreach ($volunteers as $v) {
            $score     = 0;
            $breakdown = [];

            // ── DISTANCE SCORING (40 pts) ─────────────────────
            $vol_pin  = trim($v['vol_pincode']);
            $vol_addr = strtolower($v['address'] ?? '');

            if ($donor_pin && $vol_pin && $donor_pin === $vol_pin) {
                // Same pincode = same area
                $score += 40;
                $breakdown['distance'] = 'Same pincode (+40)';
            } elseif ($donor_pin && $vol_pin) {
                // Compare first 3 digits of pincode (same district in India)
                $same_district = substr($donor_pin,0,3) === substr($vol_pin,0,3);
                $same_subdistrict = substr($donor_pin,0,4) === substr($vol_pin,0,4);
                if ($same_subdistrict) {
                    $score += 30;
                    $breakdown['distance'] = 'Same sub-district (+30)';
                } elseif ($same_district) {
                    $score += 20;
                    $breakdown['distance'] = 'Same district (+20)';
                } else {
                    // Different district — estimate by first digit (same state zone)
                    $same_zone = $donor_pin[0] === $vol_pin[0];
                    $score += $same_zone ? 8 : 2;
                    $breakdown['distance'] = $same_zone ? 'Same state zone (+8)' : 'Different zone (+2)';
                }
            } elseif ($city_hint && strpos($vol_addr, $city_hint) !== false) {
                // Fallback: city name match
                $score += 25;
                $breakdown['distance'] = "City name match '$city_hint' (+25)";
            } elseif (strlen($city_hint) > 3) {
                similar_text($city_hint, $vol_addr, $pct);
                $pts = (int)($pct * 0.3);
                $score += $pts;
                $breakdown['distance'] = "Partial city match {$pct}% (+$pts)";
            } else {
                $breakdown['distance'] = 'No location match (+0)';
            }

            // ── COMPLETION RATE (25 pts) ──────────────────────
            $completed = (int)$v['completed'];
            $pts = min(25, (int)(log(max(1,$completed)+1, 2) * 8));
            $score += $pts;
            $breakdown['completions'] = "$completed completed (+$pts)";

            // ── AVAILABILITY / WORKLOAD (20 pts) ─────────────
            $active = (int)$v['active_tasks'];
            $avail_pts = max(0, 20 - ($active * 7));
            $score += $avail_pts;
            $breakdown['workload'] = "$active active tasks (+$avail_pts)";

            // ── RECENCY (10 pts) ──────────────────────────────
            $recency_pts = 5; // default for new volunteer
            if ($v['last_task_at']) {
                $days_ago = (time() - strtotime($v['last_task_at'])) / 86400;
                if ($days_ago <= 3)       $recency_pts = 10;
                elseif ($days_ago <= 7)   $recency_pts = 8;
                elseif ($days_ago <= 14)  $recency_pts = 6;
                elseif ($days_ago <= 30)  $recency_pts = 4;
                else                      $recency_pts = 1;
            }
            $score += $recency_pts;
            $breakdown['recency'] = "Last active: $recency_pts/10";

            // ── RESPONSE RATE (5 pts) ─────────────────────────
            $total   = (int)$v['total_tasks'];
            $accepted= (int)$v['accepted_tasks'];
            $resp_rate = $total > 0 ? round($accepted/$total*100) : 100;
            $resp_pts = (int)($resp_rate / 20); // 100% → 5pts, 80% → 4pts etc.
            $score += $resp_pts;
            $breakdown['response_rate'] = "$resp_rate% response (+$resp_pts)";

            $scored[] = [
                'email'         => $v['email'],
                'name'          => $v['name'],
                'mobile'        => $v['mobile'],
                'score'         => min(100, max(0, $score)),
                'completed'     => $completed,
                'active_tasks'  => $active,
                'vol_pincode'   => $vol_pin,
                'donor_pincode' => $donor_pin,
                'city_match'    => $city_hint && strpos($vol_addr,$city_hint) !== false,
                'response_rate' => $resp_rate,
                'breakdown'     => $breakdown,
            ];
        }

        usort($scored, fn($a,$b) => $b['score'] - $a['score']);
        return $scored;
    }

    /* ── 10. LOG SEARCH / VIEW ───────────────────────────────
     * Tracks search queries and product views for recommendations.
     */
    public function logSearch(string $user_email, string $query, ?string $category, int $result_count): void {
        if (!$this->conn->query("SHOW TABLES LIKE 'product_search_history'")->num_rows) return;
        try {
            $s = $this->conn->prepare(
                "INSERT INTO product_search_history (user_email,query,category,result_count,searched_at) VALUES (?,?,?,?,NOW())"
            );
            $s->bind_param("sssi", $user_email, $query, $category, $result_count);
            $s->execute();
        } catch (Exception $e) {}
    }

    public function logView(string $user_email, int $product_id): void {
        if (!$this->conn->query("SHOW TABLES LIKE 'product_view_history'")->num_rows) return;
        try {
            $s = $this->conn->prepare(
                "INSERT INTO product_view_history (user_email,product_id,view_count,last_viewed)
                 VALUES (?,?,1,NOW())
                 ON DUPLICATE KEY UPDATE view_count=view_count+1, last_viewed=NOW()"
            );
            $s->bind_param("si", $user_email, $product_id);
            $s->execute();
        } catch (Exception $e) {}
    }

    /* ── 7. LOG AI DECISION ─────────────────────────────────
     */
    public function log(string $action, $input, $output, float $confidence = 0, string $by = 'system'): void {
        try {
            $s = $this->conn->prepare("INSERT INTO ai_logs (action_type,input_data,output_data,confidence,triggered_by) VALUES (?,?,?,?,?)");
            $in  = json_encode($input,  JSON_UNESCAPED_UNICODE);
            $out = json_encode($output, JSON_UNESCAPED_UNICODE);
            $s->bind_param("sssds", $action, $in, $out, $confidence, $by);
            $s->execute();
        } catch (Exception $e) { /* silent — logging should never break the app */ }
    }
}

/* ── Expose as singleton ── */
function adhaar_ai(): AdhaarAI {
    global $conn, $_adhaar_ai;
    if (!isset($_adhaar_ai)) $_adhaar_ai = new AdhaarAI($conn);
    return $_adhaar_ai;
}
