#!/bin/bash

# Deployment script for ArtisanPack UI CMS Framework
# Usage: ./scripts/deploy.sh [environment] [version]
# Example: ./scripts/deploy.sh production v1.0.0

set -e

# Default values
ENVIRONMENT=${1:-development}
VERSION=${2:-latest}
REGISTRY=${DOCKER_REGISTRY:-""}
PROJECT_NAME="cms-framework"
COMPOSE_PROJECT_NAME="${PROJECT_NAME}-${ENVIRONMENT}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===========================================${NC}"
echo -e "${BLUE}  ArtisanPack UI CMS Framework Deployer   ${NC}"
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

# Function to check if container is healthy
check_health() {
    local container_name=$1
    local max_attempts=30
    local attempt=1

    print_status "Checking health of $container_name..."
    
    while [ $attempt -le $max_attempts ]; do
        if docker inspect --format='{{.State.Health.Status}}' "$container_name" 2>/dev/null | grep -q "healthy"; then
            print_status "$container_name is healthy!"
            return 0
        fi
        
        print_warning "Attempt $attempt/$max_attempts: $container_name not healthy yet..."
        sleep 10
        ((attempt++))
    done
    
    print_error "$container_name failed health check after $max_attempts attempts"
    return 1
}

# Function to rollback deployment
rollback() {
    print_warning "Rolling back deployment..."
    if [ -f ".env.backup" ]; then
        mv .env.backup .env
        print_status "Environment variables restored"
    fi
    
    if docker-compose -p "$COMPOSE_PROJECT_NAME" ps -q > /dev/null 2>&1; then
        docker-compose -p "$COMPOSE_PROJECT_NAME" down
        print_status "Containers stopped"
    fi
    
    print_error "Deployment rolled back"
    exit 1
}

# Trap for cleanup on failure
trap rollback ERR

# Validate environment
if [[ ! "$ENVIRONMENT" =~ ^(development|staging|production)$ ]]; then
    print_error "Invalid environment: $ENVIRONMENT. Must be: development, staging, or production"
    exit 1
fi

print_status "Deploying to environment: $ENVIRONMENT"
print_status "Version: $VERSION"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running or accessible"
    exit 1
fi

# Pre-deployment checks
print_status "Running pre-deployment checks..."

# Check if required files exist
required_files=("docker-compose.yml" "Dockerfile")
for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Required file not found: $file"
        exit 1
    fi
done

# Backup current environment
if [ -f ".env" ]; then
    cp .env .env.backup
    print_status "Environment file backed up"
fi

# Set environment-specific variables
case $ENVIRONMENT in
    "development")
        export APP_ENV=local
        export APP_DEBUG=true
        export COMPOSE_FILE="docker-compose.yml"
        ;;
    "staging")
        export APP_ENV=staging
        export APP_DEBUG=false
        export COMPOSE_FILE="docker-compose.yml:docker-compose.staging.yml"
        ;;
    "production")
        export APP_ENV=production
        export APP_DEBUG=false
        export COMPOSE_FILE="docker-compose.yml:docker-compose.prod.yml"
        ;;
esac

# Pull latest images if using registry
if [ -n "$REGISTRY" ]; then
    print_status "Pulling latest images from registry..."
    docker-compose -p "$COMPOSE_PROJECT_NAME" pull
fi

# Stop existing containers gracefully
if docker-compose -p "$COMPOSE_PROJECT_NAME" ps -q > /dev/null 2>&1; then
    print_status "Stopping existing containers..."
    docker-compose -p "$COMPOSE_PROJECT_NAME" down --timeout 30
fi

# Start services
print_status "Starting services..."
docker-compose -p "$COMPOSE_PROJECT_NAME" up -d

# Wait for services to be ready
print_status "Waiting for services to be ready..."
sleep 15

# Run Laravel-specific deployment tasks
container_name="${COMPOSE_PROJECT_NAME}_app_1"
if docker ps --format "table {{.Names}}" | grep -q "$container_name"; then
    print_status "Running Laravel deployment tasks..."
    
    # Run migrations
    docker exec "$container_name" php artisan migrate --force || {
        print_warning "Migration failed, but continuing deployment"
    }
    
    # Clear and cache config
    docker exec "$container_name" php artisan config:cache
    docker exec "$container_name" php artisan route:cache
    docker exec "$container_name" php artisan view:cache
    
    # Optimize autoloader
    docker exec "$container_name" composer dump-autoload --optimize
    
    print_status "Laravel deployment tasks completed"
fi

# Health checks
print_status "Performing health checks..."
if ! check_health "$container_name"; then
    print_error "Health check failed"
    rollback
fi

# Test HTTP endpoint if available
if command -v curl &> /dev/null; then
    print_status "Testing HTTP endpoints..."
    if curl -f http://localhost/health > /dev/null 2>&1; then
        print_status "HTTP health check passed"
    else
        print_warning "HTTP health check failed, but deployment continues"
    fi
fi

# Cleanup old images
print_status "Cleaning up old Docker images..."
docker image prune -f > /dev/null 2>&1 || true

# Remove backup if deployment successful
if [ -f ".env.backup" ]; then
    rm .env.backup
fi

# Display running services
echo
print_status "Deployment completed successfully!"
print_status "Running services:"
docker-compose -p "$COMPOSE_PROJECT_NAME" ps

echo
print_status "Container logs can be viewed with:"
echo "  docker-compose -p $COMPOSE_PROJECT_NAME logs -f"

echo
print_status "To stop the deployment:"
echo "  docker-compose -p $COMPOSE_PROJECT_NAME down"

echo -e "${BLUE}===========================================${NC}"