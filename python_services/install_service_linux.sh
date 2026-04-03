#!/bin/bash
# Migration Manager Python Services - Linux System Service Installer
# This script installs the Python service as a systemd service on Linux

set -e

SERVICE_NAME="migration-python-services"
SERVICE_USER="${SUDO_USER:-$USER}"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "============================================================"
echo "Migration Manager Python Services - Linux Service Installer"
echo "============================================================"
echo ""
echo "This script will install the Python services as a systemd service"
echo "Service name: $SERVICE_NAME"
echo "Service user: $SERVICE_USER"
echo "Service directory: $SCRIPT_DIR"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root"
    echo "   Please run: sudo $0"
    exit 1
fi

# Check if Python is available
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed"
    echo "   Please install Python 3.7+ first"
    exit 1
fi

# Check if main.py exists
if [ ! -f "$SCRIPT_DIR/main.py" ]; then
    echo "❌ Error: main.py not found in $SCRIPT_DIR"
    exit 1
fi

# Install dependencies
echo "📦 Installing Python dependencies..."
python3 -m pip install -r "$SCRIPT_DIR/requirements.txt"

# Create systemd service file
SYSTEMD_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

echo "📝 Creating systemd service file: $SYSTEMD_FILE"

cat > "$SYSTEMD_FILE" << EOF
[Unit]
Description=Migration Manager Python Services
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
WorkingDirectory=$SCRIPT_DIR
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
ExecStart=$(which python3) $SCRIPT_DIR/main.py --host 127.0.0.1 --port 5000
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=$SERVICE_NAME

# Security settings
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

# Set correct permissions
chmod 644 "$SYSTEMD_FILE"

# Reload systemd
echo "♻️  Reloading systemd daemon..."
systemctl daemon-reload

# Enable service
echo "✅ Enabling service to start on boot..."
systemctl enable "$SERVICE_NAME"

# Start service
echo "🚀 Starting service..."
systemctl start "$SERVICE_NAME"

# Check status
sleep 2
systemctl status "$SERVICE_NAME" --no-pager

echo ""
echo "============================================================"
echo "✅ Service installed successfully!"
echo "============================================================"
echo ""
echo "Service commands:"
echo "  Start:   sudo systemctl start $SERVICE_NAME"
echo "  Stop:    sudo systemctl stop $SERVICE_NAME"
echo "  Restart: sudo systemctl restart $SERVICE_NAME"
echo "  Status:  sudo systemctl status $SERVICE_NAME"
echo "  Logs:    sudo journalctl -u $SERVICE_NAME -f"
echo ""
echo "To remove the service:"
echo "  sudo systemctl stop $SERVICE_NAME"
echo "  sudo systemctl disable $SERVICE_NAME"
echo "  sudo rm $SYSTEMD_FILE"
echo "  sudo systemctl daemon-reload"
echo ""

