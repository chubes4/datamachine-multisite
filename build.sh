#!/bin/bash
# Build script for dm-multisite WordPress plugin
# Creates production-ready ZIP file in /build directory

set -e

PLUGIN_SLUG="dm-multisite"
BUILD_DIR="build"
DIST_FILE="${BUILD_DIR}/${PLUGIN_SLUG}.zip"

echo "Building ${PLUGIN_SLUG}..."

# Clean previous build
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Copy plugin files to build directory
echo "Copying plugin files..."
rsync -av --exclude-from='.buildignore' \
    --exclude="${BUILD_DIR}" \
    --exclude='.DS_Store' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='build.sh' \
    ./ "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Verify plugin file exists
if [ ! -f "${BUILD_DIR}/${PLUGIN_SLUG}/${PLUGIN_SLUG}.php" ]; then
    echo "Error: Main plugin file not found in build"
    exit 1
fi

# Create ZIP file
echo "Creating ZIP archive..."
cd "${BUILD_DIR}"
zip -r "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}" -q

# Verify ZIP was created
if [ -f "${PLUGIN_SLUG}.zip" ]; then
    echo "✓ Build complete: ${BUILD_DIR}/${PLUGIN_SLUG}.zip"
    echo "  Plugin: ${PLUGIN_SLUG}"
    echo "  Ready for network activation"
else
    echo "Error: ZIP file creation failed"
    exit 1
fi

cd ..
