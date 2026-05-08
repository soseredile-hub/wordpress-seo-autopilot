<?php
defined('ABSPATH') || exit;

/**
 * V13 Feature 20: Cost Optimization Engine
 * Tracks spending, caches API calls, enforces daily budget.
 * MUST be loaded first — wraps all API calls.
 */
class V5_Cost {

    const OPT = 'soser_v5_cost';

    // Per 1K tokens (USD)
    const RATES = [
        'gpt-4.1-mini'           => ['in'=>0.0004, 'out'=>0.0016],
        'gpt-4.1'                => ['in'=>0.003,  'out'=>0.012],
        'text-embedding-3-small' => ['in'=>0.00002,'out'=>0],
        'gpt-image-1'            => ['img'=>0.04],
    ];

    public static function track(string $model, int $in, int $out = 0, int $imgs = 0): float {
        $rates = self::RATES[$model] ?? self::RATES['gpt-4.1-mini'];
        $cost  = isset($rates['img'])
            ? $imgs * $rates['img']
            : ($in/1000*$rates['in']) + ($out/1000*$rates['out']);

        $usage = get_option(self::OPT, []);
        $today = date('Y-m-d');
        if (!isset($usage[$today])) $usage[$today] = ['usd'=>0,'calls'=>0,'tokens'=>0];
        $usage[$today]['usd']    += $cost;
        $usage[$today]['calls']  += 1;
        $usage[$today]['tokens'] += $in + $out;
        $usage = array_slice($usage, -30, 30, true);
        update_option(self::OPT, $usage);
        return $cost;
    }

    public static function today_spend(): float {
        $u = get_option(self::OPT, []);
        return (float)($u[date('Y-m-d')]['usd'] ?? 0);
    }

    public static function can_spend(): bool {
        $opts   = V4_Options::get();
        $budget = (float)($opts['cost_budget_daily'] ?? 0);
        return $budget <= 0 || self::today_spend() < $budget;
    }

    public static function stats(int $days = 7): array {
        $usage  = get_option(self::OPT, []);
        $result = [];
        for ($i=$days-1;$i>=0;$i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date'=>$d,'usd'=>round($usage[$d]['usd']??0,4),'calls'=>$usage[$d]['calls']??0,'tokens'=>$usage[$d]['tokens']??0];
        }
        return $result;
    }

    /** Cache-first API call — saves tokens & money */
    public static function cached_call(array $messages, string $model, int $max_tokens, int $cache_hours = 24): string {
        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return '';

        $hash   = 'v5cc_' . md5(wp_json_encode($messages) . $model);
        $cached = get_transient($hash);
        if ($cached !== false) return $cached;

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => ['Authorization'=>'Bearer '.$opts['openai_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['model'=>$model,'max_tokens'=>$max_tokens,'messages'=>$messages]),
        ]);

        if (is_wp_error($r)) return '';
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        $u    = $body['usage'] ?? [];
        self::track($model, $u['prompt_tokens']??0, $u['completion_tokens']??0);
        if ($text) set_transient($hash, $text, $cache_hours * HOUR_IN_SECONDS);
        return $text;
    }
}

/**
 * V13 Feature 18: AI Analytics Dashboard
 * Aggregates: rankings, semantic coverage, CTR, cost, content score.
 */
class V5_Analytics {

    public static function report(): array {
        $cached = get_transient('v5_analytics');
        if ($cached) return $cached;

        $memory  = V5_Memory::get_all();
        $stats   = V5_Memory::stats();
        $costs   = V5_Cost::stats(30);

        $total_clicks = array_sum(array_column($memory,'gsc_clicks'));
        $total_impr   = array_sum(array_column($memory,'gsc_impr'));
        $avg_ctr      = $total_impr>0 ? round($total_clicks/$total_impr*100,2) : 0;
        $positions    = array_filter(array_column($memory,'gsc_position'));
        $avg_position = count($positions) ? round(array_sum($positions)/count($positions),1) : 0;

        $top = array_filter($memory, fn($m) => $m['gsc_clicks']>0);
        usort($top, fn($a,$b) => $b['gsc_clicks']<=>$a['gsc_clicks']);

        $report = [
            'generated_at' => current_time('mysql'),
            'content' => [
                'articles'       => (int)($stats['total']??0),
                'total_words'    => (int)($stats['words']??0),
                'avg_seo_score'  => round((float)($stats['avg_score']??0),1),
                'topics'         => count(array_unique(array_filter(array_column($memory,'topic')))),
                'needs_refresh'  => (int)($stats['to_refresh']??0),
            ],
            'performance' => [
                'total_clicks'      => $total_clicks,
                'total_impressions' => $total_impr,
                'avg_ctr'           => $avg_ctr.'%',
                'avg_position'      => $avg_position,
                'top_articles'      => array_slice(array_values($top), 0, 5),
            ],
            'cost' => [
                'today_usd'    => round(V5_Cost::today_spend(), 4),
                'last_30d_usd' => round(array_sum(array_column($costs,'usd')),4),
                'budget'       => V4_Options::get()['cost_budget_daily'] ?? '5.00',
                'daily_chart'  => $costs,
            ],
            'alerts' => self::alerts($memory, $stats),
        ];

        set_transient('v5_analytics', $report, HOUR_IN_SECONDS);
        return $report;
    }

    private static function alerts(array $memory, array $stats): array {
        $alerts = [];
        $opts   = V4_Options::get();
        $spend  = V5_Cost::today_spend();
        $budget = (float)($opts['cost_budget_daily']??0);

        if ($budget>0 && $spend>=$budget*0.8)
            $alerts[] = ['type'=>'warning','msg'=>"💰 Spesa oggi: \${$spend} (80%+ del budget)"];
        if ((int)($stats['to_refresh']??0)>3)
            $alerts[] = ['type'=>'info','msg'=>(int)$stats['to_refresh'].' articoli da aggiornare'];
        $no_gsc = count(array_filter($memory, fn($m)=>$m['gsc_impr']==0));
        if ($no_gsc>5 && !V4_GSC::is_connected())
            $alerts[] = ['type'=>'info','msg'=>"Collega Search Console per dati reali"];

        return $alerts;
    }
}

/**
 * V13 Feature 19: Behavioral SEO
 * Tracks user engagement and suggests content improvements.
 */
class V5_Behavioral {

    /** Inject lightweight JS tracker (no external service) */
    public static function get_tracker_js(): string {
        return "<script>
(function(){
  var d=document,s=0,e=0,b=0,t=Date.now();
  d.addEventListener('scroll',function(){s=Math.round(window.scrollY/(d.body.scrollHeight-window.innerHeight)*100);},{ passive:true });
  d.addEventListener('click',function(ev){if(ev.target.tagName==='A')e++;});
  window.addEventListener('beforeunload',function(){
    var td=Math.round((Date.now()-t)/1000);
    if(td<3)return;
    navigator.sendBeacon && navigator.sendBeacon('".admin_url('admin-ajax.php')."',new URLSearchParams({
      action:'v5_track',nonce:'".wp_create_nonce('v5_track')."',
      pid:'".get_the_ID()."',scroll:s,clicks:e,time:td
    }));
  });
})();
</script>";
    }

    /** Save behavioral data */
    public static function save_signal(int $post_id, int $scroll, int $clicks, int $time_sec): void {
        $key  = 'v5_behavior_' . $post_id;
        $data = get_post_meta($post_id, '_v5_behavior', true) ?: ['views'=>0,'avg_scroll'=>0,'avg_time'=>0,'avg_clicks'=>0];
        $n    = $data['views'] + 1;
        $data['views']      = $n;
        $data['avg_scroll'] = round(($data['avg_scroll']*($n-1) + $scroll)/$n, 1);
        $data['avg_time']   = round(($data['avg_time']*($n-1) + $time_sec)/$n, 1);
        $data['avg_clicks'] = round(($data['avg_clicks']*($n-1) + $clicks)/$n, 1);
        update_post_meta($post_id, '_v5_behavior', $data);
    }

    /** Check if article needs improvement based on behavior */
    public static function needs_improvement(int $post_id): bool {
        $data = get_post_meta($post_id, '_v5_behavior', true);
        if (!$data || $data['views'] < 10) return false;
        return $data['avg_scroll'] < 40  // People leave before reading
            || $data['avg_time'] < 30;   // Less than 30 seconds
    }
}
