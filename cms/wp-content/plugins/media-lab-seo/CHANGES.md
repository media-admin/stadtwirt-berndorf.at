# Bing Webmaster Tools – Integration

## class-seo.php
Neu: Bing-Meta-Tag direkt nach dem GSC-Tag:
```php
$bing = get_option( 'mlt_bing_verification', '' );
// ...
if ( $bing ) {
    echo '<meta name="msvalidate.01" content="' . esc_attr( $content ) . '">' . "\n";
}
```

## class-settings.php
1. In register_settings() ergänzen:
   'mlt_bing_verification' zur $options-Liste hinzufügen

2. Im SEO-Card nach dem GSC-Feld einfügen:
   <div class="mlt-field"> ... mlt_bing_verification ... </div>

## Anleitung Bing Webmaster Tools

1. https://www.bing.com/webmasters aufrufen und mit Microsoft-Konto anmelden
2. "Meine Website hinzufügen" → URL eintragen → "Hinzufügen"
3. Verifizierungsmethode wählen: "Meta-Tag"
4. Den Wert aus dem content-Attribut kopieren:
   <meta name="msvalidate.01" content="HIER_DEN_WERT_KOPIEREN">
5. Wert in WP-Admin → SEO Toolkit → SEO → Bing Webmaster Tools eintragen
6. Speichern → dann in Bing Webmaster Tools auf "Überprüfen" klicken

Tipp: GSC-Property kann via "Aus GSC importieren" in Bing direkt übernommen werden –
dann entfällt der manuelle Sitemap-Upload.
