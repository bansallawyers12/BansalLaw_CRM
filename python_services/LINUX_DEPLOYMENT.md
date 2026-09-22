# Linux Production Deployment Guide

This guide will help you deploy the Migration Manager Python Services on a Linux production server.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Running as a Service](#running-as-a-service)
5. [Nginx Reverse Proxy](#nginx-reverse-proxy)
6. [Security Hardening](#security-hardening)
7. [Monitoring & Logs](#monitoring--logs)
8. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

- Linux server (Ubuntu 20.04+, Debian 10+, CentOS 8+, or similar)
- Python 3.7 or higher
- Root or sudo access
- At least 512 MB RAM
- 1 GB free disk space

### Install Python and Dependencies

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install -y python3 python3-pip python3-venv
```

#### CentOS/RHEL
```bash
sudo yum install -y python3 python3-pip
```

#### Arch Linux
```bash
sudo pacman -S python python-pip
```

---

## Installation

### 1. Upload Files to Server

Upload the `python_services` directory to your server:

```bash
# Using SCP
scp -r python_services/ user@your-server:/var/www/bansal-law-crm/

# Or using rsync
rsync -avz python_services/ user@your-server:/var/www/bansal-law-crm/python_services/
```

### 2. Set Correct Permissions

```bash
cd /var/www/bansal-law-crm/python_services
sudo chown -R www-data:www-data .
sudo chmod +x start_services.sh
sudo chmod +x install_service_linux.sh
```

### 3. Install Python Dependencies

```bash
cd /var/www/bansal-law-crm/python_services
python3 -m pip install -r requirements.txt
```

Or use a virtual environment (recommended):

```bash
cd /var/www/bansal-law-crm/python_services
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

---

## Configuration

### Environment Variables

Create a `.env` file or set environment variables:

```bash
# Create .env file
cat > .env << EOF
SERVICE_HOST=127.0.0.1
SERVICE_PORT=5000
DEBUG=False
LOG_LEVEL=INFO
MAX_FILE_SIZE_MB=20
ALLOWED_PDF_SIZE_MB=50
ALLOWED_EMAIL_SIZE_MB=20
EOF
```

### Firewall Configuration

If the service needs to be accessible from outside:

```bash
# UFW (Ubuntu/Debian)
sudo ufw allow 5000/tcp

# firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-port=5000/tcp
sudo firewall-cmd --reload

# iptables
sudo iptables -A INPUT -p tcp --dport 5000 -j ACCEPT
```

**Note:** For production, it's better to use Nginx as a reverse proxy (see below) and keep the Python service on localhost only.

---

## Running as a Service

### Option 1: Quick Start (Manual)

```bash
cd /var/www/bansal-law-crm/python_services
./start_services.sh
```

### Option 2: Systemd Service (Recommended)

#### Automatic Installation

```bash
cd /var/www/bansal-law-crm/python_services
sudo ./install_service_linux.sh
```

This will:
- Install the service
- Enable it to start on boot
- Start it immediately

#### Manual Installation

If you prefer to create the service manually:

```bash
# Create service file
sudo nano /etc/systemd/system/migration-python-services.service
```

Add the following content:

```ini
[Unit]
Description=Migration Manager Python Services
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bansal-law-crm/python_services
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
ExecStart=/usr/bin/python3 /var/www/bansal-law-crm/python_services/main.py --host 127.0.0.1 --port 5000
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=migration-python-services

# Security settings
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

#### Using Virtual Environment

If you're using a virtual environment, modify the `ExecStart` line:

```ini
ExecStart=/var/www/bansal-law-crm/python_services/venv/bin/python /var/www/bansal-law-crm/python_services/main.py --host 127.0.0.1 --port 5000
```

#### Enable and Start Service

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable migration-python-services

# Start service
sudo systemctl start migration-python-services

# Check status
sudo systemctl status migration-python-services
```

#### Service Management Commands

```bash
# Start
sudo systemctl start migration-python-services

# Stop
sudo systemctl stop migration-python-services

# Restart
sudo systemctl restart migration-python-services

# Status
sudo systemctl status migration-python-services

# View logs
sudo journalctl -u migration-python-services -f

# View recent logs
sudo journalctl -u migration-python-services -n 100 --no-pager
```

---

## Nginx Reverse Proxy

For production, use Nginx as a reverse proxy to handle SSL/TLS and load balancing.

### Install Nginx

```bash
# Ubuntu/Debian
sudo apt install -y nginx

# CentOS/RHEL
sudo yum install -y nginx
```

### Configure Nginx

Create a new site configuration:

```bash
sudo nano /etc/nginx/sites-available/migration-manager
```

Add the following:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/your-cert.pem;
    ssl_certificate_key /etc/ssl/private/your-key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Python Services
    location /python/ {
        proxy_pass http://127.0.0.1:5000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Increase timeout for file uploads
        proxy_connect_timeout 300s;
        proxy_send_timeout 300s;
        proxy_read_timeout 300s;
        
        # Increase buffer size for large responses
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
        proxy_busy_buffers_size 8k;
    }

    # Increase upload size limit
    client_max_body_size 50M;
    client_body_timeout 300s;
}
```

### Enable Site and Restart Nginx

```bash
# Enable site (Ubuntu/Debian)
sudo ln -s /etc/nginx/sites-available/migration-manager /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

### SSL Certificate with Let's Encrypt

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal is set up automatically
```

---

## Security Hardening

### 1. Create Dedicated User

```bash
# Create user
sudo useradd -r -s /bin/false migration-services

# Set ownership
sudo chown -R migration-services:migration-services /var/www/bansal-law-crm/python_services

# Update systemd service file to use this user
sudo nano /etc/systemd/system/migration-python-services.service
# Change: User=migration-services
```

### 2. Restrict File Permissions

```bash
cd /var/www/bansal-law-crm/python_services

# Restrict access
sudo chmod 750 .
sudo chmod 640 config.py
sudo chmod 640 requirements.txt
sudo chmod 750 main.py
sudo chmod 750 start_services.sh

# Secure logs directory
sudo chmod 750 logs/
```

### 3. Enable SELinux (CentOS/RHEL)

```bash
# Allow network connections
sudo setsebool -P httpd_can_network_connect 1

# Set correct context
sudo semanage fcontext -a -t httpd_sys_content_t "/var/www/bansal-law-crm/python_services(/.*)?"
sudo restorecon -Rv /var/www/bansal-law-crm/python_services
```

### 4. Configure Firewall

```bash
# Allow only necessary ports
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## Monitoring & Logs

### View Service Logs

```bash
# Real-time logs
sudo journalctl -u migration-python-services -f

# Last 100 lines
sudo journalctl -u migration-python-services -n 100

# Today's logs
sudo journalctl -u migration-python-services --since today

# Logs with timestamps
sudo journalctl -u migration-python-services -o short-iso
```

### Application Logs

```bash
# View application logs
tail -f /var/www/bansal-law-crm/python_services/logs/service.log

# View error logs
tail -f /var/www/bansal-law-crm/python_services/logs/error.log
```

### Health Check

```bash
# Check service health
curl http://127.0.0.1:5000/health

# Check via Nginx
curl https://your-domain.com/python/health
```

### Set Up Log Rotation

Create `/etc/logrotate.d/migration-python-services`:

```bash
sudo nano /etc/logrotate.d/migration-python-services
```

Add:

```
/var/www/bansal-law-crm/python_services/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
    create 640 migration-services migration-services
    postrotate
        systemctl reload migration-python-services > /dev/null 2>&1 || true
    endscript
}
```

---

## Troubleshooting

### Service Won't Start

```bash
# Check service status
sudo systemctl status migration-python-services

# Check logs
sudo journalctl -u migration-python-services -n 50

# Check Python errors
python3 /var/www/bansal-law-crm/python_services/main.py

# Check permissions
ls -la /var/www/bansal-law-crm/python_services/
```

### Port Already in Use

```bash
# Check what's using port 5000
sudo lsof -i :5000
sudo netstat -tulpn | grep 5000

# Kill process
sudo kill -9 <PID>
```

### Permission Denied Errors

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/bansal-law-crm/python_services

# Fix permissions
sudo chmod -R 755 /var/www/bansal-law-crm/python_services
```

### Python Module Not Found

```bash
# Reinstall dependencies
cd /var/www/bansal-law-crm/python_services
pip3 install --upgrade -r requirements.txt

# Check installed packages
pip3 list
```

### High Memory Usage

```bash
# Check memory usage
free -h
ps aux | grep python

# Restart service
sudo systemctl restart migration-python-services
```

### Nginx 502 Bad Gateway

```bash
# Check if Python service is running
sudo systemctl status migration-python-services

# Check if service is listening
sudo netstat -tulpn | grep 5000

# Check Nginx error logs
sudo tail -f /var/log/nginx/error.log
```

---

## Performance Tuning

### Use Gunicorn for Production

Install Gunicorn:

```bash
pip3 install gunicorn
```

Update systemd service:

```ini
ExecStart=/usr/bin/python3 -m gunicorn main:app \
    --bind 127.0.0.1:5000 \
    --workers 4 \
    --worker-class uvicorn.workers.UvicornWorker \
    --timeout 300 \
    --access-logfile /var/www/bansal-law-crm/python_services/logs/access.log \
    --error-logfile /var/www/bansal-law-crm/python_services/logs/error.log
```

### Optimize Worker Count

```bash
# Formula: (2 x CPU cores) + 1
# For 2 CPU cores: 5 workers
# For 4 CPU cores: 9 workers

# Check CPU count
nproc
```

---

## Backup and Recovery

### Backup Script

```bash
#!/bin/bash
# Backup Python services

BACKUP_DIR="/var/backups/python_services"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup files
tar -czf $BACKUP_DIR/python_services_$DATE.tar.gz \
    /var/www/bansal-law-crm/python_services

# Keep only last 7 days
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

### Restore

```bash
# Stop service
sudo systemctl stop migration-python-services

# Restore files
sudo tar -xzf /var/backups/python_services/python_services_YYYYMMDD_HHMMSS.tar.gz -C /

# Start service
sudo systemctl start migration-python-services
```

---

## Updates and Maintenance

### Update Service

```bash
# Pull latest code
cd /var/www/bansal-law-crm/python_services
git pull  # or upload new files

# Update dependencies
pip3 install --upgrade -r requirements.txt

# Restart service
sudo systemctl restart migration-python-services

# Check status
sudo systemctl status migration-python-services
```

### Health Monitoring

Set up a cron job to check service health:

```bash
# Edit crontab
crontab -e

# Add health check every 5 minutes
*/5 * * * * curl -f http://127.0.0.1:5000/health || systemctl restart migration-python-services
```

---

## Production Checklist

- [ ] Python 3.7+ installed
- [ ] Dependencies installed
- [ ] Service installed and running
- [ ] Service enabled to start on boot
- [ ] Nginx configured with reverse proxy
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Log rotation set up
- [ ] Health monitoring configured
- [ ] Backup script created
- [ ] Security hardening applied
- [ ] Documentation updated with server details

---

## Support

For issues or questions:

1. Check logs: `sudo journalctl -u migration-python-services -f`
2. Check application logs in `logs/` directory
3. Test health endpoint: `curl http://127.0.0.1:5000/health`
4. Review this documentation

---

**Last Updated:** October 2025

