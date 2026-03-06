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
 * Upgrade-Pfad (empfohlen für Agenturen mit vielen SVG-Uploads):
 *   composer require enshrined/svg-sanitize
 *   Dann MediaLab_SVG_Sanitizer::sanitize() durch Sanitizer::sanitize() ersetzen.
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

    // Nochmals sicherstellen: nur Administratoren
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
     * Alles was nicht hier drin ist wird entfernt.
     */
    private static $allowed_tags = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop',
        'clipPath', 'mask', 'pattern', 'symbol', 'marker',
        'filter', 'feBlend', 'feColorMatrix', 'feComposite', 'feConvolveMatrix',
        'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feFlood',
        'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology',
        'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight',
        'feTile', 'feTurbulence',
        'image', 'a', 'switch', 'use',
    ];

    /**
     * Verbotene Tags – werden komplett mit Inhalt entfernt
     */
    private static $forbidden_tags = [
        'script', 'style',  // XSS
        'foreignObject',     // HTML-Injection
        'animate', 'animateMotion', 'animateTransform', 'set', // CSS-Animations mit JS-Payload
        'handler', 'listener',
    ];

    /**
     * Erlaubte Protokolle in href/xlink:href/src
     */
    private static $allowed_protocols = ['http', 'https', 'data:image/', '#'];

    /**
     * Haupt-Methode
     *
     * @param string $svg Raw SVG content
     * @return string|false Sanitierter SVG oder false bei ungültigem Dokument
     */
    public static function sanitize(string $svg) {
        // PHP-Tags entfernen (Sicherheit vor <?php-Injection)
        $svg = preg_replace('/<\?(?!xml).*?\?>/s', '', $svg);

        // XML laden
        $previous_errors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDATTR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);

        if (!$loaded) {
            return false;
        }

        // Root-Element muss <svg> sein
        $root = $dom->documentElement;
        if (!$root || strtolower($root->nodeName) !== 'svg') {
            return false;
        }

        // XML-Processing-Instructions entfernen
        self::remove_processing_instructions($dom);

        // Rekursiv alle Knoten bereinigen
        self::clean_node($dom->documentElement);

        // Serialisieren
        $clean = $dom->saveXML($dom->documentElement);

        // Finale Regex-Bereinigung für Edge Cases
        $clean = self::final_cleanup($clean);

        return $clean;
    }

    /**
     * Processing-Instructions entfernen (<?xml-stylesheet type="text/css" ...?>)
     */
    private static function remove_processing_instructions(DOMDocument $dom): void {
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//processing-instruction()') as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Einzelnen Knoten und seine Kinder bereinigen
     */
    private static function clean_node(DOMNode $node): void {
        $to_remove = [];

        foreach ($node->childNodes as $child) {
            // Kommentare entfernen (können conditional IE-Code enthalten)
            if ($child->nodeType === XML_COMMENT_NODE) {
                $to_remove[] = $child;
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->localName);

            // Verbotene Tags komplett entfernen (inkl. Kinder)
            if (in_array($tag, self::$forbidden_tags, true)) {
                $to_remove[] = $child;
                continue;
            }

            // Nicht in Allowlist → entfernen
            if (!in_array($tag, self::$allowed_tags, true)) {
                $to_remove[] = $child;
                continue;
            }

            // Attribute bereinigen
            self::clean_attributes($child);

            // Rekursiv
            self::clean_node($child);
        }

        foreach ($to_remove as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Attribute eines Elements bereinigen
     */
    private static function clean_attributes(DOMElement $element): void {
        $to_remove = [];

        foreach ($element->attributes as $attr) {
            $name  = strtolower($attr->localName);
            $value = $attr->value;

            // on*-Event-Handler entfernen (onclick, onload, onmouseover, ...)
            if (strpos($name, 'on') === 0) {
                $to_remove[] = $attr->name;
                continue;
            }

            // Verbotene Attribute
            if (in_array($name, ['formaction', 'action', 'method', 'srcdoc'], true)) {
                $to_remove[] = $attr->name;
                continue;
            }

            // URL-Attribute prüfen
            if (in_array($name, ['href', 'xlink:href', 'src', 'action', 'formaction'], true)) {
                if (!self::is_safe_url($value)) {
                    $to_remove[] = $attr->name;
                    continue;
                }
            }

            // CSS-Werte in style-Attribut prüfen
            if ($name === 'style') {
                $clean_style = self::sanitize_style($value);
                $element->setAttribute('style', $clean_style);
                continue;
            }

            // javascript: in beliebigen Attributen
            if (preg_match('/javascript\s*:/i', $value)) {
                $to_remove[] = $attr->name;
                continue;
            }

            // expression() in CSS-Werten (IE-Legacy-XSS)
            if (preg_match('/expression\s*\(/i', $value)) {
                $to_remove[] = $attr->name;
                continue;
            }
        }

        foreach ($to_remove as $attr_name) {
            $element->removeAttribute($attr_name);
        }

        // <use> href nur auf interne Referenzen (#id) beschränken
        if (strtolower($element->localName) === 'use') {
            foreach (['href', 'xlink:href'] as $attr) {
                $val = $element->getAttribute($attr);
                if ($val && strpos($val, '#') !== 0) {
                    $element->removeAttribute($attr);
                }
            }
        }
    }

    /**
     * Style-Attribut bereinigen
     */
    private static function sanitize_style(string $style): string {
        // javascript: und expression() entfernen
        $style = preg_replace('/javascript\s*:/i', '', $style);
        $style = preg_replace('/expression\s*\(/i', '', $style);
        $style = preg_replace('/url\s*\(\s*["\']?\s*javascript/i', '', $style);
        $style = preg_replace('/-moz-binding\s*:/i', '', $style);
        return $style;
    }

    /**
     * URL-Sicherheitsprüfung
     */
    private static function is_safe_url(string $url): bool {
        $url = trim($url);

        // Interne Referenzen (#id) immer erlaubt
        if (strpos($url, '#') === 0) {
            return true;
        }

        // javascript: und vbscript: blockieren
        $normalized = strtolower(preg_replace('/[\x00-\x1f\s]/u', '', $url));
        if (preg_match('/^(javascript|vbscript|data(?!:image\/))/i', $normalized)) {
            return false;
        }

        // data: nur für Bilder erlauben (data:image/png;base64,...)
        if (preg_match('/^data:/i', $url)) {
            return (bool) preg_match('/^data:image\/(png|jpg|jpeg|gif|webp|svg\+xml);base64,/i', $url);
        }

        return true;
    }

    /**
     * Finale Regex-Bereinigung für Edge Cases die DOMDocument durchlässt
     */
    private static function final_cleanup(string $svg): string {
        // Nochmals on*-Handler (DOMDocument kann manches durchlassen)
        $svg = preg_replace('/\bon\w+\s*=\s*(["\'])[^"\']*\1/i', '', $svg);
        $svg = preg_replace('/\bon\w+\s*=\s*[^\s>]+/i', '', $svg);

        // javascript: in allen Attributen
        $svg = preg_replace('/(\w+\s*=\s*["\'][^"\']*)\bjavascript\s*:[^"\']*(["\'])/i', '$1$2', $svg);

        return $svg;
    }
}
