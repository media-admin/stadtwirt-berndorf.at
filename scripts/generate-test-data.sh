#!/bin/bash

###############################################
# Test-Daten Generator für WordPress
# 
# Generiert Test-Daten für alle CPTs
###############################################

set -e

echo "🧪 Starte Test-Daten Generierung..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cd cms

###############################################
# 1. BLOG POSTS
###############################################

echo ""
echo "📝 Erstelle Blog Posts..."

wp post create \
  --post_type=post \
  --post_title="Die Zukunft des Web Developments" \
  --post_content="WordPress entwickelt sich ständig weiter. Mit modernen Build-Tools wie Vite und React können wir heute leistungsstarke und schnelle Websites erstellen. Die Integration von Headless CMS-Lösungen eröffnet völlig neue Möglichkeiten." \
  --post_status=publish \
  --post_category=1 \
  --user="media-admin"

wp post create \
  --post_type=post \
  --post_title="10 Tipps für bessere WordPress Performance" \
  --post_content="Performance ist entscheidend für den Erfolg einer Website. Hier sind unsere Top 10 Tipps: Caching aktivieren, Bilder optimieren, CDN nutzen, Lazy Loading implementieren, Plugins minimieren, und vieles mehr." \
  --post_status=publish \
  --post_category=1 \
  --user="media-admin"

wp post create \
  --post_type=post \
  --post_title="E-Commerce Trends 2026" \
  --post_content="Der E-Commerce Markt wächst stetig. Mobile Shopping, Social Commerce, und KI-gestützte Personalisierung sind die großen Trends. WooCommerce bietet die perfekte Plattform für moderne Online-Shops." \
  --post_status=publish \
  --post_category=1 \
  --user="media-admin"

wp post create \
  --post_type=post \
  --post_title="WordPress Security Best Practices" \
  --post_content="Sicherheit sollte höchste Priorität haben. Regelmäßige Updates, starke Passwörter, 2FA, Security Plugins, und regelmäßige Backups sind essentiell für eine sichere WordPress Installation." \
  --post_status=publish \
  --post_category=1 \
  --user="media-admin"

wp post create \
  --post_type=post \
  --post_title="Moderne CSS Techniken für WordPress" \
  --post_content="CSS Grid und Flexbox haben die Art und Weise revolutioniert, wie wir Layouts erstellen. Custom Properties (CSS Variables) ermöglichen dynamische Themes. Container Queries sind die Zukunft des Responsive Designs." \
  --post_status=publish \
  --post_category=1 \
  --user="media-admin"

###############################################
# 2. TEAM MEMBERS
###############################################

echo ""
echo "👥 Erstelle Team Members..."

wp post create \
  --post_type=team \
  --post_title="Max Mustermann" \
  --post_content="Max ist unser erfahrener Lead Developer mit über 10 Jahren Erfahrung in der WordPress-Entwicklung." \
  --post_status=publish \
  --meta_input='{"role":"CEO & Lead Developer","email":"max@example.com","phone":"+43 123 456 789","display_order":"1"}' \
  --user="media-admin"

wp post create \
  --post_type=team \
  --post_title="Anna Schmidt" \
  --post_content="Anna ist unsere kreative UI/UX Designerin, die für benutzerfreundliche und ansprechende Designs sorgt." \
  --post_status=publish \
  --meta_input='{"role":"UI/UX Designer","email":"anna@example.com","phone":"+43 123 456 790","display_order":"2"}' \
  --user="media-admin"

wp post create \
  --post_type=team \
  --post_title="Peter Müller" \
  --post_content="Peter ist unser Frontend-Spezialist mit Fokus auf moderne JavaScript-Frameworks und Performance-Optimierung." \
  --post_status=publish \
  --meta_input='{"role":"Frontend Developer","email":"peter@example.com","phone":"+43 123 456 791","display_order":"3"}' \
  --user="media-admin"

wp post create \
  --post_type=team \
  --post_title="Sarah Weber" \
  --post_content="Sarah managt unsere Projekte und sorgt dafür, dass alles termingerecht und im Budget geliefert wird." \
  --post_status=publish \
  --meta_input='{"role":"Project Manager","email":"sarah@example.com","phone":"+43 123 456 792","display_order":"4"}' \
  --user="media-admin"

wp post create \
  --post_type=team \
  --post_title="Thomas Klein" \
  --post_content="Thomas ist unser Backend-Experte und kümmert sich um Datenbankoptimierung und Server-Administration." \
  --post_status=publish \
  --meta_input='{"role":"Backend Developer","email":"thomas@example.com","phone":"+43 123 456 793","display_order":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=team \
  --post_title="Lisa Wagner" \
  --post_content="Lisa ist unsere Marketing-Spezialistin und optimiert Websites für Suchmaschinen und Conversions." \
  --post_status=publish \
  --meta_input='{"role":"Marketing Manager","email":"lisa@example.com","phone":"+43 123 456 794","display_order":"6"}' \
  --user="media-admin"

###############################################
# 3. PROJECTS
###############################################

echo ""
echo "💼 Erstelle Projects..."

wp post create \
  --post_type=project \
  --post_title="Corporate Website Redesign" \
  --post_content="Komplettes Redesign der Unternehmenswebsite mit modernem Design, verbesserter UX und Performance-Optimierung." \
  --post_status=publish \
  --meta_input='{"client":"TechCorp GmbH","project_date":"15/01/2026","project_url":"https://example.com","technologies":"WordPress, React, WooCommerce"}' \
  --user="media-admin"

wp post create \
  --post_type=project \
  --post_title="E-Commerce Platform Launch" \
  --post_content="Entwicklung einer vollständigen E-Commerce Plattform mit WooCommerce, Payment-Integration und Custom Checkout." \
  --post_status=publish \
  --meta_input='{"client":"Fashion Store AG","project_date":"01/12/2025","project_url":"https://example.com","technologies":"WooCommerce, Stripe, Custom Theme"}' \
  --user="media-admin"

wp post create \
  --post_type=project \
  --post_title="Restaurant Booking System" \
  --post_content="Maßgeschneidertes Buchungssystem für Restaurants mit Online-Reservierung, Tischverwaltung und Zahlungsintegration." \
  --post_status=publish \
  --meta_input='{"client":"Bella Italia Restaurant","project_date":"20/11/2025","project_url":"https://example.com","technologies":"WordPress, Custom Plugin, API Integration"}' \
  --user="media-admin"

wp post create \
  --post_type=project \
  --post_title="Real Estate Portal" \
  --post_content="Immobilienportal mit erweiterten Such- und Filterfunktionen, Kartenintegration und Lead-Management." \
  --post_status=publish \
  --meta_input='{"client":"Prime Properties","project_date":"05/10/2025","project_url":"https://example.com","technologies":"WordPress, Google Maps API, Custom CPT"}' \
  --user="media-admin"

wp post create \
  --post_type=project \
  --post_title="Educational Platform" \
  --post_content="E-Learning Plattform mit Kursmanagement, Video-Hosting, Quiz-System und Zertifikaten." \
  --post_status=publish \
  --meta_input='{"client":"Learn Academy","project_date":"15/09/2025","project_url":"https://example.com","technologies":"LearnDash, Video Integration, Custom Reports"}' \
  --user="media-admin"

###############################################
# 4. TESTIMONIALS
###############################################

echo ""
echo "⭐ Erstelle Testimonials..."

wp post create \
  --post_type=testimonial \
  --post_title="Hervorragende Arbeit!" \
  --post_content="Das Team hat unsere Erwartungen übertroffen. Die Website ist nicht nur optisch ansprechend, sondern auch extrem performant. Wir sind sehr zufrieden mit dem Ergebnis!" \
  --post_status=publish \
  --meta_input='{"author_name":"Michael Bauer","company":"TechCorp GmbH","position":"CEO","rating":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=testimonial \
  --post_title="Professionell und zuverlässig" \
  --post_content="Von der Planung bis zur Umsetzung lief alles reibungslos. Das Team war immer erreichbar und hat auf alle unsere Wünsche eingehen können. Absolut empfehlenswert!" \
  --post_status=publish \
  --meta_input='{"author_name":"Julia Fischer","company":"Fashion Store AG","position":"Marketing Director","rating":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=testimonial \
  --post_title="Beste Investition" \
  --post_content="Die neue Website hat unsere Online-Buchungen um 150% gesteigert. Das System ist intuitiv und unsere Kunden lieben es. Danke für die großartige Arbeit!" \
  --post_status=publish \
  --meta_input='{"author_name":"Antonio Rossi","company":"Bella Italia Restaurant","position":"Inhaber","rating":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=testimonial \
  --post_title="Kompetent und kreativ" \
  --post_content="Die Zusammenarbeit war hervorragend. Das Team hat innovative Lösungen für unsere speziellen Anforderungen gefunden. Wir freuen uns auf weitere Projekte!" \
  --post_status=publish \
  --meta_input='{"author_name":"Sandra Hoffmann","company":"Prime Properties","position":"Geschäftsführerin","rating":"5"}' \
  --user="media-admin"

###############################################
# 5. SERVICES
###############################################

echo ""
echo "🛠️ Erstelle Services..."

wp post create \
  --post_type=service \
  --post_title="WordPress Development" \
  --post_content="Professionelle WordPress-Entwicklung mit modernen Technologien. Von Custom Themes bis zu komplexen Plugins - wir setzen Ihre Ideen um." \
  --post_status=publish \
  --meta_input='{"icon":"dashicons-wordpress","price":"ab 2.500€","features":"[{\"feature_text\":\"Custom Theme Development\"},{\"feature_text\":\"Plugin Development\"},{\"feature_text\":\"API Integration\"},{\"feature_text\":\"Performance Optimization\"}]"}' \
  --user="media-admin"

wp post create \
  --post_type=service \
  --post_title="E-Commerce Solutions" \
  --post_content="Komplette E-Commerce Lösungen mit WooCommerce. Von der Produktverwaltung bis zur Payment-Integration - alles aus einer Hand." \
  --post_status=publish \
  --meta_input='{"icon":"dashicons-cart","price":"ab 3.500€","features":"[{\"feature_text\":\"WooCommerce Setup\"},{\"feature_text\":\"Payment Integration\"},{\"feature_text\":\"Inventory Management\"},{\"feature_text\":\"Custom Checkout\"}]"}' \
  --user="media-admin"

wp post create \
  --post_type=service \
  --post_title="UI/UX Design" \
  --post_content="Ansprechendes und benutzerfreundliches Design. Wir gestalten Interfaces, die Ihre Nutzer lieben werden." \
  --post_status=publish \
  --meta_input='{"icon":"dashicons-art","price":"ab 1.500€","features":"[{\"feature_text\":\"User Research\"},{\"feature_text\":\"Wireframing\"},{\"feature_text\":\"Visual Design\"},{\"feature_text\":\"Prototyping\"}]"}' \
  --user="media-admin"

wp post create \
  --post_type=service \
  --post_title="SEO & Marketing" \
  --post_content="Optimierung für Suchmaschinen und erfolgreiche Marketing-Strategien. Mehr Sichtbarkeit, mehr Conversions." \
  --post_status=publish \
  --meta_input='{"icon":"dashicons-chart-line","price":"ab 800€/Monat","features":"[{\"feature_text\":\"Technical SEO\"},{\"feature_text\":\"Content Optimization\"},{\"feature_text\":\"Link Building\"},{\"feature_text\":\"Analytics & Reporting\"}]"}' \
  --user="media-admin"

###############################################
# 6. FAQs
###############################################

echo ""
echo "❓ Erstelle FAQs..."

wp post create \
  --post_type=faq \
  --post_title="Was kostet eine WordPress Website?" \
  --post_status=publish \
  --meta_input='{"answer":"Die Kosten variieren je nach Umfang und Anforderungen. Eine einfache Website beginnt bei ca. 2.500€, während komplexere Projekte mit Custom Funktionalitäten zwischen 5.000€ und 15.000€ kosten können. Wir erstellen gerne ein individuelles Angebot basierend auf Ihren Anforderungen.","display_order":"1"}' \
  --user="media-admin"

wp post create \
  --post_type=faq \
  --post_title="Wie lange dauert die Entwicklung?" \
  --post_status=publish \
  --meta_input='{"answer":"Die Entwicklungszeit hängt vom Projektumfang ab. Eine Standard-Website dauert typischerweise 4-6 Wochen, während größere Projekte 2-3 Monate oder länger in Anspruch nehmen können. Wir erstellen zu Beginn einen detaillierten Zeitplan.","display_order":"2"}' \
  --user="media-admin"

wp post create \
  --post_type=faq \
  --post_title="Bieten Sie auch Wartung und Support an?" \
  --post_status=publish \
  --meta_input='{"answer":"Ja, wir bieten verschiedene Wartungspakete an. Diese umfassen regelmäßige Updates, Backups, Security-Monitoring, Performance-Optimierung und technischen Support. Sie können zwischen monatlichen und jährlichen Paketen wählen.","display_order":"3"}' \
  --user="media-admin"

wp post create \
  --post_type=faq \
  --post_title="Kann ich meine Website selbst pflegen?" \
  --post_status=publish \
  --meta_input='{"answer":"Absolut! Wir entwickeln alle Websites mit benutzerfreundlichen Content-Management-Systemen. Nach Fertigstellung bieten wir ein ausführliches Training, damit Sie Inhalte selbst aktualisieren können. Bei Fragen stehen wir natürlich jederzeit zur Verfügung.","display_order":"4"}' \
  --user="media-admin"

wp post create \
  --post_type=faq \
  --post_title="Ist meine Website auch mobilfreundlich?" \
  --post_status=publish \
  --meta_input='{"answer":"Ja, alle unsere Websites sind vollständig responsive und für alle Geräte optimiert. Wir testen auf verschiedenen Smartphones, Tablets und Desktop-Größen, um sicherzustellen, dass Ihre Website überall perfekt aussieht und funktioniert.","display_order":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=faq \
  --post_title="Helfen Sie auch bei SEO und Marketing?" \
  --post_status=publish \
  --meta_input='{"answer":"Ja, wir bieten umfassende SEO-Dienstleistungen an. Von technischer Optimierung über Content-Strategie bis hin zu laufenden Marketing-Kampagnen - wir helfen Ihnen, online gefunden zu werden und Ihre Zielgruppe zu erreichen.","display_order":"6"}' \
  --user="media-admin"

###############################################
# 7. HERO SLIDES
###############################################

echo ""
echo "🎬 Erstelle Hero Slides..."

wp post create \
  --post_type=hero_slide \
  --post_title="Willkommen bei unserem WordPress Studio" \
  --post_content="Wir entwickeln moderne, performante und benutzerfreundliche Websites mit WordPress" \
  --post_status=publish \
  --meta_input='{"subtitle":"Professionelle WordPress Development","button_text":"Unsere Services","button_url":"/services","button_style":"primary","text_color":"light","overlay_opacity":"30"}' \
  --user="media-admin"

wp post create \
  --post_type=hero_slide \
  --post_title="E-Commerce Lösungen" \
  --post_content="Verkaufen Sie online mit WooCommerce - der führenden E-Commerce Plattform" \
  --post_status=publish \
  --meta_input='{"subtitle":"Leistungsstarke Online-Shops","button_text":"Mehr erfahren","button_url":"/woocommerce","button_style":"primary","text_color":"light","overlay_opacity":"40"}' \
  --user="media-admin"

wp post create \
  --post_type=hero_slide \
  --post_title="Unsere Expertise" \
  --post_content="Über 50 erfolgreiche Projekte und zufriedene Kunden" \
  --post_status=publish \
  --meta_input='{"subtitle":"Erfahrung seit 2015","button_text":"Portfolio ansehen","button_url":"/portfolio","button_style":"secondary","text_color":"light","overlay_opacity":"35"}' \
  --user="media-admin"

###############################################
# 8. CAROUSEL ITEMS
###############################################

echo ""
echo "🎠 Erstelle Carousel Items..."

wp post create \
  --post_type=carousel \
  --post_title="Moderne Technologien" \
  --post_content="Wir nutzen die neuesten Technologien für beste Performance" \
  --post_status=publish \
  --meta_input='{"subtitle":"Cutting-Edge Development","link_url":"/technologie","link_target":"_self","show_overlay":"1","display_order":"1"}' \
  --user="media-admin"

wp post create \
  --post_type=carousel \
  --post_title="Agile Entwicklung" \
  --post_content="Schnelle Iterationen und regelmäßiges Feedback" \
  --post_status=publish \
  --meta_input='{"subtitle":"Flexible Workflows","link_url":"/prozess","link_target":"_self","show_overlay":"1","display_order":"2"}' \
  --user="media-admin"

wp post create \
  --post_type=carousel \
  --post_title="24/7 Support" \
  --post_content="Wir sind immer für Sie da, wenn Sie uns brauchen" \
  --post_status=publish \
  --meta_input='{"subtitle":"Zuverlässiger Service","link_url":"/support","link_target":"_self","show_overlay":"1","display_order":"3"}' \
  --user="media-admin"

wp post create \
  --post_type=carousel \
  --post_title="Performance First" \
  --post_content="Blitzschnelle Ladezeiten für beste User Experience" \
  --post_status=publish \
  --meta_input='{"subtitle":"Optimierte Websites","link_url":"/performance","link_target":"_self","show_overlay":"1","display_order":"4"}' \
  --user="media-admin"

wp post create \
  --post_type=carousel \
  --post_title="Security & Backups" \
  --post_content="Ihre Daten sind bei uns sicher" \
  --post_status=publish \
  --meta_input='{"subtitle":"Höchste Sicherheit","link_url":"/sicherheit","link_target":"_self","show_overlay":"1","display_order":"5"}' \
  --user="media-admin"

wp post create \
  --post_type=carousel \
  --post_title="Responsive Design" \
  --post_content="Perfekt auf allen Geräten" \
  --post_status=publish \
  --meta_input='{"subtitle":"Mobile-First Approach","link_url":"/design","link_target":"_self","show_overlay":"1","display_order":"6"}' \
  --user="media-admin"

###############################################
# 9. PAGES
###############################################

echo ""
echo "📄 Erstelle Test-Seiten..."

# Beispiel-Seite mit Hero Slider
wp post create \
  --post_type=page \
  --post_title="Startseite Demo" \
  --post_content='[hero_slider_query limit="3" autoplay="true" delay="5000" loop="true"]

<h2>Unsere Services</h2>
[pricing_tables columns="3"]
[pricing_table title="Basic" price="29€" period="pro Monat" features="5 Seiten, 1 GB Speicher, Email Support" button_text="Jetzt starten" button_url="/kontakt"]
[pricing_table title="Professional" price="79€" period="pro Monat" features="20 Seiten, 10 GB Speicher, Priority Support, Custom Design" button_text="Jetzt starten" button_url="/kontakt" featured="true"]
[pricing_table title="Enterprise" price="199€" period="pro Monat" features="Unlimited Seiten, 100 GB Speicher, 24/7 Support, Dedicated Manager" button_text="Kontakt" button_url="/kontakt"]
[/pricing_tables]

<h2>Unser Team</h2>
[team_query limit="6" columns="3"]

<h2>Statistiken</h2>
[stats columns="3"]
[stat number="150" suffix="+" label="Projekte" icon="dashicons-portfolio"]
[stat number="50" suffix="+" label="Kunden" icon="dashicons-groups"]
[stat number="99.9" suffix="%" label="Uptime" icon="dashicons-cloud"]
[/stats]

<h2>FAQ</h2>
[faq_accordion limit="6"]' \
  --post_status=publish \
  --user="media-admin"

# Blog-Archiv Seite
wp post create \
  --post_type=page \
  --post_title="Blog" \
  --post_content='<h1>Unser Blog</h1>
<p>Aktuelle Artikel und Neuigkeiten aus der Welt des Web Developments.</p>

[posts_load_more post_type="post" posts_per_page="6" columns="3" template="card"]' \
  --post_status=publish \
  --user="media-admin"

# Team Seite
wp post create \
  --post_type=page \
  --post_title="Unser Team" \
  --post_content='<h1>Lernen Sie unser Team kennen</h1>
<p>Wir sind ein engagiertes Team von WordPress-Experten.</p>

[posts_load_more post_type="team" posts_per_page="6" columns="3" template="team"]' \
  --post_status=publish \
  --user="media-admin"

# Portfolio Seite
wp post create \
  --post_type=page \
  --post_title="Portfolio" \
  --post_content='<h1>Unsere Projekte</h1>
<p>Eine Auswahl unserer erfolgreichen Projekte.</p>

[posts_load_more post_type="project" posts_per_page="6" columns="3" template="project"]' \
  --post_status=publish \
  --user="media-admin"

# Suche Seite
wp post create \
  --post_type=page \
  --post_title="Suche" \
  --post_content='<h1>Suche</h1>

[ajax_search post_types="post,page,team,project,faq" placeholder="Wonach suchen Sie?" limit="15"]

<h2>Aktuelle Blog Posts</h2>
[posts_load_more post_type="post" posts_per_page="6" columns="3" template="card"]' \
  --post_status=publish \
  --user="media-admin"

###############################################
# FERTIG
###############################################

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Test-Daten erfolgreich generiert!"
echo ""
echo "📊 Übersicht:"
echo "   - 5 Blog Posts"
echo "   - 6 Team Members"
echo "   - 5 Projects"
echo "   - 4 Testimonials"
echo "   - 4 Services"
echo "   - 6 FAQs"
echo "   - 3 Hero Slides"
echo "   - 6 Carousel Items"
echo "   - 5 Test-Seiten"
echo ""
echo "🌐 Besuche die Seiten im Frontend:"
echo "   - Startseite Demo"
echo "   - Blog"
echo "   - Unser Team"
echo "   - Portfolio"
echo "   - Suche"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cd ..