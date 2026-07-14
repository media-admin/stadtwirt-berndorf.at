<?php

namespace FluentCrm\App\Models;

/**
 *  UrlStores Model - DB Model for Short Urls
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */
class UrlStores extends Model
{
    protected $table = 'fc_url_stores';

    protected $guarded = ['id'];

    public static function getUrlSlug($longUrl)
    {
        // Normalize URL before lookup and storage
        $longUrl = str_replace("\xE2\x80\x8B", '', $longUrl);
        $longUrl = htmlspecialchars_decode($longUrl);

        static $urls = [];

        $cacheKey = md5($longUrl);

        if (isset($urls[$cacheKey])) {
            return $urls[$cacheKey];
        }

        $isExist = self::where('url', $longUrl)->first();

        if ($isExist) {
            $urls[$cacheKey] = $isExist->short;
            return $isExist->short;
        }

        $maxRetries = 3;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $short = self::generateRandomSlug();
            try {
                self::insert([
                    'url'        => $longUrl,
                    'short'      => $short,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]);
                $urls[$cacheKey] = $short;
                return $short;
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        return '';
    }

    public static function generateRandomSlug($length = 6)
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charsLen = strlen($chars);

        $slug = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $slug .= $chars[ord($bytes[$i]) % $charsLen];
        }

        return $slug;
    }

    public static function getRowByShort($short)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "fc_url_stores WHERE BINARY `short` = %s ORDER BY `id` DESC LIMIT 1", $short));
    }
}
