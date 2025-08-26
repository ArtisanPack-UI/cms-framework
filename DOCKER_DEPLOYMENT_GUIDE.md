# Docker Deployment Guide

## ArtisanPack UI CMS Framework Containerization

This guide provides comprehensive instructions for deploying the ArtisanPack UI CMS Framework using Docker containers.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Start](#quick-start)
3. [Container Architecture](#container-architecture)
4. [Environment Configuration](#environment-configuration)
5. [Development Setup](#development-setup)
6. [Production Deployment](#production-deployment)
7. [Service Configuration](#service-configuration)
8. [Health Checks](#health-checks)
9. [Monitoring & Logging](#monitoring--logging)
10. [Troubleshooting](#troubleshooting)
11. [Security Considerations](#security-considerations)

## 🔧 Prerequisites

### System Requirements
- Docker Engine 20.10.0 or later
- Docker Compose 2.0.0 or later
- Minimum 4GB RAM
- Minimum 10GB free disk space

### Installation
```bash
# Install Docker (Ubuntu/Debian)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### Verify Installation
```bash
docker --version
docker-compose --version
```

## 🚀 Quick Start

### 1. Initial Setup
```bash
# Clone the repository (if needed)
# git clone <repository-url>
cd cms-framework

# Run the setup script
./scripts/setup.sh development

# Start the services
docker-compose up -d

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# (Optional) Seed the database
docker-compose exec app php artisan db:seed
```

### 2. Access the Application
- **Web Application**: http://localhost
- **PHPMyAdmin**: http://localhost:8081
- **Adminer**: http://localhost:8082
- **MailHog**: http://localhost:8025
- **Kibana**: http://localhost:5601

## 🏗️ Container Architecture

### Services Overview

| Service | Purpose | Port | Dependencies |
|---------|---------|------|--------------|
| **app** | PHP-FPM application | 9000 | mysql, redis |
| **nginx** | Web server | 80, 443 | app |
| **mysql** | Primary database | 3306 | - |
| **postgresql** | Alternative database | 5432 | - |
| **redis** | Cache & sessions | 6379 | - |
| **elasticsearch** | Search engine | 9200, 9300 | - |
| **kibana** | Log visualization | 5601 | elasticsearch |
| **mailhog** | Email testing | 1025, 8025 | - |
| **phpmyadmin** | MySQL management | 8081 | mysql |
| **adminer** | Database management | 8082 | mysql, postgresql |
| **node** | Asset compilation | - | - |

### Container Images
- **Production**: `php:8.4-fpm-alpine` + optimizations
- **Development**: `php:8.4-fpm-alpine` + Xdebug + dev tools

## ⚙️ Environment Configuration

### Environment Files
The setup script creates environment-specific `.env` files:

- `.env.development` - Development settings
- `.env.staging` - Staging settings  
- `.env.production` - Production settings

### Key Configuration Options

```bash
# Application
APP_NAME="ArtisanPack CMS Framework"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=cms_framework
DB_USERNAME=cms_user
DB_PASSWORD=cms_password

# Cache & Sessions
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Mail (Development)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

## 🛠️ Development Setup

### Starting Development Environment
```bash
# Setup and start all services
./scripts/setup.sh development
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### Development Tools

#### Xdebug Configuration
- **Port**: 9003
- **IDE Key**: PHPSTORM
- **Host**: host.docker.internal

#### VS Code Configuration
Add to `.vscode/launch.json`:
```json
{
    "name": "Listen for Xdebug",
    "type": "php",
    "request": "launch",
    "port": 9003,
    "pathMappings": {
        "/var/www/html": "${workspaceFolder}"
    }
}
```

#### PHPStorm Configuration
1. Go to Settings → PHP → Servers
2. Add server:
   - Name: `localhost`
   - Host: `localhost`
   - Port: `80`
   - Debugger: `Xdebug`
   - Path mapping: `/var/www/html` → `{project_root}`

### Running Commands
```bash
# Artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan make:controller ExampleController

# Composer commands
docker-compose exec app composer install
docker-compose exec app composer require package/name

# Node/NPM commands
docker-compose exec node npm install
docker-compose exec node npm run dev

# Database access
docker-compose exec mysql mysql -u cms_user -p cms_framework
```

## 🚀 Production Deployment

### Build Production Image
```bash
# Build production image
./scripts/build.sh production v1.0.0

# Deploy to production
./scripts/deploy.sh production v1.0.0
```

### Production Environment Variables
```bash
# Security
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key

# Database (use external/managed database)
DB_HOST=your-database-host
DB_DATABASE=cms_production
DB_USERNAME=cms_user
DB_PASSWORD=your-secure-password

# Cache (use external Redis if needed)
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password

# Mail (use SMTP service)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
```

### Production Checklist
- [ ] Set secure passwords for all services
- [ ] Configure SSL/TLS certificates
- [ ] Set up external database (recommended)
- [ ] Configure external Redis (for scaling)
- [ ] Set up monitoring and alerting
- [ ] Configure log aggregation
- [ ] Set up automated backups
- [ ] Configure firewall rules
- [ ] Enable security headers
- [ ] Set up health checks

## 📊 Service Configuration

### Nginx Configuration
Located in `docker/nginx/default.conf`:
- Laravel-optimized routing
- Security headers
- Gzip compression
- Static asset caching
- Health check endpoint (`/health`)

### PHP Configuration
- **Production**: `docker/php/php.ini` + `docker/php/opcache.ini`
- **Development**: `docker/php/local.ini`

Key features:
- OPcache enabled with JIT compilation
- Redis session handling
- Security hardening
- Performance optimization

### Database Configuration
- **MySQL**: `docker/mysql/my.cnf`
- **PostgreSQL**: `docker/postgres/init.sql`

Optimized for Laravel with:
- UTF8MB4 character set
- Performance tuning
- Laravel-compatible SQL modes

### Redis Configuration
Located in `docker/redis/redis.conf`:
- Memory optimization
- Persistence configuration
- Security settings
- Performance tuning

## 🔍 Health Checks

### Built-in Health Checks
- **Application**: HTTP endpoint at `/health`
- **Container**: Docker HEALTHCHECK directive
- **Services**: Service dependency checks

### Manual Health Checks
```bash
# Check all containers
docker-compose ps

# Check specific service health
docker inspect cms-framework-app --format='{{.State.Health.Status}}'

# Test HTTP endpoint
curl -f http://localhost/health
```

### Monitoring Commands
```bash
# View real-time logs
docker-compose logs -f

# Monitor resource usage
docker stats

# Check container processes
docker-compose exec app ps aux
```

## 📊 Monitoring & Logging

### Log Locations
- **Application**: `/var/log/supervisor/`
- **Nginx**: `/var/log/nginx/`
- **PHP-FPM**: `/var/log/php_errors.log`
- **MySQL**: `/var/log/mysql/`

### Log Aggregation
Logs are automatically collected by Supervisor and can be viewed:
```bash
# View all logs
docker-compose logs

# View specific service logs
docker-compose logs app
docker-compose logs nginx

# Follow logs in real-time
docker-compose logs -f app
```

### Kibana Integration
- Access: http://localhost:5601
- Elasticsearch backend for log analysis
- Create dashboards for application metrics

## 🐛 Troubleshooting

### Common Issues

#### Container Won't Start
```bash
# Check container logs
docker-compose logs [service-name]

# Check container status
docker-compose ps

# Restart specific service
docker-compose restart [service-name]
```

#### Database Connection Issues
```bash
# Check database container
docker-compose logs mysql

# Test connection
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
```

#### Permission Issues
```bash
# Fix storage permissions
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
```

#### Performance Issues
```bash
# Check resource usage
docker stats

# Clear application cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

### Debug Mode
```bash
# Enable debug logging
echo "APP_DEBUG=true" >> .env

# Restart containers
docker-compose restart

# View detailed logs
docker-compose logs -f app
```

## 🔒 Security Considerations

### Container Security
- Non-root user execution
- Minimal base images (Alpine Linux)
- Security headers enabled
- Secrets management via environment variables

### Network Security
- Services communicate via internal Docker network
- Only necessary ports exposed
- Health check endpoints secured

### Data Security
- Database volumes for persistence
- Backup strategies implemented
- SSL/TLS for production deployments

### Production Security Checklist
- [ ] Change all default passwords
- [ ] Enable SSL/TLS certificates
- [ ] Configure firewall rules
- [ ] Set up log monitoring
- [ ] Enable container scanning
- [ ] Implement backup strategies
- [ ] Configure rate limiting
- [ ] Set up intrusion detection

## 🔧 Advanced Configuration

### Scaling Services
```bash
# Scale queue workers
docker-compose up -d --scale laravel-worker=5

# Scale web servers (behind load balancer)
docker-compose up -d --scale app=3
```

### Custom Configurations
- Copy and modify configuration files in `docker/` directory
- Rebuild images after configuration changes
- Use environment-specific docker-compose override files

### CI/CD Integration
Example GitHub Actions workflow:
```yaml
name: Deploy CMS Framework
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Build and Deploy
        run: |
          ./scripts/build.sh production ${{ github.sha }}
          ./scripts/deploy.sh production ${{ github.sha }}
```

## 📞 Support

For additional support:
1. Check the troubleshooting section above
2. Review container logs for specific error messages
3. Consult the main project documentation
4. Submit issues to the project repository

---

**Last Updated**: August 26, 2025
**Version**: 1.0.0