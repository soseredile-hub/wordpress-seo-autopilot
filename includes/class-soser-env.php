<?php
/**
 * SOSER SEO Autopilot — Environment & Configuration Loader
 * 
 * يقوم بتحميل ملف .env وتوفير دوال للوصول إلى متغيرات البيئة والإعدادات
 * Loads .env file and provides functions to access environment and settings
 * 
 * @package SOSER_SEO_Autopilot
 * @version 5.10.0
 */

defined('ABSPATH') || exit;

class SOSER_Env {
    
    /**
     * متغيرات البيئة المحملة
     * Loaded environment variables
     */
    private static $vars = [];
    
    /**
     * الإعدادات الافتراضية
     * Default settings
     */
    private static $config = [];
    
    /**
     * هل تم التحميل؟
     * Is loaded?
     */
    private static $loaded = false;
    
    /**
     * تحميل ملف .env
     * Load .env file
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }
        
        $env_file = SOSER_V4_DIR . '.env';
        
        if (!file_exists($env_file)) {
            error_log('[SOSER] ⚠️ .env file not found at ' . $env_file);
            self::$loaded = true;
            return;
        }
        
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // تخطي التعليقات
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // إزالة الاقتباسات
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            self::$vars[$key] = $value;
        }
        
        // تحميل الإعدادات الافتراضية
        self::load_defaults();
        
        self::$loaded = true;
    }
    
    /**
     * تحميل الإعدادات الافتراضية
     * Load default settings
     */
    private static function load_defaults() {
        $config_file = SOSER_V4_DIR . 'config/default-settings.php';
        
        if (file_exists($config_file)) {
            self::$config = include $config_file;
        }
    }
    
    /**
     * الحصول على قيمة متغير البيئة
     * Get environment variable value
     * 
     * @param string $key اسم المتغير
     * @param mixed $default القيمة الافتراضية
     * @return mixed
     */
    public static function get($key, $default = null) {
        self::load();
        
        // البحث في متغيرات البيئة أولاً
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }
        
        // ثم البحث في متغيرات النظام
        if (function_exists('getenv')) {
            $env_value = getenv($key);
            if ($env_value !== false) {
                return $env_value;
            }
        }
        
        // ثم البحث في الثوابت
        if (defined($key)) {
            return constant($key);
        }
        
        return $default;
    }
    
    /**
     * الحصول على إعداد من الإعدادات الافتراضية
     * Get setting from default config
     * 
     * @param string $path المسار (مثل: openai.api_key)
     * @param mixed $default القيمة الافتراضية
     * @return mixed
     */
    public static function config($path, $default = null) {
        self::load();
        
        $keys = explode('.', $path);
        $value = self::$config;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        
        return $value;
    }
    
    /**
     * التحقق من وجود متغير بيئة
     * Check if environment variable exists
     * 
     * @param string $key اسم المتغير
     * @return bool
     */
    public static function has($key) {
        self::load();
        return isset(self::$vars[$key]) || getenv($key) !== false || defined($key);
    }
    
    /**
     * تعيين متغير بيئة
     * Set environment variable
     * 
     * @param string $key اسم المتغير
     * @param mixed $value القيمة
     */
    public static function set($key, $value) {
        self::$vars[$key] = $value;
    }
    
    /**
     * الحصول على جميع متغيرات البيئة
     * Get all environment variables
     * 
     * @return array
     */
    public static function all() {
        self::load();
        return self::$vars;
    }
    
    /**
     * الحصول على جميع الإعدادات
     * Get all settings
     * 
     * @return array
     */
    public static function all_config() {
        self::load();
        return self::$config;
    }
}

// ════════════════════════════════════════════════════════════════
// Global Helper Functions
// ════════════════════════════════════════════════════════════════

/**
 * دالة مساعدة للحصول على متغير بيئة
 * Helper function to get environment variable
 * 
 * @param string $key اسم المتغير
 * @param mixed $default القيمة الافتراضية
 * @return mixed
 */
function soser_env($key, $default = null) {
    return SOSER_Env::get($key, $default);
}

/**
 * دالة مساعدة للحصول على إعداد
 * Helper function to get setting
 * 
 * @param string $path المسار (مثل: openai.api_key)
 * @param mixed $default القيمة الافتراضية
 * @return mixed
 */
function soser_config($path, $default = null) {
    return SOSER_Env::config($path, $default);
}

/**
 * التحقق من أن API مفعّل (توفير مفتاح صحيح)
 * Check if API is enabled (has valid key)
 * 
 * @param string $api اسم الـ API (openai, google, bing, etc)
 * @return bool
 */
function soser_is_api_enabled($api) {
    switch ($api) {
        case 'openai':
            return !empty(soser_env('OPENAI_API_KEY'));
        case 'google':
            return !empty(soser_env('GOOGLE_CLIENT_ID')) && !empty(soser_env('GOOGLE_CLIENT_SECRET'));
        case 'bing':
            return !empty(soser_env('BING_SEARCH_API_KEY'));
        case 'unsplash':
            return !empty(soser_env('UNSPLASH_API_KEY'));
        default:
            return false;
    }
}

/**
 * تسجيل رسالة في السجل
 * Log message to file
 * 
 * @param string $message الرسالة
 * @param string $level المستوى (info, warning, error, debug)
 */
function soser_log($message, $level = 'info') {
    $log_file = soser_config('logging.file', WP_CONTENT_DIR . '/logs/soser-seo.log');
    $log_level = soser_config('logging.level', 'info');
    
    // تحديد مستويات السجلات
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    
    // تخطي السجلات الأقل أهمية من المستوى المحدد
    if ($levels[$level] < $levels[$log_level]) {
        return;
    }
    
    // إنشاء مجلد السجلات إن لم يكن موجوداً
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    $timestamp = current_time('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // تسجيل أيضاً في سجل WordPress إذا كان Debug مفعّلاً
    if (soser_config('logging.debug_mode')) {
        error_log("[SOSER {$level}] {$message}");
    }
}

/**
 * إرسال إشعار (بريد / Slack / Discord)
 * Send notification
 * 
 * @param string $subject الموضوع
 * @param string $message الرسالة
 * @param array $options الخيارات
 */
function soser_notify($subject, $message, $options = []) {
    // إرسال بريد
    if (soser_config('notifications.send_emails')) {
        $to = soser_config('notifications.email', get_option('admin_email'));
        wp_mail($to, $subject, $message);
    }
    
    // إرسال Slack
    if (!empty(soser_config('notifications.slack_webhook'))) {
        // يمكن إضافة تكامل Slack هنا
    }
    
    // إرسال Discord
    if (!empty(soser_config('notifications.discord_webhook'))) {
        // يمكن إضافة تكامل Discord هنا
    }
}

// تحميل البيئة تلقائياً عند تضمين هذا الملف
SOSER_Env::load();
