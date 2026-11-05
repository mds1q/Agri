# PHP Admin Panel — ফাইলের গঠন

এই প্যানেল থেকে পোর্টাল, splash screen, UI colors, maintenance, security policies, welcome popup-এর লেখা ও ছবি নিয়ন্ত্রণ করা যায়। Save করলে সার্ভার JSON তৈরি করে matching RSA/SHA-256 signature-ও বানায়। প্যানেল যে key সরাসরি সম্পাদনা করে না, সেগুলোও JSON save-এর সময় অক্ষত থাকে।

UI colors-এ status bar, navigation bar, progress bar, retry/refresh button ও accent-এর রং বদলানো যায়।

Push notifications-এ `FIREBASE`, `ONESIGNAL` অথবা `BOTH` provider বেছে নেওয়া যায়। Firebase client settings ও OneSignal App ID signed public config-এ যায়; OneSignal API key এবং Firebase service account JSON শুধু `private/push-credentials.php`-এ থাকে। Admin panel থেকে HTTPS image ও click URL-সহ all-subscriber notification পাঠানো যায়। Provider SDK app-এ একবার যুক্ত করার পরে message পরিবর্তনে APK update লাগে না।

## ফোল্ডার ও ফাইল

```text
php-control-panel/
├── README.md
├── README-BN.md
├── install.php                    # CLI installer; path স্বয়ংক্রিয়ভাবে শনাক্ত করে
├── app/
│   └── common.php                 # সাইন, storage lock, JSON/signature read/write
├── public/                        # ওয়েব সার্ভারের document root
│   ├── .htaccess                  # directory listing বন্ধ; Apache access rule
│   ├── .user.ini                  # ছবি upload-এর PHP size limit
│   ├── admin.php                  # লগইন ও config control form
│   ├── install.php                # browser installer; token auto-generate করে
│   └── api.php                    # অ্যাপকে JSON এবং .sig দেয়
├── private/                      # public document root-এর বাইরে; secret ফাইল
│   ├── .htaccess                  # Apache-তে সরাসরি access নিষিদ্ধ
│   └── config-signing-private.pem # config স্বাক্ষরের secret key
└── storage/                       # public document root-এর বাইরে রাখুন
    ├── .htaccess                  # Apache-তে সরাসরি access নিষিদ্ধ
    ├── config-signing-public.pem  # সিগনেচার যাচাইয়ের public key
    ├── portal-config.json         # প্রাথমিক config
    └── portal-config.json.sig     # প্রাথমিক matching signature
```

`private/config-signing-private.pem` এই ZIP-এ থাকবে এবং `public/`-এর বাইরে থাকবে। Installer panel-এর অবস্থান থেকে path নিজে বের করবে; আলাদা absolute path দিতে হবে না। ZIP-টি গোপন রাখুন—এই private key যার হাতে যাবে, সে অ্যাপের remote config-এ স্বাক্ষর করতে পারবে।

## চালু করার ধাপ

### Existing panel update

`SecureWebPortal-admin-panel-update.zip` আগে থেকে installed panel-এর জন্য তৈরি। Extract করার আগে `private/` এবং `storage/` backup নিন। Archive-টি existing panel root-এ extract করে code files overwrite করুন। এই update archive-এ `private/` এবং `storage/` নেই, তাই live signing key, admin credentials এবং current config অক্ষত থাকবে। Update-এর পরে installer চালাবেন না। বিস্তারিত ধাপ `UPDATE-INSTRUCTIONS-BN.txt`-এ আছে।

1. PHP 8.1+, OpenSSL ও fileinfo extension-সহ hosting-এ `php-control-panel/public`-কে document root করুন। `storage/` ও private key document root-এর বাইরে রাখুন।
2. HTTPS বাধ্যতামূলক; `AGRI_ALLOW_HTTP_ADMIN=1` শুধু local development-এর জন্য। PHP service-কে `storage/` ও `private/`-এ write permission দিন।
3. Admin URL: `https://আপনার-ডোমেইন/admin.php`
4. HTTPS-এ `/install.php` খুলুন। Installer নিজে একবারের random token বানিয়ে `private/.install-token`-এ রাখবে। cPanel File Manager-এ ফাইলটি খুলে token কপি করে form-এ দিন, username ও password সেট করুন। Signing key path স্বয়ংক্রিয়ভাবে নির্ধারিত হবে। Installer initial config signature ও key যাচাই করে; সফল হলে `private/config.php` তৈরি, token মুছে এবং `/install.php` lock করে।

বিকল্পভাবে CLI installer চালাতে `AGRI_ADMIN_USER` ও `AGRI_ADMIN_PASSWORD` environment variable দিন এবং panel root থেকে `php install.php` চালান। Installer-এর path detection কাজ করতে package-এর `public/`, `app/`, `storage/` ও `private/` গঠন অক্ষত রাখুন।

Admin password কমপক্ষে ১৪ অক্ষরের হতে হবে; দীর্ঘ ও অন্য কোথাও ব্যবহার না করা passphrase নিন। একই IP থেকে ১৫ মিনিটে ৫ বার ভুল লগইন হলে সেই IP ১৫ মিনিটের জন্য আটকে থাকবে। প্রচেষ্টা `storage/.login-attempts.json`-এ রাখা হয়, তাই PHP-র storage write permission বজায় রাখুন।

Splash screen-এর fallback ছবি, প্রতিটি splash page-এর ছবি এবং welcome popup-এর ছবি JPEG, PNG অথবা WebP হিসেবে upload করা যাবে; প্রতি ফাইল সর্বোচ্চ ৫ MB। Welcome popup-এর ছবি app-এ aspect ratio বজায় রেখে সর্বোচ্চ 220dp উচ্চতায় দেখানো হয়। ফর্মে আগের ছবি মুছুন বেছে Save করলে সেটি storage থেকেও মুছে যাবে। Upload করা ছবি `storage/splash-images/`-এ থাকে এবং API দিয়ে read-only পরিবেশিত হয়। Reverse proxy-তে HTTPS terminate হলে বা panel URL prefix-এর নিচে থাকলে `AGRI_PUBLIC_BASE_URL`-এ `public/` folder-এর প্রকাশ্য base URL দিন। একবারে চারটি ছবি upload করার জন্য `public/.user.ini`-তে ২৪ MB form limit সেট করা আছে।

API config ও signature ছাড়াও এভাবে প্রতিটি uploaded ছবিকে পরিবেশন করে: `api.php?file=splash-image-<random-id>.jpg`।

5. API URL: `https://আপনার-ডোমেইন/api.php?file=portal-config.json`। Android অ্যাপকে এই URL ব্যবহার করাতে এই URL দিয়ে একবার নতুন APK build করে ইনস্টল করুন। এরপর admin panel-এ save করলে অ্যাপের জন্য config ও signature একসঙ্গে আপডেট হবে।

Installer hosting provider-এর document root নিজে বদলাতে পারে না। Hosting settings-এ `php-control-panel/public`-কে document root করতে হবে। Installer শেষে যে permissions সেট করে: public/app directory `0755`, code `0644`, storage `0750`, config/signature `0640`, private key `0400`, private settings folder/file `0700`/`0600`।

বিস্তারিত deployment নির্দেশনার জন্য `README.md` দেখুন।
