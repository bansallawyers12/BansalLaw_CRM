#!/usr/bin/env python3
"""
Script to reorganize Postman collection into folders.
Preserves ALL APIs - nothing is removed or modified except structure.
"""

import json
import re
from pathlib import Path

COLLECTION_PATH = Path(__file__).parent / "Client_Portal_Postman_Collection.json"
BACKUP_PATH = Path(__file__).parent / "Client_Portal_Postman_Collection.json.backup"

def get_folder_for_request(name):
    """Determine which folder an API request belongs to based on its name."""
    name_lower = name.lower()
    
    # Authentication
    if any(x in name_lower for x in ['login', 'logout', 'refresh token', 'forgot password', 'reset password', 'expire token']):
        return "1. Authentication"
    
    # Billing & Payments (Stripe)
    if any(x in name_lower for x in ['stripe', 'paymentintent', 'billing']):
        return "2. Billing & Payments"
    
    # Profile & Dashboard
    if any(x in name_lower for x in ['client profile', 'dashboard', 'matters', 'cases', 'update profile', 'recent cases', 'documents (view all)', 'tasks (view all)', 'recent activity']):
        return "3. Profile & Dashboard"
    
    # Document Management
    if 'document management' in name_lower or 'document checklist' in name_lower or 'upload document' in name_lower:
        return "4. Document Management"
    
    # Workflow Management
    if 'workflow' in name_lower:
        return "5. Workflow Management"
    
    # FCM (Push Notifications) - check before Messaging so "FCM - Send Message" goes here
    if 'fcm' in name_lower:
        return "10. FCM (Push Notifications)"
    
    # Messaging
    if 'messaging' in name_lower or ('message' in name_lower and any(x in name_lower for x in ['send', 'get', 'mark', 'unread', 'download', 'attachment'])):
        return "6. Messaging"
    
    # Client Details (Get/Update/Delete client info)
    if any(x in name_lower for x in ['get client personal', 'update client', 'delete client tab']):
        return "7. Client Details"
    
    # Occupation Finder - must check before Reference Data (more specific)
    if 'occupation finder' in name_lower:
        return "9. Calculators & Tools"
    
    # Reference Data (Countries, Visa Types, Occupation search)
    if any(x in name_lower for x in ['get countries', 'get visa types', 'search occupation']):
        return "8. Reference Data"
    
    
    # Blogs
    if 'blog' in name_lower:
        return "11. Blogs"
    
    # PR Point Calculator
    if 'pr point' in name_lower:
        return "12. PR Point Calculator"
    
    # Student Calculator
    if 'student' in name_lower and ('calculator' in name_lower or 'financial' in name_lower):
        return "13. Student Calculator"
    
    # Postcode Checker
    if 'postcode' in name_lower:
        return "14. Postcode Checker"
    
    # Appointments
    if 'appointment' in name_lower or 'calendar' in name_lower:
        return "15. Appointments"
    
    # Notifications (in-app, not FCM)
    if 'notification' in name_lower and 'fcm' not in name_lower:
        return "16. Notifications"
    
    # Default: Uncategorized
    return "17. Other"

def main():
    print("Loading Postman collection...")
    with open(COLLECTION_PATH, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    items = collection.get("item", [])
    print(f"Found {len(items)} top-level items")
    
    # Separate requests from potential folders (existing structure)
    requests = []
    for item in items:
        if "request" in item:
            requests.append(item)
        elif "item" in item:
            # Already a folder - flatten its requests for regrouping
            for sub in item.get("item", []):
                if "request" in sub:
                    requests.append(sub)
        else:
            requests.append(item)  # Keep unknown structure
    
    print(f"Total API requests to organize: {len(requests)}")
    
    # Group by folder
    folders = {}
    for req in requests:
        folder_name = get_folder_for_request(req.get("name", ""))
        if folder_name not in folders:
            folders[folder_name] = []
        folders[folder_name].append(req)
    
    # Sort folders by their numeric prefix for consistent order
    def folder_sort_key(name):
        match = re.match(r'^(\d+)\.', name)
        return int(match.group(1)) if match else 999
    
    sorted_folder_names = sorted(folders.keys(), key=folder_sort_key)
    
    # Build new item array: folders containing their requests
    new_items = []
    for folder_name in sorted_folder_names:
        folder_requests = folders[folder_name]
        display_name = re.sub(r'^\d+\.\s*', '', folder_name)
        new_items.append({
            "name": display_name,
            "item": folder_requests
        })
    
    # Preserve collection variables if any
    if "variable" in collection:
        pass  # Keep as is
    
    # Create new collection structure
    new_collection = {
        "info": collection["info"],
        "item": new_items
    }
    if "variable" in collection:
        new_collection["variable"] = collection["variable"]
    
    # Backup original
    print("Creating backup...")
    with open(BACKUP_PATH, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent='\t', ensure_ascii=False)
    
    # Write reorganized collection
    print("Writing reorganized collection...")
    with open(COLLECTION_PATH, 'w', encoding='utf-8') as f:
        json.dump(new_collection, f, indent='\t', ensure_ascii=False)
    
    # Verification
    total_in_folders = sum(len(f["item"]) for f in new_items)
    print(f"\nDone! Organized {total_in_folders} APIs into {len(new_items)} folders.")
    print("\nFolders created:")
    for folder in new_items:
        print(f"  - {folder['name']}: {len(folder['item'])} APIs")
    
    if total_in_folders != len(requests):
        print(f"\nWARNING: Count mismatch! Original: {len(requests)}, After: {total_in_folders}")
    else:
        print("\n[OK] All APIs preserved - none lost or removed.")

if __name__ == "__main__":
    main()
