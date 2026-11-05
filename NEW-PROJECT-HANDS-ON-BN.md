# হাতে-কলমে নতুন Project তৈরির Runbook

এই document-এ একটি আলাদা Android Web Portal project তৈরি করার কাজ বাস্তবে করার ক্রম দেওয়া আছে। উদাহরণে ব্যবহার করা নামগুলো নিজের project অনুযায়ী বদলাবেন। এখানে Linux/macOS terminal-এর command দেওয়া হয়েছে; Windows-এ Android Studio ও PowerShell ব্যবহার করলে একই file operation File Explorer দিয়েও করা যাবে।

এই runbook স্থায়ী reference হিসেবে রাখা হয়েছে। Source, package, key, Admin Panel, API, Firebase বা hosting workflow বদলালে সংশ্লিষ্ট ধাপ, command এবং expected output update করতে হবে। পরিবর্তনের record `chat-history/WORKLOG.md`-এ রাখতে হবে।

## ব্যবহার করা উদাহরণ

```text
Project name: New Portal
Package name: com.example.newportal
App domain: new-site.example
Admin domain: admin.new-site.example
Firebase project ID: new-portal-123
Admin username: newadmin
```

প্রথমে একটি আলাদা working copy তৈরি করুন। বর্তমান project-এর live `private/`, `storage/`, keystore বা password এখানে আনবেন না।

সর্বশেষ clean Android code package: [`SecureWebPortal-app-code-full-20261026.zip`](SecureWebPortal-app-code-full-20261026.zip)। cPanel/shared-hosting-এর পূর্ণ Admin package: [`SecureWebPortal-admin-control-full-20261026.zip`](SecureWebPortal-admin-control-full-20261026.zip)। Root-hosted পূর্ণ Admin package: [`SecureWebPortal-admin-control-root-full-20261026.zip`](SecureWebPortal-admin-control-root-full-20261026.zip)। পূর্ণ Admin ZIP-এ current `private/` ও `storage/` আছে এবং এটি শুধু নিরাপদ local use-এর জন্য। Fresh setup-এ নতুন key/config তৈরির জন্য code-only template [`SecureWebPortal-admin-code-latest-20261026.zip`](SecureWebPortal-admin-code-latest-20261026.zip) ব্যবহার করুন।


## ধাপ ১ — প্রয়োজনীয় software প্রস্তুত করুন

### কাজ করুন

নিচের command চালান:

```bash
java -version
./gradlew --version
php -v
openssl version
keytool -help >/dev/null && echo "keytool OK"
```

### যে ফলাফল দেখতে হবে

- Java 17 বা project-এর Gradle requirements অনুযায়ী Java দেখা যাবে।
- Gradle wrapper version দেখা যাবে।
- PHP 8.1 বা নতুন version দেখা যাবে।
- OpenSSL এবং `keytool` কাজ করবে।

PHP hosting-এ OpenSSL ও fileinfo extension আছে কি না নিশ্চিত করুন। এটি local terminal-এ নয়, hosting-এর PHP configuration-এ যাচাই করতে হবে।

কোনো command না চললে পরের ধাপে যাবেন না।

## ধাপ ২ — নতুন source working copy তৈরি করুন

### কাজ করুন

বর্তমান source-এর parent directory-তে গিয়ে আলাদা copy তৈরি করুন:

```bash
cp -a /workspace/agri /workspace/new-portal
cd /workspace/new-portal
```

তারপর নতুন copy-তে পুরোনো generated output চিহ্নিত করুন:

```bash
pwd
rg -n "app\.agriwealth\.portal|104\.243\.34\.108|agri-wealth\.bugfinder\.app" app README.md
```

### যে ফলাফল দেখতে হবে

`pwd`-এ `/workspace/new-portal` দেখা যাবে। Search result-এ পুরোনো package ও URL দেখা যাবে; এগুলো পরের ধাপে বদলানো হবে।

পুরোনো project-এর `private/`, `storage/`, `release-signing-key.p12`, `release-signing.properties` নতুন project-এ ব্যবহার করবেন না। Existing source copy-তে এগুলো থাকলে নতুন secret দিয়ে replace করার আগে public hosting-এ upload করবেন না।

## ধাপ ৩ — package name পরিবর্তন করুন

### কাজ করুন

Android Studio-তে `/workspace/new-portal` খুলুন। Project view থেকে পুরোনো Java/Kotlin package folder-এ right click করে:

```text
Refactor > Rename
```

পুরোনো package ধাপে ধাপে নতুন package করুন:

```text
app.agriwealth.portal  ->  com.example.newportal
```

তারপর [`app/build.gradle.kts`](/workspace/agri/app/build.gradle.kts)-এর মতো অংশে লিখুন:

```kotlin
android {
    namespace = "com.example.newportal"

    defaultConfig {
        applicationId = "com.example.newportal"
    }
}
```

### যাচাই করুন

```bash
rg -n "app\.agriwealth\.portal|app\.agriwealth" app settings.gradle.kts build.gradle.kts
```

কোনো output না থাকাই ভালো। যদি পুরোনো package পাওয়া যায়, Android Studio-তে সেই file খুলে package declaration ঠিক করুন।

## ধাপ ৪ — Android release keystore তৈরি করুন

### কাজ করুন

Secret রাখার জন্য project-এর বাইরে directory তৈরি করুন:

```bash
mkdir -p /workspace/new-portal-secrets
cd /workspace/new-portal
```

Keystore তৈরি করুন:

```bash
keytool -genkeypair -v \
  -keystore /workspace/new-portal-secrets/new-release-key.jks \
  -alias newportal \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

প্রশ্নের উত্তর দেওয়ার সময়:

- keystore password লিখুন
- key password লিখুন
- alias হিসেবে `newportal` রাখুন
- নিজের নাম ও organization দিন

`release-signing.properties` তৈরি করুন:

```properties
storeFile=/workspace/new-portal-secrets/new-release-key.jks
storePassword=YOUR_STORE_PASSWORD
keyAlias=newportal
keyPassword=YOUR_KEY_PASSWORD
```

### যাচাই করুন

```bash
keytool -list \
  -keystore /workspace/new-portal-secrets/new-release-key.jks \
  -alias newportal
```

Alias ও certificate information দেখা গেলে key তৈরি হয়েছে। Keystore password ভুলে গেলে পরে release update করা যাবে না।

## ধাপ ৫ — Config signing key তৈরি করুন

### কাজ করুন

```bash
mkdir -p /workspace/new-portal-secrets/config

openssl genpkey \
  -algorithm RSA \
  -pkeyopt rsa_keygen_bits:2048 \
  -out /workspace/new-portal-secrets/config/config-signing-private.pem

openssl pkey \
  -in /workspace/new-portal-secrets/config/config-signing-private.pem \
  -pubout \
  -out /workspace/new-portal-secrets/config/config-signing-public.pem
```

### যাচাই করুন

```bash
openssl pkey \
  -in /workspace/new-portal-secrets/config/config-signing-private.pem \
  -text -noout | head

openssl pkey \
  -pubin \
  -in /workspace/new-portal-secrets/config/config-signing-public.pem \
  -text -noout | head
```

দুই command-ই key information দেখাবে। Private key file অন্য কাউকে পাঠাবেন না।

## ধাপ ৬ — Public key Android app-এ বসান

### কাজ করুন

Public key-কে Android-এর প্রয়োজনীয় Base64 DER format-এ বানান:

```bash
openssl pkey \
  -in /workspace/new-portal-secrets/config/config-signing-private.pem \
  -pubout -outform DER | base64 -w 0 > /workspace/new-portal-secrets/config/public-key-base64.txt
```

ফাইলটি খুলুন:

```bash
sed -n '1p' /workspace/new-portal-secrets/config/public-key-base64.txt
```

Output কপি করে নতুন project-এর:

```text
app/src/main/res/values/strings.xml
```

এ বসান:

```xml
<string name="config_signing_public_key">OUTPUT_FROM_PUBLIC_KEY_BASE64</string>
```

### যাচাই করুন

নতুন value-তে `BEGIN PUBLIC KEY` বা line break থাকবে না। এটি PEM নয়, এক লাইনের Base64 string হবে।

## ধাপ ৭ — নতুন portal config তৈরি করুন

### কাজ করুন

বর্তমান complete config কপি করুন:

```bash
cp remote-config/portal-config.json /workspace/new-portal-secrets/config/portal-config.json
```

Editor-এ খুলে অন্তত এগুলো বদলান:

```json
{
  "app_name": "New Portal",
  "start_url": "https://new-site.example/",
  "allowed_domains": [
    "new-site.example"
  ]
}
```

পুরোনো splash image URL, allowed domain, Firebase ID ও click URL থাকলে সব নতুন domain-এ বদলান। JSON যাচাই করুন:

```bash
python3 -m json.tool \
  /workspace/new-portal-secrets/config/portal-config.json >/dev/null \
  && echo "JSON OK"
```

`JSON OK` না এলে কমা, quote বা bracket ঠিক করুন।

## ধাপ ৮ — Config sign ও verify করুন

### কাজ করুন

নতুন project root থেকে sign করুন:

```bash
cd /workspace/new-portal

CONFIG_SIGNING_KEY=/workspace/new-portal-secrets/config/config-signing-private.pem \
  tools/sign-config.sh /workspace/new-portal-secrets/config/portal-config.json
```

এতে তৈরি হবে:

```text
/workspace/new-portal-secrets/config/portal-config.json.sig
```

### যাচাই করুন

```bash
openssl dgst -sha256 \
  -verify /workspace/new-portal-secrets/config/config-signing-public.pem \
  -signature <(base64 -d /workspace/new-portal-secrets/config/portal-config.json.sig) \
  /workspace/new-portal-secrets/config/portal-config.json
```

ফলাফল অবশ্যই হবে:

```text
Verified OK
```

## ধাপ ৯ — Fresh Admin Panel directory বানান

### কাজ করুন

শুধু panel-এর code অংশ নতুন directory-তে নিন:

```bash
mkdir -p /workspace/new-admin-panel
cp -a /workspace/agri/php-control-panel/app /workspace/new-admin-panel/
cp -a /workspace/agri/php-control-panel/public /workspace/new-admin-panel/
cp -a /workspace/agri/php-control-panel/install.php /workspace/new-admin-panel/
cp -a /workspace/agri/php-control-panel/README* /workspace/new-admin-panel/
```

Fresh private/storage directory বানান:

```bash
mkdir -p /workspace/new-admin-panel/private
mkdir -p /workspace/new-admin-panel/storage
```

নতুন key ও initial config বসান:

```bash
cp /workspace/new-portal-secrets/config/config-signing-private.pem \
  /workspace/new-admin-panel/private/config-signing-private.pem

cp /workspace/new-portal-secrets/config/config-signing-public.pem \
  /workspace/new-admin-panel/storage/config-signing-public.pem

cp /workspace/new-portal-secrets/config/portal-config.json \
  /workspace/new-admin-panel/storage/portal-config.json

cp /workspace/new-portal-secrets/config/portal-config.json.sig \
  /workspace/new-admin-panel/storage/portal-config.json.sig
```

Permissions দিন:

```bash
chmod 700 /workspace/new-admin-panel/private
chmod 600 /workspace/new-admin-panel/private/config-signing-private.pem
chmod 750 /workspace/new-admin-panel/storage
chmod 640 /workspace/new-admin-panel/storage/portal-config.json
chmod 640 /workspace/new-admin-panel/storage/portal-config.json.sig
chmod 644 /workspace/new-admin-panel/storage/config-signing-public.pem
```

### যাচাই করুন

```bash
find /workspace/new-admin-panel -maxdepth 2 -type f | sort
```

`private/` ও `storage/`-এ নতুন project-এর file আছে কি না দেখুন। পুরোনো admin login বা পুরোনো image এই directory-তে থাকা উচিত নয়।

## ধাপ ১০ — Local Admin Panel চালিয়ে পরীক্ষা করুন

### কাজ করুন

Local PHP test-এর জন্য:

```bash
cd /workspace/new-admin-panel
AGRI_ALLOW_HTTP_ADMIN=1 php -S 127.0.0.1:8080 -t public
```

Browser-এ খুলুন:

```text
http://127.0.0.1:8080/install.php
```

Installer token দেখতে অন্য terminal-এ চালান:

```bash
cat /workspace/new-admin-panel/private/.install-token
```

Token, username এবং কমপক্ষে ১৪ অক্ষরের password দিয়ে setup করুন। তারপর খুলুন:

```text
http://127.0.0.1:8080/admin.php
```

### যে ফলাফল দেখতে হবে

- Login page দেখা যাবে।
- নতুন username/password দিয়ে login হবে।
- Config form-এ নতুন app name ও URL দেখা যাবে।
- Save করলে নতুন JSON ও signature update হবে।

Browser form থেকে Save করার পর signature যাচাই করুন:

```bash
openssl dgst -sha256 \
  -verify /workspace/new-admin-panel/storage/config-signing-public.pem \
  -signature <(base64 -d /workspace/new-admin-panel/storage/portal-config.json.sig) \
  /workspace/new-admin-panel/storage/portal-config.json
```

`Verified OK` না হলে panel install থামিয়ে key path, permissions এবং file contents পরীক্ষা করুন।

## ধাপ ১১ — Hosting-এ Admin Panel publish করুন

### document root সেট করা গেলে

Hosting document root হবে:

```text
/path/to/new-admin-panel/public
```

`private/`, `storage/` এবং `app/` public document root-এর বাইরে থাকবে। HTTPS চালু করুন। Browser থেকে:

```text
https://admin.new-site.example/install.php
```

Installer token নিয়ে setup করুন। তারপর API test করুন:

```bash
curl -i 'https://admin.new-site.example/api.php?file=portal-config.json'
curl -i 'https://admin.new-site.example/api.php?file=portal-config.json.sig'
curl -i 'https://admin.new-site.example/api.php?file=portal-config.version'
```

তিনটি response-এ HTTP 200 পেতে হবে।

### document root পরিবর্তন করা না গেলে

এই repository-এর `admin-panel-root/` package layout ব্যবহার করুন। `admin.php`, `api.php`, `install.php`, `app/`, `private/` ও `storage/` root অনুযায়ী বসান। Root deployment-এর `.htaccess` অক্ষত রাখুন।

## ধাপ ১২ — APK build করুন

একই package-এর পরবর্তী update install করার জন্য build-এর আগে `app/build.gradle.kts`-এ আগের release-এর চেয়ে বড় `versionCode` দিন এবং `versionName` আপডেট করুন। একই release keystore ও app configuration ব্যবহার করুন।


### কাজ করুন

নতুন API URL দিয়ে build করুন:

```bash
cd /workspace/new-portal

./gradlew assembleRelease \
  -PremoteConfigUrl='https://admin.new-site.example/api.php?file=portal-config.json'
```

এই environment-এ AAPT2 override প্রয়োজন হলে ব্যবহার করুন:

```bash
./gradlew assembleRelease \
  -Pandroid.aapt2FromMavenOverride=/workspace/agri/tools/aapt2 \
  -PremoteConfigUrl='https://admin.new-site.example/api.php?file=portal-config.json'
```

APK পাবেন:

```text
app/build/outputs/apk/release/app-release.apk
```

### যাচাই করুন

```bash
apkanalyzer manifest application-id \
  app/build/outputs/apk/release/app-release.apk
```

ফলাফল `com.example.newportal` হওয়া উচিত। APK-তে নতুন API URL আছে কি না দেখতে:

```bash
unzip -p app/build/outputs/apk/release/app-release.apk \
  resources.arsc >/dev/null && echo "APK readable"
```

তারপর Android device-এ install করে app খুলুন।

## ধাপ ১৩ — Admin update হাতে পরীক্ষা করুন

1. Admin Panel-এ `app_name` পরিবর্তন করুন।
2. Save চাপুন।
3. API থেকে JSON download করুন।
4. `.sig` download করুন।
5. Signature verify করুন।
6. App বন্ধ করে আবার foreground-এ আনুন।
7. নতুন app name বা config দেখা যাচ্ছে কি না দেখুন।

App update না হলে এই ক্রমে পরীক্ষা করুন:

```text
API URL ঠিক আছে কি না
portal-config.json HTTP 200 দিচ্ছে কি না
portal-config.json.sig HTTP 200 দিচ্ছে কি না
version endpoint HTTP 200 দিচ্ছে কি না
App-এর public key এবং server private key একই pair কি না
JSON save-এর পরে signature নতুন হয়েছে কি না
```

## ধাপ ১৪ — Firebase push হাতে পরীক্ষা করুন

Push দরকার হলে:

1. Firebase Console-এ নতুন project তৈরি করুন।
2. Android app হিসেবে `com.example.newportal` register করুন।
3. Firebase project ID, application ID, API key এবং sender ID নিন।
4. Admin Panel-এর push settings-এ public values বসান।
5. Service account JSON শুধু `private/push-credentials.php`-এ রাখুন।
6. Config-এ provider `firebase` এবং `enabled: true` করুন।
7. Admin Panel থেকে test notification পাঠান।
8. Physical device-এ notification ও tap behavior দেখুন।

Private Firebase JSON কখনো `public/`, `storage/`, APK বা Git-এ রাখবেন না।

## কাজ শেষ হওয়ার প্রমাণ

এই পাঁচটি ফলাফল পাওয়া গেলে setup সম্পূর্ণ হয়েছে:

```text
1. keytool keystore list সফল
2. config signature: Verified OK
3. Admin API: JSON, .sig, .version — HTTP 200
4. APK applicationId নতুন package name
5. Device-এ app নতুন domain খুলছে এবং Admin Panel update ধরছে
```

