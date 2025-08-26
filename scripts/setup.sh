#!/bin/bash

# Environment setup script for ArtisanPack UI CMS Framework
# Usage: ./scripts/setup.sh [environment]
# Example: ./scripts/setup.sh development

set -e

# Default values
ENVIRONMENT=${1:-development}
PROJECT_NAME="cms-framework"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===========================================${NC}"
echo -e "${BLUE}  ArtisanPack UI CMS Framework Setup      ${NC}"
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

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to create .env file
create_env_file() {
    local env_file=".env.${ENVIRONMENT}"
    
    print_status "Creating environment file: $env_file"
    
    cat > "$env_file" << EOF
# ArtisanPack UI CMS Framework Environment Configuration
# Environment: ${ENVIRONMENT}

APP_NAME="ArtisanPack CMS Framework"
APP_ENV=${ENVIRONMENT}
APP_KEY=
APP_DEBUG=$( [ "$ENVIRONMENT" = "development" ] && echo "true" || echo "false" )
APP_TIMEZONE=UTC
APP_URL=http://localhost

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=cms_framework
DB_USERNAME=cms_user
DB_PASSWORD=cms_password

# Alternative PostgreSQL Configuration
# DB_CONNECTION=pgsql
# DB_HOST=postgresql
# DB_PORT=5432
# DB_DATABASE=cms_framework
# DB_USERNAME=cms_user
# DB_PASSWORD=cms_password

# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=cms_cache

# Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue Configuration
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@cms-framework.local"
MAIL_FROM_NAME="\${APP_NAME}"

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Search Configuration (Elasticsearch)
SCOUT_DRIVER=elasticsearch
ELASTICSEARCH_HOST=elasticsearch:9200

# File Storage
FILESYSTEM_DISK=local

# Security
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
SESSION_DOMAIN=localhost

# CMS Specific
CMS_THEME=default
CMS_ADMIN_PATH=admin
CMS_MEDIA_DISK=public
CMS_CACHE_TTL=3600

# Development Tools (for development environment only)
EOF

    if [ "$ENVIRONMENT" = "development" ]; then
        cat >> "$env_file" << EOF
DEBUGBAR_ENABLED=true
TELESCOPE_ENABLED=true
XDEBUG_MODE=develop,debug
EOF
    fi

    # Copy to .env if it doesn't exist
    if [ ! -f ".env" ]; then
        cp "$env_file" .env
        print_status "Copied $env_file to .env"
    fi
}

print_status "Setting up environment: $ENVIRONMENT"

# Check prerequisites
print_status "Checking prerequisites..."

required_commands=("docker" "docker-compose")
for cmd in "${required_commands[@]}"; do
    if ! command_exists "$cmd"; then
        print_error "Required command not found: $cmd"
        echo "Please install Docker and Docker Compose:"
        echo "  https://docs.docker.com/get-docker/"
        echo "  https://docs.docker.com/compose/install/"
        exit 1
    fi
done

print_status "Docker version: $(docker --version)"
print_status "Docker Compose version: $(docker-compose --version)"

# Check if Docker daemon is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker daemon is not running"
    echo "Please start Docker and try again"
    exit 1
fi

# Create necessary directories
print_status "Creating necessary directories..."
directories=(
    "storage/app"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
)

for dir in "${directories[@]}"; do
    mkdir -p "$dir"
    chmod 755 "$dir"
done

# Create environment file
create_env_file

# Make scripts executable
print_status "Making scripts executable..."
find scripts/ -name "*.sh" -exec chmod +x {} \;

# Generate application key
if [ -f ".env" ]; then
    if ! grep -q "APP_KEY=base64:" .env; then
        print_status "Generating application key..."
        # We'll need to do this after containers are up
        print_warning "Application key will be generated after containers start"
    fi
fi

# Pull required Docker images
print_status "Pulling required Docker images..."
docker-compose pull

# Build custom images
print_status "Building custom Docker images..."
docker-compose build

print_status "Environment setup completed!"

# Show next steps
echo
echo -e "${BLUE}Next Steps:${NC}"
echo "1. Review and update .env file as needed"
echo "2. Start the development environment:"
echo "   ${GREEN}docker-compose up -d${NC}"
echo
echo "3. Generate application key (after containers are running):"
echo "   ${GREEN}docker-compose exec app php artisan key:generate${NC}"
echo
echo "4. Run database migrations:"
echo "   ${GREEN}docker-compose exec app php artisan migrate${NC}"
echo
echo "5. (Optional) Seed the database:"
echo "   ${GREEN}docker-compose exec app php artisan db:seed${NC}"
echo
echo "6. Access the application:"
echo "   - Web: ${GREEN}http://localhost${NC}"
echo "   - PHPMyAdmin: ${GREEN}http://localhost:8081${NC}"
echo "   - Adminer: ${GREEN}http://localhost:8082${NC}"
echo "   - MailHog: ${GREEN}http://localhost:8025${NC}"
echo "   - Kibana: ${GREEN}http://localhost:5601${NC}"

if [ "$ENVIRONMENT" = "development" ]; then
    echo
    echo -e "${BLUE}Development Tools:${NC}"
    echo "- Xdebug is configured on port 9003"
    echo "- Use PHPStorm or VS Code with Xdebug extension"
    echo "- Logs: ${GREEN}docker-compose logs -f${NC}"
fi

echo
echo -e "${BLUE}===========================================${NC}"