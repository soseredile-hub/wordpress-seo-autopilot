<?php
defined('ABSPATH') || exit;

/**
 * V6_Language — Multi-language prompt & UI string engine.
 * Supports: it, en, ar, es, fr, de (extendable).
 */
class V6_Language {

    /** Get system prompt for article writing in target language */
    public static function article_system(string $lang, array $profile): string {
        $name     = $profile['name']     ?: get_bloginfo('name');
        $city     = $profile['city']     ?: '';
        $country  = $profile['country']  ?: '';
        $industry = $profile['industry'] ?: '';
        $location = trim("{$city}, {$country}", ', ');
        $currency = $profile['currency_symbol'] ?: '€';

        $base = [
            'it' => "Sei un copywriter SEO esperto per {$name}, azienda di {$industry} a {$location}. "
                  . "Scrivi in italiano. Usa prezzi in {$currency}. "
                  . "Ogni articolo deve essere pensato per il mercato locale di {$city}.",

            'en' => "You are an expert SEO copywriter for {$name}, a {$industry} company in {$location}. "
                  . "Write in English. Use prices in {$currency}. "
                  . "Every article should target the local market of {$city}.",

            'ar' => "أنت كاتب محتوى SEO متخصص لـ {$name}، شركة {$industry} في {$location}. "
                  . "اكتب بالعربية. استخدم الأسعار بـ {$currency}. "
                  . "كل مقال يجب أن يستهدف السوق المحلي في {$city}.",

            'es' => "Eres un copywriter SEO experto para {$name}, empresa de {$industry} en {$location}. "
                  . "Escribe en español. Usa precios en {$currency}. "
                  . "Cada artículo debe estar orientado al mercado local de {$city}.",

            'fr' => "Vous êtes un copywriter SEO expert pour {$name}, entreprise de {$industry} à {$location}. "
                  . "Écrivez en français. Utilisez les prix en {$currency}. "
                  . "Chaque article doit cibler le marché local de {$city}.",

            'de' => "Sie sind ein SEO-Texter für {$name}, ein {$industry}-Unternehmen in {$location}. "
                  . "Schreiben Sie auf Deutsch. Verwenden Sie Preise in {$currency}. "
                  . "Jeder Artikel soll den lokalen Markt von {$city} ansprechen.",
        ];

        return $base[$lang] ?? $base['en'];
    }

    /** Article user prompt with keyword */
    public static function article_user(string $lang, string $keyword, array $context): string {
        $name     = $context['name']     ?: '';
        $city     = $context['city']     ?: '';
        $industry = $context['industry'] ?: '';
        $currency = $context['currency_symbol'] ?: '€';
        $year     = date('Y');

        $templates = [
            'it' => "Scrivi un articolo SEO completo per la keyword: \"{$keyword}\"\n\n"
                  . "Azienda: {$name} | Settore: {$industry} | Città: {$city} | Anno: {$year}\n\n"
                  . "Rispondi SOLO con JSON:\n"
                  . "{\n"
                  . "  \"title\": \"titolo ottimizzato con keyword\",\n"
                  . "  \"seo_title\": \"max 60 caratteri\",\n"
                  . "  \"meta_description\": \"max 155 caratteri con keyword\",\n"
                  . "  \"slug\": \"url-friendly-con-keyword\",\n"
                  . "  \"excerpt\": \"riassunto 2 righe\",\n"
                  . "  \"content_html\": \"HTML completo con H2, paragrafi, lista prezzi {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"prompt foto reale 1\",\"prompt foto reale 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",

            'en' => "Write a complete SEO article for the keyword: \"{$keyword}\"\n\n"
                  . "Company: {$name} | Industry: {$industry} | City: {$city} | Year: {$year}\n\n"
                  . "Reply ONLY with JSON:\n"
                  . "{\n"
                  . "  \"title\": \"optimized title with keyword\",\n"
                  . "  \"seo_title\": \"max 60 chars\",\n"
                  . "  \"meta_description\": \"max 155 chars with keyword\",\n"
                  . "  \"slug\": \"url-friendly-with-keyword\",\n"
                  . "  \"excerpt\": \"2-line summary\",\n"
                  . "  \"content_html\": \"full HTML with H2, paragraphs, price list {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"real photo prompt 1\",\"real photo prompt 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",

            'ar' => "اكتب مقالاً SEO كاملاً للكلمة المفتاحية: \"{$keyword}\"\n\n"
                  . "الشركة: {$name} | المجال: {$industry} | المدينة: {$city} | السنة: {$year}\n\n"
                  . "أجب فقط بـ JSON:\n"
                  . "{\n"
                  . "  \"title\": \"عنوان محسّن يحتوي على الكلمة المفتاحية\",\n"
                  . "  \"seo_title\": \"أقصى 60 حرف\",\n"
                  . "  \"meta_description\": \"أقصى 155 حرف مع الكلمة المفتاحية\",\n"
                  . "  \"slug\": \"url-friendly-ar\",\n"
                  . "  \"excerpt\": \"ملخص سطرين\",\n"
                  . "  \"content_html\": \"HTML كامل مع H2 وفقرات وقائمة أسعار {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"وصف صورة حقيقية 1\",\"وصف صورة حقيقية 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",

            'es' => "Escribe un artículo SEO completo para la keyword: \"{$keyword}\"\n\n"
                  . "Empresa: {$name} | Sector: {$industry} | Ciudad: {$city} | Año: {$year}\n\n"
                  . "Responde SOLO con JSON:\n"
                  . "{\n"
                  . "  \"title\": \"título optimizado con keyword\",\n"
                  . "  \"seo_title\": \"max 60 caracteres\",\n"
                  . "  \"meta_description\": \"max 155 caracteres con keyword\",\n"
                  . "  \"slug\": \"url-amigable-con-keyword\",\n"
                  . "  \"excerpt\": \"resumen 2 líneas\",\n"
                  . "  \"content_html\": \"HTML completo con H2, párrafos, lista precios {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"prompt foto real 1\",\"prompt foto real 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",

            'fr' => "Écrivez un article SEO complet pour le mot-clé: \"{$keyword}\"\n\n"
                  . "Entreprise: {$name} | Secteur: {$industry} | Ville: {$city} | Année: {$year}\n\n"
                  . "Répondez UNIQUEMENT avec du JSON:\n"
                  . "{\n"
                  . "  \"title\": \"titre optimisé avec mot-clé\",\n"
                  . "  \"seo_title\": \"max 60 caractères\",\n"
                  . "  \"meta_description\": \"max 155 caractères avec mot-clé\",\n"
                  . "  \"slug\": \"url-convivial-avec-mot-cle\",\n"
                  . "  \"excerpt\": \"résumé 2 lignes\",\n"
                  . "  \"content_html\": \"HTML complet avec H2, paragraphes, liste prix {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"prompt photo réelle 1\",\"prompt photo réelle 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",

            'de' => "Schreiben Sie einen vollständigen SEO-Artikel für das Keyword: \"{$keyword}\"\n\n"
                  . "Unternehmen: {$name} | Branche: {$industry} | Stadt: {$city} | Jahr: {$year}\n\n"
                  . "Antworten Sie NUR mit JSON:\n"
                  . "{\n"
                  . "  \"title\": \"optimierter Titel mit Keyword\",\n"
                  . "  \"seo_title\": \"max 60 Zeichen\",\n"
                  . "  \"meta_description\": \"max 155 Zeichen mit Keyword\",\n"
                  . "  \"slug\": \"url-freundlich-mit-keyword\",\n"
                  . "  \"excerpt\": \"2-Zeilen-Zusammenfassung\",\n"
                  . "  \"content_html\": \"vollständiges HTML mit H2, Absätzen, Preisliste {$currency}\",\n"
                  . "  \"faq\": [{\"question\":\"...\",\"answer\":\"...\"}],\n"
                  . "  \"image_prompts\": [\"echtes Foto-Prompt 1\",\"echtes Foto-Prompt 2\"],\n"
                  . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\"],\n"
                  . "  \"price_range\": {\"low\":\"XXX\",\"high\":\"XXXX\",\"currency\":\"{$currency}\"}\n"
                  . "}",
        ];

        return $templates[$lang] ?? $templates['en'];
    }

    /** FAQ section title per language */
    public static function faq_title(string $lang): string {
        return [
            'it' => 'Domande Frequenti',
            'en' => 'Frequently Asked Questions',
            'ar' => 'الأسئلة الشائعة',
            'es' => 'Preguntas Frecuentes',
            'fr' => 'Questions Fréquentes',
            'de' => 'Häufig gestellte Fragen',
        ][$lang] ?? 'FAQ';
    }

    /** "Why choose us" box title */
    public static function why_us_title(string $lang, string $name): string {
        return [
            'it' => "Perché scegliere {$name}?",
            'en' => "Why choose {$name}?",
            'ar' => "لماذا تختار {$name}؟",
            'es' => "¿Por qué elegir {$name}?",
            'fr' => "Pourquoi choisir {$name} ?",
            'de' => "Warum {$name} wählen?",
        ][$lang] ?? "Why choose {$name}?";
    }

    /** "Sources" accordion label */
    public static function sources_label(string $lang): string {
        return [
            'it' => '📚 Fonti ufficiali',
            'en' => '📚 Official sources',
            'ar' => '📚 المصادر الرسمية',
            'es' => '📚 Fuentes oficiales',
            'fr' => '📚 Sources officielles',
            'de' => '📚 Offizielle Quellen',
        ][$lang] ?? '📚 Sources';
    }

    /** Reading time label */
    public static function reading_time(string $lang, int $minutes): string {
        return [
            'it' => "{$minutes} min lettura",
            'en' => "{$minutes} min read",
            'ar' => "{$minutes} دقيقة قراءة",
            'es' => "{$minutes} min lectura",
            'fr' => "{$minutes} min de lecture",
            'de' => "{$minutes} Min. Lesen",
        ][$lang] ?? "{$minutes} min";
    }

    /** Verified badge prefix */
    public static function verified(string $lang): string {
        return [
            'it' => 'Verificato da',
            'en' => 'Verified by',
            'ar' => 'معتمد من',
            'es' => 'Verificado por',
            'fr' => 'Vérifié par',
            'de' => 'Geprüft von',
        ][$lang] ?? 'Verified by';
    }

    /** Price table headers */
    public static function price_tiers(string $lang): array {
        return [
            'it' => ['Base', 'Standard', 'Premium'],
            'en' => ['Basic', 'Standard', 'Premium'],
            'ar' => ['أساسي', 'قياسي', 'مميز'],
            'es' => ['Básico', 'Estándar', 'Premium'],
            'fr' => ['Basique', 'Standard', 'Premium'],
            'de' => ['Basis', 'Standard', 'Premium'],
        ][$lang] ?? ['Basic', 'Standard', 'Premium'];
    }

    /** Supported languages list for settings dropdown */
    public static function supported(): array {
        return [
            'it' => '🇮🇹 Italiano',
            'en' => '🇬🇧 English',
            'ar' => '🇸🇦 العربية',
            'es' => '🇪🇸 Español',
            'fr' => '🇫🇷 Français',
            'de' => '🇩🇪 Deutsch',
        ];
    }
}
