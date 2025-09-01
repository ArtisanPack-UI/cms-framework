#!/bin/bash

# Build script for ArtisanPack UI CMS Framework Docker containers
# Usage: ./scripts/build.sh [environment] [version]
# Example: ./scripts/build.sh production v1.0.0

set -e

# Default values
ENVIRONMENT=${1:-development}
VERSION=${2:-latest}
REGISTRY=${DOCKER_REGISTRY:-""}
PROJECT_NAME="cms-framework"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===========================================${NC}"
echo -e "${BLUE}  ArtisanPack UI CMS Framework Builder    ${NC}"
echo -e "${BLUE}===========================================${NC}"
echo

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Validate environment
if [[ ! "$ENVIRONMENT" =~ ^(development|staging|production)$ ]]; then
    print_error "Invalid environment: $ENVIRONMENT. Must be: development, staging, or production"
    exit 1
fi

print_status "Building for environment: $ENVIRONMENT"
print_status "Version: $VERSION"

# Set image names
if [ -n "$REGISTRY" ]; then
    IMAGE_BASE="$REGISTRY/$PROJECT_NAME"
else
    IMAGE_BASE="$PROJECT_NAME"
fi

# Build images based on environment
case $ENVIRONMENT in
    "development")
        print_status "Building development image..."
        docker build -f Dockerfile.dev -t "${IMAGE_BASE}:dev-${VERSION}" -t "${IMAGE_BASE}:dev-latest" .
        ;;
    "staging"|"production")
        print_status "Building production image..."
        docker build -f Dockerfile -t "${IMAGE_BASE}:${VERSION}" -t "${IMAGE_BASE}:latest" .
        ;;
esac

# Build additional services
print_status "Building development stack with docker-compose..."
docker-compose -f docker-compose.yml build --parallel

print_status "Build completed successfully!"

# Display built images
echo
print_status "Built images:"
docker images | grep "$PROJECT_NAME" | head -10

# Optional: Run security scan
if command -v docker &> /dev/null && docker --version | grep -q "Docker"; then
    print_warning "Consider running security scan:"
    echo "  docker scan ${IMAGE_BASE}:${VERSION}"
fi

# Optional: Push to registry
if [ -n "$REGISTRY" ] && [ "$ENVIRONMENT" != "development" ]; then
    echo
    read -p "Push images to registry? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Pushing images to registry..."
        docker push "${IMAGE_BASE}:${VERSION}"
        docker push "${IMAGE_BASE}:latest"
        print_status "Images pushed successfully!"
    fi
fi

echo
print_status "Build process completed!"
echo -e "${BLUE}===========================================${NC}"