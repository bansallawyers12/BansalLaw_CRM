#!/usr/bin/env python3
"""
Startup script for Migration Manager Python Services

This script provides cross-platform startup for the unified Python services.
It handles environment setup, dependency checking, and service startup.
"""

import sys
import os
import subprocess
import platform
import time
from pathlib import Path

def check_python_version():
    """Check if Python version is compatible."""
    if sys.version_info < (3, 7):
        print("❌ Error: Python 3.7 or higher is required")
        print(f"   Current version: {sys.version}")
        return False
    print(f"✅ Python version: {sys.version.split()[0]}")
    return True

def check_dependencies():
    """Check if required dependencies are installed."""
    # Map pip package names to their actual import names
    PACKAGE_IMPORT_MAP = {
        'beautifulsoup4': 'bs4',
        'extract-msg': 'extract_msg',
        'PyPDF2': 'PyPDF2',
        'PyMuPDF': 'fitz'
    }
    
    # Required packages (pip names for display, import names for checking)
    required_packages = {
        'fastapi': 'fastapi',
        'uvicorn': 'uvicorn',
        'pydantic': 'pydantic',
        'extract-msg': 'extract_msg',
        'beautifulsoup4': 'bs4'
    }
    
    missing_packages = []
    
    for package_name, import_name in required_packages.items():
        try:
            __import__(import_name)
            print(f"✅ {package_name}")
        except ImportError:
            missing_packages.append(package_name)
            print(f"❌ {package_name} - Missing")
    
    if missing_packages:
        print(f"\n❌ Missing packages: {', '.join(missing_packages)}")
        print("   Run: pip install -r requirements.txt")
        return False
    
    return True

def install_dependencies():
    """Install required dependencies."""
    print("\n📦 Installing dependencies...")
    
    try:
        requirements_file = Path(__file__).parent / 'requirements.txt'
        if requirements_file.exists():
            subprocess.run([
                sys.executable, '-m', 'pip', 'install', '-r', str(requirements_file)
            ], check=True)
            print("✅ Dependencies installed successfully")
            return True
        else:
            print("❌ requirements.txt not found")
            return False
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        return False

def start_service(host='127.0.0.1', port=5001, reload=False):
    """Start the Python service."""
    print(f"\n🚀 Starting Migration Manager Python Services...")
    print(f"   Host: {host}")
    print(f"   Port: {port}")
    print(f"   Reload: {reload}")
    print(f"   URL: http://{host}:{port}")
    print(f"   Health: http://{host}:{port}/health")
    
    try:
        # Change to the script directory
        script_dir = Path(__file__).parent
        os.chdir(script_dir)
        
        # Start the service
        cmd = [
            sys.executable, 'main.py',
            '--host', host,
            '--port', str(port)
        ]
        
        if reload:
            cmd.append('--reload')
        
        print(f"\n📝 Command: {' '.join(cmd)}")
        print("=" * 60)
        
        subprocess.run(cmd, check=True)
        
    except KeyboardInterrupt:
        print("\n\n⏹️  Service stopped by user")
    except subprocess.CalledProcessError as e:
        print(f"\n❌ Service failed to start: {e}")
        return False
    except Exception as e:
        print(f"\n❌ Unexpected error: {e}")
        return False
    
    return True

def create_windows_service():
    """Create Windows service using NSSM (if available)."""
    print("\n🔧 Windows Service Setup")
    print("   To install as Windows Service:")
    print("   1. Download NSSM from https://nssm.cc/download")
    print("   2. Extract to C:\\nssm")
    print("   3. Run as Administrator:")
    print(f"      C:\\nssm\\win64\\nssm.exe install PythonServices")
    print(f"      C:\\nssm\\win64\\nssm.exe set PythonServices Application {sys.executable}")
    print(f"      C:\\nssm\\win64\\nssm.exe set PythonServices AppDirectory {Path(__file__).parent}")
    print(f"      C:\\nssm\\win64\\nssm.exe set PythonServices AppParameters main.py")
    print("      C:\\nssm\\win64\\nssm.exe start PythonServices")

def main():
    """Main startup function."""
    print("=" * 60)
    print("🚀 Migration Manager Python Services Startup")
    print("=" * 60)
    
    # Check Python version
    if not check_python_version():
        return 1
    
    # Check dependencies
    if not check_dependencies():
        print("\n❓ Would you like to install missing dependencies? (y/n): ", end="")
        if input().lower() in ['y', 'yes']:
            if not install_dependencies():
                return 1
        else:
            return 1
    
    # Parse command line arguments
    import argparse
    parser = argparse.ArgumentParser(description='Start Migration Manager Python Services')
    parser.add_argument('--host', default='127.0.0.1', help='Host to bind to')
    parser.add_argument('--port', type=int, default=5001, help='Port to bind to')
    parser.add_argument('--reload', action='store_true', help='Enable auto-reload for development')
    parser.add_argument('--install-deps', action='store_true', help='Install dependencies and exit')
    parser.add_argument('--windows-service', action='store_true', help='Show Windows service setup instructions')
    
    args = parser.parse_args()
    
    # Install dependencies and exit
    if args.install_deps:
        if install_dependencies():
            print("✅ Dependencies installed successfully")
            return 0
        else:
            return 1
    
    # Show Windows service setup
    if args.windows_service:
        create_windows_service()
        return 0
    
    # Start the service
    if start_service(args.host, args.port, args.reload):
        return 0
    else:
        return 1

if __name__ == '__main__':
    sys.exit(main())
