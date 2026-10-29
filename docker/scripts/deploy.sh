#!/bin/bash
# ============================================
# LicenseOS V3.2 - Production Deploy Script
# ============================================
# Usage: ./deploy.sh [--first-time]
# ============================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  LicenseOS V3.2 - Production Deploy${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# --- 1. Pre-flight checks ---
echo -e "${YELLOW}[1/8] Pre-flight checks...${NC}"

if ! command -v docker &> /dev/null; then
    echo -e "${RED}ERROR: Docker is not installed.${NC}"
    exit 1
fi

if ! command -v docker compose &> /dev/null; then
    echo -e "${RED}ERROR: Docker Compose is not installed.${NC}"
    exit 1
fi

echo "  Docker: $(docker --version)"
echo "  Compose: $(docker compose version)"

# --- 2. Environment setup ---
echo -e "${YELLOW}[2/8] Environment setup...${NC}"

if [ ! -f .env ]; then
    if [ -f .env.production ]; then
        cp .env.production .env
        echo "  Copied .env.production -> .env"
    else
        echo -e "${RED}ERROR: No .env or .env.production found.${NC}"
        exit 1
    fi
fi

# --- 3. Generate APP_KEY if missing ---
echo -e "${YELLOW}[3/8] Checking APP_KEY...${NC}"

APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "  Generating APP_KEY..."
    docker compose run --rm app php artisan key:generate
fi

# --- 4. Build containers ---
echo -e "${YELLOW}[4/8] Building containers...${NC}"
docker compose build --no-cache

# --- 5. Start services ---
echo -e "${YELLOW}[5/8] Starting services...${NC}"
docker compose up -d

echo "  Waiting for services to be healthy..."
sleep 15

# --- 6. Database migration ---
echo -e "${YELLOW}[6/8] Running migrations...${NC}"

if [ "$1" = "--first-time" ]; then
    echo "  First-time setup: running migrate + seed..."
    docker compose exec app php artisan migrate --force
    docker compose exec app php artisan db:seed --class=DemoSeeder --force
else
    docker compose exec app php artisan migrate --force
fi

# --- 7. Optimize for production ---
echo -e "${YELLOW}[7/8] Optimizing for production...${NC}"
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# Create storage symlink
docker compose exec app php artisan storage:link 2>/dev/null || true

# --- 8. Health check ---
echo -e "${YELLOW}[8/8] Health check...${NC}"

# Check if app is running
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/health 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "  ${GREEN}Health check passed (HTTP 200)${NC}"
else
    echo -e "  ${YELLOW}Health check returned $HTTP_CODE (may need a moment)${NC}"
fi

# Check queue
QUEUE_STATUS=$(docker compose exec queue supervisorctl status 2>/dev/null | head -5 || echo "Queue not ready")
echo "  Queue status: $QUEUE_STATUS"

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Deploy Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "  App:     http://localhost"
echo "  Admin:   http://localhost/admin"
echo "  API:     http://localhost/api/v3/products"
echo ""
echo "  Useful commands:"
echo "    docker compose logs -f app      # View app logs"
echo "    docker compose logs -f queue    # View queue logs"
echo "    docker compose exec app bash    # Shell into app"
echo "    docker compose restart          # Restart all"
echo "    docker compose down             # Stop all"
echo ""
