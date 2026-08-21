<?php
/**
 * SoulServe AI Assistant — Unified SSE Chatbot Backend
 * GET/POST /api/ai_assistant.php
 * Supports: donor, volunteer, seller roles
 * Uses rule-based AI + DB context + Gemini (if key set)
 *
 * POST params: message, context (donor|volunteer|seller|public), stream (0|1)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_python.php';
require_once __DIR__ . '/ai_engine.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$message = trim($_POST['message'] ?? $_GET['message'] ?? '');
$context = in_array($_POST['context'] ?? $_GET['context'] ?? 'public',
           ['donor','volunteer','seller','admin','public'])
           ? ($_POST['context'] ?? $_GET['context'] ?? 'public') : 'public';
$stream  = !empty($_POST['stream']) || !empty($_GET['stream']);
$lang    = $_POST['lang'] ?? 'en';

if (!$message) {
    echo json_encode(['ok'=>false,'reply'=>'Please enter a message.']); exit;
}

$email = $_SESSION['user_email'] ?? null;
$ai    = adhaar_ai();

/* ══ LIVE DB CONTEXT ══ */
$ctx = [];
if ($email) {
    $me = mysqli_real_escape_string($conn, $email);
    if ($context === 'donor') {
        $ctx['food_donations']  = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE donor_email='$me'")->fetch_assoc()['c'];
        $ctx['cloth_donations'] = (int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE donor_email='$me'")->fetch_assoc()['c'];
        $ctx['active']          = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE donor_email='$me' AND status NOT IN ('delivered','rejected')")->fetch_assoc()['c']
                                 +(int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE donor_email='$me' AND status NOT IN ('delivered','rejected')")->fetch_assoc()['c'];
        $ctx['badges']          = [];
        try {
            $bq = $conn->query("SELECT badge_name,badge_emoji FROM donor_badges WHERE donor_email='$me' LIMIT 5");
            if ($bq) $ctx['badges'] = $bq->fetch_all(MYSQLI_ASSOC);
        } catch (Throwable $e) {}
    } elseif ($context === 'volunteer') {
        $ctx['active_tasks']  = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE volunteer_email='$me' AND status NOT IN ('delivered','rejected')")->fetch_assoc()['c']
                               +(int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE volunteer_email='$me' AND status NOT IN ('delivered','rejected')")->fetch_assoc()['c'];
        $ctx['completed']     = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE volunteer_email='$me' AND status='delivered'")->fetch_assoc()['c']
                               +(int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE volunteer_email='$me' AND status='delivered'")->fetch_assoc()['c'];
        $ctx['pending_tasks'] = (int)$conn->query("SELECT COUNT(*) c FROM volunteer_tasks WHERE volunteer_email='$me' AND task_status='pending_acceptance'")->fetch_assoc()['c'];
        $wl = $ai->checkVolunteerWorkload($email);
        $ctx['workload']  = $wl;
    } elseif ($context === 'seller') {
        try {
            $ctx['products'] = (int)$conn->query("SELECT COUNT(*) c FROM products WHERE seller_email='$me' AND is_active=1")->fetch_assoc()['c'];
            $ctx['orders']   = (int)$conn->query("SELECT COUNT(*) c FROM orders WHERE seller_email='$me'")->fetch_assoc()['c'];
            $ctx['pending']  = (int)$conn->query("SELECT COUNT(*) c FROM orders WHERE seller_email='$me' AND order_status='placed'")->fetch_assoc()['c'];
            $ctx['revenue']  = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) r FROM orders WHERE seller_email='$me' AND order_status NOT IN ('cancelled','returned')")->fetch_assoc()['r'];
        } catch (Throwable $e) {}
    }
}

/* Platform stats for all */
try {
    $ctx['platform_meals']   = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c'];
    $ctx['platform_cloth']   = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) c FROM cloth_donations WHERE status='delivered'")->fetch_assoc()['c'];
    $ctx['platform_vols']    = (int)$conn->query("SELECT COUNT(*) c FROM register WHERE role='volunteer' AND verified=1")->fetch_assoc()['c'];
} catch (Throwable $e) {}

/* ══ RULE-BASED ANSWER ENGINE ══ */
function getAIReply(string $q, string $ctx_role, array $ctx_data, object $ai_obj, string $user_email = ''): string {
    $q_lower = strtolower(trim($q));
    $meals   = $ctx_data['platform_meals'] ?? 0;
    $cloth   = $ctx_data['platform_cloth'] ?? 0;
    $vols    = $ctx_data['platform_vols']  ?? 0;

    /* ── DONOR CONTEXT ── */
    if ($ctx_role === 'donor') {
        $my_food  = $ctx_data['food_donations'] ?? 0;
        $my_cloth = $ctx_data['cloth_donations'] ?? 0;
        $active   = $ctx_data['active'] ?? 0;
        $total    = $my_food + $my_cloth;

        if (preg_match('/my.*impact|how.*impact|impact.*so far|what.*achiev/', $q_lower))
            return "💚 **Your Personal Impact:**\n\n"
                 . "🍱 **$my_food** food donations — fed approx. " . ($my_food*15) . " people\n"
                 . "👕 **$my_cloth** clothing donations — helped approx. " . ($my_cloth*3) . " families\n"
                 . "🌍 Total **$total donations** — CO₂ saved: " . round(($my_food*2.5)+($my_cloth*1.8),1) . "kg\n"
                 . "💰 Economic value created: ₹" . number_format(($my_food*120)+($my_cloth*250)) . "\n\n"
                 . ($total === 0 ? "Make your first donation today! 🎁" : "Keep it up! You're making a real difference.");

        if (preg_match('/track|where.*donation|status/', $q_lower))
            return "📍 **Track your donations:**\n\nYou currently have **$active active donation(s)**.\n\nGo to **📍 Track Donations** in the sidebar to see the real-time 6-step timeline:\n\n1️⃣ Submitted → 2️⃣ Verified → 3️⃣ Scheduled → 4️⃣ Out for Pickup → 5️⃣ Picked Up → 6️⃣ Delivered\n\nEach step sends you an email notification. 📧";

        if (preg_match('/badge|reward|achievement/', $q_lower)) {
            $badges = $ctx_data['badges'] ?? [];
            if (!empty($badges)) {
                $b_str = implode(', ', array_map(fn($b)=>$b['badge_emoji'].' '.$b['badge_name'], $badges));
                return "🏅 **Your Badges:** $b_str\n\nKeep donating to unlock more badges! 10 donations = 💪 10 Strong, 50 meals = 🍽️ 50 Meals, etc.";
            }
            return "🏅 **Earn Badges** by donating!\n\n🌱 First Drop — 1st donation\n💪 10 Strong — 10 donations\n🏆 Impact Maker — 25 donations\n👑 SoulServe Legend — 50 donations\n\nStart earning today! 🎁";
        }

        if (preg_match('/recurring|schedule|regular/', $q_lower) && $user_email) {
            $rec = $ai_obj->suggestRecurring($user_email);
            if ($rec['has_pattern'])
                return "📅 **AI Smart Schedule Suggestion:**\n\nBased on your donation history, I suggest scheduling a **{$rec['frequency']} {$rec['pref_type']} donation** on **{$rec['best_day']}s**.\n\nYour average gap between donations is {$rec['avg_gap_days']} days.\n\nWant me to set up a reminder? Go to **Schedule Donation** in the sidebar.";
            return "📅 Make 2+ donations first to enable AI-powered recurring schedule suggestions!";
        }

        if (preg_match('/cause|what.*donate|should.*donate|recommend/', $q_lower) && $user_email) {
            $causes = $ai_obj->getPersonalizedCauses($user_email);
            if (!empty($causes)) {
                $c = $causes[0];
                return "🎯 **AI Recommended Cause for You:**\n\n{$c['icon']} **{$c['title']}** (Urgency: {$c['urgency']})\n\n{$c['desc']}\n\n👉 [{$c['action']}]({$c['url']})";
            }
        }

        if (preg_match('/report|monthly|summary|this month/', $q_lower) && $user_email) {
            $rep = $ai_obj->generateMonthlyReport($user_email);
            return "📊 **{$rep['month']} Impact Report:**\n\n"
                 . "🍱 Food donations: {$rep['food_count']} ({$rep['food_qty']} servings)\n"
                 . "👕 Clothing: {$rep['cloth_count']} ({$rep['cloth_qty']} pieces)\n"
                 . "👥 People helped: {$rep['people_fed']}\n"
                 . "🌿 CO₂ saved: {$rep['co2_saved']}kg\n"
                 . "💰 Value: ₹" . number_format($rep['eco_value']) . "\n\n"
                 . $rep['rank_msg'];
        }
    }

    /* ── VOLUNTEER CONTEXT ── */
    if ($ctx_role === 'volunteer') {
        $active  = $ctx_data['active_tasks'] ?? 0;
        $done    = $ctx_data['completed'] ?? 0;
        $pending = $ctx_data['pending_tasks'] ?? 0;
        $wl      = $ctx_data['workload'] ?? [];

        if (preg_match('/my.*task|assigned|pickup/', $q_lower))
            return "📦 **Your Task Summary:**\n\n✅ Completed: **$done deliveries**\n🔄 Active: **$active tasks**\n📋 Pending acceptance: **$pending requests**\n\n"
                 . ($active > 0 ? "You have active tasks — check your **Assigned Pickups** tab!" : "Your queue is clear. Ready for new assignments! 🎯")
                 . "\n\n🏆 Impact Level: **{$wl['level']}** {$wl['level_emoji']} (Score: {$wl['impact_score']}/100)";

        if (preg_match('/route|order|which.*first|priority/', $q_lower) && $user_email) {
            $route = $ai_obj->suggestPickupRoute($user_email);
            if (!empty($route['route'])) {
                $r_str = implode("\n", array_map(fn($r)=>"Stop {$r['stop']}: {$r['type']} ({$r['priority']} priority) — {$r['reason']}", $route['route']));
                return "🗺️ **AI Route Optimization:**\n\n$r_str\n\n💡 {$route['tip']}";
            }
            return "🗺️ No active tasks to route. Check back when tasks are assigned!";
        }

        if (preg_match('/performance|stats|score|level/', $q_lower)) {
            $lvl = $ctx_data['workload']['level'] ?? 'Newcomer';
            $sc  = $ctx_data['workload']['impact_score'] ?? 0;
            $cr  = $ctx_data['workload']['completion_rate'] ?? 0;
            return "📊 **Your Volunteer Stats:**\n\n🎯 Impact Score: **$sc/100**\n🏆 Level: **$lvl**\n✅ Completed: **$done tasks**\n📈 Acceptance Rate: **$cr%**\n\n{$wl['advice']}";
        }

        if (preg_match('/eta|time|when|how long/', $q_lower))
            return "⏱️ **ETA Estimation:**\nETA depends on your active task's priority:\n\n🔴 High priority food: ~30 min\n🟡 Medium: ~1.5 hours\n🟢 Low: ~3 hours\n\nCheck the **AI ETA** badge on each task card for a personalized estimate!";

        if (preg_match('/sos|emergency|urgent|help|danger/', $q_lower))
            return "🆘 **Emergency / SOS:**\n\nIf you're in danger or have an emergency during a pickup:\n\n📞 Call: +91 82379 17354\n📧 adhaarsoulserve@gmail.com\n\n**Immediately:**\n1. Stop and move to a safe location\n2. Call the admin number above\n3. Update donation status to note the issue\n\nYour safety comes first. ❤️";
    }

    /* ── SELLER CONTEXT ── */
    if ($ctx_role === 'seller') {
        $prods   = $ctx_data['products'] ?? 0;
        $orders  = $ctx_data['orders'] ?? 0;
        $pending = $ctx_data['pending'] ?? 0;
        $rev     = $ctx_data['revenue'] ?? 0;

        if (preg_match('/price|pricing|how much|cost/', $q_lower))
            return "💰 **AI Pricing Guide:**\n\nTo get a smart pricing suggestion for a specific product, visit:\n\n**My Products** → Click on a product → View AI Price Suggestion\n\nGenerally:\n📈 High rating (4.5+) → can price 10% above market avg\n📦 Low stock + high sales → slight premium justified\n💡 New product → match market average to build reviews first";

        if (preg_match('/demand|forecast|trend|which.*sell/', $q_lower) && $user_email) {
            $fc = $ai_obj->sellerDemandForecast($user_email);
            if (!empty($fc)) {
                $top3 = array_slice($fc, 0, 3);
                $f_str = implode("\n", array_map(fn($f)=>"• **{$f['category']}** (Score:{$f['score']}/100) — {$f['advice']}", $top3));
                return "📈 **AI Demand Forecast:**\n\n$f_str\n\n💡 Stock up on trending categories before demand peaks!";
            }
        }

        if (preg_match('/review|feedback|sentiment|customer/', $q_lower))
            return "⭐ **Review Analysis:**\nVisit **My Products** → Any product with reviews → AI will show sentiment analysis:\n\n🟢 Positive (≥70%) — keep quality up!\n🟡 Mixed (30-70%) — address negative feedback\n🔴 Negative (≤30%) — review quality/description\n\nResponding to reviews increases buyer trust by 40%.";

        if (preg_match('/revenue|earning|sale|profit|money/', $q_lower))
            return "💰 **Your Revenue:**\n\n🏪 **Active Products:** $prods\n🛒 **Total Orders:** $orders\n⏳ **Pending Orders:** $pending\n💵 **Total Revenue:** ₹" . number_format($rev, 0) . "\n\nTip: Products with 3+ photos sell 60% better. Make sure all listings have high-quality images!";

        if (preg_match('/description|write|generate|product.*text/', $q_lower))
            return "✍️ **AI Product Description Generator:**\n\nWhen adding a new product, click **\"🤖 Generate Description\"** and the AI will create a professional description based on:\n• Product name\n• Category\n• Price\n• Material/size attributes\n\nYou can always edit the generated text before publishing.";
    }

    /* ── UNIVERSAL ANSWERS ── */
    if (preg_match('/^(hi|hello|hey|namaste|namaskar)/', $q_lower))
        return "👋 Hello! I'm the **SoulServe AI Assistant** for your " . ucfirst($ctx_role) . " portal.\n\nI can help you with your tasks, analytics, and smart suggestions. What do you need?";

    if (preg_match('/contact|support|help|phone|email/', $q_lower))
        return "📞 **Contact SoulServe:**\n\n📧 adhaarsoulserve@gmail.com\n📞 +91 82379 17354\n📍 Kopargaon, Maharashtra\n🕐 Mon–Sat: 9AM–7PM IST";

    if (preg_match('/impact|platform.*stat|overall/', $q_lower))
        return "🌍 **Platform Impact:**\n\n🍱 Meals distributed: **$meals**\n👕 Clothing delivered: **$cloth**\n🤝 Active volunteers: **$vols**\n👥 People helped: **" . ($meals * 3) . "**\n🌿 CO₂ saved: **" . round(($meals*2.5)+($cloth*1.8),1) . "kg**";

    if (preg_match('/thank|thanks|great|awesome/', $q_lower))
        return "💚 You're welcome! You're making a real difference every day. Keep up the amazing work! 🌟";

    /* Gemini API fallback */
    if (defined('AI_FLASK_URL') && AI_FLASK_URL) {
        try {
            $gemini = ai_call('ai_chat', [
                'message'  => $q,
                'context'  => $ctx_role,
                'language' => 'en',
            ]);
            if ($gemini && !empty($gemini['reply'])) return $gemini['reply'];
        } catch (Throwable $e) {}
    }

    return "🤖 I understand you asked: \"$q\"\n\nI'm still learning! Here's what I can help you with:\n"
         . ($ctx_role==='donor'    ? "🎁 Your donations, impact, badges, monthly report, causes" : '')
         . ($ctx_role==='volunteer'? "📦 Your tasks, route, performance, ETA, emergency" : '')
         . ($ctx_role==='seller'   ? "🛍️ Products, pricing, demand forecast, reviews, revenue" : '')
         . "\n\nTry asking about one of those topics!";
}

$reply = getAIReply($message, $context, $ctx, $ai, $email ?? '');

// Convert **bold** markdown to <strong>
$reply = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $reply);
// Convert newlines to <br>
$reply = nl2br($reply);

echo json_encode([
    'ok'      => true,
    'reply'   => $reply,
    'context' => $context,
    'ts'      => date('c'),
]);
