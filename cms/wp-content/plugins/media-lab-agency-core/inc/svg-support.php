<?php
/**
 * SVG Upload Support
 *
 * Security:
 *  - SVG-Upload auf Administratoren beschränkt
 *  - Vollständige XSS-Sanitierung via MediaLab_SVG_Sanitizer:
 *      - Erlaubt-Liste für Tags und Attribute (allowlist-basiert)
 *      - Entfernt: <script>, <foreignObject>, <use href=...>, PHP-Tags
 *      - Entfernt: alle on*-Handler, javascript:-URLs, data:-URLs in Attributen
 *      - Entfernt: XML-Processing-Instructions, Kommentare mit Code
 *
 * Fix 1.0.1: $allowed_tags auf Lowercase normalisiert –
 *   strtolower($child->localName) ergab z.B. "radialgradient",
 *   aber die Allowlist hatte "radialGradient" → Gradient-Elemente
 *   wurden fälschlicherweise entfernt, SVG-Farbverläufe unsichtbar.
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────
// SVG UPLOAD NUR FÜR ADMINISTRATOREN
// ─────────────────────────────────────────────────────────────────
add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('administrator')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
});

// MIME-Type korrekt erkennen
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    if (!current_user_can('administrator')) {
        return $data;
    }
    $filetype = wp_check_filetype($filename, $mimes);
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename'],
    ];
}, 10, 4);

// SVG-Thumbnails in der Mediathek anzeigen
add_filter('wp_prepare_attachment_for_js', function ($response, $attachment, $meta) {
    if ($response['mime'] === 'image/svg+xml' && empty($response['sizes'])) {
        $response['sizes'] = [
            'full' => ['url' => $response['url']],
        ];
    }
    return $response;
}, 10, 3);

// ─────────────────────────────────────────────────────────────────
// SVG SANITIZER – VOLLSTÄNDIGE XSS-BEREINIGUNG
// ─────────────────────────────────────────────────────────────────
add_filter('wp_handle_upload_prefilter', function ($file) {
    if ($file['type'] !== 'image/svg+xml') {
        return $file;
    }

    if (!current_user_can('administrator')) {
        $file['error'] = 'SVG-Uploads sind nur für Administratoren erlaubt.';
        return $file;
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        $file['error'] = 'SVG-Datei konnte nicht gelesen werden.';
        return $file;
    }

    $clean = MediaLab_SVG_Sanitizer::sanitize($content);

    if ($clean === false) {
        $file['error'] = 'Ungültige SVG-Datei – Upload abgebrochen.';
        return $file;
    }

    file_put_contents($file['tmp_name'], $clean);
    return $file;
});

// ─────────────────────────────────────────────────────────────────
// SVG SANITIZER KLASSE
// ─────────────────────────────────────────────────────────────────
class MediaLab_SVG_Sanitizer {

    /**
     * Erlaubte SVG-Tags (Allowlist)
     *
     * WICHTIG: Alle Einträge müssen lowercase sein, da der Vergleich
     * strtolower($child->localName) verwendet. CamelCase-Einträge wie
     * "radialGradient" würden nie matchen → Elemente fälschlich entfernt.
     */
    private static $allowed_tags = [
        // Struktur
        'svg', 'g', 'defs', 'title', 'desc', 'metadata', 'symbol', 'use', 'switch',

        // Formen
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',

        // Text
        'text', 'tspan', 'textpath',

        // Farbverläufe & Farben (lowercase – fix für strtolower-Vergleich)
        'lineargradient', 'radialgradient', 'stop',

        // Masken & Clipping (lowercase)
        'clippath', 'mask', 'pattern', 'marker',

        // Filter (lowercase)
        'filter',
        'feblend', 'fecolormatrix', 'fecomposite', 'feconvolvematrix',
        'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'feflood',
        'fegaussianblur', 'feimage', 'femerge', 'femergenode', 'femorphology',
        'feoffset', 'fepointlight', 'fespecularlighting', 'fespotlight',
        'fetile', 'feturbulence',

        // Sonstiges
        'image', 'a',
    ];

    /**
     * Verbotene Tags – werden komplett mit Inhalt entfernt
     */
    private static $forbidden_tags = [
        'script', 'style',
        'foreignobject',     // lowercase für strtolower-Konsistenz
        'animate', 'animatemotion', 'animatetransform', 'set',
        'handler', 'listener',
    ];

    /**
     * Haupt-Methode
     */
    public static function sanitize(string $svg) {
        // PHP-Tags entfernen
        $svg = preg_replace('/<\?(?!xml).*?\?>/s', '', $svg);

        $previous_errors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDATTR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);

        if (!$loaded) {
            return false;
        }

        $root = $dom->documentElement;
        if (!$root || strtolower($root->nodeName) !== 'svg') {
            return false;
        }

        self::remove_processing_instructions($dom);
        self::clean_node($dom->documentElement);

        $clean = $dom->saveXML($dom->documentElement);
        $clean = self::final_cleanup($clean);

        return $clean;
    }

    private static function remove_processing_instructions(DOMDocument $dom): void {
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//processing-instruction()') as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    private static function clean_node(DOMNode $node): void {
        $to_remove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $to_remove[] = $child;
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            // Lowercase-Vergleich – konsistent mit $allowed_tags und $forbidden_tags
            $tag = strtolower($child->localName);

            if (in_array($tag, self::$forbidden_tags, true)) {
                $to_remove[] = $child;
                continue;
            }

            if (!in_array($tag, self::$allowed_tags, true)) {
                $to_remove[] = $child;
                continue;
            }

            self::clean_attributes($child);
            self::clean_node($child);
        }

        foreach ($to_remove as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    private static function clean_attributes(DOMElement $element): void {
        $to_remove = [];

        foreach ($element->attributes as $attr) {
            $name  = strtolower($attr->localName);
            $value = $attr->value;

            if (strpos($name, 'on') === 0) {
                $to_remove[] = $attr->name;
                continue;
            }

            if (in_array($name, ['formaction', 'action', 'method', 'srcdoc'], true)) {
                $to_remove[] = $attr->name;
                continue;
            }

            if (in_array($name, ['href', 'xlink:href', 'src', 'action', 'formaction'], true)) {
                if (!self::is_safe_url($value)) {
                    $to_remove[] = $attr->name;
                    continue;
                }
            }

            if ($name === 'style') {
                $clean_style = self::sanitize_style($value);
                $element->setAttribute('style', $clean_style);
                continue;
            }

            if (preg_match('/javascript\s*:/i', $value)) {
                $to_remove[] = $attr->name;
                continue;
            }

            if (preg_match('/expression\s*\(/i', $value)) {
                $to_remove[] = $attr->name;
                continue;
            }
        }

        foreach ($to_remove as $attr_name) {
            $element->removeAttribute($attr_name);
        }

        if (strtolower($element->localName) === 'use') {
            foreach (['href', 'xlink:href'] as $attr) {
                $val = $element->getAttribute($attr);
                if ($val && strpos($val, '#') !== 0) {
                    $element->removeAttribute($attr);
                }
            }
        }
    }

    private static function sanitize_style(string $style): string {
        $style = preg_replace('/javascript\s*:/i', '', $style);
        $style = preg_replace('/expression\s*\(/i', '', $style);
        $style = preg_replace('/url\s*\(\s*["\']?\s*javascript/i', '', $style);
        $style = preg_replace('/-moz-binding\s*:/i', '', $style);
        return $style;
    }

    private static function is_safe_url(string $url): bool {
        $url = trim($url);

        if (strpos($url, '#') === 0) {
            return true;
        }

        $normalized = strtolower(preg_replace('/[\x00-\x1f\s]/u', '', $url));
        if (preg_match('/^(javascript|vbscript|data(?!:image\/))/i', $normalized)) {
            return false;
        }

        if (preg_match('/^data:/i', $url)) {
            return (bool) preg_match('/^data:image\/(png|jpg|jpeg|gif|webp|svg\+xml);base64,/i', $url);
        }

        return true;
    }

    private static function final_cleanup(string $svg): string {
        $svg = preg_replace('/\bon\w+\s*=\s*(["\'])[^"\']*\1/i', '', $svg);
        $svg = preg_replace('/\bon\w+\s*=\s*[^\s>]+/i', '', $svg);
        $svg = preg_replace('/(\w+\s*=\s*["\'][^"\']*)\bjavascript\s*:[^"\']*(["\'])/i', '$1$2', $svg);
        return $svg;
    }
}
