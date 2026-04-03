# 🚀 Uploading to Linux Server - Step by Step

This guide shows you exactly how to upload and deploy the Python services on your Linux production server.

---

## 📋 Prerequisites

Before you start, make sure you have:

- SSH access to your Linux server
- Server IP address or hostname
- Username and password (or SSH key)
- sudo/root privileges on the server

---

## Method 1: Using SCP (Secure Copy)

### Step 1: Upload Files

From your Windows machine (in PowerShell or Command Prompt):

```bash
# Navigate to your project root
cd C:\xampp\htdocs\BansalLaw_CRM

# Upload the python_services directory
scp -r python_services username@your-server-ip:/tmp/
```

**Example:**
```bash
scp -r python_services root@192.168.1.100:/tmp/
```

### Step 2: SSH to Server

```bash
ssh username@your-server-ip
```

**Example:**
```bash
ssh root@192.168.1.100
```

### Step 3: Move to Final Location

```bash
# Create directory structure
sudo mkdir -p /var/www/bansal-law-crm

# Move files
sudo mv /tmp/python_services /var/www/bansal-law-crm/

# Set ownership (adjust user as needed)
sudo chown -R www-data:www-data /var/www/bansal-law-crm/python_services
```

### Step 4: Make Scripts Executable

```bash
cd /var/www/bansal-law-crm/python_services
sudo chmod +x start_services.sh
sudo chmod +x install_service_linux.sh
sudo chmod +x check_requirements.sh
```

### Step 5: Check Requirements

```bash
./check_requirements.sh
```

### Step 6: Install Dependencies

```bash
pip3 install -r requirements.txt
```

### Step 7: Install as Service

```bash
sudo ./install_service_linux.sh
```

✅ **Done!** Your service is now running.

---

## Method 2: Using SFTP (GUI Method)

### Step 1: Open SFTP Client

Popular options:
- **WinSCP** (Windows) - https://winscp.net/
- **FileZilla** (Cross-platform) - https://filezilla-project.org/
- **Cyberduck** (Mac/Windows) - https://cyberduck.io/

### Step 2: Connect to Server

In WinSCP:
1. File Protocol: SFTP
2. Host name: your-server-ip
3. Port: 22
4. Username: your-username
5. Password: your-password
6. Click "Login"

### Step 3: Navigate to Directory

- Local (left): `C:\xampp\htdocs\BansalLaw_CRM\python_services`
- Remote (right): `/var/www/bansal-law-crm/`

### Step 4: Upload Files

1. Create directory on server: `/var/www/bansal-law-crm/python_services`
2. Drag and drop the contents of `python_services` folder
3. Wait for upload to complete

### Step 5: SSH to Server

Use PuTTY or any SSH client to connect to your server.

### Step 6: Set Permissions and Install

```bash
cd /var/www/bansal-law-crm/python_services

# Set ownership
sudo chown -R www-data:www-data .

# Make scripts executable
sudo chmod +x *.sh

# Check requirements
./check_requirements.sh

# Install dependencies
pip3 install -r requirements.txt

# Install service
sudo ./install_service_linux.sh
```

✅ **Done!**

---

## Method 3: Using Git (Recommended for Updates)

### Step 1: Initial Setup on Server

```bash
# SSH to server
ssh username@your-server-ip

# Install git (if not already installed)
sudo apt install git  # Ubuntu/Debian
# or
sudo yum install git  # CentOS/RHEL

# Clone repository
cd /var/www/bansal-law-crm
sudo git clone https://github.com/bansallawyers12/BansalLaw_CRM.git .

# Navigate to python_services
cd python_services
```

### Step 2: Install

```bash
# Make scripts executable
sudo chmod +x *.sh

# Check requirements
./check_requirements.sh

# Install dependencies
pip3 install -r requirements.txt

# Install service
sudo ./install_service_linux.sh
```

### Future Updates

```bash
cd /var/www/bansal-law-crm
sudo git pull
cd python_services
pip3 install --upgrade -r requirements.txt
sudo systemctl restart migration-python-services
```

✅ **Done!**

---

## Method 4: Using rsync (Best for Large Transfers)

### Step 1: Upload with rsync

From Windows (using WSL or Git Bash):

```bash
rsync -avz --progress \
  /c/xampp/htdocs/BansalLaw_CRM/python_services/ \
  username@your-server-ip:/var/www/bansal-law-crm/python_services/
```

### Step 2: SSH and Configure

```bash
ssh username@your-server-ip
cd /var/www/bansal-law-crm/python_services

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod +x *.sh

# Install
./check_requirements.sh
pip3 install -r requirements.txt
sudo ./install_service_linux.sh
```

✅ **Done!**

---

## After Upload - Verification

### Check Service Status

```bash
sudo systemctl status migration-python-services
```

Expected output:
```
● migration-python-services.service - Migration Manager Python Services
   Loaded: loaded (/etc/systemd/system/migration-python-services.service; enabled)
   Active: active (running) since ...
```

### Test Health Endpoint

```bash
curl http://localhost:5000/health
```

Expected response:
```json
{
  "status": "healthy",
  "services": {
    "pdf_service": "ready",
    "email_parser": "ready",
    "email_analyzer": "ready",
    "email_renderer": "ready"
  }
}
```

### View Logs

```bash
# Real-time logs
sudo journalctl -u migration-python-services -f

# Last 50 lines
sudo journalctl -u migration-python-services -n 50
```

---

## Configure Laravel to Use the Service

### Update .env on Server

```bash
# Edit Laravel .env file
sudo nano /var/www/bansal-law-crm/.env

# Update or add this line
PYTHON_SERVICE_URL=http://localhost:5000
```

### Test from Laravel

From Laravel Tinker or a test script:

```php
use Illuminate\Support\Facades\Http;

$response = Http::get('http://localhost:5000/health');
dd($response->json());
```

---

## Troubleshooting Upload Issues

### Permission Denied

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/bansal-law-crm/python_services

# Fix permissions
sudo chmod -R 755 /var/www/bansal-law-crm/python_services
```

### Python Not Found

```bash
# Install Python 3
sudo apt update
sudo apt install python3 python3-pip  # Ubuntu/Debian

# or
sudo yum install python3 python3-pip  # CentOS/RHEL
```

### Service Won't Start

```bash
# Check logs
sudo journalctl -u migration-python-services -n 50

# Check Python errors
python3 /var/www/bansal-law-crm/python_services/main.py

# Check port
sudo lsof -i :5000
```

### Missing Dependencies

```bash
# Reinstall
cd /var/www/bansal-law-crm/python_services
pip3 install --upgrade -r requirements.txt
```

---

## File Permissions Reference

### Recommended Permissions

```bash
# Directories: 755 (rwxr-xr-x)
sudo find /var/www/bansal-law-crm/python_services -type d -exec chmod 755 {} \;

# Python files: 644 (rw-r--r--)
sudo find /var/www/bansal-law-crm/python_services -type f -name "*.py" -exec chmod 644 {} \;

# Shell scripts: 755 (rwxr-xr-x)
sudo chmod 755 /var/www/bansal-law-crm/python_services/*.sh

# Config files: 640 (rw-r-----)
sudo chmod 640 /var/www/bansal-law-crm/python_services/config.py

# Ownership
sudo chown -R www-data:www-data /var/www/bansal-law-crm/python_services
```

---

## Security Checklist

After upload, verify:

- [ ] Files are owned by www-data (or appropriate user)
- [ ] Sensitive files have restricted permissions
- [ ] Firewall is configured (only necessary ports open)
- [ ] Service runs as non-root user
- [ ] Logs directory is writable
- [ ] Temp directory is writable
- [ ] No sensitive data in config files (use environment variables)

---

## Next Steps After Upload

1. **Test the service**
   ```bash
   curl http://localhost:5000/health
   ```

2. **Configure Nginx** (if needed)
   - See [LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md) → Nginx section

3. **Set up SSL** (if public-facing)
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d your-domain.com
   ```

4. **Set up monitoring**
   ```bash
   # Add to crontab
   */5 * * * * curl -f http://127.0.0.1:5000/health || systemctl restart migration-python-services
   ```

5. **Set up log rotation**
   - See [LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md) → Monitoring section

---

## Quick Command Reference

```bash
# Start service
sudo systemctl start migration-python-services

# Stop service
sudo systemctl stop migration-python-services

# Restart service
sudo systemctl restart migration-python-services

# View status
sudo systemctl status migration-python-services

# View logs
sudo journalctl -u migration-python-services -f

# Test health
curl http://localhost:5000/health

# Update service
cd /var/www/bansal-law-crm/python_services
git pull  # or re-upload files
pip3 install --upgrade -r requirements.txt
sudo systemctl restart migration-python-services
```

---

## Need More Help?

- **[LINUX_START_HERE.md](LINUX_START_HERE.md)** - Documentation index
- **[DEPLOYMENT_SUMMARY.md](DEPLOYMENT_SUMMARY.md)** - Quick overview
- **[LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md)** - Complete deployment guide
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Command reference

---

**You're all set! 🎉**

Your Python services should now be running on your Linux server.

---

Last Updated: October 2025

