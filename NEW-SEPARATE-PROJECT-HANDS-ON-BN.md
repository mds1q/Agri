# আলাদা Android App ও Admin Panel তৈরির হাতে-কলমে নির্দেশনা

এই document অনুসরণ করে বর্তমান Secure Web Portal থেকে সম্পূর্ণ আলাদা একটি নতুন App ও Admin Panel তৈরি করুন। নতুন project-এর App, package, Android release key, config signing key, Admin URL, config, Firebase/OneSignal এবং credentials পুরোনো project থেকে আলাদা থাকবে।

পুরোনো `private/`, `storage/`, `release-signing-key.p12`, `release-signing.properties`, Firebase service account এবং পুরোনো config নতুন project-এ কপি করবেন না।

## ১. কী কী আলাদা হবে

নতুন project-এ আলাদা করে তৈরি হবে:

- Android project folder
- `applicationId` ও package identity
- Android release keystore
- Remote config RSA signing private/public key
- Admin Panel installation ও admin password
- `portal-config.json` ও `.sig`
- Admin API URL
- Firebase/OneSignal app/project ও server credential

দুইটি key-এর কাজ আলাদা:

| Key | কাজ | কোথায় থাকবে |
|---|---|---|
| Android release key | APK install/update signing | Developer computer-এর private backup |
| Config signing RSA key | Remote JSON-এর signature | Private Admin Panel folder; public key App-এ |

## ২. প্রয়োজনীয় software পরীক্ষা

```sh
java -version
gradle --version
php -v
openssl version
```

Admin Panel-এর জন্য PHP 8.1 বা নতুন, OpenSSL এবং fileinfo extension প্রয়োজন। APK build-এর জন্য Android Studio/SDK এবং project-এর compile SDK প্রয়োজন।

## ৩. App code আলাদা folder-এ নিন

বর্তমান clean app code ZIP ব্যবহার করুন:

```text
SecureWebPortal-app-code-full-20261026.zip
```

Extract করে নতুন folder তৈরি করুন:

```sh
mkdir -p /workspace/new-portal
```

Android Studio-তে `/workspace/new-portal` খুলুন। Build cache, APK output এবং পুরোনো local signing files নতুন project-এ কপি করবেন না।

## ৪. নতুন package identity সেট করুন

`app/build.gradle.kts`-এ নতুন package বসান:

```kotlin
android {
    namespace = "com.example.newportal"

    defaultConfig {
        applicationId = "com.example.newportal"
        versionCode = 1
        versionName = "1.0.0"
    }
}
```

`com.example.newportal`-এর জায়গায় আপনার নিজের unique package ব্যবহার করুন। Android Studio-এর `Refactor > Rename` দিয়ে Kotlin package-ও rename করতে পারেন। Rename করলে এগুলো পরীক্ষা করুন:

- Kotlin files-এর `package` line
- `AndroidManifest.xml`-এর activity/service reference
- Firebase/OneSignal application ID
- Any deep link বা package-specific configuration

## ৫. নতুন Android release key তৈরি করুন

এই key APK install/update-এর জন্য। এটি config signing key-এর সঙ্গে মেশাবেন না।

```sh
mkdir -p /workspace/new-portal-secrets/android

keytool -genkeypair \
  -v \
  -keystore /workspace/new-portal-secrets/android/new-release-key.jks \
  -alias newportal \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

`/workspace/new-portal-secrets/android/release-signing.properties` তৈরি করুন:

```properties
storeFile=/workspace/new-portal-secrets/android/new-release-key.jks
storePassword=YOUR_STORE_PASSWORD
keyAlias=newportal
keyPassword=YOUR_KEY_PASSWORD
```

এই file, keystore এবং password Git, public ZIP বা hosting-এ upload করবেন না। `app/build.gradle.kts` যদি project root-এর `release-signing.properties` পড়ে, build করার সময় private copy root-এ রাখুন অথবা Gradle signing configuration অনুযায়ী path ঠিক করুন।

## ৬. নতুন config signing RSA key তৈরি করুন

```sh
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

Private key কেবল নতুন Admin Panel-এর `private/` folder-এ থাকবে। এটি APK, public web folder, Git বা public download-এ রাখবেন না।

## ৭. Public key App-এ বসান

Android resource-এর জন্য public key Base64 DER format-এ তৈরি করুন:

```sh
openssl pkey \
  -in /workspace/new-portal-secrets/config/config-signing-private.pem \
  -pubout -outform DER \
  | base64 -w 0 \
  > /workspace/new-portal-secrets/config/public-key-base64.txt
```

ফাইলটির এক লাইনের output কপি করে বসান:

```text
app/src/main/res/values/strings.xml
```

```xml
<string name="config_signing_public_key">NEW_BASE64_PUBLIC_KEY</string>
```

`BEGIN PUBLIC KEY` header বা line break বসাবেন না।

## ৮. নতুন portal config তৈরি করুন

বর্তমান complete config-এর copy নিয়ে নতুন project-এর config তৈরি করুন:

```sh
cp remote-config/portal-config.json \
  /workspace/new-portal-secrets/config/portal-config.json
```

অন্তত এগুলো বদলান:

```json
{
  "app_name": "নতুন অ্যাপ",
  "start_url": "https://new-domain.example/",
  "allowed_domains": [
    "new-domain.example"
  ]
}
```

এগুলোও পরীক্ষা করুন:

- Splash image URL
- Onboarding image URL
- Welcome popup image URL
- Push provider ID
- Notification click URL
- `https_only`
- `allow_external_links`
- `maintenance_mode`

JSON যাচাই করুন:

```sh
python3 -m json.tool \
  /workspace/new-portal-secrets/config/portal-config.json \
  >/dev/null && echo "JSON OK"
```

## ৯. Initial config sign করুন

নতুন App source-এর `tools/sign-config.sh` ব্যবহার করুন:

```sh
cd /workspace/new-portal

CONFIG_SIGNING_KEY=/workspace/new-portal-secrets/config/config-signing-private.pem \
  tools/sign-config.sh \
  /workspace/new-portal-secrets/config/portal-config.json
```

এতে তৈরি হবে:

```text
/workspace/new-portal-secrets/config/portal-config.json.sig
```

Signature পরীক্ষা করুন:

```sh
openssl dgst -sha256 \
  -verify /workspace/new-portal-secrets/config/config-signing-public.pem \
  -signature <(base64 -d /workspace/new-portal-secrets/config/portal-config.json.sig) \
  /workspace/new-portal-secrets/config/portal-config.json
```

ফলাফল অবশ্যই হবে:

```text
Verified OK
```

JSON পরিবর্তন করার পরে প্রতিবার নতুন `.sig` তৈরি করতে হবে।

## ১০. নতুন Admin Panel code তৈরি করুন

পুরোনো full Admin ZIP থেকে পুরোনো `private/` ও `storage/` কপি করবেন না। Code files নিয়ে নতুন structure তৈরি করুন:

```text
new-admin/
├── app/
│   └── common.php
├── public/
│   ├── admin.php
│   ├── api.php
│   ├── install.php
│   ├── .htaccess
│   └── .user.ini
├── private/
│   └── config-signing-private.pem
└── storage/
    ├── .htaccess
    ├── config-signing-public.pem
    ├── portal-config.json
    └── portal-config.json.sig
```

নতুন key/config copy করুন:

```sh
cp /workspace/new-portal-secrets/config/config-signing-private.pem \
  /workspace/new-admin/private/config-signing-private.pem

cp /workspace/new-portal-secrets/config/config-signing-public.pem \
  /workspace/new-admin/storage/config-signing-public.pem

cp /workspace/new-portal-secrets/config/portal-config.json \
  /workspace/new-admin/storage/portal-config.json

cp /workspace/new-portal-secrets/config/portal-config.json.sig \
  /workspace/new-admin/storage/portal-config.json.sig
```

Permission দিন:

```sh
chmod 700 /workspace/new-admin/private
chmod 400 /workspace/new-admin/private/config-signing-private.pem
chmod 750 /workspace/new-admin/storage
chmod 640 /workspace/new-admin/storage/portal-config.json
chmod 640 /workspace/new-admin/storage/portal-config.json.sig
chmod 644 /workspace/new-admin/storage/config-signing-public.pem
```

## ১১. Live hosting-এ Admin Panel deploy করুন

Hosting document root হিসেবে `new-admin/public` সেট করুন। `private/` ও `storage/` public document root-এর বাইরে রাখুন।

প্রয়োজনীয় server configuration:

- PHP 8.1 বা নতুন
- OpenSSL extension
- fileinfo extension
- cURL extension push send-এর জন্য
- HTTPS
- `storage/` PHP service account-এর জন্য writable

HTTPS-এ installer খুলুন:

```text
https://new-domain.example/install.php
```

Installer প্রথম access-এ `private/.install-token` তৈরি করবে। Token নিয়ে নতুন admin username ও কমপক্ষে ১৪ অক্ষরের password সেট করুন। Setup শেষ হলে খুলুন:

```text
https://new-domain.example/admin.php
```

API পরীক্ষা করুন:

```sh
curl -i 'https://new-domain.example/api.php?file=portal-config.json'
curl -i 'https://new-domain.example/api.php?file=portal-config.json.sig'
```

দুটির response HTTP 200 হতে হবে।

## ১২. App-এ নতুন Admin API URL বসান

Project root-এর `gradle.properties`-এ লিখুন:

```properties
remoteConfigUrl=https://new-domain.example/api.php?file=portal-config.json
```

অথবা Gradle command-এ দিন:

```sh
gradle :app:assembleRelease \
  -Pandroid.aapt2FromMavenOverride=/tmp/aapt2-wrapper/aapt2 \
  -PremoteConfigUrl='https://new-domain.example/api.php?file=portal-config.json'
```

Live server-এ HTTPS ব্যবহার করুন। `10.0.2.2` শুধু Android emulator-এর local server testing-এর জন্য।

## ১৩. Firebase বা OneSignal configure করুন

Push প্রয়োজন হলে নতুন project-এর provider account ব্যবহার করুন। পুরোনো project-এর credential কপি করবেন না।

Admin Panel-এর config-এ রাখবেন:

- Firebase project ID
- Firebase application ID
- Firebase API key
- Firebase sender ID
- OneSignal app ID
- Notification channel ID/name

Provider server credential Admin Panel-এর private settings-এ রাখবেন। APK বা public JSON-এ server credential রাখবেন না। Android 13 বা নতুন device-এ notification permission দিতে হবে।

## ১৪. Android Studio থেকে build করুন

1. নতুন project Android Studio-তে খুলুন।
2. Gradle Sync করুন।
3. নতুন package ID ও public key পরীক্ষা করুন।
4. `release-signing.properties` ও keystore private location থেকে project-এ configure করুন।
5. `Build > Generate Signed Bundle / APK` খুলুন।
6. `APK` এবং `release` নির্বাচন করুন।
7. নতুন keystore দিয়ে build করুন।

Update APK হলে `app/build.gradle.kts`-এ version বাড়ান:

```kotlin
versionCode = 1
versionName = "1.0.0"
```

প্রথম APK-এর পরে:

```kotlin
versionCode = 2
versionName = "1.0.1"
```

## ১৫. Final verification checklist

- [ ] নতুন package ID App ও manifest-এ ঠিক আছে
- [ ] নতুন Android release keystore নিরাপদে backup হয়েছে
- [ ] নতুন config private/public key তৈরি হয়েছে
- [ ] Android `strings.xml`-এ নতুন public key আছে
- [ ] Initial JSON `Verified OK`
- [ ] Admin API JSON HTTP 200 দিচ্ছে
- [ ] Admin API `.sig` HTTP 200 দিচ্ছে
- [ ] App-এ live HTTPS `remoteConfigUrl` আছে
- [ ] `start_url` ও `allowed_domains` নতুন domain-এর
- [ ] Firebase/OneSignal credentials নতুন project-এর
- [ ] Release APK নতুন key দিয়ে signed
- [ ] Admin Panel থেকে config Save করে foreground app-এ update দেখা গেছে

## গুরুত্বপূর্ণ নিয়ম

- Config JSON বদলালে JSON-এর সঙ্গে নতুন `.sig` publish করুন।
- Config signing key বদলালে App-এর public key বদলে নতুন APK release করুন।
- Android release key বদলালে পুরোনো installed app update করা যাবে না; নতুন package বা uninstall/reinstall লাগবে।
- পুরোনো App ও নতুন App-এর config URL এবং signing key আলাদা রাখুন।
- Private key, release keystore, passwords ও service account public hosting বা public ZIP-এ রাখবেন না।
