# নতুন Android Web Portal Project তৈরির পূর্ণ নির্দেশনা

এই document ব্যবহার করে বর্তমান Secure Web Portal source থেকে আলাদা app তৈরি করা যাবে। এতে নতুন package name, Android release key, remote-config signing key, Admin Panel, domain এবং optional Firebase project ব্যবহার করা যাবে।

প্রতিটি command চালিয়ে হাতে-কলমে কাজ করতে চাইলে [`NEW-PROJECT-HANDS-ON-BN.md`](NEW-PROJECT-HANDS-ON-BN.md) ব্যবহার করুন। এই document-এ মূল নিয়ম ও reference রাখা হয়েছে।

এই project-এর source, package, key, Admin Panel বা deployment workflow বদলালে এই document এবং hands-on guide একই কাজের মধ্যে update করতে হবে। কী বদলেছে ও কীভাবে যাচাই হয়েছে তা [`chat-history/WORKLOG.md`](chat-history/WORKLOG.md)-এ লিখতে হবে।

## ১. কোন key কোন কাজে ব্যবহার হয়

এই project-এ দুটি আলাদা signing system আছে:

| উপকরণ | কাজ | কোথায় থাকে |
|---|---|---|
| Android release keystore | APK install/update করার অনুমতি | developer-এর private location |
| Config signing RSA key | Admin Panel-এর JSON update যাচাই | private key server-এর public root-এর বাইরে |

দুই key একসঙ্গে মেশাবেন না। Config private key হারালে নতুন public key দিয়ে নতুন APK release করতে হবে। Android release keystore হারালে একই installed app-এর update দেওয়া যাবে না।

## ২. Source copy তৈরি

বর্তমান project-এর একটি আলাদা working copy নিন। পুরোনো live project-এর `private/`, `storage/`, release keystore বা password নতুন project-এ ব্যবহার করবেন না।

সর্বশেষ clean Android code ZIP হলো [`SecureWebPortal-app-code-full-20261026.zip`](SecureWebPortal-app-code-full-20261026.zip)। cPanel/shared-hosting-এর পূর্ণ Admin Panel package হলো [`SecureWebPortal-admin-control-full-20261026.zip`](SecureWebPortal-admin-control-full-20261026.zip); সরাসরি document-root deployment-এর পূর্ণ variant হলো [`SecureWebPortal-admin-control-root-full-20261026.zip`](SecureWebPortal-admin-control-root-full-20261026.zip)। পূর্ণ Admin ZIP-এ বর্তমান `private/` signing key ও `storage/` state আছে, তাই এগুলো শুধু নিজের নিরাপদ local copy হিসেবে ব্যবহার করুন। নতুন project-এর জন্য fresh key/config তৈরি করতে code-only template [`SecureWebPortal-admin-code-latest-20261026.zip`](SecureWebPortal-admin-code-latest-20261026.zip) ব্যবহার করুন।

নতুন project-এর জন্য সাধারণত যেসব source অংশ প্রয়োজন:

```text
app/
gradle/
build.gradle.kts
settings.gradle.kts
gradle.properties
gradlew
gradlew.bat
tools/
```

Admin Panel template হিসেবে `php-control-panel/` ব্যবহার করুন। Root hosting হলে `admin-panel-root/`-এর layout ব্যবহার করুন।

## ৩. নতুন package name সেট করা

`app/build.gradle.kts`-এ `namespace` এবং `applicationId` পরিবর্তন করুন:

```kotlin
android {
    namespace = "com.example.newportal"

    defaultConfig {
        applicationId = "com.example.newportal"
    }
}
```

পুরোনো package path-ও নতুন নাম অনুযায়ী rename করুন:

```text
app/src/main/java/app/agriwealth/portal/
```

Android Studio-এর **Refactor > Rename** ব্যবহার করা নিরাপদ। Source-এর `package app.agriwealth.portal` declaration-গুলোও নতুন package name-এ পরিবর্তন করুন।

তারপর search করে পুরোনো package name বাকি আছে কি না দেখুন:

```bash
rg -n "app\.agriwealth\.portal|app\.agriwealth" app settings.gradle.kts build.gradle.kts
```

## ৪. নতুন Android release keystore তৈরি

Developer computer-এ চালান:

```bash
keytool -genkeypair -v \
  -keystore new-release-key.jks \
  -alias newportal \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

Root project-এ `release-signing.properties` তৈরি করুন:

```properties
storeFile=new-release-key.jks
storePassword=YOUR_STORE_PASSWORD
keyAlias=newportal
keyPassword=YOUR_KEY_PASSWORD
```

`new-release-key.jks` এবং `release-signing.properties` Git বা public hosting-এ upload করবেন না।

## ৫. নতুন config signing RSA key তৈরি

Developer computer-এ চালান:

```bash
mkdir -p new-project-secrets

openssl genpkey \
  -algorithm RSA \
  -pkeyopt rsa_keygen_bits:2048 \
  -out new-project-secrets/config-signing-private.pem

openssl pkey \
  -in new-project-secrets/config-signing-private.pem \
  -pubout \
  -out new-project-secrets/config-signing-public.pem
```

Private key শুধু Admin Panel-এর `private/` folder-এ রাখবেন। এটি `public/`, GitHub, APK বা ZIP download-এর public অংশে রাখবেন না।

Android app-এর জন্য public key-কে Base64 DER string বানান:

```bash
openssl pkey \
  -in new-project-secrets/config-signing-private.pem \
  -pubout \
  -outform DER | base64 -w 0
```

Output কপি করে `app/src/main/res/values/strings.xml`-এর এই value-তে বসান:

```xml
<string name="config_signing_public_key">NEW_BASE64_PUBLIC_KEY</string>
```

PEM header (`-----BEGIN PUBLIC KEY-----`) বসাবেন না।

## ৬. Initial portal config তৈরি

`remote-config/portal-config.json` কপি করে নতুন project-এর নামে একটি আলাদা JSON তৈরি করুন। অন্তত এগুলো পরিবর্তন করুন:

```json
{
  "app_name": "নতুন অ্যাপ",
  "start_url": "https://new-site.example/",
  "allowed_domains": [
    "new-site.example"
  ]
}
```

সম্পূর্ণ config-এ splash, onboarding, maintenance, security, popup এবং push sections রাখুন। Admin Panel unknown fields preserve করে, তাই existing complete JSON ব্যবহার করা সহজ।

HTTPS domain ব্যবহার করুন। `start_url`, image URL, click URL এবং API URL-এ domain সঠিক আছে কি না যাচাই করুন।

## ৭. Config sign করা

Project root থেকে চালান:

```bash
CONFIG_SIGNING_KEY="$PWD/new-project-secrets/config-signing-private.pem" \
  tools/sign-config.sh new-project-config.json
```

এতে একই folder-এ তৈরি হবে:

```text
new-project-config.json
new-project-config.json.sig
```

JSON পরিবর্তন করার পর প্রতিবার signature নতুন করে তৈরি করতে হবে। JSON এবং `.sig` আলাদা সময়ে বা আলাদা bytes দিয়ে upload করবেন না।

Signature পরীক্ষা করুন:

```bash
openssl dgst -sha256 \
  -verify new-project-secrets/config-signing-public.pem \
  -signature <(base64 -d new-project-config.json.sig) \
  new-project-config.json
```

ফলাফল `Verified OK` হতে হবে।

## ৮. নতুন Admin Panel তৈরি

`php-control-panel/`-এর code template ব্যবহার করুন। নতুন project-এ fresh `private/` ও `storage/` তৈরি করুন। পুরোনো live project-এর login, config, upload, push credential বা signing key কপি করবেন না।

Panel-এর private অংশে রাখুন:

```text
private/config-signing-private.pem
```

Storage-এ রাখুন:

```text
storage/config-signing-public.pem
storage/portal-config.json
storage/portal-config.json.sig
```

`public/` folder-এ সাধারণত থাকবে:

```text
public/admin.php
public/api.php
public/install.php
public/.htaccess
public/.user.ini
```

PHP 8.1 বা নতুন version, OpenSSL এবং fileinfo extension চালু থাকতে হবে। `storage/` PHP service account-এর জন্য writable হতে হবে।

## ৯. cPanel বা document-root deployment

Hosting settings-এ `php-control-panel/public`-কে document root করুন। `private/` এবং `storage/` document root-এর বাইরে রাখুন।

তারপর HTTPS-এ installer খুলুন:

```text
https://new-domain.example/install.php
```

Installer প্রথমবার `private/.install-token` তৈরি করবে। File Manager থেকে token কপি করে installer form-এ admin username ও কমপক্ষে ১৪ অক্ষরের password দিন। Setup শেষ হলে:

```text
https://new-domain.example/admin.php
```

CLI installer ব্যবহার করলে panel root থেকে চালানো যাবে:

```bash
AGRI_ADMIN_USER=newadmin \
AGRI_ADMIN_PASSWORD='USE-A-LONG-UNIQUE-PASSWORD' \
php install.php
```

## ১০. Root hosting deployment

যদি hosting-এ `public/` document root সেট করা না যায়, `admin-panel-root/` layout ব্যবহার করুন। এই layout-এ `admin.php`, `api.php`, `install.php`, `app/`, `private/` এবং `storage/` hosting root-এ থাকে।

Root deployment-এও `private/` এবং `storage/` public URL দিয়ে expose করা যাবে না। `.htaccess` অক্ষত রাখুন এবং HTTPS ব্যবহার করুন।

## ১১. Admin Panel-এর নাম ও app name

App-এর visible name Admin Panel-এর `app_name` field থেকে আসে।

Admin Panel-এর browser title বা heading বদলাতে:

```text
php-control-panel/public/admin.php
```

এর HTML title/heading পরিবর্তন করুন। Root deployment হলে `admin-panel-root/admin.php` পরিবর্তন করুন।

## ১২. নতুন Firebase push setup

Push দরকার হলে নতুন Firebase project তৈরি করে নতুন Android app-এর package name দিয়ে register করুন। Firebase থেকে নতুন project ID, application ID, API key এবং sender ID নিন। এগুলো Admin Panel-এর push settings-এ দিন।

Firebase service-account JSON এবং OneSignal API key শুধু এখানে রাখবেন:

```text
private/push-credentials.php
```

এগুলো `portal-config.json`, APK, `public/` বা Git-এ রাখবেন না।

Push বাদ দিলে config-এ রাখুন:

```json
{
  "enabled": false,
  "provider": "none"
}
```

## ১৩. App-এর remote API URL সেট করা

Admin Panel চালু হওয়ার পর API URL হবে:

```text
https://new-domain.example/api.php?file=portal-config.json
```

একই package-এর নতুন Android update প্রকাশের সময় `app/build.gradle.kts`-এ `versionCode` বাড়ান এবং `versionName` আপডেট করুন; একই release keystore ব্যবহার করুন।

এই URL দিয়ে release APK build করুন:

```bash
./gradlew assembleRelease \
  -PremoteConfigUrl='https://new-domain.example/api.php?file=portal-config.json'
```

এই app-এর জন্য প্রথম APK-তে নতুন config public key, নতুন package name এবং নতুন API URL একসঙ্গে থাকতে হবে।

## ১৪. Deployment যাচাই

API response পরীক্ষা করুন:

```bash
curl -fsS 'https://new-domain.example/api.php?file=portal-config.json'
curl -fsS 'https://new-domain.example/api.php?file=portal-config.json.sig'
curl -fsS 'https://new-domain.example/api.php?file=portal-config.version'
```

তারপর local copy নিয়ে signature যাচাই করুন। JSON এবং signature response-এর bytes পরিবর্তিত বা HTML error page নয়, তা নিশ্চিত করুন।

শেষে APK install করে যাচাই করুন:

1. App নতুন `start_url` খুলছে।
2. Splash এবং onboarding দেখা যাচ্ছে।
3. Admin Panel-এ config পরিবর্তন করে Save করলে signed config update হচ্ছে।
4. App foreground-এ সর্বোচ্চ কয়েক সেকেন্ডের মধ্যে update ধরছে।
5. Invalid signature হলে app bundled config ব্যবহার করছে।

## ১৫. Final checklist

- [ ] নতুন `namespace` এবং `applicationId` সেট করা হয়েছে
- [ ] নতুন Android release keystore backup করা হয়েছে
- [ ] নতুন config signing private/public key তৈরি হয়েছে
- [ ] Android app-এ নতুন public key বসানো হয়েছে
- [ ] নতুন `portal-config.json` তৈরি হয়েছে
- [ ] JSON signature `Verified OK`
- [ ] নতুন Admin Panel-এর fresh `private/` ও `storage/` তৈরি হয়েছে
- [ ] PHP 8.1+, OpenSSL, fileinfo চালু
- [ ] Admin Panel HTTPS-এ চলছে
- [ ] API JSON, `.sig` এবং `.version` response দিচ্ছে
- [ ] নতুন API URL দিয়ে release APK build হয়েছে
- [ ] Firebase credential থাকলে private folder-এ রাখা হয়েছে
- [ ] private key, keystore এবং password public হয়নি

## গুরুত্বপূর্ণ নিয়ম

Existing live panel update করার জন্য `php-control-panel/UPDATE-INSTRUCTIONS-BN.txt` ব্যবহার করুন। নতুন project তৈরির সময় update archive ব্যবহার করবেন না; fresh installation করুন।

`storage/` এবং `private/` backup ছাড়া মুছবেন না। কোনো key হারালে বা compromised হলে সংশ্লিষ্ট app-এর জন্য নতুন key ও নতুন APK release করুন।
