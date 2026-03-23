<?php
/**
 * DevCore — config.php
 * Copy this file to devcore/config.php (one level above the project folders).
 * Fill in your values. NEVER commit this file — add it to .gitignore.
 *
 * Used by: restaurant-qr-ordering and any other DevCore projects in the suite.
 */
return [
    // ── Database ────────────────────────────────────────────────
    'db_host' => 'localhost',
    'db_name' => 'restaurant_qr',
    'db_user' => 'root',
    'db_pass' => '',

    // ── App ─────────────────────────────────────────────────────
    'app_name' => 'Restaurant QR',
    'app_url'  => 'http://localhost',
    'debug'    => true,            // set false in production

    // API bearer token (used by Api::requireAuth() if needed)
    'api_secret' => 'change-this-to-a-random-secret',

    // ── Storage ─────────────────────────────────────────────────
    // Controls where uploaded menu-item images are stored.
    // Switch provider by changing ONE value: 'local' | 's3' | 'r2'
    //
    // The menu-admin API will call Storage::uploadFile() automatically
    // when a file is attached in the Add/Edit Item form.
    'storage' => [

        'driver' => 'local',   // ← change this to swap provider

        // ── Local filesystem (default, works out of the box) ────
        // Images are saved under devcore/uploads/ and served at /uploads/
        'local' => [
            'root'     => __DIR__ . '/uploads',         // absolute path on disk
            'base_url' => 'http://localhost/uploads',   // public URL prefix
        ],

        // ── AWS S3 ───────────────────────────────────────────────
        // 1. Create an S3 bucket with public-read ACL (or a CloudFront CDN).
        // 2. Create an IAM user with s3:PutObject + s3:DeleteObject on the bucket.
        // 3. Fill in the keys below and change driver to 's3'.
        's3' => [
            'key'      => '',               // IAM Access Key ID
            'secret'   => '',               // IAM Secret Access Key
            'bucket'   => '',               // e.g. my-restaurant-images
            'region'   => 'us-east-1',      // e.g. eu-west-2
            'base_url' => '',               // optional CDN: https://cdn.example.com
                                            // leave blank to use default S3 URL
            'acl'      => 'public-read',
        ],

        // ── Cloudflare R2 ────────────────────────────────────────
        // 1. Create an R2 bucket and enable "Public Access" (or custom domain).
        // 2. Create an R2 API token with Object Read & Write.
        // 3. Fill in the fields below and change driver to 'r2'.
        'r2' => [
            'account_id' => '',             // Cloudflare Account ID
            'key'        => '',             // R2 Access Key ID
            'secret'     => '',             // R2 Secret Access Key
            'bucket'     => '',             // e.g. restaurant-images
            'base_url'   => '',             // e.g. https://pub-xxxx.r2.dev
                                            //  or  https://images.myrestaurant.com
        ],
    ],
];
