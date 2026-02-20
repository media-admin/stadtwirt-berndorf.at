#!/bin/bash

echo "🗄️  Starting database optimization..."

cd cms

# Delete revisions
echo "→ Deleting old revisions..."
wp post delete $(wp post list --post_type='revision' --format=ids) --force 2>/dev/null || echo "  No revisions to delete"

# Delete auto-drafts
echo "→ Deleting auto-drafts..."
wp post delete $(wp post list --post_status='auto-draft' --format=ids) --force 2>/dev/null || echo "  No auto-drafts to delete"

# Delete spam comments
echo "→ Deleting spam comments..."
wp comment delete $(wp comment list --status=spam --format=ids) --force 2>/dev/null || echo "  No spam comments"

# Delete trash comments
echo "→ Deleting trashed comments..."
wp comment delete $(wp comment list --status=trash --format=ids) --force 2>/dev/null || echo "  No trashed comments"

# Delete expired transients
echo "→ Deleting expired transients..."
wp transient delete --expired

# Optimize database
echo "→ Optimizing database tables..."
wp db optimize

cd ..

echo "✅ Database optimization complete!"