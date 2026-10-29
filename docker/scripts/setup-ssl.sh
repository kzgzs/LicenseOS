#!/bin/bash
# ============================================
# LicenseOS V3.2 - SSL Setup (Let's Encrypt)
# ============================================
# Prerequisites:
#   - Domain pointed to server IP
#   - Port 80 accessible
#   - docker compose running
# ============================================

set -e

DOMAIN=${1:-your-domain.com}
EMAIL=${2:-admin@your-domain.com}

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  LicenseOS V3.2 - SSL Setup${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# Check if certbot is installed
if ! command -v certbot &> /dev/null; then
    echo -e "${YELLOW}Installing certbot...${NC}"
    apt-get update && apt-get install -y certbot
fi

# Create SSL directory
mkdir -p docker/nginx/ssl

# Stop nginx temporarily for standalone mode
echo -e "${YELLOW}Stopping Nginx for certificate generation...${NC}"
docker compose stop nginx

# Generate certificate
echo -e "${YELLOW}Generating SSL certificate for ${DOMAIN}...${NC}"
certbot certonly --standalone \
    -d "$DOMAIN" \
    --non-interactive \
    --agree-tos \
    --email "$EMAIL"

# Copy certificates to nginx ssl directory
echo -e "${YELLOW}Copying certificates...${NC}"
cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem docker/nginx/ssl/fullchain.pem
cp /etc/letsencrypt/live/$DOMAIN/privkey.pem docker/nginx/ssl/privkey.pem

# Enable SSL in nginx config
echo -e "${YELLOW}Enabling SSL in Nginx config...${NC}"
sed -i "s/your-domain.com/$DOMAIN/g" docker/nginx/default.conf

# Uncomment SSL lines
sed -i 's/# listen 443 ssl http2;/listen 443 ssl http2;/' docker/nginx/default.conf
sed -i 's/# ssl_certificate/ssl_certificate/' docker/nginx/default.conf
sed -i 's/# ssl_certificate_key/ssl_certificate_key/' docker/nginx/default.conf
sed -i 's/# ssl_protocols/ssl_protocols/' docker/nginx/default.conf
sed -i 's/# ssl_ciphers/ssl_ciphers/' docker/nginx/default.conf
sed -i 's/# ssl_prefer_server_ciphers/ssl_prefer_server_ciphers/' docker/nginx/default.conf
sed -i 's/# add_header Strict-Transport-Security/add_header Strict-Transport-Security/' docker/nginx/default.conf

# Uncomment HTTP -> HTTPS redirect
sed -i 's/# server {/server {/' docker/nginx/default.conf
sed -i 's/#     listen 80;/    listen 80;/' docker/nginx/default.conf
sed -i "s/#     server_name your-domain.com;/    server_name $DOMAIN;/" docker/nginx/default.conf
sed -i 's/#     return 301/    return 301/' docker/nginx/default.conf

# Start nginx
echo -e "${YELLOW}Restarting Nginx with SSL...${NC}"
docker compose start nginx

# Setup auto-renewal cron
echo -e "${YELLOW}Setting up auto-renewal cron...${NC}"
CRON_CMD="0 3 * * * certbot renew --quiet && docker compose exec nginx nginx -s reload"
(crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  SSL Setup Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "  Domain: https://$DOMAIN"
echo "  Auto-renewal: Daily at 3 AM"
echo "  Cert path: docker/nginx/ssl/"
echo ""
