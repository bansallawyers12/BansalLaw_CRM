# Unified Python Services for Migration Manager

## 🎯 Overview

This is a **unified microservice** that consolidates all Python-based operations for the Migration Manager application:

- **PDF Processing**: Convert, merge, extract from PDFs
- **Email Parsing**: Parse .msg files using `extract_msg`
- **Email Analysis**: AI-powered categorization, priority detection, sentiment analysis
- **Email Rendering**: Enhanced HTML rendering with security
- **Document Conversion**: DOCX/DOC to PDF conversion

## 🏗️ Architecture

### Why Unified Service?

Instead of having multiple separate Python services (`python_pdf_service/`, `python_outlook_web/`, etc.), we consolidate everything into a single FastAPI service.

**Benefits:**
- ✅ Single service to start/stop/monitor
- ✅ Shared dependencies (no duplication)
- ✅ One port instead of multiple (5000)
- ✅ Centralized logging
- ✅ Easier maintenance
- ✅ Better resource utilization
- ✅ Simplified deployment

### Directory Structure

```
python_services/
├── main.py                         # FastAPI application (main entry point)
├── requirements.txt                # All Python dependencies
├── config.py                       # Configuration
├── start_services.py               # Startup script (cross-platform)
├── start_services.bat              # Windows startup
├── start_services.sh               # Linux startup
├── install_service_linux.sh        # Linux service installer
│
├── services/                       # Service modules
│   ├── __init__.py
│   ├── pdf_service.py              # PDF operations
│   ├── email_parser_service.py     # .msg file parsing
│   ├── email_analyzer_service.py   # Email content analysis
│   ├── email_renderer_service.py   # Email HTML rendering
│   └── document_converter_service.py # Document conversion
│
├── utils/                          # Shared utilities
│   ├── __init__.py
│   ├── logger.py                   # Centralized logging
│   ├── validators.py               # File/data validation
│   └── security.py                 # Security utilities
│
├── models/                         # Data models (Pydantic)
│   ├── __init__.py
│   ├── email_models.py
│   └── pdf_models.py
│
├── logs/                           # Log files
│   ├── pdf_service.log
│   ├── email_service.log
│   └── combined-2025-10-25.log
│
└── tests/                          # Tests
    ├── test_pdf_service.py
    ├── test_email_service.py
    └── test_integration.py
```

## 🚀 Installation

### Windows

#### 1. Install Python Dependencies

```bash
cd C:\xampp\htdocs\BansalLaw_CRM\python_services
pip install -r requirements.txt
```

#### 2. Start Service

**Option A: Using batch file (recommended)**
```bash
start_services.bat
```

**Option B: Using Python script**
```bash
python start_services.py
```

**Option C: Direct start**
```bash
python main.py --host 127.0.0.1 --port 5000
```

### Linux

#### 1. Install Python Dependencies

```bash
cd /var/www/bansal-law-crm/python_services
python3 -m pip install -r requirements.txt
```

#### 2. Start Service

**Option A: Using shell script (recommended)**
```bash
chmod +x start_services.sh
./start_services.sh
```

**Option B: Install as systemd service (production)**
```bash
chmod +x install_service_linux.sh
sudo ./install_service_linux.sh
```

**Option C: Direct start**
```bash
python3 main.py --host 127.0.0.1 --port 5000
```

For detailed Linux deployment instructions, see **[LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md)**

## 📡 API Endpoints

### Health Check

```http
GET /
GET /health
```

### PDF Service

```http
POST /pdf/convert-to-images
POST /pdf/merge
POST /pdf/extract-text
```

### Email Service

```http
POST /email/parse              # Parse .msg file
POST /email/analyze            # Analyze email content
POST /email/render             # Render enhanced HTML
POST /email/parse-analyze-render  # Complete pipeline
```

## 💻 Usage from Laravel

### 1. PDF Processing

```php
use Illuminate\Support\Facades\Http;

// Convert PDF to images
$response = Http::timeout(120)
    ->attach('file', file_get_contents($pdfPath), 'document.pdf')
    ->post('http://localhost:5000/pdf/convert-to-images');

$result = $response->json();
// {
//     "success": true,
//     "total_pages": 3,
//     "images": [...]
// }
```

### 2. Email Parsing

```php
// Parse .msg file
$response = Http::timeout(120)
    ->attach('file', file_get_contents($msgPath), 'email.msg')
    ->post('http://localhost:5000/email/parse');

$emailData = $response->json();
// {
//     "subject": "...",
//     "sender_email": "...",
//     "html_content": "...",
//     ...
// }
```

### 3. Email Analysis

```php
// Analyze email content
$response = Http::timeout(120)
    ->post('http://localhost:5000/email/analyze', [
        'subject' => $email->subject,
        'html_content' => $email->html_content,
        'text_content' => $email->text_content,
        ...
    ]);

$analysis = $response->json();
// {
//     "category": "Business",
//     "priority": "high",
//     "sentiment": "positive",
//     "security_issues": [],
//     ...
// }
```

### 4. Complete Email Pipeline

```php
// Parse + Analyze + Render in one call
$response = Http::timeout(180)
    ->attach('file', file_get_contents($msgPath), 'email.msg')
    ->post('http://localhost:5000/email/parse-analyze-render');

$result = $response->json();
// Returns complete email data with analysis and rendering
```

## 🔧 Configuration

### Environment Variables

Create a `.env` file in `python_services/`:

```env
# Service Configuration
SERVICE_HOST=127.0.0.1
SERVICE_PORT=5000
DEBUG=False

# File Upload Limits
MAX_FILE_SIZE_MB=20
ALLOWED_PDF_SIZE_MB=50

# PDF Processing
PDF_MAX_DPI=300
PDF_DEFAULT_DPI=150

# Email Processing
EMAIL_MAX_SIZE_MB=20
EMAIL_PARSE_TIMEOUT=60

# Logging
LOG_LEVEL=INFO
LOG_RETENTION_DAYS=30
```

## 📊 Service Comparison

### Before: Multiple Separate Services

```
python_pdf_service/      → Port 5000
python_outlook_web/      → Port 5001
python_email_renderer/   → Port 5002
python/                  → Standalone scripts

Problems:
- Multiple services to manage
- Duplicate dependencies
- Complex orchestration
- Higher resource usage
- Multiple log files
```

### After: Unified Service

```
python_services/         → Port 5000 (all services)

Benefits:
- Single service
- Shared dependencies
- Simple management
- Lower resource usage
- Centralized logging
```

## 🔐 Security

### Input Validation
- File type checking
- File size limits
- MIME type verification
- Path traversal prevention

### Content Security
- HTML sanitization
- XSS protection
- Script removal
- Dangerous element filtering

### Error Handling
- Graceful error responses
- Detailed logging
- No sensitive data in errors

## 📈 Performance

### Async Processing
- FastAPI async endpoints
- Non-blocking I/O
- Concurrent request handling

### Resource Management
- Memory limits for file processing
- Timeout configuration
- Clean temporary file handling

### Caching
- Response caching (optional)
- File processing cache
- Analysis result caching

## 🧪 Testing

```bash
# Run all tests
pytest

# Run specific test
pytest tests/test_email_service.py

# With coverage
pytest --cov=services --cov-report=html
```

## 📝 Logging

Logs are stored in `logs/` directory:

```
logs/
├── combined-2025-10-25.log     # All services
├── pdf_service.log             # PDF specific
├── email_service.log           # Email specific
└── error.log                   # Errors only
```

Log format:
```
2025-10-25 12:30:45 - services.email_parser - INFO - Parsing email file: test.msg
```

## 🔄 Migration from Old Structure

### Step 1: Install Dependencies
```bash
cd python_services
pip install -r requirements.txt
```

### Step 2: Update Laravel .env
```env
# Old
# PYTHON_PDF_SERVICE_URL=http://localhost:5000
# PYTHON_EMAIL_SERVICE_URL=http://localhost:5001

# New
PYTHON_SERVICE_URL=http://localhost:5000
```

### Step 3: Update Service Calls

**Old:**
```php
Http::post('http://localhost:5000/convert-pdf')      // PDF service
Http::post('http://localhost:5001/parse-email')       // Email service
```

**New:**
```php
Http::post('http://localhost:5000/pdf/convert-to-images')
Http::post('http://localhost:5000/email/parse')
```

### Step 4: Start Unified Service
```bash
python python_services/main.py
```

### Step 5: Stop Old Services
```bash
# Stop old PDF service
# Stop old email service
# etc.
```

## 🚀 Deployment

### Development
```bash
# Windows
python main.py --reload

# Linux
python3 main.py --reload
```

### Production (Windows)
```bash
# Option 1: Using NSSM (recommended)
# 1. Download NSSM from https://nssm.cc/download
# 2. Install as service:
nssm install BansalLawPythonServices "C:\Python39\python.exe" "C:\xampp\htdocs\BansalLaw_CRM\python_services\main.py"
nssm set BansalLawPythonServices AppDirectory "C:\xampp\htdocs\BansalLaw_CRM\python_services"
nssm set BansalLawPythonServices AppParameters "--host 127.0.0.1 --port 5000"
nssm start BansalLawPythonServices

# Option 2: Using Python script
python start_services.py --windows-service
```

### Production (Linux)
```bash
# Automated installation (recommended)
cd /var/www/bansal-law-crm/python_services
sudo ./install_service_linux.sh

# Service management
sudo systemctl start migration-python-services
sudo systemctl stop migration-python-services
sudo systemctl restart migration-python-services
sudo systemctl status migration-python-services

# View logs
sudo journalctl -u migration-python-services -f
```

**📘 Complete Linux deployment guide:** [LINUX_DEPLOYMENT.md](LINUX_DEPLOYMENT.md)

### Docker (Optional)
```bash
docker build -t migration-manager-python-services .
docker run -d -p 5000:5000 --name python-services migration-manager-python-services
```

## 📞 Support

For issues:
1. Check logs in `logs/` directory
2. Verify service is running: `curl http://localhost:5000/health`
3. Test individual endpoints
4. Review error messages

## 🎯 Future Enhancements

- [ ] Machine learning models for better categorization
- [ ] Real-time WebSocket support
- [ ] Advanced caching layer (Redis)
- [ ] Horizontal scaling support
- [ ] Prometheus metrics
- [ ] GraphQL API option
- [ ] Admin dashboard
- [ ] Rate limiting
- [ ] API versioning

## 📄 License

Internal use - Migration Manager Application

