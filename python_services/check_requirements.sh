#!/bin/bash
# Migration Manager Python Services - Pre-deployment Check Script
# This script verifies that all requirements are met for Linux deployment

echo "=========================================================="
echo "Migration Manager Python Services - Pre-deployment Check"
echo "=========================================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ PASS${NC} - $2"
    else
        echo -e "${RED}❌ FAIL${NC} - $2"
        ERRORS=$((ERRORS + 1))
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  WARNING${NC} - $1"
    WARNINGS=$((WARNINGS + 1))
}

print_info() {
    echo -e "ℹ️  INFO - $1"
}

echo "Checking system requirements..."
echo ""

# Check Python version
print_info "Checking Python installation..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version 2>&1 | awk '{print $2}')
    PYTHON_MAJOR=$(echo $PYTHON_VERSION | cut -d. -f1)
    PYTHON_MINOR=$(echo $PYTHON_VERSION | cut -d. -f2)
    
    if [ "$PYTHON_MAJOR" -ge 3 ] && [ "$PYTHON_MINOR" -ge 7 ]; then
        print_status 0 "Python $PYTHON_VERSION (>= 3.7 required)"
    else
        print_status 1 "Python $PYTHON_VERSION found (3.7+ required)"
    fi
else
    print_status 1 "Python 3 not found"
fi

# Check pip
print_info "Checking pip..."
if command -v pip3 &> /dev/null; then
    PIP_VERSION=$(pip3 --version 2>&1 | awk '{print $2}')
    print_status 0 "pip $PIP_VERSION installed"
else
    print_status 1 "pip3 not found"
fi

# Check curl
print_info "Checking curl..."
if command -v curl &> /dev/null; then
    print_status 0 "curl installed"
else
    print_warning "curl not found (recommended for health checks)"
fi

# Check systemd
print_info "Checking systemd..."
if command -v systemctl &> /dev/null; then
    print_status 0 "systemd available"
else
    print_warning "systemd not found (required for service installation)"
fi

# Check if main.py exists
print_info "Checking service files..."
if [ -f "main.py" ]; then
    print_status 0 "main.py found"
else
    print_status 1 "main.py not found (are you in the correct directory?)"
fi

# Check if requirements.txt exists
if [ -f "requirements.txt" ]; then
    print_status 0 "requirements.txt found"
else
    print_status 1 "requirements.txt not found"
fi

# Check if startup scripts exist
if [ -f "start_services.sh" ]; then
    print_status 0 "start_services.sh found"
    
    # Check if executable
    if [ -x "start_services.sh" ]; then
        print_status 0 "start_services.sh is executable"
    else
        print_warning "start_services.sh is not executable (run: chmod +x start_services.sh)"
    fi
else
    print_status 1 "start_services.sh not found"
fi

# Check port 5000 availability
print_info "Checking port availability..."
if command -v lsof &> /dev/null; then
    if lsof -Pi :5000 -sTCP:LISTEN -t &> /dev/null; then
        print_warning "Port 5000 is already in use"
    else
        print_status 0 "Port 5000 is available"
    fi
elif command -v netstat &> /dev/null; then
    if netstat -tuln | grep -q ":5000 "; then
        print_warning "Port 5000 may be in use"
    else
        print_status 0 "Port 5000 appears available"
    fi
else
    print_warning "Cannot check port availability (lsof/netstat not found)"
fi

# Check Python modules
print_info "Checking Python dependencies..."
MODULES=("fastapi" "uvicorn" "extract_msg" "pydantic" "beautifulsoup4")
MISSING_MODULES=()

for module in "${MODULES[@]}"; do
    if python3 -c "import $module" &> /dev/null; then
        print_status 0 "$module is installed"
    else
        print_status 1 "$module is NOT installed"
        MISSING_MODULES+=($module)
    fi
done

# Check directory permissions
print_info "Checking directory permissions..."
if [ -w "." ]; then
    print_status 0 "Current directory is writable"
else
    print_status 1 "Current directory is not writable"
fi

# Check if logs directory exists and is writable
if [ -d "logs" ]; then
    if [ -w "logs" ]; then
        print_status 0 "logs/ directory is writable"
    else
        print_warning "logs/ directory is not writable"
    fi
else
    print_info "logs/ directory will be created on first run"
fi

# Check if temp directory exists and is writable
if [ -d "temp" ]; then
    if [ -w "temp" ]; then
        print_status 0 "temp/ directory is writable"
    else
        print_warning "temp/ directory is not writable"
    fi
else
    print_info "temp/ directory will be created on first run"
fi

# Check available disk space
print_info "Checking disk space..."
AVAILABLE_SPACE=$(df -BG . | tail -1 | awk '{print $4}' | sed 's/G//')
if [ "$AVAILABLE_SPACE" -ge 1 ]; then
    print_status 0 "Sufficient disk space available (${AVAILABLE_SPACE}G)"
else
    print_warning "Low disk space (${AVAILABLE_SPACE}G available)"
fi

# Check available memory
print_info "Checking system memory..."
if command -v free &> /dev/null; then
    AVAILABLE_MEM=$(free -m | awk '/^Mem:/{print $7}')
    if [ "$AVAILABLE_MEM" -ge 512 ]; then
        print_status 0 "Sufficient memory available (${AVAILABLE_MEM}MB)"
    else
        print_warning "Low available memory (${AVAILABLE_MEM}MB)"
    fi
else
    print_warning "Cannot check memory (free command not found)"
fi

# Check if running as root (for service installation)
print_info "Checking user privileges..."
if [ "$EUID" -eq 0 ]; then
    print_info "Running as root (can install system service)"
else
    print_info "Not running as root (use 'sudo' for service installation)"
fi

# Summary
echo ""
echo "=========================================================="
echo "Summary"
echo "=========================================================="
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✅ All checks passed!${NC}"
    echo ""
    echo "You can proceed with installation:"
    echo "  1. Install dependencies: pip3 install -r requirements.txt"
    echo "  2. Start service: ./start_services.sh"
    echo "  3. Or install as system service: sudo ./install_service_linux.sh"
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  All critical checks passed with $WARNINGS warnings${NC}"
    echo ""
    echo "You can proceed with installation, but please review warnings above."
else
    echo -e "${RED}❌ $ERRORS critical errors found${NC}"
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  $WARNINGS warnings found${NC}"
    fi
    echo ""
    echo "Please fix the errors above before proceeding."
    
    if [ ${#MISSING_MODULES[@]} -gt 0 ]; then
        echo ""
        echo "To install missing Python modules, run:"
        echo "  pip3 install -r requirements.txt"
    fi
fi

echo ""
echo "For more information, see:"
echo "  - README.md - Main documentation"
echo "  - LINUX_DEPLOYMENT.md - Linux deployment guide"
echo "  - QUICK_REFERENCE.md - Quick command reference"
echo ""

exit $ERRORS

