#!/bin/bash

# ============================================
# PROJECT SETUP SCRIPT
# ============================================

echo "🚀 Media Lab - Projekt Setup"
echo ""

# Projekt Name abfragen
read -p "Projekt Name (z.B. Stadtwirt Berndorf): " PROJECT_NAME
read -p "Theme Slug (z.B. stadtwirt-theme): " THEME_SLUG
read -p "Plugin Slug (z.B. stadtwirt-plugin): " PLUGIN_SLUG
read -p "Text Domain (z.B. stadtwirt): " TEXT_DOMAIN

echo ""
echo "─────────────────────────────────────"
echo "Projekt:      $PROJECT_NAME"
echo "Theme Slug:   $THEME_SLUG"
echo "Plugin Slug:  $PLUGIN_SLUG"
echo "Text Domain:  $TEXT_DOMAIN"
echo "─────────────────────────────────────"
read -p "Korrekt? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Abgebrochen"
    exit 1
fi

# ─── THEME ───────────────────────────────
THEME_PATH="cms/wp-content/themes"
mv "$THEME_PATH/custom-theme" "$THEME_PATH/$THEME_SLUG"

sed -i '' "s/Theme Name: Media Lab Custom Theme/Theme Name: $PROJECT_NAME Theme/" "$THEME_PATH/$THEME_SLUG/style.css"
sed -i '' "s/Text Domain: media-lab-theme/Text Domain: $TEXT_DOMAIN/" "$THEME_PATH/$THEME_SLUG/style.css"
sed -i '' "s/load_theme_textdomain('media-lab-theme'/load_theme_textdomain('$TEXT_DOMAIN'/" "$THEME_PATH/$THEME_SLUG/functions.php"

if [ -f "$THEME_PATH/$THEME_SLUG/package.json" ]; then
    sed -i '' "s/\"name\": \"custom-theme\"/\"name\": \"$THEME_SLUG\"/" "$THEME_PATH/$THEME_SLUG/package.json"
fi

echo "✅ Theme umbenannt: $THEME_SLUG"

# ─── PLUGIN ──────────────────────────────
PLUGIN_PATH="cms/wp-content/plugins"
mv "$PLUGIN_PATH/media-lab-project-starter" "$PLUGIN_PATH/$PLUGIN_SLUG"

# Plugin Haupt-Datei umbenennen
mv "$PLUGIN_PATH/$PLUGIN_SLUG/media-lab-project-starter.php" "$PLUGIN_PATH/$PLUGIN_SLUG/$PLUGIN_SLUG.php"

# Plugin Header
sed -i '' "s/Plugin Name: Media Lab Project Starter/Plugin Name: $PROJECT_NAME Plugin/" "$PLUGIN_PATH/$PLUGIN_SLUG/$PLUGIN_SLUG.php"
sed -i '' "s/Text Domain: media-lab-project/Text Domain: $TEXT_DOMAIN/" "$PLUGIN_PATH/$PLUGIN_SLUG/$PLUGIN_SLUG.php"
sed -i '' "s/media-lab-project-starter/$PLUGIN_SLUG/g" "$PLUGIN_PATH/$PLUGIN_SLUG/$PLUGIN_SLUG.php"

echo "✅ Plugin umbenannt: $PLUGIN_SLUG"

echo ""
echo "✨ Setup abgeschlossen!"
echo ""
echo "Nächste Schritte:"
echo "1. cd cms"
echo "2. wp theme activate $THEME_SLUG"
echo "3. wp plugin activate $PLUGIN_SLUG"
