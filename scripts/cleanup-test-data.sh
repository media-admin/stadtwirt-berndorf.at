#!/bin/bash

###############################################
# Test-Daten Cleanup
# 
# ACHTUNG: Löscht ALLE Posts in den CPTs!
###############################################

set -e

echo "⚠️  WARNUNG: Dies löscht ALLE Test-Daten!"
echo ""
read -p "Zum Fortfahren 'DELETE' eingeben: " CONFIRM

if [ "$CONFIRM" != "DELETE" ]; then
    echo "❌ Abgebrochen"
    exit 0
fi

echo ""
echo "🗑️  Lösche Test-Daten..."

cd cms

# Lösche Posts
wp post delete $(wp post list --post_type=post --format=ids) --force
wp post delete $(wp post list --post_type=team --format=ids) --force
wp post delete $(wp post list --post_type=project --format=ids) --force
wp post delete $(wp post list --post_type=testimonial --format=ids) --force
wp post delete $(wp post list --post_type=service --format=ids) --force
wp post delete $(wp post list --post_type=faq --format=ids) --force
wp post delete $(wp post list --post_type=hero_slide --format=ids) --force
wp post delete $(wp post list --post_type=carousel --format=ids) --force
wp post delete $(wp post list --post_type=page --format=ids) --force

echo ""
echo "✅ Alle Test-Daten gelöscht!"

cd ..