#!/bin/bash

# Data Machine Multisite WordPress Plugin Build Script
# Creates production-ready zip file for WordPress installation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_FILE="datamachine-multisite.php"
BUILD_DIR="dist"
TEMP_DIR="$BUILD_DIR/temp"
BUILDIGNORE_FILE=".buildignore"

echo -e "${GREEN}🔨 Starting Data Machine Multisite build process...${NC}"

# Check if we're in the right directory
if [[ ! -f "$PLUGIN_FILE" ]]; then
    echo -e "${RED}❌ Error: $PLUGIN_FILE not found. Make sure you're in the plugin root directory.${NC}"
    exit 1
fi

# Check if .buildignore exists
if [[ ! -f "$BUILDIGNORE_FILE" ]]; then
    echo -e "${RED}❌ Error: $BUILDIGNORE_FILE not found. Build exclusion rules are required.${NC}"
    exit 1
fi

# Check if rsync is available
if ! command -v rsync &> /dev/null; then
    echo -e "${RED}❌ Error: rsync is not installed. This is required for proper file filtering.${NC}"
    exit 1
fi

# Extract version from plugin file
VERSION=$(grep "Version:" $PLUGIN_FILE | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
if [[ -z "$VERSION" ]]; then
    echo -e "${RED}❌ Error: Could not extract version from $PLUGIN_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Building version: $VERSION${NC}"

# Clean dist directory
echo -e "${YELLOW}🧹 Cleaning dist directory...${NC}"
rm -rf "$BUILD_DIR"
mkdir -p "$TEMP_DIR/datamachine-multisite"

# Copy plugin files using rsync with .buildignore exclusions
echo -e "${YELLOW}📋 Copying plugin files (excluding development files)...${NC}"
rsync -av --exclude-from="$BUILDIGNORE_FILE" . "$TEMP_DIR/datamachine-multisite/"

# Validate essential files exist
echo -e "${YELLOW}✅ Validating build...${NC}"
REQUIRED_FILES=(
    "$TEMP_DIR/datamachine-multisite/datamachine-multisite.php"
    "$TEMP_DIR/datamachine-multisite/README.md"
    "$TEMP_DIR/datamachine-multisite/inc/MultisiteLocalSearch.php"
    "$TEMP_DIR/datamachine-multisite/inc/MultisiteSiteContext.php"
    "$TEMP_DIR/datamachine-multisite/inc/ToolRegistry.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -e "$file" ]]; then
        echo -e "${RED}❌ Error: Required file/directory missing: $file${NC}"
        exit 1
    fi
done

# Validate that development files are excluded
EXCLUDED_FILES=(
    "$TEMP_DIR/datamachine-multisite/.git"
    "$TEMP_DIR/datamachine-multisite/build.sh"
    "$TEMP_DIR/datamachine-multisite/composer.lock"
)

for file in "${EXCLUDED_FILES[@]}"; do
    if [[ -e "$file" ]]; then
        echo -e "${RED}❌ Error: Development file should be excluded but was found: $file${NC}"
        exit 1
    fi
done

# Create zip file
ZIP_NAME="datamachine-multisite.zip"
echo -e "${YELLOW}📦 Creating zip file: $ZIP_NAME${NC}"

cd "$TEMP_DIR"
zip -r "../$ZIP_NAME" "datamachine-multisite/" -q

cd ../../

# Remove temporary build artifacts
rm -rf "$TEMP_DIR"

# Final output
BUILD_PATH="$BUILD_DIR/$ZIP_NAME"
FILE_SIZE=$(du -h "$BUILD_PATH" | cut -f1)

echo -e "${GREEN}✅ Build complete!${NC}"
echo -e "${GREEN}📦 ZIP file: $BUILD_PATH ($FILE_SIZE)${NC}"
echo -e "${GREEN}🚀 Ready for WordPress installation${NC}"

# Optional: Show zip contents
if command -v unzip &> /dev/null; then
    echo -e "\n${YELLOW}📋 Zip contents:${NC}"
    unzip -l "$BUILD_PATH" | head -20
    if [[ $(unzip -l "$BUILD_PATH" | wc -l) -gt 25 ]]; then
        echo "... (truncated)"
    fi
fi
