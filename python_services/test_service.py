#!/usr/bin/env python3
"""
Test script for Migration Manager Python Services

This script tests the unified Python service to ensure it's working correctly.
"""

import sys
import time
import subprocess
import requests
from pathlib import Path

def test_service_startup():
    """Test if the service can start without errors."""
    print("Testing service startup...")
    
    try:
        # Test import
        sys.path.insert(0, str(Path(__file__).parent))
        from main import app
        print("OK - Service imports successfully")
        
        # Test service initialization
        from services.pdf_service import PDFService
        from services.email_parser_service import EmailParserService
        from services.email_analyzer_service import EmailAnalyzerService
        from services.email_renderer_service import EmailRendererService
        
        print("OK - All services initialize successfully")
        return True
        
    except Exception as e:
        print(f"FAILED - Service startup failed: {e}")
        return False

def test_health_endpoint():
    """Test the health endpoint."""
    print("\nTesting health endpoint...")
    
    try:
        # Start service in background
        process = subprocess.Popen([
            sys.executable, 'main.py', '--host', '127.0.0.1', '--port', '5000'
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        # Wait for service to start
        time.sleep(3)
        
        # Test health endpoint
        response = requests.get('http://127.0.0.1:5000/health', timeout=5)
        
        if response.status_code == 200:
            print("OK - Health endpoint working")
            data = response.json()
            print(f"   Status: {data.get('status', 'unknown')}")
            return True
        else:
            print(f"FAILED - Health endpoint failed: {response.status_code}")
            return False
            
    except requests.exceptions.ConnectionError:
        print("FAILED - Could not connect to service")
        return False
    except Exception as e:
        print(f"FAILED - Health endpoint test failed: {e}")
        return False
    finally:
        # Clean up
        try:
            process.terminate()
            process.wait(timeout=5)
        except:
            process.kill()

def test_email_analysis():
    """Test email analysis functionality."""
    print("\nTesting email analysis...")
    
    try:
        from services.email_analyzer_service import EmailAnalyzerService
        
        analyzer = EmailAnalyzerService()
        
        # Test data
        email_data = {
            'subject': 'Test Email Subject',
            'text_content': 'This is a test email for migration purposes.',
            'html_content': '<p>This is a test email for migration purposes.</p>',
            'sender_email': 'test@example.com',
            'sender_name': 'Test User'
        }
        
        # Analyze
        result = analyzer.analyze_content(email_data)
        
        if result and 'category' in result:
            print("OK - Email analysis working")
            print(f"   Category: {result.get('category', 'unknown')}")
            print(f"   Priority: {result.get('priority', 'unknown')}")
            print(f"   Sentiment: {result.get('sentiment', 'unknown')}")
            return True
        else:
            print("FAILED - Email analysis failed")
            return False
            
    except Exception as e:
        print(f"FAILED - Email analysis test failed: {e}")
        return False

def test_email_rendering():
    """Test email rendering functionality."""
    print("\nTesting email rendering...")
    
    try:
        from services.email_renderer_service import EmailRendererService
        
        renderer = EmailRendererService()
        
        # Test data
        email_data = {
            'subject': 'Test Email Subject',
            'html_content': '<p>This is a test email with <strong>HTML</strong> content.</p>',
            'text_content': 'This is a test email with HTML content.',
            'sender_email': 'test@example.com',
            'sender_name': 'Test User'
        }
        
        # Render
        result = renderer.render_email(email_data)
        
        if result and 'rendered_html' in result:
            print("OK - Email rendering working")
            print(f"   Rendered HTML length: {len(result.get('rendered_html', ''))}")
            print(f"   Text preview length: {len(result.get('text_preview', ''))}")
            return True
        else:
            print("FAILED - Email rendering failed")
            return False
            
    except Exception as e:
        print(f"FAILED - Email rendering test failed: {e}")
        return False

def main():
    """Run all tests."""
    print("=" * 60)
    print("Migration Manager Python Services - Test Suite")
    print("=" * 60)
    
    tests = [
        test_service_startup,
        test_email_analysis,
        test_email_rendering,
        test_health_endpoint
    ]
    
    passed = 0
    total = len(tests)
    
    for test in tests:
        if test():
            passed += 1
        print()
    
    print("=" * 60)
    print(f"Test Results: {passed}/{total} tests passed")
    
    if passed == total:
        print("SUCCESS - All tests passed! Service is ready to use.")
        return 0
    else:
        print("WARNING - Some tests failed. Check the output above.")
        return 1

if __name__ == '__main__':
    sys.exit(main())
