<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stockki General Application Language Lines (English)
    |--------------------------------------------------------------------------
    */

    'app_name' => 'Stockki',
    'tagline' => 'Smart Multi-Tenant Stock & Expiry Control',

    // Landing Page
    'landing' => [
        'badge' => 'Next-Gen Inventory & Expiry Control',
        'hero_title' => 'Zero Expired Waste. Total Inventory Confidence.',
        'hero_subtitle' => 'Automate batch tracking, monitor critical shelf-life deadlines in real-time, and extract packaging data with instant optical recognition.',
        'cta_start' => 'Start Free Trial',
        'cta_demo' => 'Live Interactive Demo',
        'cta_login' => 'Sign In',
        'features_title' => 'Engineered for Complete Stock Reliability',
        'features_subtitle' => 'Eliminate expired write-offs with automated tracking, optical recognition, and multi-tenant security.',
        'feat_expiry_title' => 'Real-Time Expiry Analytics',
        'feat_expiry_desc' => 'Automated shelf-life calculation flagging products safe, expiring within 30 days, or expired.',
        'feat_ocr_title' => 'Optical Label Scanner',
        'feat_ocr_desc' => 'Use your camera or upload product labels to extract SKU, lot number, and expiry date in seconds.',
        'feat_rbac_title' => 'Granular Team Permissions',
        'feat_rbac_desc' => 'Define custom roles and access rights across administrators, inventory managers, and stock operators.',
        'feat_tenant_title' => 'Multi-Tenant Data Isolation',
        'feat_tenant_desc' => 'Enterprise-grade isolation ensuring zero cross-tenant contamination with encrypted boundaries.',
    ],

    // Auth (Login / Register)
    'auth' => [
        'login_title' => 'Sign in to your workspace',
        'login_subtitle' => 'Enter your credentials to manage your inventory and expiry alerts',
        'register_title' => 'Create your workspace',
        'register_subtitle' => 'Start monitoring your inventory and eliminating product expiration waste today',
        'email' => 'Email Address',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'workspace_name' => 'Workspace / Company Name',
        'full_name' => 'Full Name',
        'remember_me' => 'Remember this session',
        'forgot_password' => 'Forgot password?',
        'sign_in' => 'Sign In',
        'create_account' => 'Create Workspace',
        'demo_fill' => 'Demo Credentials',
        'have_account' => 'Already have a workspace? Sign in',
        'no_account' => "Don't have a workspace yet? Create one",
        'showcase_badge' => 'Enterprise Ready',
        'showcase_title' => 'Intelligent Shelf-Life Optimization',
        'showcase_desc' => 'Join thousands of logistics hubs, pharmacies, and food retail operators maintaining zero-waste inventory.',
    ],

    // Navigation & Layout
    'nav' => [
        'dashboard' => 'Dashboard',
        'inventory' => 'Inventory',
        'expiry_alerts' => 'Expiry Alerts',
        'recognition' => 'Image Recognition',
        'users' => 'Users & Permissions',
        'batches' => 'Batches & Lots',
        'reports' => 'Reports',
        'settings' => 'Settings',
        'logout' => 'Sign Out',
        'profile' => 'Workspace Profile',
    ],

    // Product Attributes & Labels
    'product' => [
        'singular' => 'Product',
        'plural' => 'Products',
        'name' => 'Product Name',
        'sku' => 'SKU',
        'batch_number' => 'Batch / Lot Number',
        'quantity' => 'Current Stock',
        'manufacture_date' => 'Manufacture Date',
        'expiry_date' => 'Expiry Date',
        'safety_stock' => 'Safety Stock Level',
        'status' => 'Status',
        'image' => 'Product Image',
        'extraction_metadata' => 'OCR Metadata',
        'actions' => 'Actions',
    ],

    // User Management & RBAC
    'user' => [
        'singular' => 'User',
        'plural' => 'Users',
        'title' => 'User Management & Permissions',
        'subtitle' => 'Manage workspace team members, roles, and granular access rights',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'role' => 'Assigned Role',
        'status' => 'Status',
        'permissions' => 'Permissions',
        'password' => 'Password',
        'password_hint' => 'Leave blank to keep existing password when editing',
        'active' => 'Active',
        'inactive' => 'Deactivated',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'role_admin' => 'Administrator',
        'role_manager' => 'Inventory Manager',
        'role_operator' => 'Stock Operator',
        'perm_inventory' => 'Manage Inventory (CRUD)',
        'perm_users' => 'Manage Users & Permissions',
        'perm_reports' => 'View & Export Reports',
        'perm_scan' => 'Product Recognition & Scanner',
    ],

    // Expiry Status Indicators
    'expiry' => [
        'status_expired' => 'Expired',
        'status_expiring_soon' => 'Expiring in < 30 Days',
        'status_safe' => 'Optimal / Safe',
        'days_remaining' => ':count days remaining',
        'expired_ago' => 'Expired :count days ago',
        'expires_today' => 'Expires Today',
        'alert_banner' => 'You have :count products requiring immediate attention.',
    ],

    // Automated Product Recognition (OCR / Vision / Camera)
    'recognition' => [
        'title' => 'Product Recognition Engine',
        'subtitle' => 'Capture label using your device camera or upload an image file',
        'upload_label' => 'Upload or snap product label photo',
        'scan_button' => 'Analyze Label',
        'analyzing' => 'Processing label data...',
        'confidence' => 'Extraction Confidence',
        'auto_fill' => 'Apply Extracted Details',
        're_scan' => 'Rescan Image',
        'camera_mode' => 'Live Camera',
        'upload_mode' => 'File Upload',
        'start_camera' => 'Open Camera',
        'stop_camera' => 'Close Camera',
        'capture_photo' => 'Capture Frame',
        'camera_notice' => 'Align product label within the scanner frame',
    ],

    // Common Actions & Buttons
    'actions' => [
        'create' => 'Add New Product',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save Changes',
        'update' => 'Update Changes',
        'cancel' => 'Cancel',
        'search' => 'Search by name, SKU, or batch...',
        'filter' => 'Filter',
        'export' => 'Export CSV',
        'confirm_delete' => 'Are you sure you want to delete this product?',
        'confirm_delete_user' => 'Are you sure you want to delete this user?',
        'new_user' => 'Add Team Member',
        'edit_user' => 'Edit User',
    ],

    // Flash Messages & Feedback
    'messages' => [
        'product_created' => 'Product successfully added to inventory.',
        'product_updated' => 'Product details successfully updated.',
        'product_deleted' => 'Product removed from stock records.',
        'user_created' => 'User account created and permissions assigned.',
        'user_updated' => 'User account details updated successfully.',
        'user_deleted' => 'User account permanently removed.',
        'user_status_toggled' => 'User active status updated.',
        'login_success' => 'Welcome back to your workspace.',
        'register_success' => 'Workspace created successfully.',
    ],
];
