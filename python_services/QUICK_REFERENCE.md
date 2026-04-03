# Python Services - Quick Reference

Quick commands and common tasks for Migration Manager Python Services.

---

## 🚀 Starting the Service

### Windows
```bash
# Quick start
start_services.bat

# Direct start
python main.py

# Development mode (auto-reload)
python main.py --reload
```

### Linux
```bash
# Quick start
./start_services.sh

# Direct start
python3 main.py

# Development mode
python3 main.py --reload

# Production (systemd)
sudo systemctl start migration-python-services
```

### Docker
```bash
# Build and run
docker-compose up -d

# View logs
docker-compose logs -f

# Stop
docker-compose down
```

---

## 🔍 Health Checks

```bash
# Check if service is running
curl http://localhost:5000/health

# Check service info
curl http://localhost:5000/

# Expected response
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

---

## 📡 API Endpoints

### PDF Service
```bash
# Convert PDF to images
curl -X POST http://localhost:5000/pdf/convert-to-images \
  -F "file=@document.pdf"

# Merge PDFs
curl -X POST http://localhost:5000/pdf/merge \
  -F "files=@file1.pdf" \
  -F "files=@file2.pdf"
```

### Email Service
```bash
# Parse email
curl -X POST http://localhost:5000/email/parse \
  -F "file=@email.msg"

# Analyze email
curl -X POST http://localhost:5000/email/analyze \
  -H "Content-Type: application/json" \
  -d '{"subject":"Meeting","html_content":"<p>Hello</p>"}'

# Complete pipeline (parse + analyze + render)
curl -X POST http://localhost:5000/email/parse-analyze-render \
  -F "file=@email.msg"
```

---

## 📊 Monitoring

### View Logs

#### Windows
```bash
# Application logs
type logs\service.log

# Error logs
type logs\error.log

# Real-time monitoring
powershell Get-Content logs\service.log -Wait
```

#### Linux
```bash
# Application logs
tail -f logs/service.log

# Error logs
tail -f logs/error.log

# Systemd logs
sudo journalctl -u migration-python-services -f

# Last 100 lines
sudo journalctl -u migration-python-services -n 100
```

#### Docker
```bash
# View logs
docker logs -f migration-python-services

# Last 100 lines
docker logs --tail 100 migration-python-services
```

---

## 🔧 Service Management

### Linux Systemd

```bash
# Start
sudo systemctl start migration-python-services

# Stop
sudo systemctl stop migration-python-services

# Restart
sudo systemctl restart migration-python-services

# Status
sudo systemctl status migration-python-services

# Enable on boot
sudo systemctl enable migration-python-services

# Disable on boot
sudo systemctl disable migration-python-services

# View logs
sudo journalctl -u migration-python-services -f
```

### Windows Service (NSSM)

```bash
# Install service
nssm install BansalLawPythonServices "C:\Python39\python.exe"
nssm set BansalLawPythonServices AppDirectory "C:\xampp\htdocs\BansalLaw_CRM\python_services"
nssm set BansalLawPythonServices AppParameters "main.py"

# Start service
nssm start BansalLawPythonServices

# Stop service
nssm stop BansalLawPythonServices

# Restart service
nssm restart BansalLawPythonServices

# Remove service
nssm remove BansalLawPythonServices confirm
```

### Docker

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Restart
docker-compose restart

# Rebuild
docker-compose up -d --build

# View status
docker-compose ps

# View logs
docker-compose logs -f
```

---

## 🔍 Troubleshooting

### Check if Port is in Use

#### Windows
```bash
netstat -ano | findstr :5000
taskkill /PID <PID> /F
```

#### Linux
```bash
sudo lsof -i :5000
sudo kill -9 <PID>
```

### Test Dependencies

```bash
# Test Python
python --version
python3 --version

# Test pip
pip --version
pip3 --version

# Test imports
python -c "import fastapi, uvicorn, extract_msg"
```

### Reinstall Dependencies

```bash
# Windows
pip install --upgrade -r requirements.txt

# Linux
pip3 install --upgrade -r requirements.txt

# Force reinstall
pip install --force-reinstall -r requirements.txt
```

### Check File Permissions (Linux)

```bash
# Check ownership
ls -la /var/www/bansal-law-crm/python_services/

# Fix permissions
sudo chown -R www-data:www-data /var/www/bansal-law-crm/python_services/
sudo chmod -R 755 /var/www/bansal-law-crm/python_services/
```

---

## 🧪 Testing

### Quick Test

```bash
# Run test script
python test_service.py

# Expected output
✅ Service is running
✅ All services are healthy
```

### Manual Tests

```bash
# Test health
curl http://localhost:5000/health

# Test PDF conversion (need actual PDF file)
curl -X POST http://localhost:5000/pdf/convert-to-images \
  -F "file=@test.pdf"

# Test email parsing (need actual .msg file)
curl -X POST http://localhost:5000/email/parse \
  -F "file=@test.msg"
```

---

## 📦 Installation

### Quick Install

#### Windows
```bash
cd C:\xampp\htdocs\BansalLaw_CRM\python_services
pip install -r requirements.txt
start_services.bat
```

#### Linux
```bash
cd /var/www/bansal-law-crm/python_services
pip3 install -r requirements.txt
chmod +x start_services.sh
./start_services.sh
```

#### Docker
```bash
cd /var/www/bansal-law-crm/python_services
docker-compose up -d
```

---

## 🔄 Updates

### Update Service

```bash
# Pull latest code
git pull

# Update dependencies
pip install --upgrade -r requirements.txt

# Restart service (Linux)
sudo systemctl restart migration-python-services

# Restart service (Docker)
docker-compose restart
```

---

## 📝 Configuration

### Environment Variables

```bash
# Create .env file
SERVICE_HOST=127.0.0.1
SERVICE_PORT=5000
DEBUG=False
LOG_LEVEL=INFO
MAX_FILE_SIZE_MB=20
ALLOWED_PDF_SIZE_MB=50
```

### Apply Configuration

```bash
# Linux (add to /etc/environment or .env file)
export SERVICE_HOST=127.0.0.1
export SERVICE_PORT=5000

# Windows (Command Prompt)
set SERVICE_HOST=127.0.0.1
set SERVICE_PORT=5000

# Windows (PowerShell)
$env:SERVICE_HOST="127.0.0.1"
$env:SERVICE_PORT="5000"
```

---

## 🔐 Security

### Firewall Rules

#### Linux (UFW)
```bash
# Allow port 5000
sudo ufw allow 5000/tcp

# Remove rule
sudo ufw delete allow 5000/tcp
```

#### Linux (firewalld)
```bash
# Allow port 5000
sudo firewall-cmd --permanent --add-port=5000/tcp
sudo firewall-cmd --reload

# Remove rule
sudo firewall-cmd --permanent --remove-port=5000/tcp
sudo firewall-cmd --reload
```

#### Windows Firewall
```powershell
# Allow port 5000
netsh advfirewall firewall add rule name="Python Services" dir=in action=allow protocol=TCP localport=5000

# Remove rule
netsh advfirewall firewall delete rule name="Python Services"
```

---

## 📞 Support

### Documentation Files

- **[README.md](README.md)** - Main documentation
- **[LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md)** - Linux production deployment
- **[PYTHON_SERVICES_START_HERE.md](../PYTHON_SERVICES_START_HERE.md)** - Getting started guide
- **[PYTHON_SERVICES_MASTER_GUIDE.md](../PYTHON_SERVICES_MASTER_GUIDE.md)** - Complete guide

### Common Issues

1. **Port already in use**: Kill the process using port 5000
2. **Module not found**: Reinstall dependencies
3. **Permission denied**: Fix file permissions
4. **Service won't start**: Check logs for errors

---

## 📊 Performance

### Resource Usage

```bash
# Check memory usage (Linux)
ps aux | grep python

# Check CPU usage
top -p $(pgrep -f "main.py")

# Docker stats
docker stats migration-python-services
```

### Optimize Performance

```bash
# Use Gunicorn for production (Linux)
pip install gunicorn
gunicorn main:app -w 4 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:5000

# Number of workers = (2 x CPU cores) + 1
```

---

**Last Updated:** October 2025

